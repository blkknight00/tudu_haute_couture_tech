<?php
require_once 'config.php';

// Reset password for user ID=1 (Eduardo/blkknight00)
$newPassword = 'Svetlana25.09';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = 1");
$stmt->execute([$hash]);

echo json_encode([
    'status' => 'success',
    'message' => "Password updated for user ID=1",
    'username' => 'blkknight00',
    'new_password' => $newPassword,
    'hash_preview' => substr($hash, 0, 20) . '...'
]);
