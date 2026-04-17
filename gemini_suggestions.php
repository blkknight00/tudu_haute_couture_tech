<?php
require_once 'config.php';
header('Content-Type: application/json');

// --- OBTENER API KEY DE DEEPSEEK DESDE LA BASE DE DATOS ---
$stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'DEEPSEEK_API_KEY'");
$stmt->execute();
$apiKey = trim($stmt->fetchColumn());

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'La clave de API de DeepSeek no está configurada. Un administrador debe configurarla.']);
    exit;
}

// --- DEBUG TEMPORAL: Verificar la API Key (quitar después) ---
// Esto escribirá en el log de errores de tu servidor web (ej. error.log de Apache o PHP).
error_log("TuDu DeepSeek Debug - Últimos 4 caracteres de la Key: ****" . substr($apiKey, -4));
error_log("TuDu DeepSeek Debug - Longitud de la Key: " . strlen($apiKey));

$apiUrl = 'https://api.deepseek.com/v1/chat/completions';

// --- OBTENER DATOS DE LA PETICIÓN ---
$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$descripcion_actual = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$action = isset($_POST['action']) ? $_POST['action'] : 'suggestion'; // 'suggestion' o 'estimate'

if (empty($titulo)) {
    echo json_encode(['success' => false, 'error' => 'El título es obligatorio para obtener una sugerencia.']);
    exit;
}

// --- CONSTRUIR EL PROMPT PARA LA IA ---

if ($action === 'estimate') {
    // Prompt específico para estimación de tiempo
    $prompt_template = "Actúa como un Project Manager experto. Analiza la siguiente tarea y proporciona una estimación de tiempo realista para completarla, basándote en su complejidad implícita.\n\nTítulo: {\$titulo}\nDescripción: {\$descripcion_actual}\n\nInstrucciones: Responde ÚNICAMENTE con el tiempo estimado (ej. '2 horas', '3 días') seguido de una justificación muy breve de una sola frase.";
} else {
    // Prompt normal para sugerir/mejorar descripción
    // Obtenemos el prompt personalizado desde la base de datos
    $stmt_prompt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'DEEPSEEK_PROMPT'");
    $stmt_prompt->execute();
    $prompt_template = $stmt_prompt->fetchColumn();

    if (empty($prompt_template)) {
        $prompt_template = "Expande la siguiente idea:\nTítulo: {\$titulo}\nDescripción: {\$descripcion_actual}";
    }
}

// Reemplazamos las variables en la plantilla del prompt con los datos reales
$prompt = str_replace(
    ['{$titulo}', '{$descripcion_actual}'],
    [$titulo, $descripcion_actual],
    $prompt_template
);

// --- PREPARAR LA PETICIÓN A LA API DE DEEPSEEK ---
$data = [
    'model' => 'deepseek-chat', // Modelo recomendado para chat/instrucciones
    'messages' => [
        ['role' => 'system', 'content' => 'Eres un asistente experto en productividad.'],
        ['role' => 'user', 'content' => $prompt]
    ],
    'temperature' => 0.7 // Un poco de creatividad
];

// USAR cURL EN LUGAR DE file_get_contents (más confiable)
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false // En local (XAMPP) es mejor false para evitar errores de certificado SSL
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// --- MANEJAR LA RESPUESTA ---
if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $curl_error]);
    exit;
}

$response_data = json_decode($response, true);

// Verificar código de estado HTTP primero
if ($http_code !== 200) {
    $error_message = $response_data['error']['message'] ?? 'Error HTTP ' . $http_code . '. Verifica tu API Key o la respuesta de la API.';
    echo json_encode(['success' => false, 'error' => $error_message]);
    exit;
}

if (isset($response_data['choices'][0]['message']['content'])) {
    $suggestion = $response_data['choices'][0]['message']['content'];
    echo json_encode(['success' => true, 'suggestion' => trim($suggestion)]);
} else {
    $error_message = $response_data['error']['message'] ?? 'Estructura de respuesta inesperada de la API.';
    echo json_encode(['success' => false, 'error' => $error_message]);
}
?>