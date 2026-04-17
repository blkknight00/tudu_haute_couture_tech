<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

$task_id = 99; 

echo "<h1>Inspecting Task $task_id</h1>";

try {
    $stmt = $pdo->prepare("SELECT * FROM tareas WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($task) {
        echo "<pre>";
        echo "ID: " . $task['id'] . "\n";
        // Check encoding
        echo "Titulo Encoding: " . mb_detect_encoding($task['titulo']) . "\n";
        echo "Titulo: " . htmlspecialchars($task['titulo'], ENT_SUBSTITUTE, 'UTF-8') . "\n"; // Safe output
        
        echo "Descripcion Length: " . strlen($task['descripcion']) . "\n";
        echo "Descripcion Encoding: " . mb_detect_encoding($task['descripcion']) . "\n";
        
        // Debug preg_replace behavior
        $desc = $task['descripcion'];
        $clean = preg_replace('/@(\w+)/', 'MATCH', $desc);
        if ($clean === null) echo "PREG_REPLACE FAILED ERROR: " . preg_last_error() . "\n";
        else echo "PREG_REPLACE OK\n";
        
        echo "Estado: " . $task['estado'] . "\n";
        echo "Fecha Termino: " . $task['fecha_termino'] . "\n";
        
        print_r($task);
        echo "</pre>";
    } else {
        echo "Task not found.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<h2>Memory Usage: " . memory_get_usage() . " bytes</h2>";
?>
