<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
header('Content-Type: application/json; charset=UTF-8');

$method = $_SERVER['REQUEST_METHOD'];

// Helper to get input data
$data = json_decode(file_get_contents('php://input'), true);

try {
    $org_id = $_SESSION['organizacion_id'] ?? 1;
    $user_rol = $_SESSION['usuario_rol'] ?? '';
    $is_global = ($user_rol === 'super_admin' || $user_rol === 'admin_global');

    if ($method === 'GET') {
        // List all tags
        $stmt = $pdo->prepare("SELECT * FROM etiquetas WHERE (organizacion_id = ? OR ? = 1) ORDER BY nombre ASC");
        $stmt->execute([$org_id, $is_global ? 1 : 0]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    } elseif ($method === 'POST') {
        // Create or Update
        $nombre = $data['nombre'] ?? '';
        $color = $data['color'] ?? '#6B7280'; // Default Gray
        $id = $data['id'] ?? null;

        if (!$nombre) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio']);
            exit;
        }

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE etiquetas SET nombre = ?, color = ? WHERE id = ? AND (organizacion_id = ? OR ? = 1)");
            $stmt->execute([$nombre, $color, $id, $org_id, $is_global ? 1 : 0]);
            echo json_encode(['status' => 'success', 'message' => 'Etiqueta actualizada']);
        } else {
            // Create
            $stmt = $pdo->prepare("INSERT INTO etiquetas (nombre, color, organizacion_id) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $color, $org_id]);
            echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId(), 'message' => 'Etiqueta creada']);
        }

    } elseif ($method === 'DELETE') {
        // Delete
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID requerido']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM etiquetas WHERE id = ? AND (organizacion_id = ? OR ? = 1)");
        $stmt->execute([$id, $org_id, $is_global ? 1 : 0]);
        echo json_encode(['status' => 'success', 'message' => 'Etiqueta eliminada']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
