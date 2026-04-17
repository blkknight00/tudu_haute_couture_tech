<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Solo administradores y super administradores pueden ejecutar esto
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['usuario_rol'], ['admin', 'super_admin'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key = $_POST['api_key'] ?? '';
    $prompt_ia = $_POST['prompt'] ?? '';

    try {
        $pdo->beginTransaction();

        // Guardar/Actualizar la API Key
        $sql_key = "INSERT INTO configuracion (clave, valor) VALUES ('DEEPSEEK_API_KEY', ?) 
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
        $stmt_key = $pdo->prepare($sql_key);
        $stmt_key->execute([$api_key]);
        
        // Guardar/Actualizar el Prompt
        $sql_prompt = "INSERT INTO configuracion (clave, valor) VALUES ('DEEPSEEK_PROMPT', ?) 
                       ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
        $stmt_prompt = $pdo->prepare($sql_prompt);
        $stmt_prompt->execute([$prompt_ia]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente.']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
}