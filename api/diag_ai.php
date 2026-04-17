<?php
// DIAGNOSTIC TOOL - DELETE AFTER USE
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=UTF-8');

$result = [
    'time' => date('Y-m-d H:i:s'),
    'environment' => []
];

// 1. Check directory structure
$result['environment']['dir'] = __DIR__;
$result['environment']['writable'] = is_writable('.');
$result['environment']['config_exists'] = file_exists('../config.php');
$result['environment']['db_creds_exists'] = file_exists('../db_credentials.php');

// 2. Try to load config safely
try {
    if ($result['environment']['config_exists']) {
        ob_start();
        require_once '../config.php';
        $output = ob_get_clean();
        $result['config_loaded'] = true;
        if (!empty($output)) {
            $result['config_output'] = $output;
        }
    } else {
        $result['config_loaded'] = false;
    }
} catch (Throwable $e) {
    $result['config_loaded'] = false;
    $result['config_error'] = $e->getMessage();
}

// 3. Database Check
if (isset($pdo)) {
    $result['database']['connected'] = true;
    try {
        $tables = ['usuarios', 'tareas', 'proyectos', 'eventos', 'ajustes'];
        $result['database']['tables'] = [];
        foreach ($tables as $t) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$t'");
            $result['database']['tables'][$t] = ($stmt->rowCount() > 0);
        }
        
        if ($result['database']['tables']['ajustes']) {
            $stmt = $pdo->query("SELECT clave, length(valor) as len FROM ajustes");
            $result['database']['ajustes_keys'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $result['database']['error'] = $e->getMessage();
    }
}

// 4. Trace log check
$result['trace_log'] = [
    'exists' => file_exists('api_trace.log'),
    'writable' => is_writable('api_trace.log') || is_writable('.'),
    'last_lines' => []
];
if ($result['trace_log']['exists']) {
    $logContent = file('api_trace.log');
    $result['trace_log']['last_lines'] = array_slice($logContent, -10);
}

// 5. Session
$result['session'] = [
    'status' => session_status(),
    'id' => session_id(),
    'user' => $_SESSION['usuario_id'] ?? 'NONE'
];

echo json_encode($result, JSON_PRETTY_PRINT);
?>
