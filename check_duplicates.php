<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("SELECT id, username, email FROM usuarios WHERE id IN (SELECT id FROM usuarios GROUP BY id HAVING COUNT(*) > 1) ORDER BY id");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($duplicates)) {
        echo "No duplicates found (weird, maybe error message was misleading?)";
    } else {
        echo "Duplicates found:\n";
        foreach ($duplicates as $row) {
            echo "ID: {$row['id']} | User: {$row['username']} | Email: {$row['email']}\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
