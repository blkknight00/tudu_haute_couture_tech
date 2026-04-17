<?php

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $tarea_id = $_GET['tarea_id'] ?? null;
        if (!$tarea_id) {
            echo json_encode(['status' => 'error', 'message' => 'Falta tarea_id']);
            exit;
        }

        // Separate endpoint: fetch file attachments
        if (($_GET['action'] ?? '') === 'files') {
            $stmt = $pdo->prepare(
                "SELECT id, nombre_original, nombre_archivo, tipo_archivo, tamano, fecha_subida
                 FROM archivos_adjuntos
                 WHERE tarea_id = ?
                 ORDER BY fecha_subida ASC"
            );
            $stmt->execute([$tarea_id]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $files]);
            exit;
        }

        // Fetch comments with user info (LEFT JOIN to include guest comments where usuario_id IS NULL)
        $sql = "SELECT n.*, u.nombre as user_name, u.foto_perfil as user_avatar, u.username as user_username,
                       COALESCE(u.nombre, n.nombre_invitado, 'Invitado') as display_name
                FROM notas_tareas n
                LEFT JOIN usuarios u ON n.usuario_id = u.id
                WHERE n.tarea_id = ?
                ORDER BY n.fecha_creacion ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tarea_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $comments]);

    } elseif ($method === 'POST') {
        // Check if it's a multipart request (file upload) or JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if ($data) {
            // JSON request
            $tarea_id = $data['tarea_id'] ?? null;
            $nota = trim($data['nota'] ?? '');
        } else {
            // Multipart/form-data
            $tarea_id = $_POST['tarea_id'] ?? null;
            $nota = trim($_POST['nota'] ?? '');
        }

        // Handle File Upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (in_array($file['type'], $allowedTypes)) {
                $uploadDir = '../uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9\.\-_]/", "_", basename($file['name']));
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Start on a new line if there is text
                    if (!empty($nota)) {
                        $nota .= "\n";
                    }
                    // Append URL
                    // Assuming API is at /tudu_development/api/ and uploads at /tudu_development/uploads/
                    // We construct a relative URL that the frontend can resolve or a full URL if we knew the host.
                    // For now, let's just append the filename and let frontend handle or absolute path.
                    // Better: Store relative path that frontend's formatter picks up.
                    // The frontend formatter detects http(s), so let's try to build a full URL request scheme.
                    
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $host = $_SERVER['HTTP_HOST'];
                    // We assume the script is in /something/api/comments.php, so uploads is ../uploads
                    $scriptDir = dirname($_SERVER['SCRIPT_NAME']); // /tudu_development/api
                    $baseDir = dirname($scriptDir); // /tudu_development
                    
                    $fileUrl = $protocol . $host . $baseDir . '/uploads/' . $filename;
                    
                    $nota .= $fileUrl;
                }
            }
        }

        if (!$tarea_id || (!$nota && !isset($_FILES['file']))) {
            echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO notas_tareas (tarea_id, usuario_id, nota, fecha_creacion) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$tarea_id, $_SESSION['usuario_id'], $nota]);

        // Return the new comment with user info
        $newId = $pdo->lastInsertId();
        $sql = "SELECT n.*, u.nombre as user_name, u.foto_perfil as user_avatar, u.username as user_username
                FROM notas_tareas n
                JOIN usuarios u ON n.usuario_id = u.id
                WHERE n.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newId]);
        $newComment = $stmt->fetch(PDO::FETCH_ASSOC);

        // --- Handle Mentions ---
        // Extract all @usernames
        preg_match_all('/@([\w\._-]+)/', $nota, $matches);
        if (!empty($matches[1])) {
            $mentioned_usernames = array_unique($matches[1]);
            
            // Prepare statement to find user IDs
            // We'll verify they exist and get their IDs
            $placeholders = implode(',', array_fill(0, count($mentioned_usernames), '?'));
            $sqlUsers = "SELECT id, username FROM usuarios WHERE username IN ($placeholders)";
            $stmtUsers = $pdo->prepare($sqlUsers);
            $stmtUsers->execute(array_values($mentioned_usernames));
            $mentioned_users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

            // Insert notifications
            $sqlNotify = "INSERT INTO notificaciones (usuario_id_destino, usuario_id_origen, tipo, tarea_id, mensaje, leido, fecha_creacion) VALUES (?, ?, 'mencion', ?, ?, 0, NOW())";
            $stmtNotify = $pdo->prepare($sqlNotify);

            foreach ($mentioned_users as $user) {
                // Don't notify yourself
                if ($user['id'] != $_SESSION['usuario_id']) {
                    $mensaje = "Te mencionó en un comentario";
                    $stmtNotify->execute([$user['id'], $_SESSION['usuario_id'], $tarea_id, $mensaje]);
                }
            }
        }
        // -----------------------

        echo json_encode(['status' => 'success', 'data' => $newComment]);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
