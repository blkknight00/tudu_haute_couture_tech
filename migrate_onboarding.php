<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN tour_visto TINYINT(1) DEFAULT 0");
    echo "Columna tour_visto añadida con éxito.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "La columna ya existe.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
