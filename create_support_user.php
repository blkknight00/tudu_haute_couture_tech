<?php
require_once 'db_credentials.php';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$user = 'soporte';
$pass = 'tudu123';
$hash = password_hash($pass, PASSWORD_DEFAULT);
$role = 'super_admin';

// Check if exists
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
$stmt->execute([$user]);
$exists = $stmt->fetch();

if ($exists) {
    $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, rol = ?, activo = 1 WHERE username = ?");
    $stmt->execute([$hash, $role, $user]);
    echo "Usuario '$user' actualizado correctamente.\n";
} else {
    $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, rol, activo) VALUES (?, ?, ?, 1)");
    $stmt->execute([$user, $hash, $role]);
    echo "Usuario '$user' creado correctamente.\n";
}

echo "Credenciales: $user / $pass\n";
?>
