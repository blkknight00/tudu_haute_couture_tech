<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once 'auth_middleware.php';

// Ensure only platform admins can manage organizations
$user = checkAuth();
if ($user['rol'] !== 'super_admin' && $user['rol'] !== 'administrador') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Se requiere ser Administrador o Super Admin.']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT id, nombre, plan, plan_status, plan_renews_at, fecha_creacion, edition, members_limit, projects_limit, tasks_limit, deepseek_api_key FROM organizaciones ORDER BY nombre ASC");
            $orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $orgs]);
            break;

        case 'save':
            $data = json_decode(file_get_contents("php://input"), true);
            $id = $data['id'] ?? null;
            $nombre = $data['nombre'] ?? '';

            if (empty($nombre)) throw new Exception('El nombre es obligatorio');

            $deepseek_api_key = $data['deepseek_api_key'] ?? null;

            if ($id) {
                $stmt = $pdo->prepare("UPDATE organizaciones SET nombre = ?, deepseek_api_key = ? WHERE id = ?");
                $stmt->execute([$nombre, $deepseek_api_key, $id]);
            } else {
                $orgCode = "ORG-" . strtoupper(bin2hex(random_bytes(3)));
                $stmt = $pdo->prepare("INSERT INTO organizaciones (nombre, codigo_acceso, deepseek_api_key) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $orgCode, $deepseek_api_key]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Organización guardada']);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('ID requerido');
            if ($id == 1) throw new Exception('No se puede eliminar la organización principal');

            $stmt = $pdo->prepare("DELETE FROM organizaciones WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Organización eliminada']);
            break;

        case 'toggle_lifetime':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('ID requerido');
            
            $stmt = $pdo->prepare("SELECT plan_status FROM organizaciones WHERE id = ?");
            $stmt->execute([$id]);
            $currentStatus = $stmt->fetchColumn();
            
            $newStatus = ($currentStatus === 'lifetime') ? 'trialing' : 'lifetime';
            $stmt = $pdo->prepare("UPDATE organizaciones SET plan_status = ?, members_limit = ?, projects_limit = ?, tasks_limit = ? WHERE id = ?");
            
            if ($newStatus === 'lifetime') {
                $stmt->execute([$newStatus, 99999, 99999, 999999, $id]);
            } else {
                $stmt->execute([$newStatus, 3, 5, 100, $id]);
            }
            
            echo json_encode(['status' => 'success', 'message' => "Estado actualizado a $newStatus", 'new_status' => $newStatus]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
