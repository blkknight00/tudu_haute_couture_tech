<?php
require_once 'config.php';

/*
================================================================================
!! ADVERTENCIA !!
ESTE SCRIPT ES SOLO PARA LA CONFIGURACIÓN INICIAL.
NO LO EJECUTES SI YA TIENES DATOS EN TU APLICACIÓN, PORQUE PUEDE CAUSAR CONFLICTOS.
UNA VEZ QUE TU USUARIO ADMIN ESTÉ FUNCIONANDO, CONSIDERA RENOMBRAR O ELIMINAR ESTE ARCHIVO.
================================================================================
*/

// Crear usuario admin
$username = "eduardo";
$nombre = "Eduardo";
$password = "Svetlana25.09";
$rol = "admin";

// Hashear la contraseña
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO usuarios (username, nombre, password, rol, activo) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $nombre, $password_hash, $rol, 1]);
    
    echo "✅ Usuario admin creado exitosamente!<br>";
    echo "👤 Usuario: <strong>" . $username . "</strong><br>";
    echo "🔑 Password: <strong>" . $password . "</strong><br>";
    echo "📛 Nombre: " . $nombre . "<br>";
    echo "🎯 Rol: " . $rol . "<br>";
    echo "<br><a href='login.php' class='btn btn-primary'>Ir al Login</a>";
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "⚠️ El usuario admin ya existe. <a href='login.php'>Ir al Login</a>";
    } else {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>