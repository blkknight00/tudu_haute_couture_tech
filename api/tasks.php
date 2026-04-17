<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once 'auth_middleware.php';

// Polyfill session via JWT
checkAuth();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in'])) {
    error_log("[TASKS.PHP] No autorizado. headers=" . json_encode(getallheaders()));
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['usuario_rol'];
$proyecto_id = $_GET['proyecto_id'] ?? null;
$view = $_GET['view'] ?? 'public'; // 'public' or 'private'/'personal'

error_log("[TASKS.PHP] Authorized. view=$view");
file_put_contents(__DIR__ . '/tasks_trace.log', "[" . date('Y-m-d H:i:s') . "] view=$view. User={$usuario_id}. Project={$proyecto_id}\n", FILE_APPEND);

try {
    $user_rol = $_SESSION['usuario_rol'] ?? '';
    $is_global = ($user_rol === 'super_admin' || $user_rol === 'admin_global');
    $org_id = $_SESSION['organizacion_id'] ?? 1;

    // Base SQL — NO JOIN on tarea_asignaciones here (it causes one row per assignee → duplicate task IDs).
    // The user-filter uses an EXISTS subquery instead.
    $sql = "SELECT t.*, p.nombre as proyecto_nombre, 
            (SELECT COUNT(*) FROM notas_tareas WHERE tarea_id = t.id) as comments_count,
            (SELECT COUNT(*) FROM recursos WHERE tarea_id = t.id) as files_count
            FROM tareas t
            LEFT JOIN proyectos p ON t.proyecto_id = p.id
            WHERE (t.organizacion_id = ? OR ? = 1)";

    $params = [$org_id, $is_global ? 1 : 0];

    // Filter by Archived Status
    if (isset($_GET['archived']) && $_GET['archived'] === 'true') {
        $sql .= " AND t.estado = 'archivado'";
    } else {
        $sql .= " AND t.estado != 'archivado'";
    }

    // Filter by View (based on PROJECT ownership, not task visibility)
    // 'private' = tasks in projects owned by current user (p.user_id = current user)
    // 'public'  = tasks in public projects (p.user_id IS NULL) visible to the org
    if ($view === 'personal' || $view === 'private') {
        // Show tasks from the current user's own private projects
        $sql .= " AND p.user_id = ?";
        $params[] = $usuario_id;
    } elseif ($view === 'all') {
        // Show all accessible tasks (public projects + own private projects)
        $sql .= " AND (p.user_id IS NULL OR p.user_id = ?)";
        $params[] = $usuario_id;
    } else {
        // Default 'public': tasks from public projects only (no private project owner)
        $sql .= " AND (p.id IS NULL OR p.user_id IS NULL)";
    }

    if ($proyecto_id) {
        $sql .= " AND t.proyecto_id = ?";
        $params[] = $proyecto_id;
    }

    // --- NEW FILTERS ---
    // Filter by Status
    if (isset($_GET['estado']) && $_GET['estado'] !== 'todos') {
        $sql .= " AND t.estado = ?";
        $params[] = $_GET['estado'];
    }

    // Filter by Priority
    if (isset($_GET['prioridad']) && $_GET['prioridad'] !== 'todas') {
        $sql .= " AND t.prioridad = ?";
        $params[] = $_GET['prioridad'];
    }

    // Filter by User (Designer) — use EXISTS to avoid row duplication
    if (isset($_GET['usuario_id_filtro']) && $_GET['usuario_id_filtro'] !== '') {
        $sql .= " AND EXISTS (SELECT 1 FROM tarea_asignaciones ta2 WHERE ta2.tarea_id = t.id AND ta2.usuario_id = ?)";
        $params[] = intval($_GET['usuario_id_filtro']);
    }

    // Filter by Due Date
    if (isset($_GET['fecha_vencimiento']) && $_GET['fecha_vencimiento'] !== '') {
        $sql .= " AND DATE(t.fecha_termino) = ?";
        $params[] = $_GET['fecha_vencimiento'];
    }

    // Search by title or description
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $searchTerm = '%' . $_GET['search'] . '%';
        $sql .= " AND (t.titulo LIKE ? OR t.descripcion LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Sorting Logic:
    $sql .= " ORDER BY 
        CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END ASC,
        CASE WHEN t.fecha_termino IS NOT NULL THEN 0 ELSE 1 END ASC,
        t.fecha_termino ASC,
        t.fecha_creacion DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Assignments for Avatars
    $taskIds = array_column($tareas, 'id');
    $assignments = [];
    $tags = [];
    if (!empty($taskIds)) {
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        
        // 1. Fetch Assignments
        $sqlAsig = "SELECT ta.tarea_id, u.id, u.username, u.foto_perfil, u.nombre
                   FROM tarea_asignaciones ta 
                   JOIN usuarios u ON ta.usuario_id = u.id 
                   WHERE ta.tarea_id IN ($placeholders)";
        $stmtAsig = $pdo->prepare($sqlAsig);
        $stmtAsig->execute($taskIds);
        while ($row = $stmtAsig->fetch(PDO::FETCH_ASSOC)) {
            $assignments[$row['tarea_id']][] = $row;
        }

        // 2. Fetch Tags (DISTINCT prevents duplicate rows from tarea_etiquetas)
        $sqlTags = "SELECT DISTINCT te.tarea_id, e.id, e.nombre, e.color
                   FROM tarea_etiquetas te
                   JOIN etiquetas e ON te.etiqueta_id = e.id
                   WHERE te.tarea_id IN ($placeholders)";
        $stmtTags = $pdo->prepare($sqlTags);
        $stmtTags->execute($taskIds);
        $tagsSeen = []; // Guard: track (tarea_id, etiqueta_id) pairs already added
        while ($row = $stmtTags->fetch(PDO::FETCH_ASSOC)) {
            $pairKey = $row['tarea_id'] . '_' . $row['id'];
            if (!isset($tagsSeen[$pairKey])) {
                $tagsSeen[$pairKey] = true;
                $tags[$row['tarea_id']][] = $row;
            }
        }
    }

    // Attach assignments and tags
    foreach ($tareas as &$t) {
        $t['assignees'] = $assignments[$t['id']] ?? [];
        $t['tags'] = $tags[$t['id']] ?? [];
    }

    echo json_encode([
        'status' => 'success',
        'tasks' => $tareas
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
