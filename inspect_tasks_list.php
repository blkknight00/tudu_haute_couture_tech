<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

echo "<h1>Listing First 20 Tasks (Public Sort)</h1>";

try {
    // Mimic dashboard query for public view (default)
    $sql = "SELECT t.id, t.titulo, t.fecha_creacion, t.fecha_termino, t.estado, t.prioridad 
            FROM tareas t 
            WHERE t.visibility = 'public' AND t.estado != 'archivado'
            ORDER BY 
                FIELD(t.estado, 'pendiente', 'en_progreso', 'completado'),
                CASE WHEN t.fecha_termino IS NULL THEN 1 ELSE 0 END,
                t.fecha_termino ASC,
                FIELD(t.prioridad, 'alta', 'media', 'baja'),
                t.fecha_creacion DESC
            LIMIT 20";
    $stmt = $pdo->query($sql);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    foreach ($tasks as $task) {
        // Safe output
        $tit = htmlspecialchars($task['titulo'], ENT_SUBSTITUTE, 'UTF-8');
        echo "TASK_ID: " . $task['id'] . " | TITLE: " . $tit . " | DATE: " . $task['fecha_creacion'] . " | DUE: " . $task['fecha_termino'] . "\n";
    }
    echo "END OF LIST</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
