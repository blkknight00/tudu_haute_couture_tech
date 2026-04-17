<?php
/**
 * ONE-TIME setup for production server.
 * 1. Generates VAPID keys for Web Push
 * 2. Seeds ElevenLabs API key + voice ID
 *
 * UPLOAD this file, visit it ONCE, then DELETE IT IMMEDIATELY.
 */
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/WebPush.php';

$results = [];

// ── 1. VAPID Keys ──────────────────────────────────────────────────────────
$existingVapid = $pdo->query("SELECT valor FROM ajustes WHERE clave = 'vapid_public_base64' LIMIT 1")->fetchColumn();
if ($existingVapid) {
    $results['vapid'] = ['status' => 'already_exists', 'public' => $existingVapid];
} else {
    try {
        $keys = WebPush::generateVapidKeys();
        $ins  = $pdo->prepare("INSERT INTO ajustes (clave,valor) VALUES(?,?) ON DUPLICATE KEY UPDATE valor=?");
        $ins->execute(['vapid_private_pem',   $keys['private_pem'],      $keys['private_pem']]);
        $ins->execute(['vapid_public_base64', $keys['public_base64url'], $keys['public_base64url']]);
        $results['vapid'] = ['status' => 'created', 'public' => $keys['public_base64url']];
    } catch (Exception $e) {
        $results['vapid'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// ── 2. ElevenLabs Key ─────────────────────────────────────────────────────
try {
    $elKey     = '8530c580f98fe1f11c4190f54aabf94b1b65b5a95813378f43a74d61bedff414';
    $elVoiceId = 'cgSgspJ2msm6clMCkdW9';
    $ins2      = $pdo->prepare("INSERT INTO ajustes (clave,valor) VALUES(?,?) ON DUPLICATE KEY UPDATE valor=?");
    $ins2->execute(['elevenlabs_api_key',  $elKey,     $elKey]);
    $ins2->execute(['elevenlabs_voice_id', $elVoiceId, $elVoiceId]);
    $results['elevenlabs'] = ['status' => 'saved'];
} catch (Exception $e) {
    $results['elevenlabs'] = ['status' => 'error', 'message' => $e->getMessage()];
}

// ── Done ───────────────────────────────────────────────────────────────────
$results['warning'] = 'DELETE THIS FILE IMMEDIATELY after running it once.';
echo json_encode($results, JSON_PRETTY_PRINT);
?>
