<?php
require_once '../config.php';
// session_start(); // En config.php

error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display to prevent JSON corruption
header('Content-Type: application/json');

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$action = $_GET['action'] ?? 'get_profile';

try {
    // --- GET PROFILE ---
    if ($action === 'get_profile') {
        $stmt = $pdo->prepare("SELECT id, nombre, username, rol, foto_perfil, telefono, fecha_creacion FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        echo json_encode(['status' => 'success', 'data' => $user]);
    }

    // --- UPDATE PROFILE ---
    elseif ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle Multipart/Form-data (files) or JSON
        $input = $_POST;
        if (empty($input)) {
            $json = json_decode(file_get_contents('php://input'), true);
            if ($json) $input = $json;
        }

        $nombre = $input['nombre'] ?? '';
        $username = $input['username'] ?? ''; // Optional: Allow chaging username?
        $telefono = $input['telefono'] ?? '';
        $current_password = $input['current_password'] ?? '';
        $new_password = $input['new_password'] ?? '';

        if (empty($nombre) || empty($username)) {
            throw new Exception("Nombre y Usuario son obligatorios");
        }

        // 1. Verify Username Uniqueness (if changed)
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
        $stmtCheck->execute([$username, $usuario_id]);
        if ($stmtCheck->fetchColumn()) {
            throw new Exception("El nombre de usuario ya está en uso.");
        }

        // 2. Handle Password Change
        $password_update_sql = "";
        $params = [$nombre, $username, $telefono];

        if (!empty($new_password)) {
            if (empty($current_password)) {
                throw new Exception("Debes ingresar tu contraseña actual para cambiarla.");
            }
            // Verify current
            $stmtPass = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmtPass->execute([$usuario_id]);
            $hash = $stmtPass->fetchColumn();

            if (!password_verify($current_password, $hash)) {
                throw new Exception("La contraseña actual es incorrecta.");
            }

            $password_update_sql = ", password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        // 3. Handle File Upload (Foto Perfil)
        $foto_update_sql = "";
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_perfil'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Formato de imagen no válido (solo jpg, png, gif, webp).");
            }

            $uploadDir = '../uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'user_' . $usuario_id . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Return relative path. Assuming public root is the parent of 'api'
                // We want a path that works for the frontend.
                // If frontend is 'localhost/tudu_development/', then 'uploads/profiles/...' should work.
                
                $webPath = 'uploads/profiles/' . $filename;
                $foto_update_sql = ", foto_perfil = ?";
                $params[] = $webPath;

                // Update Session immediately
                $_SESSION['usuario_foto'] = $webPath;
            } else {
                throw new Exception("Error al guardar la imagen.");
            }
        }

        $params[] = $usuario_id; // For WHERE clause

        $sql = "UPDATE usuarios SET nombre = ?, username = ?, telefono = ? $password_update_sql $foto_update_sql WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Update Session for other fields
        $_SESSION['usuario_nombre'] = $nombre;
        $_SESSION['usuario_username'] = $username;

        // Return updated data
        echo json_encode(['status' => 'success', 'message' => 'Perfil actualizado correctamente']);
    } else {
        throw new Exception("Acción inválida");
    }

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
