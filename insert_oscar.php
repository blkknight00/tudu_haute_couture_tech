<?php
require 'config.php';

$nombre = "Oscar Garcia";
$email = "ogarcia@interdata.mx";
$username = "ogarcia";
$password = "G@lapago50:)";
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE usuarios SET rol = 'super_admin', nombre = ?, password = ? WHERE id = ?");
        $stmt->execute([$nombre, $password_hash, $user['id']]);
        echo "Usuario actualizado a super_admin. ID: " . $user['id'];
    } else {
        // Obteniendo organizacion base (ID 1)
        $org_id = 1;
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, username, password, rol, organizacion_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $email, $username, $password_hash, 'super_admin', $org_id]);
        echo "Usuario insertado y ascendido a super_admin. ID: " . $pdo->lastInsertId();
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
