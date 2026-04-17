<?php
require_once 'config.php';

try {
    echo "<h2>Configurando enlaces públicos para tareas...</h2>";

    $sql = "CREATE TABLE IF NOT EXISTS tarea_share_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tarea_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        allow_comments BOOLEAN DEFAULT TRUE,
        allow_uploads BOOLEAN DEFAULT TRUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "✅ <strong>¡Éxito!</strong> La tabla <code>tarea_share_tokens</code> ha sido creada.<br>";
    echo "<hr><a href='dashboard.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";
} catch (PDOException $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}
?>