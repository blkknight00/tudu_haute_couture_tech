<?php
// Este archivo simula tu servidor SaaS (Backend Central)
// En producción, este archivo viviría en tu servidor principal (ej: interdata1.store)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permitir peticiones desde cualquier dominio cliente

// 1. Base de Datos Simulada de Licencias
// En un sistema real, esto sería una consulta a tu base de datos MySQL (tabla 'licencias')
$licencias_db = [
    'TUDU-DEMO-KEY' => [
        'status' => 'active',
        'user_limit' => 5,
        'empresa' => 'Demo Local',
        'expiration' => '2030-12-31'
    ],
    'TUDU-PRO-KEY' => [
        'status' => 'active',
        'user_limit' => 50,
        'empresa' => 'Cliente Pro',
        'expiration' => '2030-12-31'
    ],
    'TUDU-CORP-KEY' => [
        'status' => 'active',
        'user_limit' => 1000,
        'empresa' => 'Corporativo',
        'expiration' => '2030-12-31'
    ],
    'TUDU-EXPIRED-KEY' => [
        'status' => 'inactive',
        'user_limit' => 5,
        'empresa' => 'Cliente Vencido',
        'expiration' => '2022-01-01'
    ]
];

// 2. Recibir parámetros
$key = $_GET['key'] ?? '';
$action = $_GET['action'] ?? 'check'; // 'activate' o 'check'

// 3. Validar
if (empty($key)) {
    echo json_encode(['status' => 'error', 'message' => 'No se proporcionó una llave de licencia.']);
    exit;
}

if (array_key_exists($key, $licencias_db)) {
    $data = $licencias_db[$key];
    
    // Verificar si está activa y no ha expirado
    if ($data['status'] === 'active' && strtotime($data['expiration']) > time()) {
        echo json_encode([
            'status' => 'active',
            'user_limit' => $data['user_limit'],
            'message' => 'Licencia válida para ' . $data['empresa']
        ]);
    } else {
        echo json_encode(['status' => 'inactive', 'message' => 'La licencia ha expirado o está suspendida.']);
    }
} else {
    echo json_encode(['status' => 'invalid', 'message' => 'Llave de licencia no válida.']);
}
?>
