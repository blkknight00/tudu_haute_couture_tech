<?php
require_once 'config.php';
session_start();

// 1. SEGURIDAD: Verificar si ya está instalado
// Si ya existe una licencia en la BD, bloqueamos el acceso al instalador.
$stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'TUDU_LICENSE_KEY'");
$licencia_existente = $stmt->fetchColumn();

if ($licencia_existente) {
    die("<h1>⚠️ El sistema ya está instalado.</h1><p>Por seguridad, no puedes ejecutar el instalador nuevamente. <a href='login.php'>Ir al Login</a></p>");
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_key = trim($_POST['license_key']);
    $saas_url = trim($_POST['saas_url']);
    
    // Datos del Admin
    $admin_user = trim($_POST['admin_user']);
    $admin_pass = $_POST['admin_pass'];
    $admin_name = trim($_POST['admin_name']);
    $admin_email = trim($_POST['admin_email']);

    if (empty($license_key) || empty($admin_user) || empty($admin_pass)) {
        $mensaje = "Por favor completa todos los campos obligatorios.";
        $tipo_mensaje = "danger";
    } else {
        // 2. VALIDAR LICENCIA CON EL SAAS (Simulación o Real)
        // Aquí tu sistema contacta a tu servidor central para ver si la llave es válida
        $es_valida = false;
        
        if ($saas_url) {
            $validation_url = $saas_url . '?key=' . urlencode($license_key) . '&action=activate';
            // Simulación de llamada cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $validation_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response_json = curl_exec($ch);
            curl_close($ch);
            
            $response = json_decode($response_json, true);
            if (isset($response['status']) && $response['status'] === 'active') {
                $es_valida = true;
            } else {
                $mensaje = "Error de Licencia: " . ($response['message'] ?? 'La llave no es válida o no se pudo conectar al servidor de licencias.');
                $tipo_mensaje = "danger";
            }
        } else {
            // Si no hay URL de SaaS configurada, asumimos validación manual para pruebas locales
            $es_valida = true; 
        }

        if ($es_valida) {
            try {
                $pdo->beginTransaction();

                // A. Guardar Configuración
                $sql_config = "INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
                $pdo->prepare($sql_config)->execute(['TUDU_LICENSE_KEY', $license_key]);
                $pdo->prepare($sql_config)->execute(['SAAS_API_URL', $saas_url]);
                $pdo->prepare($sql_config)->execute(['APP_STATUS', 'active']);
                $pdo->prepare($sql_config)->execute(['MAX_USERS', $response['user_limit'] ?? 10]); // Límite que viene del SaaS

                // B. Crear Organización Principal
                $pdo->exec("INSERT IGNORE INTO organizaciones (id, nombre) VALUES (1, 'Organización Principal')");

                // C. Crear Super Admin
                $pass_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("INSERT INTO usuarios (username, nombre, email, password, rol, activo) VALUES (?, ?, ?, ?, 'super_admin', 1)");
                $stmt_user->execute([$admin_user, $admin_name, $admin_email, $pass_hash]);
                $new_uid = $pdo->lastInsertId();

                // D. Vincular Admin a Org
                $pdo->prepare("INSERT INTO miembros_organizacion (usuario_id, organizacion_id, rol_organizacion) VALUES (?, 1, 'admin')")->execute([$new_uid]);

                $pdo->commit();

                // Redirigir al login
                header("Location: login.php?installed=true");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $mensaje = "Error en la base de datos: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación de TuDu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h3>🚀 Bienvenido a TuDu</h3>
                        <p class="mb-0">Asistente de Configuración Inicial</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($mensaje): ?>
                            <div class="alert alert-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <h5 class="mb-3 text-primary">1. Licencia</h5>
                            <div class="mb-3">
                                <label class="form-label">Llave de Licencia (License Key):</label>
                                <input type="text" name="license_key" class="form-control" placeholder="XXXX-XXXX-XXXX-XXXX" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL del Servidor de Licencias (SaaS):</label>
                                <input type="url" name="saas_url" class="form-control" value="https://interdata1.store/saas_api/validate.php" placeholder="https://tu-saas.com/api">
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3 text-primary">2. Cuenta de Super Administrador</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">Nombre:</label><input type="text" name="admin_name" class="form-control" required></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Usuario:</label><input type="text" name="admin_user" class="form-control" required></div>
                            </div>
                            <div class="mb-3"><label class="form-label">Email:</label><input type="email" name="admin_email" class="form-control"></div>
                            <div class="mb-3"><label class="form-label">Contraseña:</label><input type="password" name="admin_pass" class="form-control" required></div>

                            <button type="submit" class="btn btn-success w-100 btn-lg mt-3">Instalar y Activar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>