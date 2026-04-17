<?php
session_start();
require_once 'config.php';

// Seguridad: SOLO SUPER ADMIN
if (!isset($_SESSION['logged_in']) || ($_SESSION['usuario_rol'] ?? 'guest') != 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

header('Content-Type: application/json');

try {
    $stmt_config = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('SAAS_API_URL', 'TUDU_LICENSE_KEY')");
    $config = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);

    $saas_api_url = $config['SAAS_API_URL'] ?? null;
    $license_key = $config['TUDU_LICENSE_KEY'] ?? null;

    if (!$saas_api_url || !$license_key) {
        throw new Exception("La URL de la API o la clave de licencia no están configuradas.");
    }

    $validation_url = $saas_api_url . '?key=' . urlencode($license_key);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $validation_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response_json = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response_json === false) {
        throw new Exception("Error de cURL al conectar con el servidor de licencias: " . $curl_error);
    }

    $response = json_decode($response_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("La respuesta del servidor de licencias no es un JSON válido. Respuesta recibida: " . substr($response_json, 0, 200));
    }

    $new_status = $response['status'] ?? 'inactive';
    $new_message = $response['message'] ?? 'No se pudo validar la licencia.';

    // Actualizar caché local
    $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('LICENSE_STATUS', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$new_status]);
    $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('LICENSE_LAST_CHECK', NOW()) ON DUPLICATE KEY UPDATE valor = NOW()")->execute();

    if ($new_status === 'active') {
        echo json_encode(['success' => true, 'message' => '¡Éxito! La licencia está activa. El sistema está desbloqueado.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'La licencia sigue inactiva. Mensaje del servidor: ' . $new_message]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al forzar la verificación: ' . $e->getMessage()]);
}
?>