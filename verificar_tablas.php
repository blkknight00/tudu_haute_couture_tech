<?php
require_once 'config.php';

echo "<h2>Verificando Estructura de la Base de Datos</h2>";

// Verificar tablas existentes
$stmt = $pdo->query("SHOW TABLES");
$tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<h3>Tablas existentes:</h3>";
echo "<ul>";
foreach ($tablas as $tabla) {
    echo "<li>" . $tabla . "</li>";
}
echo "</ul>";

// Verificar estructura de la tabla usuarios
if (in_array('usuarios', $tablas)) {
    echo "<h3>Estructura de la tabla 'usuarios':</h3>";
    $stmt = $pdo->query("DESCRIBE usuarios");
    $estructura = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($estructura as $campo) {
        echo "<tr>";
        echo "<td>" . $campo['Field'] . "</td>";
        echo "<td>" . $campo['Type'] . "</td>";
        echo "<td>" . $campo['Null'] . "</td>";
        echo "<td>" . $campo['Key'] . "</td>";
        echo "<td>" . $campo['Default'] . "</td>";
        echo "<td>" . $campo['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar usuarios existentes
    echo "<h3>Usuarios existentes:</h3>";
    $stmt = $pdo->query("SELECT id, username, nombre, rol, activo FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($usuarios) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Nombre</th><th>Rol</th><th>Activo</th></tr>";
        foreach ($usuarios as $usuario) {
            echo "<tr>";
            echo "<td>" . $usuario['id'] . "</td>";
            echo "<td>" . $usuario['username'] . "</td>";
            echo "<td>" . $usuario['nombre'] . "</td>";
            echo "<td>" . $usuario['rol'] . "</td>";
            echo "<td>" . $usuario['activo'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No hay usuarios en la tabla.";
    }
} else {
    echo "❌ La tabla 'usuarios' no existe.";
}

echo "<br><br><a href='crear_admin.php' class='btn btn-primary'>Crear Usuario Admin</a>";
?>