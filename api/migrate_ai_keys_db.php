<?php
require_once 'c:\\xampp\\htdocs\\tudu_haute_couture_tech\\config.php';
try {
    $pdo->exec("ALTER TABLE organizaciones ADD COLUMN deepseek_api_key VARCHAR(255) NULL DEFAULT NULL;");
    echo "Success: Column deepseek_api_key added to organizaciones.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Success: Column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
