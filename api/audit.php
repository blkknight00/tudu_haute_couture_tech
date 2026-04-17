<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once 'auth_middleware.php';

header("Content-Type: application/json; charset=UTF-8");

// Ensure only admins can access
$user = checkAuth();
if ($user['rol'] !== 'administrador' && $user['rol'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $org_id = $_SESSION['organizacion_id'] ?? null;
            $is_super_admin = ($user['rol'] === 'super_admin');
            
            $sql = "SELECT a.*, u.nombre as usuario_nombre 
                    FROM auditoria a 
                    LEFT JOIN usuarios u ON a.usuario_id = u.id ";
            
            $params = [];
            if (!$is_super_admin) {
                $sql .= " WHERE a.organizacion_id = ? ";
                $params[] = $org_id;
            }
            
            $sql .= " ORDER BY a.fecha DESC LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $registros]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
