<?php
require_once 'config.php';

echo "<h2>🛠️ Reparando permisos para Invitados...</h2>";

try {
    // 1. Modificar notas_tareas para permitir usuario_id NULL
    // Esto es necesario porque los invitados no tienen ID de usuario
    $sql1 = "ALTER TABLE notas_tareas MODIFY COLUMN usuario_id INT NULL";
    $pdo->exec($sql1);
    echo "✅ Tabla <code>notas_tareas</code> actualizada: Ahora permite comentarios de invitados.<br>";

    // 2. Modificar archivos_adjuntos para permitir usuario_id NULL (prevención)
    $sql2 = "ALTER TABLE archivos_adjuntos MODIFY COLUMN usuario_id INT NULL";
    $pdo->exec($sql2);
    echo "✅ Tabla <code>archivos_adjuntos</code> actualizada: Ahora permite subidas de invitados.<br>";

    echo "<hr>";
    echo "<h3>¡Listo! El problema ha sido resuelto.</h3>";
    echo "<p>Por favor, intenta subir el archivo nuevamente.</p>";
    echo "<a href='dashboard.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}
?>