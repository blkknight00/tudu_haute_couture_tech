<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) {
    exit(json_encode([]));
}

$stmt = $pdo->prepare("SELECT n.*, 
                       CASE WHEN n.tipo = 'vencimiento' THEN '📅 Recordatorio' ELSE u.nombre END as origen_nombre 
                       FROM notificaciones n 
                       JOIN usuarios u ON n.usuario_id_origen = u.id 
                       WHERE n.usuario_id_destino = ? AND n.leido = 0 
                       ORDER BY n.fecha_creacion DESC");
$stmt->execute([$_SESSION['usuario_id']]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>