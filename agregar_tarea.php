<?php
require_once 'config.php';
session_start();

if ($_POST) {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $proyecto_id = $_POST['proyecto_id'];
    // El formulario envía 'usuario_id', no 'usuario'.
    $usuario_id = $_POST['usuario_id'] ?? $_SESSION['usuario_id']; // Usar el usuario de la sesión como fallback

    $stmt = $pdo->prepare("INSERT INTO tareas (titulo, descripcion, proyecto_id, usuario_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$titulo, $descripcion, $proyecto_id, $usuario_id]);

    header("Location: index.php?proyecto_id=" . $proyecto_id);
    exit();
}
?>