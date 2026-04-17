<?php
require_once 'config.php';

try {
    echo "Agregando columnas de ubicación a la tabla eventos...\n";
    
    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM eventos LIKE 'ubicacion_tipo'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE eventos 
                ADD COLUMN ubicacion_tipo VARCHAR(50) DEFAULT 'presencial' AFTER descripcion,
                ADD COLUMN ubicacion_detalle TEXT AFTER ubicacion_tipo";
        $pdo->exec($sql);
        echo "Columnas 'ubicacion_tipo' y 'ubicacion_detalle' agregadas correctamente.\n";
    } else {
        echo "Las columnas ya existen.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
