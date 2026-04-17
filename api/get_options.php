<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
// session_start(); // En config.php

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    // 1. Fetch Users
    $stmtUsers = $pdo->query("SELECT id, username, nombre, foto_perfil, telefono FROM usuarios WHERE activo = 1 ORDER BY nombre");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Projects (matching exact visibility from projects.php)
    $org_id = $_SESSION['organizacion_id'] ?? 1;
    $user_rol = $_SESSION['usuario_rol'] ?? '';
    $is_global = ($user_rol === 'super_admin' || $user_rol === 'admin_global');

    // Base condition: match organization or global admin
    $whereClause = "WHERE (p.organizacion_id = ? OR ? = 1)";
    $params = [$org_id, $is_global ? 1 : 0];
    
    // Strict privacy filter: apply to EVERYONE including admins.
    // A project is visible if it is public (user_id IS NULL) OR belongs to the current user.
    $current_user_id = $_SESSION['usuario_id'] ?? -1;
    if ($current_user_id > 0) {
        $whereClause .= " AND (p.user_id IS NULL OR p.user_id = ?)";
        $params[] = $current_user_id;
    } else {
        $whereClause .= " AND p.user_id IS NULL";
    }

    $sqlProjects = "SELECT DISTINCT p.id, p.nombre, COALESCE(p.user_id, 0) as user_id 
                    FROM proyectos p 
                    $whereClause 
                    ORDER BY p.nombre";
    $stmtProjects = $pdo->prepare($sqlProjects);
    $stmtProjects->execute($params);
    $projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Tags (Etiquetas)
    $stmtTags = $pdo->query("SELECT id, nombre, color FROM etiquetas ORDER BY nombre");
    $tags = $stmtTags->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'users' => $users,
        'projects' => $projects,
        'tags' => $tags
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
