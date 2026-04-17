<?php
/**
 * CSRF Protection Helper Functions
 */
// Asegurarse de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Genera un token CSRF y lo almacena en la sesión.
 * Si ya existe uno, lo reutiliza para la sesión actual.
 * @return string El token CSRF.
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
/**
 * Verifica si el token CSRF recibido coincide con el de la sesión.
 * @param string $token El token recibido del formulario.
 * @return bool True si es válido, False si no.
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
/**
 * Genera un campo input hidden con el token CSRF para insertar en formularios.
 * @return string HTML input tag.
 */
function getCsrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
