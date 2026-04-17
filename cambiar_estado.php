<?php
require_once 'config.php';
session_start(); // Necesitamos la sesión para el ID de usuario

if ($_POST) {
    $tarea_id = $_POST['tarea_id'];
    $estado = $_POST['estado'];

    if (isset($_SESSION['logged_in']) && in_array($_SESSION['usuario_rol'], ['admin', 'super_admin'])) {
        $stmt = $pdo->prepare("UPDATE tareas SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $tarea_id]);

        // Registrar auditoría
        registrarAuditoria($_SESSION['usuario_id'], 'UPDATE', 'tareas', $tarea_id, "Cambió el estado a '{$estado}'");
    }

    // Redirigir de vuelta a la página anterior manteniendo el filtro
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $referer");
    exit();
}
?>