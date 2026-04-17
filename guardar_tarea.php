<?php
require_once 'config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$usuario_actual = $_SESSION['usuario_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar CSRF
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de seguridad inválido.");
        }

        $id = $_POST['tarea_id'] ?? '';
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $proyecto_id = $_POST['proyecto_id'] ?? null;
        $prioridad = $_POST['prioridad'] ?? 'media';
        $estado = $_POST['estado'] ?? 'pendiente';
        $visibility = $_POST['visibility'] ?? 'public';
        $fecha_termino = !empty($_POST['fecha_termino']) ? $_POST['fecha_termino'] : null;
        $tags_string = $_POST['tags_ids_string'] ?? '';
        $usuarios_asignados = $_POST['usuarios_ids'] ?? [];

        if (empty($titulo)) throw new Exception("El título es obligatorio.");
        if (empty($proyecto_id)) throw new Exception("El proyecto es obligatorio.");

        $pdo->beginTransaction();

        // FORZAR: Si ID es 0, tratarlo como nueva tarea
        if ($id === '0' || $id === 0) {
            $id = '';
        }

        // Obtener organización_id si existe en sesión
        $organizacion_id = $_SESSION['organizacion_id'] ?? null;

        if (!empty($id) && is_numeric($id)) {
            // --- ACTUALIZAR ---
            $stmt = $pdo->prepare("UPDATE tareas SET titulo=?, descripcion=?, proyecto_id=?, prioridad=?, estado=?, visibility=?, fecha_termino=?, fecha_actualizacion=NOW() WHERE id=?");
            $stmt->execute([$titulo, $descripcion, $proyecto_id, $prioridad, $estado, $visibility, $fecha_termino, $id]);
            
            $tarea_id = $id;
            registrarAuditoria($usuario_actual, 'UPDATE', 'tareas', $tarea_id, "Actualizó la tarea '$titulo'");
        } else {
            // --- CREAR ---
            $stmt = $pdo->prepare("INSERT INTO tareas (organizacion_id, usuario_id, proyecto_id, titulo, descripcion, prioridad, estado, visibility, fecha_termino, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$organizacion_id, $usuario_actual, $proyecto_id, $titulo, $descripcion, $prioridad, $estado, $visibility, $fecha_termino]);
            $tarea_id = $pdo->lastInsertId();
            
            // Si lastInsertId devuelve 0, intentar otra forma
            if ($tarea_id == 0) {
                $stmt = $pdo->query("SELECT LAST_INSERT_ID()");
                $tarea_id = $stmt->fetchColumn();
            }
            
            // Si sigue siendo 0, obtener el máximo ID (fallback extremo)
            if ($tarea_id == 0) {
                $stmt = $pdo->query("SELECT MAX(id) as max_id FROM tareas");
                $max_id = $stmt->fetchColumn();
                $tarea_id = $max_id ?: 1;
            }
            
            registrarAuditoria($usuario_actual, 'INSERT', 'tareas', $tarea_id, "Creó la tarea '$titulo'");
        }

        // --- ETIQUETAS ---
        $pdo->prepare("DELETE FROM tarea_etiquetas WHERE tarea_id = ?")->execute([$tarea_id]);
        if (!empty($tags_string)) {
            $tags_ids = explode(',', $tags_string);
            $stmt_tag = $pdo->prepare("INSERT INTO tarea_etiquetas (tarea_id, etiqueta_id) VALUES (?, ?)");
            foreach ($tags_ids as $tag_id) {
                if(is_numeric($tag_id) && $tag_id > 0) {
                    $stmt_tag->execute([$tarea_id, $tag_id]);
                }
            }
        }

        // --- ASIGNACIONES ---
        $pdo->prepare("DELETE FROM tarea_asignaciones WHERE tarea_id = ?")->execute([$tarea_id]);
        if (!empty($usuarios_asignados)) {
            $stmt_assign = $pdo->prepare("INSERT INTO tarea_asignaciones (tarea_id, usuario_id) VALUES (?, ?)");
            $stmt_notif = $pdo->prepare("INSERT INTO notificaciones (usuario_id_destino, usuario_id_origen, tarea_id, tipo, mensaje) VALUES (?, ?, ?, 'asignacion', ?)");
            
            foreach ($usuarios_asignados as $uid) {
                if(is_numeric($uid) && $uid > 0) {
                    $stmt_assign->execute([$tarea_id, $uid]);
                    
                    // Notificar si no es el mismo usuario
                    if ($uid != $usuario_actual) {
                        $mensaje = ($_SESSION['usuario_nombre'] ?? 'Usuario') . " te ha asignado la tarea: $titulo";
                        $stmt_notif->execute([$uid, $usuario_actual, $tarea_id, $mensaje]);
                    }
                }
            }
        }

        // --- ARCHIVOS ---
        if (!empty($_FILES['archivos']['name'][0])) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $stmt_file = $pdo->prepare("INSERT INTO archivos_adjuntos (tarea_id, usuario_id, nombre_original, nombre_archivo, tipo_archivo, tamano) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($_FILES['archivos']['name'] as $key => $name) {
                if ($_FILES['archivos']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['archivos']['tmp_name'][$key];
                    $type = $_FILES['archivos']['type'][$key];
                    $size = $_FILES['archivos']['size'][$key];
                    
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $new_name = uniqid('file_') . '.' . $ext;
                    
                    if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                        $stmt_file->execute([$tarea_id, $usuario_actual, $name, $new_name, $type, $size]);
                    }
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $tarea_id, 'message' => 'Tarea guardada correctamente']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error en guardar_tarea: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>