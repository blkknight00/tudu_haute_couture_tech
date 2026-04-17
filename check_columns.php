<?php
require 'config.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM notas_tareas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $c) {
        echo $c['Field'] . "\n";
    }
} catch (Exception $e) { echo $e->getMessage(); }
?>
