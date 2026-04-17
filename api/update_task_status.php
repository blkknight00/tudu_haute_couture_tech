<?php
require_once '../config.php';
require_once 'auth_middleware.php';

$user = checkAuth();

$data = json_decode(file_get_contents("php://input"), true);
$taskId = $data['id'] ?? null;
$newStatus = $data['status'] ?? null;

if (!$taskId || !$newStatus) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$validStatuses = ['pendiente', 'en_progreso', 'completado'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Estado inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tareas SET estado = ? WHERE id = ?");
    $stmt->execute([$newStatus, $taskId]);
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
