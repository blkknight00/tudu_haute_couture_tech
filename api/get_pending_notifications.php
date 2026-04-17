<?php
/**
 * Returns unread push notifications for the current user.
 * Called by the Service Worker when it receives a push event (empty ping).
 * Also returns the count for the notification badge.
 */
require_once '../config.php';
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['usuario_id'])) {
    // Not logged in — return generic notification
    echo json_encode([
        'status'        => 'success',
        'notifications' => [[
            'title' => 'TuDu',
            'body'  => 'Tienes actualizaciones en tu cuenta.',
            'url'   => '/',
        ]],
    ]);
    exit;
}

$userId = (int) $_SESSION['usuario_id'];

try {
    // Check if table exists first
    $check = $pdo->query("SHOW TABLES LIKE 'push_notifications'")->fetchColumn();
    if (!$check) {
        echo json_encode(['status' => 'success', 'notifications' => []]);
        exit;
    }

    // Fetch unread notifications for this user
    $stmt = $pdo->prepare("
        SELECT id, title, body, url, created_at
        FROM push_notifications
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark them as read
    if (!empty($notifications)) {
        $ids = implode(',', array_column($notifications, 'id'));
        $pdo->exec("UPDATE push_notifications SET is_read = 1 WHERE id IN ($ids)");
    }

    // Also check for overdue tasks and add them
    if (empty($notifications)) {
        $stmt2 = $pdo->prepare("
            SELECT t.titulo, t.fecha_termino
            FROM tareas t
            WHERE t.responsable_id = ?
              AND t.estado != 'completado'
              AND t.fecha_termino < CURDATE()
            ORDER BY t.fecha_termino ASC
            LIMIT 3
        ");
        $stmt2->execute([$userId]);
        $overdue = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        foreach ($overdue as $task) {
            $notifications[] = [
                'title' => '⚠️ Tarea vencida',
                'body'  => $task['titulo'],
                'url'   => '/?overdue=1',
            ];
        }
    }

    if (empty($notifications)) {
        $notifications[] = [
            'title' => 'TuDu',
            'body'  => 'Tienes actualizaciones pendientes.',
            'url'   => '/',
        ];
    }

    echo json_encode([
        'status'        => 'success',
        'notifications' => $notifications,
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status'        => 'success',
        'notifications' => [['title' => 'TuDu', 'body' => 'Revisa tus tareas.', 'url' => '/']],
    ]);
}
?>
