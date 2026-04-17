<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT valor FROM ajustes WHERE clave = 'deepseek_api_key'");
$apiKey = $stmt->fetchColumn();

echo "Testing Suggest Feature with real API Key...\n";
$url = 'https://api.deepseek.com/chat/completions';
$data = [
    'model' => 'deepseek-chat',
    'messages' => [
        ['role' => 'system', 'content' => 'Test'],
        ['role' => 'user', 'content' => 'Hi']
    ],
    'stream' => false
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "AI Reponse: $response\n";

if ($httpCode !== 200) {
    echo "\nSUSPICION CONFIRMED: The AI fails and the system shows the 'Basic Mode' fallback.\n";
} else {
    echo "\nAI SUCCESS: The issue might be specific to the 'chat' block in ai_assistant.php.\n";
}
?>
