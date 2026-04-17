<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'No autorizado']));
}

$task_ids = $_GET['task_ids'] ?? [];
$last_timestamp = $_GET['last_timestamp'] ?? '1970-01-01 00:00:00';

if (empty($task_ids)) {
    exit(json_encode([]));
}

// Sanear IDs para seguridad
$task_ids = array_map('intval', $task_ids);
$placeholders = implode(',', array_fill(0, count($task_ids), '?'));

$sql = "SELECT n.*, u.nombre as usuario_nombre 
        FROM notas_tareas n 
        JOIN usuarios u ON n.usuario_id = u.id 
        WHERE n.tarea_id IN ($placeholders) 
        AND n.fecha_creacion > ?
        ORDER BY n.fecha_creacion ASC";

$params = array_merge($task_ids, [$last_timestamp]);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nuevas_notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($nuevas_notas);
?>