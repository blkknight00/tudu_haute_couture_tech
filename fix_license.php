<?php
require_once 'config.php';

// --- Check if already licensed ---
$stmt_check = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'TUDU_LICENSE_KEY'");
$licencia_existente = $stmt_check->fetchColumn();

if ($licencia_existente) {
    echo "<h2>✅ Sistema ya Licenciado</h2>";
    echo "<p>Tu instalación de desarrollo ya tiene una licencia (<code>" . htmlspecialchars($licencia_existente) . "</code>).</p>";
    echo "<p>Este script <strong>fix_license.php</strong> fue una reparación de un solo uso y ya no es necesario. Puedes eliminarlo por seguridad.</p>";
    echo "<hr>";
    echo "<a href='login.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Ir al Login</a>";
    exit();
}

try {
    echo "<h2>🛠️ Reparando Licencia de Desarrollo...</h2>";

    // Insertar configuraciones de licencia para el entorno local
    $configs = [
        'TUDU_LICENSE_KEY' => 'TUDU-DEV-LOCAL-KEY', // Una llave para desarrollo
        'SAAS_API_URL'     => 'http://localhost/InterData/saas_api/validate.php',
        'APP_STATUS'       => 'active',
        'MAX_USERS'        => '50' // Un límite alto para que no te bloquee
    ];

    $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    
    foreach ($configs as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    
    echo "✅ Se ha insertado una licencia de desarrollo en la base de datos.<br>";
    echo "<p>Ahora el sistema ya no debería redirigirte al instalador.</p>";
    echo "<hr>";
    echo "<a href='login.php' style='padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px;'>Ir al Login</a>";

} catch (PDOException $e) {
    echo "<h1>❌ Error</h1><pre>" . $e->getMessage() . "</pre>";
}
?>