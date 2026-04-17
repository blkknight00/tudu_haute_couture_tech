<?php
// api/generar_share_link.php

// CORS Headers

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php'; // Adjusted path
session_start();

// Verificar autenticación básica
if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$tarea_id = $_GET['id'] ?? null;

if (!$tarea_id || !is_numeric($tarea_id)) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea no válido']);
    exit;
}

try {
    // Verificar que la tarea existe
    $stmt = $pdo->prepare("SELECT id, titulo FROM tareas WHERE id = ?");
    $stmt->execute([$tarea_id]);
    $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tarea) {
        echo json_encode(['success' => false, 'error' => 'Tarea no encontrada']);
        exit;
    }
    
    // Cualquier usuario logueado puede compartir cualquier tarea
    $token = bin2hex(random_bytes(16));
    $expiracion = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Actualizar la tarea (Legacy/Simple method)
    // NOTE: Ideally we should use tarea_share_tokens table if moving to robust system, but sticking to legacy as requested by user file structure
    // Checking if tarea_share_tokens exists or just using tasks column?
    // public_task.php attempts table `tarea_share_tokens` first, then `tareas.token_compartido`.
    // generar_share_link.php original code used `tareas` table update. We stick to that to minimize regression unless we know `tarea_share_tokens` structure.
    
    // Let's try to insert into share tokens table IF it is the preferred way, but original file used UPDATE tareas.
    // I will stick to what the original file did: UPDATE TAREAS. 
    // Wait, the original file had `UPDATE tareas`.
    // But `public_task.php` reads from `tarea_share_tokens` first.
    // Ideally I should populate BOTH or the new one. 
    // Since I don't know the schema of `tarea_share_tokens` fully (cols), I will stick to the original logic which was UPDATE TAREAS.
    // Re-reading original `generar_share_link.php`:
    /*
    $update_stmt = $pdo->prepare("
        UPDATE tareas 
        SET token_compartido = ?, token_expiracion = ? 
        WHERE id = ?
    ");
    */
    
    $update_stmt = $pdo->prepare("
        UPDATE tareas 
        SET token_compartido = ?, token_expiracion = ? 
        WHERE id = ?
    ");
    
    $success = $update_stmt->execute([$token, $expiracion, $tarea_id]);
    

    // ALSO TRY to insert into new table if it exists, just to be safe for `public_task.php`?
    // public_task.php has a fallback to `tareas` table. So updating `tareas` table is enough.
    
    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar el token']);
        exit;
    }
    
    // Construir URL - Apuntar a la página pública
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    
    // Fix logic for Base Path since we are now in /api/
    // We want to point to /tudu_development/public_task.php
    // Current dir is /tudu_development/api
    // Parent dir is /tudu_development
    
    $path_parts = pathinfo($_SERVER['PHP_SELF']); 
    $api_dir = $path_parts['dirname']; // /tudu_development/api or \tudu_development\api
    $root_dir = dirname($api_dir); // /tudu_development
    
    // Normalize slashes
    $root_dir = str_replace('\\', '/', $root_dir);
    if (substr($root_dir, -1) !== '/') $root_dir .= '/';
    
    $script_compartir = 'public_task.php';
    
    $url = $protocol . $domainName . $root_dir . $script_compartir . '?token=' . urlencode($token);
    
    echo json_encode([
        'success' => true,
        'url' => $url,
        'token' => $token,
        'expiracion' => $expiracion,
        'tarea' => [
            'id' => $tarea['id'],
            'titulo' => $tarea['titulo']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
