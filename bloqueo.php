<?php
session_start();
require_once 'config.php';

// Obtener el mensaje de bloqueo. La prioridad es:
// 1. Mensaje específico de la API guardado en la sesión.
// 2. Mensaje genérico de la licencia guardado en la BD.
// 3. Mensaje por defecto si no hay nada configurado.
$mensaje = $_SESSION['bloqueo_msg'] ?? null;

// Limpiar el mensaje de la sesión para que no se muestre en futuras visitas si el problema se resuelve.
if (isset($_SESSION['bloqueo_msg'])) {
    unset($_SESSION['bloqueo_msg']);
}

if (!$mensaje) {
    $stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'LICENSE_MSG'");
    $stmt->execute();
    $mensaje = $stmt->fetchColumn();
}

if (!$mensaje) {
    $mensaje = "El acceso a la aplicación ha sido restringido. Por favor, contacte al administrador.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Bloqueado - TuDu</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="container text-center" style="max-width: 600px;">
        <div class="card p-4 p-md-5 shadow-sm">
            <h1 class="display-4 text-danger"><i class="bi bi-exclamation-octagon-fill"></i></h1>
            <h2 class="mb-3">Acceso Bloqueado</h2>
            <p class="lead text-muted"><?= htmlspecialchars($mensaje) ?></p>
            <hr>
            <p>Si crees que esto es un error, por favor contacta al administrador del sistema.</p>
            <?php if (isset($_SESSION['logged_in'])): ?>
                <div class="mt-3">
                    <a href="logout.php" class="btn btn-secondary">Cerrar Sesión</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>