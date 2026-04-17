<?php
require_once 'config.php';
session_start();

// Verificar login
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$usuario_id = $_GET['id'] ?? null;
$usuario_actual_id = $_SESSION['usuario_id'];
$usuario_actual_rol = $_SESSION['usuario_rol'];
$organizacion_id_actual = $_SESSION['organizacion_id'] ?? null;

// Permitir si es admin O si el usuario solicita sus propios datos
$tiene_permiso = false;
if ($usuario_actual_rol == 'super_admin') {
    $tiene_permiso = true;
} elseif ($usuario_id == $usuario_actual_id) {
    $tiene_permiso = true; // Un usuario siempre puede editarse a sí mismo
} elseif ($usuario_actual_rol == 'admin' && $organizacion_id_actual) {
    // Un admin de org puede editar a otros de su org
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM miembros_organizacion WHERE usuario_id = ? AND organizacion_id = ?");
    $stmt_check->execute([$usuario_id, $organizacion_id_actual]);
    if ($stmt_check->fetchColumn() > 0) {
        $tiene_permiso = true;
    }
}

if (!$tiene_permiso) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

if (!$usuario_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'ID de usuario no proporcionado']);
    exit();
}

$stmt = $pdo->prepare("SELECT id, nombre, username, email, telefono, rol, foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && $usuario_actual_rol == 'admin' && $organizacion_id_actual) {
    // Si el que pide es un admin de org, devolvemos el rol de la organización
    $stmt_org_rol = $pdo->prepare("SELECT rol_organizacion FROM miembros_organizacion WHERE usuario_id = ? AND organizacion_id = ?");
    $stmt_org_rol->execute([$usuario_id, $organizacion_id_actual]);
    $org_rol = $stmt_org_rol->fetchColumn();
    if ($org_rol) {
        $usuario['rol'] = $org_rol; // Sobreescribimos el rol global con el de la organización
    }
}

if ($usuario) {
    header('Content-Type: application/json');
    echo json_encode($usuario);
} else {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Usuario no encontrado']);
}
?>