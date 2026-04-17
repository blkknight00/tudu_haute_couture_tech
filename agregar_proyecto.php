<?php
require_once 'config.php';
session_start();

if ($_POST && isset($_SESSION['logged_in'])) {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $is_private = isset($_POST['is_private']);
    $usuario_id = $_SESSION['usuario_id'];

    // Si es privado, se asigna el ID del usuario. Si no, se deja en NULL.
    $user_id_for_project = $is_private ? $usuario_id : null;

    $sql = "INSERT INTO proyectos (nombre, descripcion, user_id) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nombre, $descripcion, $user_id_for_project]);
    
    $nuevo_id = $pdo->lastInsertId();
    registrarAuditoria($usuario_id, 'INSERT', 'proyectos', $nuevo_id, "Se creó el proyecto: '$nombre'");

    header("Location: dashboard.php");
    exit();
}
?>