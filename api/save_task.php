<?php
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// DEBUG LOGGING
file_put_contents('debug_save_task.log', date('Y-m-d H:i:s') . " - Input: " . print_r($data, true) . "\n", FILE_APPEND);

$usuario_actual = $_SESSION['usuario_id'];

// Data extraction
$id = $data['id'] ?? null;
$titulo = trim($data['titulo'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');
$proyecto_id = $data['proyecto_id'] ?? null;
$prioridad = $data['prioridad'] ?? 'media';
$estado = $data['estado'] ?? 'pendiente';
$fecha_termino = !empty($data['fecha_termino']) ? $data['fecha_termino'] : null;
$assignees = $data['assignees'] ?? []; // Array of user IDs
$tags = $data['tags'] ?? []; // Array of tag IDs

$visibility = $data['visibility'] ?? 'public'; // Default to public

if (empty($titulo) || empty($proyecto_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Título y Proyecto son obligatorios']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE tareas SET titulo=?, descripcion=?, proyecto_id=?, prioridad=?, estado=?, fecha_termino=?, visibility=?, fecha_actualizacion=NOW() WHERE id=?");
        $stmt->execute([$titulo, $descripcion, $proyecto_id, $prioridad, $estado, $fecha_termino, $visibility, $id]);
        $tarea_id = $id;
    } else {
        // Create
        $org_id = $_SESSION['organizacion_id'] ?? 1;
        $stmt = $pdo->prepare("INSERT INTO tareas (usuario_id, proyecto_id, titulo, descripcion, prioridad, estado, fecha_termino, fecha_creacion, fecha_actualizacion, visibility, organizacion_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)");
        $stmt->execute([$usuario_actual, $proyecto_id, $titulo, $descripcion, $prioridad, $estado, $fecha_termino, $visibility, $org_id]);
        $tarea_id = $pdo->lastInsertId();
    }

    // Handle Assignments
    // First delete existing
    $pdo->prepare("DELETE FROM tarea_asignaciones WHERE tarea_id = ?")->execute([$tarea_id]);
    
    // Then re-insert
    if (!empty($assignees)) {
        $stmtAssign = $pdo->prepare("INSERT INTO tarea_asignaciones (tarea_id, usuario_id) VALUES (?, ?)");
        foreach ($assignees as $uid) {
            $stmtAssign->execute([$tarea_id, $uid]);
        }
    }

    // Handle Tags (Etiquetas)
    $pdo->prepare("DELETE FROM tarea_etiquetas WHERE tarea_id = ?")->execute([$tarea_id]);
    
    if (!empty($tags)) {
        $stmtTags = $pdo->prepare("INSERT INTO tarea_etiquetas (tarea_id, etiqueta_id) VALUES (?, ?)");
        foreach ($tags as $tagId) {
            $stmtTags->execute([$tarea_id, $tagId]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'id' => $tarea_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
