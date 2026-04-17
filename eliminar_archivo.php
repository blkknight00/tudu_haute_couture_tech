<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) exit();

if ($_POST) {
    $archivo_id = $_POST['id'];
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_rol = $_SESSION['usuario_rol'];

    // Verificar permisos (dueño del archivo, dueño de la tarea, o admin)
    $stmt = $pdo->prepare("SELECT a.*, t.usuario_id as tarea_owner, t.titulo as tarea_titulo 
                           FROM archivos_adjuntos a 
                           JOIN tareas t ON a.tarea_id = t.id 
                           WHERE a.id = ?");
    $stmt->execute([$archivo_id]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($archivo) {
        if ($archivo['usuario_id'] == $usuario_id || $archivo['tarea_owner'] == $usuario_id || in_array($usuario_rol, ['admin', 'super_admin'])) {
            // Eliminar archivo físico
            $ruta = "uploads/" . $archivo['nombre_archivo'];
            if (file_exists($ruta)) {
                unlink($ruta);
            }
            
            // Eliminar registro de BD
            $stmt_del = $pdo->prepare("DELETE FROM archivos_adjuntos WHERE id = ?");
            $stmt_del->execute([$archivo_id]);
            
            // Auditoría
            $detalle = "Se eliminó el archivo '{$archivo['nombre_original']}' de la tarea: '{$archivo['tarea_titulo']}'";
            registrarAuditoria($usuario_id, 'DELETE', 'archivos_adjuntos', $archivo_id, $detalle);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
    }
}
?>