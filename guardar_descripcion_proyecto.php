<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Seguridad: Solo usuarios logueados pueden ejecutar esto.
// Podrías restringirlo a administradores si fuera necesario.
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$proyecto_id = $data['proyecto_id'] ?? null;
$descripcion = $data['descripcion'] ?? '';

if (!$proyecto_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'ID de proyecto no proporcionado.']);
    exit();
}

try {
    $sql = "UPDATE proyectos SET descripcion = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$descripcion, $proyecto_id]);
    echo json_encode(['success' => true, 'message' => 'Descripción actualizada correctamente.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>