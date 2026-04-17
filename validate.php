<?php
// Este archivo simula tu servidor SaaS (Backend Central)
// En producción, este archivo viviría en tu servidor principal (ej: interdata1.store)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permitir peticiones desde cualquier dominio cliente

// 1. CONEXIÓN A LA BASE DE DATOS DEL SAAS
// Usamos la misma lógica de detección de entorno que en config.php
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    $host = 'localhost';
    $dbname = 'adcecmis_todo_collaborative'; 
    $username = 'root';
    $password = '';
} else {
    $host = 'localhost';
    $dbname = 'adcecmis_todo_collaborative';
    $username = 'adcecmis_eduardo';
    $password = 'Svetlana25.09';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor de licencias']);
    exit;
}

// 2. Recibir parámetros
$key = $_GET['key'] ?? '';
$action = $_GET['action'] ?? 'check'; // 'activate' o 'check'

// 3. Validar
if (empty($key)) {
    echo json_encode(['status' => 'error', 'message' => 'No se proporcionó una llave de licencia.']);
    exit;
}

// 3. CONSULTAR LA BASE DE DATOS
$stmt = $pdo->prepare("SELECT * FROM saas_licencias WHERE license_key = ?");
$stmt->execute([$key]);
$licencia = $stmt->fetch(PDO::FETCH_ASSOC);

if ($licencia) {
    // Verificar si está activa y no ha expirado
    if ($licencia['status'] === 'active' && strtotime($licencia['expiration_date']) > time()) {
        echo json_encode([
            'status' => 'active',
            'user_limit' => (int)$licencia['user_limit'],
            'message' => 'Licencia válida para ' . $licencia['client_name']
        ]);
    } else {
        echo json_encode(['status' => 'inactive', 'message' => 'La licencia ha expirado o está suspendida.']);
    }
} else {
    echo json_encode(['status' => 'invalid', 'message' => 'Llave de licencia no válida.']);
}
?>