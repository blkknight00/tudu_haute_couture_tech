<?php
require_once 'config.php';

$email = "admin@TuDu.com";
$nueva_password = "admin123";

// Hashear la nueva contraseña
$password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
    $stmt->execute([$password_hash, $email]);
    
    if ($stmt->rowCount() > 0) {
        echo "Contraseña actualizada exitosamente!<br>";
        echo "Nueva contraseña: " . $nueva_password . "<br>";
        echo "<a href='login.php'>Ir al Login</a>";
    } else {
        echo "Usuario no encontrado.";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>