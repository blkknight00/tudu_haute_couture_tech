<?php
require_once 'config.php';

try {
    echo "<h2>Reparando estructura de la Base de Datos...</h2>";
    
    // Alterar la tabla para agregar 'archivado' al ENUM
    $sql = "ALTER TABLE tareas MODIFY COLUMN estado ENUM('pendiente', 'en_progreso', 'completado', 'archivado') DEFAULT 'pendiente'";
    $pdo->exec($sql);
    
    echo "✅ <strong>Éxito:</strong> Se agregó la opción 'archivado' al campo de estado.<br>";
    echo "Ahora el sistema permitirá archivar tareas correctamente.<br><br>";
    echo "<a href='dashboard.php' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>Volver al Dashboard</a>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}
?>