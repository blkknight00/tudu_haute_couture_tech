<?php
require_once 'config.php';

echo "<h2>🛠️ Reparando Tablas Faltantes de AUTO_INCREMENT...</h2>";

$tables_to_fix = [
    'notificaciones',
    'tarea_asignaciones',
    'archivos_adjuntos',
    'proyectos'
];

try {
    foreach ($tables_to_fix as $table) {
        echo "🔍 Analizando tabla '$table'...<br>";
        
        // Verificar si tiene AUTO_INCREMENT
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'id'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (strpos($col['Extra'], 'auto_increment') === false) {
            echo "⚠️ '$table' NO tiene AUTO_INCREMENT. Reparando...<br>";
            
            // Paso preventivo: Si hay un ID 0, cambiarlo para evitar conflictos si SQL_MODE es estricto
            $countZero = $pdo->query("SELECT COUNT(*) FROM $table WHERE id = 0")->fetchColumn();
            if ($countZero > 0) {
                $maxId = $pdo->query("SELECT MAX(id) FROM $table")->fetchColumn();
                $newId = ($maxId ?: 0) + 1;
                $pdo->exec("UPDATE $table SET id = $newId WHERE id = 0");
                echo "ℹ️ Se actualizó fila con ID 0 a ID $newId.<br>";
            }

            try {
                $pdo->exec("ALTER TABLE $table MODIFY id INT AUTO_INCREMENT");
                echo "✅ AUTO_INCREMENT añadido a '$table'.<br>";
            } catch (PDOException $e) {
                echo "❌ Error en '$table': " . $e->getMessage() . "<br>";
            }
        } else {
            echo "✅ '$table' ya tiene AUTO_INCREMENT.<br>";
        }
        echo "<hr>";
    }
    echo "<h3>¡Proceso completado! intenta guardar la tarea de nuevo.</h3>";

} catch (PDOException $e) {
    echo "❌ Error General: " . $e->getMessage();
}
?>
