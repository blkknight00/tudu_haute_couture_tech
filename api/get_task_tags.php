<?php
require_once '../config.php';
header('Content-Type: application/json; charset=UTF-8');

$tarea_id = isset($_GET['tarea_id']) ? intval($_GET['tarea_id']) : 0;

if (!$tarea_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT etiqueta_id FROM tarea_etiquetas WHERE tarea_id = ?");
    $stmt->execute([$tarea_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN); // Returns array of IDs like [1, 5, 8]

    echo json_encode(['status' => 'success', 'tags' => $tags]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
