<?php
require_once 'config.php';
session_start();

// Seguridad: Solo Super Admin puede tocar esto
if (!isset($_SESSION['logged_in']) || trim($_SESSION['usuario_rol']) !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $max_users = $_POST['max_users'] ?? null;
    $app_status = $_POST['app_status'] ?? null;

    if ($max_users !== null) {
        // Guardar o actualizar la configuración (Asumimos clave 'MAX_USERS')
        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('MAX_USERS', ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute([$max_users, $max_users]);
        
        if ($app_status !== null) {
            $stmt_status = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('APP_STATUS', ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt_status->execute([$app_status, $app_status]);
        }

        // --- MANEJO DE LOGO ---
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);

            if (in_array($mime_type, $allowed_types)) {
                $upload_dir = 'uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = 'app_logo_' . time() . '.' . $ext; // Unique name to avoid cache issues
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                    // FIX: Guardar ruta relativa para evitar problemas con URLs absolutas en diferentes equipos
                    // $logo_url = APP_URL . $filepath; 
                    $logo_url = $filepath; 
                    $stmt_logo = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('APP_LOGO', ?) ON DUPLICATE KEY UPDATE valor = ?");
                    $stmt_logo->execute([$logo_url, $logo_url]);
                }
            }
        }
        
        registrarAuditoria($_SESSION['usuario_id'], 'UPDATE', 'configuracion', 0, "Configuración actualizada (incluyendo logo si aplica).");
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Faltan datos']);
    }
} else {
    // GET: Obtener valor actual
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('MAX_USERS', 'APP_STATUS', 'APP_LOGO')");
    $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt_count = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1");
    $count = $stmt_count->fetchColumn();
    
    // Si no existe configuración, devolvemos un valor alto por defecto para no bloquear, o 5 si es estricto.
    echo json_encode([
        'max_users' => $config['MAX_USERS'] ?? 10, 
        'app_status' => $config['APP_STATUS'] ?? 'active',
        'app_logo' => $config['APP_LOGO'] ?? '',
        'current_users' => $count
    ]); 
}
?>