<?php
require_once 'config.php';
session_start();

// Respuesta JSON por defecto
$response = ['success' => false, 'error' => 'Error desconocido'];

// Verificar permisos
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['usuario_rol'], ['admin', 'super_admin'])) {
    $response['error'] = 'Acceso denegado';
    if (isset($_POST['ajax'])) {
        echo json_encode($response);
        exit;
    }
    die($response['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $usuario_id = $_POST['usuario_id'] ?? '';
        $nombre = trim($_POST['nombre'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'usuario';

        if (empty($nombre) || empty($username)) {
            throw new Exception("Nombre y usuario son obligatorios.");
        }

        // --- VERIFICAR LÍMITE DE USUARIOS (Solo al crear) ---
        if (empty($usuario_id)) {
            // Obtener límite configurado
            $stmt_config = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'MAX_USERS'");
            $stmt_config->execute();
            $max_users = $stmt_config->fetchColumn();
            if ($max_users === false) $max_users = 10; // Default

            // Contar usuarios activos
            $stmt_count = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1");
            $current_users = $stmt_count->fetchColumn();

            if ($current_users >= $max_users) {
                throw new Exception("Límite de usuarios alcanzado ($current_users/$max_users). Aumenta el límite en la configuración.");
            }
            
            // Verificar duplicados
            $stmt_dup = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt_dup->execute([$username]);
            if ($stmt_dup->fetch()) {
                throw new Exception("El nombre de usuario '$username' ya existe.");
            }
        }

        // --- MANEJO DE FOTO ---
        $foto_url = null;
        if (!empty($_FILES['foto_perfil']['name'])) {
            if ($_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error al subir archivo (Código " . $_FILES['foto_perfil']['error'] . ")");
            }

            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception("No se pudo crear la carpeta 'uploads/'. Verifica los permisos del servidor.");
                }
            }
            
            $ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $new_name = uniqid('user_') . '.' . $ext;
                $dest = $upload_dir . $new_name;
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $dest)) {
                    $foto_url = $dest;
                } else {
                    throw new Exception("Error al guardar la imagen. La carpeta 'uploads/' no tiene permisos de escritura.");
                }
            } else {
                throw new Exception("Formato de imagen no válido. Usa JPG, PNG o WEBP.");
            }
        }

        if ($usuario_id) {
            // UPDATE
            $sql = "UPDATE usuarios SET nombre=?, username=?, email=?, telefono=?, rol=? " . 
                   ($foto_url ? ", foto_perfil=? " : "") . 
                   (!empty($password) ? ", password=? " : "") . 
                   "WHERE id=?";
            
            $params = [$nombre, $username, $email, $telefono, $rol];
            if ($foto_url) $params[] = $foto_url;
            if (!empty($password)) $params[] = password_hash($password, PASSWORD_DEFAULT);
            $params[] = $usuario_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // --- MEJORA: Actualizar sesión si el usuario actual se edita a sí mismo ---
            if ($usuario_id == $_SESSION['usuario_id']) {
                $_SESSION['usuario_nombre'] = $nombre;
                if ($foto_url) {
                    $_SESSION['usuario_foto'] = $foto_url;
                    // Devolver la nueva URL para que el frontend la pueda usar
                    $response['new_photo_url'] = $foto_url;
                }
            }
            
            $response['message'] = 'Usuario actualizado correctamente.';
        } else {
            // INSERT
            $sql = "INSERT INTO usuarios (nombre, username, email, telefono, password, rol, foto_perfil, activo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $pass_hash = password_hash(!empty($password) ? $password : '123456', PASSWORD_DEFAULT);
            $params = [$nombre, $username, $email, $telefono, $pass_hash, $rol, $foto_url];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $new_id = $pdo->lastInsertId();

            $response['message'] = 'Usuario creado correctamente.';
        }

        $response['success'] = true;
        $response['error'] = null;

    } catch (Exception $e) {
        $response['success'] = false;
        $response['error'] = $e->getMessage();
    }
}

if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    if ($response['success']) {
        header("Location: dashboard.php");
    } else {
        // Fallback HTML para cuando no es AJAX (el caso del error reportado)
        echo "<div style='font-family:sans-serif; padding:20px; text-align:center;'>";
        echo "<h2 style='color:red;'>Error</h2>";
        echo "<p>" . htmlspecialchars($response['error']) . "</p>";
        echo "<a href='dashboard.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver</a>";
        echo "</div>";
    }
}
?>