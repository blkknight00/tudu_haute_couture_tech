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

$user = checkAuth();
$user_id = $user['id'];

$action = $_GET['action'] ?? 'status';

try {
    switch ($action) {
        case 'status':
            $stmt = $pdo->prepare("SELECT tour_visto FROM usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'tour_visto' => (int)($row['tour_visto'] ?? 0)]);
            break;

        case 'complete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
            $stmt = $pdo->prepare("UPDATE usuarios SET tour_visto = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['status' => 'success']);
            break;

        case 'reset':
            // Reset for debugging or manual re-tour
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
            $stmt = $pdo->prepare("UPDATE usuarios SET tour_visto = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['status' => 'success']);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
