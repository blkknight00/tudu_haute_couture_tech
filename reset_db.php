<?php
require_once 'config.php';

// Verificación de seguridad: Solo permitir en localhost
$is_local = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

if (!$is_local) {
    die("<h1>❌ Error de Seguridad</h1><p>Este script solo puede ejecutarse en el entorno local (localhost) para evitar accidentes en producción.</p>");
}

if (isset($_POST['confirm']) && $_POST['confirm'] === 'DELETE_ALL') {
    try {
        // Desactivar revisión de llaves foráneas
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Obtener todas las tablas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            echo "<h2>La base de datos ya está vacía.</h2>";
        } else {
            echo "<ul>";
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
                echo "<li>🗑️ Tabla <code>$table</code> eliminada.</li>";
            }
            echo "</ul>";
            echo "<h2>✅ Base de datos limpiada exitosamente.</h2>";
            echo "<p>Ahora está lista para recibir la importación de producción.</p>";
        }

        // Reactivar revisión de llaves foráneas
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    } catch (PDOException $e) {
        echo "<h1>❌ Error</h1><p>" . $e->getMessage() . "</p>";
    }
    echo "<a href='dashboard.php'>Volver al Dashboard</a>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Limpiar Base de Datos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5 text-center">
    <div class="card shadow border-danger">
        <div class="card-header bg-danger text-white">
            <h1>⚠️ ZONA DE PELIGRO ⚠️</h1>
        </div>
        <div class="card-body">
            <h3 class="card-title">¿Estás seguro de que quieres BORRAR TODA la base de datos local?</h3>
            <p class="card-text lead">Esta acción eliminará <strong>todas las tablas y datos</strong> de la base de datos local.</p>
            <p class="text-muted">Esto es útil para dejar la base de datos "en blanco" antes de importar un respaldo de producción.</p>
            
            <form method="POST">
                <input type="hidden" name="confirm" value="DELETE_ALL">
                <button type="submit" class="btn btn-danger btn-lg">
                    🗑️ SÍ, BORRAR TODO Y DEJAR LIMPIA
                </button>
            </form>
            <br>
            <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </div>
</body>
</html>