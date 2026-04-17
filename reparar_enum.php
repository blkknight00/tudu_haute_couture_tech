<?php
require_once 'config.php';

try {
    echo "<h2>Reparando la estructura de Roles...</h2>";
    
    // Alterar la tabla 'usuarios' para agregar 'super_admin' al ENUM del campo 'rol'
    $sql = "ALTER TABLE usuarios MODIFY COLUMN rol ENUM('usuario', 'admin', 'super_admin') DEFAULT 'usuario'";
    $pdo->exec($sql);
    
    echo "✅ <strong>¡Éxito!</strong> Se agregó el rol 'super_admin' a la base de datos.<br>";
    echo "Ahora el sistema puede asignar este rol correctamente.<br><br>";
    echo "<p><strong>Siguiente paso:</strong></p>";
    echo "<p>Ahora, por favor, ejecuta el script para asignarte este nuevo rol:</p>";
    echo "<a href='fix_my_role.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>2. Asignarme el rol de Super Admin</a>";
    
} catch (PDOException $e) {
    // Si el rol ya existe, no es un error fatal.
    if (strpos($e->getMessage(), "Duplicate entry") !== false || strpos($e->getMessage(), "Data truncated") !== false) {
         echo "✅ <strong>Parece que el rol 'super_admin' ya existía.</strong> ¡No hay problema!<br><br>";
         echo "<p><strong>Siguiente paso:</strong></p>";
         echo "<p>Ahora, por favor, ejecuta el script para asignarte este nuevo rol:</p>";
         echo "<a href='fix_my_role.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>2. Asignarme el rol de Super Admin</a>";
    } else {
        echo "❌ <strong>Error:</strong> " . $e->getMessage();
        echo "<br><br>Hubo un problema al modificar la base de datos. Por favor, contacta a soporte.";
    }
}
?>