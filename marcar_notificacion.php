<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) exit();

if (isset($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ? AND usuario_id_destino = ?");
    $stmt->execute([$_POST['id'], $_SESSION['usuario_id']]);
}
?>