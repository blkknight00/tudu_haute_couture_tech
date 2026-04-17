<?php
require_once 'config.php';

try {
    echo "<h2>Configurando Módulo Super Admin...</h2>";

    // 1. Insertar configuraciones de licencia si no existen
    $configs = [
        'MAX_USERS' => '5',           // Límite de usuarios por defecto
        'APP_STATUS' => 'active',     // 'active' o 'inactive' (bloqueado)
        'LICENSE_MSG' => 'Su licencia ha expirado. Por favor contacte a soporte.' // Mensaje de bloqueo
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor) VALUES (?, ?)");
    
    foreach ($configs as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    echo "✅ Configuraciones de licencia creadas.<br>";

    // 2. Promover usuarios a super_admin
    $super_admins = ['eduardo', 'Oscar'];
    
    // Crear placeholders para la consulta IN (?, ?)
    $placeholders = implode(',', array_fill(0, count($super_admins), '?'));
    
    $stmt_upd = $pdo->prepare("UPDATE usuarios SET rol = 'super_admin' WHERE username IN ($placeholders)");
    $stmt_upd->execute($super_admins);
    
    $rows_affected = $stmt_upd->rowCount();
    if ($rows_affected > 0) {
        echo "✅ Se promovieron <strong>$rows_affected</strong> usuario(s) a <strong>super_admin</strong>: " . htmlspecialchars(implode(', ', $super_admins)) . ".<br>";
    } else {
        echo "ℹ️ No se encontraron los usuarios para promover o ya eran super_admin.<br>";
    }

    echo "<hr>";
    echo "<h3>¡Listo! 🛡️</h3>";
    echo "<a href='dashboard.php'>Ir al Dashboard</a>";

} catch (PDOException $e) {
    echo "<h1>❌ Error</h1><pre>" . $e->getMessage() . "</pre>";
}
?>