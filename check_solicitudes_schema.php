<?php
require_once 'config.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM solicitudes_cita");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "COLUMNS FOR solicitudes_cita:\n";
    print_r($cols);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
