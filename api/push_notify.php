<?php
/**
 * Send a Web Push notification to one or all users.
 * Optionally queues a visible notification in push_notifications (if $title/$body provided).
 * Also used internally to check overdue tasks and send automatic reminders.
 *
 * Accepts:
 *   POST { user_id?: int, title: string, body: string, url?: string }
 *   GET  ?check_overdue=1   (can be called by Windows Task Scheduler / cron)
 */
require_once '../config.php';
require_once __DIR__ . '/WebPush.php';

session_start();
header('Content-Type: application/json');

// Auth: must be admin OR an internal call (no session) from localhost
$isInternalCall = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']);
$isAdmin        = !empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'global_manager']);

if (!$isInternalCall && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Load VAPID keys from ajustes
try {
    $stmt = $pdo->query("SELECT clave, valor FROM ajustes WHERE clave IN ('vapid_private_pem', 'vapid_public_base64')");
    $vapid = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
    exit;
}

if (empty($vapid['vapid_private_pem']) || empty($vapid['vapid_public_base64'])) {
    echo json_encode(['status' => 'error', 'message' => 'VAPID keys not generated. Run /setup_vapid.php first.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── Check overdue tasks (cron / GET) ─────────────────────────────────────────
if ($method === 'GET' && isset($_GET['check_overdue'])) {
    try {
        // Find users with overdue tasks
        $stmt = $pdo->query("
            SELECT DISTINCT t.responsable_id AS user_id, COUNT(*) AS cnt
            FROM tareas t
            WHERE t.estado != 'completado'
              AND t.fecha_termino < CURDATE()
              AND t.responsable_id IS NOT NULL
            GROUP BY t.responsable_id
        ");
        $usersWithOverdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        foreach ($usersWithOverdue as $row) {
            // Queue a notification
            $pdo->prepare("
                INSERT INTO push_notifications (user_id, title, body, url)
                VALUES (?, ?, ?, ?)
            ")->execute([
                $row['user_id'],
                '⚠️ Tareas vencidas',
                "Tienes {$row['cnt']} tarea(s) pendiente(s) vencida(s).",
                '/?overdue=1',
            ]);

            // Send empty push
            $subs = $pdo->prepare("SELECT endpoint FROM push_subscriptions WHERE user_id = ?");
            $subs->execute([$row['user_id']]);
            foreach ($subs->fetchAll(PDO::FETCH_COLUMN) as $endpoint) {
                WebPush::sendEmpty($endpoint, $vapid['vapid_private_pem'], $vapid['vapid_public_base64']);
                $sent++;
            }
        }

        echo json_encode(['status' => 'success', 'users_notified' => count($usersWithOverdue), 'pushes_sent' => $sent]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Manual push from admin (POST) ─────────────────────────────────────────────
if ($method === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $title  = $input['title'] ?? 'TuDu';
    $body   = $input['body']  ?? 'Notificación nueva';
    $url    = $input['url']   ?? '/';
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : null;

    try {
        // Get subscriptions
        if ($userId) {
            $stmt = $pdo->prepare("SELECT endpoint FROM push_subscriptions WHERE user_id = ?");
            $stmt->execute([$userId]);
            // Queue notification
            $pdo->prepare("INSERT INTO push_notifications (user_id, title, body, url) VALUES (?, ?, ?, ?)")
                ->execute([$userId, $title, $body, $url]);
        } else {
            $stmt = $pdo->query("SELECT DISTINCT endpoint FROM push_subscriptions");
            // Queue for all users
            $allUsers = $pdo->query("SELECT DISTINCT user_id FROM push_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allUsers as $uid) {
                $pdo->prepare("INSERT INTO push_notifications (user_id, title, body, url) VALUES (?, ?, ?, ?)")
                    ->execute([$uid, $title, $body, $url]);
            }
        }

        $endpoints = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $sent = 0;
        foreach ($endpoints as $endpoint) {
            if (WebPush::sendEmpty($endpoint, $vapid['vapid_private_pem'], $vapid['vapid_public_base64'])) {
                $sent++;
            }
        }

        echo json_encode(['status' => 'success', 'pushes_sent' => $sent]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>
