<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

/**
 * Verifica el Bearer Token JWT y rehidrata las variables $_SESSION 
 * temporales para compatibilidad con el resto del sistema.
 */
function checkAuth(): array
{
    // Ensure session is started so we can inject legacy variables safely
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $headers = apache_request_headers();
    
    // Fallback: Check custom X-Auth-Token in case Apache strips Authorization
    $customToken = $headers['X-Auth-Token'] ?? $headers['x-auth-token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    
    $jwt = '';
    if ($customToken) {
        $jwt = $customToken;
    } elseif ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $jwt = $matches[1];
    }
    
    if (!$jwt) {
        file_put_contents(__DIR__ . '/auth_trace.log', "[" . date('H:i:s') . "] No JWT found. Headers: " . json_encode($headers) . " | SERVER: " . json_encode($_SERVER) . "\n", FILE_APPEND);
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Token JWT no proporcionado']);
        exit;
    }

    try {
        if (!defined('JWT_SECRET')) {
            throw new Exception("Lave JWT iterativa secreta no encontrada en config");
        }
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
        $user = (array) $decoded->user;

        // --- POLYFILL / REHIDRATACIÓN DE $_SESSION ---
        // Esto mantiene compatibles a los +40 archivos que no usan JWT
        $_SESSION['logged_in'] = true;
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_username'] = $user['username'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_rol'] = $user['rol'];
        $_SESSION['usuario_foto'] = $user['foto'];
        $_SESSION['organizacion_id'] = $user['organizacion_id'];
        $_SESSION['organizacion_nombre'] = $user['organizacion_nombre'];
        $_SESSION['rol_organizacion'] = $user['rol_organizacion'];

        return $user;

    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/auth_trace.log', "[" . date('H:i:s') . "] JWT Exception: " . $e->getMessage() . "\n", FILE_APPEND);
        // Destroy fake session
        session_unset();
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Token inválido o expirado.']);
        exit;
    }
}

/**
 * Retorna el plan y status de la organización activa.
 */
function getOrgPlan(?int $org_id = null): array
{
    global $pdo;

    $oid = $org_id ?? ($_SESSION['organizacion_id'] ?? null);
    if (!$oid) return ['plan' => 'starter', 'plan_status' => 'inactive', 'edition' => 'standalone'];

    try {
        $stmt = $pdo->prepare("
            SELECT plan, plan_status, edition, trial_ends_at, plan_renews_at,
                   members_limit, projects_limit, tasks_limit, storage_limit_mb, whatsapp_bot
            FROM organizaciones WHERE id = ?
        ");
        $stmt->execute([$oid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: ['plan' => 'starter', 'plan_status' => 'inactive', 'edition' => 'standalone'];
    } catch (Exception $e) {
        return ['plan' => 'starter', 'plan_status' => 'inactive', 'edition' => 'standalone'];
    }
}

function authIsSuperAdmin(): bool
{
    return ($_SESSION['usuario_rol'] ?? '') === 'super_admin';
}

function authIsAdmin(): bool
{
    return in_array($_SESSION['usuario_rol'] ?? '', ['administrador', 'admin_global', 'super_admin']);
}
