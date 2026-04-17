<?php
declare(strict_types=1);
// ═══════════════════════════════════════════════════════════════════════
// TuDu SaaS · Setup Super Admins
// Ejecutar UNA SOLA VEZ: http://localhost/tudu_haute_couture_tech/setup_superadmins.php
// ELIMINAR este archivo después de ejecutarlo en producción.
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/config.php';

// ── Protección básica: solo desde localhost ───────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$allowed = in_array($ip, ['127.0.0.1', '::1', 'localhost']);
if (!$allowed && !isset($_GET['force'])) {
    http_response_code(403);
    die('<h2>⛔ Solo accesible desde localhost</h2>');
}

$results = [];
$errors  = [];

// ── Definición de Super Admins ────────────────────────────────────────
$superAdmins = [
    [
        'nombre'    => 'Oscar García',
        'username'  => 'ogarcia',
        'email'     => 'ogarcia@interdata.mx',
        'password'  => 'G@lapago50:)',
        'telefono'  => '',
        'whatsapp'  => '',
    ],
    [
        'nombre'    => 'Eduardo',
        'username'  => 'blkknight00',
        'email'     => 'blkknight00@gmail.com',
        'password'  => 'Svetlana25.09',
        'telefono'  => '',
        'whatsapp'  => '',
    ],
];

try {
    $pdo->beginTransaction();

    // ── 1. Crear organización "TuDu Platform" para super admins ──────
    $stmt = $pdo->prepare("SELECT id FROM organizaciones WHERE nombre = 'TuDu Platform' LIMIT 1");
    $stmt->execute();
    $platformOrg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$platformOrg) {
        $stmt = $pdo->prepare("
            INSERT INTO organizaciones
                (nombre, edition, plan, plan_status, trial_ends_at, members_limit, projects_limit,
                 tasks_limit, storage_limit_mb, whatsapp_bot)
            VALUES
                ('TuDu Platform', 'corp', 'enterprise', 'active', NULL, 999999, 999999, 999999, 999999, 1)
        ");
        $stmt->execute();
        $platformOrgId = (int)$pdo->lastInsertId();
        $results[] = "✅ Organización 'TuDu Platform' creada (ID: $platformOrgId)";
    } else {
        $platformOrgId = (int)$platformOrg['id'];
        // Actualizar a enterprise/active por si acaso
        $pdo->prepare("
            UPDATE organizaciones SET
                edition = 'corp', plan = 'enterprise', plan_status = 'active',
                trial_ends_at = NULL, members_limit = 999999, projects_limit = 999999,
                tasks_limit = 999999, storage_limit_mb = 999999, whatsapp_bot = 1
            WHERE id = ?
        ")->execute([$platformOrgId]);
        $results[] = "✅ Organización 'TuDu Platform' existente actualizada a Enterprise (ID: $platformOrgId)";
    }

    // ── 2. Crear / actualizar cada super admin ────────────────────────
    foreach ($superAdmins as $admin) {
        $hashedPassword = password_hash($admin['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        // Verificar si ya existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([$admin['email'], $admin['username']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Actualizar: siempre forzar rol y contraseña
            $pdo->prepare("
                UPDATE usuarios SET
                    nombre    = ?,
                    password  = ?,
                    rol       = 'super_admin',
                    activo    = 1,
                    email     = ?,
                    username  = ?
                WHERE id = ?
            ")->execute([
                $admin['nombre'],
                $hashedPassword,
                $admin['email'],
                $admin['username'],
                $existing['id'],
            ]);
            $userId = (int)$existing['id'];
            $results[] = "✅ Super Admin '{$admin['nombre']}' actualizado (ID: $userId)";
        } else {
            // Crear nuevo
            $pdo->prepare("
                INSERT INTO usuarios
                    (nombre, username, email, telefono, whatsapp, rol, password, activo)
                VALUES
                    (?, ?, ?, ?, ?, 'super_admin', ?, 1)
            ")->execute([
                $admin['nombre'],
                $admin['username'],
                $admin['email'],
                $admin['telefono'],
                $admin['whatsapp'],
                $hashedPassword,
            ]);
            $userId = (int)$pdo->lastInsertId();
            $results[] = "✅ Super Admin '{$admin['nombre']}' creado (ID: $userId)";
        }

        // ── 3. Asociar a organización TuDu Platform ──────────────────
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM miembros_organizacion
            WHERE usuario_id = ? AND organizacion_id = ?
        ");
        $stmt->execute([$userId, $platformOrgId]);

        if (!$stmt->fetch()) {
            $pdo->prepare("
                INSERT INTO miembros_organizacion
                    (usuario_id, organizacion_id, rol_organizacion)
                VALUES
                    (?, ?, 'admin')
            ")->execute([$userId, $platformOrgId]);
            $results[] = "   └── Asignado a org 'TuDu Platform'";
        } else {
            $results[] = "   └── Ya estaba en org 'TuDu Platform'";
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    $errors[] = "❌ ERROR: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TuDu · Setup Super Admins</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #09090b;
            color: #e4e4e7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #18181b;
            border: 1px solid #27272a;
            border-radius: 16px;
            padding: 32px;
            max-width: 560px;
            width: 100%;
        }
        .logo { font-size: 32px; margin-bottom: 8px; }
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 4px; }
        .sub { font-size: 13px; color: #71717a; margin-bottom: 24px; }
        .result-list { list-style: none; display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px; }
        .result-list li {
            font-size: 13px;
            padding: 10px 14px;
            border-radius: 8px;
            line-height: 1.5;
        }
        .result-list li.ok  { background: rgba(5,150,105,0.1);  color: #34d399; border: 1px solid rgba(5,150,105,0.2); }
        .result-list li.err { background: rgba(220,38,38,0.1);  color: #f87171; border: 1px solid rgba(220,38,38,0.2); }
        .warning {
            background: rgba(217,119,6,0.1);
            border: 1px solid rgba(217,119,6,0.25);
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 12px;
            color: #fbbf24;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background: #7c3aed;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
        }
        .users { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        .user-card {
            background: #09090b;
            border: 1px solid #27272a;
            border-radius: 10px;
            padding: 14px;
        }
        .user-card .avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .user-card .name { font-size: 13px; font-weight: 600; }
        .user-card .role { font-size: 11px; color: #a78bfa; }
        .user-card .badge {
            display: inline-block;
            background: rgba(5,150,105,0.15);
            color: #34d399;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 4px;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">👑</div>
    <h1>TuDu · Super Admin Setup</h1>
    <div class="sub">Configuración inicial de cuentas administradoras</div>

    <?php if (!empty($errors)): ?>
        <ul class="result-list">
            <?php foreach ($errors as $e): ?>
                <li class="err"><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <ul class="result-list">
            <?php foreach ($results as $r): ?>
                <li class="ok"><?= htmlspecialchars($r) ?></li>
            <?php endforeach; ?>
        </ul>

        <div class="users">
            <div class="user-card">
                <div class="avatar">OG</div>
                <div class="name">Oscar García</div>
                <div class="role">Super Admin · InterData</div>
                <div class="badge">♾️ Enterprise · Gratis de por vida</div>
            </div>
            <div class="user-card">
                <div class="avatar">E</div>
                <div class="name">Eduardo</div>
                <div class="role">Super Admin · TuDu</div>
                <div class="badge">♾️ Enterprise · Gratis de por vida</div>
            </div>
        </div>
    <?php endif; ?>

    <div class="warning">
        ⚠️ <strong>Seguridad:</strong> Elimina este archivo del servidor de producción después de ejecutarlo.<br>
        <code>rm setup_superadmins.php</code>
    </div>

    <?php if (empty($errors)): ?>
        <a class="btn" href="/tudu_haute_couture_tech/">→ Ir a TuDu</a>
    <?php endif; ?>
</div>
</body>
</html>
