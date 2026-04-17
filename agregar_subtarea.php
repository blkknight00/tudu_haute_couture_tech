<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado']));
}

if ($_POST) {
    $tarea_id = $_POST['tarea_id'];
    $titulo = trim($_POST['titulo']);

    if ($tarea_id && $titulo) {
        $stmt = $pdo->prepare("INSERT INTO subtareas (tarea_id, titulo) VALUES (?, ?)");
        $stmt->execute([$tarea_id, $titulo]);
        $id = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $id, 'titulo' => $titulo]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    }
}
?>