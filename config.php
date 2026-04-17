<?php
require_once __DIR__ . '/csrf.php';

// --- CENTRALIZED CORS FOR DEVELOPMENT ---
$is_api = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
if ($is_api) {
    header("Content-Type: application/json; charset=UTF-8");
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    // Allows localhost, local network IPs, and the current domain in production
    $http_host = $_SERVER['HTTP_HOST'] ?? '';
    $is_allowed = false;
    
    if (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+|\[::1\])(:\d+)?$/', $origin)) {
        $is_allowed = true;
    } elseif ($http_host && strpos($origin, $http_host) !== false) {
        $is_allowed = true;
    }

    if ($is_allowed) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_credentials.php';

// Evitar que warnings rompan las respuestas JSON (salvo en debug)
if (!defined('DEBUG_MODE')) {
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api/php_error.log');
error_reporting(E_ALL);

// Definir la URL base de la aplicación para usarla en todo el sistema
$server_port = $_SERVER['SERVER_PORT'] ?? 80;
$https = $_SERVER['HTTPS'] ?? 'off';
$protocol = (!empty($https) && $https !== 'off' || $server_port == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$php_self = $_SERVER['PHP_SELF'] ?? '';
$base_dir = str_replace('\\', '/', dirname($php_self));
$base_path = rtrim($base_dir, '/') . '/';

define('APP_URL', $protocol . $domainName . $base_path);
define('JWT_SECRET', 'td_v3_hc_tech_!x9QzP2@2_secur3_t0k3n_$2026');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// --- APP EDITION (Standard vs Corporate) ---
// This can be determined by a license key or a database setting.
// For now, we look for a 'TUDU_EDITION' key in the configuracion table.
$edition = 'standard';
try {
    // We check if the table exists first to avoid errors during initial setup
    $check_config = $pdo->query("SHOW TABLES LIKE 'configuracion'")->fetch();
    if ($check_config) {
        $stmt_ed = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'TUDU_EDITION'");
        $stmt_ed->execute();
        $saved_edition = $stmt_ed->fetchColumn();
        if ($saved_edition) $edition = $saved_edition;
    }
} catch (Exception $e) {
    // Fallback to standard
}
define('APP_EDITION', $edition);

/**
 * Determina si el color de texto debe ser blanco o negro basado en el color de fondo.
 * @param string $hexcolor Color de fondo en formato hexadecimal (ej. #RRGGBB).
 * @return string Retorna '#FFFFFF' (blanco) o '#000000' (negro).
 */
function getContrastingTextColor($hexcolor) {
    $hexcolor = ltrim($hexcolor ?? '', '#');
    if (strlen($hexcolor) != 6) return '#000000'; // Default to black for invalid colors
    list($r, $g, $b) = sscanf($hexcolor, "%02x%02x%02x");
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 128) ? '#000000' : '#FFFFFF';
}

function registrarAuditoria($usuario_id, $accion, $tabla_afectada, $registro_id, $detalles) {
    global $pdo;
    try {
        $org_id = $_SESSION['organizacion_id'] ?? null;
        $sql = "INSERT INTO auditoria (usuario_id, organizacion_id, accion, tabla_afectada, registro_id, detalles) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $org_id, $accion, $tabla_afectada, $registro_id, $detalles]);
    } catch (PDOException $e) {
        // En un entorno real, sería bueno registrar este error en un log de sistema
        // y no detener la ejecución principal.
        error_log("Error al registrar auditoría: " . $e->getMessage());
    }
}

// --- LÓGICA DE RECORDAR SESIÓN ---
function checkRememberMe() {
    global $pdo;
    if (!isset($_SESSION['usuario_id']) && isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        // Simple sanitization
        $token = preg_replace('/[^a-f0-9]/', '', $token);

        if ($token) {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE remember_token = ? AND activo = 1");
            $stmt->execute([$token]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_username'] = $usuario['username'];
                $_SESSION['usuario_rol'] = $usuario['rol'];
                $_SESSION['usuario_foto'] = $usuario['foto_perfil'];
                $_SESSION['logged_in'] = true;
                
                // Opcional: Rotar token aquí para mayor seguridad
                registrarAuditoria($usuario['id'], 'AUTO_LOGIN', 'usuarios', $usuario['id'], 'Inicio sesión por cookie');
                return true;
            }
        }
    }
    return false;
}

// Intentar restaurar sesión si no existe
checkRememberMe();