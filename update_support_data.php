<?php
require_once 'db_credentials.php';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE username = 'soporte'");
$stmt->execute(['Soporte Técnico', 'soporte@tudu.app']);

echo "Usuario 'soporte' actualizado con Nombre y Email.\n";
?>
