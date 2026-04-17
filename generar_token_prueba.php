<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<h1>Generar Token de Prueba</h1>";

try {
    // Verificar si hay tareas en la base de datos
    $stmt = $pdo->query("SELECT id, titulo, token_compartido, token_expiracion FROM tareas LIMIT 5");
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tareas)) {
        echo "<p style='color:red;'>No hay tareas en la base de datos.</p>";
        echo "<p>Crea al menos una tarea primero en tu aplicación principal.</p>";
    } else {
        echo "<h3>Tareas existentes:</h3>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Título</th><th>Token</th><th>Expiración</th><th>Acción</th></tr>";
        
        foreach ($tareas as $tarea) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($tarea['id']) . "</td>";
            echo "<td>" . htmlspecialchars($tarea['titulo']) . "</td>";
            echo "<td>";
            
            if ($tarea['token_compartido']) {
                echo "<code>" . htmlspecialchars($tarea['token_compartido']) . "</code><br>";
                $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/?token=" . urlencode($tarea['token_compartido']);
                echo "<a href='$url' target='_blank'>Abrir enlace</a>";
            } else {
                echo "<em>Sin token</em>";
            }
            
            echo "</td>";
            echo "<td>" . ($tarea['token_expiracion'] ? htmlspecialchars($tarea['token_expiracion']) : 'N/A') . "</td>";
            
            echo "<td>";
            if (!$tarea['token_compartido']) {
                echo "<form method='POST' style='display:inline;'>";
                echo "<input type='hidden' name='tarea_id' value='" . $tarea['id'] . "'>";
                echo "<input type='submit' name='generar_token' value='Generar Token'>";
                echo "</form>";
            }
            echo "</td>";
            
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Generar token si se solicita
    if (isset($_POST['generar_token']) && isset($_POST['tarea_id'])) {
        $tarea_id = $_POST['tarea_id'];
        $token = bin2hex(random_bytes(16));
        $expiracion = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $pdo->prepare("UPDATE tareas SET token_compartido = ?, token_expiracion = ? WHERE id = ?");
        $stmt->execute([$token, $expiracion, $tarea_id]);
        
        echo "<div style='background-color: #d4edda; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h3>✓ Token generado exitosamente!</h3>";
        echo "<p><strong>Token:</strong> <code>$token</code></p>";
        echo "<p><strong>Expira:</strong> $expiracion</p>";
        
        $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/?token=" . urlencode($token);
        echo "<p><strong>Enlace:</strong> <a href='$url' target='_blank'>$url</a></p>";
        echo "</div>";
        
        // Refrescar la página para mostrar el nuevo token
        echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
    }

} catch (PDOException $e) {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Error de Base de Datos</h3>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Código:</strong> " . $e->getCode() . "</p>";
    
    // Verificar estructura de la tabla
    echo "<h4>Diagnóstico:</h4>";
    
    try {
        // Verificar columnas de la tabla tareas
        $stmt = $pdo->query("DESCRIBE tareas");
        $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Columnas en tabla 'tareas':</p>";
        echo "<ul>";
        foreach ($columnas as $columna) {
            echo "<li>" . htmlspecialchars($columna['Field']) . " (" . htmlspecialchars($columna['Type']) . ")</li>";
        }
        echo "</ul>";
        
        // Verificar si existen las columnas necesarias
        $columnas_necesarias = ['token_compartido', 'token_expiracion'];
        $columnas_existentes = array_column($columnas, 'Field');
        
        foreach ($columnas_necesarias as $necesaria) {
            if (!in_array($necesaria, $columnas_existentes)) {
                echo "<p style='color:red;'>✗ Falta columna: <strong>$necesaria</strong></p>";
            } else {
                echo "<p style='color:green;'>✓ Columna <strong>$necesaria</strong> existe</p>";
            }
        }
        
    } catch (Exception $ex) {
        echo "<p>No se pudo verificar la estructura de la tabla: " . htmlspecialchars($ex->getMessage()) . "</p>";
    }
    
    echo "</div>";
}
?>