<?php
require_once 'config.php';

try {
    // 1. Crear tabla api_keys
    $sql = "CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_name VARCHAR(100) NOT NULL,
        api_key VARCHAR(64) NOT NULL UNIQUE,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "Tabla 'api_keys' verificada/creada correctamente.<br>";

    // 2. Insertar clave de prueba para LexData (si no existe)
    // Generamos una clave aleatoria segura
    $testKey = bin2hex(random_bytes(32)); 
    
    // Verificamos si ya existe LexData
    $stmt = $pdo->prepare("SELECT id, api_key FROM api_keys WHERE app_name = ?");
    $stmt->execute(['LexData']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "La aplicación 'LexData' ya existe. API Key actual: <strong>" . htmlspecialchars($existing['api_key']) . "</strong><br>";
    } else {
        $stmt_ins = $pdo->prepare("INSERT INTO api_keys (app_name, api_key) VALUES (?, ?)");
        $stmt_ins->execute(['LexData', $testKey]);
        echo "Clave API creada para 'LexData': <strong>$testKey</strong><br>";
        echo "(Guarda esta clave, la necesitarás para configurar LexData).<br>";
    }

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>
