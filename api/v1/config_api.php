<?php
// api/v1/config_api.php

// Cargar credenciales de BD (asumiendo que está 2 niveles arriba)
require_once '../../db_credentials.php';

header('Content-Type: application/json; charset=utf-8');

// Configuración de errores para API (Devolver JSON en lugar de HTML)
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

/**
 * Verifica el Token Bearer contra la tabla api_keys
 * @param PDO $pdo
 * @return array Datos de la app (id, app_name) si es válido
 */
function authenticateApi($pdo) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ''; // Case insensitive check

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid Authorization header']);
        exit();
    }

    $apiKey = $matches[1];

    $stmt = $pdo->prepare("SELECT id, app_name FROM api_keys WHERE api_key = ? AND is_active = 1");
    $stmt->execute([$apiKey]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or inactive API Key']);
        exit();
    }

    return $app;
}
?>
