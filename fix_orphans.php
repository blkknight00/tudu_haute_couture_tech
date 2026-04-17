<?php
require_once 'config.php';

try {
    echo "<h2>🛠️ Reparando usuarios huérfanos...</h2>";
    
    // 1. Asegurar que existe la organización 1
    $pdo->exec("INSERT IGNORE INTO organizaciones (id, nombre) VALUES (1, 'Mi Organización Principal')");
    echo "✅ Organización Principal verificada.<br>";
    
    // 2. Vincular usuarios huérfanos a la Org 1
    // Busca usuarios que NO estén en la tabla de miembros y los inserta
    $sql = "INSERT IGNORE INTO miembros_organizacion (usuario_id, organizacion_id, rol_organizacion)
            SELECT id, 1, 'miembro' 
            FROM usuarios 
            WHERE id NOT IN (SELECT usuario_id FROM miembros_organizacion)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        echo "✅ Se rescataron <strong>$count</strong> usuarios huérfanos y se unieron a la organización.<br>";
    } else {
        echo "✅ Todos los usuarios ya tienen organización. No hubo cambios necesarios.<br>";
    }
    
    // 3. Asegurar que TU usuario sea Admin (opcional, pero útil para que no pierdas acceso)
    session_start();
    if (isset($_SESSION['usuario_id'])) {
        $uid = $_SESSION['usuario_id'];
        // Verificar si ya es admin
        $stmt_check = $pdo->prepare("SELECT rol_organizacion FROM miembros_organizacion WHERE usuario_id = ? AND organizacion_id = 1");
        $stmt_check->execute([$uid]);
        $rol = $stmt_check->fetchColumn();
        
        if ($rol !== 'admin') {
            $pdo->prepare("UPDATE miembros_organizacion SET rol_organizacion = 'admin' WHERE usuario_id = ? AND organizacion_id = 1")->execute([$uid]);
            echo "✅ Tu usuario ahora es <strong>Admin</strong> de la organización.<br>";
        }
        
        // Actualizar sesión para que veas los cambios ya
        $_SESSION['organizacion_id'] = 1;
    }
    
    echo "<hr><a href='dashboard.php' style='padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>