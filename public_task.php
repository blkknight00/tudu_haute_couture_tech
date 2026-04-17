<?php
require_once 'config.php';

$token = $_GET['token'] ?? null;

if (empty($token)) {
    die('<div style="text-align:center; margin-top:50px; font-family:sans-serif;"><h1>Enlace no válido</h1><p>No se proporcionó un token de acceso.</p></div>');
}

try {
    // 1. Buscar tarea por token (Soporta ambos métodos: tabla separada o columna en tareas)
    $tarea = null;
    
    // Intento A: Tabla tarea_share_tokens (Método nuevo/robusto)
    $stmt = $pdo->prepare("
        SELECT t.*, p.nombre as proyecto_nombre 
        FROM tareas t 
        JOIN tarea_share_tokens s ON t.id = s.tarea_id 
        LEFT JOIN proyectos p ON t.proyecto_id = p.id
        WHERE s.token = ?
    ");
    $stmt->execute([$token]);
    $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Intento B: Columna en tabla tareas (Método simple/legacy)
    if (!$tarea) {
        $stmt = $pdo->prepare("
            SELECT t.*, p.nombre as proyecto_nombre 
            FROM tareas t 
            LEFT JOIN proyectos p ON t.proyecto_id = p.id 
            WHERE t.token_compartido = ?
        ");
        $stmt->execute([$token]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$tarea) {
        die('<div style="text-align:center; margin-top:50px; font-family:sans-serif;"><h1>Tarea no encontrada</h1><p>El enlace puede haber expirado o la tarea fue eliminada.</p></div>');
    }
    
    $tarea_id = $tarea['id'];

    // 2. Obtener subtareas
    $stmt_sub = $pdo->prepare("SELECT * FROM subtareas WHERE tarea_id = ? ORDER BY id ASC");
    $stmt_sub->execute([$tarea_id]);
    $subtareas = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener notas
    $stmt_notas = $pdo->prepare("
        SELECT n.*, u.nombre as usuario_nombre 
        FROM notas_tareas n 
        LEFT JOIN usuarios u ON n.usuario_id = u.id 
        WHERE n.tarea_id = ? 
        ORDER BY n.fecha_creacion ASC
    ");
    $stmt_notas->execute([$tarea_id]);
    $notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Obtener archivos
    $stmt_files = $pdo->prepare("SELECT * FROM archivos_adjuntos WHERE tarea_id = ?");
    $stmt_files->execute([$tarea_id]);
    $archivos = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<h1>Error de Sistema</h1><p>" . $e->getMessage() . "</p>");
}

$mensaje_feedback = $_GET['feedback'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tarea['titulo']) ?> - TuDu Compartido</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent-color: #D97941;
            --bg-color: #f4f6f9;
        }
        body { 
            background-color: var(--bg-color); 
            font-family: 'Poppins', sans-serif; 
            padding-top: 40px; 
            padding-bottom: 40px;
        }
        .task-container { max-width: 850px; margin: 0 auto; }
        .card { border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-radius: 16px; overflow: hidden; }
        .header-logo { margin-bottom: 2rem; }
        .status-badge { font-size: 0.9rem; padding: 0.5em 1em; border-radius: 50px; }
        
        /* Colores de estado */
        .bg-pendiente { background-color: #ff3b30 !important; color: white; }
        .bg-en_progreso { background-color: #ff9500 !important; color: white; }
        .bg-completado { background-color: #34c759 !important; color: white; }

        .nota-item { 
            background-color: #fff; 
            border-left: 4px solid var(--accent-color); 
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .nota-item.guest { border-left-color: #6c757d; background-color: #f8f9fa; }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="container task-container">
        <div class="text-center header-logo">
            <img src="icons/logo_top.png" alt="TuDu" height="60">
        </div>

        <?php if ($mensaje_feedback): ?>
            <div class="alert alert-success text-center mb-4 shadow-sm border-0">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars(urldecode($mensaje_feedback)) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <!-- Encabezado de la Tarea -->
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <span class="badge bg-light text-dark border rounded-pill">
                        <i class="bi bi-folder2"></i> <?= htmlspecialchars($tarea['proyecto_nombre'] ?? 'General') ?>
                    </span>
                    <span class="badge status-badge bg-<?= $tarea['estado'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $tarea['estado'])) ?>
                    </span>
                </div>
                
                <h1 class="card-title mb-4 fw-bold text-dark"><?= htmlspecialchars($tarea['titulo']) ?></h1>
                
                <div class="mb-5 text-secondary" style="white-space: pre-wrap; font-size: 1.05rem; line-height: 1.7;"><?= htmlspecialchars($tarea['descripcion']) ?: '<em>Sin descripción detallada.</em>' ?></div>
                
                <div class="row mb-4 g-3">
                    <div class="col-6 col-md-3">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Prioridad</small>
                        <div class="mt-1">
                            <?php if(($tarea['prioridad'] ?? 'media') == 'alta'): ?>
                                <span class="text-danger fw-bold"><i class="bi bi-flag-fill"></i> Alta</span>
                            <?php elseif(($tarea['prioridad'] ?? 'media') == 'media'): ?>
                                <span class="text-warning fw-bold"><i class="bi bi-flag-fill"></i> Media</span>
                            <?php else: ?>
                                <span class="text-success fw-bold"><i class="bi bi-flag-fill"></i> Baja</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Vencimiento</small>
                        <div class="mt-1 fw-medium">
                            <?php if($tarea['fecha_termino']): ?>
                                <i class="bi bi-calendar-event"></i> <?= date('d/m/Y', strtotime($tarea['fecha_termino'])) ?>
                            <?php else: ?>
                                <span class="text-muted">--</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Subtareas -->
                <?php if (!empty($subtareas)): ?>
                    <hr class="my-4">
                    <h6 class="section-title"><i class="bi bi-check2-square"></i> Lista de Pasos</h6>
                    <ul class="list-group list-group-flush mb-4">
                        <?php foreach ($subtareas as $sub): ?>
                            <li class="list-group-item d-flex align-items-center bg-transparent px-0 border-bottom-0 py-1">
                                <i class="bi <?= $sub['completado'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?> me-3 fs-5"></i>
                                <span class="<?= $sub['completado'] ? 'text-decoration-line-through text-muted' : '' ?>">
                                    <?= htmlspecialchars($sub['titulo']) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <hr class="my-4">

                <!-- Archivos Adjuntos -->
                <h6 class="section-title"><i class="bi bi-paperclip"></i> Archivos Adjuntos</h6>
                <?php if (!empty($archivos)): ?>
                    <div class="list-group mb-3">
                        <?php foreach ($archivos as $file): ?>
                            <a href="uploads/<?= htmlspecialchars($file['nombre_archivo']) ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center border rounded mb-2 p-3">
                                <div class="me-3 bg-light rounded p-2 text-primary">
                                    <i class="bi bi-file-earmark-text fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($file['nombre_original']) ?></div>
                                    <small class="text-muted">Clic para descargar (<?= round($file['tamano'] / 1024, 1) ?> KB)</small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-3">No hay archivos adjuntos.</p>
                <?php endif; ?>

                <!-- Formulario Subir Archivo (Invitado) -->
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body">
                        <h6 class="card-title mb-3 small fw-bold">📤 Subir un archivo</h6>
                        <form action="public_upload.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="share_token" value="<?= htmlspecialchars($token) ?>">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <input type="text" class="form-control form-control-sm" name="uploader_name" placeholder="Tu nombre (ej: Cliente)" required>
                                </div>
                                <div class="col-md-5">
                                    <input type="file" class="form-control form-control-sm" name="archivo" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-sm btn-secondary w-100">Subir</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Comentarios -->
                <h6 class="section-title"><i class="bi bi-chat-left-text"></i> Comentarios</h6>
                <div class="mb-4" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($notas)): ?>
                        <div class="text-center py-4 text-muted bg-light rounded">
                            <i class="bi bi-chat-square-dots fs-3 d-block mb-2"></i>
                            No hay comentarios aún.
                        </div>
                    <?php else: ?>
                        <?php foreach ($notas as $nota): 
                            $is_guest = empty($nota['usuario_id']);
                        ?>
                            <div class="nota-item <?= $is_guest ? 'guest' : '' ?>">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong class="<?= $is_guest ? 'text-secondary' : 'text-primary' ?>">
                                        <?= htmlspecialchars($is_guest ? ($nota['nombre_invitado'] ?? 'Invitado') : $nota['usuario_nombre']) ?>
                                    </strong>
                                    <small class="text-muted" style="font-size: 0.8rem;"><?= date('d/m/Y H:i', strtotime($nota['fecha_creacion'])) ?></small>
                                </div>
                                <p class="mb-0 text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($nota['nota']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Formulario Comentario (Invitado) -->
                <div class="card border-0 shadow-sm" style="background-color: #f8f9fa;">
                    <div class="card-body">
                        <h6 class="card-title mb-3">💬 Dejar un comentario</h6>
                        <form action="agregar_nota.php" method="POST">
                            <input type="hidden" name="share_token" value="<?= htmlspecialchars($token) ?>">
                            <div class="mb-3">
                                <input type="text" class="form-control" name="nombre_invitado" placeholder="Tu Nombre (para que sepamos quién eres)" required>
                            </div>
                            <div class="mb-3">
                                <textarea class="form-control" name="nota" rows="3" placeholder="Escribe tu mensaje aquí..." required></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Enviar Comentario</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            <div class="card-footer bg-white text-center py-3 border-top">
                <small class="text-muted">Vista pública de tarea compartida en <strong>TuDu</strong>.</small>
            </div>
        </div>
    </div>
</body>
</html>