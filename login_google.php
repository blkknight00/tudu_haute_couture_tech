<?php
require_once 'config.php';
session_start();

// --- CONFIGURACIÓN DE GOOGLE ---
// 1. PEGA AQUÍ TUS CREDENCIALES (Las que obtuviste en la consola)
// Por seguridad, se deben obtener de variables de entorno (.env) o de la DB.
$google_client_id = getenv('GOOGLE_CLIENT_ID') ?: '';
$google_client_secret = getenv('GOOGLE_CLIENT_SECRET') ?: '';

// 2. ESTA URL DEBE SER EXACTAMENTE IGUAL A LA QUE PUSISTE EN LA CONSOLA
// Detectamos automáticamente el entorno para elegir la URL correcta
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    // FORZAMOS la URL exacta que registraste en Google para evitar errores de "mismatch"
    $redirect_uri = 'http://localhost:8080/InterData/tudu/login_google.php';
} else {
    $redirect_uri = 'https://interdata1.store/tudu/login_google.php';
}

// --- LÓGICA DE LOGIN ---

if (isset($_GET['code'])) {
    // ============================================================
    // PARTE 2: EL USUARIO YA VOLVIÓ DE GOOGLE CON UN CÓDIGO
    // ============================================================
    $code = $_GET['code'];

    // A. Intercambiar el código por un Token de Acceso
    $token_url = "https://oauth2.googleapis.com/token";
    $post_data = [
        'code' => $code,
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita errores SSL en localhost
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
        die("Error al conectar con Google. Verifica tus credenciales en el archivo login_google.php");
    }

    $access_token = $data['access_token'];

    // B. Obtener los datos del usuario usando el Token
    $user_info_url = "https://www.googleapis.com/oauth2/v1/userinfo?access_token=$access_token";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $user_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $user_info_response = curl_exec($ch);
    curl_close($ch);

    $google_user = json_decode($user_info_response, true);
    
    // Datos que nos da Google
    $google_id = $google_user['id'];
    $email = $google_user['email'];
    $nombre = $google_user['name'];
    $picture = $google_user['picture'] ?? null; // Foto de Google

    // C. Verificar en nuestra Base de Datos
    // Buscamos si ya existe alguien con ese google_id O con ese email
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE google_id = ? OR email = ?");
    $stmt->execute([$google_id, $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        // --- USUARIO EXISTE: INICIAR SESIÓN ---
        
        // Si tenía el email pero no el google_id (se registró antes manualmente), lo actualizamos
        if (empty($usuario['google_id'])) {
            $stmt_update = $pdo->prepare("UPDATE usuarios SET google_id = ? WHERE id = ?");
            $stmt_update->execute([$google_id, $usuario['id']]);
        }

        // Si el usuario no tiene foto local, usamos la de Google
        if (empty($usuario['foto_perfil']) && $picture) {
            $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")->execute([$picture, $usuario['id']]);
            $usuario['foto_perfil'] = $picture;
        }

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_username'] = $usuario['username'];
        $_SESSION['usuario_rol'] = $usuario['rol'];
        $_SESSION['logged_in'] = true;
        $_SESSION['usuario_foto'] = $usuario['foto_perfil'];
        // Asegurar que la foto se actualice en sesión si venía de Google

    } else {
        // --- USUARIO NUEVO: REGISTRAR AUTOMÁTICAMENTE ---
        
        // 1. VERIFICAR LÍMITE DE LICENCIA
        $stmt_limit = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'MAX_USERS'");
        $max_users = (int)$stmt_limit->fetchColumn();
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1");
        $current_users = (int)$stmt_count->fetchColumn();

        if ($current_users >= $max_users) {
            die("<h1>Límite de usuarios alcanzado</h1><p>Su licencia no permite más usuarios. Contacte al administrador.</p><a href='login.php'>Volver</a>");
        }

        // Generamos un username a partir del email (ej: juan.perez)
        $username = explode('@', $email)[0];
        
        // Insertar nuevo usuario (password vacío porque entra con Google)
        $stmt_insert = $pdo->prepare("INSERT INTO usuarios (nombre, username, email, google_id, rol, activo, foto_perfil) VALUES (?, ?, ?, ?, 'usuario', 1, ?)");
        $stmt_insert->execute([$nombre, $username, $email, $google_id, $picture]);
        
        $nuevo_id = $pdo->lastInsertId();

        $_SESSION['usuario_id'] = $nuevo_id;
        $_SESSION['usuario_nombre'] = $nombre;
        $_SESSION['usuario_username'] = $username;
        $_SESSION['usuario_rol'] = 'usuario';
        $_SESSION['usuario_foto'] = $picture;
        $_SESSION['logged_in'] = true;
    }

    header("Location: dashboard.php");
    exit();

} else {
    // ============================================================
    // PARTE 1: ENVIAR AL USUARIO A GOOGLE
    // ============================================================
    $auth_url = "https://accounts.google.com/o/oauth2/v2/auth?client_id=$google_client_id&redirect_uri=$redirect_uri&response_type=code&scope=email%20profile&access_type=online";
    
    // DESCOMENTA LA SIGUIENTE LÍNEA SI SIGUES CON ERRORES PARA VER LA URL EXACTA QUE SE ESTÁ GENERANDO
    // echo "Copia esta URL en Google Cloud Console: <strong>$redirect_uri</strong>"; exit();
    
    header("Location: $auth_url");
    exit();
}
?>