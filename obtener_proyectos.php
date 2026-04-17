<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$vista = $_GET['vista'] ?? 'public';

$sql = "SELECT id, nombre FROM proyectos WHERE ";
$params = [];

if ($vista == 'personal') {
    $sql .= "user_id = ?";
    $params[] = $usuario_id;
} else { // 'public'
    $sql .= "user_id IS NULL";
}

$sql .= " ORDER BY nombre";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($proyectos);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener proyectos']);
}
?>
