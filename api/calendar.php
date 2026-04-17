<?php
require_once '../config.php';
require_once 'auth_middleware.php';

$user = checkAuth();

$usuario_id = $user['id'];
$usuario_nombre = $user['nombre'];
$rol = $user['rol'];

$action = $_GET['action'] ?? 'get_events'; 
$org_id = $_SESSION['organizacion_id'] ?? 1;
$is_global = ($rol === 'super_admin' || $rol === 'admin_global');
try {
    // --- OBTENER EVENTOS (TAREAS + EVENTOS + SOLICITUDES) ---
    if ($action === 'get_events') {
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t');
        
        try {
            $start = (new DateTime($start))->format('Y-m-d H:i:s');
            $end = (new DateTime($end))->format('Y-m-d H:i:s');
        } catch (Exception $e) {}

        $filtro_usuario = $_GET['user_id'] ?? null;
        $proyecto_id = $_GET['proyecto_id'] ?? null;
        $tipo_filtro = $_GET['filter'] ?? 'confirmed'; // 'confirmed' or 'pending'

        $events = [];

        // 1. SOLICITUDES PENDIENTES
        if ($tipo_filtro === 'pending') {
            $sql = "SELECT sc.*, 
                           u_sol.nombre as solicitante_nombre, 
                           u_rec.nombre as receptor_nombre
                    FROM solicitudes_cita sc
                    JOIN usuarios u_sol ON sc.solicitante_id = u_sol.id
                    JOIN usuarios u_rec ON sc.receptor_id = u_rec.id
                    WHERE (sc.organizacion_id = ? OR ? = 1) 
                    AND (sc.receptor_id = ? OR sc.solicitante_id = ?) 
                    AND sc.estado = 'pendiente'
                    AND sc.fecha_propuesta BETWEEN ? AND ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$org_id, $is_global ? 1 : 0, $usuario_id, $usuario_id, $start, $end]);
            $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($solicitudes as $sol) {
                $es_receptor = ($sol['receptor_id'] == $usuario_id);
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
                    'color' => '#ffc107',
                    'textColor' => '#000000',
                    'extendedProps' => [
                        'tipo' => 'solicitud', 
                        'descripcion' => $sol['mensaje'],
                        'es_receptor' => $es_receptor,
                        'solicitante_nombre' => $sol['solicitante_nombre'],
                        'receptor_nombre' => $sol['receptor_nombre'],
                        'link_maps' => $sol['link_maps']
                    ]
                ];
            }
        } else {
            // 2. TAREAS CON FECHA — always show tasks for this user (owner or assignee)
            $sql_tareas = "SELECT t.id, t.titulo, t.fecha_termino, t.usuario_id, t.visibility, t.proyecto_id, t.estado, t.prioridad 
                           FROM tareas t
                           WHERE t.fecha_termino IS NOT NULL 
                           AND t.fecha_termino > '1000-01-01'
                           AND t.estado != 'archivado' 
                           AND (
                               t.usuario_id = ?
                               OR EXISTS (SELECT 1 FROM tarea_asignaciones ta WHERE ta.tarea_id = t.id AND ta.usuario_id = ?)
                               OR (t.visibility = 'public' AND (t.organizacion_id = ? OR ? = 1))
                           )
                           AND DATE(t.fecha_termino) BETWEEN DATE(?) AND DATE(?)";
            
            $params_tareas = [$usuario_id, $usuario_id, $org_id ?? 0, $is_global ? 1 : 0, $start, $end];
            
            if ($proyecto_id) {
                $sql_tareas .= " AND t.proyecto_id = ?";
                $params_tareas[] = $proyecto_id;
            }

            $stmt_tareas = $pdo->prepare($sql_tareas);
            $stmt_tareas->execute($params_tareas);
            
            $hoy = new DateTime('today');
            
            foreach ($stmt_tareas->fetchAll(PDO::FETCH_ASSOC) as $tarea) {
                $fecha_termino = new DateTime($tarea['fecha_termino']);
                
                $color = '#17a2b8'; 
                $prefix = '';
                
                if ($tarea['estado'] === 'completado') {
                    $color = '#22c55e'; // Green
                    $prefix = '✓ ';
                } elseif ($fecha_termino < $hoy) {
                    $color = '#ef4444'; // Red — overdue
                    $prefix = '⚠️ ';
                } elseif ($fecha_termino->format('Y-m-d') == $hoy->format('Y-m-d')) {
                    $color = '#f59e0b'; // Amber — due today
                    $prefix = '📅 ';
                } else {
                    switch($tarea['prioridad']) {
                        case 'alta': $color = '#f97316'; break; // Orange
                        case 'media': $color = '#8b5cf6'; break; // Violet
                        default: $color = '#6b7280'; break; // Gray
                    }
                }

                $events[] = [
                    'id' => 'task-' . $tarea['id'],
                    'title' => $prefix . $tarea['titulo'],
                    'start' => substr($tarea['fecha_termino'], 0, 10), // date-only for allDay
                    'allDay' => true,
                    'color' => $color,
                    'textColor' => '#FFFFFF',
                    'extendedProps' => [
                        'tipo' => 'tarea', 
                        'tarea_id' => $tarea['id'],
                        'proyecto_id' => $tarea['proyecto_id'],
                        'status' => $tarea['estado']
                    ]
                ];
            }

            // 2b. OVERDUE TASKS — show tasks that are past due but outside calendar view range
            $sql_overdue = "SELECT t.id, t.titulo, t.fecha_termino, t.usuario_id, t.visibility, t.proyecto_id, t.estado, t.prioridad 
                            FROM tareas t
                            WHERE t.fecha_termino IS NOT NULL 
                            AND t.fecha_termino > '1000-01-01'
                            AND t.estado NOT IN ('archivado', 'completado')
                            AND DATE(t.fecha_termino) < DATE(?)
                            AND (
                                t.usuario_id = ?
                                OR EXISTS (SELECT 1 FROM tarea_asignaciones ta WHERE ta.tarea_id = t.id AND ta.usuario_id = ?)
                            )";
            $stmt_overdue = $pdo->prepare($sql_overdue);
            $stmt_overdue->execute([$start, $usuario_id, $usuario_id]);

            foreach ($stmt_overdue->fetchAll(PDO::FETCH_ASSOC) as $tarea) {
                // Show on their actual overdue date, but visually mark them
                $events[] = [
                    'id' => 'task-' . $tarea['id'],
                    'title' => '⚠️ ' . $tarea['titulo'],
                    'start' => substr($tarea['fecha_termino'], 0, 10),
                    'allDay' => true,
                    'color' => '#ef4444',
                    'textColor' => '#FFFFFF',
                    'extendedProps' => [
                        'tipo' => 'tarea',
                        'tarea_id' => $tarea['id'],
                        'proyecto_id' => $tarea['proyecto_id'],
                        'status' => $tarea['estado']
                    ]
                ];
            }

            // 3. EVENTOS — show own events (no organizacion_id column in eventos table)
            $sql_eventos = "SELECT e.*, u.nombre as autor_nombre, u.foto_perfil 
                FROM eventos e 
                JOIN usuarios u ON e.usuario_id = u.id 
                WHERE e.usuario_id = ?
                AND e.start BETWEEN ? AND ?";
        
            $params_eventos = [$usuario_id, $start, $end];

            if ($filtro_usuario) {
                $sql_eventos .= " AND e.usuario_id = ?";
                $params_eventos[] = $filtro_usuario;
            }

            $stmt_eventos = $pdo->prepare($sql_eventos);
            $stmt_eventos->execute($params_eventos);
            
            foreach ($stmt_eventos->fetchAll(PDO::FETCH_ASSOC) as $ev) {
                $is_owner = ($ev['usuario_id'] == $usuario_id);
                
                if (!$is_owner && $ev['privacidad'] === 'privado') {
                    $events[] = [
                        'id' => 'busy-' . $ev['id'], 'title' => 'Ocupado', 'start' => $ev['start'], 'end' => $ev['end'],
                        'color' => '#6c757d', 'display' => 'block', 'editable' => false, 'extendedProps' => ['is_private' => true]
                    ];
                } else {
                    $color = '#3b82f6'; // Blue
                    if ($ev['tipo_evento'] == 'reunion') $color = '#10b981'; // Emerald
                    if ($ev['tipo_evento'] == 'entrega') $color = '#ef4444'; // Red
                    if ($ev['tipo_evento'] == 'personal') $color = '#8b5cf6'; // Violet

                    $events[] = [
                        'id' => $ev['id'], 
                        'title' => $ev['titulo'], 
                        'start' => $ev['start'], 
                        'end' => $ev['end'], 
                        'color' => $color,
                        'extendedProps' => [
                            'descripcion' => $ev['descripcion'], 'tipo' => $ev['tipo_evento'], 'autor' => $ev['autor_nombre'],
                            'privacidad' => $ev['privacidad'], 'can_edit' => $is_owner,
                            'ubicacion_tipo' => $ev['ubicacion_tipo'], 'ubicacion_detalle' => $ev['ubicacion_detalle'],
                            'link_maps' => $ev['link_maps']
                        ]
                    ];
                }
            }

            // 4. CITAS ACEPTADAS — show accepted appointment requests on confirmed view
            $sql_citas_aceptadas = "SELECT sc.*, 
                       u_sol.nombre as solicitante_nombre, 
                       u_rec.nombre as receptor_nombre
                FROM solicitudes_cita sc
                JOIN usuarios u_sol ON sc.solicitante_id = u_sol.id
                JOIN usuarios u_rec ON sc.receptor_id = u_rec.id
                WHERE (sc.solicitante_id = ? OR sc.receptor_id = ?)
                AND sc.estado = 'aceptada'
                AND sc.fecha_propuesta BETWEEN ? AND ?";

            $stmt_citas = $pdo->prepare($sql_citas_aceptadas);
            $stmt_citas->execute([$usuario_id, $usuario_id, $start, $end]);

            foreach ($stmt_citas->fetchAll(PDO::FETCH_ASSOC) as $cita) {
                $es_receptor = ($cita['receptor_id'] == $usuario_id);
                $otra_parte = $es_receptor ? $cita['solicitante_nombre'] : $cita['receptor_nombre'];
                $startObj = new DateTime($cita['fecha_propuesta']);
                $endObj = clone $startObj;
                $endObj->modify('+' . ($cita['duracion_minutos'] ?? 30) . ' minutes');

                $events[] = [
                    'id' => 'cita-' . $cita['id'],
                    'title' => '📅 Cita con ' . $otra_parte . ': ' . $cita['titulo'],
                    'start' => $startObj->format('Y-m-d H:i:s'),
                    'end' => $endObj->format('Y-m-d H:i:s'),
                    'color' => '#0ea5e9', // Sky blue for accepted appointments
                    'textColor' => '#FFFFFF',
                    'extendedProps' => [
                        'tipo' => 'cita',
                        'descripcion' => $cita['mensaje'],
                        'es_receptor' => $es_receptor,
                        'link_maps' => $cita['link_maps']
                    ]
                ];
            }
        }

        echo json_encode(['status' => 'success', 'data' => $events]);
    }
    
    // --- GUARDAR EVENTO ---
    elseif ($action === 'save_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Read JSON input since React sends JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST; // Fallback to POST if needed

        $id = $input['event_id'] ?? null;
        $titulo = $input['titulo'];
        $start = $input['start'];
        $end = $input['end'];
        $tipo = $input['tipo_evento'];
        $privacidad = $input['privacidad'];
        $descripcion = $input['descripcion'] ?? '';
        $ubicacion_tipo = $input['ubicacion_tipo'] ?? 'oficina';
        $ubicacion_detalle = $input['ubicacion_detalle'] ?? '';
        $link_maps = $input['link_maps'] ?? '';

        // Overlap Check
        $sqlCheck = "SELECT COUNT(*) FROM eventos WHERE usuario_id = ? AND (start < ? AND end > ?)";
        $paramsCheck = [$usuario_id, $end, $start];
        if ($id) {
            $sqlCheck .= " AND id != ?";
            $paramsCheck[] = $id;
        }
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute($paramsCheck);
        
        if ($stmtCheck->fetchColumn() > 0) {
           echo json_encode(['status' => 'error', 'message' => 'Ya tienes un evento programado en ese horario.']);
           exit;
        }

        if ($id) {
            // Check ownership
            $stmtCheck = $pdo->prepare("SELECT usuario_id FROM eventos WHERE id = ?");
            $stmtCheck->execute([$id]);
            $owner_id = $stmtCheck->fetchColumn();
            if ($owner_id != $usuario_id && !$is_global) throw new Exception("No tienes permiso.");
            
            $stmt = $pdo->prepare("UPDATE eventos SET titulo=?, descripcion=?, tipo_evento=?, start=?, end=?, privacidad=?, ubicacion_tipo=?, ubicacion_detalle=?, link_maps=? WHERE id=?");
            $stmt->execute([$titulo, $descripcion, $tipo, $start, $end, $privacidad, $ubicacion_tipo, $ubicacion_detalle, $link_maps, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO eventos (usuario_id, titulo, descripcion, tipo_evento, start, end, privacidad, ubicacion_tipo, ubicacion_detalle, link_maps, organizacion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $titulo, $descripcion, $tipo, $start, $end, $privacidad, $ubicacion_tipo, $ubicacion_detalle, $link_maps, $org_id]);
        }

        echo json_encode(['status' => 'success']);
    }

    // --- ELIMINAR EVENTO ---
    elseif ($action === 'delete_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'];

        $stmtCheck = $pdo->prepare("SELECT usuario_id FROM eventos WHERE id = ?");
        $stmtCheck->execute([$id]);
        $owner_id = $stmtCheck->fetchColumn();
        if ($owner_id != $usuario_id && !$is_global) throw new Exception("No tienes permiso.");

        $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
    }

    // --- SOLICITAR CITA ---
    elseif ($action === 'request_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $receptor_id = $input['receptor_id'];
        $titulo = $input['titulo'];
        $startStr = $input['start'];
        $duracion = intval($input['duracion_minutos'] ?? 30);
        $mensaje = $input['mensaje'] ?? ''; 
        $link_maps = $input['link_maps'] ?? '';

        $start = new DateTime($startStr);
        $end = clone $start;
        $end->modify("+$duracion minutes");
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        // Check availability
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE usuario_id = ? AND (start < ? AND end > ?)");
        $stmtCheck->execute([$receptor_id, $endStr, $startStr]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'El usuario ya tiene un evento en ese horario.']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO solicitudes_cita (solicitante_id, receptor_id, titulo, mensaje, fecha_propuesta, duracion_minutos, link_maps, organizacion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $receptor_id, $titulo, $mensaje, $startStr, $duracion, $link_maps, $org_id]);
        
        $msg_notif = "$usuario_nombre te ha enviado una solicitud de cita para el $startStr";
        $stmt_notif = $pdo->prepare("INSERT INTO notificaciones (usuario_id_destino, usuario_id_origen, tipo, mensaje) VALUES (?, ?, 'cita', ?)");
        $stmt_notif->execute([$receptor_id, $usuario_id, $msg_notif]);

        echo json_encode(['status' => 'success']);
    }

    // --- ELIMINAR/CANCELAR SOLICITUD (POR SOLICITANTE) ---
    elseif ($action === 'delete_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
        $id = $input['id'] ?? null;

        if (!$id) throw new Exception("ID es requerido.");

        $stmt = $pdo->prepare("SELECT * FROM solicitudes_cita WHERE id = ? AND solicitante_id = ?");
        $stmt->execute([$id, $usuario_id]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud) throw new Exception("Solicitud no encontrada o no tienes permiso.");

        $stmtDelete = $pdo->prepare("DELETE FROM solicitudes_cita WHERE id = ?");
        $stmtDelete->execute([$id]);

        echo json_encode(['status' => 'success']);
    }

    // --- RESPONDER SOLICITUD ---
    elseif ($action === 'respond_appointment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $id = $input['id'] ?? null;
        $estado = $input['estado'] ?? null; // 'aceptada' or 'rechazada'
        
        if (!$id || !$estado) {
            throw new Exception("ID y estado son requeridos.");
        }

        $stmt = $pdo->prepare("SELECT * FROM solicitudes_cita WHERE id = ? AND receptor_id = ?");
        $stmt->execute([$id, $usuario_id]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$solicitud) throw new Exception("Solicitud no encontrada o no tienes permiso.");

        $pdo->beginTransaction();
        try {
            error_log("Responding to appointment $id with status $estado for user $usuario_id");
            $stmtUpdate = $pdo->prepare("UPDATE solicitudes_cita SET estado = ? WHERE id = ?");
            $stmtUpdate->execute([$estado, $id]);
            error_log("Update for solicitudes_cita executed. Rows affected: " . $stmtUpdate->rowCount());

            if ($estado === 'aceptada') {
                $start = new DateTime($solicitud['fecha_propuesta']);
                $end = clone $start;
                $end->modify('+' . ($solicitud['duracion_minutos'] ?? 30) . ' minutes');
                $startStr = $start->format('Y-m-d H:i:s');
                $endStr = $end->format('Y-m-d H:i:s');
                
                // Event for recipient (me)
                $stmtUser = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
                $stmtUser->execute([$solicitud['solicitante_id']]);
                $nombreSolicitante = $stmtUser->fetchColumn();

                $stmtEv2 = $pdo->prepare("INSERT INTO eventos (usuario_id, titulo, descripcion, tipo_evento, start, end, privacidad, link_maps) VALUES (?, ?, ?, 'reunion', ?, ?, 'privado', ?)");
                $stmtEv2->execute([$usuario_id, "Cita con $nombreSolicitante: " . $solicitud['titulo'], $solicitud['mensaje'], $startStr, $endStr, $solicitud['link_maps']]);
                $evento_id = $pdo->lastInsertId();

                // Event for requester
                $stmtEv1 = $pdo->prepare("INSERT INTO eventos (usuario_id, titulo, descripcion, tipo_evento, start, end, privacidad, link_maps) VALUES (?, ?, ?, 'reunion', ?, ?, 'privado', ?)");
                $stmtEv1->execute([$solicitud['solicitante_id'], "Cita con $usuario_nombre: " . $solicitud['titulo'], $solicitud['mensaje'], $startStr, $endStr, $solicitud['link_maps']]);

                // Link evento_id to the request
                $stmtLink = $pdo->prepare("UPDATE solicitudes_cita SET evento_id = ? WHERE id = ?");
                $stmtLink->execute([$evento_id, $id]);
            }

            $msg = "$usuario_nombre ha $estado tu solicitud de cita.";
            $stmtNotif = $pdo->prepare("INSERT INTO notificaciones (usuario_id_destino, usuario_id_origen, tipo, mensaje) VALUES (?, ?, 'cita', ?)");
            $stmtNotif->execute([$solicitud['solicitante_id'], $usuario_id, $msg]);

            $pdo->commit();
            error_log("Transaction committed for appointment $id");
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Transaction failed in respond_appointment: " . $e->getMessage());
            throw $e;
        }
    }

    // --- ACTUALIZAR FECHA VENCIMIENTO TAREA (Drag & Drop) ---
    elseif ($action === 'update_task_due_date' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $tarea_id = $input['id'];
        $fecha = $input['fecha_termino'];

        // Solo permitir actualizar fecha si el usuario tiene permiso (asumimos que sí si puede verla)
        // Idealmente validar permisos más estrictos.
        $stmt = $pdo->prepare("UPDATE tareas SET fecha_termino = ? WHERE id = ?");
        $stmt->execute([$fecha, $tarea_id]);
        
        echo json_encode(['status' => 'success']);
    }

    // --- CHECK AVAILABILITY ---
    elseif ($action === 'check_availability') {
        $receptor_id = $_GET['receptor_id'] ?? 0;
        $fecha = $_GET['fecha'] ?? '';
        $duracion = intval($_GET['duracion'] ?? 30);
        
        if ($receptor_id && $fecha) {
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
    }

    // --- GET SUMMARY (Conteo Vencidas/Hoy) ---
    elseif ($action === 'get_summary') {
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t');
        
        // Vencidas: Fecha termino < Hoy AND Estado != completado/archivado
        $sqlVencidas = "SELECT COUNT(*) FROM tareas 
                        WHERE usuario_id = ? 
                        AND fecha_termino < CURDATE() 
                        AND estado NOT IN ('completado', 'archivado')";
        $stmtVencidas = $pdo->prepare($sqlVencidas);
        $stmtVencidas->execute([$usuario_id]);
        $vencidas = $stmtVencidas->fetchColumn();

        // Hoy: Fecha termino = Hoy AND Estado != completado/archivado
        $sqlHoy = "SELECT COUNT(*) FROM tareas 
                   WHERE usuario_id = ? 
                   AND fecha_termino = CURDATE() 
                   AND estado NOT IN ('completado', 'archivado')";
        $stmtHoy = $pdo->prepare($sqlHoy);
        $stmtHoy->execute([$usuario_id]);
        $hoy = $stmtHoy->fetchColumn();

        echo json_encode(['status' => 'success', 'data' => ['vencidas' => $vencidas, 'hoy' => $hoy]]);
    }
    
    else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
