<?php
require_once 'config.php';

// Lista de tablas que deben tener 'id' con AUTO_INCREMENT
$tablas = [
    'tareas',
    'usuarios',
    'proyectos',
    'etiquetas',
    'tarea_etiquetas',
    'tarea_asignaciones',
    'archivos_adjuntos',
    'notas_tareas',
    'notificaciones',
    'auditoria',
    'eventos',
    'solicitudes_cita',
    'tarea_share_tokens',
    'subtareas',
    'saas_licencias',
    'organizaciones',
    'miembros_organizacion'
];

echo "<h2>🔧 Reparando Índices y Auto-Incremento</h2>";
echo "<p>Este script intentará activar AUTO_INCREMENT en las tablas que lo necesiten.</p>";

foreach ($tablas as $tabla) {
    try {
        // Verificar si la tabla existe
        $check = $pdo->query("SHOW TABLES LIKE '$tabla'");
        if ($check->rowCount() == 0) continue;

        // Verificar si tiene columna 'id'
        $cols = $pdo->query("SHOW COLUMNS FROM $tabla LIKE 'id'");
        if ($cols->rowCount() > 0) {
            $colInfo = $cols->fetch(PDO::FETCH_ASSOC);
            
            // Si ya tiene auto_increment, saltar
            if (strpos($colInfo['Extra'], 'auto_increment') !== false) {
                echo "<div style='color:gray'>✓ Tabla <strong>$tabla</strong>: Ya está correcta.</div>";
                continue;
            }

            // Intento 1: Solo agregar AUTO_INCREMENT
            try {
                $pdo->exec("ALTER TABLE `$tabla` MODIFY COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT");
                echo "<div style='color:green'>✅ Tabla <strong>$tabla</strong>: AUTO_INCREMENT activado.</div>";
            } catch (PDOException $e) {
                // Si falla, intentamos hacerlo PK y AI
                $pdo->exec("ALTER TABLE `$tabla` MODIFY COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY");
                echo "<div style='color:green'>✅ Tabla <strong>$tabla</strong>: Se convirtió en PK y se activó AUTO_INCREMENT.</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div style='color:red'>⚠️ Nota sobre <strong>$tabla</strong>: " . $e->getMessage() . "</div>";
    }
}

echo "<hr><h3>¡Reparación finalizada!</h3>";
echo "<p>Ahora intenta guardar la tarea nuevamente.</p>";
echo "<a href='dashboard.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";
?>