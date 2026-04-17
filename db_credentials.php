<?php
/**
 * Configuración de Base de Datos
 * Utilizando variables de entorno (.env) para mayor seguridad.
 * ADVERTENCIA: El archivo .env jamás debe ser versionado o incluido en el código fuente final.
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        // En algunos ambientes super-controlados, las variables ya podrían estar en $_ENV
        if (isset($_SERVER['DB_HOST']) && isset($_SERVER['DB_NAME'])) {
            return; 
        }
        die("Error Crítico: No se encontró el archivo de configuración .env");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Cargar el .env (asume que está en root, donde también está este archivo)
loadEnv(__DIR__ . '/.env');

// Extraer credenciales
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');

if (!$dbname || !$username) {
    die("Error Crítico: Faltan variables de base de datos en el archivo .env");
}
