<?php
declare(strict_types=1);
// ═══════════════════════════════════════════════════════════════════════
// TuDu SaaS · Public Registration API
// POST /api/register.php
// Crea: usuario + organización en trial de 14 días
// NO requiere autenticación — es el endpoint de adquisición de clientes
// ═══════════════════════════════════════════════════════════════════════

// CORS abierto para pre-flight de la landing page
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

require_once '../config.php';
require_once 'saas_limits.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Datos requeridos ──────────────────────────────────────────────────
$nombre    = trim($input['nombre']    ?? '');
$username  = trim($input['username']  ?? '');
$email     = trim($input['email']     ?? '');
$password  = $input['password']       ?? '';
$telefono  = trim($input['telefono']  ?? '');
$whatsapp  = trim($input['whatsapp']  ?? $telefono);
$empresa   = trim($input['empresa']   ?? $nombre);   // Nombre de la organización
$edition   = $input['edition']        ?? 'standalone';
$plan      = $input['plan']           ?? 'starter';

// ── Validaciones ──────────────────────────────────────────────────────
$errors = [];

if (empty($nombre))   $errors[] = 'El nombre es obligatorio';
if (empty($email))    $errors[] = 'El correo es obligatorio';
if (empty($password)) $errors[] = 'La contraseña es obligatoria';
if (strlen($password) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Correo electrónico no válido';

// Generar username si no viene
if (empty($username)) {
    $base     = preg_replace('/[^a-z0-9]/', '', strtolower($nombre));
    $username = substr($base, 0, 15) . rand(10, 99);
}

// Validar edición y plan
$allowedEditions = ['standalone', 'corp'];
$allowedPlans    = ['starter', 'pro', 'agency'];
if (!in_array($edition, $allowedEditions)) $edition = 'standalone';
if (!in_array($plan, $allowedPlans))       $plan     = 'starter';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
    exit;
}

try {
    // ── Verificar email duplicado ─────────────────────────────────────
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'El correo o usuario ya está registrado']);
        exit;
    }

    // ── Configurar límites según plan ─────────────────────────────────
    $plans = tuduPlanDefinitions();
    $planKey = "{$edition}_{$plan}";
    $planDef = $plans[$planKey] ?? $plans['standalone_starter'];

    $membersLimit  = min((int)$planDef['members_limit'],  9999);
    $projectsLimit = min((int)$planDef['projects_limit'], 9999);
    $tasksLimit    = min((int)$planDef['tasks_limit'],     9999);
    $storageLimit  = min((int)$planDef['storage_limit_mb'], 9999);
    $whatsappBot   = $planDef['whatsapp_bot'] ? 1 : 0;
    $trialEnds     = date('Y-m-d H:i:s', strtotime('+14 days'));

    $pdo->beginTransaction();

    // ── 1. Crear organización ─────────────────────────────────────────
    $randCode = strtoupper(substr(md5(uniqid((string)rand(), true)), 0, 5));
    $orgCode = "TUDU-" . $randCode; // Temporal code until we have ID, then we update it or just use random.
    // Actually let's use a secure unique 8-character code like Nelti:
    $orgCode = "ORG-" . strtoupper(bin2hex(random_bytes(3)));

    $orgStmt = $pdo->prepare("
        INSERT INTO organizaciones
            (nombre, codigo_acceso, edition, plan, plan_status, trial_ends_at,
             members_limit, projects_limit, tasks_limit, storage_limit_mb, whatsapp_bot)
        VALUES (?, ?, ?, ?, 'trialing', ?, ?, ?, ?, ?, ?)
    ");
    $orgStmt->execute([
        $empresa, $orgCode, $edition, $plan, $trialEnds,
        $membersLimit, $projectsLimit, $tasksLimit, $storageLimit, $whatsappBot
    ]);
    $orgId = (int)$pdo->lastInsertId();

    // ── 2. Crear usuario ──────────────────────────────────────────────
    $hashedPw = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $usrStmt  = $pdo->prepare("
        INSERT INTO usuarios
            (nombre, username, email, telefono, whatsapp, rol, password, activo)
        VALUES (?, ?, ?, ?, ?, 'administrador', ?, 1)
    ");
    $usrStmt->execute([$nombre, $username, $email, $telefono, $whatsapp, $hashedPw]);
    $userId = (int)$pdo->lastInsertId();

    // ── 3. Asociar usuario a organización como admin ──────────────────
    $pdo->prepare("
        INSERT INTO miembros_organizacion (usuario_id, organizacion_id, rol_organizacion)
        VALUES (?, ?, 'admin')
    ")->execute([$userId, $orgId]);

    // ── 4. Crear proyecto de bienvenida ───────────────────────────────
    $pdo->prepare("
        INSERT INTO proyectos (nombre, descripcion, user_id, organizacion_id, fecha_creacion)
        VALUES ('Mi primer proyecto', '¡Bienvenido a TuDu! Aquí puedes organizar tus tareas.', ?, ?, NOW())
    ")->execute([$userId, $orgId]);

    $pdo->commit();

    // ── 5. Iniciar sesión automáticamente (JWT) ──────────────────────
    require_once 'vendor/autoload.php';

    $payload = [
        'iat' => time(),
        'exp' => time() + (86400 * 7),
        'user' => [
            'id' => $userId,
            'nombre' => $nombre,
            'username' => $username,
            'email' => $email,
            'rol' => 'administrador',
            'foto' => null,
            'organizacion_id' => $orgId,
            'organizacion_nombre' => $empresa,
            'rol_organizacion' => 'admin',
            'organizations' => [
                ['id' => $orgId, 'nombre' => $empresa, 'rol_organizacion' => 'admin']
            ]
        ]
    ];

    $token = \Firebase\JWT\JWT::encode($payload, JWT_SECRET, 'HS256');

    echo json_encode([
        'status'       => 'success',
        'message'      => '¡Bienvenido a TuDu!',
        'trial_ends'   => $trialEnds,
        'codigo_acceso'=> $orgCode,
        'token'        => $token,
        'user'         => $payload['user'],
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('TuDu register error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al crear la cuenta. Intenta de nuevo.']);
}
