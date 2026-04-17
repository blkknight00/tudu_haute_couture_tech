<?php
declare(strict_types=1);
// ═══════════════════════════════════════════════════════════════════════
// TuDu SaaS · Limits Engine
// Equivalente a User.php de RankPilot, adaptado para organizaciones TuDu
// Incluye: límites por plan, WhatsApp por teléfono, guest tokens
// ═══════════════════════════════════════════════════════════════════════

/**
 * Definición de planes TuDu
 * Stand Alone → usuarios individuales o equipos muy pequeños
 * Corp        → empresas con equipos y roles
 */
function tuduPlanDefinitions(): array
{
    return [
        // ── STAND ALONE ───────────────────────────────────────────────
        'standalone_starter' => [
            'edition'          => 'standalone',
            'plan'             => 'starter',
            'label'            => 'Stand Alone Starter',
            'price_mxn'        => 0,       // Gratis con límites
            'members_limit'    => 1,
            'projects_limit'   => 3,
            'tasks_limit'      => 50,
            'storage_limit_mb' => 100,
            'whatsapp_bot'     => false,
            'ai_assistant'     => false,
        ],
        'standalone_pro' => [
            'edition'          => 'standalone',
            'plan'             => 'pro',
            'label'            => 'Stand Alone Pro',
            'price_mxn'        => 19900,   // $199 MXN/mes
            'members_limit'    => 3,
            'projects_limit'   => 10,
            'tasks_limit'      => 200,
            'storage_limit_mb' => 1024,
            'whatsapp_bot'     => true,
            'ai_assistant'     => false,
        ],

        // ── CORP ──────────────────────────────────────────────────────
        'corp_starter' => [
            'edition'          => 'corp',
            'plan'             => 'starter',
            'label'            => 'Corp Starter',
            'price_mxn'        => 39900,   // $399 MXN/mes
            'members_limit'    => 5,
            'projects_limit'   => 10,
            'tasks_limit'      => 300,
            'storage_limit_mb' => 2048,
            'whatsapp_bot'     => true,
            'ai_assistant'     => false,
        ],
        'corp_pro' => [
            'edition'          => 'corp',
            'plan'             => 'pro',
            'label'            => 'Corp Pro',
            'price_mxn'        => 99900,   // $999 MXN/mes
            'members_limit'    => 15,
            'projects_limit'   => 50,
            'tasks_limit'      => 2000,
            'storage_limit_mb' => 10240,
            'whatsapp_bot'     => true,
            'ai_assistant'     => true,
        ],
        'corp_agency' => [
            'edition'          => 'corp',
            'plan'             => 'agency',
            'label'            => 'Corp Agency',
            'price_mxn'        => 249900,  // $2,499 MXN/mes
            'members_limit'    => 50,
            'projects_limit'   => PHP_INT_MAX,
            'tasks_limit'      => PHP_INT_MAX,
            'storage_limit_mb' => 51200,
            'whatsapp_bot'     => true,
            'ai_assistant'     => true,
        ],
        'corp_enterprise' => [
            'edition'          => 'corp',
            'plan'             => 'enterprise',
            'label'            => 'Corp Enterprise',
            'price_mxn'        => 0,       // Cotización directa
            'members_limit'    => PHP_INT_MAX,
            'projects_limit'   => PHP_INT_MAX,
            'tasks_limit'      => PHP_INT_MAX,
            'storage_limit_mb' => PHP_INT_MAX,
            'whatsapp_bot'     => true,
            'ai_assistant'     => true,
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────────
// OBTENER DATOS DE LA ORGANIZACIÓN (con caché en request)
// ─────────────────────────────────────────────────────────────────────
function getOrgData(int $org_id): ?array
{
    global $pdo;
    static $cache = [];

    if (isset($cache[$org_id])) return $cache[$org_id];

    $stmt = $pdo->prepare("
        SELECT id, nombre, edition, plan, plan_status,
               trial_ends_at, plan_renews_at, stripe_subscription_id,
               members_limit, projects_limit, tasks_limit,
               storage_limit_mb, whatsapp_bot
        FROM organizaciones
        WHERE id = ?
    ");
    $stmt->execute([$org_id]);
    $org = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $cache[$org_id] = $org;
    return $org;
}

// ─────────────────────────────────────────────────────────────────────
// ¿EL PLAN ESTÁ ACTIVO?
// ─────────────────────────────────────────────────────────────────────
function hasActivePlan(int $org_id): bool
{
    // Super admin siempre activo
    if (isSuperAdmin()) return true;

    $org = getOrgData($org_id);
    if (!$org) return false;

    // En trial → válido hasta que venza
    if ($org['plan_status'] === 'trialing') {
        if ($org['trial_ends_at'] === null) return true;
        return strtotime($org['trial_ends_at']) > time();
    }

    return in_array($org['plan_status'], ['active', 'lifetime']);
}

// ─────────────────────────────────────────────────────────────────────
// VERIFICAR SI ES SUPER ADMIN
// ─────────────────────────────────────────────────────────────────────
function isSuperAdmin(): bool
{
    return ($_SESSION['usuario_rol'] ?? '') === 'super_admin';
}

function isAdminGlobal(): bool
{
    return in_array($_SESSION['usuario_rol'] ?? '', ['super_admin', 'admin_global']);
}

// ─────────────────────────────────────────────────────────────────────
// ¿PUEDE CREAR MÁS PROYECTOS?
// ─────────────────────────────────────────────────────────────────────
function canCreateProject(int $org_id): array
{
    global $pdo;
    if (isSuperAdmin()) return ['allowed' => true];
    if (!hasActivePlan($org_id)) return ['allowed' => false, 'reason' => 'plan_inactive'];

    $org = getOrgData($org_id);
    if (($org['plan_status'] ?? '') === 'lifetime') return ['allowed' => true];
    
    $limit = (int)($org['projects_limit'] ?? 5);
    if ($limit === PHP_INT_MAX) return ['allowed' => true];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM proyectos WHERE organizacion_id = ?");
    $stmt->execute([$org_id]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $limit) {
        return ['allowed' => false, 'reason' => 'limit_reached', 'used' => $count, 'limit' => $limit];
    }
    return ['allowed' => true, 'used' => $count, 'limit' => $limit];
}

// ─────────────────────────────────────────────────────────────────────
// ¿PUEDE AGREGAR MÁS MIEMBROS?
// ─────────────────────────────────────────────────────────────────────
function canAddMember(int $org_id): array
{
    global $pdo;
    if (isSuperAdmin()) return ['allowed' => true];
    if (!hasActivePlan($org_id)) return ['allowed' => false, 'reason' => 'plan_inactive'];

    $org = getOrgData($org_id);
    if (($org['plan_status'] ?? '') === 'lifetime') return ['allowed' => true];
    
    $limit = (int)($org['members_limit'] ?? 3);
    if ($limit === PHP_INT_MAX) return ['allowed' => true];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM miembros_organizacion mo
        JOIN usuarios u ON mo.usuario_id = u.id
        WHERE mo.organizacion_id = ? AND u.activo = 1
    ");
    $stmt->execute([$org_id]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $limit) {
        return ['allowed' => false, 'reason' => 'limit_reached', 'used' => $count, 'limit' => $limit];
    }
    return ['allowed' => true, 'used' => $count, 'limit' => $limit];
}

// ─────────────────────────────────────────────────────────────────────
// ¿PUEDE CREAR MÁS TAREAS?
// ─────────────────────────────────────────────────────────────────────
function canCreateTask(int $org_id): array
{
    global $pdo;
    if (isSuperAdmin()) return ['allowed' => true];
    if (!hasActivePlan($org_id)) return ['allowed' => false, 'reason' => 'plan_inactive'];

    $org = getOrgData($org_id);
    if (($org['plan_status'] ?? '') === 'lifetime') return ['allowed' => true];
    
    $limit = (int)($org['tasks_limit'] ?? 100);
    if ($limit === PHP_INT_MAX) return ['allowed' => true];

    // Contar tareas activas (excluir archivadas)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tareas t
        JOIN proyectos p ON t.proyecto_id = p.id
        WHERE p.organizacion_id = ? AND t.estado != 'archivado'
    ");
    $stmt->execute([$org_id]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $limit) {
        return ['allowed' => false, 'reason' => 'limit_reached', 'used' => $count, 'limit' => $limit];
    }
    return ['allowed' => true, 'used' => $count, 'limit' => $limit];
}

// ─────────────────────────────────────────────────────────────────────
// ¿TIENE ACCESO AL BOT DE WHATSAPP?
// ─────────────────────────────────────────────────────────────────────
function canUseWhatsAppBot(int $org_id): bool
{
    if (isSuperAdmin()) return true;
    if (!hasActivePlan($org_id)) return false;

    $org = getOrgData($org_id);
    if (($org['plan_status'] ?? '') === 'lifetime') return true;
    
    return (bool)($org['whatsapp_bot'] ?? false);
}

// ─────────────────────────────────────────────────────────────────────
// BUSCAR USUARIO POR TELÉFONO (modelo WhatsApp)
// ─────────────────────────────────────────────────────────────────────
function findUserByPhone(string $phone): ?array
{
    global $pdo;

    // Normalizar: eliminar espacios, guiones, paréntesis
    $normalized = preg_replace('/[^0-9+]/', '', $phone);

    $stmt = $pdo->prepare("
        SELECT id, nombre, username, email, telefono, whatsapp, foto_perfil, activo
        FROM usuarios
        WHERE (telefono = ? OR whatsapp = ? OR telefono = ? OR whatsapp = ?)
        AND activo = 1
        LIMIT 1
    ");
    // Buscar con y sin prefijo +52
    $normalized_mx = preg_replace('/^\+52/', '', $normalized);
    $stmt->execute([$normalized, $normalized, $normalized_mx, $normalized_mx]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ─────────────────────────────────────────────────────────────────────
// GENERAR TOKEN DE GUEST (acceso sin cuenta)
// ─────────────────────────────────────────────────────────────────────
function createGuestToken(int $tarea_id, string $phone, int $created_by, string $nombre = ''): string
{
    global $pdo;

    $token = bin2hex(random_bytes(32)); // 64 chars hex
    $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

    $stmt = $pdo->prepare("
        INSERT INTO guest_access (tarea_id, token, telefono, nombre, acciones, expires_at, created_by)
        VALUES (?, ?, ?, ?, 'view,comment', ?, ?)
    ");
    $stmt->execute([$tarea_id, $token, $phone, $nombre, $expires, $created_by]);
    return $token;
}

// ─────────────────────────────────────────────────────────────────────
// UTILIDAD: Devolver error 403 de límite de plan
// ─────────────────────────────────────────────────────────────────────
function respondPlanLimit(array $check, string $upgrade_url = '/pricing'): void
{
    http_response_code(403);
    $message = match($check['reason'] ?? '') {
        'plan_inactive'   => 'Tu plan no está activo. Actualiza tu suscripción para continuar.',
        'limit_reached'   => "Has alcanzado el límite de tu plan ({$check['limit']} máximo). Actualiza para continuar.",
        default           => 'Límite del plan alcanzado.',
    };
    echo json_encode([
        'status'       => 'error',
        'code'         => 'plan_limit',
        'message'      => $message,
        'upgrade_url'  => $upgrade_url,
        'used'         => $check['used'] ?? null,
        'limit'        => $check['limit'] ?? null,
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────
// RESUMEN DE USO DE LA ORGANIZACIÓN
// ─────────────────────────────────────────────────────────────────────
function getOrgUsageSummary(int $org_id): array
{
    global $pdo;

    $org = getOrgData($org_id);
    if (!$org) return [];

    // Contar miembros
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM miembros_organizacion mo JOIN usuarios u ON mo.usuario_id = u.id WHERE mo.organizacion_id = ? AND u.activo = 1");
    $stmt->execute([$org_id]);
    $members_used = (int)$stmt->fetchColumn();

    // Contar proyectos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM proyectos WHERE organizacion_id = ?");
    $stmt->execute([$org_id]);
    $projects_used = (int)$stmt->fetchColumn();

    // Contar tareas activas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tareas t JOIN proyectos p ON t.proyecto_id = p.id WHERE p.organizacion_id = ? AND t.estado != 'archivado'");
    $stmt->execute([$org_id]);
    $tasks_used = (int)$stmt->fetchColumn();

    $ml = (int)$org['members_limit'];
    $pl = (int)$org['projects_limit'];
    $tl = (int)$org['tasks_limit'];

    return [
        'plan'        => $org['plan'],
        'edition'     => $org['edition'],
        'plan_status' => $org['plan_status'],
        'has_subscription' => !empty($org['stripe_subscription_id']),
        'trial_ends'  => $org['trial_ends_at'],
        'members'     => ['used' => $members_used,  'limit' => $ml === PHP_INT_MAX ? null : $ml,  'pct' => $ml ? min(100, round($members_used / $ml * 100))  : 0],
        'projects'    => ['used' => $projects_used, 'limit' => $pl === PHP_INT_MAX ? null : $pl, 'pct' => $pl ? min(100, round($projects_used / $pl * 100)) : 0],
        'tasks'       => ['used' => $tasks_used,    'limit' => $tl === PHP_INT_MAX ? null : $tl,   'pct' => $tl ? min(100, round($tasks_used / $tl * 100))   : 0],
    ];
}
