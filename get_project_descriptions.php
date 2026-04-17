<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_rol = $_SESSION['usuario_rol'] ?? 'usuario';

try {
    if ($usuario_rol === 'admin') {
        // Si es ADMIN: Traer TODOS los proyectos (públicos y privados de cualquiera)
        $stmt = $pdo->prepare("
            SELECT p.id, p.nombre, p.descripcion, p.user_id
            FROM proyectos p
            ORDER BY p.nombre ASC
        ");
        $stmt->execute();
    } else {
        // Si es USUARIO: Solo públicos y los suyos propios
        $stmt = $pdo->prepare("
            SELECT p.id, p.nombre, p.descripcion, p.user_id
            FROM proyectos p
            WHERE p.user_id IS NULL OR p.user_id = ?
            ORDER BY p.nombre ASC
        ");
        $stmt->execute([$usuario_id]);
    }
    $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'proyectos' => $proyectos]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error al consultar la base de datos: ' . $e->getMessage()]);
}
?>