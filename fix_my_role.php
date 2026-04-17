<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) {
    die("Por favor, inicia sesión en la aplicación y luego vuelve a cargar esta página.");
}

$usuario_id = $_SESSION['usuario_id'];

try {
    // Actualizar el rol del usuario actual a 'super_admin' en la base de datos
    $stmt = $pdo->prepare("UPDATE usuarios SET rol = 'super_admin' WHERE id = ?");
    $stmt->execute([$usuario_id]);

    // Forzar la actualización del rol en la sesión actual, sin importar si la BD cambió o no.
    // Esto soluciona el problema si la BD ya era correcta pero la sesión estaba desactualizada.
    $_SESSION['usuario_rol'] = 'super_admin';
    
    echo "<h1>¡Rol Forzado a Super Admin!</h1>";
    echo "<p>Tu rol en esta sesión ha sido forzado a <strong>super_admin</strong>.</p>";
    echo "<p>Ahora deberías poder ver todos los menús. Para que el cambio sea permanente, por favor, <strong>cierra sesión y vuelve a iniciar sesión</strong>.</p>";
    echo "<p><a href='dashboard.php' style='font-size: 1.2rem; text-decoration: none;'>Ir al Dashboard y verificar</a></p>";
    echo "<p><strong style='color:red;'>IMPORTANTE:</strong> Una vez que todo funcione, elimina este archivo (<code>fix_my_role.php</code>) del servidor.</p>";

} catch (PDOException $e) {
    die("Error de Base de Datos: " . $e->getMessage());
}
?>