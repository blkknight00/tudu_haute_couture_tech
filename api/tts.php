<?php
/**
 * ElevenLabs TTS Proxy
 * Reads the API key from the DB and proxies requests to ElevenLabs.
 * Returns audio data (mp3) directly, never exposing the API key to the frontend.
 */
require_once '../config.php';

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Starting tts.php\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Auth check — session already started by config.php
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$text = trim($input['text'] ?? '');

if (empty($text)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No text provided']);
    exit;
}

// Fetch settings from DB
try {
    $stmt = $pdo->query("SELECT clave, valor FROM ajustes WHERE clave IN ('elevenlabs_api_key', 'elevenlabs_voice_id')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
    exit;
}

$apiKey  = trim($settings['elevenlabs_api_key'] ?? '');
$apiKey  = preg_replace('/[^\x20-\x7E]/', '', $apiKey);
$voiceId = $settings['elevenlabs_voice_id'] ?? 'cgSgspJ2msm6clMCkdW9'; // Default: "Jessica" — young female, Multilingual v2

if (empty($apiKey)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'ElevenLabs API Key no configurada.']);
    exit;
}

// Call ElevenLabs TTS API
$url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}";

$payload = json_encode([
    'text' => $text,
    'model_id' => 'eleven_multilingual_v2',
    'voice_settings' => [
        'stability' => 0.5,
        'similarity_boost' => 0.85,
        'style' => 0.3,
        'use_speaker_boost' => true
    ]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'xi-api-key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: audio/mpeg'
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response     = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError    = curl_error($ch);
curl_close($ch);

file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] tts.php ElevenLabs raw response: " . substr($response, 0, 500) . " (Code: $httpCode) Error: $curlError\n", FILE_APPEND);

if ($httpCode === 200 && str_contains($contentType, 'audio')) {
    // Stream audio back to client
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($response));
    echo $response;
} else {
    $errorData = json_decode($response, true);
    $msg = $errorData['detail']['message'] ?? $errorData['message'] ?? 'Error calling ElevenLabs API';
    file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] tts.php ERROR ($httpCode): $msg\n", FILE_APPEND);
    http_response_code($httpCode ?: 500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $msg, 'code' => $httpCode]);
}
?>
