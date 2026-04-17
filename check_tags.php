<?php
require_once 'config.php';
header('Content-Type: text/plain');

echo "=== TABLE: etiquetas ===\n";
try {
    $stmt = $pdo->query("DESCRIBE etiquetas");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error describing etiquetas: " . $e->getMessage() . "\n";
}

echo "\n=== LOOKING FOR PIVOT TABLE ===\n";
try {
    $tables = $pdo->query("SHOW TABLES LIKE '%etiqueta%'")->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);
    
    foreach ($tables as $table) {
        if ($table !== 'etiquetas') {
            echo "\nDESCRIBE $table:\n";
            $stmt = $pdo->query("DESCRIBE $table");
            print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    }
} catch (Exception $e) {
    echo "Error showing tables: " . $e->getMessage() . "\n";
}
