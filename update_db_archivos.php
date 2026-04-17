<?php
require_once 'config.php';

try {
    echo "<h2>Creando tabla de Archivos Adjuntos...</h2>";

    $sql = "CREATE TABLE IF NOT EXISTS archivos_adjuntos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tarea_id INT NOT NULL,
        usuario_id INT,
        nombre_original VARCHAR(255) NOT NULL,
        nombre_archivo VARCHAR(255) NOT NULL,
        tipo_archivo VARCHAR(100),
        tamano INT,
        fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    
    echo "✅ Tabla <strong>archivos_adjuntos</strong> creada correctamente.<br>";
    echo "<hr>";
    echo "<a href='dashboard.php'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "<h1>❌ Error</h1><pre>" . $e->getMessage() . "</pre>";
}
?>