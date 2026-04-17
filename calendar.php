<?php
require_once 'config.php';
// session_start(); // Ya en config.php

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$usuario_actual_id = $_SESSION['usuario_id'];
$usuario_actual_nombre = $_SESSION['usuario_nombre'];
$usuario_actual_username = $_SESSION['usuario_username'];
$usuario_actual_rol = trim($_SESSION['usuario_rol']);
$usuario_actual_foto = $_SESSION['usuario_foto'] ?? null;

// Configuración (Logo, etc) - Unificado con Dashboard
$stmt_config = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('TUDU_LICENSE_KEY', 'APP_STATUS', 'APP_LOGO')");
$config = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);

// Obtener usuarios para el selector de citas (Prepared Statement por seguridad)
$stmt_users = $pdo->prepare("SELECT id, nombre, username, telefono FROM usuarios WHERE id != ? AND activo = 1");
$stmt_users->execute([$usuario_actual_id]);
$usuarios_disponibles = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// Contador de archivados (para el menú)
$stmt_archivados = $pdo->query("SELECT COUNT(*) FROM tareas WHERE estado = 'archivado'");
$count_archivados = $stmt_archivados->fetchColumn();

// Obtener todas las etiquetas existentes (para el modal de gestión)
$stmt_all_tags = $pdo->query("SELECT id, nombre, color FROM etiquetas ORDER BY nombre");
$todas_las_etiquetas = $stmt_all_tags->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario - TuDu</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    
    <!-- Estilos TuDu -->
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* --- PALETA DE COLORES (Igual que Dashboard) --- */
        :root, [data-bs-theme="light"] {
            --background-color: #f8f9fa; /* Lighter cleaner background */
            --content-background: #ffffff;
            --column-background: #f8f9fa;
            --accent-color: #D97941;
            --accent-color-hover: #C86A35;
            --text-color: #2c3e50;
            --secondary-text-color: #6c757d;
            --border-color: #e9ecef;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); /* Soft shadow */
        }

        [data-bs-theme="dark"] {
            --background-color: #0F1C2E;
            --content-background: #152B47;
            --column-background: #1E3A5F;
            --text-color: #E8ECF0;
            --secondary-text-color: #C0C8D0;
            --border-color: #2A4A6F;
            --accent-color: #D97941;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }

        body {
            font-family: 'Inter', 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            padding-top: 70px;
        }

        /* Header y Context Bar */
        .main-header {
            background-color: var(--content-background);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }
        
        .main-header .dropdown-toggle::after {
            display: none; /* Cleaner look */
        }

        /* Context bar fija */
        .context-bar {
            position: sticky !important;
            top: 70px;
            z-index: 1010;
            background-color: var(--content-background);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card {
            background-color: var(--content-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: none;
        }

        /* --- FullCalendar Clean Styling --- */
        #calendar {
            font-family: 'Inter', sans-serif;
        }
        
        .fc-theme-standard td, .fc-theme-standard th {
            border-color: var(--border-color);
        }
        
        .fc-header-toolbar {
            margin-bottom: 1.5rem !important;
        }

        .fc-toolbar-title {
            font-size: 1.5rem !important;
            font-weight: 600;
            color: var(--text-color);
        }

        .fc-button-primary {
            background-color: var(--content-background) !important;
            border-color: var(--border-color) !important;
            color: var(--text-color) !important;
            font-weight: 500;
            text-transform: capitalize;
            box-shadow: none !important;
        }
        
        .fc-button-primary:hover {
            background-color: var(--column-background) !important;
            border-color: var(--border-color) !important;
        }

        .fc-button-primary:focus {
            box-shadow: 0 0 0 0.2rem rgba(217, 121, 65, 0.25) !important;
        }

        .fc-button-active {
            background-color: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
            color: white !important;
        }

        .fc-day-today {
            background-color: rgba(217, 121, 65, 0.05) !important;
        }

        /* --- Eventos Premium --- */
        .fc-event {
            border: none;
            border-radius: 4px;
            padding: 2px 6px;
            margin-bottom: 2px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.1s ease;
        }
        
        .fc-event:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
            z-index: 5;
        }

        .fc-daygrid-event-dot {
            border-color: currentColor; 
        }

        /* --- Tipos de eventos (bordes laterales) --- */
        /* Haremos que el color de fondo sea suave y tenga un borde izquierdo fuerte */
        .fc-daygrid-event {
            background-color: var(--content-background);
            border-left: 4px solid;
        }

        /* --- MODALES --- */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            background-color: var(--accent-color);
            color: white;
            border-bottom: none;
            border-radius: 16px 16px 0 0;
            padding: 1.25rem 1.5rem;
        }
        
        .modal-title {
            font-weight: 600;
        }

        /* Hacer que el botón de cerrar sea blanco en el header naranja */
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .modal-body {
            padding: 1.5rem;
            background-color: var(--content-background);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            background-color: var(--content-background);
            border-radius: 0 0 16px 16px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 12px;
            background-color: var(--column-background); /* Subtle contrast */
            border: 1px solid transparent;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--content-background);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(217, 121, 65, 0.15);
        }

        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        
        /* Mobile - Horizontal Scroll for Context Bar - SYNCHRONIZED WITH DASHBOARD */
        @media (max-width: 991px) {
            body { padding-top: 70px !important; }
            .main-header { padding: 8px 0; }
            .header-logo img { height: 32px !important; }
            
            .user-menu .dropdown-toggle span { display: none; }
            .header-right .btn { padding: 4px 8px; } 
            .header-right .btn i { font-size: 1.1rem !important; }
            .user-menu .user-avatar { width: 30px; height: 30px; }

            .fc-toolbar-title { font-size: 1.2rem !important; }
            
            /* Helper for explicit rows on mobile */
            .mobile-row {
                width: 100% !important;
                display: flex !important;
                justify-content: center !important;
                gap: 8px !important;
            }

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
            
            /* --- FULLCALENDAR MOBILE TOOLBAR (2 ROWS) --- */
            /* We use headerToolbar for Row 1 and footerToolbar for Row 2 */
            /* But we want both at the top */
            
            #calendar {
                display: flex;
                flex-direction: column;
            }
            
            .fc-header-toolbar {
                order: 1;
                margin-bottom: 5px !important;
            }
            
            .fc-footer-toolbar {
                order: 2;
                margin-bottom: 15px !important;
                margin-top: 0 !important;
            }
            
            .fc-view-harness {
                order: 3;
            }

            /* Adjust Row 1 (Header) Layout */
            .fc-header-toolbar .fc-toolbar-chunk:first-child { /* Left: Prev/Next */
                display: flex; gap: 5px;
            }
            
            .fc-header-toolbar .fc-toolbar-chunk:nth-child(2) { /* Center: Title */
                font-size: 0.9rem;
                display: flex; align-items: center; justify-content: center;
                white-space: nowrap;
            }
            
            /* Make buttons smaller on mobile */
            .fc-button {
                padding: 0.2rem 0.5rem !important;
                font-size: 0.75rem !important;
            }
            
            .fc-toolbar-title {
                font-size: 1rem !important;
            }
        }
    </style>
    <script>
        // Aplicar tema inmediatamente
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body>
    <!-- Header Fijo (Igual que Dashboard) -->
    <header class="main-header fixed-top">
        <div class="header-content container position-relative d-flex justify-content-between align-items-center py-2">
            
            <!-- Izquierda: Logo Custom -->
            <div class="header-left d-flex align-items-center">
                <?php if (!empty($config['APP_LOGO'])): ?>
                    <img src="<?= $config['APP_LOGO'] ?>" alt="Logo Empresa" style="height: 40px; max-width: 150px; object-fit: contain;">
                <?php endif; ?>
            </div>

            <!-- Centro: Logo TuDu -->
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
                            <span class="badge rounded-pill bg-secondary ms-2"><?= $count_archivados ?></span>
                        </a></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-power"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <!-- Barra de Contexto (Controles del Calendario) -->
    <div class="context-bar">
        <div class="container d-flex justify-content-center align-items-center h-100">
            <div class="header-controls d-flex gap-2 align-items-center flex-wrap justify-content-center">
                
                <!-- Row 1: Navigation -->
                <div class="d-flex gap-2 align-items-center mobile-row" style="z-index: 1050; position: relative;">
                    <div class="dropdown" style="z-index: 1060;">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-grid-1x2"></i> <span class="d-none d-md-inline">Vistas</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-list-ul me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="kanban.php"><i class="bi bi-kanban me-2"></i>Tablero Kanban</a></li>
                            <li><a class="dropdown-item active" href="#"><i class="bi bi-calendar-week me-2"></i>Calendario</a></li>
                        </ul>
                    </div>
                    
                    <select class="form-select form-select-sm w-auto border-secondary" id="calendarFilter">
                        <option value="confirmed" selected>📅 <span class="d-none d-md-inline">Eventos Confirmados</span><span class="d-md-none">Confirmados</span></option>
                        <option value="pending">⏳ <span class="d-none d-md-inline">Solicitudes Pendientes</span><span class="d-md-none">Pendientes</span></option>
                    </select>
                </div>

                <div class="vr mx-2 d-none d-md-block"></div>
                
                <!-- Row 2: Actions -->
                <div class="d-flex gap-2 align-items-center mobile-row justify-content-end">
                    <button class="btn btn-primary btn-sm flex-fill" onclick="abrirModalEvento()">
                        <i class="bi bi-plus-circle"></i> <span class="d-none d-md-inline">Nuevo Evento</span>
                    </button>
                    <button class="btn btn-outline-warning btn-sm position-relative flex-fill" onclick="abrirBandejaCitas()">
                        <i class="bi bi-inbox"></i> Solicitudes
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="badgeSolicitudes" style="display: none;">
                            0
                            <span class="visually-hidden">solicitudes pendientes</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-3">
                        <!-- Leyenda Horizontal Compacta -->
                        <div class="d-flex gap-3 mb-3 justify-content-center flex-wrap small text-muted">
                            <span><i class="bi bi-circle-fill text-primary"></i> General</span>
                            <span><i class="bi bi-circle-fill text-success"></i> Reunión</span>
                            <span><i class="bi bi-circle-fill text-danger"></i> Entrega</span>
                            <span><i class="bi bi-circle-fill text-warning"></i> Vence Hoy</span>
                            <span><i class="bi bi-circle-fill" style="color: #6f42c1;"></i> Tarea Pendiente</span>
                            <span><i class="bi bi-circle-fill text-secondary"></i> Ocupado (Privado)</span>
                        </div>
                        
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Evento -->
    <div class="modal fade" id="modalEvento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEvento">
                    <input type="hidden" name="event_id" id="event_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Título</label>
                            <input type="text" class="form-control" name="titulo" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Fecha y Hora</label>
                                <input type="datetime-local" class="form-control" name="start" id="evtStart" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Duración</label>
                                <select class="form-select" id="evtDuration">
                                    <option value="15">15 minutos</option>
                                    <option value="30" selected>30 minutos</option>
                                    <option value="45">45 minutos</option>
                                    <option value="60">1 hora</option>
                                    <option value="90">1 hora 30 min</option>
                                    <option value="120">2 horas</option>
                                    <option value="180">3 horas</option>
                                    <option value="240">4 horas</option>
                                    <option value="allDay">Todo el día</option>
                                </select>
                                <!-- Campo oculto para enviar 'end' calculado -->
                                <input type="hidden" name="end" id="evtEnd">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo_evento">
                                <option value="personal">Personal</option>
                                <option value="reunion">Reunión</option>
                                <option value="entrega">Entrega</option>
                                <option value="revision">Revisión</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Privacidad</label>
                            <select class="form-select" name="privacidad">
                                <option value="privado">Privado (Solo yo)</option>
                                <option value="publico">Público (Visible equipo)</option>
                                <option value="confidencial">Confidencial</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Participante / Destinatario <small class="text-muted">(Opcional)</small></label>
                            <select class="form-select" name="receptor_id" id="evtReceptor">
                                <option value="">Evento Personal (Solo yo)</option>
                                <?php foreach ($usuarios_disponibles as $u): ?>
                                    <option value="<?= $u['id'] ?>">
                                        <?= htmlspecialchars($u['nombre']) ?> (<?= htmlspecialchars($u['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Ubicación</label>
                                <select class="form-select" name="ubicacion_tipo" id="evtUbicacionTipo" onchange="toggleUbicacionDetalle()">
                                    <option value="oficina">En Oficina</option>
                                    <option value="externa">Ubicación Externa (Maps)</option>
                                    <option value="llamada">Llamada Telefónica</option>
                                    <option value="virtual">Videollamada</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label" id="lblUbicacionDetalle">Detalles / Sala</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="ubicacion_detalle" id="evtUbicacionDetalle" placeholder="Ej: Sala de Juntas 1 / Dirección física">
                                    <button class="btn btn-outline-primary" type="button" id="btnPreviewMap" style="display:none;" onclick="previewLocationMap()" title="Verificar en Google Maps">
                                        <i class="bi bi-map"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Enlace de Google Maps <small class="text-muted">(Compartible)</small></label>
                            <input type="url" class="form-control" name="link_maps" id="evtLinkMaps" placeholder="https://maps.app.goo.gl/...">
                            <div class="form-text mt-1 text-muted">
                                <i class="bi bi-info-circle"></i> Pega aquí el enlace que obtienes al "Compartir" la ubicación en Google Maps. Este enlace se enviará por WhatsApp a los participantes.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- El modal de Solicitar Cita ha sido eliminado y unificado en modalEvento -->

    <!-- Modal Bandeja de Citas (Solicitudes Pendientes) -->
    <div class="modal fade" id="modalBandejaCitas" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-inbox"></i> Solicitudes de Citas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="listaSolicitudes" class="list-group list-group-flush">
                        <!-- Cargado dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalle Evento (Con WhatsApp) -->
    <div class="modal fade" id="modalDetalleEvento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalleEventoTitulo"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3"><i class="bi bi-clock"></i> <span id="detalleEventoFecha"></span></p>
                    <div class="p-3 rounded border mb-3" id="detalleEventoDescripcion" style="background-color: var(--content-background);"></div>
                    
                    <div class="d-flex justify-content-end align-items-center mt-4 gap-2">
                        <!-- Quick WhatsApp Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Enviar WhatsApp rápido">
                                <i class="bi bi-whatsapp"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Seleccionar destinatario</h6></li>
                                <?php foreach ($usuarios_disponibles as $u): ?>
                                    <?php if (!empty($u['telefono'])): ?>
                                        <li><a class="dropdown-item whatsapp-link" href="#" target="_blank" data-phone="<?= preg_replace('/[^0-9]/', '', $u['telefono']) ?>" data-name="<?= htmlspecialchars($u['nombre']) ?>"><?= htmlspecialchars($u['nombre']) ?></a></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (empty($usuarios_disponibles)): ?><li><span class="dropdown-item text-muted">No hay usuarios disponibles</span></li><?php endif; ?>
                            </ul>
                        </div>
                        
                        <!-- Share Button -->
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="shareEvent()">
                            <i class="bi bi-share-fill"></i> Compartir
                        </button>
                    </div>
                </div>
                <div class="modal-footer" id="footerAccionesEvento" style="display: none;">
                    <button type="button" class="btn btn-outline-danger me-auto" id="btnEliminarEvento"><i class="bi bi-trash"></i> Eliminar</button>
                    <button type="button" class="btn btn-primary" id="btnEditarEvento"><i class="bi bi-pencil"></i> Editar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Compartir Evento (Nuevo) -->
    <div class="modal fade" id="modalShareEvento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-share"></i> Compartir Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <h6 class="mb-3 text-start"><i class="bi bi-whatsapp text-success"></i> Enviar por WhatsApp</h6>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control" id="shareWhatsappPhone" placeholder="Ej: 5215512345678">
                        <button class="btn btn-success" type="button" onclick="enviarWhatsAppShare()">
                            <i class="bi bi-send"></i> Enviar
                        </button>
                    </div>
                    <div class="form-text text-muted small mt-2 text-start">Ingresa el número con código de país (sin +).</div>
                    
                    <hr class="my-3">
                    
                    <p class="mb-3">O copia el enlace manualmente:</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="shareLinkInput" readonly>
                        <button class="btn btn-outline-primary" type="button" onclick="copiarEnlaceShare()">
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

    <!-- Modal Detalle Notificación (Citas Rechazadas/Aceptadas) -->
    <div class="modal fade" id="modalDetalleNotificacion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-info-circle"></i> Información de Cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="detalleNotificacionTexto" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cargar jQuery primero (necesario para algunas funciones de FullCalendar) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Luego Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Leaflet.js para mapas robustos (sustituto de Google Maps que no se bloquea) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
    <!-- FullCalendar Bootstrap 5 Plugin -->
    <link href='https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@5.11.3/main.min.js'></script>
    
    <script>
        // Lógica de Tema Oscuro/Claro
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const iconTheme = document.getElementById('iconTheme');
        const htmlElement = document.documentElement;

        function updateThemeIcon(theme) {
            if(iconTheme) iconTheme.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
        updateThemeIcon(htmlElement.getAttribute('data-bs-theme'));

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                const newTheme = htmlElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
                // Recargar calendario para aplicar nuevo tema
                if (window.calendarInstance) window.calendarInstance.render();
            });
        }

        // --- INICIALIZACIÓN DEL CALENDARIO ---
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var isMobile = window.innerWidth < 768;

            var calendarConfig = {
                themeSystem: 'bootstrap5',
                initialView: isMobile ? 'listMonth' : 'dayGridMonth',
                headerToolbar: isMobile ? {
                    // Mobile Header: Row 1
                    left: 'prev,next',
                    center: 'title',
                    right: 'today'
                } : {
                    // Desktop
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                },
                footerToolbar: isMobile ? {
                    // Mobile Footer: Row 2 (Views) - We will move this to top via CSS
                    center: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                } : false, // No footer on desktop
                locale: 'es',
                navLinks: true,
                editable: true,
                selectable: true,
                nowIndicator: true,
                dayMaxEvents: true,
                events: function(fetchInfo, successCallback, failureCallback) {
                    var filter = document.getElementById('calendarFilter').value;
                    actualizarResumen(fetchInfo.startStr, fetchInfo.endStr); // Actualizar contadores
                    fetch(`calendar_actions.php?action=get_events&filter=${filter}&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`)
                        .then(response => response.json())
                        .then(data => {
                            successCallback(data);
                        })  
                        .catch(error => {
                            console.error('Error cargando eventos:', error);
                            failureCallback(error);
                        });
                },
                
                // Al hacer clic en una fecha vacía
                select: function(info) {
                    abrirModalEvento(info.startStr, info.endStr);
                    calendar.unselect(); // Limpiar selección
                },
                
                // Al hacer clic en un día específico (o hora en vistas semanales)
                dateClick: function(info) {
                    abrirModalEvento(info.dateStr);
                },

                // Al hacer clic en un evento
                eventClick: function(info) {
                    mostrarDetalleEvento(info.event);
                },
                
                // Configurar colores de eventos
                eventDidMount: function(info) {
                    // Asignar colores según el tipo de evento
                    const tipo = info.event.extendedProps.tipo;
                    let color = '#3788d8'; // Default blue
                    
                    switch(tipo) {
                        case 'reunion': color = '#28a745'; break; // Green
                        case 'entrega': color = '#dc3545'; break; // Red
                        case 'tarea': color = '#8E44AD'; break; // Purple
                        case 'personal': color = '#6c757d'; break; // Gray
                    }
                    
                    info.el.style.backgroundColor = color;
                    info.el.style.borderColor = color;
                }
            };

            // Apply height auto ONLY for mobile to avoid whitespace
            if (isMobile) {
                calendarConfig.height = 'auto';
            }

            var calendar = new FullCalendar.Calendar(calendarEl, calendarConfig);

            // Habilitar Drag & Drop para actualizar fechas
            calendar.on('eventDrop', function(info) {
                if (info.event.id.startsWith('task-')) {
                    if (!confirm(`¿Estás seguro de cambiar la fecha de vencimiento de "${info.event.title}" a ${info.event.start.toLocaleDateString()}?`)) {
                        info.revert();
                        return;
                    }

                    const formData = new FormData();
                    formData.append('id', info.event.extendedProps.tarea_id);
                    formData.append('new_date', info.event.start.toISOString().split('T')[0]);

                    fetch('calendar_actions.php?action=update_task_due_date', { 
                        method: 'POST', 
                        body: formData 
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            alert('Error al actualizar la fecha.');
                            info.revert();
                        }
                    });
                } else {
                    // Actualizar evento normal (drag & drop)
                    const event = info.event;
                    const formData = new FormData();
                    formData.append('event_id', event.id);
                    formData.append('titulo', event.title);
                    formData.append('start', event.start.toISOString().slice(0, 19).replace('T', ' '));
                    formData.append('end', event.end ? event.end.toISOString().slice(0, 19).replace('T', ' ') : event.start.toISOString().slice(0, 19).replace('T', ' '));
                    formData.append('tipo_evento', event.extendedProps.tipo);
                    formData.append('privacidad', event.extendedProps.privacidad);
                    formData.append('ubicacion_tipo', event.extendedProps.ubicacion_tipo || 'oficina');
                    formData.append('ubicacion_detalle', event.extendedProps.ubicacion_detalle || '');
                    formData.append('link_maps', event.extendedProps.link_maps || '');
                    
                    fetch('calendar_actions.php?action=save_event', {
                        method: 'POST',
                        body: formData
                    }).then(r => r.json()).then(data => {
                       if(!data.success) {
                           alert('Error al mover evento: ' + data.error);
                           info.revert();
                       }
                    });
                }
            });

            window.calendarInstance = calendar;
            calendar.render();

            // Listener para el filtro
            document.getElementById('calendarFilter').addEventListener('change', function() {
                calendar.refetchEvents();
            });

            document.getElementById('formEvento').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Calcular fecha fin basada en inicio + duración
                const startInput = document.getElementById('evtStart').value;
                const duration = document.getElementById('evtDuration').value;
                const endInput = document.getElementById('evtEnd');
                
                if (startInput) {
                    const startDate = new Date(startInput);
                    let endDate = new Date(startDate);
                    
                    if (duration === 'allDay') {
                        // Para todo el día, lo ideal es que termine a final del día
                         endDate.setHours(23, 59, 59);
                    } else {
                        endDate.setMinutes(startDate.getMinutes() + parseInt(duration));
                    }
                    
                    // Ajustar a formato local ISO
                    const endLocal = new Date(endDate.getTime() - (endDate.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
                    endInput.value = endLocal;
                }

                const formData = new FormData(this);
                const isRequest = formData.get('receptor_id') !== "";
                const action = isRequest ? 'request_appointment' : 'save_event';
                
                fetch(`calendar_actions.php?action=${action}`, { 
                    method: 'POST', 
                    body: formData 
                })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        if (isRequest) {
                            alert('Solicitud de cita enviada correctamente.');
                            actualizarBadgeSolicitudes();
                        }
                        const modalEl = document.getElementById('modalEvento');
                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.hide();
                        
                        calendar.refetchEvents();
                        this.reset();
                    } else {
                        alert('Error: ' + (data.error || 'Error desconocido'));
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error.message);
                });
            });

            // El listener de formCita ha sido eliminado (unificado)

            // Iniciar notificaciones
            cargarNotificaciones();
            setInterval(cargarNotificaciones, 30000);

            // Iniciar badge de solicitudes
            actualizarBadgeSolicitudes();
            setInterval(actualizarBadgeSolicitudes, 30000);

            // --- Lógica para abrir modal de evento (unificado) desde URL ---
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'solicitarCita') {
                abrirModalEvento();
                const form = document.getElementById('formEvento');
                
                if (urlParams.has('titulo')) {
                    form.querySelector('[name="titulo"]').value = urlParams.get('titulo');
                }
            }

            // --- DEEP LINKING PARA EVENTOS ---
            const eventIdFromUrl = urlParams.get('event_id');
            if (eventIdFromUrl) {
                // Fetch event details and open modal
                fetch(`calendar_actions.php?action=get_event&id=${eventIdFromUrl}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.event) {
                             // Simular objeto 'event' para reusar la lógica de eventClick
                             const fakeEvent = {
                                 id: data.event.id,
                                 title: data.event.titulo,
                                 start: new Date(data.event.start.replace(' ', 'T')),
                                 end: new Date(data.event.end.replace(' ', 'T')),
                                 extendedProps: {
                                     tipo: data.event.tipo_evento,
                                     descripcion: data.event.descripcion,
                                     privacidad: data.event.privacidad,
                                     ubicacion_tipo: data.event.ubicacion_tipo,
                                     ubicacion_detalle: data.event.ubicacion_detalle,
                                     is_private: (data.event.privacidad === 'privado' && data.event.usuario_id != <?= $usuario_actual_id ?>),
                                     can_edit: (data.event.usuario_id == <?= $usuario_actual_id ?> || ['admin','super_admin'].includes('<?= $usuario_actual_rol ?>'))
                                 }
                             };
                             
                             setTimeout(() => {
                                 mostrarDetalleEvento(fakeEvent);
                             }, 500);
                        }
                    });
            }
        });

        // Función para actualizar el panel de resumen
        function actualizarResumen(start, end) {
            fetch(`calendar_actions.php?action=get_summary&start=${start}&end=${end}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('countVencidas').textContent = data.vencidas;
                    document.getElementById('countHoy').textContent = data.hoy;
                });
        }

        // --- FUNCIONES DEL CALENDARIO ---
        
        function toggleUbicacionDetalle() {
            const tipo = document.getElementById('evtUbicacionTipo').value;
            const input = document.getElementById('evtUbicacionDetalle');
            const label = document.getElementById('lblUbicacionDetalle');
            const btnPreview = document.getElementById('btnPreviewMap');
            
            btnPreview.style.display = (tipo === 'externa') ? 'block' : 'none';

            if (tipo === 'oficina') {
                label.textContent = 'Sala / Oficina';
                input.placeholder = 'Ej: Sala de Juntas 1';
            } else if (tipo === 'externa') {
                label.textContent = 'Dirección / Texto';
                input.placeholder = 'Ej: Av. Reforma 222, CDMX (No pongas links aquí)';
            } else if (tipo === 'llamada') {
                label.textContent = 'Número de Teléfono';
                input.placeholder = 'Ej: +52 55 1234 5678';
            } else if (tipo === 'virtual') {
                label.textContent = 'Enlace de Reunión';
                input.placeholder = 'Zoom / Meet / Teams';
            }
        }

        function previewLocationMap() {
            const address = document.getElementById('evtUbicacionDetalle').value;
            if (!address) {
                alert('Por favor escribe una dirección primero.');
                return;
            }
            const mapUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
            window.open(mapUrl, '_blank');
        }

        function copiarAlPortapapeles(texto) {
            navigator.clipboard.writeText(texto).then(() => {
                alert('Enlace copiado al portapapeles');
            }).catch(err => {
                const el = document.createElement('textarea');
                el.value = texto;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
                alert('Enlace copiado');
            });
        }

        function mostrarDetalleEvento(event) {
            const props = event.extendedProps || {};
            
            if (props.tipo === 'tarea') {
                const tareaId = props.tarea_id;
                const proyectoId = props.proyecto_id;
                window.location.href = `dashboard.php?proyecto_id=${proyectoId}&action=verTarea&id=${tareaId}`;
                return;
            }
            if (props.tipo === 'solicitud') {
                abrirBandejaCitas();
                return;
            }
            if (props.is_private) {
                alert('Este evento es privado.');
                return;
            }
            
            document.getElementById('detalleEventoTitulo').textContent = event.title;
            const start = event.start;
            const end = event.end;
            let timeStr = start.toLocaleString('es-ES', { dateStyle: 'full', timeStyle: 'short' });
            if (end) {
                if (end.toDateString() === start.toDateString()) {
                    timeStr += ' - ' + end.toLocaleTimeString('es-ES', { timeStyle: 'short' });
                } else {
                    timeStr += ' - ' + end.toLocaleString('es-ES', { dateStyle: 'full', timeStyle: 'short' });
                }
            }
            
            document.getElementById('detalleEventoFecha').textContent = timeStr;
            
            // Ubicación y Mapas (simplificado en output para no repetir todo el bloque de lógica visual que no cambia)
            // ... (Lógica de visualización de ubicación mantenida igual conceptualmente)
            let desc = props.descripcion || '';
            let mapSection = '';
            
            if (props.link_maps) {
                mapSection += `<div class="alert alert-info py-2 mb-3"><a href="${props.link_maps}" target="_blank" class="fw-bold text-decoration-none"><i class="bi bi-geo-alt"></i> Ver en Maps</a></div>`;
            }
            
            // Para simplificar el reemplazo, asumimos que no cambiamos la lógica interna de mostrarDetalleEvento demasiado, 
            // solo necesitamos asegurar que la llamada al modal sea correcta.
            // Pero como replace_file_content necesita exactitud, regeneraré la parte de arriba del modal
            // y me enfocaré en la apertura del modal al final de esta función.
            
            // ... REINSERTO LOGICA VISUAL COMPLETA PARA EVITAR ERRORES DE MATCH ...
             let htmlContent = '';
             if(props.link_maps) htmlContent += `<p class="mb-2"><a href="${props.link_maps}" target="_blank"><i class="bi bi-link-45deg"></i> Link Maps</a></p>`;
             if(props.ubicacion_tipo && props.ubicacion_tipo !== 'oficina') htmlContent += `<p class="mb-2"><strong>Ubicación:</strong> ${props.ubicacion_tipo} - ${props.ubicacion_detalle}</p>`;
             else if(props.ubicacion_detalle) htmlContent += `<p class="mb-2"><strong>Lugar:</strong> ${props.ubicacion_detalle}</p>`;
             htmlContent += `<div class="p-2 border rounded bg-light">${desc}</div>`;
             
             document.getElementById('detalleEventoDescripcion').innerHTML = htmlContent;

            // Configurar botones
            const footerAcciones = document.getElementById('footerAccionesEvento');
            if (props.can_edit) {
                footerAcciones.style.display = 'flex';
                document.getElementById('btnEditarEvento').onclick = function() {
                    const modalEl = document.getElementById('modalDetalleEvento');
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.hide();
                    abrirModalEditar(event);
                };
                document.getElementById('btnEliminarEvento').onclick = function() {
                    eliminarEvento(event.id);
                };
            } else {
                footerAcciones.style.display = 'none';
            }

            // Actualizar enlaces de WhatsApp
            // Construir URL del evento
            const eventUrl = `<?= APP_URL ?>calendar.php?event_id=${event.id}`;
            let whatsappDesc = event.extendedProps.descripcion || '';
            if (props.link_maps) whatsappDesc += `\n📍 Maps: ${props.link_maps}`;
            
            const msg = `Hola, te comparto los detalles de la cita:\n\n📅 *${event.title}*\n🕐 ${timeStr}\n\n📝 ${whatsappDesc}\n\n🔗 Ver evento: ${eventUrl}`;
            
            // Globales para compartir
            window.currentDetailEventId = event.id;
            window.currentShareMessage = msg;
            
            document.querySelectorAll('.whatsapp-link').forEach(link => {
                link.href = `https://wa.me/${link.dataset.phone}?text=${encodeURIComponent(msg)}`;
            });

            const modalEl = document.getElementById('modalDetalleEvento');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }

        function abrirModalEvento(startStr = null, endStr = null) {
            document.getElementById('formEvento').reset();
            document.getElementById('event_id').value = '';
            document.querySelector('#modalEvento .modal-title').textContent = 'Nuevo Evento';
            
            document.getElementById('evtUbicacionTipo').value = 'oficina';
            document.getElementById('evtReceptor').value = '';
            document.getElementById('evtLinkMaps').value = '';
            toggleUbicacionDetalle();

            // Default duration 30 min
            document.getElementById('evtDuration').value = '30';

            // Fechas
            const now = new Date();
            let startDate;

            if (startStr) {
                if (startStr.indexOf('T') > -1 || startStr.indexOf(' ') > -1) {
                    startDate = new Date(startStr);
                } else {
                    startDate = new Date(startStr + 'T' + now.toTimeString().slice(0,5));
                }
            } else {
                startDate = new Date();
                startDate.setMinutes(0, 0, 0);
            }
            
            // Si hay endStr, calcular duración
            if (startStr && endStr) {
                 const s = new Date(startStr);
                 const e = new Date(endStr);
                 const diff = Math.round((e - s) / 60000); // minutos
                 
                 // Seleccionar la opción más cercana o exacta
                 const durSelect = document.getElementById('evtDuration');
                 let found = false;
                 // Reset
                 durSelect.value = '30';
                 
                 for(let i=0; i<durSelect.options.length; i++) {
                    if(parseInt(durSelect.options[i].value) === diff) {
                        durSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                 }
                 // Si no match exacto y es grande, poner allDay o dejar 30
                 if(!found && diff >= 1440) durSelect.value = 'allDay';
                 else if (!found && diff > 0) {
                     // Podríamos añadir lógica para aproximar, pero dejemos 30 default por seguridad o el valor custom si existiera
                     // Check if logic allows 'custom' (not implemented yet), so default to 30 or similar.
                 }
            }

            const startLocal = new Date(startDate.getTime() - (startDate.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
            document.getElementById('evtStart').value = startLocal;
            
            const modalEl = document.getElementById('modalEvento');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
        
        function abrirModalEditar(event) {
            const props = event.extendedProps;
            
            document.getElementById('event_id').value = event.id;
            document.querySelector('#modalEvento .modal-title').textContent = 'Editar Evento';
            
            const form = document.getElementById('formEvento');
            form.querySelector('[name="titulo"]').value = event.title;
            form.querySelector('[name="descripcion"]').value = props.descripcion;
            form.querySelector('[name="tipo_evento"]').value = props.tipo;
            form.querySelector('[name="privacidad"]').value = props.privacidad;
            
            if (props.ubicacion_tipo) {
                form.querySelector('[name="ubicacion_tipo"]').value = props.ubicacion_tipo;
                toggleUbicacionDetalle();
                form.querySelector('[name="ubicacion_detalle"]').value = props.ubicacion_detalle || '';
            } else {
                 form.querySelector('[name="ubicacion_tipo"]').value = 'oficina';
                 toggleUbicacionDetalle();
            }

            form.querySelector('[name="link_maps"]').value = props.link_maps || '';
            form.querySelector('[name="receptor_id"]').value = props.receptor_id || '';

            const start = event.start;
            const end = event.end || new Date(start.getTime() + 1800000);
            
            const startLocal = new Date(start.getTime() - (start.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
            document.getElementById('evtStart').value = startLocal;
            
            const diffMin = Math.round((end - start) / 60000);
            const durSelect = document.getElementById('evtDuration');
            let found = false;
            for(let i=0; i<durSelect.options.length; i++) {
                if(parseInt(durSelect.options[i].value) === diffMin) {
                    durSelect.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if(!found) {
                if (diffMin > 240) {
                     durSelect.value = 'allDay';
                } else {
                     durSelect.value = '30';
                }
            }
            
            const modalEl = document.getElementById('modalEvento');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
        
        function abrirModalEditar(event) {
            const props = event.extendedProps;
            
            document.getElementById('event_id').value = event.id;
            document.querySelector('#modalEvento .modal-title').textContent = 'Editar Evento';
            
            const form = document.getElementById('formEvento');
            form.querySelector('[name="titulo"]').value = event.title;
            form.querySelector('[name="descripcion"]').value = props.descripcion;
            form.querySelector('[name="tipo_evento"]').value = props.tipo;
            form.querySelector('[name="privacidad"]').value = props.privacidad;
            
            if (props.ubicacion_tipo) {
                form.querySelector('[name="ubicacion_tipo"]').value = props.ubicacion_tipo;
                toggleUbicacionDetalle();
                form.querySelector('[name="ubicacion_detalle"]').value = props.ubicacion_detalle || '';
            } else {
                 form.querySelector('[name="ubicacion_tipo"]').value = 'oficina';
                 toggleUbicacionDetalle();
            }

            form.querySelector('[name="link_maps"]').value = props.link_maps || '';
            form.querySelector('[name="receptor_id"]').value = props.receptor_id || '';

            const start = event.start;
            const end = event.end || new Date(start.getTime() + 1800000);
            
            const startLocal = new Date(start.getTime() - (start.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
            document.getElementById('evtStart').value = startLocal;
            
            const diffMin = Math.round((end - start) / 60000);
            const durSelect = document.getElementById('evtDuration');
            let found = false;
            for(let i=0; i<durSelect.options.length; i++) {
                if(parseInt(durSelect.options[i].value) === diffMin) {
                    durSelect.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if(!found) {
                if (diffMin > 240) {
                     durSelect.value = 'allDay';
                } else {
                     durSelect.value = '30';
                }
            }
            
            const modalEl = document.getElementById('modalEvento');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }

        function eliminarEvento(id) {
            if (!confirm('¿Estás seguro de eliminar este evento?')) return;
            
            const formData = new FormData();
            formData.append('id', id);
            
            fetch('calendar_actions.php?action=delete_event', { 
                method: 'POST', 
                body: formData 
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const detalleModal = bootstrap.Modal.getInstance(document.getElementById('modalDetalleEvento'));
                    if (detalleModal) detalleModal.hide();
                    
                    if (window.calendarInstance) window.calendarInstance.refetchEvents();
                } else {
                    alert('Error: ' + (data.error || 'Error desconocido'));
                }
            });
        }

        // La función abrirModalCita ha sido eliminada por unificación

        // --- Validación Visual de Disponibilidad ---
        document.addEventListener('DOMContentLoaded', function() {
            const receptorSelect = document.getElementById('cita_receptor');
            const fechaInput = document.getElementById('cita_fecha');
            const duracionSelect = document.getElementById('cita_duracion');
            const feedback = document.getElementById('feedbackDisponibilidad');
            const btnSubmit = document.querySelector('#formCita button[type="submit"]');

            function verificarDisponibilidad() {
                const receptorId = receptorSelect.value;
                const fecha = fechaInput.value;
                const duracion = duracionSelect.value;

                if (receptorId && fecha) {
                    feedback.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm"></span> Verificando...</span>';
                    
                    fetch(`calendar_actions.php?action=check_availability&receptor_id=${receptorId}&fecha=${fecha}&duracion=${duracion}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.busy) {
                                feedback.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle"></i> Usuario ocupado en este horario.</span>';
                                if (btnSubmit) btnSubmit.disabled = true;
                            } else {
                                feedback.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle"></i> Disponible.</span>';
                                if (btnSubmit) btnSubmit.disabled = false;
                            }
                        })
                        .catch(() => {
                            feedback.innerHTML = '<span class="text-warning fw-bold"><i class="bi bi-exclamation-triangle"></i> No se pudo verificar</span>';
                            if (btnSubmit) btnSubmit.disabled = false;
                        });
                } else {
                    feedback.innerHTML = '';
                    if (btnSubmit) btnSubmit.disabled = false;
                }
            }

            if(receptorSelect && fechaInput && duracionSelect) {
                receptorSelect.addEventListener('change', verificarDisponibilidad);
                fechaInput.addEventListener('change', verificarDisponibilidad);
                duracionSelect.addEventListener('change', verificarDisponibilidad);
            }
        });

        // --- Lógica de Bandeja de Citas ---
        function abrirBandejaCitas() {
            const modal = new bootstrap.Modal(document.getElementById('modalBandejaCitas'));
            modal.show();
            cargarSolicitudesCitas();
        }

        function actualizarBadgeSolicitudes() {
            fetch('calendar_actions.php?action=get_pending_appointments')
                .then(r => r.json())
                .then(response => {
                    const badge = document.getElementById('badgeSolicitudes');
                    if (badge) {
                        if (response.success && response.data && response.data.length > 0) {
                            badge.textContent = response.data.length;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(e => console.error('Error cargando solicitudes:', e));
        }

        function cargarSolicitudesCitas() {
            const lista = document.getElementById('listaSolicitudes');
            if (!lista) return;
            
            lista.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

            fetch('calendar_actions.php?action=get_pending_appointments')
                .then(r => r.json())
                .then(response => {
                    if (response.success && response.data && response.data.length > 0) {
                        lista.innerHTML = '';
                        response.data.forEach(cita => {
                            const item = document.createElement('div');
                            item.className = 'list-group-item p-3 border-bottom';
                            const fecha = new Date(cita.fecha_propuesta).toLocaleString('es-ES', { dateStyle: 'full', timeStyle: 'short' });
                            
                            item.innerHTML = `
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <h6 class="mb-1 fw-bold text-primary">${escapeHtml(cita.titulo || 'Sin título')}</h6>
                                        <p class="mb-1 text-muted small"><i class="bi bi-person-circle"></i> Solicitado por: <strong>${escapeHtml(cita.solicitante_nombre || 'Usuario')}</strong></p>
                                        <p class="mb-2"><i class="bi bi-calendar-event"></i> ${fecha} <span class="badge bg-light text-dark border">${cita.duracion_minutos || 30} min</span></p>
                                        ${cita.mensaje ? `<div class="alert alert-light py-2 px-3 small mb-0 border">${escapeHtml(cita.mensaje)}</div>` : ''}
                                    </div>
                                    <div class="d-flex flex-column gap-2">
                                        <button class="btn btn-success btn-sm" onclick="responderCita(${cita.id}, 'aceptada')"><i class="bi bi-check-lg"></i> Aceptar</button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="responderCita(${cita.id}, 'rechazada')"><i class="bi bi-x-lg"></i> Rechazar</button>
                                    </div>
                                </div>
                            `;
                            lista.appendChild(item);
                        });
                    } else {
                        lista.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-calendar-check display-4"></i><p class="mt-3">No tienes solicitudes pendientes.</p></div>';
                    }
                })
                .catch(error => {
                    lista.innerHTML = '<div class="text-center py-5 text-danger"><i class="bi bi-exclamation-triangle display-4"></i><p class="mt-3">Error cargando solicitudes</p></div>';
                    console.error('Error:', error);
                });
        }

        function responderCita(id, estado) {
            let motivo = '';
            if (estado === 'rechazada') {
                motivo = prompt("Indica el motivo del rechazo (opcional):");
                if (motivo === null) return;
            } else {
                if (!confirm(`¿Estás seguro de que quieres aceptar esta cita?`)) return;
            }

            const formData = new FormData();
            formData.append('id', id);
            formData.append('estado', estado);
            if (motivo) formData.append('motivo', motivo);
            
            fetch('calendar_actions.php?action=respond_appointment', { 
                method: 'POST', 
                body: formData 
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cargarSolicitudesCitas();
                    if (window.calendarInstance) window.calendarInstance.refetchEvents();
                    actualizarBadgeSolicitudes();
                    alert(estado === 'aceptada' ? 'Cita aceptada correctamente' : 'Cita rechazada');
                } else {
                    alert('Error: ' + (data.error || 'Error desconocido'));
                }
            });
        }

        function mostrarDetalleNotificacion(mensaje) {
            document.getElementById('detalleNotificacionTexto').textContent = mensaje;
            new bootstrap.Modal(document.getElementById('modalDetalleNotificacion')).show();
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // --- SISTEMA DE NOTIFICACIONES ---
        let lastNotificationCount = -1;
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
        
        window.addEventListener('focus', stopBlinking);
        document.addEventListener('click', stopBlinking);

        function cargarNotificaciones() {
            fetch('obtener_notificaciones.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notifBadge');
                    const list = document.getElementById('notifList');
                    
                    if (lastNotificationCount !== -1 && data.length > lastNotificationCount) {
                        const audio = document.getElementById('notificationSound');
                        if (audio) {
                            audio.currentTime = 0;
                            audio.play().catch(e => console.log("Audio bloqueado:", e));
                        }
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
                            
                            if (notif.tarea_id) {
                                link.href = `dashboard.php?action=verTarea&id=${notif.tarea_id}`;
                            } else {
                                link.href = '#';
                                link.onclick = function(e) {
                                    e.preventDefault();
                                    if (notif.mensaje && notif.mensaje.includes('te ha enviado una solicitud')) {
                                        abrirBandejaCitas();
                                    } else {
                                        mostrarDetalleNotificacion(notif.mensaje);
                                    }
                                };
                            }
                            
                            link.innerHTML = `<div class="d-flex justify-content-between"><small class="fw-bold">${notif.origen_nombre || 'Sistema'}</small></div><div class="text-wrap" style="font-size: 0.85rem;">${notif.mensaje || 'Notificación'}</div>`;
                            
                            const originalOnClick = link.onclick;
                            link.onclick = function(e) {
                                if (originalOnClick) originalOnClick(e);
                                const formData = new FormData();
                                formData.append('id', notif.id);
                                formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
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
                .catch(e => console.error('Error cargando notificaciones:', e));
        }

        // --- Funciones de Gestión (simplificadas) ---
        function abrirModalUsuarios() { 
            fetch('obtener_usuarios_admin.php')
                .then(r => r.text())
                .then(html => {
                    const modal = new bootstrap.Modal(document.getElementById('modalUsuarios'));
                    document.getElementById('modalUsuarios').querySelector('.modal-body').innerHTML = html;
                    modal.show();
                });
        }
        
        function abrirModalEtiquetas() { 
            new bootstrap.Modal(document.getElementById('modalEtiquetas')).show(); 
        }
        
        function abrirModalLicencia() {
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
                    new bootstrap.Modal(document.getElementById('modalLicencia')).show();
                });
        }

        // Función para redimensionar calendario al cambiar tamaño de ventana
        window.addEventListener('resize', function() {
            if (window.calendarInstance) {
                window.calendarInstance.render();
            }
        });

        // --- FUNCIONES COMPARTIR (NUEVO) ---
        function shareEvent() {
            if (!window.currentDetailEventId) return;
            const url = '<?= APP_URL ?>calendar.php?event_id=' + window.currentDetailEventId;
            document.getElementById('shareLinkInput').value = url;
            document.getElementById('shareWhatsappPhone').value = ''; // Reset phone
            
            // Ocultar modal detalle
            bootstrap.Modal.getInstance(document.getElementById('modalDetalleEvento')).hide();
            
            const modal = new bootstrap.Modal(document.getElementById('modalShareEvento'));
            modal.show();
        }

        function enviarWhatsAppShare() {
            const phone = document.getElementById('shareWhatsappPhone').value.replace(/[^0-9]/g, '');
            if (!phone) {
                alert('Ingresa un número de teléfono válido.');
                return;
            }
            const msg = window.currentShareMessage || ('Te comparto un evento: ' + document.getElementById('shareLinkInput').value);
            window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
        }

        function copiarEnlaceShare() {
            const input = document.getElementById('shareLinkInput');
            input.select();
            document.execCommand('copy'); // Fallback
            navigator.clipboard.writeText(input.value).then(() => {
                const msg = document.getElementById('shareSuccessMsg');
                msg.style.display = 'block';
                msg.style.opacity = '1';
                setTimeout(() => { msg.style.opacity = '0'; setTimeout(()=>msg.style.display='none',500); }, 2000);
            });
        }
    </script>
    
    <!-- Sonido de Notificación -->
    <audio id="notificationSound" src="https://cdn.pixabay.com/download/audio/2022/03/24/audio_ff70e2a305.mp3?filename=notification-sound-7062.mp3" preload="auto"></audio>
</body>
</html>