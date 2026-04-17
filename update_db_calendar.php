<?php
require_once 'config.php';

echo "<h2>📅 Instalando Módulo de Calendario...</h2>";

try {
    $pdo->beginTransaction();

    // 1. Tabla de Eventos
     = "CREATE TABLE IF NOT EXISTS eventos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT NOT NULL,
        proyecto_id INT NULL,
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
    
    ->exec();
    echo "✅ Tabla 'eventos' creada.<br>";

    // 2. Tabla de Solicitudes de Citas
     = "CREATE TABLE IF NOT EXISTS solicitudes_cita (
        id INT PRIMARY KEY AUTO_INCREMENT,
        solicitante_id INT NOT NULL,
        receptor_id INT NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        mensaje TEXT,
        fecha_propuesta DATETIME NOT NULL,
        duracion_minutos INT DEFAULT 30,
        estado ENUM('pendiente', 'aceptada', 'rechazada') DEFAULT 'pendiente',
        evento_id INT NULL, -- ID del evento creado si se acepta
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (solicitante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (receptor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    ->exec();
    echo "✅ Tabla 'solicitudes_cita' creada.<br>";

    ->commit();
    echo "<hr><h3>¡Instalación completada!</h3><a href='calendar.php' class='btn-link'>Ir al Calendario</a>";

} catch (PDOException ) {
    ->rollBack();
    echo "❌ Error: " . ->getMessage();
}
?>
