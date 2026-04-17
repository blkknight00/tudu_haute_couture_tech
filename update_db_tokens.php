<?php
require_once 'config.php';

echo "<h2>Verificando y actualizando la tabla 'user_tokens'...</h2>";

try {
    // 1. Verificar si la tabla 'user_tokens' existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_tokens'");
    if ($stmt->rowCount() == 0) {
        // La tabla no existe, la creamos con la estructura completa y segura
        $pdo->exec("
            CREATE TABLE user_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                series_id VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                expiry DATETIME NOT NULL,
                INDEX (series_id),
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "✅ <strong>¡Éxito!</strong> La tabla <code>user_tokens</code> no existía y ha sido creada.<br>";
    } else {
        echo "✅ <strong>¡Todo en orden!</strong> La tabla <code>user_tokens</code> ya existe.<br>";
    }
    echo "<p>La función 'Recordarme' ahora debería funcionar correctamente.</p>";
} catch (PDOException $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
    echo "<p>Hubo un problema al actualizar la tabla. Por favor, revisa el mensaje de error.</p>";
}

echo "<hr>";
echo "<a href='login.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Login</a>";

?>