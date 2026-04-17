<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) exit();

if ($_POST) {
    $id = $_POST['id'];
    $completado = $_POST['completado']; // 1 o 0

    $stmt = $pdo->prepare("UPDATE subtareas SET completado = ? WHERE id = ?");
    $stmt->execute([$completado, $id]);
    
    echo json_encode(['success' => true]);
}
?>