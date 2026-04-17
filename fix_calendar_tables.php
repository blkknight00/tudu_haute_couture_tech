<?php
require_once 'config.php';

echo "<h2>📅 Reparando Módulo de Calendario...</h2>";

try {
    // 0. Reparar Tabla Usuarios (Eliminar duplicados y agregar PK)
    echo "🔍 Verificando estructura de 'usuarios'...<br>";
    
    // Verificar si existe índice PRIMARY
    $stmtCheck = $pdo->prepare("SHOW KEYS FROM usuarios WHERE Key_name = 'PRIMARY'");
    $stmtCheck->execute();
    if ($stmtCheck->rowCount() == 0) {
        echo "⚠️ La tabla 'usuarios' no tenía PRIMARY KEY. Analizando duplicados...<br>";
        
        // 1. Obtener filas únicas
        $stmtUni = $pdo->query("SELECT DISTINCT * FROM usuarios");
        $uniqueRows = $stmtUni->fetchAll(PDO::FETCH_ASSOC);
        
        $countOriginal = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        $countUnique = count($uniqueRows);
        
        if ($countOriginal > $countUnique) {
            echo "⚠️ Se encontraron " . ($countOriginal - $countUnique) . " filas duplicadas. Eliminando...<br>";
            
            $pdo->exec("TRUNCATE TABLE usuarios");
            
            $columns = array_keys($uniqueRows[0]);
            $colNames = implode(", ", $columns);
            $placeholders = implode(", ", array_fill(0, count($columns), "?"));
            
            $stmtInsert = $pdo->prepare("INSERT INTO usuarios ($colNames) VALUES ($placeholders)");
            
            foreach ($uniqueRows as $row) {
                $stmtInsert->execute(array_values($row));
            }
            echo "✅ Duplicados eliminados.<br>";
        }

        try {
            // Intentar añadir PK y AutoIncrement
            $pdo->exec("ALTER TABLE usuarios ADD PRIMARY KEY (id)");
            $pdo->exec("ALTER TABLE usuarios MODIFY id INT AUTO_INCREMENT");
            echo "✅ PRIMARY KEY añadida a 'usuarios'.<br>";
        } catch (PDOException $e) {
            echo "❌ Error reparando usuarios: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "✅ La tabla 'usuarios' ya tiene PRIMARY KEY.<br>";
    }

    $pdo->beginTransaction();

    // 1. Tabla de Eventos
    $sql1 = "CREATE TABLE IF NOT EXISTS eventos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT(11) NOT NULL,
        proyecto_id INT(11) NULL,
        titulo VARCHAR(200) NOT NULL,
        descripcion TEXT,
        tipo_evento ENUM('reunion', 'entrega', 'revision', 'produccion', 'personal') DEFAULT 'personal',
        start DATETIME NOT NULL,
        end DATETIME NOT NULL,
        privacidad ENUM('publico', 'privado', 'confidencial') DEFAULT 'privado',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $pdo->exec($sql1);
    echo "✅ Tabla 'eventos' verificada/creada.<br>";

    // 2. Tabla de Solicitudes de Citas
    $sql2 = "CREATE TABLE IF NOT EXISTS solicitudes_cita (
        id INT PRIMARY KEY AUTO_INCREMENT,
        solicitante_id INT(11) NOT NULL,
        receptor_id INT(11) NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        mensaje TEXT,
        fecha_propuesta DATETIME NOT NULL,
        duracion_minutos INT DEFAULT 30,
        estado ENUM('pendiente', 'aceptada', 'rechazada') DEFAULT 'pendiente',
        evento_id INT NULL, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (solicitante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (receptor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    $pdo->exec($sql2);
    echo "✅ Tabla 'solicitudes_cita' verificada/creada.<br>";

    $pdo->commit();
    echo "<hr><h3>¡Reparación completada!</h3>";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage();
}
?>
