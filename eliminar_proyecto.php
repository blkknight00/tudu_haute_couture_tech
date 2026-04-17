<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['usuario_rol'], ['admin', 'super_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['proyecto_id'] ?? null;
}

if ($id) {
    // Obtener nombre del proyecto para auditoría antes de borrarlo
    $stmt_info = $pdo->prepare("SELECT nombre FROM proyectos WHERE id = ?");
    $stmt_info->execute([$id]);
    $nombre_proyecto = $stmt_info->fetchColumn();
    
    // Asegurar que siempre haya un nombre para el log
    $nombre_log = $nombre_proyecto ? $nombre_proyecto : "Proyecto ID $id";

    // Eliminar tareas asociadas primero (opcional si hay ON DELETE CASCADE, pero seguro hacerlo)
    $pdo->prepare("DELETE FROM tareas WHERE proyecto_id = ?")->execute([$id]);
    
    // Eliminar proyecto
    $stmt = $pdo->prepare("DELETE FROM proyectos WHERE id = ?");
    $stmt->execute([$id]);

    registrarAuditoria($_SESSION['usuario_id'], 'DELETE', 'proyectos', $id, "Se eliminó el proyecto: '$nombre_log'");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
}

header("Location: dashboard.php?action=gestionProyectos");
exit();
?>