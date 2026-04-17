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

$input = json_decode(file_get_contents('php://input'), true);
$tarea_id = $input['tarea_id'] ?? $_POST['tarea_id'] ?? null;
$usuario_id = $_SESSION['usuario_id'];

if (!$tarea_id) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'ID requerido']));
}

try {
    // Audit info
    $stmt_info = $pdo->prepare("SELECT titulo FROM tareas WHERE id = ?");
    $stmt_info->execute([$tarea_id]);
    $titulo = $stmt_info->fetchColumn();

    $stmt = $pdo->prepare("UPDATE tareas SET estado = 'archivado' WHERE id = ?");
    $stmt->execute([$tarea_id]);

    if ($stmt->rowCount() > 0) {
        $detalle = $titulo ? "Se archivó la tarea: '$titulo'" : "Se archivó la tarea ID $tarea_id";
        registrarAuditoria($usuario_id, 'archivar', 'tareas', $tarea_id, $detalle);
        echo json_encode(['status' => 'success']);
    } else {
        // If not changed, maybe already archived?
        $check = $pdo->prepare("SELECT estado FROM tareas WHERE id = ?");
        $check->execute([$tarea_id]);
        $estadoActual = $check->fetchColumn();

        if ($estadoActual === 'archivado') {
             echo json_encode(['status' => 'success', 'message' => 'Ya estaba archivada']);
        } else {
             http_response_code(500);
             echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>