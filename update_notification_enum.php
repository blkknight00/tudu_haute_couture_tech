<?php
require_once 'config.php';
try {
    echo "Actualizando columna 'tipo' en tabla notificaciones...\n";
    // Add 'cita' and 'bienvenida' to the enum
    $sql = "ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM('mencion','asignacion','sistema','vencimiento','cita','bienvenida') NOT NULL";
    $pdo->exec($sql);
    echo "Columna 'tipo' actualizada correctamente.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
