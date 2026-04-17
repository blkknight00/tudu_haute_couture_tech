<?php
require_once 'config.php';
// session_start(); // Iniciada en config.php

// Verificar login
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

// Obtener usuario actual
$usuario_actual_id = $_SESSION['usuario_id'];
$usuario_actual_nombre = $_SESSION['usuario_nombre'];
$usuario_actual_username = $_SESSION['usuario_username'];
$usuario_actual_rol = trim($_SESSION['usuario_rol']); // Limpiar espacios
$usuario_actual_foto = $_SESSION['usuario_foto'] ?? null;

// --- SISTEMA DE ALERTAS DE VENCIMIENTO ---
$sql_alertas = "SELECT t.id, t.titulo, t.fecha_termino FROM tareas t
                JOIN tarea_asignaciones ta ON t.id = ta.tarea_id
                WHERE ta.usuario_id = ? 
                AND t.estado NOT IN ('completado', 'archivado') 
                AND t.fecha_termino BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
$stmt_alertas = $pdo->prepare($sql_alertas);
$stmt_alertas->execute([$usuario_actual_id]);
$tareas_por_vencer = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);

foreach ($tareas_por_vencer as $tarea_alerta) {
    $stmt_check = $pdo->prepare("SELECT id FROM notificaciones 
                                 WHERE usuario_id_destino = ? 
                                 AND tarea_id = ? 
                                 AND tipo = 'vencimiento' 
                                 AND DATE(fecha_creacion) = CURDATE()");
    $stmt_check->execute([$usuario_actual_id, $tarea_alerta['id']]);
    
    if ($stmt_check->rowCount() == 0) {
        $fecha_obj = new DateTime($tarea_alerta['fecha_termino']);
        $hoy_obj = new DateTime('today');
        $es_hoy = $fecha_obj->format('Y-m-d') === $hoy_obj->format('Y-m-d');
        
        $mensaje = $es_hoy 
            ? "⚠️ La tarea '{$tarea_alerta['titulo']}' vence HOY." 
            : "⏰ La tarea '{$tarea_alerta['titulo']}' vence mañana.";
            
        $stmt_ins = $pdo->prepare("INSERT INTO notificaciones (usuario_id_destino, usuario_id_origen, tarea_id, tipo, mensaje) VALUES (?, ?, ?, 'vencimiento', ?)");
        $stmt_ins->execute([$usuario_actual_id, $usuario_actual_id, $tarea_alerta['id'], $mensaje]);
    }
}

// Filtros
$proyecto_id = isset($_GET['proyecto_id']) ? $_GET['proyecto_id'] : null;

// Determinar si el proyecto actual es privado (para el botón de "Agregar Primera Idea")
$is_current_project_private = false;
if ($proyecto_id) {
    $stmt_check_priv = $pdo->prepare("SELECT user_id FROM proyectos WHERE id = ?");
    $stmt_check_priv->execute([$proyecto_id]);
    if ($stmt_check_priv->fetchColumn()) $is_current_project_private = true;
}

$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$filtro_usuario = isset($_GET['usuario_id']) ? $_GET['usuario_id'] : null;
$filtro_prioridad = isset($_GET['prioridad']) ? $_GET['prioridad'] : 'todas';
$filtro_vista = isset($_GET['vista']) ? $_GET['vista'] : 'publico';
$filtro_fecha_vencimiento = isset($_GET['fecha_vencimiento']) && !empty($_GET['fecha_vencimiento']) ? $_GET['fecha_vencimiento'] : null;

// Obtener proyectos
$params_proyectos = [];
$sql_proyectos = "SELECT * FROM proyectos WHERE ";
if ($filtro_vista == 'personal') {
    $sql_proyectos .= "user_id = ?";
    $params_proyectos[] = $usuario_actual_id;
} else {
    $sql_proyectos .= "user_id IS NULL";
}
$sql_proyectos .= " ORDER BY nombre";
$stmt_proyectos = $pdo->prepare($sql_proyectos);
$stmt_proyectos->execute($params_proyectos);
$proyectos = $stmt_proyectos->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuarios (para filtros)
$stmt_usuarios = $pdo->query("SELECT id, username, nombre, telefono, foto_perfil FROM usuarios WHERE activo = 1 ORDER BY nombre");
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

// Construir consulta base para tareas
$sql = "SELECT t.*, p.nombre as proyecto_nombre, t.visibility, t.fecha_termino
        FROM tareas t 
        JOIN proyectos p ON t.proyecto_id = p.id 
        WHERE ";
$params = [];

if ($filtro_vista == 'personal') {
    $sql .= "t.visibility = 'private' AND t.usuario_id = ?";
    $params[] = $usuario_actual_id;
} else {
    $sql .= "t.visibility = 'public'";
}

if ($proyecto_id) {
    $sql .= " AND t.proyecto_id = ?";
    $params[] = $proyecto_id;
}

if ($filtro_estado != 'todos') {
    $sql .= " AND t.estado = ? ";
    $params[] = $filtro_estado;
}

if ($filtro_usuario) {
    $sql .= " AND EXISTS (SELECT 1 FROM tarea_asignaciones ta_f WHERE ta_f.tarea_id = t.id AND ta_f.usuario_id = ?)";
    $params[] = $filtro_usuario;
}

if ($filtro_prioridad != 'todas') {
    $sql .= " AND t.prioridad = ? ";
    $params[] = $filtro_prioridad;
}

if ($filtro_fecha_vencimiento) {
    $sql .= " AND DATE(t.fecha_termino) = ? ";
    $params[] = $filtro_fecha_vencimiento;
}

$sql .= " AND t.estado != 'archivado' ";

$sql .= " ORDER BY 
            FIELD(t.estado, 'pendiente', 'en_progreso', 'completado'),
            CASE WHEN t.fecha_termino IS NULL THEN 1 ELSE 0 END,
            t.fecha_termino ASC,
            FIELD(t.prioridad, 'alta', 'media', 'baja'),
            t.fecha_creacion DESC
        ";

$stmt_tareas = $pdo->prepare($sql);
$stmt_tareas->execute($params);
$tareas = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);

// Obtener asignaciones para las tareas visibles
$asignaciones_por_tarea = [];
if (!empty($tareas)) {
    $tarea_ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($tarea_ids), '?'));
    $sql_asig = "SELECT ta.tarea_id, u.id, u.nombre, u.foto_perfil FROM tarea_asignaciones ta JOIN usuarios u ON ta.usuario_id = u.id WHERE ta.tarea_id IN ($placeholders)";
    $stmt_asig = $pdo->prepare($sql_asig);
    $stmt_asig->execute($tarea_ids);
    foreach ($stmt_asig->fetchAll(PDO::FETCH_ASSOC) as $asig) {
        $asignaciones_por_tarea[$asig['tarea_id']][] = $asig;
    }
}

// Obtener subtareas
$subtareas_por_tarea = [];
if (!empty($tareas)) {
    $tarea_ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($tarea_ids), '?'));
    
    $sql_sub = "SELECT * FROM subtareas WHERE tarea_id IN ($placeholders) ORDER BY id ASC";
    $stmt_sub = $pdo->prepare($sql_sub);
    $stmt_sub->execute($tarea_ids);
    foreach ($stmt_sub->fetchAll(PDO::FETCH_ASSOC) as $sub) {
        $subtareas_por_tarea[$sub['tarea_id']][] = $sub;
    }
}

// Obtener etiquetas
$etiquetas_por_tarea = [];
if (!empty($tareas)) {
    $tarea_ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($tarea_ids), '?'));
    
    $sql_tags = "SELECT te.tarea_id, e.nombre, e.color FROM tarea_etiquetas te JOIN etiquetas e ON te.etiqueta_id = e.id WHERE te.tarea_id IN ($placeholders)";
    $stmt_tags = $pdo->prepare($sql_tags);
    $stmt_tags->execute($tarea_ids);
    foreach ($stmt_tags->fetchAll(PDO::FETCH_ASSOC) as $tag) {
        $etiquetas_por_tarea[$tag['tarea_id']][] = $tag;
    }
}

// Obtener archivos adjuntos
$archivos_por_tarea = [];
// ... (existing logic for files could be here or I assume it's below) ...

// Obtener conteo y estado de notas
$notas_info = [];
if (!empty($tareas)) {
    $tarea_ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($tarea_ids), '?'));
    
    $sql_notas = "SELECT tarea_id, COUNT(*) as total, MAX(fecha_creacion) as ultima_nota FROM notas_tareas WHERE tarea_id IN ($placeholders) GROUP BY tarea_id";
    $stmt_notas = $pdo->prepare($sql_notas);
    $stmt_notas->execute($tarea_ids);
    foreach ($stmt_notas->fetchAll(PDO::FETCH_ASSOC) as $nota) {
        $notas_info[$nota['tarea_id']] = $nota;
    }
}
if (!empty($tareas)) {
    $tarea_ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($tarea_ids), '?'));
    $sql_files = "SELECT tarea_id, id, nombre_original, nombre_archivo, tipo_archivo FROM archivos_adjuntos WHERE tarea_id IN ($placeholders)";
    $stmt_files = $pdo->prepare($sql_files);
    $stmt_files->execute($tarea_ids);
    foreach ($stmt_files->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $archivos_por_tarea[$f['tarea_id']][] = $f;
    }
}

// Verificar configuración
$stmt_config = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('TUDU_LICENSE_KEY', 'APP_STATUS', 'APP_LOGO')");
$config = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);
$app_logo = $config['APP_LOGO'] ?? 'assets/logo_tudu.png'; // Default logo
// Si no hay logo custom y no existe el default, usar texto o un placeholder
if (empty($config['APP_LOGO']) && !file_exists('assets/logo_tudu.png')) {
    $app_logo = null;
}

// Obtener todas las etiquetas existentes para el selector
$stmt_all_tags = $pdo->query("SELECT id, nombre, color FROM etiquetas ORDER BY nombre");
$todas_las_etiquetas = $stmt_all_tags->fetchAll(PDO::FETCH_ASSOC);

// Contadores (KPIs) - AHORA SON DINÁMICOS
$sql_kpi = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
    COUNT(CASE WHEN estado = 'en_progreso' THEN 1 END) as en_progreso,
    COUNT(CASE WHEN estado = 'completado' THEN 1 END) as completados
    FROM tareas
    WHERE estado != 'archivado'";

$params_kpi = [];

// Aplicar filtro de vista
if ($filtro_vista == 'personal') {
    $sql_kpi .= " AND visibility = 'private' AND usuario_id = ?";
    $params_kpi[] = $usuario_actual_id;
} else {
    $sql_kpi .= " AND visibility = 'public'";
}

// Aplicar filtro de proyecto si está activo
if ($proyecto_id) {
    $sql_kpi .= " AND proyecto_id = ?";
    $params_kpi[] = $proyecto_id;
}

$stmt_contadores = $pdo->prepare($sql_kpi);
$stmt_contadores->execute($params_kpi);
$contadores = $stmt_contadores->fetch(PDO::FETCH_ASSOC);

// Contador de archivados (este sí es global)
$stmt_archivados = $pdo->query("SELECT COUNT(*) FROM tareas WHERE estado = 'archivado'");
$count_archivados = $stmt_archivados->fetchColumn();


// Vistas rápidas: Hoy y Próximo
$vence_hoy_sql = "SELECT id, titulo, estado, proyecto_id FROM tareas WHERE fecha_termino = CURDATE() AND estado != 'completado' ORDER BY fecha_creacion DESC LIMIT 5";
$stmt_vence_hoy = $pdo->prepare($vence_hoy_sql);
$stmt_vence_hoy->execute();
$tareas_vence_hoy = $stmt_vence_hoy->fetchAll(PDO::FETCH_ASSOC);

$vence_semana_sql = "SELECT id, titulo, fecha_termino, proyecto_id FROM tareas WHERE fecha_termino BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 7 DAY AND estado != 'completado' ORDER BY fecha_termino ASC LIMIT 5";
$stmt_vence_semana = $pdo->prepare($vence_semana_sql);
$stmt_vence_semana->execute();
$tareas_vence_semana = $stmt_vence_semana->fetchAll(PDO::FETCH_ASSOC);

// Tareas nuevas (creadas hoy)
$nuevas_hoy_sql = "SELECT id, titulo, proyecto_id FROM tareas WHERE DATE(fecha_creacion) = CURDATE() ORDER BY fecha_creacion DESC LIMIT 5";
$stmt_nuevas_hoy = $pdo->prepare($nuevas_hoy_sql);
$stmt_nuevas_hoy->execute();
$tareas_nuevas_hoy = $stmt_nuevas_hoy->fetchAll(PDO::FETCH_ASSOC);

// Para construir los enlaces, necesitamos los nombres de los proyectos
$proyectos_map = array_column($proyectos, 'nombre', 'id');

// Construir la URL base para los enlaces de WhatsApp
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
$app_base_url = $protocol . $domainName . $base_path;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TuDu</title>
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">

    <!-- PWA Meta Tags -->
    <meta name="application-name" content="TuDu">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="TuDu">
    <meta name="description" content="Sistema de To-Do list colaborativo">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-TileColor" content="#2c3e50">
    <meta name="msapplication-tap-highlight" content="no">
    <meta name="theme-color" content="#D97941">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <!-- Chart.js para gráficas -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Shepherd.js para Tour Guiado -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/css/shepherd.css"/>
    <script src="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/js/shepherd.min.js"></script>
    <script src="tour.js"></script>

    <!-- Estilos personalizados (con cache buster) -->
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="manifest" href="manifest.json">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* FAILSAFE: Garantizar visibilidad en móvil sin importar style.css externo */
        @media (max-width: 991px) {
            .context-bar {
                display: block !important;
                visibility: visible !important;
            }
            .header-controls {
                display: flex !important;
                visibility: visible !important;
            }
        }



        /* --- PALETA DE COLORES (NUEVA) --- */
        :root, [data-bs-theme="light"] {
            --background-color: #E2E5E9;
            --content-background: #F4F9fC;
            --column-background: #EDF0F2;
            --accent-color: #D97941;
            --accent-color-hover: #C86A35;
            --text-color: #212529;
            --secondary-text-color: #6c757d;
            --border-color: #dee2e6;
        }

        [data-bs-theme="dark"] {
            --background-color: #0F1C2E;
            --content-background: #152B47;
            --column-background: #1E3A5F;
            --text-color: #E8ECF0;
            --secondary-text-color: #C0C8D0;
            --border-color: #2A4A6F;
            --accent-color: #D97941;
        }

        /* --- ESTILOS BASE --- */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            padding-top: 150px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* --- HEADER Y BARRA DE CONTEXTO --- */
        .main-header {
            background-image: linear-gradient(to right, var(--background-color), var(--content-background));
            border-bottom: 1px solid var(--border-color);
        }
        [data-bs-theme="light"] .main-header .btn-outline-light {
            color: var(--accent-color) !important;
        }
        [data-bs-theme="light"] .main-header .btn-outline-light:hover {
            color: var(--accent-color-hover) !important;
        }
        .context-bar {
            background-color: var(--content-background);
            border-bottom: 1px solid var(--border-color);
        }
        .context-bar .btn-outline-secondary {
            color: var(--secondary-text-color);
            border-color: var(--border-color);
        }
        .context-bar .btn-outline-secondary:hover {
            background-color: var(--column-background);
            border-color: var(--accent-color);
            color: var(--accent-color);
        }
        .context-bar .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        .context-bar .btn-primary:hover {
            background-color: var(--accent-color-hover);
            border-color: var(--accent-color-hover);
        }
        .context-bar .form-select {
            background-color: var(--column-background);
            color: var(--text-color);
            border-color: var(--border-color);
        }

        /* --- TARJETAS PRINCIPALES --- */
        .card {
            background-color: var(--content-background);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: 1rem;
        }
        .card .text-secondary, .card .text-muted {
            color: var(--secondary-text-color) !important;
        }
        .list-group-item {
            background-color: transparent;
            border-color: var(--border-color);
        }

        /* --- TARJETAS DE TAREAS (Estilo compacto y profesional) --- */
        .task-card {
            background: var(--column-background);
            border: 1px solid var(--border-color);
            border-left-width: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 0.8rem;
            padding: 0.8rem 1rem;
        }
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Colores de borde por estado (como en Kanban) */
        .task-card.card-pendiente { border-left-color: #ff3b30; }
        .task-card.card-progreso { border-left-color: #ff9500; }
        .task-card.card-completado { border-left-color: #34c759; }

        /* --- Estilos para Badges de Estado --- */
        .status-badge.bg-pendiente { background-color: #ff3b30 !important; color: white !important; }
        .status-badge.bg-en_progreso { background-color: #ff9500 !important; color: white !important; }
        .status-badge.bg-completado { background-color: #34c759 !important; color: white !important; }

        /* --- KPIs Y VISTAS RÁPIDAS (Estilo Compacto) --- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.75rem;
        }
        .kpi-card {
            background-color: var(--column-background);
            border: 1px solid var(--border-color);
            padding: 0.75rem;
            border-radius: 12px;
            text-align: center;
        }
        .kpi-number {
            font-size: 1.75rem;
            font-weight: 600;
            display: block;
            line-height: 1.1;
            color: var(--text-color);
        }
        .kpi-label {
            font-size: 0.8rem;
            color: var(--secondary-text-color);
        }
        .quick-view-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.5rem;
        }
        .quick-view-card {
            background-color: var(--column-background);
            border: 1px solid var(--border-color);
        }
        .quick-view-card .list-group-item {
            background-color: var(--content-background);
            border-color: var(--border-color);
            color: var(--secondary-text-color);
        }
        .quick-view-card .list-group-item:hover {
            background-color: var(--background-color);
        }

        /* --- MODALES --- */
        .modal-content {
            background-color: var(--content-background);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }
        .modal-header {
            border-bottom-color: var(--border-color);
        }
        .modal-footer {
            border-top-color: var(--border-color);
        }
        .form-control, .form-select {
            background-color: var(--background-color);
            color: var(--text-color);
            border-color: var(--border-color);
        }
        .form-control:focus, .form-select:focus {
            background-color: var(--background-color);
            color: var(--text-color);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(217, 121, 65, 0.25);
        }
        .form-control::placeholder {
            color: var(--secondary-text-color);
            opacity: 0.7;
        }
        .dropdown-menu {
            background-color: var(--column-background);
            border-color: var(--border-color);
        }
        .dropdown-item {
            color: var(--secondary-text-color);
        }
        .dropdown-item:hover, .dropdown-item:focus {
            background-color: var(--content-background);
            color: var(--accent-color);
        }
        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        [data-bs-theme="light"] .btn-close {
            filter: none;
        }

        /* --- BOTONES --- */
        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        .btn-primary:hover {
            background-color: var(--accent-color-hover);
            border-color: var(--accent-color-hover);
        }
        .btn-success {
            background-color: #198754;
            border-color: #198754;
        }
        .btn-outline-secondary {
            color: var(--secondary-text-color);
            border-color: var(--border-color);
        }
        .btn-outline-secondary:hover {
            background-color: var(--column-background);
            color: var(--text-color);
            border-color: var(--border-color);
        }
        .user-menu .btn-light {
            background-color: var(--column-background);
            color: var(--text-color);
            border-color: var(--border-color);
        }
        [data-bs-theme="light"] .user-menu .btn-light {
             background-color: #f8f9fa;
             border-color: #dee2e6;
             color: #212529;
        }

        /* Ajuste para que todos los botones de la barra de contexto tengan la misma altura */
        .header-controls .btn {
            padding: 0.2rem 0.5rem;
            font-weight: 400;
        }

        .header-controls .btn,
        .header-controls .form-select,
        .header-controls label {
            font-size: 0.75rem;
        }

        /* --- RESPONSIVO PARA MÓVIL - MEJORADO (Horizontal Scroll) --- */
        @media (max-width: 991px) {
            body { padding-top: 70px !important; }
            .main-header { padding: 8px 0; }
            .header-logo img { height: 32px !important; }
            
            .user-menu .dropdown-toggle span { display: none; }
            .header-right .btn { padding: 4px 8px; } 
            .header-right .btn i { font-size: 1.1rem !important; }
            .user-menu .user-avatar { width: 30px; height: 30px; }
            
            /* BARRA DE CONTEXTO - MODIFICADO A 2 FILAS */
            .context-bar { 
                position: static !important;
                height: auto !important; 
                padding: 8px 0 !important;
                overflow: visible !important; /* Permitir dropdowns */
                border-bottom: 1px solid var(--border-color);
            }
            
            .context-bar::-webkit-scrollbar { display: none; }
            
            .context-bar .container {
                display: flex !important;
                flex-direction: column !important; /* Columna para agrupar en filas */
                width: 100% !important;
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .header-controls {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: wrap !important; /* Permitir 2 filas */
                gap: 8px !important;
                width: 100% !important;
                justify-content: center !important;
                visibility: visible !important;
                opacity: 1 !important;
            }

            /* Force visibility for children just in case */
            .header-controls > * {
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
            }

            /* Elementos individuales */
            
            /* Helper for explicit rows on mobile */
            .mobile-row {
                width: 100% !important;
                display: flex !important;
                justify-content: center !important;
                gap: 8px !important;
            }

            .project-view-selector {
                display: flex !important;
                flex-direction: row !important;
                align-items: center;
                margin-bottom: 0 !important;
                width: auto !important;
            }
            
            .project-view-selector label {
                display: none !important; /* Ocultar label "Proyecto:" para ahorrar espacio */
            }
            
            .project-view-selector select {
                width: auto !important;
                max-width: 150px;
                font-size: 0.85rem;
            }
            
            /* Botones compactos (Solo íconos o texto corto) */
            .view-switcher .btn,
            #btnVistaTablero,
            #btnNuevoProyecto,
            #btnNuevaIdea {
                white-space: nowrap;
            }
            
            /* Ajuste de KPIs en móvil */
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.5rem;
            }
            .kpi-card {
                padding: 0.5rem;
            }
            .kpi-number {
                font-size: 1.2rem;
            }
            .kpi-label {
                font-size: 0.65rem;
            }
        }

        /* Ajustes adicionales para pantallas muy pequeñas */
        @media (max-width: 576px) {
            .context-bar {
                min-height: 110px !important;
                padding: 8px 0 !important;
            }
            
            .header-controls {
                gap: 6px !important;
            }
            
            .project-view-selector label {
                font-size: 0.7rem;
            }
            
            .project-view-selector select {
                font-size: 0.8rem;
                padding: 6px 8px;
            }
            
            .view-switcher .btn,
            #btnVistaTablero,
            #btnNuevoProyecto,
            #btnNuevaIdea {
                padding: 6px 4px !important;
                font-size: 0.8rem !important;
            }
        }

        /* --- OTROS --- */
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .user-avatar-sm { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; margin-right: 4px; }
        .user-avatar-lg { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-color); }
        .highlight {
            animation: highlight-task 2s ease-out;
        }
        @keyframes highlight-task {
            0% {
                background-color: rgba(217, 121, 65, 0.3);
            }
            100% {
                background-color: transparent;
            }
        }

        .task-card.overdue {
            border-left-color: #dc3545 !important;
            animation: siren-glow 1.5s ease-in-out infinite;
        }

        @keyframes siren-glow {
            0% {
                box-shadow: 0 0 3px rgba(220, 53, 69, 0.4), inset 0 0 5px rgba(220, 53, 69, 0.3);
            }
            50% {
                box-shadow: 0 0 15px rgba(220, 53, 69, 0.7), inset 0 0 8px rgba(220, 53, 69, 0.5);
            }
            100% {
                box-shadow: 0 0 3px rgba(220, 53, 69, 0.4), inset 0 0 5px rgba(220, 53, 69, 0.3);
            }
        }

        /* Estilos para compactar la barra de filtros */
        .filter-bar-compact {
            font-size: 0.8rem;
            overflow-x: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .filter-bar-compact::-webkit-scrollbar {
            display: none;
        }
        .filter-bar-compact .form-select, .filter-bar-compact .form-control, .filter-bar-compact .btn {
            font-size: 0.8rem;
        }
        .filter-bar-compact #searchInput {
            max-width: 140px;
        }

        /* --- Estilos para etiquetas --- */
        .tag-badge {
            font-size: 0.75em;
            margin: 2px 4px 2px 0;
            border-radius: 4px;
        }

        .due-date-badge {
            font-size: 0.8em;
            font-weight: 500;
            padding: .3em .6em;
        }

        .user-badge {
            background-color: var(--background-color);
            color: var(--secondary-text-color);
            font-size: 0.8em;
            padding: .3em .6em;
        }

        /* --- Estilos para Autocompletado de Menciones --- */
        .suggestions-box {
            position: absolute;
            background: var(--content-background);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-height: 150px;
            overflow-y: auto;
            z-index: 1060; /* Mayor que el modal */
            display: none;
            min-width: 200px;
        }
        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        .suggestion-item:hover {
            background-color: var(--column-background);
            color: var(--accent-color);
        }
    </style>

    <script>
        // Aplicar tema inmediatamente
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();

        // Variables globales
        const appBaseUrl = '<?= $app_base_url ?>';
        const usuariosSistema = <?= json_encode(array_map(function($u) {
            return ['username' => $u['username'], 'nombre' => $u['nombre'], 'telefono' => $u['telefono'], 'foto_perfil' => $u['foto_perfil']];
        }, $usuarios)) ?>;

        // Funciones Helper Globales
        function escapeHtml(text) { 
            return text ? String(text).replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;") : ''; 
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString + 'Z');
            return `${date.getDate().toString().padStart(2,'0')}/${(date.getMonth()+1).toString().padStart(2,'0')} ${date.getHours().toString().padStart(2,'0')}:${date.getMinutes().toString().padStart(2,'0')}`;
        }

function renderNotaModal(nota) {
            const list = document.getElementById('modal-notas-list');
            if (!list) return;
            
            // Resaltar menciones y URLs
            let noteContent = escapeHtml(nota.nota).replace(/\n/g, '<br>');
            noteContent = noteContent.replace(/@(\w+)/g, '<span class="text-primary fw-bold">@$1</span>');
            // Convertir URLs a enlaces o imágenes
            noteContent = noteContent.replace(/((https?:\/\/|www\.)[^\s<]+)/g, function(url) {
                let href = url;
                if (!/^https?:\/\//i.test(url)) {
                    href = 'http://' + url;
                }
                
                // Detectar si es imagen
                if (url.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                    return '<br><a href="' + href + '" target="_blank"><img src="' + href + '" class="img-fluid rounded mt-2 border" style="max-height: 200px;" alt="Imagen adjunta"></a><br>';
                }
                
                return '<a href="' + href + '" target="_blank" rel="noopener noreferrer" class="text-break">' + url + '</a>';
            });

            const nombreMostrar = nota.usuario_nombre || nota.nombre_invitado || 'Invitado';

            const div = document.createElement('div');
            div.className = 'nota-item mb-2';
            div.innerHTML = `<div class="d-flex justify-content-between"><strong>${escapeHtml(nombreMostrar)}</strong><small class="text-muted">${formatDate(nota.fecha_creacion)}</small></div><p class="mb-0">${noteContent}</p>`;
            
            list.appendChild(div);
            list.scrollTop = list.scrollHeight;
        }

        function filtrarPorFecha(fecha) {
            const url = new URL(window.location);
            if (fecha) {
                url.searchParams.set('fecha_vencimiento', fecha);
            } else {
                url.searchParams.delete('fecha_vencimiento');
            }
            window.location.href = url.toString();
        }

        function shareTask(taskId) {
            fetch('generar_share_link.php?id=' + taskId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modalEl = document.getElementById('modalShare');
                        const input = document.getElementById('shareLinkInput');
                        const msg = document.getElementById('shareSuccessMsg');
                        
                        if(modalEl && input) {
                            input.value = data.url;
                            if(msg) { msg.style.display = 'none'; msg.style.opacity = '0'; }
                            const phoneInput = document.getElementById('whatsappPhone');
                            if(phoneInput) phoneInput.value = '';
                            const modal = new bootstrap.Modal(modalEl);
                            modal.show();
                        } else {
                            window.prompt("Copia este enlace:", data.url);
                        }
                    } else {
                        alert('Error al generar el enlace: ' + (data.error || 'Error desconocido.'));
                    }
                })
                .catch(error => {
                    console.error('Error al compartir:', error);
                    alert('No se pudo generar el enlace para compartir.');
                });
        }

        function copiarEnlace() {
            const input = document.getElementById('shareLinkInput');
            input.select();
            input.setSelectionRange(0, 99999); // Para móviles
            
            navigator.clipboard.writeText(input.value).then(() => {
                const msg = document.getElementById('shareSuccessMsg');
                if(msg) {
                    msg.style.display = 'block';
                    setTimeout(() => msg.style.opacity = '1', 10);
                    setTimeout(() => {
                        msg.style.opacity = '0';
                        setTimeout(() => msg.style.display = 'none', 500);
                    }, 2000);
                }
            }).catch(err => console.error('Error al copiar', err));
        }

        function enviarWhatsApp() {
            const phone = document.getElementById('whatsappPhone').value.replace(/[^0-9]/g, '');
            const url = document.getElementById('shareLinkInput').value;
            
            if (!phone || phone.length < 7) {
                alert('Por favor, ingresa un número de teléfono válido (con código de país).');
                return;
            }
            
            const message = encodeURIComponent("Hola, te comparto esta tarea: " + url);
            window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
        }

        function abrirModalTarea(defaultProjectId = null, visibility = 'public') {
            document.getElementById('modalTareaTitle').textContent = 'Agregar Nueva Idea';
            document.getElementById('formTarea').reset();
            document.getElementById('tarea_id').value = '';
            
            // Asegurar que los campos estén desbloqueados al crear nueva tarea
            document.getElementById('titulo').readOnly = false;
            document.getElementById('descripcion').readOnly = false;
            document.getElementById('titulo').style.backgroundColor = '';
            document.getElementById('descripcion').style.backgroundColor = '';
            if(document.getElementById('edit-lock-info')) document.getElementById('edit-lock-info').style.display = 'none';

            document.getElementById('fecha_termino_container').style.display = 'none';
            document.getElementById('lista-archivos-existentes').innerHTML = '';
            document.getElementById('prioridad').value = 'media';
            document.getElementById('estado_tarea').value = 'pendiente';

            // Limpiar selección de etiquetas
            const tagCheckboxes = document.querySelectorAll('.tag-checkbox');
            tagCheckboxes.forEach(cb => cb.checked = false);
            const hiddenTagsInput = document.getElementById('tags_ids_string');
            if(hiddenTagsInput) hiddenTagsInput.value = '';
            updateTagsButtonText(0);

            // Configurar visibilidad y cargar la lista correcta
            if (visibility === 'private') {
                document.getElementById('visibility_private').checked = true;
                document.getElementById('visibility_public').checked = false;
                actualizarProyectosModal('personal', defaultProjectId);
            } else {
                document.getElementById('visibility_public').checked = true;
                document.getElementById('visibility_private').checked = false;
                actualizarProyectosModal('public', defaultProjectId);
            }
            
            // Preseleccionar usuario actual en el multiselect
            const usuariosSelect = document.getElementById('usuarios_ids');
            if (usuariosSelect) {
                for (let i = 0; i < usuariosSelect.options.length; i++) {
                    if (usuariosSelect.options[i].value == '<?= $usuario_actual_id ?>') {
                        usuariosSelect.options[i].selected = true;
                    } else {
                        usuariosSelect.options[i].selected = false;
                    }
                }
            }

            const modal = new bootstrap.Modal(document.getElementById('modalTarea'));
            modal.show();
        }
        
        function editarTarea(tareaId) {
            fetch('obtener_tarea.php?id=' + tareaId)
                .then(response => {
                    if (!response.ok) throw new Error('Error al obtener los datos de la tarea.');
                    return response.json();
                })
                .then(data => {
                    document.getElementById('modalTareaTitle').textContent = 'Editar Idea';
                    document.getElementById('formTarea').reset();
                    document.getElementById('tarea_id').value = data.id;
                    document.getElementById('titulo').value = data.titulo || '';
                    document.getElementById('descripcion').value = data.descripcion || '';
                    document.getElementById('proyecto_id').value = data.proyecto_id || '';

                    // --- LÓGICA DE PERMISOS DE EDICIÓN ---
                    const tituloInput = document.getElementById('titulo');
                    const descTextarea = document.getElementById('descripcion');
                    const editLockInfo = document.getElementById('edit-lock-info');

                    // Resetear primero
                    tituloInput.readOnly = false;
                    descTextarea.readOnly = false;
                    tituloInput.style.backgroundColor = '';
                    descTextarea.style.backgroundColor = '';
                    if(editLockInfo) editLockInfo.style.display = 'none';

                    const currentUserIsOwner = data.usuario_id == <?= $usuario_actual_id ?>;
                    const currentUserIsAdmin = ['admin', 'super_admin'].includes('<?= $usuario_actual_rol ?>');

                    if (!currentUserIsOwner && !currentUserIsAdmin) {
                        tituloInput.readOnly = true;
                        descTextarea.readOnly = true;
                        tituloInput.style.backgroundColor = 'var(--column-background)';
                        descTextarea.style.backgroundColor = 'var(--column-background)';
                        if(editLockInfo) {
                            editLockInfo.innerHTML = '<i class="bi bi-lock-fill"></i> Solo el creador o un admin pueden editar el título y descripción.';
                            editLockInfo.style.display = 'block';
                        }
                    }
                    
                    // CORRECCIÓN: Asegurarse de que el estado se muestre correctamente
                    const estadoSelect = document.getElementById('estado_tarea');
                    if (estadoSelect) {
                        estadoSelect.value = data.estado || 'pendiente';
                    }

                    // Preseleccionar etiquetas
                    const tagCheckboxes = document.querySelectorAll('.tag-checkbox');
                    const selectedTagIds = [];
                    if (tagCheckboxes && data.tags_ids) {
                        tagCheckboxes.forEach(cb => {
                            cb.checked = data.tags_ids.includes(parseInt(cb.value));
                            if (cb.checked) {
                                selectedTagIds.push(cb.value);
                            }
                        });
                        document.getElementById('tags_ids_string').value = selectedTagIds.join(',');
                        updateTagsButtonText(selectedTagIds.length);
                    } else {
                        updateTagsButtonText(0);
                    }
                    
                    // CORRECCIÓN: Preseleccionar usuarios asignados - AJUSTADO
                    const usuariosSelect = document.getElementById('usuarios_ids');
                    if (usuariosSelect && data.asignados) {
                        // Primero deseleccionar todos
                        for (let i = 0; i < usuariosSelect.options.length; i++) {
                            usuariosSelect.options[i].selected = false;
                        }
                        
                        // Luego seleccionar solo los asignados
                        if (Array.isArray(data.asignados)) {
                            data.asignados.forEach(userId => {
                                for (let i = 0; i < usuariosSelect.options.length; i++) {
                                    if (parseInt(usuariosSelect.options[i].value) === userId) {
                                        usuariosSelect.options[i].selected = true;
                                        break;
                                    }
                                }
                            });
                        }
                    }

                    // Fecha de término
                    if (data.fecha_termino) {
                        document.getElementById('check_fecha_termino').checked = true;
                        document.getElementById('fecha_termino_container').style.display = 'block';
                        document.getElementById('fecha_termino').value = data.fecha_termino;
                    } else {
                        document.getElementById('check_fecha_termino').checked = false;
                        document.getElementById('fecha_termino_container').style.display = 'none';
                    }
                    
                    // Prioridad
                    const prioridadSelect = document.getElementById('prioridad');
                    if (prioridadSelect) {
                        prioridadSelect.value = data.prioridad || 'media';
                    }

                    // Visibilidad
                    const visibility = data.visibility || 'public';
                    const radioPublic = document.getElementById('visibility_public');
                    const radioPrivate = document.getElementById('visibility_private');
                    if (radioPublic && radioPrivate) {
                        if (visibility === 'private') {
                            radioPrivate.checked = true;
                            radioPublic.checked = false;
                        } else {
                            radioPublic.checked = true;
                            radioPrivate.checked = false;
                        }
                    }

                    // Mostrar archivos existentes
                    const listaArchivos = document.getElementById('lista-archivos-existentes');
                    listaArchivos.innerHTML = '';
                    if (data.archivos && data.archivos.length > 0) {
                        let html = '<label class="form-label mt-2">Archivos adjuntos:</label>';
                        html += '<small class="text-muted d-block mb-2"><i class="bi bi-info-circle"></i> Haz clic en el archivo para verlo o descargarlo.</small><ul class="list-group mb-3">';
                        data.archivos.forEach(file => {
                            const fileUrl = `uploads/${escapeHtml(file.nombre_archivo)}`;
                            const fileName = escapeHtml(file.nombre_original);
                            const fileType = escapeHtml(file.tipo_archivo || '');
                            let linkHtml;

                            if (fileType.startsWith('image/') || fileType === 'application/pdf') {
                                linkHtml = `<a href="#" onclick="event.preventDefault(); openLightbox('${fileUrl}', '${fileName}', '${fileType}')" class="text-decoration-none text-truncate" style="max-width: 80%; cursor: pointer;">
                                                <i class="bi bi-eye-fill text-info"></i> ${fileName}
                                            </a>`;
                            } else {
                                linkHtml = `<a href="${fileUrl}" target="_blank" class="text-decoration-none text-truncate" style="max-width: 80%;">
                                                <i class="bi bi-download"></i> ${fileName}
                                            </a>`;
                            }

                            html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                        ${linkHtml}
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="eliminarArchivo(${file.id}, this.closest('li'))" title="Eliminar archivo"><i class="bi bi-x-lg"></i></button>
                                     </li>`;
                        });
                        html += '</ul>';
                        listaArchivos.innerHTML = html;
                    }

                    // Mostrar notas existentes
                    const listaNotas = document.getElementById('modal-notas-list');
                    listaNotas.innerHTML = '';
                    if (data.notas && data.notas.length > 0) {
                        data.notas.forEach(nota => renderNotaModal(nota));
                    } else {
                        listaNotas.innerHTML = '<p class="text-muted text-center small my-2">No hay comentarios aún.</p>';
                    }

                    const modal = new bootstrap.Modal(document.getElementById('modalTarea'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error en fetch de editarTarea:', error);
                    alert('No se pudo cargar la información de la tarea. Revisa la consola para más detalles.');
                });
        }
       
        function eliminarTarea(tareaId) {
            if (confirm('¿Estás seguro de que quieres eliminar esta tarea?')) {
                sessionStorage.setItem('scrollPos', window.scrollY);
                window.location.href = 'eliminar_tarea.php?id=' + tareaId;
            }
        }
        
        function archivarTarea(tareaId) {
            if (confirm('¿Deseas archivar esta tarea? Se moverá a la sección de Archivo.')) {
                sessionStorage.setItem('scrollPos', window.scrollY);
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'archivar_tarea.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tarea_id';
                input.value = tareaId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function abrirModalUsuarios() {
            const modal = new bootstrap.Modal(document.getElementById('modalUsuarios'));
            modal.show();
        }
        
        function abrirModalUsuario() {
            document.getElementById('modalUsuarioTitle').textContent = 'Nuevo Usuario';
            document.getElementById('formUsuario').reset();
            document.getElementById('usuario_id_edit').value = '';
            document.getElementById('preview_foto').src = 'https://via.placeholder.com/100?text=Sin+Foto';
            const modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
            modal.show();
        }
        
        function editarUsuario(usuarioId) {
            fetch('obtener_usuario.php?id=' + usuarioId)
                .then(response => {
                    if (!response.ok) throw new Error('Error al obtener los datos del usuario.');
                    return response.json();
                })
                .then(data => {
                    document.getElementById('modalUsuarioTitle').textContent = 'Editar Usuario';
                    document.getElementById('usuario_id_edit').value = data.id;
                    document.getElementById('usuario_nombre').value = data.nombre;
                    document.getElementById('usuario_username').value = data.username;
                    document.getElementById('usuario_email').value = data.email;
                    document.getElementById('usuario_telefono').value = data.telefono;
                    const rolSelect = document.getElementById('usuario_rol');
                    if (rolSelect) rolSelect.value = data.rol;
                    document.getElementById('usuario_password').value = '';

                    // Previsualizar foto
                    const preview = document.getElementById('preview_foto');
                    if (preview) {
                        preview.src = data.foto_perfil ? data.foto_perfil : 'https://via.placeholder.com/100?text=Sin+Foto';
                    }

                    const modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
                    modal.show();
                })
                .catch(error => console.error('Error:', error));
        }
        
        function abrirModalLicencia() {
            cargarConfiguracionLicencia();
            const modal = new bootstrap.Modal(document.getElementById('modalLicencia'));
            modal.show();
        }

        function cargarConfiguracionLicencia() {
            fetch('admin_config.php')
                .then(r => r.json())
                .then(data => {
                    if(data.max_users) document.getElementById('inputMaxUsuariosLicencia').value = data.max_users;
                    if(data.current_users !== undefined) {
                        document.getElementById('usersCountDisplayLicencia').textContent = `${data.current_users} / ${data.max_users}`;
                    }
                    if(document.getElementById('switchAppStatusLicencia')) {
                        document.getElementById('switchAppStatusLicencia').checked = (data.app_status === 'active');
                    }
                });
        }

        function guardarConfiguracionLicencia() {
            const val = document.getElementById('inputMaxUsuariosLicencia').value;
            const status = document.getElementById('switchAppStatusLicencia').checked ? 'active' : 'inactive';
            const fileInput = document.getElementById('inputLogoApp');
            
            const formData = new FormData();
            formData.append('max_users', val);
            formData.append('app_status', status);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            
            if (fileInput && fileInput.files.length > 0) {
                formData.append('logo', fileInput.files[0]);
            }
            
            fetch('admin_config.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        alert('✅ Configuración actualizada correctamente.');
                        cargarConfiguracionLicencia(); // Recargar para ver cambios
                        if (fileInput.files.length > 0) {
                             setTimeout(() => location.reload(), 1000); // Recargar página para ver nuevo logo
                        }
                    } else {
                        alert('❌ Error al actualizar: ' + (data.error || 'Desconocido'));
                    }
                });
        }
        
        function eliminarUsuario(usuarioId) {
            if (confirm('¿Estás seguro de que quieres eliminar este usuario? Esta acción no se puede deshacer.')) {
                window.location.href = 'eliminar_usuario.php?id=' + usuarioId;
            }
        }

        function abrirModalProyecto() {
            document.getElementById('modalProyectoTitle').textContent = 'Nuevo Proyecto';
            document.getElementById('formProyecto').reset();
            document.getElementById('proyecto_id_edit').value = '';
            const modal = new bootstrap.Modal(document.getElementById('modalProyecto'));
            modal.show();
        }
        
        function editarProyecto(id) {
            fetch('obtener_proyecto.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalProyectoTitle').textContent = 'Editar Proyecto';
                    document.getElementById('proyecto_id_edit').value = data.id;
                    document.getElementById('nombre_proyecto').value = data.nombre;
                    document.getElementById('descripcion_proyecto').value = data.descripcion;
                    document.getElementById('is_private_project').checked = (data.user_id !== null);
                    
                    // Cerrar modal de gestión y abrir el de edición
                    const modalGestion = bootstrap.Modal.getInstance(document.getElementById('modalGestionProyectos'));
                    if(modalGestion) modalGestion.hide();
                    
                    const modal = new bootstrap.Modal(document.getElementById('modalProyecto'));
                    modal.show();
                })
                .catch(error => console.error('Error:', error));
        }

        function eliminarProyecto(id) {
            if(confirm('¿Estás seguro de eliminar este proyecto? Todas las tareas asociadas también se eliminarán.')) {
                window.location.href = 'eliminar_proyecto.php?id=' + id;
            }
        }

        // Función para actualizar la lista de proyectos en el modal
        function actualizarProyectosModal(vista, proyectoSeleccionadoId = null) {
            const proyectoSelect = document.getElementById('proyecto_id');
            
            fetch(`obtener_proyectos.php?vista=${vista}`)
                .then(response => response.json())
                .then(proyectos => {
                    // Limpiar opciones existentes
                    proyectoSelect.innerHTML = '';

                    // Añadir nuevas opciones
                    proyectos.forEach(proyecto => {
                        const option = new Option(proyecto.nombre, proyecto.id);
                        proyectoSelect.add(option);
                    });

                    // Si se pasó un ID, seleccionarlo
                    if (proyectoSeleccionadoId) {
                        proyectoSelect.value = proyectoSeleccionadoId;
                    }
                })
                .catch(error => console.error('Error al cargar proyectos:', error));
        }

        function abrirPopupConfigIA() {
            const isMobile = window.innerWidth <= 768;
            const url = 'config_ia.php';

            if (isMobile) {
                window.location.href = url;
            } else {
                const windowName = 'configIaPopup';
                const windowFeatures = 'width=800,height=600,scrollbars=yes,resizable=yes';
                window.open(url, windowName, windowFeatures);
            }
        }

        function abrirModalEtiquetas() {
            const modal = new bootstrap.Modal(document.getElementById('modalEtiquetas'));
            modal.show();
        }

        function editarEtiqueta(id, nombre, color) {
            document.getElementById('etiqueta_id').value = id;
            document.getElementById('nombre_etiqueta').value = nombre;
            document.getElementById('color_etiqueta').value = color;
            document.getElementById('btnGuardarEtiqueta').innerHTML = '<i class="bi bi-check-circle"></i> Actualizar';
        }

        function eliminarEtiqueta(id) {
            if(confirm('¿Estás seguro de eliminar esta etiqueta?')) {
                window.location.href = 'eliminar_etiqueta.php?id=' + id;
            }
        }

        function updateTagsButtonText(count) {
            const tagsDropdownBtn = document.getElementById('tagsDropdownBtn');
            if (!tagsDropdownBtn) return;
            if (count === 0) {
                tagsDropdownBtn.textContent = 'Seleccionar etiquetas';
            } else if (count === 1) {
                tagsDropdownBtn.textContent = '1 etiqueta seleccionada';
            } else {
                tagsDropdownBtn.textContent = `${count} etiquetas seleccionadas`;
            }
        }

        function reiniciarTour() {
            if (confirm('¿Quieres reiniciar el tour guiado para la próxima vez que cargues la página?')) {
                localStorage.removeItem('tourCompletado');
                alert('¡Listo! El tour se mostrará la próxima vez que recargues el dashboard.');
                location.reload();
            }
        }

        // --- Funciones para Subtareas ---
        function agregarSubtarea(tareaId, input) {
            const titulo = input.value.trim();
            if (!titulo) return;
            
            const formData = new FormData();
            formData.append('tarea_id', tareaId);
            formData.append('titulo', titulo);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            
            fetch('agregar_subtarea.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        const list = document.getElementById('subtasks-list-' + tareaId);
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center p-1 bg-transparent border-0';
                        li.innerHTML = `
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" onchange="toggleSubtarea(${data.id}, ${tareaId}, this)" id="sub-${data.id}">
                                <label class="form-check-label" for="sub-${data.id}">${data.titulo}</label>
                            </div>
                            <button class="btn btn-xs text-danger p-0 ms-2" onclick="eliminarSubtarea(${data.id}, ${tareaId}, this.closest('li'))"><i class="bi bi-x"></i></button>
                        `;
                        list.appendChild(li);
                        input.value = '';
                        actualizarProgreso(tareaId);
                    }
                });
        }

        function toggleSubtarea(id, tareaId, checkbox) {
            const label = checkbox.nextElementSibling;
            if (checkbox.checked) label.classList.add('subtask-completed');
            else label.classList.remove('subtask-completed');
            
            const formData = new FormData();
            formData.append('id', id);
            formData.append('completado', checkbox.checked ? 1 : 0);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            fetch('toggle_subtarea.php', { method: 'POST', body: formData });
            
            actualizarProgreso(tareaId);
        }

        function eliminarSubtarea(id, tareaId, element) {
            if(!confirm('¿Borrar paso?')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            fetch('eliminar_subtarea.php', { method: 'POST', body: formData })
                .then(() => {
                    element.remove();
                    actualizarProgreso(tareaId);
                });
        }

        function actualizarProgreso(tareaId) {
            const list = document.getElementById('subtasks-list-' + tareaId);
            const checkboxes = list.querySelectorAll('input[type="checkbox"]');
            const total = checkboxes.length;
            const checked = list.querySelectorAll('input[type="checkbox"]:checked').length;
            const percent = total === 0 ? 0 : Math.round((checked / total) * 100);
            
            const bar = document.getElementById('progress-bar-' + tareaId);
            const text = document.getElementById('progress-text-' + tareaId);
            
            if(bar) bar.style.width = percent + '%';
            if(text) text.textContent = percent + '%';
        }

        function eliminarArchivo(id, element) {
            if(!confirm('¿Eliminar este archivo adjunto?')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            fetch('eliminar_archivo.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => { if(data.success) element.remove(); else alert('Error al eliminar'); });
        }
        
        function openLightbox(url, filename, type) {
            const modalEl = document.getElementById('lightboxModal');
            const modal = new bootstrap.Modal(modalEl);
            const title = document.getElementById('lightboxTitle');
            const body = document.getElementById('lightboxBody');
            const downloadBtn = document.getElementById('lightboxDownload');

            title.textContent = filename;
            downloadBtn.href = url;
            downloadBtn.setAttribute('download', filename);

            body.innerHTML = ''; 

            if (type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = url;
                img.style.maxWidth = '100%';
                img.style.maxHeight = '80vh';
                img.style.borderRadius = '8px';
                body.appendChild(img);
            } else if (type === 'application/pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = url;
                iframe.style.width = '100%';
                iframe.style.height = '80vh';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                iframe.style.backgroundColor = 'white';
                body.appendChild(iframe);
            }

            modal.show();
        }

        // Asignar funciones globales
        window.abrirModalTarea = abrirModalTarea;
        window.editarTarea = editarTarea;
        window.eliminarTarea = eliminarTarea;
        window.archivarTarea = archivarTarea;
        window.filtrarPorFecha = filtrarPorFecha;
        window.abrirModalUsuarios = abrirModalUsuarios;
        window.abrirModalUsuario = abrirModalUsuario;
        window.editarUsuario = editarUsuario;
        window.eliminarUsuario = eliminarUsuario;
        window.abrirModalProyecto = abrirModalProyecto;
        window.abrirPopupConfigIA = abrirPopupConfigIA;
        window.reiniciarTour = reiniciarTour;
        window.shareTask = shareTask;
        window.copiarEnlace = copiarEnlace;
        window.enviarWhatsApp = enviarWhatsApp;

        document.addEventListener('DOMContentLoaded', function() {
            // --- Lógica de Modo Claro/Oscuro ---
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const iconTheme = document.getElementById('iconTheme');
            const htmlElement = document.documentElement;

            function updateThemeIcon(theme) {
                if(iconTheme) {
                    iconTheme.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
                }
            }

            // Inicializar icono
            updateThemeIcon(htmlElement.getAttribute('data-bs-theme'));

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const newTheme = htmlElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                    htmlElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    updateThemeIcon(newTheme);
                });
            }

            // Manejar checkboxes de etiquetas
            document.querySelectorAll('.tag-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    const selected = Array.from(document.querySelectorAll('.tag-checkbox:checked')).map(c => c.value);
                    const hiddenInput = document.getElementById('tags_ids_string');
                    if (hiddenInput) hiddenInput.value = selected.join(',');
                    updateTagsButtonText(selected.length);
                });
            });

            // Inicializar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            // Inicializar popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl)
            });

            // Inicializar popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl)
            });

            // --- AUTOCOMPLETADO DE MENCIONES (@) ---
            const suggestionBox = document.createElement('div');
            suggestionBox.className = 'suggestions-box';
            document.body.appendChild(suggestionBox);

            document.addEventListener('input', function(e) {
                 // Solo se activa en inputs con name="nota" o el input del modal
                if (e.target.name === 'nota' || e.target.id === 'modal-nota-input') {
                    const input = e.target;
                    const val = input.value;
                    const cursorPos = input.selectionStart;
                    
                    const textBeforeCursor = val.substring(0, cursorPos);
                    const words = textBeforeCursor.split(/\s+/);
                    const currentWord = words[words.length - 1];

                    if (currentWord.startsWith('@')) {
                        const query = currentWord.substring(1).toLowerCase();
                        const matches = usuariosSistema.filter(u => 
                            u.username.toLowerCase().includes(query) || 
                            u.nombre.toLowerCase().includes(query)
                        );

                        if (matches.length > 0) {
                            const rect = input.getBoundingClientRect();
                            suggestionBox.style.top = (rect.bottom + window.scrollY) + 'px';
                            suggestionBox.style.left = rect.left + 'px';
                            suggestionBox.style.display = 'block';
                            
                            suggestionBox.innerHTML = '';
                            matches.forEach(u => {
                                const div = document.createElement('div');
                                div.className = 'suggestion-item';
                                div.innerHTML = `<strong>${u.nombre}</strong> <small class="text-muted">@${u.username}</small>`;
                                div.onclick = function() {
                                    const textBefore = textBeforeCursor.substring(0, textBeforeCursor.lastIndexOf(currentWord));
                                    const textAfter = val.substring(cursorPos);
                                    input.value = textBefore + '@' + u.username + ' ' + textAfter;
                                    suggestionBox.style.display = 'none';
                                    input.focus();
                                };
                                suggestionBox.appendChild(div);
                            });
                        } else {
                            suggestionBox.style.display = 'none';
                        }
                    } else {
                        suggestionBox.style.display = 'none';
                    }
                }
            });

            // Cerrar sugerencias al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.suggestions-box')) {
                    suggestionBox.style.display = 'none';
                }
            });
        });
    </script>
</head>
<body>
    <!-- Header Fijo (estilo de dashboard_temp.php) -->
    <header class="main-header">
        <div class="header-content container position-relative d-flex justify-content-between align-items-center">
            
            <!-- Izquierda: Logo Custom (si existe) -->
            <div class="header-left d-flex align-items-center">
                <?php if (!empty($config['APP_LOGO'])): ?>
                    <img src="<?= $config['APP_LOGO'] ?>" alt="Logo Empresa" style="height: 40px; max-width: 150px; object-fit: contain;">
                <?php endif; ?>
            </div>

            <!-- Centro: Logo TuDu (Fijo) -->
            <div class="position-absolute top-50 start-50 translate-middle">
                <a class="header-logo" href="dashboard.php">
                    <img src="icons/logo_top.png" alt="TuDu Logo" style="height: 40px; max-width: 150px; object-fit: contain;">
                </a>
            </div>

            <div class="header-right d-flex align-items-center">
                <!-- Botón Modo Oscuro -->
                <button class="btn btn-outline-light border-0 me-2" id="themeToggleBtn" title="Cambiar Tema">
                    <i class="bi bi-moon-stars-fill" id="iconTheme"></i>
                </button>

                <!-- Campana de Notificaciones -->
                <div class="dropdown me-3">
                    <button class="btn btn-outline-light position-relative border-0" type="button" id="notifBell" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell-fill" style="font-size: 1.2rem;"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge" style="display: none;">
                            0
                            <span class="visually-hidden">mensajes no leídos</span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notifBell" id="notifList" style="width: 300px; max-height: 400px; overflow-y: auto;">
                        <li><span class="dropdown-item text-muted text-center small">No tienes notificaciones nuevas</span></li>
                    </ul>
                </div>

                <div class="dropdown user-menu">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <?php if ($usuario_actual_foto): ?>
                            <img src="<?= htmlspecialchars($usuario_actual_foto) ?>" alt="Perfil" class="user-avatar">
                        <?php else: ?>
                            <i class="bi bi-person-circle" style="font-size: 1.5rem;"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($usuario_actual_nombre) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text"><small>Usuario: <?= htmlspecialchars($usuario_actual_username) ?></small></span></li>
                        <li><span class="dropdown-item-text"><small>Rol: <?= htmlspecialchars($usuario_actual_rol) ?></small></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="editarUsuario(<?= $usuario_actual_id ?>)"><i class="bi bi-person-gear"></i> Mi Perfil</a></li>
                        <li><a class="dropdown-item" href="guia.php"><i class="bi bi-book"></i> Guía de Uso</a></li>
                        <?php if (in_array($usuario_actual_rol, ['admin', 'super_admin'])): ?>
                            <li class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" id="verDescripcionesProyectosBtn"><i class="bi bi-kanban me-2"></i>Gestión de Proyectos</a></li>
                            <li><a class="dropdown-item" href="#" onclick="abrirModalEtiquetas()"><i class="bi bi-tags me-2"></i>Gestionar Etiquetas</a></li>
                            <li><a class="dropdown-item" href="#" onclick="abrirModalUsuarios()"><i class="bi bi-people"></i> Gestionar Usuarios</a></li>
                            <li><a class="dropdown-item" href="#" onclick="abrirPopupConfigIA()"><i class="bi bi-robot"></i> Configurar IA</a></li>
                            <li><a class="dropdown-item" href="auditoria.php"><i class="bi bi-clipboard-data"></i> Auditoría</a></li>
                            <li><a class="dropdown-item" href="#" onclick="reiniciarTour()"><i class="bi bi-cursor-fill"></i> Reiniciar Tour</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <?php if ($usuario_actual_rol == 'super_admin'): ?>
                            <li><a class="dropdown-item text-primary" href="#" onclick="abrirModalLicencia()"><i class="bi bi-shield-lock"></i> Panel Licencia</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="archivo.php">
                            <i class="bi bi-archive me-2"></i> Archivo
                            <span class="badge rounded-pill bg-secondary ms-2"><?= $count_archivados ?></span>
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-power"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <!-- Barra de Contexto / Filtros -->
    <div class="context-bar">
        <div class="container d-flex justify-content-center align-items-center h-100">
            <div class="header-controls d-flex gap-2 align-items-center flex-wrap justify-content-center">
                
                <!-- Row 1: Navigation & Filters -->
                <div class="d-flex gap-2 align-items-center mobile-row" style="z-index: 1050; position: relative;">
                    <!-- Dropdown Vistas (Izquierda siempre) -->
                    <div class="dropdown" style="z-index: 1060;">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-grid-1x2"></i> <span class="d-none d-md-inline">Vistas</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="dashboard.php"><i class="bi bi-list-ul me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="kanban.php"><i class="bi bi-kanban me-2"></i>Tablero Kanban</a></li>
                            <li><a class="dropdown-item" href="calendar.php"><i class="bi bi-calendar-week me-2"></i>Calendario</a></li>
                        </ul>
                    </div>

                    <!-- Project Selector -->
                    <div class="project-view-selector d-flex align-items-center gap-2">
                         <label for="project-selector" class="mb-0 text-nowrap small text-muted d-none d-lg-block">Proyecto:</label>
                        <select class="form-select form-select-sm" id="project-selector" onchange="if(this.value) window.location.href=this.value" style="max-width: 200px;">
                            <option value="dashboard.php?vista=<?= $filtro_vista ?>">📁 Todos</option>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto['id'] ?>" <?= $proyecto_id == $proyecto['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proyecto['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Grupo de botones para la vista -->
                    <div class="btn-group view-switcher" role="group">
                        <a href="dashboard.php?vista=publico" 
                           class="btn <?= $filtro_vista == 'publico' ? 'btn-primary' : 'btn-outline-secondary' ?>">Públicos</a>
                        <a href="dashboard.php?vista=personal" 
                           class="btn <?= $filtro_vista == 'personal' ? 'btn-primary' : 'btn-outline-secondary' ?>">Personales</a>
                    </div>
                </div>

                <div class="vr mx-2 d-none d-md-block"></div>

                <!-- Row 2: Actions -->
                <div class="d-flex gap-2 align-items-center mobile-row justify-content-end">
                    <button class="btn btn-success btn-sm flex-fill" id="btnNuevoProyecto" onclick="abrirModalProyecto()">
                        <i class="bi bi-plus-circle"></i> Nuevo Proyecto
                    </button>
                    <button class="btn btn-primary btn-sm flex-fill" id="btnNuevaIdea" onclick="abrirModalTarea(<?= !empty($proyecto_id) ? $proyecto_id : 'null' ?>, '<?= $filtro_vista == 'personal' ? 'private' : 'public' ?>')">
                        <i class="bi bi-plus-circle"></i> Nueva Tarea
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="container my-4">
        <div class="row">
            <main class="col-lg-12">
                <div class="card p-4">
                    <!-- KPIs / Contadores -->
                    <div class="kpi-grid mb-4">
                        <div class="kpi-card">
                            <span class="kpi-number" style="color: var(--text-color);"><?= $contadores['total'] ?></span>
                            <span class="kpi-label">Total</span>
                        </div>
                        <div class="kpi-card">
                            <span class="kpi-number" style="color: #ff3b30;"><?= $contadores['pendientes'] ?></span>
                            <span class="kpi-label">Pendientes</span>
                        </div>
                        <div class="kpi-card">
                            <span class="kpi-number" style="color: #ff9500;"><?= $contadores['en_progreso'] ?></span>
                            <span class="kpi-label">En Progreso</span>
                        </div>
                        <div class="kpi-card">
                            <span class="kpi-number" style="color: #34c759;"><?= $contadores['completados'] ?></span>
                            <span class="kpi-label">Completados</span>
                        </div>
                    </div>

                    <!-- Vistas Rápidas -->
                    <div class="quick-view-grid mb-4">
                        <div class="quick-view-card card">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="bi bi-calendar-day"></i> Vence Hoy</h6>
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($tareas_vence_hoy)): ?>
                                        <li class="list-group-item text-muted"><small>¡Ninguna tarea vence hoy!</small></li>
                                    <?php else: ?>
                                        <?php foreach ($tareas_vence_hoy as $tarea_vence): ?>
                                            <a href="dashboard.php?proyecto_id=<?= $tarea_vence['proyecto_id'] ?>#task-<?= $tarea_vence['id'] ?>" class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?= htmlspecialchars(mb_strimwidth($tarea_vence['titulo'], 0, 30, "...")) ?></span>
                                                <span class="badge status-badge bg-<?= $tarea_vence['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $tarea_vence['estado'])) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="quick-view-card card">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="bi bi-calendar-event"></i> Próximos 7 días</h6>
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($tareas_vence_semana)): ?>
                                        <li class="list-group-item text-muted"><small>¡Sin vencimientos próximos!</small></li>
                                    <?php else: ?>
                                        <?php foreach ($tareas_vence_semana as $tarea_semana): ?>
                                            <a href="dashboard.php?proyecto_id=<?= $tarea_semana['proyecto_id'] ?>#task-<?= $tarea_semana['id'] ?>" class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?= htmlspecialchars(mb_strimwidth($tarea_semana['titulo'], 0, 30, "...")) ?></span>
                                                <small class="text-muted"><?= (new DateTime($tarea_semana['fecha_termino']))->format('d/m') ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="card mb-4 filter-bar-compact" id="filtrosPrincipales">
                        <div class="card-body py-3">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                    <strong>Filtros:</strong>
                                    <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value" title="Filtrar por estado">
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=todos&usuario_id=<?= $filtro_usuario ?>&prioridad=<?= $filtro_prioridad ?>&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" 
                                                <?= $filtro_estado == 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=pendiente&usuario_id=<?= $filtro_usuario ?>&prioridad=<?= $filtro_prioridad ?>&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" 
                                                <?= $filtro_estado == 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=en_progreso&usuario_id=<?= $filtro_usuario ?>&prioridad=<?= $filtro_prioridad ?>&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" 
                                                <?= $filtro_estado == 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=completado&usuario_id=<?= $filtro_usuario ?>&prioridad=<?= $filtro_prioridad ?>&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" 
                                                <?= $filtro_estado == 'completado' ? 'selected' : '' ?>>Completados</option>
                                    </select>
                                    
                                    <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value" title="Filtrar por usuario">
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=<?= $filtro_estado ?>&usuario_id=&prioridad=<?= $filtro_prioridad ?>&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" 
                                                <?= !$filtro_usuario ? 'selected' : '' ?>>Todos los usuarios</option>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=<?= $filtro_estado ?>&usuario_id=<?= $usuario['id'] ?>&prioridad=<?= $filtro_prioridad ?>&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" 
                                                    <?= $filtro_usuario == $usuario['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($usuario['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value" title="Filtrar por prioridad">
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=<?= $filtro_estado ?>&usuario_id=<?= $filtro_usuario ?>&prioridad=todas&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" <?= $filtro_prioridad == 'todas' ? 'selected' : '' ?>>Todas las prioridades</option>
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=<?= $filtro_estado ?>&usuario_id=<?= $filtro_usuario ?>&prioridad=alta&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" <?= $filtro_prioridad == 'alta' ? 'selected' : '' ?>>🔴 Alta</option>
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=<?= $filtro_estado ?>&usuario_id=<?= $filtro_usuario ?>&prioridad=media&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" <?= $filtro_prioridad == 'media' ? 'selected' : '' ?>>🟡 Media</option>
                                        <option value="dashboard.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto_id ?>&estado=<?= $filtro_estado ?>&usuario_id=<?= $filtro_usuario ?>&prioridad=baja&fecha_vencimiento=<?= $filtro_fecha_vencimiento ?>" <?= $filtro_prioridad == 'baja' ? 'selected' : '' ?>>🟢 Baja</option>
                                    </select>
                                    
                                    <!-- Filtro por fecha de vencimiento -->
                                    <input type="date" class="form-control form-control-sm w-auto" title="Filtrar por fecha de vencimiento"
                                           value="<?= $filtro_fecha_vencimiento ?>" 
                                           onchange="filtrarPorFecha(this.value)"
                                           style="max-width: 140px;">
                                    
                                    <!-- Buscador movido al final con ms-auto -->
                                    <div class="input-group input-group-sm w-auto ms-auto" style="max-width: 250px;">
                                        <input type="text" id="searchInput" class="form-control" placeholder="Buscar tarea...">
                                        <button class="btn btn-outline-secondary" type="button" id="btnSearch"><i class="bi bi-search"></i></button>
                                    </div>
                            </div>
                        </div>
                    </div>

                    <!-- Título de sección -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>
                            <?php if ($proyecto_id): ?>
                                <i class="bi bi-folder2"></i> Tareas para: <strong><?= htmlspecialchars($proyectos_map[$proyecto_id] ?? 'Proyecto') ?></strong>
                            <?php elseif ($filtro_vista == 'personal'): ?>
                                <i class="bi bi-person-lock"></i> Mis Tareas Personales
                            <?php else: ?>
                                <i class="bi bi-globe"></i> Todas las Tareas Públicas
                            <?php endif; ?> <span class="badge bg-secondary ms-2"><?= count($tareas) ?></span>
                        </h5>
                        
                        <div>
                            <?php if ($filtro_estado != 'todos'): ?>
                                <span class="badge bg-secondary me-2">
                                    Estado: <?= ucfirst(str_replace('_', ' ', $filtro_estado)) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($filtro_usuario): ?>
                                <span class="badge bg-secondary">
                                    Usuario: <?= htmlspecialchars($usuarios[array_search($filtro_usuario, array_column($usuarios, 'id'))]['nombre'] ?? '') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Lista de Tareas -->
                    <div class="lista-tareas" style="max-height: 60vh; overflow-y: auto;">
                        <?php if (empty($tareas)): ?> 
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3 text-muted">No hay Tareas aún</h5>
                                <p class="text-muted">¡Comienza agregando la primera idea!</p>
                                <button class="btn btn-success" onclick="abrirModalTarea(<?= !empty($proyecto_id) ? $proyecto_id : 'null' ?>, '<?= $is_current_project_private ? 'private' : 'public' ?>')">
                                    <i class="bi bi-plus-circle"></i> Agregar Primera Idea
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tareas as $tarea): ?>
                                <?php try { ?>
                                    <?php
                                        // Verificar si está vencida para la animación
                                        $is_overdue = false;
                                        if (!empty($tarea['fecha_termino']) && $tarea['estado'] != 'completado') {
                                            $ft_obj = new DateTime($tarea['fecha_termino']);
                                            $today_obj = new DateTime('today');
                                            if ($ft_obj < $today_obj) $is_overdue = true;
                                        }
                                        
                                        // Mapear estado a clase CSS para el color del borde
                                        $estado_clase = '';
                                        if ($tarea['estado'] == 'pendiente') $estado_clase = 'card-pendiente';
                                        elseif ($tarea['estado'] == 'en_progreso') $estado_clase = 'card-progreso';
                                        elseif ($tarea['estado'] == 'completado') $estado_clase = 'card-completado';

                                        $card_classes = $estado_clase;
                                        if ($is_overdue) $card_classes .= ' overdue';
                                    ?>
                                    <div id="task-<?= $tarea['id'] ?>" class="card task-card <?= $card_classes ?>">
                                        <div class="card-body p-3">
                                            <!-- Nombre del Proyecto -->
                                            <div class="mb-1">
                                                <small class="text-muted" title="Proyecto">
                                                    <i class="bi bi-folder2"></i> <?= htmlspecialchars($tarea['proyecto_nombre']) ?>
                                                </small>
                                            </div>
                                            
                                            <!-- Título y Descripción -->
                                            <h6 class="card-title mb-1 text-truncate-2" onclick="editarTarea(<?= $tarea['id'] ?>);" title="<?= htmlspecialchars($tarea['titulo']) ?>">
                                                <?= htmlspecialchars($tarea['titulo']) ?>
                                                <?php if (!empty($archivos_por_tarea[$tarea['id']])): ?>
                                                    <i class="bi bi-paperclip text-muted ms-1" style="font-size: 0.9em;" title="<?= count($archivos_por_tarea[$tarea['id']]) ?> archivos adjuntos"></i>
                                                <?php endif; ?>
                                                <?php 
                                                // Icono de Notas
                                                if (isset($notas_info[$tarea['id']]) && $notas_info[$tarea['id']]['total'] > 0): 
                                                    $last_note_time = strtotime($notas_info[$tarea['id']]['ultima_nota']);
                                                    $is_recent = (time() - $last_note_time) < (24 * 3600); // 24 horas
                                                    $note_color = $is_recent ? 'text-primary' : 'text-muted';
                                                    $note_title = $is_recent ? 'Hay comentarios recientes' : 'Comentarios antiguos';
                                                ?>
                                                    <i class="bi bi-chat-left-text-fill <?= $note_color ?> ms-1" style="font-size: 0.9em;" title="<?= $notas_info[$tarea['id']]['total'] ?> comentarios. <?= $note_title ?>"></i>
                                                <?php endif; ?>
                                            </h6>
                                            <?php 
                                                $desc_html = htmlspecialchars($tarea['descripcion']);
                                                $desc_html = preg_replace('/@(\w+)/', '<span class="text-primary fw-bold">@$1</span>', $desc_html);
                                            ?>
                                            <p class="card-text small text-muted mb-2 text-truncate-2" style="cursor: pointer;" onclick="editarTarea(<?= $tarea['id'] ?>)"><?= $desc_html ?></p>

                                            <!-- Fechas de la Tarea (Nuevo) -->
                                            <div class="d-flex flex-wrap gap-2 mb-2 small" style="font-size: 0.75rem;">
                                                <span class="text-muted" title="Fecha de Creación">
                                                    <i class="bi bi-calendar-plus me-1"></i><?= (new DateTime($tarea['fecha_creacion']))->format('d/m H:i') ?>
                                                </span>
                                                <?php if (!empty($tarea['fecha_termino'])): ?>
                                                    <?php 
                                                        $ft_date = new DateTime($tarea['fecha_termino']);
                                                        $is_expired = $ft_date < new DateTime();
                                                        $date_color = $is_expired && $tarea['estado'] != 'completado' ? 'text-danger fw-bold' : 'text-muted';
                                                    ?>
                                                    <span class="<?= $date_color ?>" title="Fecha de Término">
                                                        <i class="bi bi-calendar-event me-1"></i><?= $ft_date->format('d/m H:i') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Barra de Botones y Acciones -->
                                            <div class="d-flex justify-content-end align-items-center gap-1 mb-2">
                                                <!-- Botones de acción a la derecha -->
                                                <button type="button" class="btn btn-sm btn-outline-info border-0" onclick="shareTask(<?= $tarea['id'] ?>)" title="Compartir"><i class="bi bi-share-fill"></i></button>

                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-link text-success p-0" type="button" data-bs-toggle="dropdown" title="Enviar WhatsApp a...">
                                                        <i class="bi bi-whatsapp" style="font-size: 1.1rem;"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php foreach ($usuarios as $u): ?>
                                                            <?php if (!empty($u['telefono'])): ?>
                                                                <?php
                                                                    $task_url = $app_base_url . 'dashboard.php?proyecto_id=' . $tarea['proyecto_id'] . '#task-' . $tarea['id'];
                                                                    $mensaje_texto = "Hola " . htmlspecialchars($u['nombre']) . ", te escribo sobre la tarea: '" . htmlspecialchars($tarea['titulo']) . "'.\n\nPuedes verla aquí: " . $task_url;
                                                                    $mensaje_whatsapp = urlencode($mensaje_texto);
                                                                ?>
                                                                <li><a class="dropdown-item" href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $u['telefono']) ?>?text=<?= $mensaje_whatsapp ?>" target="_blank"><?= htmlspecialchars($u['nombre']) ?></a></li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>

                                                <button type="button" class="btn btn-sm btn-outline-primary border-0" onclick="editarTarea(<?= $tarea['id'] ?>)" title="Editar"><i class="bi bi-pencil-fill"></i></button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary border-0" onclick="archivarTarea(<?= $tarea['id'] ?>)" title="Archivar"><i class="bi bi-box-arrow-in-down"></i></button>
                                                <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="eliminarTarea(<?= $tarea['id'] ?>)" title="Eliminar"><i class="bi bi-trash3-fill"></i></button>
                                            </div>

                                            <!-- Formulario para Agregar Nota -->
                                            <div>
                                                <form method="POST" action="agregar_nota.php" class="form-nota-card">
                                                    <?= getCsrfField() ?>
                                                    <input type="hidden" name="tarea_id" value="<?= $tarea['id'] ?>">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" name="nota" placeholder="Agregar nota..." required>
                                                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-send"></i></button>
                                                    </div>
                                                </form>
                                            </div>

                                        </div>
                                    </div>
                                <?php } catch (Exception $e) { ?>
                                    <div class="alert alert-danger p-2 mb-2">
                                        <small><i class="bi bi-exclamation-triangle"></i> Error al mostrar tarea <?= $tarea['id'] ?></small>
                                    </div>
                                <?php } ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Botón flotante para mobile -->
    <div class="d-lg-none">
        <div class="position-fixed bottom-0 end-0 p-3">
            <button class="btn btn-primary btn-lg rounded-circle shadow-lg" id="btnNuevaIdeaMobile" onclick="abrirModalTarea(<?= !empty($proyecto_id) ? $proyecto_id : 'null' ?>, '<?= $filtro_vista == 'personal' ? 'private' : 'public' ?>')" style="width: 60px; height: 60px;">
                <i class="bi bi-plus"></i>
            </button>
        </div>
    </div>

    <!-- Modal Splash Screen (Pantalla Inicial) -->
    <div class="modal fade" id="splashScreenModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header border-0 bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-speedometer2"></i> Resumen del Día</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row align-items-center">
                        <!-- Gráfica de Dona -->
                        <div class="col-md-5 text-center mb-4 mb-md-0">
                            <h6 class="text-muted mb-3">Estado General</h6>
                            <div style="position: relative; height: 200px; width: 100%; margin: 0 auto;">
                                <canvas id="splashChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Listas Rápidas -->
                        <div class="col-md-7">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h6 class="text-success"><i class="bi bi-stars"></i> Nuevas Tareas (Hoy)</h6>
                                    <ul class="list-group list-group-flush small">
                                        <?php if (empty($tareas_nuevas_hoy)): ?>
                                            <li class="list-group-item text-muted bg-body-tertiary rounded border-0">No se han creado Tareas hoy.</li>
                                        <?php else: ?>
                                            <?php foreach ($tareas_nuevas_hoy as $t): ?>
                                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
                                                    <span><?= htmlspecialchars(mb_strimwidth($t['titulo'], 0, 25, "...")) ?></span>
                                                    <a href="dashboard.php?proyecto_id=<?= $t['proyecto_id'] ?>#task-<?= $t['id'] ?>" class="btn btn-xs btn-outline-primary py-0" style="font-size: 0.7rem;">Ver</a>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                </div>
                                <div class="col-12 mb-3">
                                    <h6 class="text-danger"><i class="bi bi-exclamation-circle"></i> Vence Hoy</h6>
                                    <ul class="list-group list-group-flush small">
                                        <?php if (empty($tareas_vence_hoy)): ?>
                                            <li class="list-group-item text-muted bg-body-tertiary rounded border-0">¡Todo al día! No hay vencimientos hoy.</li>
                                        <?php else: ?>
                                            <?php foreach ($tareas_vence_hoy as $t): ?>
                                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
                                                    <span><?= htmlspecialchars(mb_strimwidth($t['titulo'], 0, 25, "...")) ?></span>
                                                    <a href="dashboard.php?proyecto_id=<?= $t['proyecto_id'] ?>#task-<?= $t['id'] ?>" class="btn btn-xs btn-outline-primary py-0" style="font-size: 0.7rem;">Ver</a>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <div class="col-12">
                                    <h6 class="text-info"><i class="bi bi-calendar-week"></i> Próximos 7 días</h6>
                                    <ul class="list-group list-group-flush small">
                                        <?php if (empty($tareas_vence_semana)): ?>
                                            <li class="list-group-item text-muted bg-body-tertiary rounded border-0">Nada pendiente para la semana.</li>
                                        <?php else: ?>
                                            <?php foreach ($tareas_vence_semana as $t): ?>
                                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
                                                    <span><?= htmlspecialchars(mb_strimwidth($t['titulo'], 0, 25, "...")) ?></span>
                                                    <span class="badge bg-body-tertiary text-body border"><?= (new DateTime($t['fecha_termino']))->format('d/m') ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn btn-primary px-5 rounded-pill" data-bs-dismiss="modal">¡A trabajar!</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Compartir (Nuevo) -->
    <div class="modal fade" id="modalShare" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-share"></i> Compartir Idea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <h6 class="mb-3 text-start"><i class="bi bi-whatsapp text-success"></i> Enviar por WhatsApp</h6>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control" id="whatsappPhone" placeholder="Ej: 5215512345678">
                        <button class="btn btn-success" type="button" onclick="enviarWhatsApp()">
                            <i class="bi bi-send"></i> Enviar
                        </button>
                    </div>
                    <div class="form-text text-muted small mt-2 text-start">Ingresa el número con código de país (sin +).</div>
                    
                    <hr class="my-3">
                    
                    <p class="mb-3">O copia el enlace manualmente:</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="shareLinkInput" readonly>
                        <button class="btn btn-outline-primary" type="button" onclick="copiarEnlace()">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>
                    <div id="shareSuccessMsg" class="text-success small" style="display:none; opacity:0; transition: opacity 0.5s;">
                        <i class="bi bi-check-circle"></i> ¡Enlace copiado!
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar/Editar Tarea -->
    <div class="modal fade" id="modalTarea" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTareaTitle">
                        <i class="bi bi-lightbulb"></i> Agregar Nueva Idea
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="modalTareaCloseBtn" aria-label="Cerrar"></button>
                </div>
                
                <form method="POST" action="guardar_tarea.php" id="formTarea" enctype="multipart/form-data">
                    <?= getCsrfField() ?>
                    <input type="hidden" name="tarea_id" id="tarea_id" value="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Título:</label>
                                    <input type="text" class="form-control" name="titulo" id="titulo" placeholder="Ej: Implementar login seguro" required>
                                    <div id="edit-lock-info" class="form-text text-warning small mt-1" style="display: none;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Proyecto:</label>
                                    <select class="form-select" name="proyecto_id" id="proyecto_id" required>
                                        <?php foreach ($proyectos as $proyecto): ?>
                                            <option value="<?= $proyecto['id'] ?>"><?= htmlspecialchars($proyecto['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prioridad:</label>
                                    <select class="form-select" name="prioridad" id="prioridad">
                                        <option value="baja">🟢 Baja</option>
                                        <option value="media" selected>🟡 Media</option>
                                        <option value="alta">🔴 Alta</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Estado:</label>
                                    <select class="form-select" name="estado" id="estado_tarea">
                                        <option value="pendiente">Pendiente</option>
                                        <option value="en_progreso">En Progreso</option>
                                        <option value="completado">Completado</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción:</label>
                            <textarea class="form-control" name="descripcion" id="descripcion" rows="4" placeholder="Describe tu idea en detalle..."></textarea>
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-sm btn-outline-info" type="button" id="ask-gemini-btn" title="Obtener sugerencias de la IA">
                                    <i class="bi bi-stars"></i> Sugerir
                                </button>
                                <button class="btn btn-sm btn-outline-warning" type="button" id="estimate-time-btn" title="Estimar tiempo basado en complejidad">
                                    <i class="bi bi-hourglass-split"></i> Estimar
                                </button>
                            </div>
                            <div id="gemini-suggestions-container" class="mt-2" style="display:none;">
                                <div class="spinner-border spinner-border-sm text-info" role="status">
                                    <span class="visually-hidden">Generando...</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Etiquetas (Tags):</label>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="tagsDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    Seleccionar etiquetas
                                </button>
                                <div class="dropdown-menu w-100" aria-labelledby="tagsDropdownBtn" id="tagsDropdownMenu">
                                    <?php if (empty($todas_las_etiquetas)): ?>
                                        <span class="dropdown-item-text text-muted">No hay etiquetas creadas.</span>
                                    <?php else: ?>
                                        <?php foreach ($todas_las_etiquetas as $etiqueta): ?>
                                            <div class="dropdown-item">
                                                <div class="form-check">
                                                    <input class="form-check-input tag-checkbox" type="checkbox" value="<?= $etiqueta['id'] ?>" id="tag-<?= $etiqueta['id'] ?>">
                                                    <label class="form-check-label" for="tag-<?= $etiqueta['id'] ?>">
                                                        <span class="badge" style="background-color: <?= $etiqueta['color'] ?? '#6c757d' ?>; color: <?= getContrastingTextColor($etiqueta['color'] ?? '#6c757d') ?>;"><?= htmlspecialchars($etiqueta['nombre']) ?></span>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="hidden" name="tags_ids_string" id="tags_ids_string">
                        </div>
                        <!-- Sección de Archivos -->
                        <div class="mb-3">
                            <label class="form-label">Adjuntar archivos:</label>
                            <input type="file" class="form-control" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                            <div id="lista-archivos-existentes" class="mt-2"></div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check_fecha_termino">
                                <label class="form-check-label" for="check_fecha_termino">
                                    Añadir fecha de término
                                </label>
                            </div>
                            <div id="fecha_termino_container" style="display: none;" class="mt-2">
                                <label for="fecha_termino" class="form-label">Fecha de término:</label>
                                <input type="date" class="form-control" name="fecha_termino" id="fecha_termino">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Visibilidad:</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="visibility" id="visibility_public" value="public" checked>
                                    <label class="form-check-label" for="visibility_public">Público (Visible para todos)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="visibility" id="visibility_private" value="private">
                                    <label class="form-check-label" for="visibility_private">
                                        <i class="bi bi-lock-fill"></i> Personal (Solo visible para mí)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- CORRECCIÓN: Sección de asignación siempre visible -->
                        <div class="mb-3">
                            <label class="form-label">Asignar a usuarios:</label>
                            <select class="form-select" name="usuarios_ids[]" id="usuarios_ids" multiple size="3">
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>">
                                        <?= htmlspecialchars($usuario['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Mantén presionado Ctrl (o Cmd) para seleccionar varios.</small>
                        </div>

                        <!-- Sección de Comentarios en el Modal -->
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="bi bi-chat-left-text"></i> Comentarios</h6>
                            <span class="text-primary" id="infoMenciones" style="cursor: pointer; font-size: 0.9rem;" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Escribe '@' para mencionar a un compañero (ej: @juan) y enviarle una notificación.">
                                <i class="bi bi-info-circle"></i> ¿Cómo mencionar?
                            </span>
                        </div>
                        <div id="modal-notas-list" class="mb-3" style="max-height: 200px; overflow-y: auto; background: var(--content-background); padding: 10px; border-radius: 5px; border: 1px solid var(--border-color);"></div>
                        <div class="input-group">
                            <input type="text" id="modal-nota-input" class="form-control" placeholder="Escribe un comentario...">
                            <button class="btn btn-outline-primary" type="button" id="btn-agregar-nota-modal"><i class="bi bi-send"></i></button>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarTarea">
                            <i class="bi bi-check-circle"></i> Guardar Idea
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Gestionar Usuarios -->
    <div class="modal fade" id="modalUsuarios" tabindex="-1">
        <div class="modal-dialog modal-xl" style="max-width: 95%;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-people"></i> Gestionar Usuarios
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <button class="btn btn-primary mb-3" onclick="abrirModalUsuario()">
                        <i class="bi bi-person-plus"></i> Nuevo Usuario
                    </button>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sql_users = "SELECT id, username, nombre, email, telefono, rol, activo FROM usuarios";
                                // Ocultar super_admin si el usuario actual no es super_admin
                                if ($usuario_actual_rol != 'super_admin') {
                                    $sql_users .= " WHERE rol != 'super_admin'";
                                }
                                $sql_users .= " ORDER BY nombre";
                                $stmt_todos_usuarios = $pdo->query($sql_users);
                                $todos_usuarios = $stmt_todos_usuarios->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php foreach ($todos_usuarios as $usuario): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($usuario['username']) ?></strong></td>
                                        <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                        <td><?= htmlspecialchars($usuario['telefono'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($usuario['email'] ?? 'No especificado') ?></td>
                                        <td><span class="badge bg-<?= $usuario['rol'] == 'admin' ? 'danger' : 'primary' ?>"><?= $usuario['rol'] ?></span></td>
                                        <td><span class="badge bg-<?= $usuario['activo'] ? 'success' : 'secondary' ?>"><?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editarUsuario(<?= $usuario['id'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($usuario['id'] != $usuario_actual_id): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="eliminarUsuario(<?= $usuario['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Panel de Licencia (Super Admin) -->
    <div class="modal fade" id="modalLicencia" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Panel de Licencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-3 border-warning">
                        <div class="card-body bg-warning-subtle py-2">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h6 class="mb-0 text-dark"><i class="bi bi-speedometer"></i> Licencia</h6>
                                <small id="usersCountDisplayLicencia" class="text-dark fw-bold"></small>
                                <form id="formConfigLicencia" class="d-flex align-items-center gap-2">
                                    <input type="number" class="form-control form-control-sm" id="inputMaxUsuariosLicencia" name="max_users" style="width: 70px;" min="1" title="Límite de usuarios">
                                    <div class="form-check form-switch ms-2" title="Activar/Desactivar Sistema">
                                        <input class="form-check-input" type="checkbox" id="switchAppStatusLicencia">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="guardarConfiguracionLicencia()">Guardar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Personalización (Logo) -->
                    <div class="card border-info">
                        <div class="card-header bg-info-subtle py-2">
                             <h6 class="mb-0 text-dark"><i class="bi bi-palette"></i> Personalización</h6>
                        </div>
                        <div class="card-body py-3">
                            <label class="form-label small fw-bold">Logo de la Aplicación</label>
                            <div class="input-group input-group-sm">
                                <input type="file" class="form-control" id="inputLogoApp" accept="image/*">
                                <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('inputLogoApp').value = '';">Limpiar</button>
                            </div>
                            <div class="form-text small mt-1">
                                Sube el logo de tu organización. Aparecerá en la parte izquierda del encabezado, junto al logo de TuDu.
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="alert alert-info small"><i class="bi bi-info-circle-fill"></i> Estos controles afectan a toda la aplicación. El <strong>límite de usuarios</strong> previene nuevos registros si se alcanza. El <strong>interruptor</strong> activa o desactiva el acceso para todos los usuarios (excepto Super Admins).</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar/Editar Usuario -->
    <div class="modal fade" id="modalUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUsuarioTitle">
                        <i class="bi bi-person-plus"></i> Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" action="guardar_usuario.php" id="formUsuario" enctype="multipart/form-data">
                    <?= getCsrfField() ?>
                    <input type="hidden" name="usuario_id" id="usuario_id_edit" value="">
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <img id="preview_foto" src="https://via.placeholder.com/100?text=Foto" class="user-avatar-lg mb-2">
                            <br>
                            <label class="btn btn-sm btn-outline-primary" for="foto_perfil_input"><i class="bi bi-camera"></i> Cambiar Foto</label>
                            <input type="file" name="foto_perfil" id="foto_perfil_input" class="d-none" accept="image/*" onchange="document.getElementById('preview_foto').src = window.URL.createObjectURL(this.files[0])">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre completo:</label>
                            <input type="text" class="form-control" name="nombre" id="usuario_nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Usuario (para login):</label>
                            <input type="text" class="form-control" name="username" id="usuario_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email (opcional):</label>
                            <input type="email" class="form-control" name="email" id="usuario_email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Teléfono (para WhatsApp):</label>
                            <input type="tel" class="form-control" name="telefono" id="usuario_telefono" placeholder="Ej: 5211234567890">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña:</label>
                            <input type="password" class="form-control" name="password" id="usuario_password">
                            <small class="text-muted">Dejar en blanco para no cambiar</small>
                        </div>

                        <?php if (in_array($usuario_actual_rol, ['admin', 'super_admin'])): ?>
                        <div class="mb-3">
                            <label for="usuario_rol" class="form-label">Rol:</label>
                            <select class="form-select" name="rol" id="usuario_rol">
                                <option value="usuario">Usuario</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Guardar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Gestionar Etiquetas -->
    <div class="modal fade" id="modalEtiquetas" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tags"></i> Gestionar Etiquetas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="guardar_etiqueta.php" class="mb-4 p-3 bg-light rounded border">
                        <?= getCsrfField() ?>
                        <input type="hidden" name="etiqueta_id" id="etiqueta_id">
                        <div class="row g-2 align-items-end">
                            <div class="col-7">
                                <label class="form-label small">Nombre</label>
                                <input type="text" class="form-control form-control-sm" name="nombre" id="nombre_etiqueta" required placeholder="Ej: Urgente">
                            </div>
                            <div class="col-2">
                                <label class="form-label small">Color</label>
                                <input type="color" class="form-control form-control-sm form-control-color w-100" name="color" id="color_etiqueta" value="#6c757d" title="Elige un color">
                            </div>
                            <div class="col-3">
                                <button type="submit" class="btn btn-sm btn-primary w-100" id="btnGuardarEtiqueta"><i class="bi bi-plus-lg"></i> Crear</button>
                            </div>
                        </div>
                    </form>

                    <h6 class="mb-3">Etiquetas Existentes</h6>
                    <div class="list-group">
                        <?php foreach ($todas_las_etiquetas as $tag): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge me-2" style="background-color: <?= $tag['color'] ?>; color: <?= getContrastingTextColor($tag['color']) ?>;">
                                        <?= htmlspecialchars($tag['nombre']) ?>
                                    </span>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary border-0" onclick="editarEtiqueta(<?= $tag['id'] ?>, '<?= htmlspecialchars($tag['nombre']) ?>', '<?= $tag['color'] ?>')"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger border-0" onclick="eliminarEtiqueta(<?= $tag['id'] ?>)"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($todas_las_etiquetas)): ?><p class="text-muted text-center small">No hay etiquetas.</p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Gestionar Proyectos -->
    <div class="modal fade" id="modalGestionProyectos" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-kanban"></i> Gestionar Proyectos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <button class="btn btn-success mb-3" onclick="abrirModalProyecto()">
                        <i class="bi bi-plus-circle"></i> Nuevo Proyecto
                    </button>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Tipo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $stmt_all_projs = $pdo->query("SELECT p.*, u.nombre as owner_name FROM proyectos p LEFT JOIN usuarios u ON p.user_id = u.id ORDER BY p.nombre");
                                while ($p = $stmt_all_projs->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                                    <td><?= htmlspecialchars($p['descripcion']) ?></td>
                                    <td>
                                        <?php if ($p['user_id']): ?>
                                            <span class="badge bg-secondary">Privado</span>
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                <i class="bi bi-person"></i> <?= htmlspecialchars($p['owner_name'] ?? 'Usuario eliminado') ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-success">Público</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="dashboard.php?proyecto_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Ver Tareas"><i class="bi bi-eye"></i></a>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editarProyecto(<?= $p['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarProyecto(<?= $p['id'] ?>)"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar Proyecto -->
    <div class="modal fade" id="modalProyecto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProyectoTitle">
                        <i class="bi bi-folder-plus"></i> Nuevo Proyecto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="guardar_proyecto.php" id="formProyecto">
                    <?= getCsrfField() ?>
                    <input type="hidden" name="proyecto_id" id="proyecto_id_edit">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_proyecto" class="form-label">Nombre del proyecto:</label>
                            <input type="text" class="form-control" id="nombre_proyecto" name="nombre" placeholder="Nombre del proyecto" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_proyecto" class="form-label">Descripción (opcional):</label>
                            <textarea class="form-control" id="descripcion_proyecto" name="descripcion" placeholder="Descripción" rows="3"></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_private" id="is_private_project">
                            <label class="form-check-label" for="is_private_project">
                                <i class="bi bi-lock-fill"></i> Proyecto Privado (solo para mí)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Proyecto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Lightbox para Previsualización -->
    <div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <h5 class="modal-title text-truncate" id="lightboxTitle"></h5>
                        <div>
                            <a href="#" id="lightboxDownload" class="btn btn-primary btn-sm me-2" download><i class="bi bi-download"></i> Descargar</a>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <div class="modal-body text-center" id="lightboxBody">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer justify-content-center">
                    <small class="text-muted"><i class="bi bi-info-circle"></i> Previsualización de archivo. Usa el botón de descarga para guardarlo.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // FORZAR VISIBILIDAD EN MÓVIL - EJECUCIÓN INMEDIATA
        (function() {
            if (window.innerWidth <= 991) {
                console.log('Forzando visibilidad de elementos en móvil...');
                
                // Ejecutar después de que todo se cargue
                setTimeout(function() {
                    const elements = [
                        '.project-view-selector',
                        '#btnNuevoProyecto',
                        '#btnNuevaIdea',
                        '.view-switcher',
                        '#btnVistaTablero'
                    ];
                    
                    elements.forEach(selector => {
                        const element = document.querySelector(selector);
                        if (element) {
                            console.log('Mostrando elemento:', selector);
                            element.style.display = 'block';
                            element.style.visibility = 'visible';
                            element.style.opacity = '1';
                            element.style.position = 'relative';
                            element.style.zIndex = '1000';
                            
                            // Remover cualquier clase que pueda ocultar
                            element.classList.remove('d-none');
                            element.classList.add('d-block');
                            
                            // Especial para el selector de proyectos
                            if (selector === '.project-view-selector') {
                                const label = element.querySelector('label');
                                const select = element.querySelector('select');
                                if (label) label.style.display = 'block';
                                if (select) select.style.display = 'block';
                            }
                        }
                    });
                    
                    // Forzar reflow
                    const contextBar = document.querySelector('.context-bar');
                    if (contextBar) contextBar.offsetHeight;
                    
                }, 300);
            }
        })();
       
        // Función auxiliar para recargar guardando la posición
        function reloadWithScroll() {
            sessionStorage.setItem('scrollPos', window.scrollY);
            location.reload();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // --- SPLASH SCREEN (Pantalla Inicial) ---
            const splashModalElem = document.getElementById('splashScreenModal');
            let splashWasShown = false;

            // Mostrar solo si no se ha mostrado en esta sesión
            if (!sessionStorage.getItem('splashShown')) {
                if (splashModalElem) {
                    const splashModal = new bootstrap.Modal(splashModalElem);
                    splashModal.show();
                    sessionStorage.setItem('splashShown', 'true');

                    // Inicializar Gráfica
                    const ctx = document.getElementById('splashChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Pendientes', 'En Progreso', 'Completados'],
                            datasets: [{
                                data: [
                                    <?= $contadores['pendientes'] ?>, 
                                    <?= $contadores['en_progreso'] ?>, 
                                    <?= $contadores['completados'] ?>
                                ],
                                backgroundColor: ['#ff3b30', '#ff9500', '#34c759'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                            },
                            cutout: '70%'
                        }
                    });
                    splashWasShown = true;
                }
            }

            // --- Lógica para acciones desde URL (para unificar con Kanban) ---
            function handleUrlActions() {
                const urlParams = new URLSearchParams(window.location.search);
                const action = urlParams.get('action');
                const id = urlParams.get('id');

                if (action) {
                    setTimeout(() => {
                        switch (action) {
                            case 'verTarea':
                                if (id) editarTarea(id);
                                break;
                            case 'miPerfil':
                                editarUsuario(<?= $usuario_actual_id ?>);
                                break;
                            case 'gestionarUsuarios':
                                abrirModalUsuarios();
                                break;
                            case 'gestionProyectos':
                                const btn = document.getElementById('verDescripcionesProyectosBtn');
                                if(btn) btn.click();
                                break;
                            case 'gestionEtiquetas':
                                abrirModalEtiquetas();
                                break;
                        }
                        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.hash;
                        window.history.replaceState({path:newUrl},'',newUrl);
                    }, 300);
                }
            }
            handleUrlActions();

            // --- SISTEMA DE NOTIFICACIONES ---
            let lastNotificationCount = -1; // Inicializamos en -1 para no sonar al cargar la página

            // Variables para el parpadeo del título
            let originalTitle = document.title;
            let blinkInterval = null;

            function startBlinking() {
                if (blinkInterval) return;
                let showAlert = true;
                blinkInterval = setInterval(() => {
                    document.title = showAlert ? "🔔 ¡Nueva Notificación!" : originalTitle;
                    showAlert = !showAlert;
                }, 1000);
            }

            function stopBlinking() {
                if (blinkInterval) {
                    clearInterval(blinkInterval);
                    blinkInterval = null;
                    document.title = originalTitle;
                }
            }
            
            // Detener parpadeo al enfocar la ventana o hacer clic
            window.addEventListener('focus', stopBlinking);
            document.addEventListener('click', stopBlinking);

            function cargarNotificaciones() {
                fetch('obtener_notificaciones.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.getElementById('notifBadge');
                        const list = document.getElementById('notifList');
                        
                        // Reproducir sonido si hay MÁS notificaciones que la última vez
                        if (lastNotificationCount !== -1 && data.length > lastNotificationCount) {
                            const audio = document.getElementById('notificationSound');
                            if (audio) audio.play().catch(e => console.log("Audio bloqueado por navegador:", e));
                            
                            // Iniciar parpadeo del título
                            startBlinking();
                        }
                        lastNotificationCount = data.length;

                        if (data.length > 0) {
                            badge.textContent = data.length;
                            badge.style.display = 'inline-block';
                            
                            list.innerHTML = '';
                            data.forEach(notif => {
                                const li = document.createElement('li');
                                const link = document.createElement('a');
                                link.className = 'dropdown-item notification-item unread py-2';
                                link.href = `dashboard.php?action=verTarea&id=${notif.tarea_id}`;
                                link.innerHTML = `<div class="d-flex justify-content-between"><small class="fw-bold">${notif.origen_nombre}</small></div><div class="text-wrap" style="font-size: 0.85rem;">${notif.mensaje}</div>`;
                                link.onclick = function() {
                                    const formData = new FormData();
                                    formData.append('id', notif.id);
                                    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                                    fetch('marcar_notificacion.php', { method: 'POST', body: formData });
                                };
                                li.appendChild(link);
                                list.appendChild(li);
                            });
                        } else {
                            badge.style.display = 'none';
                            list.innerHTML = '<li><span class="dropdown-item text-muted text-center small">No tienes notificaciones nuevas</span></li>';
                        }
                    })
                    .catch(e => console.error(e));
            }
            cargarNotificaciones();
            setInterval(cargarNotificaciones, 30000);

            // Inicializar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            // Lógica para mostrar/ocultar el campo de fecha de término
            const checkFechaTermino = document.getElementById('check_fecha_termino');
            if (checkFechaTermino) {
                const fechaTerminoContainer = document.getElementById('fecha_termino_container');
                const fechaTerminoInput = document.getElementById('fecha_termino');

                checkFechaTermino.addEventListener('change', function() {
                    fechaTerminoContainer.style.display = this.checked ? 'block' : 'none';
                    if (!this.checked) {
                        fechaTerminoInput.value = '';
                    }
                });
            }

            // --- Lógica para sugerencias con IA ---
            const askGeminiBtn = document.getElementById('ask-gemini-btn');
            const estimateTimeBtn = document.getElementById('estimate-time-btn');

            if (askGeminiBtn || estimateTimeBtn) {
                const geminiSuggestionsContainer = document.getElementById('gemini-suggestions-container');
                const descripcionTextarea = document.getElementById('descripcion');
                const tituloInput = document.getElementById('titulo');

                const callAi = (action) => {
                    const titulo = tituloInput.value;
                    const descripcion = descripcionTextarea.value;

                    if (!titulo.trim()) {
                        alert('Por favor, escribe un título antes de pedir una sugerencia.');
                        return;
                    }

                    geminiSuggestionsContainer.style.display = 'block';
                    if(askGeminiBtn) askGeminiBtn.disabled = true;
                    if(estimateTimeBtn) estimateTimeBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('titulo', titulo);
                    formData.append('descripcion', descripcion);
                    formData.append('action', action);
                    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                    fetch('gemini_suggestions.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (action === 'estimate') {
                                    descripcionTextarea.value += '\n\n[Estimación IA]: ' + data.suggestion;
                                } else {
                                    descripcionTextarea.value = data.suggestion;
                                }
                            } else {
                                alert('Error de la IA: ' + (data.error || 'Error desconocido.'));
                            }
                        })
                        .catch(error => console.error('Error al contactar la IA:', error))
                        .finally(() => {
                            geminiSuggestionsContainer.style.display = 'none';
                            if(askGeminiBtn) askGeminiBtn.disabled = false;
                            if(estimateTimeBtn) estimateTimeBtn.disabled = false;
                        });
                };

                if (askGeminiBtn) {
                    askGeminiBtn.addEventListener('click', () => callAi('suggestion'));
                }
                
                if (estimateTimeBtn) {
                    estimateTimeBtn.addEventListener('click', () => callAi('estimate'));
                }
            }

            // --- AJAX para guardar Tareas y Notas sin recarga completa ---
            // --- AJAX para guardar Tareas y Notas sin recarga completa ---
            // Usamos delegación de eventos para asegurar que funcione siempre
            document.addEventListener('submit', function(e) {
                if (e.target && e.target.id === 'formTarea') {
                    e.preventDefault();
                    
                    const form = e.target;
                    const btnGuardar = form.querySelector('button[type="submit"]');
                    const textoOriginal = btnGuardar.innerHTML;
                    btnGuardar.disabled = true;
                    btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

                    const formData = new FormData(form);
                    formData.append('ajax', '1');

                    fetch(form.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const modalEl = document.getElementById('modalTarea');
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                            
                            reloadWithScroll();
                        } else {
                            alert('Error al guardar: ' + (data.error || 'Error desconocido'));
                            btnGuardar.disabled = false;
                            btnGuardar.innerHTML = textoOriginal;
                        }
                    })
                    .catch(error => {
                        console.error('Error al guardar la tarea:', error);
                        alert('Error de conexión o respuesta inválida del servidor.');
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = textoOriginal;
                    });
                }
            });

            // --- Manejo de Notas desde la Tarjeta (Dashboard) ---
            document.querySelectorAll('.form-nota-card').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = this.querySelector('button');
                    const originalBtnHtml = btn.innerHTML;
                    
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    const formData = new FormData(this);
                    formData.append('ajax', '1');

                    fetch(this.action, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if(data.success) {
                                reloadWithScroll();
                            } else {
                                alert('Error: ' + data.error);
                                btn.disabled = false;
                                btn.innerHTML = originalBtnHtml;
                            }
                        })
                        .catch(e => {
                            console.error(e);
                            btn.disabled = false;
                            btn.innerHTML = originalBtnHtml;
                        });
                });
            });

            // --- SISTEMA DE COMENTARIOS EN TIEMPO REAL ---
            let lastCommentTimestamp = '<?= date('Y-m-d H:i:s') ?>';

            setInterval(() => {
                const cards = document.querySelectorAll('.task-card');
                if (cards.length === 0) return;

                const taskIds = Array.from(cards).map(c => c.id.replace('task-', ''));
                if (taskIds.length === 0) return;

                const params = new URLSearchParams({ last_timestamp: lastCommentTimestamp });
                taskIds.forEach(id => params.append('task_ids[]', id));

                fetch(`obtener_nuevas_notas.php?${params.toString()}`)
                    .then(r => r.json())
                    .then(nuevasNotas => {
                        if (nuevasNotas.length > 0) {
                            nuevasNotas.forEach(nota => {
                                // 1. Si el modal está abierto para esta tarea, agregar nota
                                const modalEl = document.getElementById('modalTarea');
                                const modalIdInput = document.getElementById('tarea_id');
                                if (modalEl && modalEl.classList.contains('show') && modalIdInput && modalIdInput.value == nota.tarea_id) {
                                    renderNotaModal(nota);
                                }

                                // 2. Actualizar tarjeta en el dashboard
                                const card = document.getElementById(`task-${nota.tarea_id}`);
                                if (card) {
                                    // Efecto visual (parpadeo naranja)
                                    card.classList.remove('highlight');
                                    void card.offsetWidth; // Forzar reflow para reiniciar animación
                                    card.classList.add('highlight');

                                    // Actualizar contador
                                    let badge = document.getElementById(`note-count-badge-${nota.tarea_id}`);
                                    if (!badge) {
                                        // Si no existe el badge, lo creamos
                                        const badgesContainer = card.querySelector('.d-flex.flex-wrap.gap-2.align-items-center.mb-3');
                                        if (badgesContainer) {
                                            badge = document.createElement('span');
                                            badge.id = `note-count-badge-${nota.tarea_id}`;
                                            badge.className = 'badge bg-light text-dark border ms-1';
                                            badge.innerHTML = '<i class="bi bi-chat-left-text"></i> 0';
                                            badgesContainer.appendChild(badge);
                                        }
                                    }
                                    
                                    if (badge) {
                                        const currentCount = parseInt(badge.innerText.replace(/\D/g, '')) || 0;
                                        const newCount = currentCount + 1;
                                        badge.innerHTML = `<i class="bi bi-chat-left-text"></i> ${newCount}`;
                                        badge.title = `${newCount} comentarios`;
                                    }
                                }
                            });
                            lastCommentTimestamp = nuevasNotas[nuevasNotas.length - 1].fecha_creacion;
                        }
                    })
                    .catch(e => console.error(e));
            }, 7000); // Revisar cada 7 segundos

            // --- Enviar nota desde el modal ---
            const btnSendNote = document.getElementById('btn-agregar-nota-modal');
            const inputNote = document.getElementById('modal-nota-input');
            
            // Crear el input de archivo oculto y el botón de clip
            if (inputNote && !document.getElementById('input-imagen-nota')) {
                const parentDiv = inputNote.closest('.input-group');
                if(parentDiv) {
                    // Crear input file oculto
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.id = 'input-imagen-nota';
                    fileInput.accept = 'image/*';
                    fileInput.style.display = 'none';
                    parentDiv.appendChild(fileInput);

                    // Crear botón clip
                    const btnClip = document.createElement('button');
                    btnClip.className = 'btn btn-outline-secondary';
                    btnClip.type = 'button';
                    btnClip.innerHTML = '<i class="bi bi-paperclip"></i>';
                    btnClip.title = 'Adjuntar imagen';
                    btnClip.onclick = () => fileInput.click();
                    
                    // Insertar antes del input de texto
                    parentDiv.insertBefore(btnClip, inputNote);

                    // Mostrar nombre del archivo seleccionado
                    fileInput.addEventListener('change', () => {
                        if(fileInput.files.length > 0) {
                            btnClip.className = 'btn btn-primary';
                            btnClip.innerHTML = '<i class="bi bi-image"></i>';
                            inputNote.placeholder = 'Imagen seleccionada: ' + fileInput.files[0].name;
                        } else {
                            btnClip.className = 'btn btn-outline-secondary';
                            btnClip.innerHTML = '<i class="bi bi-paperclip"></i>';
                            inputNote.placeholder = 'Agregar nota...';
                        }
                    });
                }
            }

            if (btnSendNote && inputNote) {
                const enviarNota = () => {
                    const texto = inputNote.value.trim();
                    const tareaId = document.getElementById('tarea_id').value;
                    const fileInput = document.getElementById('input-imagen-nota');
                    const hasFile = fileInput && fileInput.files.length > 0;

                    if ((!texto && !hasFile) || !tareaId) return;

                    const formData = new FormData();
                    formData.append('tarea_id', tareaId);
                    formData.append('nota', texto);
                    formData.append('ajax', '1');
                    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                    
                    if (hasFile) {
                        formData.append('imagen', fileInput.files[0]);
                    }

                    btnSendNote.disabled = true;
                    fetch('agregar_nota.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const list = document.getElementById('modal-notas-list');
                                // Eliminar mensaje de "sin comentarios" si existe, sin borrar las notas previas
                                const placeholder = list.querySelector('p.text-muted.text-center');
                                if (placeholder) placeholder.remove();
                                
                                renderNotaModal(data.nota);
                                inputNote.value = '';
                                inputNote.placeholder = 'Agregar nota...';
                                
                                // Resetear input file
                                const fileInput = document.getElementById('input-imagen-nota');
                                if(fileInput) {
                                    fileInput.value = '';
                                    const btnClip = fileInput.parentNode.querySelector('button.btn'); // Encontrar el botón del clip
                                    if(btnClip) {
                                        btnClip.className = 'btn btn-outline-secondary';
                                        btnClip.innerHTML = '<i class="bi bi-paperclip"></i>';
                                    }
                                }
                                
                                lastCommentTimestamp = data.nota.fecha_creacion; // Actualizar timestamp para no auto-notificarse
                            } else {
                                alert('Error al guardar la nota: ' + (data.error || 'Desconocido'));
                            }
                        })
                        .catch(err => { 
                            console.error(err); 
                            alert('Error de conexión al guardar nota.'); 
                        })
                        .finally(() => btnSendNote.disabled = false);
                };
                btnSendNote.addEventListener('click', enviarNota);
                inputNote.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); enviarNota(); } });
            }

            // --- Buscador en tiempo real ---
            const searchInput = document.getElementById('searchInput');
            const searchBtn = document.getElementById('btnSearch');

            function realizarBusqueda() {
                    const searchText = searchInput.value.toLowerCase();
                    const tasks = document.querySelectorAll('.task-card');
                    let visibleCount = 0;

                    tasks.forEach(task => {
                        const title = task.querySelector('.card-title').textContent.toLowerCase();
                        const description = task.querySelector('.card-text').textContent.toLowerCase();
                        
                        if (title.includes(searchText) || description.includes(searchText)) {
                            task.style.display = '';
                            visibleCount++;
                        } else {
                            task.style.display = 'none';
                        }
                    });

                    const counterElement = document.querySelector('.col-md-3.text-md-end strong');
                    if (counterElement) {
                        counterElement.textContent = `Mostrando: ${visibleCount} tarea(s)`;
                    }
            }

            if (searchInput) {
                searchInput.addEventListener('input', realizarBusqueda);
            }

            if (searchBtn) {
                searchBtn.addEventListener('click', realizarBusqueda);
            }
            
            // Listener para el botón de Gestión de Proyectos
            const btnGestionProyectos = document.getElementById('verDescripcionesProyectosBtn');
            if (btnGestionProyectos) {
                btnGestionProyectos.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modal = new bootstrap.Modal(document.getElementById('modalGestionProyectos'));
                    modal.show();
                });
            }

            // Iniciar tour: Si se mostró el splash, esperar a que se cierre. Si no, iniciar tras breve pausa.
            if (typeof iniciarTour === 'function') {
                if (splashWasShown && splashModalElem) {
                    splashModalElem.addEventListener('hidden.bs.modal', () => setTimeout(iniciarTour, 500));
                } else {
                    setTimeout(iniciarTour, 1000);
                }
            }
        });

        // DEPURACIÓN MEJORADA DEL BOTÓN NUEVA TAREA
        console.log('=== DEPURACIÓN BOTÓN NUEVA TAREA ===');

        // Verificar que la función existe
        console.log('Función abrirModalTarea:', typeof abrirModalTarea);

        // Capturar todos los botones de nueva tarea
        document.querySelectorAll('[id*="Nueva"], [onclick*="abrirModalTarea"]').forEach(btn => {
            console.log('Botón encontrado:', btn.id, btn.className);
            
            // Remover onclick antiguo y agregar nuevo con depuración
            const oldOnClick = btn.getAttribute('onclick');
            if (oldOnClick) {
                console.log('Onclick original:', oldOnClick);
                btn.removeAttribute('onclick');
                
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('=== CLICK EN BOTÓN NUEVA TAREA ===');
                    console.log('ID del botón:', this.id);
                    
                    const proyectoId = <?= $proyecto_id ?? 'null' ?>;
                    const visibility = '<?= $filtro_vista == 'personal' ? 'private' : 'public' ?>';
                    
                    console.log('Llamando abrirModalTarea con:', proyectoId, visibility);
                    
                    // Verificar si el modal existe
                    const modal = document.getElementById('modalTarea');
                    console.log('Modal existe?', !!modal);
                    
                    if (modal) {
                        console.log('Modal clases:', modal.className);
                    }
                    
                    // Llamar a la función
                    abrirModalTarea(proyectoId, visibility);
                });
            }
        });

        // También verificar si hay errores en la consola
        window.addEventListener('error', function(e) {
            console.error('Error global:', e.message, 'en', e.filename, 'línea', e.lineno);
        });
    </script>

    <!-- Sonido de Notificación -->
    <audio id="notificationSound" src="https://cdn.pixabay.com/download/audio/2022/03/24/audio_ff70e2a305.mp3?filename=notification-sound-7062.mp3" preload="auto"></audio>
</body>
</html>
