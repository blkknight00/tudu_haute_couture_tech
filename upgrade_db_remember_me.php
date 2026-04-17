<?php
require_once 'config.php';

try {
    // Verificar si la columna ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'remember_token'");
    if ($stmt->fetch()) {
        echo "La columna 'remember_token' ya existe.\n";
    } else {
        // Agregar la columna
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN remember_token VARCHAR(64) NULL DEFAULT NULL");
        echo "Columna 'remember_token' agregada exitosamente.\n";
    }
} catch (PDOException $e) {
    die("Error al actualizar la base de datos: " . $e->getMessage());
}
?>
