<?php
require_once 'config.php';

try {
    echo "<h2>Actualizando base de datos para fotos de perfil...</h2>";
    
    // Agregar columna foto_perfil
    $sql = "ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(500) DEFAULT NULL";
    $pdo->exec($sql);
    
    echo "✅ <strong>Éxito:</strong> Se agregó la columna 'foto_perfil'.<br>";
    echo "<a href='dashboard.php' style='padding:10px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";
    
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') { // Código de error para "Columna ya existe"
        echo "ℹ️ La columna 'foto_perfil' ya existía.<br>";
        echo "<a href='dashboard.php'>Volver al Dashboard</a>";
    } else {
        echo "❌ <strong>Error:</strong> " . $e->getMessage();
    }
}
?>