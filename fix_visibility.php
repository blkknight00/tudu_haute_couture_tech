<?php
require_once 'config.php';

try {
    echo "<h2>🛠️ Reparando visibilidad de datos...</h2>";
    
    // 1. Asegurar que existe la organización 1
    $pdo->exec("INSERT IGNORE INTO organizaciones (id, nombre) VALUES (1, 'Mi Organización Principal')");
    
    // 2. Asignar tareas antiguas a la Org 1
    // Esto toma todas las tareas que no tienen dueño (org) y se las asigna a la principal
    $sql = "UPDATE tareas SET organizacion_id = 1 WHERE organizacion_id IS NULL OR organizacion_id = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    
    echo "✅ Se actualizaron <strong>$count</strong> tareas antiguas para que sean visibles por el equipo.<br>";
    
    // 3. Asegurar que TODOS los usuarios estén en la Org 1 (por si acaso alguno quedó fuera)
    $sql_users = "INSERT IGNORE INTO miembros_organizacion (usuario_id, organizacion_id, rol_organizacion)
                  SELECT id, 1, 'miembro' FROM usuarios";
    $pdo->exec($sql_users);
    echo "✅ Se aseguró que todos los usuarios pertenezcan a la organización.<br>";

    echo "<hr><a href='dashboard.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>