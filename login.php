<?php
// session_start(); // Iniciado en config.php
require_once 'config.php';

if ($_POST) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        die("Error de seguridad: Token CSRF inválido. Recarga la página.");
    }
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ? AND activo = 1");
    $stmt->execute([$username]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && password_verify($password, $usuario['password'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_username'] = $usuario['username'];
        $_SESSION['usuario_rol'] = $usuario['rol'];
        $_SESSION['usuario_foto'] = $usuario['foto_perfil']; // Cargar foto
        $_SESSION['logged_in'] = true;
        
        // --- LÓGICA RECORDARME ---
        if (isset($_POST['remember_me'])) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE usuarios SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $usuario['id']]);
            
            // Cookie por 30 días
            setcookie('remember_me', $token, time() + (86400 * 30), "/", "", false, true); // HttpOnly
        }

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Credenciales incorrectas";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TuDu</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            padding-top: 0 !important; /* Eliminar el padding global que empuja el login hacia abajo */
        }
        .login-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card p-4">
                    <div class="text-center mb-4">
                        <img src="icons/logo_top.png" alt="TuDu Logo" style="height: 50px;">
                        <p class="text-muted">Inicia sesión para continuar</p>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?= getCsrfField() ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario:</label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Tu nombre de usuario" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña:</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Tu contraseña" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">Recuérdame</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">Iniciar Sesión</button>
                    </form>

                    <div class="text-center my-3">
                        <span class="text-muted">o</span>
                    </div>

                    <a href="login_google.php" class="btn btn-outline-secondary w-100" style="display: flex; align-items: center; justify-content: center; gap: 10px; color: #1c1c1e; border-color: #e5e5ea; background: white;">
                        <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0OCA0OCI+PHBhdGggZmlsbD0iI0ZGQzEwNyIgZD0iTTQzLjYxMSAyMC4wODNINDJWMjBIMjR2OGgxMS4zMDNjLTEuNjQ5IDQuNjU3LTYuMDggOC0xMS4zMDMgOGMtNi42MjcgMC0xMi01LjM3My0xMi0xMmMwLTYuNjI3IDUuMzczLTEyIDEyLTEyYzMuMDU5IDAgNS44NDIgMS4xNTQgNy45NjEgMy4wMzlsNS42NTctNS42NTdDMzQuMDQ2IDYuMDUzIDI5LjI2OCA0IDI0IDRDMTIuOTU1IDQgNCAxMi45NTUgNCAyNGMwIDExLjA0NSA4Ljk1NSAyMCAyMCAyMGMxMS4wNDUgMCAyMC04Ljk1NSAyMC0yMEM0NCAyMi42NTkgNDMuODYyIDIxLjM1IDQzLjYxMSAyMC4wODN6Ii8+PHBhdGggZmlsbD0iI0ZGM0QwMCIgZD0iTTYuMzA2IDE0LjY5MWw2LjU3MSA0LjgxOUMxNC42NTUgMTUuMTA4IDE4Ljk2MSAxMiAyNCAxMmMzLjA1OSAwIDUuODQyIDEuMTU0IDcuOTYxIDMuMDM5bDUuNjU3LTUuNjU3QzM0LjA0NiA2LjA1MyAyOS4yNjggNCAyNCA0QzE2LjMxOCA0IDkuNjU2IDguMzM3IDYuMzA2IDE0LjY5MXoiLz48cGF0aCBmaWxsPSIjNENBRjUwIiBkPSJNMjQgNDRjNS4xNjYgMCA5Ljg2LTEuOTc3IDEzLjQwOS01LjE5MmwtNi4xOS01LjIzOEMyOS4yMTEgMzUuMDkxIDI2LjcxNSAzNiAyNCAzNmMtNS4yMDIgMC05LjYxOS0zLjMxNy0xMS4yODMtNy45NDZsLTYuNTIyIDUuMDI1QzkuNTA1IDM5LjU1NiAxNi4yMjcgNDQgMjQgNDR6Ii8+PHBhdGggZmlsbD0iIzE5NzZEMiIgZD0iTTQzLjYxMSAyMC4wODNINDJWMjBIMjR2OGgxMS4zMDNjLTAuNzkyIDIuMjM3LTIuMjMxIDQuMTY2LTQuMDg3IDUuNTcxYzAuMDAxLTAuMDAxIDAuMDAyLTAuMDAxIDAuMDAzLTAuMDAybDYuMTkgNS4yMzhDMzYuOTcxIDM5LjIwNSA0NCAzNCA0NCAyNEM0NCAyMi42NTkgNDMuODYyIDIxLjM1IDQzLjYxMSAyMC4wODN6Ii8+PC9zdmc+" alt="Google" style="width: 20px; height: 20px;">
                        Continuar con Google
                    </a>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted"> </small>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    <script>
        // Limpiar el flag del Splash Screen para que vuelva a aparecer al iniciar sesión
        sessionStorage.removeItem('splashShown');
    </script>
</body>
</html>