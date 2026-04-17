<?php
require_once 'config.php';


// Verificar que el usuario esté logueado
if (!isset($_SESSION['logged_in'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$tarea_id = $_GET['id'] ?? null;

if (!$tarea_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'ID de tarea no proporcionado']);
    exit();
}

// Aquí no es estrictamente necesario validar si el usuario tiene permiso sobre la tarea,
// pero en un sistema más complejo, se debería hacer.
$stmt = $pdo->prepare("SELECT id, titulo, descripcion, proyecto_id, usuario_id, visibility, fecha_termino, prioridad, estado FROM tareas WHERE id = ?");
$stmt->execute([$tarea_id]);
$tarea = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener usuarios asignados
$stmt_asig = $pdo->prepare("SELECT usuario_id FROM tarea_asignaciones WHERE tarea_id = ?");
$stmt_asig->execute([$tarea_id]);
$tarea['asignados'] = $stmt_asig->fetchAll(PDO::FETCH_COLUMN);

// Obtener etiquetas
$stmt_tags = $pdo->prepare("SELECT e.id, e.nombre FROM tarea_etiquetas te JOIN etiquetas e ON te.etiqueta_id = e.id WHERE te.tarea_id = ?");
$stmt_tags->execute([$tarea_id]);
$tags_data = $stmt_tags->fetchAll(PDO::FETCH_ASSOC);

$tarea['tags'] = array_column($tags_data, 'nombre');
$tarea['tags_ids'] = array_column($tags_data, 'id');
$tarea['tags_string'] = implode(', ', $tarea['tags']);

// Obtener archivos adjuntos
$stmt_files = $pdo->prepare("SELECT id, nombre_original, nombre_archivo, tipo_archivo, tamano, fecha_subida FROM archivos_adjuntos WHERE tarea_id = ?");
$stmt_files->execute([$tarea_id]);
$tarea['archivos'] = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

// Obtener notas (comentarios)
$stmt_notas = $pdo->prepare("SELECT n.*, u.nombre as usuario_nombre FROM notas_tareas n LEFT JOIN usuarios u ON n.usuario_id = u.id WHERE n.tarea_id = ? ORDER BY n.fecha_creacion ASC");
$stmt_notas->execute([$tarea_id]);
$tarea['notas'] = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);

if ($tarea) {
    header('Content-Type: application/json');
    echo json_encode($tarea);
} else {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Tarea no encontrada']);
}