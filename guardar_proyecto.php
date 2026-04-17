<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['usuario_rol'], ['admin', 'super_admin'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_POST) {
    $id = $_POST['proyecto_id'] ?? null;
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $is_private = isset($_POST['is_private']);
    $usuario_id = $_SESSION['usuario_id'];

    // Si es privado, se asigna el ID del usuario. Si no, se deja en NULL.
    $user_id_for_project = $is_private ? $usuario_id : null;

    if ($id) {
        // Actualizar
        $stmt = $pdo->prepare("UPDATE proyectos SET nombre = ?, descripcion = ?, user_id = ? WHERE id = ?");
        $stmt->execute([$nombre, $descripcion, $user_id_for_project, $id]);
        
        registrarAuditoria($usuario_id, 'UPDATE', 'proyectos', $id, "Se actualizó el proyecto: '$nombre'");
    } else {
        // Insertar
        $stmt = $pdo->prepare("INSERT INTO proyectos (nombre, descripcion, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $descripcion, $user_id_for_project]);
        $id = $pdo->lastInsertId(); // Obtener el ID del nuevo proyecto
        
        registrarAuditoria($usuario_id, 'INSERT', 'proyectos', $id, "Se creó el proyecto: '$nombre'");
    }

    // Redirigir a la vista correcta del proyecto creado/editado
    $vista_redirect = $is_private ? 'personal' : 'publico';
    header("Location: dashboard.php?vista=" . $vista_redirect . "&proyecto_id=" . $id);
    exit();
}
?>