<?php
require 'config.php';

function fixTable($pdo, $table) {
    echo "Processing $table...\n";
    
    // Check if ID 0 exists
    $count = $pdo->query("SELECT COUNT(*) FROM $table WHERE id = 0")->fetchColumn();
    
    if ($count > 0) {
        // Get Max ID
        $max = $pdo->query("SELECT MAX(id) FROM $table")->fetchColumn();
        $new_id = $max + 1;
        echo "Found ID 0. Updating to $new_id...\n";
        
        // Update
        $stmt = $pdo->prepare("UPDATE $table SET id = ? WHERE id = 0");
        $stmt->execute([$new_id]);
        echo "Updated.\n";
    } else {
        echo "No ID 0 found.\n";
    }

    // Add AUTO_INCREMENT
    echo "Adding AUTO_INCREMENT...\n";
    try {
        $pdo->exec("ALTER TABLE $table MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        echo "AUTO_INCREMENT added successfully.\n";
    } catch (PDOException $e) {
        echo "Error altering table: " . $e->getMessage() . "\n";
    }
    echo "Done with $table.\n\n";
}

try {
    fixTable($pdo, 'notas_tareas');
    fixTable($pdo, 'auditoria');
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
}
?>
