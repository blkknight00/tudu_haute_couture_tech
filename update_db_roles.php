<?php
require_once 'config.php';

try {
    echo "<h2>Actualizando Estructura de Roles...</h2>";

    // Modificar la columna 'rol' para incluir 'super_admin'
    $sql = "ALTER TABLE usuarios MODIFY COLUMN rol ENUM('usuario', 'admin', 'super_admin') NOT NULL DEFAULT 'usuario'";
    $pdo->exec($sql);
    
    echo "✅ <strong>¡Éxito!</strong> La columna 'rol' ha sido actualizada para aceptar el rol 'super_admin'.<br>";
    echo "<p>Ahora puedes ejecutar el script <code>update_db_superadmin.php</code> de nuevo sin problemas.</p>";
    echo "<a href='update_db_superadmin.php' style='display:inline-block; padding:10px 20px; background-color:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Ejecutar script de Super Admin ahora</a>";

} catch (PDOException $e) {
    echo "<h1>❌ Error</h1>";
    echo "<p>Hubo un problema al actualizar la base de datos:</p>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>