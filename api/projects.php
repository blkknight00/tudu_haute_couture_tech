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
require_once 'saas_limits.php';

$user = checkAuth();
$usuario_id = $_SESSION['usuario_id'] ?? 0;

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Public Projects (user_id IS NULL) OR Private Projects (user_id = current)
        // If not logged in, only public.
        $org_id = $_SESSION['organizacion_id'] ?? 1;
        $user_rol = $_SESSION['usuario_rol'] ?? '';
        $is_global = ($user_rol === 'super_admin' || $user_rol === 'admin_global');

        // Base condition: match organization or global admin
        $whereClause = "WHERE (p.organizacion_id = ? OR ? = 1)";
        $params = [$org_id, $is_global ? 1 : 0];
        
        // Strict privacy filter: apply to EVERYONE including admins.
        // A project is visible if it is public (user_id IS NULL) OR if it belongs to the current user.
        if ($usuario_id > 0) {
            $whereClause .= " AND (p.user_id IS NULL OR p.user_id = ?)";
            $params[] = $usuario_id;
        } else {
            $whereClause .= " AND p.user_id IS NULL";
        }

        $sql = "SELECT DISTINCT p.id, p.nombre, p.descripcion, COALESCE(p.user_id, 0) as user_id, p.fecha_creacion, 
                       o.nombre as organizacion_nombre, p.organizacion_id, COUNT(t.id) as total_tasks,
                       u.username as creador_username, u.nombre as creador_nombre
                FROM proyectos p 
                LEFT JOIN tareas t ON p.id = t.proyecto_id AND t.estado != 'archivado'
                LEFT JOIN organizaciones o ON p.organizacion_id = o.id
                LEFT JOIN usuarios u ON p.user_id = u.id
                $whereClause
                GROUP BY p.id, p.nombre, p.descripcion, p.user_id, p.fecha_creacion, o.nombre, p.organizacion_id, u.username, u.nombre
                ORDER BY p.id DESC"; // Newer first
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // PHP 7.2+ supports JSON_INVALID_UTF8_SUBSTITUTE which is safer and faster
        $json = json_encode(
            ['status' => 'success', 'data' => $projects], 
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json === false) {
             echo json_encode([
                'status' => 'error', 
                'message' => 'Error JSON Fatal: ' . json_last_error_msg()
            ]);
        } else {
            echo $json;
        }
    }
    elseif ($method === 'POST') {
        error_log("PROJECT API HIT: POST request received. User ID: " . $usuario_id);
        
        // Create or Update
        $raw_input = file_get_contents('php://input');
        error_log("PROJECT API RAW INPUT: " . $raw_input);
        
        $input = json_decode($raw_input, true);
        
        $nombre = trim($input['nombre'] ?? '');
        $descripcion = trim($input['descripcion'] ?? '');
        $id = $input['id'] ?? null;
        $target_org_id = $input['organizacion_id'] ?? null;
        $visibilidad = $input['visibilidad'] ?? 'public';

        error_log("PROJECT API PARSED: nombre='$nombre', id='$id', target_org_id='$target_org_id', visibilidad='$visibilidad'");

        if (empty($nombre)) {
            error_log("PROJECT API ERROR: Empty name");
            echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio']);
            exit;
        }

        $user_rol = $_SESSION['usuario_rol'] ?? '';
        $is_global = ($user_rol === 'super_admin' || $user_rol === 'admin_global');
        
        $project_user_id = ($visibilidad === 'private') ? $usuario_id : null;

        if ($id) {
            // Ownership check: only owner or global admin can update
            $checkStmt = $pdo->prepare("SELECT user_id FROM proyectos WHERE id = ?");
            $checkStmt->execute([$id]);
            $existingProject = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingProject && $existingProject['user_id'] !== null && !$is_global && (int)$existingProject['user_id'] !== $usuario_id) {
                echo json_encode(['status' => 'error', 'message' => 'No tienes permisos para editar este proyecto']);
                exit;
            }

            // Update
            $sql = "UPDATE proyectos SET nombre = ?, descripcion = ?, user_id = ?";
            $params = [$nombre, $descripcion, $project_user_id];

            if ($is_global && $target_org_id) {
                $sql .= ", organizacion_id = ?";
                $params[] = $target_org_id;
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Create
            $org_id = ($is_global && $target_org_id) ? $target_org_id : ($_SESSION['organizacion_id'] ?? 1);
            // ── SaaS: verificar límite de proyectos del plan ──────────
            if (!authIsSuperAdmin()) {
                $planCheck = canCreateProject((int)$org_id);
                if (!$planCheck['allowed']) {
                    respondPlanLimit($planCheck, '/pricing');
                }
            }

            $sql = "INSERT INTO proyectos (nombre, descripcion, user_id, organizacion_id, fecha_creacion) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([$nombre, $descripcion, $project_user_id, $org_id]);
            } catch (PDOException $e) {
                error_log("CREATE PROJECT ERROR: " . $e->getMessage() . " | Payload: " . json_encode(['nombre' => $nombre, 'user_id' => $project_user_id, 'org_id' => $org_id]));
                echo json_encode(['status' => 'error', 'message' => 'Error al guardar en BD: ' . $e->getMessage()]);
                exit;
            }
        }

        echo json_encode(['status' => 'success']);
    }
    elseif ($method === 'DELETE') {
        // Delete
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID requerido']);
            exit;
        }

        // Ownership check: only owner or global admin can delete a private project
        $user_rol = $_SESSION['usuario_rol'] ?? '';
        $is_global = ($user_rol === 'super_admin' || $user_rol === 'admin_global');
        $checkStmt = $pdo->prepare("SELECT user_id FROM proyectos WHERE id = ?");
        $checkStmt->execute([$id]);
        $existingProject = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingProject && $existingProject['user_id'] !== null && !$is_global && (int)$existingProject['user_id'] !== $usuario_id) {
            echo json_encode(['status' => 'error', 'message' => 'No tienes permisos para eliminar este proyecto']);
            exit;
        }

        $sql = "DELETE FROM proyectos WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        echo json_encode(['status' => 'success']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
