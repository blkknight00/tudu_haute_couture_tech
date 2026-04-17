<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Trace execution
file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Starting get_context.php\n", FILE_APPEND);

try {

require_once '../config.php';
require_once 'auth_middleware.php';

file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Files required in get_context\n", FILE_APPEND);

$authUser = checkAuth(); 
$user_id = $authUser['id'];
file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Auth OK for user $user_id in get_context\n", FILE_APPEND);


$urgentTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

// 1b. Get Tomorrow's Tasks
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));
$stmtTomorrowTasks = $pdo->prepare("
    SELECT t.id, t.titulo, t.descripcion, t.prioridad, t.fecha_termino, p.nombre as proyecto
    FROM tareas t
    LEFT JOIN proyectos p ON t.proyecto_id = p.id
    WHERE t.usuario_id = ?
    AND t.estado != 'completado'
    AND t.fecha_termino = ?
    ORDER BY t.prioridad DESC
");
$stmtTomorrowTasks->execute([$user_id, $tomorrow_date]);
$tomorrowTasks = $stmtTomorrowTasks->fetchAll(PDO::FETCH_ASSOC);

$upcomingEvents = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

// 2b. Get Tomorrow's Events
$tomorrow_start = $tomorrow_date . ' 00:00:00';
$tomorrow_end = $tomorrow_date . ' 23:59:59';
$stmtTomorrowEvents = $pdo->prepare("
    SELECT id, titulo, descripcion, start, end
    FROM eventos
    WHERE usuario_id = ?
    AND start >= ? AND start <= ?
    ORDER BY start ASC
");
$stmtTomorrowEvents->execute([$user_id, $tomorrow_start, $tomorrow_end]);
$tomorrowEvents = $stmtTomorrowEvents->fetchAll(PDO::FETCH_ASSOC);

// 3. System Status
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tareas WHERE usuario_id = ? AND estado = 'pendiente'");
$stmtCount->execute([$user_id]);
$pendingCount = $stmtCount->fetchColumn();

// 4. Projects List
$stmtProjects = $pdo->prepare("
    SELECT id, nombre 
    FROM proyectos 
    WHERE organizacion_id = ? 
    AND (user_id IS NULL OR user_id = ?)
");
$stmtProjects->execute([$_SESSION['organizacion_id'] ?? 1, $user_id]);
$projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => [
        'user_profile' => [
            'name' => $_SESSION['usuario_nombre'] ?? 'Usuario',
            'role' => $_SESSION['usuario_rol'] ?? 'usuario',
            'org' => $_SESSION['organizacion_nombre'] ?? 'Personal'
        ],
        'today' => [
            'urgent_tasks' => $urgentTasks,
            'upcoming_events' => $upcomingEvents,
        ],
        'tomorrow' => [
            'tasks' => $tomorrowTasks,
            'events' => $tomorrowEvents
        ],
        'pending_total' => $pendingCount,
        'projects' => $projects,
        'current_time' => $now
    ]
]);

} catch (Throwable $e) {
    file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] get_context.php FATAL: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'status' => 'error',
        'message' => 'Context Error: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()
    ]);
}
?>
