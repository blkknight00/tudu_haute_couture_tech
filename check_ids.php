<?php
require 'config.php';
try {
    $tables = ['notas_tareas', 'auditoria'];
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM $t WHERE id = 0")->fetchColumn();
        echo "$t has $count rows with ID 0.\n";
        
        $max = $pdo->query("SELECT MAX(id) FROM $t")->fetchColumn();
        echo "$t MAX ID is $max.\n\n";
    }
} catch (Exception $e) { echo $e->getMessage(); }
?>
