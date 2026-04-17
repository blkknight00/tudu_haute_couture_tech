<?php
/**
 * Save a browser push subscription for the current user.
 * Called by the React usePushNotifications hook after user grants permission.
 *
 * POST body: { endpoint, keys: { p256dh, auth } }
 */
require_once '../config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['status' => 'error', 'message' => 'Method not allowed']); exit; }
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'No autorizado']); exit; }

// Ensure tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        user_id       INT NOT NULL,
        endpoint      TEXT NOT NULL,
        endpoint_hash CHAR(64) NOT NULL,
        p256dh        TEXT NOT NULL,
        auth_key      TEXT NOT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_endpoint (endpoint_hash)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS push_notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        title      VARCHAR(255) NOT NULL,
        body       TEXT NOT NULL,
        url        VARCHAR(500) DEFAULT '/',
        is_read    TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_unread (user_id, is_read)
    )");
} catch (PDOException $e) {
    // Tables may already exist, continue
}

$input    = json_decode(file_get_contents('php://input'), true);
$endpoint = $input['endpoint'] ?? '';
$p256dh   = $input['keys']['p256dh'] ?? '';
$auth     = $input['keys']['auth'] ?? '';

if (!$endpoint || !$p256dh || !$auth) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$hash = hash('sha256', $endpoint);

try {
    $stmt = $pdo->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth_key)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            user_id  = VALUES(user_id),
            p256dh   = VALUES(p256dh),
            auth_key = VALUES(auth_key)
    ");
    $stmt->execute([$_SESSION['user_id'], $endpoint, $hash, $p256dh, $auth]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
