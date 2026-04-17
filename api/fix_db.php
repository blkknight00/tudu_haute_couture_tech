<?php
require_once 'c:/xampp/htdocs/tudu_haute_couture_tech/config.php';
try {
    $pdo->exec("ALTER TABLE organizaciones ADD COLUMN deepseek_api_key VARCHAR(255) NULL DEFAULT NULL;");
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
try {
    $stmt = $pdo->query("DESCRIBE organizaciones");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error describing: " . $e->getMessage() . "\n";
}
