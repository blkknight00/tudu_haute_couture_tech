<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once 'auth_middleware.php';

header("Content-Type: application/json; charset=UTF-8");

// Ensure only admins can access
$user = checkAuth();
if ($user['rol'] !== 'administrador' && $user['rol'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit;
}

$action = $_GET['action'] ?? 'info';

try {
    switch ($action) {
        case 'info':
            $stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('TUDU_LICENSE_KEY', 'LICENSE_STATUS', 'LICENSE_LAST_CHECK', 'MAX_USERS', 'APP_STATUS')");
            $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'license_key' => $config['TUDU_LICENSE_KEY'] ?? 'No configurada',
                    'status' => $config['LICENSE_STATUS'] ?? ($config['APP_STATUS'] ?? 'unknown'),
                    'last_check' => $config['LICENSE_LAST_CHECK'] ?? 'Nunca',
                    'max_users' => $config['MAX_USERS'] ?? '0'
                ]
            ]);
            break;

        case 'activate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
            
            $input = json_decode(file_get_contents('php://input'), true);
            $key = $input['license_key'] ?? '';

            if (empty($key)) {
                throw new Exception('La clave de licencia es obligatoria');
            }

            // Save key
            $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('TUDU_LICENSE_KEY', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt->execute([$key]);

            // Trigger force check (mimicking logic from force_license_check.php)
            validateLicense($pdo);

            echo json_encode(['status' => 'success', 'message' => 'Licencia actualizada y validada.']);
            break;

        case 'force_check':
            validateLicense($pdo);
            echo json_encode(['status' => 'success', 'message' => 'Sincronización con el servidor completada.']);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * Helper to validate license with SaaS server
 */
function validateLicense($pdo) {
    $stmt_config = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('SAAS_API_URL', 'TUDU_LICENSE_KEY')");
    $config = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);

    $saas_api_url = $config['SAAS_API_URL'] ?? null;
    $license_key = $config['TUDU_LICENSE_KEY'] ?? null;

    if (!$saas_api_url || !$license_key) {
        return; // Silent fail if not configured
    }

    $validation_url = $saas_api_url . '?key=' . urlencode($license_key);
    
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $response_json = @file_get_contents($validation_url, false, $ctx);
    
    if ($response_json === false) {
        return; // Connection error
    }

    $response = json_decode($response_json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $new_status = $response['status'] ?? 'inactive';
        
        $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('LICENSE_STATUS', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$new_status]);
        $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('LICENSE_LAST_CHECK', NOW()) ON DUPLICATE KEY UPDATE valor = NOW()")->execute();
    }
}
?>
