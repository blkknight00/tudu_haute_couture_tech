<?php
require_once __DIR__ . '/db_credentials.php';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Añadir columna si no existe
    $sql = "SHOW COLUMNS FROM organizaciones LIKE 'codigo_acceso'";
    $stmt = $db->query($sql);
    if ($stmt->rowCount() === 0) {
        echo "Añadiendo columna 'codigo_acceso' a organizaciones...\n";
        $db->exec("ALTER TABLE organizaciones ADD COLUMN codigo_acceso VARCHAR(20) NULL UNIQUE AFTER id");
    }

    // 2. Generar códigos para organizaciones existentes que no lo tienen
    $orgs = $db->query("SELECT id, nombre FROM organizaciones WHERE codigo_acceso IS NULL OR codigo_acceso = ''")->fetchAll();
    
    if (count($orgs) > 0) {
        $updateStmt = $db->prepare("UPDATE organizaciones SET codigo_acceso = ? WHERE id = ?");
        
        foreach ($orgs as $org) {
            // Generar algo como ORG-12X9Z
            $rand = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
            $code = "ORG-" . str_pad($org['id'], 3, '0', STR_PAD_LEFT) . "-" . $rand;
            
            $updateStmt->execute([$code, $org['id']]);
            echo "Organización '{$org['nombre']}' actualizada con código: $code\n";
        }
    } else {
        echo "Todas las organizaciones ya tienen codigo_acceso.\n";
    }

    echo "Migración completada exitosamente.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
