<?php
require 'config.php';

$outputFile = 'tudu_v10_master.sql';
$schemaFile = 'tudu_v10_schema.sql';

if (!file_exists($schemaFile)) {
    die("Schema file not found\n");
}

$sql = file_get_contents($schemaFile);

// Filter out AUTO_INCREMENT values from the schema to make it clean
$sql = preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $sql);

// Add the initial data header
$sql .= "\n\n-- --------------------------------------------------------\n";
$sql .= "-- DATOS INICIALES PARA INSTALACIÓN NUEVA DE TUDU V10\n";
$sql .= "-- --------------------------------------------------------\n\n";

// 1. Configuracion
try {
    $stmt = $pdo->query("SELECT * FROM configuracion");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($configs) {
        $sql .= "-- Volcar la base de datos para la tabla `configuracion`\n";
        $sql .= "INSERT INTO `configuracion` (`clave`, `valor`) VALUES\n";
        $vals = [];
        foreach($configs as $c) {
            $vals[] = "(" . $pdo->quote($c['clave']) . ", " . $pdo->quote($c['valor']) . ")";
        }
        $sql .= implode(",\n", $vals) . ";\n\n";
    }
} catch(Exception $e) {}

// 2. Roles
try {
    $stmt = $pdo->query("SELECT * FROM roles");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($roles) {
        $sql .= "-- Volcar la base de datos para la tabla `roles`\n";
        $sql .= "INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES\n";
        $vals = [];
        // Only keep standard roles if lots exist, or just dump them all
        foreach($roles as $r) {
            $vals[] = "(" . intval($r['id']) . ", " . $pdo->quote($r['nombre']) . ", " . $pdo->quote($r['descripcion']) . ")";
        }
        $sql .= implode(",\n", $vals) . ";\n\n";
    }
} catch (Exception $e) {}

// 3. Organización Principal
// Creamos una organización por defecto
$sql .= "-- Volcar la base de datos para la tabla `organizaciones`\n";
$sql .= "INSERT INTO `organizaciones` (`id`, `nombre`, `fecha_creacion`) VALUES\n";
$sql .= "(1, 'Mi Organización Principal', NOW());\n\n";

// 4. Usuario Super Admin (Eduardo / Svetlana25.09)
// Generamos el hash de la contraseña actual si es necesario, o la copiamos de la bd local
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = 'eduardo' OR rol = 'super_admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($admin) {
        $password = isset($admin['password_hash']) ? $admin['password_hash'] : (isset($admin['password']) ? $admin['password'] : '');
        $sql .= "-- Volcar la base de datos para la tabla `usuarios` (Credenciales por defecto)\n";
        $sql .= "INSERT INTO `usuarios` (`id`, `nombre`, `username`, `email`, `password`, `rol`, `activo`, `fecha_creacion`) VALUES\n";
        $sql .= "(" . intval($admin['id']) . ", " . $pdo->quote($admin['nombre']) . ", " . $pdo->quote($admin['username']) . ", " . $pdo->quote($admin['email']) . ", " . $pdo->quote($password) . ", " . $pdo->quote($admin['rol']) . ", 1, NOW());\n\n";
        
        $sql .= "-- Vincular Administrador a Organización Principal\n";
        $sql .= "INSERT INTO `miembros_organizacion` (`usuario_id`, `organizacion_id`, `rol_organizacion`) VALUES\n";
        $sql .= "(" . intval($admin['id']) . ", 1, 'admin');\n\n";
    }
} catch (Exception $e) {}


file_put_contents($outputFile, $sql);
echo "Archivo $outputFile generado con éxito.\n";
?>
