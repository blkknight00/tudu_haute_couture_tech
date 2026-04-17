<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, nombre, email, username, rol, activo FROM usuarios");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
