<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<h1>Actualizar Tabla Tareas</h1>";

try {
    // Agregar columnas si no existen
    $alter_queries = [
        "ALTER TABLE tareas ADD COLUMN token_compartido VARCHAR(64) NULL UNIQUE AFTER prioridad",
        "ALTER TABLE tareas ADD COLUMN token_expiracion DATETIME NULL AFTER token_compartido",
        "CREATE INDEX idx_token_compartido ON tareas(token_compartido)"
    ];
    
    echo "<h3>Ejecutando actualizaciones...</h3>";
    
    foreach ($alter_queries as $query) {
        echo "<p><strong>Ejecutando:</strong> <code>" . htmlspecialchars($query) . "</code>...</p>";
        
        try {
            $pdo->exec($query);
            echo "<p style='color:green;'>✓ Completado</p>";
        } catch (PDOException $e) {
            // Si la columna ya existe, ignorar el error
            if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color:orange;'>⚠ La columna ya existe</p>";
            } else if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "<p style='color:orange;'>⚠ El índice ya existe</p>";
            } else {
                echo "<p style='color:red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    // Verificar estructura actualizada
    echo "<h3>Verificando estructura actualizada...</h3>";
    $stmt = $pdo->query("DESCRIBE tareas");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columnas as $columna) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($columna['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($columna['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($columna['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($columna['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($columna['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($columna['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar columnas específicas
    echo "<h3>Verificación de columnas necesarias:</h3>";
    $columnas_necesarias = ['token_compartido', 'token_expiracion'];
    $columnas_existentes = array_column($columnas, 'Field');
    
    foreach ($columnas_necesarias as $necesaria) {
        if (in_array($necesaria, $columnas_existentes)) {
            echo "<p style='color:green;'>✓ Columna <strong>$necesaria</strong> existe</p>";
        } else {
            echo "<p style='color:red;'>✗ Falta columna: <strong>$necesaria</strong></p>";
        }
    }
    
    // Opcional: Actualizar una tarea existente con token de prueba
    echo "<h3>Generar token de prueba</h3>";
    
    // Buscar una tarea existente
    $stmt = $pdo->query("SELECT id, titulo FROM tareas LIMIT 1");
    $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tarea) {
        $token_prueba = bin2hex(random_bytes(16));
        $expiracion = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $update_stmt = $pdo->prepare("
            UPDATE tareas 
            SET token_compartido = ?, token_expiracion = ? 
            WHERE id = ?
        ");
        
        $update_stmt->execute([$token_prueba, $expiracion, $tarea['id']]);
        
        echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "<h4>✓ Token de prueba generado</h4>";
        echo "<p><strong>Tarea:</strong> " . htmlspecialchars($tarea['titulo']) . " (ID: " . $tarea['id'] . ")</p>";
        echo "<p><strong>Token:</strong> <code>$token_prueba</code></p>";
        echo "<p><strong>Expiración:</strong> $expiracion</p>";
        
        // Generar URL de prueba
        $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/?token=" . urlencode($token_prueba);
        echo "<p><strong>Enlace de prueba:</strong> <a href='$url' target='_blank'>Ver tarea compartida</a></p>";
        echo "</div>";
    } else {
        echo "<p style='color:orange;'>No hay tareas para generar token de prueba</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Error General</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>