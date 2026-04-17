<?php
require_once 'config.php';

try {
    echo "<h2>Actualizando estructura de Notas...</h2>";
    
    // 1. Agregar columna nombre_invitado a notas_tareas
    try {
        $sql = "ALTER TABLE notas_tareas ADD COLUMN nombre_invitado VARCHAR(100) DEFAULT NULL";
        $pdo->exec($sql);
        echo "✅ Se agregó la columna 'nombre_invitado'.<br>";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21' || strpos($e->getMessage(), "Duplicate column") !== false) {
            echo "ℹ️ La columna 'nombre_invitado' ya existía.<br>";
        } else {
            throw $e;
        }
    }

    // 2. Permitir NULL en usuario_id (CRÍTICO para invitados)
    // Esto soluciona el error 1452 de integridad referencial
    $sql_modify = "ALTER TABLE notas_tareas MODIFY COLUMN usuario_id INT NULL";
    $pdo->exec($sql_modify);
    echo "✅ Se modificó 'usuario_id' para permitir valores NULL (necesario para invitados).<br>";
    
    echo "<br><strong>¡Actualización completada!</strong><br>";
    echo "Ahora intenta subir el archivo de nuevo.<br><br>";
    echo "<a href='dashboard.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}
?>
