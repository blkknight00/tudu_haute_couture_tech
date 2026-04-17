<?php
require_once '../config.php';
require_once 'auth_middleware.php';

$user = checkAuth();

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['usuario_rol'];
$proyecto_id = $_GET['proyecto_id'] ?? null;
$view = $_GET['view'] ?? 'public'; 

try {
    $org_id = $_SESSION['organizacion_id'] ?? 1;
    $user_rol = $_SESSION['usuario_rol'] ?? '';
    $is_global = ($user_rol === 'super_admin' || $user_rol === 'admin_global');

    // 1. Fetch Distinct Tasks
    $sql = "SELECT DISTINCT t.*, p.nombre as proyecto_nombre
            FROM tareas t
            LEFT JOIN proyectos p ON t.proyecto_id = p.id
            LEFT JOIN tarea_asignaciones ta ON t.id = ta.tarea_id
            WHERE (t.organizacion_id = ? OR ? = 1) AND t.estado != 'archivado'";

    $params = [$org_id, $is_global ? 1 : 0];

    // Filter by View (based on PROJECT ownership, same logic as tasks.php)
    if ($view === 'personal' || $view === 'private') {
        // Show tasks from the current user's own private projects
        $sql .= " AND p.user_id = ?";
        $params[] = $usuario_id;
    } elseif ($view === 'all') {
        // Show all accessible tasks (public projects + own private projects)
        $sql .= " AND (p.user_id IS NULL OR p.user_id = ?)";
        $params[] = $usuario_id;
    } else {
        // Default 'public': tasks from public projects only
        $sql .= " AND (p.id IS NULL OR p.user_id IS NULL)";
    }

    if ($proyecto_id) {
        $sql .= " AND t.proyecto_id = ?";
        $params[] = $proyecto_id;
    }

    $sql .= " ORDER BY 
        CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END ASC,
        CASE WHEN t.fecha_termino IS NOT NULL THEN 0 ELSE 1 END ASC,
        t.fecha_termino ASC,
        t.fecha_creacion DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Extras (Assignments, Tags) if needed
    // For now, let's keep it lightweight. We can fetch assignments if we want avatars on cards.
    // Let's do a simple enrichment loop or separate query. Use separate query for efficiency.
    
    $taskIds = array_column($tareas, 'id');
    $assignments = [];
    
    if (!empty($taskIds)) {
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        
        // Assignments
        $sqlAsig = "SELECT ta.tarea_id, u.id as usuario_id, u.username, u.foto_perfil 
                   FROM tarea_asignaciones ta 
                   JOIN usuarios u ON ta.usuario_id = u.id 
                   WHERE ta.tarea_id IN ($placeholders)";
        $stmtAsig = $pdo->prepare($sqlAsig);
        $stmtAsig->execute($taskIds);
        while ($row = $stmtAsig->fetch(PDO::FETCH_ASSOC)) {
            $assignments[$row['tarea_id']][] = $row;
        }

        // Files Count
        $sqlFiles = "SELECT tarea_id, COUNT(*) as count FROM archivos_adjuntos WHERE tarea_id IN ($placeholders) GROUP BY tarea_id";
        $stmtFiles = $pdo->prepare($sqlFiles);
        $stmtFiles->execute($taskIds);
        $filesCounts = $stmtFiles->fetchAll(PDO::FETCH_KEY_PAIR);

        // Comments Count
        $sqlComments = "SELECT tarea_id, COUNT(*) as count FROM notas_tareas WHERE tarea_id IN ($placeholders) GROUP BY tarea_id";
        $stmtComments = $pdo->prepare($sqlComments);
        $stmtComments->execute($taskIds);
        $commentsCounts = $stmtComments->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // 3. Group
    $columns = [
        'pendiente' => [],
        'en_progreso' => [],
        'completado' => []
    ];

    foreach ($tareas as $t) {
        $t['asignados'] = $assignments[$t['id']] ?? [];
        $t['files_count'] = $filesCounts[$t['id']] ?? 0;
        $t['comments_count'] = $commentsCounts[$t['id']] ?? 0;
        
        if (isset($columns[$t['estado']])) {
            $columns[$t['estado']][] = $t;
        }
    }

    echo json_encode([
        'status' => 'success',
        'columns' => $columns
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
