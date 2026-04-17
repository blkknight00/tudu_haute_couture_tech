<?php
require_once 'config.php';

// Desactivar errores de salida para evitar que rompan el JSON o el texto si se usa en AJAX,
// aunque aquí lo queremos ver en texto plano.
header('Content-Type: text/plain; charset=utf-8');

try {
    echo "=== REPARACIÓN FORZADA DE BASE DE DATOS ===\n\n";
    
    // 1. Tabla 'eventos'
    $cols_eventos = [
        'ubicacion_tipo'    => "VARCHAR(50) DEFAULT 'oficina'",
        'ubicacion_detalle' => "TEXT",
        'link_maps'         => "TEXT"
    ];

    foreach ($cols_eventos as $col => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM eventos LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            echo "[+] Agregando '$col' a 'eventos'...\n";
            // Quitamos el 'AFTER' para que no falle si la columna anterior no existe aún
            $pdo->exec("ALTER TABLE eventos ADD COLUMN $col $definition");
            echo "    -> OK\n";
        } else {
            echo "[i] '$col' ya existe en 'eventos'.\n";
        }
    }

    // 2. Tabla 'solicitudes_cita'
    $stmt2 = $pdo->query("SHOW COLUMNS FROM solicitudes_cita LIKE 'link_maps'");
    if ($stmt2->rowCount() == 0) {
        echo "[+] Agregando 'link_maps' a 'solicitudes_cita'...\n";
        $pdo->exec("ALTER TABLE solicitudes_cita ADD COLUMN link_maps TEXT");
        echo "    -> OK\n";
    } else {
        echo "[i] 'link_maps' ya existe en 'solicitudes_cita'.\n";
    }
    
    echo "\n¡PROCESO COMPLETADO! Todos los campos necesarios están presentes.\n";
    echo "Ya puedes borrar este archivo y el anterior 'update_calendar_schema_v2.php'.\n";
    
} catch (PDOException $e) {
    echo "\n--- ERROR CRÍTICO ---\n";
    echo $e->getMessage() . "\n";
}
?>
