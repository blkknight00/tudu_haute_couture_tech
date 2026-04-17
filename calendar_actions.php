<?php
require_once 'config.php';
// Prevent PHP warnings from corrupting JSON output
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$usuario_actual = $_SESSION['usuario_id'];
$action = $_GET['action'] ?? '';

try {
    // --- OBTENER EVENTOS ---
    if ($action === 'get_events') {
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t');
        
        // Formatear fechas para MySQL (FullCalendar envía ISO8601 y MySQL puede fallar al comparar)
        try {
            $start = (new DateTime($start))->format('Y-m-d H:i:s');
            $end = (new DateTime($end))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Si falla, usamos los valores originales
        }

        $filtro_usuario = $_GET['user_id'] ?? null; // Si queremos ver el calendario de otro
        $tipo_filtro = $_GET['filter'] ?? 'confirmed'; // 'confirmed' o 'pending'

        $events = [];

        // --- CASO 1: SOLICITUDES PENDIENTES ---
        if ($tipo_filtro === 'pending') {
            $sql = "SELECT sc.*, 
                           u_sol.nombre as solicitante_nombre, 
                           u_rec.nombre as receptor_nombre
                    FROM solicitudes_cita sc
                    JOIN usuarios u_sol ON sc.solicitante_id = u_sol.id
                    JOIN usuarios u_rec ON sc.receptor_id = u_rec.id
                    WHERE (sc.receptor_id = ? OR sc.solicitante_id = ?) 
                    AND sc.estado = 'pendiente'
                    AND sc.fecha_propuesta BETWEEN ? AND ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_actual, $usuario_actual, $start, $end]);
            $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($solicitudes as $sol) {
                $es_receptor = ($sol['receptor_id'] == $usuario_actual);
                $titulo = $es_receptor 
                    ? "Solicitud de " . $sol['solicitante_nombre'] 
                    : "Solicitud a " . $sol['receptor_nombre'];
                
                $startObj = new DateTime($sol['fecha_propuesta']);
                $endObj = clone $startObj;
                $endObj->modify('+' . ($sol['duracion_minutos'] ?? 30) . ' minutes');

                $events[] = [
                    'id' => 'req-' . $sol['id'],
                    'title' => $titulo . ': ' . $sol['titulo'],
                    'start' => $startObj->format('Y-m-d H:i:s'),
                    'end' => $endObj->format('Y-m-d H:i:s'),
                    'color' => '#ffc107', // Amarillo/Naranja para pendientes
                    'textColor' => '#000000',
                    'extendedProps' => ['tipo' => 'solicitud', 'descripcion' => $sol['mensaje']]
                ];
            }
        } else {
            // --- CASO 2: EVENTOS CONFIRMADOS Y TAREAS CON VENCIMIENTO ---

            // A. OBTENER TAREAS CON FECHA DE VENCIMIENTO
            // Solo se muestran si no se está filtrando el calendario de otro usuario, para no saturar.
            if (!$filtro_usuario) {
                $sql_tareas = "SELECT t.id, t.titulo, t.fecha_termino, t.usuario_id, t.visibility, t.proyecto_id, t.estado, t.prioridad 
                               FROM tareas t
                               WHERE t.fecha_termino IS NOT NULL 
                               AND t.fecha_termino > '1000-01-01'
                               AND t.estado != 'archivado' 
                               AND (
                                   t.visibility = 'public' 
                                   OR t.usuario_id = ?
                                   OR EXISTS (SELECT 1 FROM tarea_asignaciones ta WHERE ta.tarea_id = t.id AND ta.usuario_id = ?)
                               )
                               AND t.fecha_termino BETWEEN ? AND ?";
                
                $stmt_tareas = $pdo->prepare($sql_tareas);
                $stmt_tareas->execute([$usuario_actual, $usuario_actual, $start, $end]);
                
                $hoy = new DateTime('today');
                
                foreach ($stmt_tareas->fetchAll(PDO::FETCH_ASSOC) as $tarea) {
                    $fecha_termino = new DateTime($tarea['fecha_termino']);
                    
                    // Lógica de colores y estados (Inspirado en DeepSeek + TuDu)
                    $color = '#17a2b8'; // Azul default (Info)
                    $prefix = '';
                    
                    if ($tarea['estado'] === 'completado') {
                        $color = '#28a745'; // Verde (Completada)
                        $prefix = '✓ ';
                    } elseif ($fecha_termino < $hoy) {
                        $color = '#dc3545'; // Rojo (Vencida)
                        $prefix = '⚠️ ';
                    } elseif ($fecha_termino == $hoy) {
                        $color = '#ffc107'; // Amarillo (Hoy)
                        $prefix = '📅 ';
                    } else {
                        // Pendiente futura, color por prioridad
                        switch($tarea['prioridad']) {
                            case 'alta': $color = '#fd7e14'; break; // Naranja
                            case 'media': $color = '#6f42c1'; break; // Morado
                            default: $color = '#6c757d'; break; // Gris
                        }
                    }

                    $events[] = [
                        'id' => 'task-' . $tarea['id'],
                        'title' => $prefix . $tarea['titulo'],
                        'start' => $tarea['fecha_termino'], // Se mostrará como un evento de todo el día
                        'allDay' => true,
                        'color' => $color,
                        'textColor' => '#FFFFFF',
                        'extendedProps' => [
                            'tipo' => 'tarea', 
                            'tarea_id' => $tarea['id'],
                            'proyecto_id' => $tarea['proyecto_id']
                        ]
                    ];
                }
            }

            // B. OBTENER EVENTOS (Lógica existente)
            $sql_eventos = "SELECT e.*, u.nombre as autor_nombre, u.foto_perfil 
                FROM eventos e 
                JOIN usuarios u ON e.usuario_id = u.id 
                WHERE e.start BETWEEN ? AND ?";
        
            $params_eventos = [$start, $end];

            if ($filtro_usuario) {
                $sql_eventos .= " AND e.usuario_id = ?";
                $params_eventos[] = $filtro_usuario;
            }

            $stmt_eventos = $pdo->prepare($sql_eventos);
            $stmt_eventos->execute($params_eventos);
            $raw_events = $stmt_eventos->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($raw_events as $ev) {
                $is_owner = ($ev['usuario_id'] == $usuario_actual);
                
                if (!$is_owner && $ev['privacidad'] === 'privado') {
                    $events[] = [
                        'id' => 'busy-' . $ev['id'], 'title' => 'Ocupado', 'start' => $ev['start'], 'end' => $ev['end'],
                        'color' => '#6c757d', 'display' => 'block', 'editable' => false, 'extendedProps' => ['is_private' => true]
                    ];
                } else {
                    $color = '#3788d8';
                    if ($ev['tipo_evento'] == 'reunion') $color = '#28a745';
                    if ($ev['tipo_evento'] == 'entrega') $color = '#dc3545';
                    if ($ev['tipo_evento'] == 'personal') $color = '#6f42c1';

                    $events[] = [
                        'id' => $ev['id'], 'title' => $ev['titulo'], 'start' => $ev['start'], 'end' => $ev['end'], 'color' => $color,
                        'extendedProps' => [
                            'descripcion' => $ev['descripcion'], 'tipo' => $ev['tipo_evento'], 'autor' => $ev['autor_nombre'],
                            'privacidad' => $ev['privacidad'], 'can_edit' => $is_owner,
                            'ubicacion_tipo' => $ev['ubicacion_tipo'], 'ubicacion_detalle' => $ev['ubicacion_detalle'],
                            'link_maps' => $ev['link_maps']
                        ]
                    ];
                }
            }
        }
        echo json_encode($events);
        exit;
    }

    // --- OBTENER UN SOLO EVENTO (Para Deep Linking) ---
    if ($action === 'get_event') {
        $id = $_GET['id'] ?? 0;
        
        $sql = "SELECT e.*, u.nombre as autor_nombre, u.foto_perfil 
                FROM eventos e 
                JOIN usuarios u ON e.usuario_id = u.id 
                WHERE e.id = ?";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($event) {
            echo json_encode(['success' => true, 'event' => $event]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Evento no encontrado']);
        }
        exit;
    }

    // --- GUARDAR EVENTO ---
    if ($action === 'save_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['event_id'] ?? '';
        $titulo = $_POST['titulo'];
        $start = $_POST['start'];
        $end = $_POST['end'];
        $tipo = $_POST['tipo_evento'];
        $privacidad = $_POST['privacidad'];
        $descripcion = $_POST['descripcion'] ?? '';
        
        // Campos de Ubicación
        $ubicacion_tipo = $_POST['ubicacion_tipo'] ?? 'oficina';
        $ubicacion_detalle = $_POST['ubicacion_detalle'] ?? '';
        $link_maps = $_POST['link_maps'] ?? '';
        
        // Verificar superposición (Overlap Check)
        // Excluir el evento actual si es edición
        $sqlCheck = "SELECT COUNT(*) FROM eventos WHERE usuario_id = ? AND (start < ? AND end > ?)";
        $paramsCheck = [$usuario_actual, $end, $start];
        
        if ($id) {
            $sqlCheck .= " AND id != ?";
            $paramsCheck[] = $id;
        }

        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute($paramsCheck);
        
        if ($stmtCheck->fetchColumn() > 0) {
           echo json_encode(['success' => false, 'error' => 'Ya tienes un evento programado en ese horario (o se solapa con otro). Por favor verifica tu disponibilidad.']);
           exit;
        }

        if ($id) {
            // Verificar propiedad antes de editar
            $stmtCheck = $pdo->prepare("SELECT usuario_id FROM eventos WHERE id = ?");
            $stmtCheck->execute([$id]);
            if ($stmtCheck->fetchColumn() != $usuario_actual) throw new Exception("No tienes permiso para editar este evento");
            
            $stmt = $pdo->prepare("UPDATE eventos SET titulo=?, descripcion=?, tipo_evento=?, start=?, end=?, privacidad=?, ubicacion_tipo=?, ubicacion_detalle=?, link_maps=? WHERE id=?");
            $stmt->execute([$titulo, $descripcion, $tipo, $start, $end, $privacidad, $ubicacion_tipo, $ubicacion_detalle, $link_maps, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO eventos (usuario_id, titulo, descripcion, tipo_evento, start, end, privacidad, ubicacion_tipo, ubicacion_detalle, link_maps) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_actual, $titulo, $descripcion, $tipo, $start, $end, $privacidad, $ubicacion_tipo, $ubicacion_detalle, $link_maps]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // --- ELIMINAR EVENTO ---
    if ($action === 'delete_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];
        
        // Verificar propiedad
        $stmtCheck = $pdo->prepare("SELECT usuario_id FROM eventos WHERE id = ?");
        $stmtCheck->execute([$id]);
        if ($stmtCheck->fetchColumn() != $usuario_actual) throw new Exception("No tienes permiso para eliminar este evento");

        $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // --- ACTUALIZAR FECHA DE VENCIMIENTO DE TAREA (Drag & Drop) ---
    if ($action === 'update_task_due_date' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $tarea_id = str_replace('task-', '', $_POST['id']);
        $nueva_fecha = $_POST['new_date'];

        if (!is_numeric($tarea_id)) {
            throw new Exception("ID de tarea no válido.");
        }

        $stmt = $pdo->prepare("UPDATE tareas SET fecha_termino = ? WHERE id = ?");
        $stmt->execute([$nueva_fecha, $tarea_id]);

        // Registrar en auditoría
        $stmt_info = $pdo->prepare("SELECT titulo FROM tareas WHERE id = ?");
        $stmt_info->execute([$tarea_id]);
        $titulo_tarea = $stmt_info->fetchColumn();
        registrarAuditoria($usuario_actual, 'UPDATE', 'tareas', $tarea_id, "Cambió la fecha de vencimiento de '{$titulo_tarea}' a {$nueva_fecha} desde el calendario.");

        echo json_encode(['success' => true]);
        exit;
    }

    // --- SOLICITAR CITA ---
    if ($action === 'request_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $receptor_id = $_POST['receptor_id'];
        $titulo = $_POST['titulo'];
        
        // Unificación: Soporta tanto 'start'/'end' de formEvento como 'fecha_propuesta'/'duracion' de lo viejo
        $startStr = $_POST['start'] ?? $_POST['fecha_propuesta'];
        $endStr = $_POST['end'] ?? null;
        $mensaje = $_POST['descripcion'] ?? $_POST['mensaje'] ?? ''; 
        $link_maps = $_POST['link_maps'] ?? '';

        if (!$startStr) throw new Exception("Fecha no especificada");

        $start = new DateTime($startStr);
        if ($endStr) {
            $end = new DateTime($endStr);
            $duracion = round(abs($end->getTimestamp() - $start->getTimestamp()) / 60);
        } else {
            $duracion = intval($_POST['duracion_minutos'] ?? 30);
            $end = clone $start;
            $end->modify("+$duracion minutes");
        }
        
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        // Verificar si el receptor tiene eventos que se solapen en ese horario
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE usuario_id = ? AND (start < ? AND end > ?)");
        $stmtCheck->execute([$receptor_id, $endStr, $startStr]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'El usuario ya tiene un evento programado en ese horario. Por favor selecciona otra hora.']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO solicitudes_cita (solicitante_id, receptor_id, titulo, mensaje, fecha_propuesta, duracion_minutos, link_maps) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_actual, $receptor_id, $titulo, $mensaje, $startStr, $duracion, $link_maps]);
        
        // Crear notificación para el receptor
        $nombre_solicitante = $_SESSION['usuario_nombre'];
        $msg_notif = "$nombre_solicitante te ha enviado una solicitud de cita para el $startStr";
        $stmt_notif = $pdo->prepare("INSERT INTO notificaciones (usuario_id_destino, usuario_id_origen, tipo, mensaje) VALUES (?, ?, 'cita', ?)");
        $stmt_notif->execute([$receptor_id, $usuario_actual, $msg_notif]);

        echo json_encode(['success' => true]);
        exit;
    }

    // --- OBTENER SOLICITUDES PENDIENTES ---
    if ($action === 'get_pending_appointments') {
        $stmt = $pdo->prepare("
            SELECT sc.*, u.nombre as solicitante_nombre, u.foto_perfil as solicitante_foto
            FROM solicitudes_cita sc
            JOIN usuarios u ON sc.solicitante_id = u.id
            WHERE sc.receptor_id = ? AND sc.estado = 'pendiente'
            ORDER BY sc.fecha_propuesta ASC
        ");
        $stmt->execute([$usuario_actual]);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $solicitudes]);
        exit;
    }

    // --- RESPONDER SOLICITUD ---
    if ($action === 'respond_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];
        $estado = $_POST['estado']; // 'aceptada' o 'rechazada'
        $motivo = trim($_POST['motivo'] ?? '');
        
        if (!in_array($estado, ['aceptada', 'rechazada'])) {
            throw new Exception("Estado no válido");
        }

        // Obtener la solicitud
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_cita WHERE id = ? AND receptor_id = ?");
        $stmt->execute([$id, $usuario_actual]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud) {
            throw new Exception("Solicitud no encontrada o no autorizada");
        }

        $pdo->beginTransaction();

        try {
            // Actualizar estado
            $stmtUpdate = $pdo->prepare("UPDATE solicitudes_cita SET estado = ? WHERE id = ?");
            $stmtUpdate->execute([$estado, $id]);

            // Si se acepta, crear eventos
            if ($estado === 'aceptada') {
                $start = new DateTime($solicitud['fecha_propuesta']);
                $end = clone $start;
                $end->modify('+' . ($solicitud['duracion_minutos'] ?? 30) . ' minutes');
                
                $startStr = $start->format('Y-m-d H:i:s');
                $endStr = $end->format('Y-m-d H:i:s');
                
                // 1. Evento para el solicitante
                $stmtEv1 = $pdo->prepare("INSERT INTO eventos (usuario_id, titulo, descripcion, tipo_evento, start, end, privacidad, link_maps) VALUES (?, ?, ?, 'reunion', ?, ?, 'privado', ?)");
                $stmtEv1->execute([
                    $solicitud['solicitante_id'], 
                    "Cita con " . $_SESSION['usuario_nombre'] . ": " . $solicitud['titulo'],
                    $solicitud['mensaje'],
                    $startStr,
                    $endStr,
                    $solicitud['link_maps']
                ]);

                // 2. Evento para el receptor (yo)
                $stmtUser = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
                $stmtUser->execute([$solicitud['solicitante_id']]);
                $nombreSolicitante = $stmtUser->fetchColumn();

                $stmtEv2 = $pdo->prepare("INSERT INTO eventos (usuario_id, titulo, descripcion, tipo_evento, start, end, privacidad, link_maps) VALUES (?, ?, ?, 'reunion', ?, ?, 'privado', ?)");
                $stmtEv2->execute([
                    $usuario_actual, 
                    "Cita con " . $nombreSolicitante . ": " . $solicitud['titulo'],
                    $solicitud['mensaje'],
                    $startStr,
                    $endStr,
                    $solicitud['link_maps']
                ]);
                
                // Guardar referencia del evento creado (del receptor)
                $eventoId = $pdo->lastInsertId();
                $pdo->prepare("UPDATE solicitudes_cita SET evento_id = ? WHERE id = ?")->execute([$eventoId, $id]);
            }

            // Notificar al solicitante
            $mensajeNotif = $_SESSION['usuario_nombre'] . " ha " . $estado . " tu solicitud de cita: " . $solicitud['titulo'];
            if ($estado === 'rechazada' && !empty($motivo)) {
                $mensajeNotif .= ". Motivo: " . $motivo;
            }
            $stmtNotif = $pdo->prepare("INSERT INTO notificaciones (usuario_id_destino, usuario_id_origen, tipo, mensaje) VALUES (?, ?, 'cita', ?)");
            $stmtNotif->execute([$solicitud['solicitante_id'], $usuario_actual, $mensajeNotif]);

            $pdo->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    // --- VERIFICAR DISPONIBILIDAD (AJAX) ---
    if ($action === 'check_availability') {
        $receptor_id = $_GET['receptor_id'] ?? 0;
        $fecha = $_GET['fecha'] ?? '';
        $duracion = intval($_GET['duracion'] ?? 30);
        
        if ($receptor_id && $fecha) {
            // Usamos la duración recibida
            $start = new DateTime($fecha);
            $end = clone $start;
            $end->modify("+$duracion minutes");
            
            $startStr = $start->format('Y-m-d H:i:s');
            $endStr = $end->format('Y-m-d H:i:s');

            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE usuario_id = ? AND (start < ? AND end > ?)");
            $stmtCheck->execute([$receptor_id, $endStr, $startStr]);
            
            echo json_encode(['busy' => ($stmtCheck->fetchColumn() > 0)]);
        } else {
            echo json_encode(['busy' => false]);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
