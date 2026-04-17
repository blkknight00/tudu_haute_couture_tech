<?php
require_once 'config.php';

try {
    echo "<h2>🏗️ Configurando Base de Datos del SaaS...</h2>";

    // 1. Crear la tabla única de licencias
    $sql = "CREATE TABLE IF NOT EXISTS saas_licencias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        license_key VARCHAR(100) NOT NULL UNIQUE,
        client_name VARCHAR(255) NOT NULL,
        user_limit INT DEFAULT 5,
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        expiration_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "✅ Tabla <code>saas_licencias</code> creada o verificada.<br>";

    // 2. Insertar las licencias de ejemplo (si no existen)
    $licencias_ejemplo = [
        ['TUDU-DEMO-KEY', 'Demo Local', 5, 'active', '2030-12-31'],
        ['TUDU-PRO-KEY', 'Cliente Pro', 50, 'active', '2030-12-31'],
        ['TUDU-CORP-KEY', 'Corporativo', 1000, 'active', '2030-12-31'],
        ['TUDU-EXPIRED-KEY', 'Cliente Vencido', 5, 'inactive', '2022-01-01']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO saas_licencias (license_key, client_name, user_limit, status, expiration_date) VALUES (?, ?, ?, ?, ?)");

    foreach ($licencias_ejemplo as $lic) {
        $stmt->execute($lic);
    }
    
    echo "✅ Licencias de ejemplo insertadas.<br>";
    
    // Mostrar lo que hay en la base de datos
    echo "<h3>📋 Licencias actuales en el sistema:</h3>";
    $stmt_show = $pdo->query("SELECT * FROM saas_licencias");
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr style='background:#eee;'><th>Llave (Key)</th><th>Cliente</th><th>Límite Usuarios</th><th>Estado</th><th>Vence</th></tr>";
    while ($row = $stmt_show->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['license_key']}</td>";
        echo "<td>{$row['client_name']}</td>";
        echo "<td>{$row['user_limit']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['expiration_date']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<br><hr>";
    echo "👉 <strong>Siguiente paso:</strong> Ahora tu <code>validate.php</code> leerá de esta tabla real en lugar del array fijo.";
    echo "<br><a href='dashboard.php'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>