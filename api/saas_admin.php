<?php
declare(strict_types=1);
// ═══════════════════════════════════════════════════════════════════════
// TuDu SaaS · Admin Panel API
// Equivalente a AdminController.php de RankPilot, adaptado para TuDu
// Solo accesible por: super_admin
// ═══════════════════════════════════════════════════════════════════════

require_once '../config.php';
require_once 'auth_middleware.php';
require_once 'saas_limits.php';

header("Content-Type: application/json; charset=UTF-8");

$user = checkAuth();
if (!authIsSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Solo Super Admin.']);
    exit;
}

$action = $_GET['action'] ?? 'metrics';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ── GET /saas_admin.php?action=metrics ───────────────────────
        case 'metrics':
            // Conteos globales
            $totalOrgs = (int)$pdo->query("SELECT COUNT(*) FROM organizaciones")->fetchColumn();

            $activeOrgs = (int)$pdo->query("
                SELECT COUNT(*) FROM organizaciones
                WHERE plan_status IN ('active', 'lifetime')
            ")->fetchColumn();

            $pastDueOrgs = (int)$pdo->query("
                SELECT COUNT(*) FROM organizaciones WHERE plan_status = 'past_due'
            ")->fetchColumn();

            $trialOrgs = (int)$pdo->query("
                SELECT COUNT(*) FROM organizaciones WHERE plan_status = 'trialing'
            ")->fetchColumn();

            $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();

            $totalProjects = (int)$pdo->query("SELECT COUNT(*) FROM proyectos")->fetchColumn();

            $totalTasks = (int)$pdo->query("
                SELECT COUNT(*) FROM tareas WHERE estado != 'archivado'
            ")->fetchColumn();

            $tasksMonth = (int)$pdo->query("
                SELECT COUNT(*) FROM tareas
                WHERE fecha_creacion >= DATE_FORMAT(NOW(),'%Y-%m-01')
            ")->fetchColumn();

            // MRR estimado (en centavos MXN → mostrar en pesos)
            $planPrices = ['starter' => 399, 'pro' => 999, 'agency' => 2499, 'enterprise' => 0];
            $orgsWithPlan = $pdo->query("
                SELECT plan, COUNT(*) as cnt FROM organizaciones
                WHERE plan_status = 'active' GROUP BY plan
            ")->fetchAll(PDO::FETCH_ASSOC);

            $mrr = 0;
            foreach ($orgsWithPlan as $row) {
                $mrr += ($planPrices[$row['plan']] ?? 0) * (int)$row['cnt'];
            }

            // WhatsApp messages este mes
            $waMessages = 0;
            try {
                $res = $pdo->query("
                    SELECT COUNT(*) FROM whatsapp_messages
                    WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
                ");
                if ($res) $waMessages = (int)$res->fetchColumn();
            } catch (Throwable $e) { /* tabla puede no existir aún */ }

            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'total_orgs'    => $totalOrgs,
                    'active_orgs'   => $activeOrgs,
                    'trial_orgs'    => $trialOrgs,
                    'past_due_orgs' => $pastDueOrgs,
                    'total_users'   => $totalUsers,
                    'total_projects'=> $totalProjects,
                    'total_tasks'   => $totalTasks,
                    'tasks_month'   => $tasksMonth,
                    'mrr_mxn'       => $mrr,
                    'wa_messages_month' => $waMessages,
                ],
            ]);
            break;


        // ── GET /saas_admin.php?action=tenants ───────────────────────
        case 'tenants':
            $search  = $_GET['q']      ?? '';
            $plan    = $_GET['plan']   ?? '';
            $status  = $_GET['status'] ?? '';
            $edition = $_GET['edition']?? '';

            $where  = ["1=1"];
            $params = [];

            if ($search) {
                $where[] = "o.nombre LIKE ?";
                $params[] = "%$search%";
            }
            if ($plan)    { $where[] = "o.plan = ?";         $params[] = $plan; }
            if ($status)  { $where[] = "o.plan_status = ?";  $params[] = $status; }
            if ($edition) { $where[] = "o.edition = ?";      $params[] = $edition; }

            $whereSQL = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT
                    o.id, o.nombre, o.edition, o.plan, o.plan_status,
                    o.trial_ends_at, o.plan_renews_at,
                    o.members_limit, o.projects_limit, o.tasks_limit,
                    o.whatsapp_bot, o.created_at,
                    COUNT(DISTINCT mo.usuario_id) as members_count,
                    COUNT(DISTINCT p.id) as projects_count,
                    COUNT(DISTINCT t.id) as tasks_count
                FROM organizaciones o
                LEFT JOIN miembros_organizacion mo ON o.id = mo.organizacion_id
                LEFT JOIN proyectos p ON o.id = p.organizacion_id
                LEFT JOIN tareas t ON p.id = t.proyecto_id AND t.estado != 'archivado'
                WHERE $whereSQL
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT 100
            ");
            $stmt->execute($params);
            $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $tenants]);
            break;


        // ── GET /saas_admin.php?action=tenant_detail&id=X ────────────
        case 'tenant_detail':
            $org_id = (int)($_GET['id'] ?? 0);
            if (!$org_id) throw new Exception('ID de organización requerido');

            // Datos de la org
            $stmt = $pdo->prepare("SELECT * FROM organizaciones WHERE id = ?");
            $stmt->execute([$org_id]);
            $org = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$org) throw new Exception('Organización no encontrada');

            // Miembros
            $stmt = $pdo->prepare("
                SELECT u.id, u.nombre, u.username, u.email, u.telefono, u.whatsapp, u.rol, u.activo, mo.rol_organizacion
                FROM usuarios u
                JOIN miembros_organizacion mo ON u.id = mo.usuario_id
                WHERE mo.organizacion_id = ?
                ORDER BY u.nombre ASC
            ");
            $stmt->execute([$org_id]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Uso actual
            $usage = getOrgUsageSummary($org_id);

            // Historial de suscripciones
            $subscriptions = [];
            try {
                $stmt2 = $pdo->prepare("SELECT * FROM subscripciones WHERE organizacion_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt2->execute([$org_id]);
                $subscriptions = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}

            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'org'           => $org,
                    'members'       => $members,
                    'usage'         => $usage,
                    'subscriptions' => $subscriptions,
                ],
            ]);
            break;


        // ── POST /saas_admin.php?action=update_tenant ─────────────────
        case 'update_tenant':
            if ($method !== 'POST') throw new Exception('Método no permitido');

            $input   = json_decode(file_get_contents('php://input'), true) ?? [];
            $org_id  = (int)($input['id'] ?? 0);
            if (!$org_id) throw new Exception('ID de organización requerido');

            $allowedPlans    = ['starter', 'pro', 'agency', 'enterprise'];
            $allowedStatuses = ['active', 'trialing', 'past_due', 'cancelled', 'inactive', 'lifetime'];
            $allowedEditions = ['standalone', 'corp'];

            $fields  = [];
            $params  = [];

            if (isset($input['plan']) && in_array($input['plan'], $allowedPlans)) {
                $fields[] = 'plan = ?';
                $params[] = $input['plan'];
                // Actualizar límites automáticamente según el plan
                $planDefs = tuduPlanDefinitions();
                $key      = ($input['edition'] ?? 'corp') . '_' . $input['plan'];
                if (isset($planDefs[$key])) {
                    $def = $planDefs[$key];
                    $fields[] = 'members_limit = ?';   $params[] = min($def['members_limit'],   PHP_INT_MAX);
                    $fields[] = 'projects_limit = ?';  $params[] = min($def['projects_limit'],  PHP_INT_MAX);
                    $fields[] = 'tasks_limit = ?';     $params[] = min($def['tasks_limit'],     PHP_INT_MAX);
                    $fields[] = 'storage_limit_mb = ?';$params[] = min($def['storage_limit_mb'],PHP_INT_MAX);
                }
            }

            if (isset($input['plan_status']) && in_array($input['plan_status'], $allowedStatuses)) {
                $fields[] = 'plan_status = ?'; $params[] = $input['plan_status'];
            }

            if (isset($input['edition']) && in_array($input['edition'], $allowedEditions)) {
                $fields[] = 'edition = ?'; $params[] = $input['edition'];
            }

            // Sobreescritura manual de límites (super admin puede personalizar)
            if (isset($input['members_limit']))  { $fields[] = 'members_limit = ?';  $params[] = (int)$input['members_limit']; }
            if (isset($input['projects_limit'])) { $fields[] = 'projects_limit = ?'; $params[] = (int)$input['projects_limit']; }
            if (isset($input['tasks_limit']))    { $fields[] = 'tasks_limit = ?';    $params[] = (int)$input['tasks_limit']; }
            if (isset($input['whatsapp_bot']))   { $fields[] = 'whatsapp_bot = ?';   $params[] = $input['whatsapp_bot'] ? 1 : 0; }

            // Extensión de trial
            if (isset($input['trial_days'])) {
                $fields[] = 'trial_ends_at = DATE_ADD(NOW(), INTERVAL ? DAY)';
                $params[]  = max(1, (int)$input['trial_days']);
            }

            if (empty($fields)) throw new Exception('Sin cambios que aplicar');

            $params[] = $org_id;
            $stmt = $pdo->prepare("UPDATE organizaciones SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($params);

            // Log auditoría
            registrarAuditoria($user['id'], 'SAAS_TENANT_UPDATE', 'organizaciones', $org_id, json_encode($input));

            echo json_encode(['status' => 'success', 'message' => 'Organización actualizada']);
            break;


        // ── GET /saas_admin.php?action=settings ──────────────────────
        case 'settings':
            $defs = [
                ['key' => 'TWILIO_ACCOUNT_SID',    'icon' => '📲', 'label' => 'Twilio Account SID',    'group' => 'whatsapp'],
                ['key' => 'TWILIO_AUTH_TOKEN',      'icon' => '📲', 'label' => 'Twilio Auth Token',     'group' => 'whatsapp'],
                ['key' => 'TWILIO_WHATSAPP_FROM',   'icon' => '📲', 'label' => 'Twilio WhatsApp From',  'group' => 'whatsapp'],
                ['key' => 'STRIPE_SECRET_KEY',      'icon' => '💳', 'label' => 'Stripe Secret Key',     'group' => 'payments'],
                ['key' => 'STRIPE_PUBLISHABLE_KEY', 'icon' => '💳', 'label' => 'Stripe Publishable Key','group' => 'payments'],
                ['key' => 'STRIPE_WEBHOOK_SECRET',  'icon' => '💳', 'label' => 'Stripe Webhook Secret', 'group' => 'payments'],
                ['key' => 'CONEKTA_SECRET_KEY',     'icon' => '🏪', 'label' => 'Conekta Secret Key',    'group' => 'payments'],
                ['key' => 'CONEKTA_PUBLIC_KEY',     'icon' => '🏪', 'label' => 'Conekta Public Key',    'group' => 'payments'],
                ['key' => 'APP_NAME',               'icon' => '⚙️', 'label' => 'Nombre de la App',      'group' => 'general'],
                ['key' => 'APP_URL',                'icon' => '⚙️', 'label' => 'URL de la App',         'group' => 'general'],
            ];

            // Cargar valores guardados
            $saved = [];
            try {
                $rows = $pdo->query("SELECT `key`, `value` FROM system_settings")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) $saved[$r['key']] = $r['value'];
            } catch (Exception $e) {}

            $settings = array_map(function ($def) use ($saved) {
                $raw = $saved[$def['key']] ?? '';
                return array_merge($def, [
                    'set'   => !empty($raw),
                    // Mostrar solo últimos 4 chars si está configurado
                    'preview' => !empty($raw) ? '••••' . substr(preg_replace('/^enc:/', '', $raw), -4) : '',
                ]);
            }, $defs);

            echo json_encode(['status' => 'success', 'data' => $settings]);
            break;


        // ── POST /saas_admin.php?action=save_setting ──────────────────
        case 'save_setting':
            if ($method !== 'POST') throw new Exception('Método no permitido');

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $key   = trim($input['key']   ?? '');
            $value = trim($input['value'] ?? '');

            if (empty($key) || empty($value)) throw new Exception('Clave y valor requeridos');

            // Encriptar con AES-256 antes de guardar
            $encKey    = substr(hash('sha256', APP_URL . 'tudu-saas'), 0, 32);
            $iv        = openssl_random_pseudo_bytes(16);
            $encrypted = 'enc:' . base64_encode($iv . openssl_encrypt($value, 'AES-256-CBC', $encKey, 0, $iv));

            $stmt = $pdo->prepare("
                INSERT INTO system_settings (`key`, `value`, updated_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by), updated_at = NOW()
            ");
            $stmt->execute([$key, $encrypted, $user['id']]);

            registrarAuditoria($user['id'], 'SAAS_SETTING_UPDATE', 'system_settings', 0, "key=$key");
            echo json_encode(['status' => 'success', 'message' => "Configuración '$key' guardada"]);
            break;


        // ── GET /saas_admin.php?action=wa_logs ───────────────────────
        case 'wa_logs':
            $org_id = (int)($_GET['org_id'] ?? 0);
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $params = [];
            $where  = '1=1';

            if ($org_id) { $where .= ' AND wm.organizacion_id = ?'; $params[] = $org_id; }

            try {
                $stmt = $pdo->prepare("
                    SELECT wm.*, u.nombre as usuario_nombre, t.titulo as tarea_titulo
                    FROM whatsapp_messages wm
                    LEFT JOIN usuarios u ON wm.usuario_id = u.id
                    LEFT JOIN tareas t ON wm.tarea_id = t.id
                    WHERE $where
                    ORDER BY wm.created_at DESC
                    LIMIT $limit
                ");
                $stmt->execute($params);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $logs]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'success', 'data' => [], 'note' => 'Tabla aún no creada']);
            }
            break;


        default:
            throw new Exception("Acción '$action' no reconocida");
    }

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
