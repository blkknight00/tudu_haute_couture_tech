<?php
require_once 'config.php';

// Asegurar que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Limpiar token de "Recordarme" en la base de datos si el usuario estaba logueado
if (isset($_SESSION['usuario_id'])) {
    try {
        // Invalidar el token en la BD para seguridad
        $stmt = $pdo->prepare("UPDATE usuarios SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
    } catch (Exception $e) {
        // Continuar con el logout aunque falle la BD
    }
}

// 2. Limpiar todas las variables de sesión
$_SESSION = array();

// 3. Borrar la cookie de sesión del navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Borrar la cookie de "Recordarme"
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// 5. Destruir la sesión
session_destroy();

// 6. Redirigir al login
header("Location: login.php");
exit();
?>