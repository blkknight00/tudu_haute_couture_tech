<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) {
    exit(json_encode(['error' => 'No autorizado']));
}

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM proyectos WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($proyecto);
}
?>