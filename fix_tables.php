<?php
require_once 'config.php';

try {
    echo "<h2>🔧 Reparando Tablas...</h2>";

    // Intentar activar AUTO_INCREMENT de forma inteligente
    try {
        // Intento 1: Asumimos que ya es Primary Key (tu caso actual) y solo agregamos AUTO_INCREMENT
        $pdo->exec("ALTER TABLE tareas MODIFY COLUMN id INT AUTO_INCREMENT");
        echo "✅ Tabla 'tareas': AUTO_INCREMENT activado.<br>";
    } catch (PDOException $e) {
        // Intento 2: Si falla (quizás no era PK), intentamos definirla como PK
        $pdo->exec("ALTER TABLE tareas MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
        echo "✅ Tabla 'tareas': AUTO_INCREMENT y PRIMARY KEY activados.<br>";
    }
    
    // Verificar si existen otros problemas comunes
    $pdo->exec("ALTER TABLE tareas MODIFY COLUMN organizacion_id INT NULL");
    $pdo->exec("ALTER TABLE tareas MODIFY COLUMN proyecto_id INT NOT NULL");
    $pdo->exec("ALTER TABLE tareas MODIFY COLUMN usuario_id INT NOT NULL");
    
    echo "✅ Estructura de columnas verificada.<br>";
    echo "<hr><a href='dashboard.php'>Volver al Dashboard</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>