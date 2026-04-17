<?php
require_once 'config.php';

try {
    echo "Actualizando esquema para enlaces de Maps...\n";
    
    // Agregar link_maps a eventos
    $stmt = $pdo->query("SHOW COLUMNS FROM eventos LIKE 'link_maps'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE eventos ADD COLUMN link_maps TEXT AFTER ubicacion_detalle");
        echo "Columna 'link_maps' agregada a 'eventos'.\n";
    }

    // Agregar link_maps a solicitudes_cita
    $stmt2 = $pdo->query("SHOW COLUMNS FROM solicitudes_cita LIKE 'link_maps'");
    if ($stmt2->rowCount() == 0) {
        $pdo->exec("ALTER TABLE solicitudes_cita ADD COLUMN link_maps TEXT AFTER mensaje");
        echo "Columna 'link_maps' agregada a 'solicitudes_cita'.\n";
    }
    
    echo "Migración completada con éxito.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
