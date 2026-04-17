<?php
require_once 'config.php';
session_start();

// Seguridad: SOLO SUPER ADMIN
if (!isset($_SESSION['logged_in']) || $_SESSION['usuario_rol'] != 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

// Procesar formulario
$mensaje = '';
if ($_POST) {
    try {
        $pdo->beginTransaction();
        
        $configs = [
            'MAX_USERS' => $_POST['max_users'],
            'APP_STATUS' => isset($_POST['app_status']) ? 'active' : 'inactive',
            'LICENSE_MSG' => $_POST['license_msg']
        ];

        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        
        foreach ($configs as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        
        $pdo->commit();
        $mensaje = '<div class="alert alert-success">Configuración actualizada correctamente.</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Obtener valores actuales
$stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('MAX_USERS', 'APP_STATUS', 'LICENSE_MSG')");
$current_config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Contar usuarios actuales
$stmt_count = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1");
$total_usuarios = $stmt_count->fetchColumn();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - TuDu</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light p-5">
    <div class="container" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-shield-shaded text-primary"></i> Panel de Licencia</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">Volver al Dashboard</a>
        </div>

        <?= $mensaje ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-4">Estado de la Licencia</h5>
                
                <div class="row text-center mb-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white">
                            <h3 class="text-primary"><?= $total_usuarios ?></h3>
                            <small class="text-muted">Usuarios Activos</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white">
                            <h3 class="text-secondary"><?= $current_config['MAX_USERS'] ?? 5 ?></h3>
                            <small class="text-muted">Límite Permitido</small>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="app_status" name="app_status" style="width: 3em; height: 1.5em;" <?= ($current_config['APP_STATUS'] ?? 'active') == 'active' ? 'checked' : '' ?>>
                        <label class="form-check-label ms-2 mt-1 fw-bold" for="app_status">Sistema Activo (Permitir acceso)</label>
                        <div class="form-text">Si desactivas esto, nadie (excepto tú) podrá entrar al sistema.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Límite Máximo de Usuarios:</label>
                        <input type="number" class="form-control" name="max_users" value="<?= $current_config['MAX_USERS'] ?? 5 ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mensaje de Bloqueo (si se desactiva):</label>
                        <input type="text" class="form-control" name="license_msg" value="<?= $current_config['LICENSE_MSG'] ?? 'Su licencia ha expirado.' ?>">
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>