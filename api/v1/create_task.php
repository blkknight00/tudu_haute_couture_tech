<?php
// api/v1/create_task.php
require_once 'config_api.php';

// 1. Autenticación
$appData = authenticateApi($pdo);

// 2. Verificar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// 3. Obtener Datos
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit();
}

// 4. Validar Campos Requeridos
if (empty($input['title'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Field "title" is required']);
    exit();
}

// 5. Preparar Datos
$titulo = trim($input['title']);
$descripcion = isset($input['description']) ? trim($input['description']) : '';
// Añadir info de origen a la descripción si se provee external_id
if (!empty($input['external_id'])) {
    $descripcion .= "\n\n[Importado de {$appData['app_name']}: {$input['external_id']}]";
}

$proyecto_id = !empty($input['project_id']) ? (int)$input['project_id'] : null; // null = Sin proyecto
// Si se envía project_id, verificar que exista (opcional, por ahora lo dejamos pasar si es nulo)

$fecha_termino = !empty($input['due_date']) ? $input['due_date'] : null;
$prioridad = !empty($input['priority']) && in_array(strtolower($input['priority']), ['alta', 'media', 'baja']) ? strtolower($input['priority']) : 'media';

// Usuario por defecto: Asignar al Super Admin o al primer usuario disponible si no se especifica
// En una integración real, quizás quieras pasar un 'assigned_user_email' en el payload.
// Por ahora, asignamos al usuario ID 1 (asumiendo que es el admin principal) o dejamos null.
$usuario_creador = 1; // Asumimos ID 1 es el sistema/admin. Ajustar según necesidad.

try {
    // 6. Insertar Tarea
    $sql = "INSERT INTO tareas (
                titulo, 
                descripcion, 
                estado, 
                proyecto_id, 
                usuario_id, 
                prioridad, 
                fecha_termino, 
                visibility, 
                fecha_creacion
            ) VALUES (?, ?, 'pendiente', ?, ?, ?, ?, 'public', NOW())"; // Visibility public por defecto para integraciones empresariales
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $titulo,
        $descripcion,
        $proyecto_id,
        $usuario_creador,
        $prioridad,
        $fecha_termino
    ]);

    $tarea_id = $pdo->lastInsertId();

    // 7. Respuesta Exitosa
    http_response_code(201); // Created
    echo json_encode([
        'success' => true,
        'message' => 'Task created successfully',
        'task_id' => $tarea_id,
        'source' => $appData['app_name']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal Server Error: ' . $e->getMessage()]);
}
?>
