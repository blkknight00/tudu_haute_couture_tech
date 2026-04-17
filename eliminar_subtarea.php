<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) exit();

if ($_POST) {
    $id = $_POST['id'];
    
    $stmt = $pdo->prepare("DELETE FROM subtareas WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
}
?>