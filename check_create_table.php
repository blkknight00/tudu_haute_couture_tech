<?php
require 'config.php';
try {
    $tables = ['notas_tareas', 'auditoria'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW CREATE TABLE $t");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Table: $t\n";
        echo $row['Create Table'] . "\n\n";
    }
} catch (Exception $e) { echo $e->getMessage(); }
?>
