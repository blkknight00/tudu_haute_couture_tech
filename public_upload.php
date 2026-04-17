<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['share_token'] ?? null;
    $uploader_name = trim($_POST['uploader_name'] ?? 'Invitado');
    $file = $_FILES['archivo'] ?? null;

    if (!$token || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        die("Error: Faltan datos o hubo un problema con la subida del archivo.");
    }

    // 1. Validar token y obtener tarea_id
    $stmt_token = $pdo->prepare("SELECT tarea_id FROM tarea_share_tokens WHERE token = ?");
    $stmt_token->execute([$token]);
    $tarea_id = $stmt_token->fetchColumn();

    // 1.1 Si no se encuentra, buscar en la tabla tareas (Legacy/Compatibilidad)
    if (!$tarea_id) {
        $stmt_legacy = $pdo->prepare("SELECT id FROM tareas WHERE token_compartido = ?");
        $stmt_legacy->execute([$token]);
        $tarea_id = $stmt_legacy->fetchColumn();
    }

    if (!$tarea_id) {
        die("Error: Enlace de subida no válido.");
    }

    // 2. Procesar el archivo
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $nombre_original = basename($file['name']);
    $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    $nombre_archivo_servidor = uniqid('public_') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $ruta_destino = $upload_dir . $nombre_archivo_servidor;

    // Mover el archivo
    if (move_uploaded_file($file['tmp_name'], $ruta_destino)) {
        try {
            $pdo->beginTransaction();

            // 3. Guardar en la tabla de archivos_adjuntos (sin usuario_id)
            $stmt_file = $pdo->prepare(
                "INSERT INTO archivos_adjuntos (tarea_id, nombre_original, nombre_archivo, tipo_archivo, tamano) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt_file->execute([$tarea_id, $nombre_original, $nombre_archivo_servidor, $file['type'], $file['size']]);

            // 4. Crear una nota para notificar al equipo
            $nota_texto = "El cliente '" . htmlspecialchars($uploader_name) . "' ha subido un nuevo archivo: " . htmlspecialchars($nombre_original);
            $stmt_nota = $pdo->prepare("INSERT INTO notas_tareas (tarea_id, nota, nombre_invitado) VALUES (?, ?, ?)");
            $stmt_nota->execute([$tarea_id, $nota_texto, 'Sistema (Cliente)']);

            $pdo->commit();

            // 5. Redirigir con mensaje de éxito
            header("Location: public_task.php?token=" . $token . "&feedback=" . urlencode('¡Archivo subido con éxito!'));
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            if (file_exists($ruta_destino)) unlink($ruta_destino);
            // Redirect with visible error instead of dying
            header("Location: public_task.php?token=" . $token . "&feedback=" . urlencode('Error DB al guardar: ' . $e->getMessage()));
            exit();
        }
    } else {
        die("Error al mover el archivo subido. Verifica los permisos de la carpeta 'uploads'.");
    }
}
?>