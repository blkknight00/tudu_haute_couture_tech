<?php
// CORS is already handled in config.php which is required below.
require_once '../config.php';

// Auto-create table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ajustes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clave VARCHAR(50) NOT NULL UNIQUE,
        valor TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // Continue, assume table might exist or error will be caught later
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT clave, valor FROM ajustes");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Only hide the VAPID private PEM key (too sensitive and never needed in UI)
        $sensitiveKeys = ['vapid_private_pem'];
        foreach ($sensitiveKeys as $k) {
            if (isset($settings[$k])) {
                $settings[$k] = !empty($settings[$k]) ? '***configured***' : '';
            }
        }

        echo json_encode([
            'status' => 'success',
            'data'   => $settings,
        ]);
    } 
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($input)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO ajustes (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        
        $pdo->beginTransaction();
        foreach ($input as $key => $value) {
            // Do not save the placeholder back to the database
            if ($value === '***configured***') continue;
            $stmt->execute([$key, $value, $value]);
        }
        $pdo->commit();

        echo json_encode(['status' => 'success']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
