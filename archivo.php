<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$usuario_actual_nombre = $_SESSION['usuario_nombre'];
$usuario_actual_username = $_SESSION['usuario_username'];
$usuario_actual_rol = $_SESSION['usuario_rol'];

// Obtener todas las tareas archivadas
$stmt = $pdo->prepare("
    SELECT t.*, p.nombre as proyecto_nombre, u.nombre as usuario_nombre
    FROM tareas t
    JOIN proyectos p ON t.proyecto_id = p.id
    JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.estado = 'archivado'
    ORDER BY t.fecha_creacion DESC
");
$stmt->execute();
$tareas_archivadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archivo - TuDu</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .card {
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <!-- Header Fijo (reutilizado de dashboard) -->
    <header class="main-header">
        <div class="header-content container">
            <div class="header-left">
                <a class="header-logo" href="dashboard.php">
                    <img src="icons/logo_top.png" alt="TuDu Logo" style="height: 40px;">
                </a>
            </div>
            <div class="header-right">
                <div class="dropdown user-menu">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($usuario_actual_nombre) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="container my-5 pt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-archive-fill"></i> Tareas Archivadas</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Dashboard</a>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($tareas_archivadas)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-box-seam" style="font-size: 3rem; color: #6c757d;"></i>
                        <h5 class="mt-3 text-muted">El archivo está vacío</h5>
                        <p class="text-muted">Las tareas que archives aparecerán aquí.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Proyecto</th>
                                    <th>Usuario</th>
                                    <th>Fecha Archivada</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tareas_archivadas as $tarea): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tarea['titulo']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($tarea['proyecto_nombre']) ?></span></td>
                                        <td><?= htmlspecialchars($tarea['usuario_nombre']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($tarea['fecha_actualizacion'])) ?></td>
                                        <td class="text-end">
                                            <form method="POST" action="restaurar_tarea.php" class="d-inline">
                                                <input type="hidden" name="tarea_id" value="<?= $tarea['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Restaurar Tarea">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                                </button>
                                            </form>
                                            <a href="eliminar_tarea.php?id=<?= $tarea['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger ms-2" 
                                               title="Eliminar Permanentemente"
                                               onclick="return confirm('¿Estás seguro de que quieres ELIMINAR PERMANENTEMENTE esta tarea? Esta acción no se puede deshacer.')">
                                                <i class="bi bi-trash3-fill"></i> Eliminar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
