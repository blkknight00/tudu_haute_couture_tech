<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$usuario_actual_nombre = $_SESSION['usuario_nombre'];
$usuario_actual_id = $_SESSION['usuario_id'];
$usuario_actual_rol = trim($_SESSION['usuario_rol']); // Limpiar espacios
$usuario_actual_username = $_SESSION['usuario_username'];
$usuario_actual_foto = $_SESSION['usuario_foto'] ?? null;

// --- FILTROS (Reutilizamos la lógica del Dashboard) ---
$proyecto_id = isset($_GET['proyecto_id']) ? $_GET['proyecto_id'] : null;
$filtro_usuario = isset($_GET['usuario_id']) ? $_GET['usuario_id'] : null;
$filtro_vista = isset($_GET['vista']) ? $_GET['vista'] : 'publico';

// Obtener proyectos para el selector
$params_proyectos = [];
if (in_array($usuario_actual_rol, ['admin', 'super_admin'])) {
    if ($filtro_vista == 'personal') {
        $sql_proyectos = "SELECT * FROM proyectos WHERE user_id IS NOT NULL ORDER BY nombre";
    } else {
        $sql_proyectos = "SELECT * FROM proyectos WHERE user_id IS NULL ORDER BY nombre";
    }
} else {
    $sql_proyectos = "SELECT * FROM proyectos WHERE " . ($filtro_vista == 'personal' ? "user_id = ?" : "user_id IS NULL") . " ORDER BY nombre";
    if ($filtro_vista == 'personal') {
        $params_proyectos[] = $usuario_actual_id;
    }
}
$stmt_proyectos = $pdo->prepare($sql_proyectos);
$stmt_proyectos->execute($params_proyectos);
$proyectos = $stmt_proyectos->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuarios (para el modal de edición)
$stmt_usuarios = $pdo->query("SELECT id, username, nombre, telefono FROM usuarios WHERE activo = 1 ORDER BY nombre");
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las etiquetas (para el modal de edición)
$stmt_all_tags = $pdo->query("SELECT id, nombre, color FROM etiquetas ORDER BY nombre");
$todas_las_etiquetas = $stmt_all_tags->fetchAll(PDO::FETCH_ASSOC);

// Construir consulta de tareas
$sql = "SELECT t.*, p.nombre as proyecto_nombre 
        FROM tareas t 
        JOIN proyectos p ON t.proyecto_id = p.id 
        WHERE t.estado != 'archivado' ";
$params = [];

if (in_array($usuario_actual_rol, ['admin', 'super_admin']) && $proyecto_id) {
    // Admin viendo un proyecto específico: ver todo
} else {
    if ($filtro_vista == 'personal') {
        $sql .= "AND t.visibility = 'private' AND t.usuario_id = ? ";
        $params[] = $usuario_actual_id;
    } else {
        $sql .= "AND t.visibility = 'public' ";
    }
}

if ($proyecto_id) {
    $sql .= "AND t.proyecto_id = ? ";
    $params[] = $proyecto_id;
}

if ($filtro_usuario) {
    $sql .= "AND EXISTS (SELECT 1 FROM tarea_asignaciones ta_f WHERE ta_f.tarea_id = t.id AND ta_f.usuario_id = ?) ";
    $params[] = $filtro_usuario;
}

$sql .= "ORDER BY t.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener asignados (para mostrar en las tarjetas)
$asignados_map = [];
if (!empty($tareas)) {
    $ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql_asig = "SELECT ta.tarea_id, u.nombre, u.foto_perfil FROM tarea_asignaciones ta JOIN usuarios u ON ta.usuario_id = u.id WHERE ta.tarea_id IN ($placeholders)";
    $stmt_asig = $pdo->prepare($sql_asig);
    $stmt_asig->execute($ids);
    while ($row = $stmt_asig->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($asignados_map[$row['tarea_id']])) {
            $asignados_map[$row['tarea_id']] = ['nombre' => $row['nombre'], 'foto' => $row['foto_perfil']];
        }
    }
}

// Obtener etiquetas
$etiquetas_map = [];
if (!empty($tareas)) {
    $ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql_tags = "SELECT te.tarea_id, e.nombre, e.color FROM tarea_etiquetas te JOIN etiquetas e ON te.etiqueta_id = e.id WHERE te.tarea_id IN ($placeholders)";
    $stmt_tags = $pdo->prepare($sql_tags);
    $stmt_tags->execute($ids);
    while ($row = $stmt_tags->fetch(PDO::FETCH_ASSOC)) {
        $etiquetas_map[$row['tarea_id']][] = $row;
    }
}

// Obtener archivos adjuntos (detalles para mostrar en tarjeta)
$archivos_map = [];
if (!empty($tareas)) {
    $ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql_files = "SELECT tarea_id, id, nombre_original, nombre_archivo, tipo_archivo FROM archivos_adjuntos WHERE tarea_id IN ($placeholders)";
    $stmt_files = $pdo->prepare($sql_files);
    $stmt_files->execute($ids);
    while ($row = $stmt_files->fetch(PDO::FETCH_ASSOC)) {
        $archivos_map[$row['tarea_id']][] = $row;
    }
}

// Obtener progreso de subtareas (para mostrar en tarjeta)
$subtareas_map = [];
if (!empty($tareas)) {
    $ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql_subs = "SELECT tarea_id, COUNT(*) as total, SUM(CASE WHEN completado = 1 THEN 1 ELSE 0 END) as completados FROM subtareas WHERE tarea_id IN ($placeholders) GROUP BY tarea_id";
    $stmt_subs = $pdo->prepare($sql_subs);
    $stmt_subs->execute($ids);
    while ($row = $stmt_subs->fetch(PDO::FETCH_ASSOC)) {
        $subtareas_map[$row['tarea_id']] = $row;
    }
}

// Obtener conteo de notas (para mostrar en tarjeta)
$notas_map = [];
if (!empty($tareas)) {
    $ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql_notas = "SELECT tarea_id, COUNT(*) as total FROM notas_tareas WHERE tarea_id IN ($placeholders) GROUP BY tarea_id";
    $stmt_notas = $pdo->prepare($sql_notas);
    $stmt_notas->execute($ids);
    while ($row = $stmt_notas->fetch(PDO::FETCH_ASSOC)) {
        $notas_map[$row['tarea_id']] = $row['total'];
    }
}

// Contadores (solo necesitamos 'archivados' para el menú)
$stmt_contadores = $pdo->query("SELECT COUNT(CASE WHEN estado = 'archivado' THEN 1 END) as archivados FROM tareas");
$contadores = $stmt_contadores->fetch(PDO::FETCH_ASSOC);

// Configuración (Logo, etc) - Unificado con Dashboard
$stmt_config = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('TUDU_LICENSE_KEY', 'APP_STATUS', 'APP_LOGO')");
$config = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);

// Organizar tareas por columnas
$columnas = ['pendiente' => [], 'en_progreso' => [], 'completado' => []];
foreach ($tareas as $t) {
    $t['asignado_info'] = $asignados_map[$t['id']] ?? null;
    $t['tags'] = $etiquetas_map[$t['id']] ?? [];
    $t['archivos'] = $archivos_map[$t['id']] ?? [];
    $t['total_notas'] = $notas_map[$t['id']] ?? 0;
    $t['subtareas_info'] = $subtareas_map[$t['id']] ?? null;
    if (isset($columnas[$t['estado']])) {
        $columnas[$t['estado']][] = $t;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablero Kanban - TuDu</title>
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <!-- SortableJS para Drag & Drop -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <style>
        /* --- Variables para Temas (UNIFICADO CON DASHBOARD) --- */
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

        .kanban-board {
            display: flex;
            gap: 1.5rem;
            overflow-x: auto;
            padding-bottom: 1rem;
            height: calc(100vh - 200px); /* Altura dinámica */
        }
        .kanban-column {
            flex: 1;
            min-width: 300px;
            background-color: var(--column-background);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color);
        }
        .kanban-header {
            padding: 1rem;
            font-weight: bold;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--content-background);
            border-radius: 12px 12px 0 0;
        }
        .kanban-body {
            padding: 1rem;
            flex-grow: 1;
            overflow-y: auto;
        }
        .kanban-card {
            background: var(--content-background);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.8rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            cursor: grab;
            border-left: 4px solid transparent;
            transition: transform 0.2s, box-shadow 0.2s;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            touch-action: none; /* Prioridad total al arrastre, desactiva scroll en la tarjeta */
        }
        .kanban-card * {
            user-select: none; /* Asegurar que los hijos (textos, iconos) tampoco sean seleccionables */
        }
        .kanban-card:active { cursor: grabbing; }
        .kanban-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        /* Colores de borde por estado */
        .card-pendiente { border-left-color: #ff3b30; }
        .card-progreso { border-left-color: #ff9500; }
        .card-completado { border-left-color: #34c759; }
        
        /* Estilo cuando se arrastra */
        .sortable-ghost { opacity: 0.4; background-color: #e9ecef; }
        .sortable-drag { cursor: grabbing; opacity: 1; background: white; transform: scale(1.02); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
        
        /* Animación de parpadeo para nuevos comentarios */
        @keyframes flash-highlight {
            0% { background-color: var(--content-background); }
            50% { background-color: #fff3cd; border-color: #ffc107; }
            100% { background-color: var(--content-background); }
        }
        .card-highlight { animation: flash-highlight 1s ease-in-out 3; }
        
        /* Estilo para recortar texto a 2 líneas */
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Estilo unificado para notas */
        .nota-item {
            background: var(--column-background);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 3px solid var(--accent-color);
        }
        
        /* Avatar en Kanban */
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .user-avatar-sm { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; margin-right: 4px; }

        .kanban-card h6 {
            color: var(--text-color);
        }

        /* Ajustes para barra de contexto (para unificar con dashboard) */
        .header-controls .btn {
            padding: 0.25rem 0.6rem; /* Padding más reducido */
            font-weight: 400;
        }

        .header-controls .btn,
        .header-controls .form-select,
        .header-controls label {
            font-size: 0.8rem; /* Tamaño de fuente más pequeño */
        }

        /* Corrección para títulos de modal en modo oscuro */
        [data-bs-theme="dark"] .modal-title,
        [data-bs-theme="dark"] .modal-body .form-label,
        [data-bs-theme="dark"] .modal-body .form-check-label {
            color: var(--text-color);
        }

        /* --- RESPONSIVO PARA MÓVIL (KANBAN HORIZONTAL) --- */
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
                display: block !important;
                visibility: visible !important;
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
            
            .header-controls .btn, .header-controls .form-select {
                font-size: 0.75rem !important;
            }

            /* FORCE HORIZONTAL SCROLL FOR KANBAN (Industry Standard Mobile View) */
            .kanban-board {
                flex-direction: row !important; /* Keep columns side-by-side */
                overflow-x: auto !important; /* Allow scrolling */
                scroll-snap-type: x mandatory; /* Snap to columns */
                padding-bottom: 20px;
                gap: 15px; /* Spacing between columns */
                justify-content: flex-start; /* Align left */
            }
            
            .kanban-column {
                min-width: 85vw !important; /* Columns take 85% of screen width */
                max-width: 85vw !important;
                scroll-snap-align: center; /* Snap to center of screen */
                height: calc(100vh - 220px) !important; /* Occupy full available vertical space */
            }
            
            /* Hide non-critical buttons to save space */
            .input-group { width: 100% !important; max-width: none !important; margin-top: 5px; } 
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
        // Aplicar tema inmediatamente para evitar "flash" blanco al recargar
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();

        // Funciones Helper Globales (Necesarias para renderNotaModal)
        function escapeHtml(text) { return text ? String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;") : ''; }
        
        function formatDate(dateString) {
            const date = new Date(dateString + 'Z'); // Asumir UTC
            return `${date.getDate().toString().padStart(2,'0')}/${(date.getMonth()+1).toString().padStart(2,'0')} ${date.getHours().toString().padStart(2,'0')}:${date.getMinutes().toString().padStart(2,'0')}`;
        }

        // Función para renderizar notas en el modal (Faltaba esta definición)
        function renderNotaModal(nota) {
            const list = document.getElementById('modal-notas-list');
            if (!list) return;
            
            let noteContent = escapeHtml(nota.nota).replace(/\n/g, '<br>');
            noteContent = noteContent.replace(/@(\w+)/g, '<span class="text-primary fw-bold">@$1</span>');
            const nombreMostrar = nota.usuario_nombre || nota.nombre_invitado || 'Invitado';
            const div = document.createElement('div');
            div.className = 'nota-item mb-2';
            div.innerHTML = `<div class="d-flex justify-content-between"><strong>${escapeHtml(nombreMostrar)}</strong><small class="text-muted">${formatDate(nota.fecha_creacion)}</small></div><p class="mb-0">${noteContent}</p>`;
            list.appendChild(div);
            list.scrollTop = list.scrollHeight;
        }
    </script>
</head>
<body>
    <!-- Header Fijo (Igual que Dashboard) -->
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
                        <li><a class="dropdown-item" href="dashboard.php?action=miPerfil"><i class="bi bi-person-gear"></i> Mi Perfil</a></li>
                        <li><a class="dropdown-item" href="guia.php"><i class="bi bi-book"></i> Guía de Uso</a></li>
                        <?php if (in_array(strtolower(trim($usuario_actual_rol)), ['admin', 'super_admin'])): ?>
                            <li class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="dashboard.php?action=gestionProyectos"><i class="bi bi-kanban me-2"></i>Gestión de Proyectos</a></li>
                            <li><a class="dropdown-item" href="dashboard.php?action=gestionEtiquetas"><i class="bi bi-tags me-2"></i>Gestionar Etiquetas</a></li>
                            <li><a class="dropdown-item" href="dashboard.php?action=gestionarUsuarios"><i class="bi bi-people"></i> Gestionar Usuarios</a></li>
                            <li><a class="dropdown-item" href="#" onclick="abrirPopupConfigIA()"><i class="bi bi-robot"></i> Configurar IA</a></li>
                            <li><a class="dropdown-item" href="auditoria.php"><i class="bi bi-clipboard-data"></i> Auditoría</a></li>
                            <?php if ($usuario_actual_rol == 'super_admin'): ?>
                                <li><a class="dropdown-item text-primary" href="dashboard.php?action=licencia"><i class="bi bi-shield-lock"></i> Panel Licencia</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="archivo.php">
                            <i class="bi bi-archive me-2"></i> Archivo
                            <span class="badge rounded-pill bg-secondary ms-2"><?= $contadores['archivados'] ?></span>
                        </a></li>
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
                            <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-list-ul me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item active" href="kanban.php"><i class="bi bi-kanban me-2"></i>Tablero Kanban</a></li>
                            <li><a class="dropdown-item" href="calendar.php"><i class="bi bi-calendar-week me-2"></i>Calendario</a></li>
                        </ul>
                    </div>

                    <!-- Project Selector -->
                    <div class="project-view-selector d-flex align-items-center gap-2">
                         <label for="project-selector" class="mb-0 text-nowrap small text-muted d-none d-lg-block">Proyecto:</label>
                        <select class="form-select form-select-sm" id="project-selector" onchange="if(this.value) window.location.href=this.value" style="max-width: 200px;">
                            <option value="kanban.php?vista=<?= $filtro_vista ?>">📁 Todos</option>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <option value="kanban.php?vista=<?= $filtro_vista ?>&proyecto_id=<?= $proyecto['id'] ?>" <?= $proyecto_id == $proyecto['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proyecto['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Grupo de botones para la vista -->
                    <div class="btn-group view-switcher" role="group">
                        <a href="kanban.php?vista=publico&proyecto_id=<?= $proyecto_id ?>" class="btn <?= $filtro_vista == 'publico' ? 'btn-primary' : 'btn-outline-secondary' ?>">Públicos</a>
                        <a href="kanban.php?vista=personal&proyecto_id=<?= $proyecto_id ?>" class="btn <?= $filtro_vista == 'personal' ? 'btn-primary' : 'btn-outline-secondary' ?>">Personales</a>
                    </div>
                </div>

                <div class="vr mx-2 d-none d-md-block"></div>

                <!-- Row 2: Actions -->
                <div class="d-flex gap-2 align-items-center mobile-row justify-content-end">
                    <button class="btn btn-success btn-sm flex-fill" id="btnNuevoProyecto" onclick="abrirModalProyecto()">
                        <i class="bi bi-plus-circle"></i> Nuevo Proyecto
                    </button>
                    <button class="btn btn-primary btn-sm flex-fill" id="btnNuevaIdea" onclick="abrirModalTarea('pendiente')">
                        <i class="bi bi-plus-circle"></i> Nueva Tarea
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 my-4">
        <div class="kanban-board">
            
            <!-- Columna Pendiente -->
            <div class="kanban-column">
                <div class="kanban-header border-bottom border-danger border-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-danger"><i class="bi bi-circle"></i> Pendiente</span>
                        <span class="badge bg-secondary rounded-pill count-badge"><?= count($columnas['pendiente']) ?></span>
                    </div>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="abrirModalTarea('pendiente')" title="Agregar tarea"><i class="bi bi-plus-lg"></i></button>
                </div>
                <div class="kanban-body" id="col-pendiente" data-estado="pendiente">
                    <?php foreach ($columnas['pendiente'] as $t): renderCard($t, 'pendiente'); endforeach; ?>
                </div>
            </div>

            <!-- Columna En Progreso -->
            <div class="kanban-column">
                <div class="kanban-header border-bottom border-warning border-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-warning"><i class="bi bi-play-circle"></i> En Progreso</span>
                        <span class="badge bg-secondary rounded-pill count-badge"><?= count($columnas['en_progreso']) ?></span>
                    </div>
                    <button class="btn btn-sm btn-outline-warning py-0 px-2" onclick="abrirModalTarea('en_progreso')" title="Agregar tarea"><i class="bi bi-plus-lg"></i></button>
                </div>
                <div class="kanban-body" id="col-en_progreso" data-estado="en_progreso">
                    <?php foreach ($columnas['en_progreso'] as $t): renderCard($t, 'progreso'); endforeach; ?>
                </div>
            </div>

            <!-- Columna Completado -->
            <div class="kanban-column">
                <div class="kanban-header border-bottom border-success border-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-success"><i class="bi bi-check-circle"></i> Completado</span>
                        <span class="badge bg-secondary rounded-pill count-badge"><?= count($columnas['completado']) ?></span>
                    </div>
                    <button class="btn btn-sm btn-outline-success py-0 px-2" onclick="abrirModalTarea('completado')" title="Agregar tarea"><i class="bi bi-plus-lg"></i></button>
                </div>
                <div class="kanban-body" id="col-completado" data-estado="completado">
                    <?php foreach ($columnas['completado'] as $t): renderCard($t, 'completado'); endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Botón flotante para mobile -->
    <div class="d-lg-none">
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
            <button class="btn btn-primary btn-lg rounded-circle shadow-lg" onclick="abrirModalTarea('pendiente')" style="width: 60px; height: 60px;">
                <i class="bi bi-plus"></i>
            </button>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Proyecto -->
    <div class="modal fade" id="modalProyecto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProyectoTitle">Nuevo Proyecto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formProyecto" action="guardar_proyecto.php" method="POST">
                        <input type="hidden" name="id" id="proyecto_id_edit">
                        <div class="mb-3">
                            <label for="nombre_proyecto" class="form-label">Nombre del Proyecto</label>
                            <input type="text" class="form-control" id="nombre_proyecto" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_proyecto" class="form-label">Descripción (Opcional)</label>
                            <textarea class="form-control" id="descripcion_proyecto" name="descripcion" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="color_proyecto" class="form-label">Color (Opcional)</label>
                            <input type="color" class="form-control form-control-color" id="color_proyecto" name="color" value="#0d6efd">
                        </div>
                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="btnGuardarProyecto"><i class="bi bi-save"></i> Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sonido de éxito -->
    <audio id="successAudio" src="https://cdn.pixabay.com/download/audio/2021/08/04/audio_bb630cc098.mp3?filename=success-1-6297.mp3" preload="auto"></audio>
    <!-- Sonido de notificación -->
    <audio id="notificationSound" src="https://cdn.pixabay.com/download/audio/2022/03/24/audio_ff70e2a305.mp3?filename=notification-sound-7062.mp3" preload="auto"></audio>

    <?php
    function renderCard($t, $clase) {
        echo '<div class="kanban-card card-' . $clase . '" data-id="' . $t['id'] . '">';
        echo '<div class="d-flex justify-content-between mb-1">';
        echo '<div class="mb-1"><small class="text-muted" style="font-size:0.75rem" title="Proyecto"><i class="bi bi-folder2"></i> ' . htmlspecialchars($t['proyecto_nombre']) . '</small></div>';
        if($t['visibility'] == 'private') echo '<i class="bi bi-lock-fill text-muted" style="font-size:0.75rem"></i>';
        
        $prio = $t['prioridad'] ?? 'media';
        if($prio == 'alta') echo '<i class="bi bi-flag-fill text-danger" style="font-size:0.75rem" title="Prioridad Alta"></i>';
        elseif($prio == 'baja') echo '<i class="bi bi-flag-fill text-success" style="font-size:0.75rem" title="Prioridad Baja"></i>';
        
        echo '</div>';
        echo '<h6 class="mb-2 text-truncate-2" style="font-size: 0.9rem; cursor: pointer;" onclick="editarTarea('.$t['id'].')" title="'.htmlspecialchars($t['titulo']).'">' . htmlspecialchars($t['titulo']);
        // Icono de adjunto en el título
        if (!empty($t['archivos'])) {
            echo ' <i class="bi bi-paperclip text-muted ms-1" style="font-size: 0.9em;" title="' . count($t['archivos']) . ' archivos adjuntos"></i>';
        }
        echo '</h6>';

        if (!empty($t['descripcion'])) {
            $desc_html = htmlspecialchars($t['descripcion']);
            $desc_html = preg_replace('/@(\w+)/', '<span class="text-primary fw-bold">@$1</span>', $desc_html);
            echo '<p class="text-muted mb-2 text-truncate" style="font-size: 0.85rem; cursor: pointer;" onclick="editarTarea('.$t['id'].')">' . $desc_html . '</p>';
        }
        
        if (!empty($t['tags'])) {
            echo '<div class="mb-2">';
            foreach ($t['tags'] as $tag) {
                $bgColor = $tag['color'] ?? '#6c757d';
                $textColor = getContrastingTextColor($bgColor);
                echo '<span class="badge me-1" style="background-color: ' . $bgColor . '; color: ' . $textColor . '; font-size: 0.65rem; font-weight: normal;">' . htmlspecialchars($tag['nombre']) . '</span>';
            }
            echo '</div>';
        }

        echo '<div class="d-flex justify-content-between align-items-center mt-2">';
        
        // Fecha de término
        if (!empty($t['fecha_termino'])) {
            $ft = new DateTime($t['fecha_termino']);
            $hoy = new DateTime('today');
            $colorClass = 'text-muted';
            if ($t['estado'] != 'completado') {
                if ($ft < $hoy) $colorClass = 'text-danger fw-bold';
                elseif ($ft == $hoy) $colorClass = 'text-warning fw-bold';
            }
            echo '<small class="'.$colorClass.' me-2" title="Vencimiento: '.$ft->format('d/m/Y').'"><i class="bi bi-calendar-event"></i> ' . $ft->format('d/m') . '</small>';
        }

        // Subtareas
        if (!empty($t['subtareas_info']) && $t['subtareas_info']['total'] > 0) {
            echo '<small class="text-muted me-2" title="Progreso: '.$t['subtareas_info']['completados'].' de '.$t['subtareas_info']['total'].' pasos"><i class="bi bi-check2-square"></i> ' . $t['subtareas_info']['completados'] . '/' . $t['subtareas_info']['total'] . '</small>';
        }

        // Archivos
        if (!empty($t['archivos'])) {
            echo '<small class="text-muted me-2"><i class="bi bi-paperclip"></i> ' . count($t['archivos']) . '</small>';
        }

        if ($t['total_notas'] > 0) {
            echo '<small class="text-muted me-2" id="note-count-'.$t['id'].'"><i class="bi bi-chat-left-text"></i> ' . $t['total_notas'] . '</small>';
        }
        
        // Mostrar asignado
        echo '<small class="text-muted d-flex align-items-center">';
        if ($t['asignado_info']) {
            if(!empty($t['asignado_info']['foto'])) echo '<img src="'.htmlspecialchars($t['asignado_info']['foto']).'" class="user-avatar-sm">';
            else echo '<i class="bi bi-person me-1"></i>';
            echo htmlspecialchars(explode(' ', $t['asignado_info']['nombre'])[0]);
        } else {
            echo 'Sin asignar';
        }
        echo '</small>';

        echo '<button onclick="editarTarea('.$t['id'].')" class="btn btn-xs btn-outline-primary border-0"><i class="bi bi-pencil-fill"></i></button>';
        echo '</div>';
        echo '</div>';
    }
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Lógica de Modo Oscuro ---
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const iconTheme = document.getElementById('iconTheme');
            const htmlElement = document.documentElement;

            // Actualizar icono según el tema actual
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

            function updateThemeIcon(theme) {
                if(iconTheme) {
                    if (theme === 'dark') {
                        iconTheme.className = 'bi bi-sun-fill';
                    } else {
                        iconTheme.className = 'bi bi-moon-stars-fill';
                    }
                }
            }

            // Inicializar Popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) { return new bootstrap.Popover(popoverTriggerEl) })

            // Inicializar SortableJS en las 3 columnas
            const columnas = ['col-pendiente', 'col-en_progreso', 'col-completado'];
            
            columnas.forEach(colId => {
                const el = document.getElementById(colId);
                if (el) {
                    new Sortable(el, {
                        group: 'tablero', // Permite mover entre listas del mismo grupo
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        dragClass: 'sortable-drag',
                        onEnd: function (evt) {
                            const itemEl = evt.item;
                            const nuevoEstado = evt.to.getAttribute('data-estado');
                            const tareaId = itemEl.getAttribute('data-id');

                            // Reproducir sonido si se mueve a completado
                            if (nuevoEstado === 'completado') {
                                const audio = document.getElementById('successAudio');
                                if (audio) audio.play().catch(e => console.log(e));
                            }

                            // Actualizar contadores visualmente
                            document.querySelectorAll('.kanban-column').forEach(col => {
                                const count = col.querySelector('.kanban-body').children.length;
                                col.querySelector('.count-badge').textContent = count;
                            });

                            // Enviar actualización al servidor
                            fetch('actualizar_kanban.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ tarea_id: tareaId, estado: nuevoEstado })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if(!data.success) alert('Error al guardar cambio');
                            })
                            .catch(err => console.error(err));
                        }
                    });
                }
            });

            // --- Buscador en tiempo real ---
            const searchInput = document.getElementById('searchInput');
            const searchBtn = document.getElementById('btnSearch');
            
            function realizarBusqueda() {
                const searchText = searchInput.value.toLowerCase();
                const cards = document.querySelectorAll('.kanban-card');

                cards.forEach(card => {
                    const textContent = card.textContent.toLowerCase();
                    card.style.display = textContent.includes(searchText) ? '' : 'none';
                });

                // Actualizar contadores de columnas
                document.querySelectorAll('.kanban-column').forEach(col => {
                    let visibleCount = 0;
                    col.querySelectorAll('.kanban-card').forEach(card => {
                        if (card.style.display !== 'none') visibleCount++;
                    });
                    col.querySelector('.count-badge').textContent = visibleCount;
                });
            }

            if (searchInput) searchInput.addEventListener('input', realizarBusqueda);
            if (searchBtn) searchBtn.addEventListener('click', realizarBusqueda);

            // --- SISTEMA DE NOTIFICACIONES ---
            let lastNotificationCount = -1;

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
                        
                        // Reproducir sonido si hay nuevas notificaciones
                        if (lastNotificationCount !== -1 && data.length > lastNotificationCount) {
                            const audio = document.getElementById('notificationSound');
                            if (audio) audio.play().catch(e => console.log("Audio bloqueado:", e));
                            
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
                                    fetch('marcar_notificacion.php', { method: 'POST', body: formData });
                                };
                                li.appendChild(link);
                                list.appendChild(li);
                            });
                        } else {
                            badge.style.display = 'none';
                            list.innerHTML = '<li><span class="dropdown-item text-muted text-center small">No tienes notificaciones nuevas</span></li>';
                        }
                    });
            }
            cargarNotificaciones();
            setInterval(cargarNotificaciones, 30000);

            // --- SISTEMA DE COMENTARIOS EN TIEMPO REAL ---
            let lastCommentTimestamp = '<?= date('Y-m-d H:i:s') ?>';
            let currentOpenTaskId = null; // Para saber qué tarea está abierta en el modal

            // Enviar nota desde el modal
            const btnSendNote = document.getElementById('btn-agregar-nota-modal');
            const inputNote = document.getElementById('modal-nota-input');

            if (btnSendNote && inputNote) {
                const enviarNota = () => {
                    const texto = inputNote.value.trim();
                    if (!texto || !currentOpenTaskId) return;

                    const formData = new FormData();
                    formData.append('tarea_id', currentOpenTaskId);
                    formData.append('nota', texto);
                    formData.append('ajax', '1');

                    btnSendNote.disabled = true;
                    fetch('agregar_nota.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const list = document.getElementById('modal-notas-list');
                                const placeholder = list.querySelector('p.text-muted.text-center');
                                if (placeholder) placeholder.remove();

                                renderNotaModal(data.nota); // Llama a la función global
                                inputNote.value = '';
                                lastCommentTimestamp = data.nota.fecha_creacion;
                            } else {
                                alert('Error al guardar la nota: ' + (data.error || 'Desconocido'));
                            }
                        })
                        .catch(err => { console.error(err); alert('Error de conexión al guardar nota.'); })
                        .finally(() => btnSendNote.disabled = false);
                };

                btnSendNote.addEventListener('click', enviarNota);
                inputNote.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); enviarNota(); }
                });
            }

            // Polling global (funciona para el modal y para las tarjetas)
            setInterval(() => {
                const cards = document.querySelectorAll('.kanban-card');
                if (cards.length === 0) return;

                const taskIds = Array.from(cards).map(c => c.getAttribute('data-id'));
                const params = new URLSearchParams({ last_timestamp: lastCommentTimestamp });
                taskIds.forEach(id => params.append('task_ids[]', id));

                fetch(`obtener_nuevas_notas.php?${params.toString()}`)
                    .then(r => r.json())
                    .then(nuevasNotas => {
                        if (nuevasNotas.length > 0) {
                            nuevasNotas.forEach(nota => {
                                // 1. Si el modal de esta tarea está abierto, agregar nota
                                if (currentOpenTaskId == nota.tarea_id) {
                                    renderNotaModal(nota); // Llama a la función global
                                }

                                // 2. Actualizar contador en la tarjeta y resaltar
                                const card = document.querySelector(`.kanban-card[data-id="${nota.tarea_id}"]`);
                                if (card) {
                                    // Efecto visual
                                    card.classList.remove('card-highlight');
                                    void card.offsetWidth; // Trigger reflow
                                    card.classList.add('card-highlight');

                                    // Actualizar contador (o crearlo si no existe)
                                    let badge = document.getElementById(`note-count-${nota.tarea_id}`);
                                    if (!badge) {
                                        // Si no existía, lo insertamos antes del usuario asignado
                                        const footerDiv = card.lastElementChild; // El div con botones
                                        badge = document.createElement('small');
                                        badge.id = `note-count-${nota.tarea_id}`;
                                        badge.className = 'text-muted me-2';
                                        footerDiv.insertBefore(badge, footerDiv.firstChild);
                                    }
                                    // Incrementar número (simple parseo)
                                    const currentCount = parseInt(badge.innerText.replace(/\D/g, '')) || 0;
                                    badge.innerHTML = `<i class="bi bi-chat-left-text"></i> ${currentCount + 1}`;
                                }
                            });
                            // Actualizar timestamp al más reciente
                            lastCommentTimestamp = nuevasNotas[nuevasNotas.length - 1].fecha_creacion;
                        }
                    })
                    .catch(e => console.error(e));
            }, 7000);

            // Helpers globales para este scope
            window.setCurrentOpenTaskId = (id) => { currentOpenTaskId = id; };

            // --- AUTOCOMPLETADO DE MENCIONES (@) ---
            const usuariosSistema = <?= json_encode(array_map(function($u) {
                return ['username' => $u['username'], 'nombre' => $u['nombre']];
            }, $usuarios)) ?>;

            const suggestionBox = document.createElement('div');
            suggestionBox.className = 'suggestions-box';
            document.body.appendChild(suggestionBox);

            document.addEventListener('input', function(e) {
                if (e.target.id === 'modal-nota-input') {
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
                                div.innerHTML = `<strong>${u.nombre}</strong> <small>@${u.username}</small>`;
                                div.onclick = function() {
                                    const textBefore = textBeforeCursor.substring(0, textBeforeCursor.lastIndexOf(currentWord));
                                    const textAfter = val.substring(cursorPos);
                                    input.value = textBefore + '@' + u.username + ' ' + textAfter;
                                    suggestionBox.style.display = 'none';
                                    input.focus();
                                };
                                suggestionBox.appendChild(div);
                            });
                        } else { suggestionBox.style.display = 'none'; }
                    } else { suggestionBox.style.display = 'none'; }
                }
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.suggestions-box')) {
                    suggestionBox.style.display = 'none';
                }
            });

            // Asignar funciones globales para que funcionen los onclick del HTML
            window.abrirModalTarea = abrirModalTarea;
            window.editarTarea = editarTarea;
        });

        function abrirPopupConfigIA() {
            const isMobile = window.innerWidth <= 768;
            const url = 'config_ia.php';

            if (isMobile) {
                window.location.href = url; // Abrir en la misma pestaña en móvil
            } else {
                const windowName = 'configIaPopup';
                const windowFeatures = 'width=800,height=600,scrollbars=yes,resizable=yes';
                window.open(url, windowName, windowFeatures); // Abrir en popup en escritorio
            }
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
    </script>

    <!-- Modal para Editar Tarea (Copiado y adaptado de Dashboard) -->
    <div class="modal fade" id="modalTarea" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTareaTitle">Editar Idea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="guardar_tarea.php" id="formTarea" enctype="multipart/form-data">
                    <input type="hidden" name="tarea_id" id="tarea_id" value="">
                    <input type="hidden" name="estado" id="input_estado" value="pendiente">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Título:</label>
                                    <input type="text" class="form-control" name="titulo" id="titulo" required>
                                    <div id="edit-lock-info" class="form-text text-warning small mt-1" style="display: none;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"></label>
                                    <select class="form-select" name="proyecto_id" id="proyecto_id" required>
                                        <?php foreach ($proyectos as $proyecto): ?>
                                            <option value="<?= $proyecto['id'] ?>"><?= htmlspecialchars($proyecto['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Prioridad:</label>
                            <select class="form-select" name="prioridad" id="prioridad">
                                <option value="baja">🟢 Baja</option>
                                <option value="media" selected>🟡 Media</option>
                                <option value="alta">🔴 Alta</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción:</label>
                            <div class="input-group">
                                <textarea class="form-control" name="descripcion" id="descripcion" rows="4"></textarea>
                                <button class="btn btn-outline-info" type="button" id="ask-gemini-btn" title="Obtener sugerencias de la IA"><i class="bi bi-stars"></i> Sugerir</button>
                                <button class="btn btn-outline-warning" type="button" id="estimate-time-btn" title="Estimar tiempo"><i class="bi bi-hourglass-split"></i> Estimar</button>
                            </div>
                            <div id="gemini-suggestions-container" class="mt-2" style="display:none;">
                                <div class="spinner-border spinner-border-sm text-info" role="status"><span class="visually-hidden">Generando...</span></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Etiquetas:</label>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="tagsDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                    Seleccionar etiquetas
                                </button>
                                <div class="dropdown-menu w-100">
                                    <?php foreach ($todas_las_etiquetas as $etiqueta): ?>
                                        <div class="dropdown-item">
                                            <div class="form-check">
                                                <input class="form-check-input tag-checkbox" type="checkbox" value="<?= $etiqueta['id'] ?>" id="tag-<?= $etiqueta['id'] ?>">
                                                <label class="form-check-label" for="tag-<?= $etiqueta['id'] ?>">
                                                    <span class="badge" style="background-color: <?= $etiqueta['color'] ?>; color: white;"><?= htmlspecialchars($etiqueta['nombre']) ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" name="tags_ids_string" id="tags_ids_string">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Adjuntar archivos:</label>
                            <input type="file" class="form-control" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                            <div id="lista-archivos-existentes" class="mt-2"></div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check_fecha_termino">
                                <label class="form-check-label" for="check_fecha_termino">Añadir fecha de término</label>
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
                                    <label class="form-check-label" for="visibility_public">Público</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="visibility" id="visibility_private" value="private">
                                    <label class="form-check-label" for="visibility_private"><i class="bi bi-lock-fill"></i> Personal</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Asignar a:</label>
                            <select class="form-select" name="usuarios_ids[]" id="usuarios_ids" multiple size="3">
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>">
                                        <?= htmlspecialchars($usuario['nombre']) ?> (<?= htmlspecialchars($usuario['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Sección de Comentarios en el Modal -->
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="bi bi-chat-left-text"></i> Comentarios</h6>
                            <span class="text-primary" style="cursor: pointer; font-size: 0.9rem;" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Escribe '@' para mencionar a un compañero (ej: @juan) y enviarle una notificación.">
                                <i class="bi bi-info-circle"></i> ¿Cómo mencionar?
                            </span>
                        </div>
                        <div id="modal-notas-list" class="mb-3" style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px;"></div>
                        <div class="input-group">
                            <input type="text" id="modal-nota-input" class="form-control" placeholder="Escribe un comentario...">
                            <button class="btn btn-outline-primary" type="button" id="btn-agregar-nota-modal"><i class="bi bi-send"></i></button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (Necesario para el Modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- Funciones Helper Globales ---
        function escapeHtml(text) { return text ? String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;") : ''; }
        
        function formatDate(dateString) {
            const date = new Date(dateString + 'Z'); // Asumir UTC
            return `${date.getDate().toString().padStart(2,'0')}/${(date.getMonth()+1).toString().padStart(2,'0')} ${date.getHours().toString().padStart(2,'0')}:${date.getMinutes().toString().padStart(2,'0')}`;
        }

        function renderNotaModal(nota) {
            const list = document.getElementById('modal-notas-list');
            if (!list) return;
            
            // Resaltar menciones
            let noteContent = escapeHtml(nota.nota).replace(/\n/g, '<br>');
            noteContent = noteContent.replace(/@(\w+)/g, '<span class="text-primary fw-bold">@$1</span>');

            const nombreMostrar = nota.usuario_nombre || nota.nombre_invitado || 'Invitado';

            const div = document.createElement('div');
            div.className = 'nota-item mb-2';
            div.innerHTML = `
                <div class="d-flex justify-content-between">
                    <strong>${escapeHtml(nombreMostrar)}</strong>
                    <small class="text-muted">${formatDate(nota.fecha_creacion)}</small>
                </div>
                <p class="mb-0">${noteContent}</p>
            `;
            list.appendChild(div);
            list.scrollTop = list.scrollHeight;
        };

        // Función para abrir el modal de creación
        function abrirModalTarea(estado) {
            document.getElementById('modalTareaTitle').textContent = 'Nueva Idea';
            document.getElementById('formTarea').reset();
            document.getElementById('tarea_id').value = '';
            
            // Asegurar que los campos estén desbloqueados al crear nueva tarea
            document.getElementById('titulo').readOnly = false;
            document.getElementById('descripcion').readOnly = false;
            document.getElementById('titulo').style.backgroundColor = '';
            document.getElementById('descripcion').style.backgroundColor = '';
            if(document.getElementById('edit-lock-info')) document.getElementById('edit-lock-info').style.display = 'none';

            document.getElementById('input_estado').value = estado;
            document.getElementById('lista-archivos-existentes').innerHTML = '';
            document.getElementById('modal-notas-list').innerHTML = '<small class="text-muted">Guarda la tarea para agregar comentarios.</small>';
            
            // Resetear campos específicos
            document.getElementById('tags_ids_string').value = '';
            updateTagsButtonText(0);
            document.querySelectorAll('.tag-checkbox').forEach(cb => cb.checked = false);
            
            document.getElementById('check_fecha_termino').checked = false;
            document.getElementById('fecha_termino_container').style.display = 'none';
            document.getElementById('fecha_termino').value = '';
            if(document.getElementById('prioridad')) document.getElementById('prioridad').value = 'media';
            
            // Preseleccionar proyecto si hay filtro activo
            const urlParams = new URLSearchParams(window.location.search);
            const currentView = urlParams.get('vista');
            if (currentView === 'personal') {
                document.getElementById('visibility_private').checked = true;
                document.getElementById('visibility_public').checked = false;
            } else {
                document.getElementById('visibility_public').checked = true;
                document.getElementById('visibility_private').checked = false;
            }

            const currentProject = urlParams.get('proyecto_id');
            if(currentProject) {
                document.getElementById('proyecto_id').value = currentProject;
            }

            if(window.setCurrentOpenTaskId) window.setCurrentOpenTaskId(null); // Nueva tarea, sin ID aún

            new bootstrap.Modal(document.getElementById('modalTarea')).show();
        }

        // Función para abrir modal de proyectos (Unificado con Dashboard)
        function abrirModalProyecto() {
            document.getElementById('modalProyectoTitle').textContent = 'Nuevo Proyecto';
            document.getElementById('formProyecto').reset();
            document.getElementById('proyecto_id_edit').value = '';
            document.getElementById('color_proyecto').value = '#0d6efd';
            const modal = new bootstrap.Modal(document.getElementById('modalProyecto'));
            modal.show();
        }

        // Función para abrir el modal de edición
        function editarTarea(tareaId) {
            fetch('obtener_tarea.php?id=' + tareaId)
                .then(response => {
                    if (!response.ok) {
                        // Si la respuesta del servidor no fue OK (ej. 404, 500), lanzar un error
                        return response.json().then(errorData => {
                            throw new Error(errorData.error || 'Error desconocido al obtener la tarea.');
                        }).catch(() => {
                            throw new Error('Error de red o respuesta no JSON al obtener la tarea.');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    document.getElementById('tarea_id').value = data.id;
                    document.getElementById('titulo').value = data.titulo;
                    document.getElementById('descripcion').value = data.descripcion;
                    document.getElementById('proyecto_id').value = data.proyecto_id;

                    // --- LÓGICA DE PERMISOS DE EDICIÓN ---
                    const tituloInput = document.getElementById('titulo');
                    const descTextarea = document.getElementById('descripcion');
                    const editLockInfo = document.getElementById('edit-lock-info');

                    const currentUserIsOwner = data.usuario_id == <?= $usuario_actual_id ?>;
                    const currentUserIsAdmin = ['admin', 'super_admin'].includes('<?= $usuario_actual_rol ?>');

                    if (currentUserIsOwner || currentUserIsAdmin) {
                        tituloInput.readOnly = false;
                        descTextarea.readOnly = false;
                        tituloInput.style.backgroundColor = '';
                        descTextarea.style.backgroundColor = '';
                        if(editLockInfo) editLockInfo.style.display = 'none';
                    } else {
                        tituloInput.readOnly = true;
                        descTextarea.readOnly = true;
                        tituloInput.style.backgroundColor = 'var(--column-background)';
                        descTextarea.style.backgroundColor = 'var(--column-background)';
                        if(editLockInfo) {
                            editLockInfo.innerHTML = '<i class="bi bi-lock-fill"></i> Solo el creador o un admin pueden editar el título y descripción.';
                            editLockInfo.style.display = 'block';
                        }
                    }

                    if(window.setCurrentOpenTaskId) window.setCurrentOpenTaskId(data.id); // Establecer ID para polling

                    // Preseleccionar etiquetas
                    const tagCheckboxes = document.querySelectorAll('.tag-checkbox');
                    const selectedTagIds = [];
                    tagCheckboxes.forEach(cb => {
                        cb.checked = data.tags_ids && data.tags_ids.includes(parseInt(cb.value));
                        if (cb.checked) selectedTagIds.push(cb.value);
                    });
                    document.getElementById('tags_ids_string').value = selectedTagIds.join(',');
                    updateTagsButtonText(selectedTagIds.length);

                    // Mostrar archivos existentes
                    const listaArchivos = document.getElementById('lista-archivos-existentes');
                    listaArchivos.innerHTML = '';
                    if (data.archivos && data.archivos.length > 0) {
                        let html = '<label class="form-label mt-2">Archivos adjuntos:</label>';
                        html += '<small class="text-muted d-block mb-2"><i class="bi bi-info-circle"></i> Haz clic en el archivo para verlo o descargarlo.</small><ul class="list-group mb-3">';
                        data.archivos.forEach(file => {
                            html += `
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="uploads/${file.nombre_archivo}" target="_blank" class="text-decoration-none text-truncate" style="max-width: 80%;">
                                        <i class="bi bi-paperclip"></i> ${file.nombre_original}
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="eliminarArchivo(${file.id}, this.closest('li'))" title="Eliminar archivo"><i class="bi bi-x-lg"></i></button>
                                </li>
                            `;
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

                    // Fecha de término
                    if (data.fecha_termino) {
                        document.getElementById('check_fecha_termino').checked = true;
                        document.getElementById('fecha_termino_container').style.display = 'block';
                        document.getElementById('fecha_termino').value = data.fecha_termino;
                    } else {
                        document.getElementById('check_fecha_termino').checked = false;
                        document.getElementById('fecha_termino_container').style.display = 'none';
                        document.getElementById('fecha_termino').value = '';
                    }
                    
                    // Prioridad
                    if(document.getElementById('prioridad')) {
                        document.getElementById('prioridad').value = data.prioridad || 'media';
                    }

                    // Visibilidad
                    const visibility = data.visibility || 'public';
                    const radio = document.querySelector(`input[name="visibility"][value="${visibility}"]`);
                    if (radio) radio.checked = true;

                    // Preseleccionar usuarios
                    const usuariosSelect = document.getElementById('usuarios_ids');
                    if (usuariosSelect) {
                        const asignadosIds = Array.isArray(data.asignados) ? data.asignados : []; // Asegurarse de que sea un array
                        for (let i = 0; i < usuariosSelect.options.length; i++) {
                            usuariosSelect.options[i].selected = asignadosIds.includes(parseInt(usuariosSelect.options[i].value));
                        }
                    }

                    new bootstrap.Modal(document.getElementById('modalTarea')).show();
                })
                .catch(error => {
                    console.error('Error al cargar la tarea:', error);
                    alert('No se pudo cargar la tarea: ' + error.message);
                });
        }

        // Limpiar ID de tarea al cerrar modal para detener polling específico
        document.getElementById('modalTarea').addEventListener('hidden.bs.modal', function () {
            if(window.setCurrentOpenTaskId) window.setCurrentOpenTaskId(null);
        });

        function updateTagsButtonText(count) {
            const btn = document.getElementById('tagsDropdownBtn');
            btn.textContent = count === 0 ? 'Seleccionar etiquetas' : `${count} etiquetas seleccionadas`;
        }

        // Manejar checkboxes de etiquetas
        document.querySelectorAll('.tag-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                const selected = Array.from(document.querySelectorAll('.tag-checkbox:checked')).map(c => c.value);
                document.getElementById('tags_ids_string').value = selected.join(',');
                updateTagsButtonText(selected.length);
            });
        });

        // Toggle fecha término
        document.getElementById('check_fecha_termino').addEventListener('change', function() {
            document.getElementById('fecha_termino_container').style.display = this.checked ? 'block' : 'none';
            if (!this.checked) document.getElementById('fecha_termino').value = '';
        });

        function eliminarArchivo(id, element) {
            if(!confirm('¿Eliminar este archivo adjunto?')) return;
            const formData = new FormData();
            formData.append('id', id);
            fetch('eliminar_archivo.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => { if(data.success) element.remove(); else alert('Error al eliminar'); });
        }

        // Guardar tarea vía AJAX para no salir del Kanban via Delegación
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.id === 'formTarea') {
                e.preventDefault();
                
                // Feedback visual: Deshabilitar botón y mostrar spinner
                const btnGuardar = e.target.querySelector('button[type="submit"]');
                const textoOriginal = btnGuardar.innerHTML;
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

                const formData = new FormData(e.target);
                formData.append('ajax', '1'); // Indicamos que es una petición AJAX

                fetch(e.target.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Cerrar modal visualmente antes de recargar
                        const modalEl = document.getElementById('modalTarea');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                        
                        location.reload(); // Recargar para ver cambios
                    } else {
                        alert('Error al guardar: ' + (data.error || 'Error desconocido'));
                        // Restaurar botón si hubo error
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = textoOriginal;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión o respuesta inválida del servidor.');
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoOriginal;
                });
            }
        });

        // Lógica de IA (Copiada de Dashboard)
        const askGeminiBtn = document.getElementById('ask-gemini-btn');
        const estimateTimeBtn = document.getElementById('estimate-time-btn');
        const geminiSuggestionsContainer = document.getElementById('gemini-suggestions-container');
        const descripcionTextarea = document.getElementById('descripcion');
        const tituloInput = document.getElementById('titulo');

        const callAi = (action) => {
            const titulo = tituloInput.value;
            const descripcion = descripcionTextarea.value;
            if (!titulo.trim()) { alert('Escribe un título primero.'); return; }

            geminiSuggestionsContainer.style.display = 'block';
            askGeminiBtn.disabled = true;
            estimateTimeBtn.disabled = true;

            const formData = new FormData();
            formData.append('titulo', titulo);
            formData.append('descripcion', descripcion);
            formData.append('action', action);

            fetch('gemini_suggestions.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (action === 'estimate') descripcionTextarea.value += '\n\n[Estimación IA]: ' + data.suggestion;
                        else descripcionTextarea.value = data.suggestion;
                    } else { alert('Error IA: ' + data.error); }
                })
                .catch(e => console.error(e))
                .finally(() => {
                    geminiSuggestionsContainer.style.display = 'none';
                    askGeminiBtn.disabled = false;
                    estimateTimeBtn.disabled = false;
                });
        };
        askGeminiBtn.addEventListener('click', () => callAi('suggestion'));
        estimateTimeBtn.addEventListener('click', () => callAi('estimate'));

        // --- Funciones de Licencia (Unificado) ---
        function abrirModalLicencia() {
            fetch('admin_config.php').then(r => r.json()).then(data => {
                if(data.max_users) document.getElementById('inputMaxUsuariosLicencia').value = data.max_users;
                if(data.current_users !== undefined) document.getElementById('usersCountDisplayLicencia').textContent = `${data.current_users} / ${data.max_users}`;
                if(document.getElementById('switchAppStatusLicencia')) document.getElementById('switchAppStatusLicencia').checked = (data.app_status === 'active');
                new bootstrap.Modal(document.getElementById('modalLicencia')).show();
            });
        }

        function guardarConfiguracionLicencia() {
            const formData = new FormData(document.getElementById('formConfigLicencia'));
            const fileInput = document.getElementById('inputLogoApp');
            if (fileInput && fileInput.files.length > 0) formData.append('logo', fileInput.files[0]);
            
            formData.append('app_status', document.getElementById('switchAppStatusLicencia').checked ? 'active' : 'inactive');
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            
            fetch('admin_config.php', { method: 'POST', body: formData }).then(r => r.json()).then(data => {
                if(data.success) { alert('Guardado'); location.reload(); } else { alert('Error: ' + (data.error || 'Desconocido')); }
            });
        }
    </script>

    <!-- Modal Lightbox para Previsualización -->
    <div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-contentx justify-content-between w-100 align-items-center">
                        <h5 class="modal-title text-truncate" id="lightboxTitle"></h5>
                        <div>="D
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                <div class="modal-body text-center" id="lightboxBody">
                    <!-- Contenido dinámico -->
                </div>
                <div "></i> Previsualización de archivo. Usa el botón de descarga para guardarlo.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Panel de Licencia (Super Admin) -->
    <div class="modal fade" id="modalLicencia" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-shield-lock"></i> Panel de Licencia</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
                    <div class="card border-info mb-3"><div class="card-header bg-info-subtle py-2"><h6 class="mb-0 text-dark"><i class="bi bi-palette"></i> Personalización</h6></div><div class="card-body py-3"><label class="form-label small fw-bold">Logo de la Aplicación</label><div class="input-group input-group-sm"><input type="file" class="form-control" id="inputLogoApp" accept="image/*"><button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('inputLogoApp').value = '';">Limpiar</button></div><div class="form-text small mt-1">Sube el logo de tu organización. Aparecerá en la parte izquierda del encabezado, junto al logo de TuDu.</div></div></div>
                    <div class="alert alert-info small"><i class="bi bi-info-circle-fill"></i> Estos controles afectan a toda la aplicación. El <strong>límite de usuarios</strong> previene nuevos registros si se alcanza. El <strong>interruptor</strong> activa o desactiva el acceso para todos los usuarios (excepto Super Admins).</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
