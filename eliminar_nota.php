<?php
require_once 'config.php';
session_start();

// Verificar que el usuario esté logueado
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$nota_id = $_GET['id'] ?? null;
$usuario_actual_id = $_SESSION['usuario_id'];
$usuario_actual_rol = $_SESSION['usuario_rol'];

if ($nota_id) {
    try {
        // Medida de seguridad: Verificar que el usuario es dueño de la nota o es admin
        // También obtenemos el título de la tarea para la auditoría
        $stmt = $pdo->prepare("SELECT n.usuario_id, t.titulo FROM notas_tareas n LEFT JOIN tareas t ON n.tarea_id = t.id WHERE n.id = ?");
        $stmt->execute([$nota_id]);
        $nota = $stmt->fetch();

        if ($nota && ($nota['usuario_id'] == $usuario_actual_id || $usuario_actual_rol == 'admin')) {
            // El usuario tiene permiso, proceder a eliminar
            $stmt_delete = $pdo->prepare("DELETE FROM notas_tareas WHERE id = ?");
            $stmt_delete->execute([$nota_id]);

            // Registrar en auditoría
            $detalle = $nota['titulo'] ? "Se eliminó una nota de la tarea: '{$nota['titulo']}'" : "Se eliminó la nota ID {$nota_id}";
            registrarAuditoria($usuario_actual_id, 'DELETE', 'notas_tareas', $nota_id, $detalle);
        }
    } catch (PDOException $e) {
        // En un entorno de producción, sería bueno registrar este error.
        // error_log("Error al eliminar nota: " . $e->getMessage());
    }
}

// Redirigir de vuelta a la página anterior para mantener los filtros
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
exit();