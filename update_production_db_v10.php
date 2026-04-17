<?php
// Script FUERTE para actualizar la base de datos de Producción a la v10 (Añade organizacion_id a proyectos, etiquetas y tareas)
// Muestra TODOS los errores SQL para depuración.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

echo "<h1>Actualización FORZADA de Base de Datos - TuDu</h1>";

function fixColumn($pdo, $table, $column, $definition) {
    echo "<h3>Verificando tabla: <code>$table</code>...</h3>";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "⏳ La columna '$column' NO existe en '$table'. Intentando agregarla...<br>";
        try {
            // Forzar alter table
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "✅ <strong style='color:green'>Columna '$column' añadida con éxito a '$table'.</strong><br>";
        } catch (PDOException $e) {
            echo "❌ <strong style='color:red'>Fallo al hacer ALTER TABLE en '$table':</strong> " . $e->getMessage() . "<br>";
            return false;
        }
    } else {
         echo "ℹ️ La columna '$column' ya existe en '$table'.<br>";
    }

    // Force updates for orphaned records
    try {
        $count = $pdo->exec("UPDATE `$table` SET `$column` = 1 WHERE `$column` IS NULL OR `$column` = 0");
        if ($count > 0) {
            echo "✅ $count registros actualizados a la organización principal (ID 1) en '$table'.<br>";
        } else {
            echo "ℹ️ No se encontraron registros huérfanos en '$table'.<br>";
        }
    } catch (PDOException $e) {
         echo "❌ <strong style='color:red'>Fallo al hacer UPDATE en '$table':</strong> " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
    return true;
}

try {
    // 1. Añadir organizacion_id a proyectos
    fixColumn($pdo, 'proyectos', 'organizacion_id', 'INT DEFAULT NULL');

    // 2. Añadir organizacion_id a etiquetas
    fixColumn($pdo, 'etiquetas', 'organizacion_id', 'INT DEFAULT NULL');
    
    // 3. Añadir organizacion_id a tareas
    fixColumn($pdo, 'tareas', 'organizacion_id', 'INT DEFAULT NULL');
    
    echo "<br><h2 style='color:green'>¡Validación de Base de Datos Terminada!</h2>";
    echo "<p>Si ves errores ROJOS arriba, por favor cópialos y pégalos en el chat.</p>";
    echo "<p>Si todo está VERDE, los proyectos ya deberían cargar.</p>";

} catch (Exception $e) {
    echo "<h1>Error Fatal General: " . $e->getMessage() . "</h1>";
}
?>
