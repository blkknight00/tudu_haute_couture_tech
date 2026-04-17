<?php
declare(strict_types=1);

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once 'auth_middleware.php';

$action = $_GET['action'] ?? '';

// ── Public action: validate token (no auth needed) ──
if ($action === 'validate') {
    $token = $_GET['token'] ?? '';
    if (empty($token)) {
        echo json_encode(['status' => 'error', 'message' => 'Token requerido']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT i.id, i.email, i.telefono, i.estado, i.fecha_expiracion,
               o.nombre AS organizacion_nombre
        FROM invitaciones i
        JOIN organizaciones o ON i.organizacion_id = o.id
        WHERE i.token = ?
    ");
    $stmt->execute([$token]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        echo json_encode(['status' => 'error', 'message' => 'Invitación no encontrada']);
        exit;
    }

    if ($inv['estado'] !== 'pendiente') {
        echo json_encode(['status' => 'error', 'message' => 'Esta invitación ya fue utilizada o ha expirado']);
        exit;
    }

    if ($inv['fecha_expiracion'] && strtotime($inv['fecha_expiracion']) < time()) {
        // Mark as expired
        $pdo->prepare("UPDATE invitaciones SET estado = 'expirada' WHERE token = ?")->execute([$token]);
        echo json_encode(['status' => 'error', 'message' => 'Esta invitación ha expirado']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'organizacion_nombre' => $inv['organizacion_nombre'],
            'email' => $inv['email'],
            'telefono' => $inv['telefono']
        ]
    ]);
    exit;
}

// ── Protected actions below ──
$user = checkAuth();
$org_id = (int)($_SESSION['organizacion_id'] ?? 0);
$is_admin = in_array($user['rol'], ['administrador', 'super_admin', 'admin'])
            || ($_SESSION['rol_organizacion'] ?? '') === 'admin';

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Solo administradores pueden gestionar invitaciones']);
    exit;
}

try {
    switch ($action) {

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');

            $data = json_decode(file_get_contents("php://input"), true);
            $email = trim($data['email'] ?? '');
            $telefono = trim($data['telefono'] ?? '');

            if (empty($email) && empty($telefono)) {
                throw new Exception('Debes proporcionar un email o teléfono');
            }

            // Check SaaS member limit
            if (function_exists('canAddMember') && !authIsSuperAdmin()) {
                require_once 'saas_limits.php';
                $memberCheck = canAddMember($org_id);
                if (!$memberCheck['allowed']) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => $memberCheck['message'] ?? 'Has alcanzado el límite de miembros de tu plan',
                        'upgrade' => true
                    ]);
                    exit;
                }
            }

            // Check if invitation already exists and is pending
            $checkSql = "SELECT id FROM invitaciones WHERE organizacion_id = ? AND estado = 'pendiente'";
            $checkParams = [$org_id];
            if ($email) {
                $checkSql .= " AND email = ?";
                $checkParams[] = $email;
            } else {
                $checkSql .= " AND telefono = ?";
                $checkParams[] = $telefono;
            }
            $stmtCheck = $pdo->prepare($checkSql);
            $stmtCheck->execute($checkParams);
            if ($stmtCheck->fetch()) {
                throw new Exception('Ya existe una invitación pendiente para este contacto');
            }

            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expDate = date('Y-m-d H:i:s', strtotime('+7 days'));

            $stmt = $pdo->prepare("
                INSERT INTO invitaciones (organizacion_id, invitado_por, email, telefono, token, fecha_expiracion)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$org_id, $user['id'], $email ?: null, $telefono ?: null, $token, $expDate]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Invitación creada',
                'data' => [
                    'token' => $token,
                    'expires' => $expDate
                ]
            ]);
            break;

        case 'list':
            $stmt = $pdo->prepare("
                SELECT i.id, i.email, i.telefono, i.token, i.estado, i.fecha_creacion, i.fecha_expiracion,
                       u.nombre AS invitado_por_nombre
                FROM invitaciones i
                JOIN usuarios u ON i.invitado_por = u.id
                WHERE i.organizacion_id = ?
                ORDER BY i.fecha_creacion DESC
            ");
            $stmt->execute([$org_id]);
            $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Auto-expire stale invitations
            foreach ($invitations as &$inv) {
                if ($inv['estado'] === 'pendiente' && $inv['fecha_expiracion'] && strtotime($inv['fecha_expiracion']) < time()) {
                    $inv['estado'] = 'expirada';
                    $pdo->prepare("UPDATE invitaciones SET estado = 'expirada' WHERE id = ?")->execute([$inv['id']]);
                }
            }

            echo json_encode(['status' => 'success', 'data' => $invitations]);
            break;

        case 'revoke':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');

            $data = json_decode(file_get_contents("php://input"), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) throw new Exception('ID requerido');

            $stmt = $pdo->prepare("UPDATE invitaciones SET estado = 'expirada' WHERE id = ? AND organizacion_id = ?");
            $stmt->execute([$id, $org_id]);

            echo json_encode(['status' => 'success', 'message' => 'Invitación revocada']);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
