<?php
require_once 'config.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM notificaciones LIKE 'tipo'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($col);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
