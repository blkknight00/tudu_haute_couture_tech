<?php
require_once 'config.php';
header('Content-Type: text/plain');

try {
    // Create tarea_etiquetas table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS tarea_etiquetas (
        tarea_id INT NOT NULL,
        etiqueta_id INT NOT NULL,
        PRIMARY KEY (tarea_id, etiqueta_id),
        FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
        FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "Table 'tarea_etiquetas' verified/created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
