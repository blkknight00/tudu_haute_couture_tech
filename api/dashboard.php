<?php
// Force error reporting for production debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep 0 to avoid breaking JSON, but we will catch errors

// CORS - More flexible for production subfolders
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header("Access-Control-Allow-Origin: " . ($origin ?: "*"));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once '../config.php';
    require_once 'auth_middleware.php';

    // Polyfill session via JWT
    checkAuth();

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
        exit();
    }

    $usuario_actual_id = $_SESSION['usuario_id'];
    $usuario_actual_rol = $_SESSION['usuario_rol'] ?? '';
    $org_id = $_SESSION['organizacion_id'] ?? 1;
    $is_global = ($usuario_actual_rol === 'super_admin' || $usuario_actual_rol === 'admin_global');

    // Filtros
    $proyecto_id = isset($_GET['proyecto_id']) && $_GET['proyecto_id'] !== 'null' ? intval($_GET['proyecto_id']) : null;
    $filtro_vista = isset($_GET['vista']) ? $_GET['vista'] : 'publico';

    // --- KPIs ---
    $sql_kpi = "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN estado = 'en_progreso' THEN 1 END) as en_progreso,
        COUNT(CASE WHEN estado = 'completado' THEN 1 END) as completados
        FROM tareas
        WHERE (organizacion_id = ? OR ? = 1) AND estado != 'archivado'";

    $params_kpi = [$org_id, $is_global ? 1 : 0];
    if ($filtro_vista == 'personal') {
        $sql_kpi .= " AND visibility = 'private' AND usuario_id = ?";
        $params_kpi[] = $usuario_actual_id;
    } else {
        $sql_kpi .= " AND (visibility = 'public' OR (visibility = 'private' AND usuario_id = ?))";
        $params_kpi[] = $usuario_actual_id;
    }
    if ($proyecto_id) {
        $sql_kpi .= " AND proyecto_id = ?";
        $params_kpi[] = $proyecto_id;
    }

    $stmt_contadores = $pdo->prepare($sql_kpi);
    $stmt_contadores->execute($params_kpi);
    $contadores = $stmt_contadores->fetch(PDO::FETCH_ASSOC);

    // --- Alert system ---
    $sql_alertas = "SELECT t.id, t.titulo, t.fecha_termino, p.nombre as proyecto_nombre 
                    FROM tareas t
                    JOIN tarea_asignaciones ta ON t.id = ta.tarea_id
                    JOIN proyectos p ON t.proyecto_id = p.id
                    WHERE (t.organizacion_id = ? OR ? = 1) AND ta.usuario_id = ? 
                    AND t.estado NOT IN ('completado', 'archivado') 
                    AND t.fecha_termino BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    $stmt_alertas = $pdo->prepare($sql_alertas);
    $stmt_alertas->execute([$org_id, $is_global ? 1 : 0, $usuario_actual_id]);
    $tareas_por_vencer = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);

    // --- Quick Views ---
    // Vence Hoy
    $vence_hoy_sql = "SELECT t.id, t.titulo, t.estado, t.proyecto_id, p.nombre as proyecto_nombre,
                      (SELECT COUNT(*) FROM recursos r WHERE r.tarea_id = t.id) as files_count 
                       FROM tareas t 
                       JOIN proyectos p ON t.proyecto_id = p.id
                       WHERE (t.organizacion_id = ? OR ? = 1) AND t.fecha_termino = CURDATE() AND t.estado != 'completado' ";
    $params_vh = [$org_id, $is_global ? 1 : 0, $usuario_actual_id];
    $vence_hoy_sql .= " AND (t.visibility = 'public' OR (t.visibility = 'private' AND t.usuario_id = ?))";
    if ($proyecto_id) {
        $vence_hoy_sql .= " AND t.proyecto_id = ?";
        $params_vh[] = $proyecto_id;
    }
    $vence_hoy_sql .= " ORDER BY t.fecha_creacion DESC LIMIT 5";
    $stmt_vence_hoy = $pdo->prepare($vence_hoy_sql);
    $stmt_vence_hoy->execute($params_vh);
    $tareas_vence_hoy = $stmt_vence_hoy->fetchAll(PDO::FETCH_ASSOC);

    // Vence Semana - Using DATE_ADD for better compatibility
    $vence_semana_sql = "SELECT t.id, t.titulo, t.fecha_termino, t.proyecto_id, p.nombre as proyecto_nombre,
                         (SELECT COUNT(*) FROM recursos r WHERE r.tarea_id = t.id) as files_count 
                          FROM tareas t 
                          JOIN proyectos p ON t.proyecto_id = p.id
                          WHERE (t.organizacion_id = ? OR ? = 1) AND t.fecha_termino >= CURDATE() 
                          AND t.estado != 'completado'";
    $params_vs = [$org_id, $is_global ? 1 : 0, $usuario_actual_id];
    $vence_semana_sql .= " AND (t.visibility = 'public' OR (t.visibility = 'private' AND t.usuario_id = ?))";
    if ($proyecto_id) {
        $vence_semana_sql .= " AND t.proyecto_id = ?";
        $params_vs[] = $proyecto_id;
    }
    $vence_semana_sql .= " ORDER BY t.fecha_termino ASC LIMIT 5";
    $stmt_vence_semana = $pdo->prepare($vence_semana_sql);
    $stmt_vence_semana->execute($params_vs);
    $tareas_vence_semana = $stmt_vence_semana->fetchAll(PDO::FETCH_ASSOC);

    // Nuevas Hoy
    $nuevas_hoy_sql = "SELECT t.id, t.titulo, t.proyecto_id, p.nombre as proyecto_nombre,
                       (SELECT COUNT(*) FROM recursos r WHERE r.tarea_id = t.id) as files_count 
                        FROM tareas t 
                        JOIN proyectos p ON t.proyecto_id = p.id
                        WHERE (t.organizacion_id = ? OR ? = 1)";
    $params_nh = [$org_id, $is_global ? 1 : 0, $usuario_actual_id];
    $nuevas_hoy_sql .= " AND (t.visibility = 'public' OR (t.visibility = 'private' AND t.usuario_id = ?))";
    if ($proyecto_id) {
        $nuevas_hoy_sql .= " AND t.proyecto_id = ?";
        $params_nh[] = $proyecto_id;
    }
    $nuevas_hoy_sql .= " ORDER BY t.fecha_creacion DESC LIMIT 5";
    $stmt_nuevas_hoy = $pdo->prepare($nuevas_hoy_sql);
    $stmt_nuevas_hoy->execute($params_nh);
    $tareas_nuevas_hoy = $stmt_nuevas_hoy->fetchAll(PDO::FETCH_ASSOC);

    $json = json_encode([
        'status' => 'success',
        'kpis' => $contadores,
        'alerts' => $tareas_por_vencer,
        'quick_views' => [
            'due_today' => $tareas_vence_hoy,
            'due_week' => $tareas_vence_semana,
            'new_today' => $tareas_nuevas_hoy
        ]
    ]);
    
    if ($json === false) {
        throw new Exception("Error al codificar JSON: " . json_last_error_msg());
    }
    echo $json;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error en el servidor',
        'debug' => $e->getMessage()
    ]);
}
