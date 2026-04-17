<?php
require_once 'config.php';
session_start();

// Solo administradores y super administradores pueden ejecutar esto
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['usuario_rol'], ['admin', 'super_admin'])) {
    header("Location: login.php");
    exit();
}

$usuario_id_a_eliminar = $_GET['id'] ?? null;
$usuario_actual_id = $_SESSION['usuario_id'];
$usuario_actual_rol = $_SESSION['usuario_rol'];
$organizacion_id_actual = $_SESSION['organizacion_id'] ?? null;

// Validaciones de seguridad
// 1. Asegurarse de que se proveyó un ID
// 2. Asegurarse de que el admin no se está eliminando a sí mismo
if (!$usuario_id_a_eliminar || $usuario_id_a_eliminar == $usuario_actual_id) {
    header("Location: dashboard.php");
    exit();
}

$puede_eliminar = false;
if ($usuario_actual_rol == 'super_admin') {
    $puede_eliminar = true;
} elseif ($usuario_actual_rol == 'admin' && $organizacion_id_actual) {
    // Verificar que el usuario a eliminar pertenece a la misma organización
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM miembros_organizacion WHERE usuario_id = ? AND organizacion_id = ?");
    $stmt_check->execute([$usuario_id_a_eliminar, $organizacion_id_actual]);
    if ($stmt_check->fetchColumn() > 0) {
        $puede_eliminar = true;
    }
}

if ($puede_eliminar) {
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id_a_eliminar]);
}

header("Location: dashboard.php");
exit();
?>