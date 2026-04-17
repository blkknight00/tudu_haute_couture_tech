<?php
require_once 'config.php';
session_start();

// Verificar login
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$usuario_actual_rol = $_SESSION['usuario_rol'];

// Obtener datos JSON
$data = json_decode(file_get_contents('php://input'), true);
$tarea_id = $data['tarea_id'] ?? null;
$nuevo_estado = $data['estado'] ?? null;

if (!$tarea_id || !$nuevo_estado) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

// Actualizar estado
$stmt = $pdo->prepare("UPDATE tareas SET estado = ? WHERE id = ?");
if ($stmt->execute([$nuevo_estado, $tarea_id])) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en BD']);
}
?>