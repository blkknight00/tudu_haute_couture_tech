<?php
require_once '../config.php';

// Ensure upload directory exists
$uploadDir = '../uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Auto-create table if not exists
$createTableSql = "CREATE TABLE IF NOT EXISTS recursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    filetype VARCHAR(100),
    size INT,
    uploaded_by INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE SET NULL
)";
try {
    $pdo->exec($createTableSql);
    // Alter table if it exists but missing column (quick fix for dev)
    // In production, use migrations. Here we try/catch silent.
    $pdo->exec("ALTER TABLE recursos ADD COLUMN tarea_id INT NULL AFTER id");
    $pdo->exec("ALTER TABLE recursos ADD FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE SET NULL");
} catch (PDOException $e) {
    // Silent fail or log? Proceeding.
}

$method = $_SERVER['REQUEST_METHOD'];
$org_id = $_SESSION['organizacion_id'] ?? 1;
$user_rol = $_SESSION['usuario_rol'] ?? '';
$is_global = ($user_rol === 'super_admin' || $user_rol === 'admin_global');

if ($method === 'GET') {
    try {
        $tarea_id = isset($_GET['tarea_id']) ? intval($_GET['tarea_id']) : null;
        if ($tarea_id) {
            $stmt = $pdo->prepare("SELECT * FROM recursos WHERE tarea_id = ? AND (organizacion_id = ? OR ? = 1) ORDER BY created_at DESC");
            $stmt->execute([$tarea_id, $org_id, $is_global ? 1 : 0]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM recursos WHERE (organizacion_id = ? OR ? = 1) ORDER BY created_at DESC");
            $stmt->execute([$org_id, $is_global ? 1 : 0]);
        }
        $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $resources]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $filename = basename($file['name']);
        
        // Simple sanitation
        $filename = preg_replace("/[^a-zA-Z0-9\.\-_]/", "_", $filename);
        $targetPath = $uploadDir . uniqid() . '_' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Save to DB
            $uploaded_by = $_POST['user_id'] ?? 0; // Or from session if available
            $tarea_id = !empty($_POST['tarea_id']) ? intval($_POST['tarea_id']) : null;
            
            try {
                $stmt = $pdo->prepare("INSERT INTO recursos (tarea_id, filename, filepath, filetype, size, uploaded_by, organizacion_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $tarea_id,
                    $filename, 
                    $targetPath, 
                    $file['type'], 
                    $file['size'], 
                    $uploaded_by,
                    $org_id
                ]);
                
                echo json_encode(['status' => 'success', 'message' => 'File uploaded']);
            } catch (PDOException $e) {
                echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    }
} elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if ($id) {
        try {
            // Get file path first
            $stmt = $pdo->prepare("SELECT filepath FROM recursos WHERE id = ? AND (organizacion_id = ? OR ? = 1)");
            $stmt->execute([$id, $org_id, $is_global ? 1 : 0]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($file) {
                if (file_exists($file['filepath'])) {
                    unlink($file['filepath']);
                }
                
                $delStmt = $pdo->prepare("DELETE FROM recursos WHERE id = ?");
                $delStmt->execute([$id]);
                
                echo json_encode(['status' => 'success', 'message' => 'File deleted']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'File not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID required']);
    }
}
?>
