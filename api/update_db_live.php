<?php
require_once '../config.php';

try {
    echo "<h3>Actualizador de Base de Datos de TuDu (Producción)</h3>";
    
    // Tratamos de agregar la columna
    $pdo->exec("ALTER TABLE organizaciones ADD COLUMN deepseek_api_key VARCHAR(255) NULL DEFAULT NULL;");
    
    echo "<p style='color:green; font-weight:bold;'>✅ ¡Éxito! La columna 'deepseek_api_key' fue inyectada a la base de datos en vivo.</p>";
    echo "<p>Tu Copiloto y el Panel de Empresas ya deberían funcionar correctamente.</p>";
    echo "<p><small>Por seguridad, puedes borrar este archivo de tu hosting.</small></p>";
    
} catch (PDOException $e) {
    // Si da error de "Duplicate column", es que ya estaba y no hay problema.
    if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), '1060') !== false) {
        echo "<p style='color:blue; font-weight:bold;'>ℹ️ La columna ya existía en la base de datos. Todo está listo.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>❌ Error SQL: " . $e->getMessage() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ Error general: " . $e->getMessage() . "</p>";
}
?>
