<?php
require_once 'config.php';
session_start();

// --- CONFIGURACIÓN Y SEGURIDAD ---
$panel_password = 'MiClaveSaaSAdmin'; // ¡Cambia esto por una contraseña segura!
$is_logged_in = isset($_SESSION['saas_admin_logged_in']) && $_SESSION['saas_admin_logged_in'] === true;

$mensaje = '';
$action = $_GET['action'] ?? 'list'; // 'list', 'edit', 'new', 'delete'
$license_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// --- PROCESAMIENTO DE ACCIONES ---

// Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['saas_admin_logged_in']);
    header("Location: saas_admin.php");
    exit;
}

// Login
if (isset($_POST['password'])) {
    if ($_POST['password'] === $panel_password) {
        $_SESSION['saas_admin_logged_in'] = true;
        header("Location: saas_admin.php");
        exit;
    } else {
        $mensaje = '<div class="alert alert-danger">Contraseña incorrecta.</div>';
    }
}

// Proteger el panel si no está logueado
if (!$is_logged_in) {
    // El HTML mostrará el formulario de login
} else {
    // --- LÓGICA CRUD (Solo si está logueado) ---

    // Procesar creación o edición
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_name'])) {
        try {
            $edit_id = isset($_POST['license_id']) ? (int)$_POST['license_id'] : null;
            $client_name = trim($_POST['client_name']);
            $user_limit = (int)$_POST['user_limit'];
            $expiration_date = $_POST['expiration_date'];
            $status = $_POST['status'];

            if ($edit_id) { // Actualizar (Update)
                $stmt = $pdo->prepare(
                    "UPDATE saas_licencias SET client_name = ?, user_limit = ?, expiration_date = ?, status = ? WHERE id = ?"
                );
                $stmt->execute([$client_name, $user_limit, $expiration_date, $status, $edit_id]);
                $_SESSION['flash_message'] = '<div class="alert alert-info">¡Licencia actualizada con éxito!</div>';
            } else { // Crear (Create)
                $license_key = 'TUDU-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
                $stmt = $pdo->prepare(
                    "INSERT INTO saas_licencias (license_key, client_name, user_limit, expiration_date, status) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$license_key, $client_name, $user_limit, $expiration_date, $status]);
                $_SESSION['flash_message'] = '<div class="alert alert-success">¡Licencia creada con éxito! Llave: <strong>' . $license_key . '</strong></div>';
            }
            header("Location: saas_admin.php");
            exit;
        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-danger">Error al procesar licencia: ' . $e->getMessage() . '</div>';
        }
    }

    // Procesar eliminación (Delete)
    if ($action === 'delete' && $license_id && isset($_GET['confirm'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM saas_licencias WHERE id = ?");
            $stmt->execute([$license_id]);
            $_SESSION['flash_message'] = '<div class="alert alert-warning">Licencia eliminada correctamente.</div>';
            header("Location: saas_admin.php");
            exit;
        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-danger">Error al eliminar: ' . $e->getMessage() . '</div>';
        }
    }

    // --- OBTENER DATOS PARA LAS VISTAS ---
    $licencias = [];
    $licencia_a_editar = null;

    if ($action === 'list' || $action === 'delete') {
        $stmt = $pdo->query("SELECT * FROM saas_licencias ORDER BY created_at DESC");
        $licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($action === 'edit' && $license_id) {
        $stmt = $pdo->prepare("SELECT * FROM saas_licencias WHERE id = ?");
        $stmt->execute([$license_id]);
        $licencia_a_editar = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$licencia_a_editar) { // Si el ID no existe, redirigir
            header("Location: saas_admin.php");
            exit;
        }
    }

    // Mensajes flash para feedback después de redirigir
    if (isset($_SESSION['flash_message'])) {
        $mensaje = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin de Licencias SaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light py-5">

<div class="container">
    <h1 class="text-center mb-4"><i class="bi bi-key-fill text-primary"></i> Panel de Licencias TuDu</h1>

    <?= $mensaje ?>

    <?php if (!$is_logged_in): ?>
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="card-title text-center">Acceso de Administrador</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña del Panel:</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mt-2">Entrar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        
        <?php if ($action === 'list'): ?>
            <!-- VISTA DE LISTA Y CREACIÓN -->
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5><i class="bi bi-plus-circle"></i> Crear Nueva Licencia</h5></div>
                <div class="card-body">
                    <form method="POST" class="row g-3 align-items-end">
                        <div class="col-md-4"><label class="form-label">Nombre del Cliente:</label><input type="text" name="client_name" class="form-control" required></div>
                        <div class="col-md-2"><label class="form-label">Límite Usuarios:</label><input type="number" name="user_limit" class="form-control" value="5" required></div>
                        <div class="col-md-3"><label class="form-label">Fecha Expiración:</label><input type="date" name="expiration_date" class="form-control" required></div>
                        <div class="col-md-3"><input type="hidden" name="status" value="active"><button type="submit" class="btn btn-success w-100">Generar Licencia</button></div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-list-ul"></i> Licencias Existentes</h5>
                    <a href="?logout=true" class="btn btn-sm btn-outline-secondary">Cerrar Sesión</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead><tr><th>Cliente</th><th>Llave</th><th>Límite</th><th>Estado</th><th>Vence</th><th>Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($licencias as $lic): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($lic['client_name']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($lic['license_key']) ?></code></td>
                                        <td class="text-center"><?= htmlspecialchars($lic['user_limit']) ?></td>
                                        <td><span class="badge bg-<?= $lic['status'] == 'active' ? 'success' : ($lic['status'] == 'suspended' ? 'warning' : 'danger') ?>"><?= ucfirst($lic['status']) ?></span></td>
                                        <td><?= date('d/m/Y', strtotime($lic['expiration_date'])) ?></td>
                                        <td>
                                            <a href="?action=edit&id=<?= $lic['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                            <a href="?action=delete&id=<?= $lic['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta licencia? Esta acción no se puede deshacer.')"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'edit' || $action === 'new'): ?>
            <!-- VISTA DE EDICIÓN / CREACIÓN -->
            <div class="card shadow-sm">
                <div class="card-header"><h5><i class="bi bi-pencil-square"></i> <?= $licencia_a_editar ? 'Editar' : 'Crear' ?> Licencia</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="license_id" value="<?= $licencia_a_editar['id'] ?? '' ?>">
                        <div class="mb-3"><label class="form-label">Nombre del Cliente:</label><input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($licencia_a_editar['client_name'] ?? '') ?>" required></div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Límite Usuarios:</label><input type="number" name="user_limit" class="form-control" value="<?= $licencia_a_editar['user_limit'] ?? 5 ?>" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Fecha Expiración:</label><input type="date" name="expiration_date" class="form-control" value="<?= $licencia_a_editar['expiration_date'] ?? '' ?>" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Estado:</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= ($licencia_a_editar['status'] ?? 'active') == 'active' ? 'selected' : '' ?>>Activa</option>
                                    <option value="suspended" <?= ($licencia_a_editar['status'] ?? '') == 'suspended' ? 'selected' : '' ?>>Suspendida</option>
                                    <option value="inactive" <?= ($licencia_a_editar['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactiva</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="saas_admin.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>