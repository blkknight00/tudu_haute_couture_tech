<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// Opcional: Proteger el script para que solo lo corra un admin (o quitar esto si no pueden loguearse)
// require_once 'auth_middleware.php'; 
// checkAuth(); // Comentado para permitir que lo ejecuten sin login si hay error crítico

try {
    // 1. Obtener todas las tablas en la base de datos actual
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $results = [];
    $fixes = 0;
    
    foreach ($tables as $table) {
        // 2. Verificar si la tabla tiene una columna 'id'
        $stmtCol = $pdo->prepare("SHOW COLUMNS FROM `$table` WHERE Field = 'id'");
        $stmtCol->execute();
        $col = $stmtCol->fetch(PDO::FETCH_ASSOC);

        if ($col) {
            $type = $col['Type']; // ej: int(11)
            $extra = strtolower($col['Extra'] ?? '');

            if (strpos($extra, 'auto_increment') === false) {
                // 3. No tiene AUTO_INCREMENT, vamos a intentar agregarlo
                $alterSql = "ALTER TABLE `$table` MODIFY `id` $type NOT NULL AUTO_INCREMENT";
                
                try {
                    $pdo->exec($alterSql);
                    $results[] = "[$table] CORREGIDO: Se agregó AUTO_INCREMENT a la columna id.";
                    $fixes++;
                } catch (PDOException $e) {
                    $results[] = "[$table] ERROR al corregir: " . $e->getMessage();
                }
            } else {
                $results[] = "[$table] OK: Ya tiene AUTO_INCREMENT.";
            }
        } else {
            $results[] = "[$table] IGNORADA: No tiene columna 'id'.";
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Proceso completado. Se han realizado $fixes correcciones.",
        'details' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al analizar la base de datos: ' . $e->getMessage()
    ]);
}
?>
