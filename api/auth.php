<?php
// Trace execution
file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Starting auth.php\n", FILE_APPEND);

try {

require_once '../config.php';
file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Config.php loaded in auth.php\n", FILE_APPEND);

$action = $_GET['action'] ?? null;

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Usuario y contraseña son requeridos']);
        exit;
    }

    // 2. Buscar al usuario (por username o email)
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (username = ? OR email = ?) AND activo = 1");
    $stmt->execute([$username, $username]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario['password'])) {
        
        $isGlobalAdmin = in_array($usuario['rol'], ['super_admin', 'admin_global']);
        
        // Fetch all organizations for this user
        if ($isGlobalAdmin) {
            $stmt_orgs = $pdo->prepare("SELECT id, nombre, 'admin' as rol_organizacion FROM organizaciones");
            $stmt_orgs->execute();
        } else {
            $stmt_orgs = $pdo->prepare("
                SELECT o.id, o.nombre, mo.rol_organizacion 
                FROM organizaciones o
                JOIN miembros_organizacion mo ON o.id = mo.organizacion_id
                WHERE mo.usuario_id = ?
            ");
            $stmt_orgs->execute([$usuario['id']]);
        }
        $organizaciones = $stmt_orgs->fetchAll(PDO::FETCH_ASSOC);

        if (count($organizaciones) === 0 && !$isGlobalAdmin) {
             echo json_encode(['status' => 'error', 'message' => 'Tu cuenta no está vinculada a ninguna empresa']);
             exit;
        }

        // Determinar la organización principal (la primera encontrada)
        $org_login = $organizaciones[0] ?? ['id' => 0, 'nombre' => 'Global', 'rol_organizacion' => 'admin'];


        // JWT Payload Setup
        require_once 'vendor/autoload.php';

        $payload = [
            'iat' => time(),
            'exp' => time() + (86400 * 365), // 1 year expiration para experiencia fluida tipo app nativa
            'user' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'username' => $usuario['username'],
                'email' => $usuario['email'] ?? '',
                'rol' => $usuario['rol'],
                'foto' => $usuario['foto_perfil'],
                'organizacion_id' => $org_login['id'],
                'organizacion_nombre' => $org_login['nombre'],
                'rol_organizacion' => $org_login['rol_organizacion'],
                'organizations' => $organizaciones
            ]
        ];

        try {
            $token = \Firebase\JWT\JWT::encode($payload, JWT_SECRET, 'HS256');
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al firmar Token']);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'edition' => APP_EDITION,
            'token' => $token,
            'user' => $payload['user']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas (Usuario o Contraseña)']);
    }
    exit;
}

if ($action === 'check_session') {
    require_once 'auth_middleware.php';
    try {
        $userData = checkAuth();
        echo json_encode([
            'status' => 'authenticated',
            'edition' => APP_EDITION,
            'user' => $userData
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'guest']);
    }
    exit;
}

if ($action === 'switch_org') {
    require_once 'auth_middleware.php';
    require_once 'vendor/autoload.php';

    $user = checkAuth(); // Validates current JWT

    $data = json_decode(file_get_contents("php://input"), true);
    $org_id = $data['organizacion_id'] ?? null;

    if (!$org_id) {
        echo json_encode(['status' => 'error', 'message' => 'ID de organización requerido']);
        exit;
    }

    $isGlobalAdmin = in_array($user['rol'], ['super_admin', 'admin_global']);

    if ($isGlobalAdmin) {
        $stmt = $pdo->prepare("SELECT id, nombre, 'admin' as rol_organizacion FROM organizaciones WHERE id = ?");
        $stmt->execute([$org_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT o.id, o.nombre, mo.rol_organizacion 
            FROM organizaciones o
            JOIN miembros_organizacion mo ON o.id = mo.organizacion_id
            WHERE mo.usuario_id = ? AND o.id = ?
        ");
        $stmt->execute([$user['id'], $org_id]);
    }
    $org = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($org) {
        // Regenerate JWT
        $stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt_user->execute([$user['id']]);
        $usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

        // Fetch all orgs again
        if ($isGlobalAdmin) {
            $stmt_orgs = $pdo->prepare("SELECT id, nombre, 'admin' as rol_organizacion FROM organizaciones");
            $stmt_orgs->execute();
        } else {
            $stmt_orgs = $pdo->prepare("
                SELECT o.id, o.nombre, mo.rol_organizacion 
                FROM organizaciones o
                JOIN miembros_organizacion mo ON o.id = mo.organizacion_id
                WHERE mo.usuario_id = ?
            ");
            $stmt_orgs->execute([$usuario['id']]);
        }
        $organizaciones = $stmt_orgs->fetchAll(PDO::FETCH_ASSOC);

        $payload = [
            'iat' => time(),
            'exp' => time() + (86400 * 365),
            'user' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'username' => $usuario['username'],
                'email' => $usuario['email'] ?? '',
                'rol' => $usuario['rol'],
                'foto' => $usuario['foto_perfil'],
                'organizacion_id' => $org['id'],
                'organizacion_nombre' => $org['nombre'],
                'rol_organizacion' => $org['rol_organizacion'],
                'organizations' => $organizaciones
            ]
        ];

        $token = \Firebase\JWT\JWT::encode($payload, JWT_SECRET, 'HS256');

        echo json_encode([
            'status' => 'success', 
            'token' => $token,
            'user' => $payload['user']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No perteneces a esta organización']);
    }
    exit;
}

// ── Self-registration via invitation ──────────────────────────
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $nombre   = trim($data['nombre'] ?? '');
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $invite_token = $data['invite_token'] ?? '';

    if (empty($nombre) || empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Nombre, usuario y contraseña son obligatorios']);
        exit;
    }
    if (strlen($password) < 4) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener mínimo 4 caracteres']);
        exit;
    }
    if (empty($invite_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Token de invitación requerido']);
        exit;
    }

    // Validate invitation
    $stmtInv = $pdo->prepare("
        SELECT i.*, o.nombre AS org_nombre
        FROM invitaciones i
        JOIN organizaciones o ON i.organizacion_id = o.id
        WHERE i.token = ? AND i.estado = 'pendiente'
    ");
    $stmtInv->execute([$invite_token]);
    $invitation = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invitation) {
        echo json_encode(['status' => 'error', 'message' => 'Invitación no válida o ya utilizada']);
        exit;
    }
    if ($invitation['fecha_expiracion'] && strtotime($invitation['fecha_expiracion']) < time()) {
        $pdo->prepare("UPDATE invitaciones SET estado = 'expirada' WHERE id = ?")->execute([$invitation['id']]);
        echo json_encode(['status' => 'error', 'message' => 'Esta invitación ha expirado']);
        exit;
    }

    // Check username uniqueness
    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmtCheck->execute([$username]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Ese nombre de usuario ya está ocupado. Intenta con otro.']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        // Create user
        $stmtUser = $pdo->prepare("INSERT INTO usuarios (nombre, username, email, telefono, password, rol, activo) VALUES (?, ?, ?, ?, ?, 'usuario', 1)");
        $stmtUser->execute([
            $nombre,
            $username,
            $invitation['email'] ?: '',
            $invitation['telefono'] ?: '',
            $hashedPassword
        ]);
        $newUserId = (int)$pdo->lastInsertId();

        // Link to organization
        $stmtOrg = $pdo->prepare("INSERT INTO miembros_organizacion (usuario_id, organizacion_id, rol_organizacion) VALUES (?, ?, 'miembro')");
        $stmtOrg->execute([$newUserId, $invitation['organizacion_id']]);

        // Mark invitation as accepted
        $pdo->prepare("UPDATE invitaciones SET estado = 'aceptada' WHERE id = ?")->execute([$invitation['id']]);

        $pdo->commit();

        // Auto-login: generate JWT
        require_once 'vendor/autoload.php';

        $payload = [
            'iat' => time(),
            'exp' => time() + (86400 * 365),
            'user' => [
                'id' => $newUserId,
                'nombre' => $nombre,
                'username' => $username,
                'email' => $invitation['email'] ?: '',
                'rol' => 'usuario',
                'foto' => null,
                'organizacion_id' => $invitation['organizacion_id'],
                'organizacion_nombre' => $invitation['org_nombre'],
                'rol_organizacion' => 'miembro',
                'organizations' => [[
                    'id' => $invitation['organizacion_id'],
                    'nombre' => $invitation['org_nombre'],
                    'rol_organizacion' => 'miembro'
                ]]
            ]
        ];

        $token = \Firebase\JWT\JWT::encode($payload, JWT_SECRET, 'HS256');

        echo json_encode([
            'status' => 'success',
            'message' => '¡Bienvenido! Tu cuenta ha sido creada.',
            'token' => $token,
            'user' => $payload['user']
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);

} catch (Throwable $e) {
    file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] auth.php FATAL: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Auth Error: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()
    ]);
}
?>
