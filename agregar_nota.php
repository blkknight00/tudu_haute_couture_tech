<?php
require_once 'config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- LÓGICA PARA INVITADOS (CLIENTES) ---
    if (isset($_POST['share_token'])) {
        $token = $_POST['share_token'];
        $nombre_invitado = trim($_POST['nombre_invitado'] ?? 'Invitado');
        $nota_texto = trim($_POST['nota'] ?? '');

        // Validar token y obtener tarea_id
        $stmt_token = $pdo->prepare("SELECT tarea_id FROM tarea_share_tokens WHERE token = ?");
        $stmt_token->execute([$token]);
        $tarea_id = $stmt_token->fetchColumn();

        // Soporte para tokens legacy (tabla tareas)
        if (!$tarea_id) {
            $stmt_legacy = $pdo->prepare("SELECT id FROM tareas WHERE token_compartido = ?");
            $stmt_legacy->execute([$token]);
            $tarea_id = $stmt_legacy->fetchColumn();
        }

        if ($tarea_id && !empty($nota_texto) && !empty($nombre_invitado)) {
            try {
                // Insertar la nota SIN usuario_id, pero con el nombre del invitado
                $sql = "INSERT INTO notas_tareas (tarea_id, nota, nombre_invitado) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$tarea_id, $nota_texto, $nombre_invitado]);

                // Redirigir de vuelta a la página pública con un mensaje de éxito
                header("Location: public_task.php?token=" . $token . "&feedback=" . urlencode('¡Comentario agregado!'));
                exit();
            } catch (PDOException $e) {
                // Show DB error visibly (e.g. missing column) instead of silently failing
                header("Location: public_task.php?token=" . $token . "&feedback=" . urlencode('Error DB: ' . $e->getMessage()));
                exit();
            }
        } else {
            // Token inválido o datos faltantes
            die("Error: No se pudo procesar el comentario.");
        }
    }

    // --- LÓGICA PARA USUARIOS LOGUEADOS (EXISTENTE) ---
    if (!isset($_SESSION['logged_in'])) {
        header("Location: login.php");
        exit();
    }

    // Recoger los datos del formulario
    $tarea_id = $_POST['tarea_id'] ?? null;
    $nota_texto = trim($_POST['nota'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];
    $imagen_url = '';

    // --- PROCESAR IMAGEN ADJUNTA ---
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
        finfo_close($finfo);

        if (in_array($mime_type, $allowed_types)) {
            $upload_dir = 'uploads/notas/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('nota_img_') . '.' . $ext;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $filepath)) {
                $imagen_url = APP_URL . $filepath;
                // Adjuntar la URL al texto de la nota
                $nota_texto .= "\n" . $imagen_url;
            }
        }
    }

    // Validar que los datos necesarios estén presentes (Texto O Imagen)
    if (!$tarea_id || (empty($nota_texto) && empty($imagen_url))) {
        // Si la petición es AJAX pero los datos son inválidos, devolver un error JSON
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'error' => 'La nota no puede estar vacía (escribe algo o sube una imagen).']);
            exit();
        }
        // Para peticiones normales, simplemente redirigir sin hacer nada.
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit();
    }

    // Si llegamos aquí, los datos son válidos. Procedemos a insertar.
    try {
        $sql = "INSERT INTO notas_tareas (tarea_id, usuario_id, nota) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tarea_id, $usuario_id, $nota_texto]);
        $nueva_nota_id = $pdo->lastInsertId();

        // --- SISTEMA DE MENCIONES ---
        preg_match_all('/@(\w+)/', $nota_texto, $menciones);
        if (!empty($menciones[1])) {
            $usernames_unicos = array_unique($menciones[1]);
            foreach ($usernames_unicos as $username_mencionado) {
                $stmt_u = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
                $stmt_u->execute([$username_mencionado, $usuario_id]);
                $user_dest = $stmt_u->fetchColumn();
                if ($user_dest) {
                    $mensaje = $_SESSION['usuario_nombre'] . " te mencionó en una nota.";
                    $stmt_notif = $pdo->prepare("INSERT INTO notificaciones (usuario_id_destino, usuario_id_origen, tarea_id, tipo, mensaje) VALUES (?, ?, ?, 'mencion', ?)");
                    $stmt_notif->execute([$user_dest, $usuario_id, $tarea_id, $mensaje]);
                }
            }
        }

        // Registrar auditoría
        // Obtener título de la tarea para auditoría
        $stmt_t = $pdo->prepare("SELECT titulo FROM tareas WHERE id = ?");
        $stmt_t->execute([$tarea_id]);
        $titulo_tarea = $stmt_t->fetchColumn();
        $detalle_audit = $titulo_tarea ? "Agregó una nota a la tarea: '$titulo_tarea'" : "Agregó una nota a la tarea ID $tarea_id";
        
        registrarAuditoria($usuario_id, 'INSERT', 'notas_tareas', $nueva_nota_id, $detalle_audit);

        // Si es una petición AJAX, devolver el nuevo comentario como JSON
        if (!empty($_POST['ajax'])) {
            $stmt_new_note = $pdo->prepare("SELECT n.*, u.nombre as usuario_nombre FROM notas_tareas n LEFT JOIN usuarios u ON n.usuario_id = u.id WHERE n.id = ?");
            $stmt_new_note->execute([$nueva_nota_id]);
            $nueva_nota = $stmt_new_note->fetch(PDO::FETCH_ASSOC);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'nota' => $nueva_nota]);
            exit();
        }

    } catch (PDOException $e) {
        error_log("Error al guardar nota: " . $e->getMessage());
        // Si es AJAX, devolver un error
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos.']);
            exit();
        }
    }
}

// Redirigir de vuelta a la página anterior para mantener los filtros
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
exit();
