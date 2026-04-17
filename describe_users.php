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

echo "--- ESTRUCTURA DE LA TABLA usuarios ---\n";
$stmt = $pdo->query("DESCRIBE usuarios");
$columns = $stmt->fetchAll();
foreach ($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ") Null: " . $col['Null'] . "\n";
}

echo "\n--- DATOS DE USUARIO 'soporte' ---\n";
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = 'soporte'");
$stmt->execute();
$user = $stmt->fetch();

if ($user) {
    foreach ($user as $key => $value) {
        echo "$key: " . ($value === null ? "NULL" : $value) . "\n";
    }
} else {
    echo "Usuario 'soporte' no encontrado.\n";
}
?>
