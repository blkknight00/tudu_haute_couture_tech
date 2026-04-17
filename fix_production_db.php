<?php
// Script para reparar la base de datos en Producción
// Sube este archivo a tu carpeta pública (public_html o htdocs) y ejecútalo desde el navegador via tudoname.com/fix_production_db.php

require 'config.php';

echo "<h1>Reparación de Base de Datos - TuDu</h1>";

function fixTable($pdo, $table) {
    echo "<h3>Procesando tabla: <code>$table</code>...</h3>";
    
    try {
        // 1. Verificar si existe la fila con ID 0 (Signo de falta de Auto Increment)
        $count = $pdo->query("SELECT COUNT(*) FROM $table WHERE id = 0")->fetchColumn();
        
        if ($count > 0) {
            // Obtener el ID máximo actual para mover el 0 al final
            $max = $pdo->query("SELECT MAX(id) FROM $table")->fetchColumn();
            $new_id = $max + 1;
            echo "⚠️ Se encontró un registro con ID 0. Moviéndolo al ID $new_id...<br>";
            
            // Actualizar el ID 0
            $stmt = $pdo->prepare("UPDATE $table SET id = ? WHERE id = 0");
            $stmt->execute([$new_id]);
            echo "✅ Registro corregido.<br>";
        } else {
            echo "ℹ️ No se encontraron registros conflictivos (ID 0).<br>";
        }

        // 2. Verificar si ya es AUTO_INCREMENT
        // Una forma sencilla es intentar modificarlo. Si ya lo es, no pasa nada o avisa.
        // Pero mejor intentamos aplicarlo directamente.
        
        echo "🔧 Aplicando AUTO_INCREMENT a la columna ID...<br>";
        $pdo->exec("ALTER TABLE $table MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        echo "✅ <strong style='color:green'>AUTO_INCREMENT aplicado correctamente.</strong><br>";
        
    } catch (PDOException $e) {
        echo "❌ <strong style='color:red'>Error:</strong> " . $e->getMessage() . "<br>";
    }
    echo "<hr>";
}

try {
    // Reparar tablas afectadas
    fixTable($pdo, 'notas_tareas');
    fixTable($pdo, 'auditoria');
    
    echo "<h2 style='color:green'>¡Reparación Completada!</h2>";
    echo "<p>Ahora puedes borrar este archivo del servidor.</p>";
    
} catch (Exception $e) {
    echo "<h1>Error Fatal: " . $e->getMessage() . "</h1>";
}
?>
