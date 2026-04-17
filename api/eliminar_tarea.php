<?php
// Fix CORS

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
// session_start(); // En config.php

if (!isset($_SESSION['logged_in'])) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'No autorizado']));
}

$tarea_id = $_GET['id'] ?? null;

if (!$tarea_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'ID requerido']));
}

try {
    $pdo->beginTransaction();

    // Audit
    $stmt_titulo = $pdo->prepare("SELECT titulo FROM tareas WHERE id = ?");
    $stmt_titulo->execute([$tarea_id]);
    $titulo_tarea = $stmt_titulo->fetchColumn();

    // Delete Notes dependencies
    $stmt_notas = $pdo->prepare("DELETE FROM notas_tareas WHERE tarea_id = ?");
    $stmt_notas->execute([$tarea_id]);

    // Delete Task
    $stmt_tarea = $pdo->prepare("DELETE FROM tareas WHERE id = ?");
    $stmt_tarea->execute([$tarea_id]);

    registrarAuditoria($_SESSION['usuario_id'], 'DELETE', 'tareas', $tarea_id, "Se eliminó la tarea: '{$titulo_tarea}' (ID: {$tarea_id})");

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>