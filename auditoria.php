<?php
require_once 'config.php';
session_start();

// Seguridad: Solo administradores y super administradores
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['usuario_rol'], ['admin', 'super_admin'])) {
    header("Location: dashboard.php");
    exit();
}

$usuario_actual_nombre = $_SESSION['usuario_nombre'];

// Obtener registros de auditoría
$registros = [];
$error = null;

try {
    // Consultar los últimos 100 registros
    $sql = "SELECT a.*, u.nombre as usuario_nombre 
            FROM auditoria a 
            LEFT JOIN usuarios u ON a.usuario_id = u.id 
            LEFT JOIN tareas t ON (a.tabla_afectada = 'tareas' AND a.registro_id = t.id)
            ORDER BY a.fecha DESC LIMIT 100";
    $stmt = $pdo->query($sql);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $error = "La tabla de auditoría no existe. <a href='update_db_auditoria.php' class='alert-link'>Haz clic aquí para crearla</a>.";
    } else {
        $error = "Error al cargar auditoría: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría - TuDu</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .card { border-radius: 15px; }
        .table-audit { font-size: 0.9rem; }
    </style>
</head>
<body>
    <!-- Header Fijo -->
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
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-power"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="container my-5 pt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-clipboard-data"></i> Registro de Auditoría</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-warning"><?= $error ?></div>
                <?php elseif (empty($registros)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-journal-check" style="font-size: 3rem; color: #6c757d;"></i>
                        <h5 class="mt-3 text-muted">No hay registros de auditoría</h5>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-audit align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Tabla</th>
                                    <th>ID Reg.</th>
                                    <th>Detalles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $reg): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($reg['fecha'])) ?></td>
                                        <td>
                                            <?php if ($reg['usuario_nombre']): ?>
                                                <span class="fw-bold"><?= htmlspecialchars($reg['usuario_nombre']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">ID: <?= $reg['usuario_id'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = 'secondary';
                                            if (strpos($reg['accion'], 'INSERT') !== false) $badgeClass = 'success';
                                            if (strpos($reg['accion'], 'UPDATE') !== false) $badgeClass = 'warning text-dark';
                                            if (strpos($reg['accion'], 'DELETE') !== false) $badgeClass = 'danger';
                                            if (strpos($reg['accion'], 'LOGIN') !== false) $badgeClass = 'info text-dark';
                                            ?>
                                            <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($reg['accion']) ?></span>
                                        </td>
                                        <td><code><?= htmlspecialchars($reg['tabla_afectada']) ?></code></td>
                                        <td><?= htmlspecialchars($reg['registro_id']) ?></td>
                                        <td><?= htmlspecialchars($reg['detalles']) ?></td>
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