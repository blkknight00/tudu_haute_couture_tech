<?php
require_once 'config.php';

echo "<h2>🛠️ Asignando Propiedad de Datos (Multi-Tenant)...</h2>";

try {
    $pdo->beginTransaction();

    // --- PASO 1: Asegurar que las tablas tengan la columna de organización ---
    try {
        $pdo->exec("ALTER TABLE proyectos ADD COLUMN organizacion_id INT DEFAULT NULL;");
        echo "✅ Columna 'organizacion_id' añadida a la tabla 'proyectos'.<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ La columna 'organizacion_id' ya existía en 'proyectos'.<br>";
        } else { throw $e; }
    }

    try {
        $pdo->exec("ALTER TABLE etiquetas ADD COLUMN organizacion_id INT DEFAULT NULL;");
        echo "✅ Columna 'organizacion_id' añadida a la tabla 'etiquetas'.<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ La columna 'organizacion_id' ya existía en 'etiquetas'.<br>";
        } else { throw $e; }
    }

    // --- PASO 2: Asignar todos los datos existentes a la Organización Principal (ID 1) ---
    $pdo->exec("INSERT IGNORE INTO organizaciones (id, nombre) VALUES (1, 'Mi Organización Principal')");

    $count_p = $pdo->exec("UPDATE proyectos SET organizacion_id = 1 WHERE organizacion_id IS NULL OR organizacion_id = 0");
    echo "✅ Se asignaron <strong>$count_p</strong> proyectos a la organización principal.<br>";

    $count_t = $pdo->exec("UPDATE tareas SET organizacion_id = 1 WHERE organizacion_id IS NULL OR organizacion_id = 0");
    echo "✅ Se asignaron <strong>$count_t</strong> tareas a la organización principal.<br>";

    $count_e = $pdo->exec("UPDATE etiquetas SET organizacion_id = 1 WHERE organizacion_id IS NULL OR organizacion_id = 0");
    echo "✅ Se asignaron <strong>$count_e</strong> etiquetas a la organización principal.<br>";

    // --- PASO 3: Asegurar que todos los usuarios pertenezcan a la organización ---
    $sql_users = "INSERT IGNORE INTO miembros_organizacion (usuario_id, organizacion_id, rol_organizacion) SELECT id, 1, 'miembro' FROM usuarios WHERE id NOT IN (SELECT usuario_id FROM miembros_organizacion)";
    $count_u = $pdo->exec($sql_users);
    echo "✅ Se vincularon <strong>$count_u</strong> usuarios huérfanos a la organización principal.<br>";

    $pdo->commit();
    echo "<hr><h3>¡Reparación completada!</h3><a href='dashboard.php' style='padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ <strong>Error Crítico:</strong> " . $e->getMessage();
}
?>