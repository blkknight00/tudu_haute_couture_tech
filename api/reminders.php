<?php
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';
require_once 'auth_middleware.php';

$authUser = checkAuth();
$user_id = $authUser['id'];

$action = $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if ($action === 'create') {
        $titulo = trim($data['titulo'] ?? '');
        $fecha = $data['fecha_recordatorio'] ?? null;
        
        if (empty($titulo) || empty($fecha)) {
            echo json_encode(['status' => 'error', 'message' => 'Título y fecha son requeridos']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO recordatorios (usuario_id, titulo, fecha_recordatorio, estado) VALUES (?, ?, ?, 'pendiente')");
            $stmt->execute([$user_id, $titulo, $fecha]);
            echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'complete') {
        $id = $data['id'] ?? null;
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID requerido']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE recordatorios SET estado = 'completado' WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['status' => 'success']);
    } elseif ($action === 'notified') {
        $id = $data['id'] ?? null;
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID requerido']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE recordatorios SET estado = 'notificado' WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['status' => 'success']);
    }
} else {
    if ($action === 'list_pending') {
        $now = date('Y-m-d H:i:s');
        // Get reminders that are due and still pending
        $stmt = $pdo->prepare("SELECT * FROM recordatorios WHERE usuario_id = ? AND estado = 'pendiente' AND fecha_recordatorio <= ? ORDER BY fecha_recordatorio ASC");
        $stmt->execute([$user_id, $now]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
?>
