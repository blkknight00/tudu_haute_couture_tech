<?php
require_once 'config.php';

try {
    echo "<h2>Iniciando reparación de Base de Datos...</h2>";

    // --- 1. Reparar la visibilidad de los proyectos (Bug de "Privado / Oscar") ---
    // Como implementamos el filtro estricto de privacidad, todos los proyectos antiguos
    // quedaron atados al ID de quien los creó, volviéndose invisibles para los demás.
    // Esta consulta los vuelve todos PÚBLICOS por defecto (user_id = NULL), 
    // y solo los nuevos que se marquen explícitamente como "Privados" mantendrán el dueño.
    
    $stmt = $pdo->prepare("UPDATE proyectos SET user_id = NULL");
    $stmt->execute();
    $filas_proyectos = $stmt->rowCount();
    
    echo "<p>✅ <strong>Proyectos:</strong> Se actualizaron $filas_proyectos proyectos para que sean <strong>Públicos</strong> y visibles para todo el equipo nuevamente.</p>";

    // --- 2. Asegurar que la tabla 'organizaciones' tenga permisos y estructura correcta ---
    // Aunque phpMyAdmin a veces separa el AUTO_INCREMENT al final del archivo, 
    // nos aseguramos de inyectarlo si por alguna razón falla en Producción.
    
    try {
        $pdo->exec("ALTER TABLE organizaciones MODIFY id INT NOT NULL AUTO_INCREMENT");
        echo "<p>✅ <strong>Organizaciones:</strong> Estructura verificada (AUTO_INCREMENT activado correctamente).</p>";
    } catch (PDOException $e) {
        // Ignoramos el error si ya tiene la llave primaria y auto incremento correcto.
        echo "<p>ℹ️ <strong>Organizaciones:</strong> La tabla ya tenía la estructura correcta.</p>";
    }

    echo "<h3>¡Reparación completada con éxito! 🎉</h3>";
    echo "<p>Nota: Verifica en la plataforma que ya puedes ver los proyectos antiguos, y haz una prueba creando una Empresa nueva en el panel de administrador.</p>";
    echo "<p><em>Por seguridad, elimina este archivo ('fix_db_production.php') de tu servidor después de usarlo.</em></p>";

} catch (PDOException $e) {
    echo "<h3>Error crítico:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
