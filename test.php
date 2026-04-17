<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test del Servidor</h1>";

// Test 1: PHP funciona
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test 2: Verificar config.php
if (file_exists('config.php')) {
    echo "<p style='color:green;'>✓ config.php existe</p>";
    
    // Intentar incluir sin ejecutar
    $config_content = file_get_contents('config.php');
    if (strpos($config_content, '$pdo') !== false) {
        echo "<p style='color:green;'>✓ config.php contiene \$pdo</p>";
    } else {
        echo "<p style='color:red;'>✗ config.php NO contiene \$pdo</p>";
    }
} else {
    echo "<p style='color:red;'>✗ config.php NO existe</p>";
}

// Test 3: Verificar permisos
echo "<p>Permisos de archivos:</p>";
echo "<ul>";
echo "<li>config.php: " . (is_readable('config.php') ? 'Legible ✓' : 'NO legible ✗') . "</li>";
echo "<li>Directorio: " . (is_writable('.') ? 'Escribible ✓' : 'NO escribible ✗') . "</li>";
echo "</ul>";

// Test 4: Variables GET
echo "<p>Token en GET: " . ($_GET['token'] ?? 'NO PROPORCIONADO') . "</p>";

// Test 5: PDO disponible
if (class_exists('PDO')) {
    echo "<p style='color:green;'>✓ PDO está disponible</p>";
    $drivers = PDO::getAvailableDrivers();
    echo "<p>Drivers PDO: " . implode(', ', $drivers) . "</p>";
} else {
    echo "<p style='color:red;'>✗ PDO NO está disponible</p>";
}
?>