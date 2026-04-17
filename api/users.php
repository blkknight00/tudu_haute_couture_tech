<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
require_once 'auth_middleware.php';
require_once 'saas_limits.php';

header("Content-Type: application/json; charset=UTF-8");

// Ensure only global admins or workspace admins can access
$user = checkAuth();
$is_global_admin = in_array($user['rol'], ['administrador', 'super_admin', 'admin_global']);
$is_workspace_admin = ($user['rol_organizacion'] === 'admin');

if (!$is_global_admin && !$is_workspace_admin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit;
}

// Log for debug (can be removed later)
error_log("Accessing users.php - User role: " . ($user['rol'] ?? 'undefined'));

$action = $_GET['action'] ?? '';
$org_id = $_SESSION['organizacion_id'] ?? null;
$is_global = $is_global_admin; // Alias para lógica heredada
$is_super_admin = ($user['rol'] === 'super_admin'); // specifically for platform settings later if needed

try {
    switch ($action) {
        case 'list':
            $sql = "SELECT u.id, u.username, u.nombre, u.email, u.rol, u.activo, u.foto_perfil, u.telefono,
                           o.id as organizacion_id, o.nombre as organizacion_nombre
                    FROM usuarios u
                    LEFT JOIN miembros_organizacion mo ON u.id = mo.usuario_id
                    LEFT JOIN organizaciones o ON mo.organizacion_id = o.id";
            
            $params = [];
            if (!$is_global) {
                $sql .= " WHERE mo.organizacion_id = ?";
                $params[] = $org_id;
            }
            
            $sql .= " ORDER BY u.nombre ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $users]);
            break;

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
            
            $nombre = $_POST['nombre'] ?? '';
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $raw_rol = $_POST['rol'] ?? 'miembro';
            $password = $_POST['password'] ?? '123456';
            $target_org_id = $_POST['organizacion_id'] ?? $org_id;

            // Whitelist: sanitize rol to only known ENUM values in DB
            $allowed_roles = ['miembro', 'administrador', 'invitado', 'super_admin', 'admin_global'];
            $rol = in_array($raw_rol, $allowed_roles) ? $raw_rol : 'miembro';

            if (empty($nombre) || empty($username)) {
                throw new Exception('Nombre y Usuario son obligatorios');
            }

            // Check if username exists
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetch()) {
                throw new Exception('El nombre de usuario ya existe');
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // ── SaaS: verificar límite de miembros del plan ───────────
            if (!authIsSuperAdmin() && $target_org_id) {
                $memberCheck = canAddMember((int)$target_org_id);
                if (!$memberCheck['allowed']) {
                    respondPlanLimit($memberCheck, '/pricing');
                }
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, username, email, telefono, rol, password, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$nombre, $username, $email, $telefono, $rol, $hashedPassword]);
                $new_user_id = $pdo->lastInsertId();

                // Assign to organization
                if ($target_org_id) {
                    $stmtOrg = $pdo->prepare("INSERT INTO miembros_organizacion (usuario_id, organizacion_id, rol_organizacion) VALUES (?, ?, ?)");
                    $stmtOrg->execute([$new_user_id, $target_org_id, ($rol === 'administrador' ? 'admin' : 'miembro')]);
                }

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Usuario creado correctamente']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
            
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception('ID de usuario requerido');

            $nombre = $_POST['nombre'] ?? '';
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $raw_rol = $_POST['rol'] ?? 'miembro';
            $activo = $_POST['activo'] ?? 1;
            $target_org_id = $_POST['organizacion_id'] ?? null;

            // Whitelist: sanitize rol
            $allowed_roles = ['miembro', 'administrador', 'invitado', 'super_admin', 'admin_global'];
            $rol = in_array($raw_rol, $allowed_roles) ? $raw_rol : 'miembro';

            if (empty($nombre) || empty($username)) {
                throw new Exception('Nombre y Usuario son obligatorios');
            }

            // Ownership check
            if (!$is_global) {
                $stmtAuth = $pdo->prepare("SELECT COUNT(*) FROM miembros_organizacion WHERE usuario_id = ? AND organizacion_id = ?");
                $stmtAuth->execute([$id, $org_id]);
                if ($stmtAuth->fetchColumn() == 0) throw new Exception('No tienes permiso para modificar este usuario');
            }

            // Check if username exists for OTHER user
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
            $stmtCheck->execute([$username, $id]);
            if ($stmtCheck->fetch()) {
                throw new Exception('El nombre de usuario ya existe');
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, username = ?, email = ?, telefono = ?, rol = ?, activo = ? WHERE id = ?");
                $stmt->execute([$nombre, $username, $email, $telefono, $rol, $activo, $id]);

                // Update organization assignment if global admin
                if ($is_global && $target_org_id) {
                    // Check if already in an org
                    $stmtCheckOrg = $pdo->prepare("SELECT COUNT(*) FROM miembros_organizacion WHERE usuario_id = ?");
                    $stmtCheckOrg->execute([$id]);
                    if ($stmtCheckOrg->fetchColumn() > 0) {
                        $stmtUpdateOrg = $pdo->prepare("UPDATE miembros_organizacion SET organizacion_id = ? WHERE usuario_id = ?");
                        $stmtUpdateOrg->execute([$target_org_id, $id]);
                    } else {
                        $stmtInsertOrg = $pdo->prepare("INSERT INTO miembros_organizacion (usuario_id, organizacion_id, rol_organizacion) VALUES (?, ?, ?)");
                        $stmtInsertOrg->execute([$id, $target_org_id, ($rol === 'administrador' ? 'admin' : 'miembro')]);
                    }
                }

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Usuario actualizado correctamente']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'reset_password':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
            
            $id = $_POST['id'] ?? null;
            $password = $_POST['password'] ?? '123456';

            if (!$id) throw new Exception('ID de usuario requerido');

            // Ownership check
            if (!$is_global) {
                $stmtAuth = $pdo->prepare("SELECT COUNT(*) FROM miembros_organizacion WHERE usuario_id = ? AND organizacion_id = ?");
                $stmtAuth->execute([$id, $org_id]);
                if ($stmtAuth->fetchColumn() == 0) throw new Exception('No tienes permiso para modificar este usuario');
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $id]);

            echo json_encode(['status' => 'success', 'message' => 'Contraseña restablecida correctamente']);
            break;

        case 'delete':
            // Soft delete
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception('ID de usuario requerido');

            // Ownership check
            if (!$is_global) {
                $stmtAuth = $pdo->prepare("SELECT COUNT(*) FROM miembros_organizacion WHERE usuario_id = ? AND organizacion_id = ?");
                $stmtAuth->execute([$id, $org_id]);
                if ($stmtAuth->fetchColumn() == 0) throw new Exception('No tienes permiso para modificar este usuario');
            }

            $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['status' => 'success', 'message' => 'Usuario desactivado correctamente']);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
