<?php
require_once '../config.php';
require_once 'auth_middleware.php';

header('Content-Type: application/json; charset=UTF-8');

file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Starting ai_assistant.php\n", FILE_APPEND);

try {
    $user = checkAuth();
    file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Auth OK in ai_assistant\n", FILE_APPEND);
} catch (Throwable $e) {
    file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] Auth FAILED in ai_assistant: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Auth Error: ' . $e->getMessage()]);
    exit;
}

// Define callDeepSeek OUTSIDE the try block 
function callDeepSeek($apiKey, $systemPrompt, $userMessage) {
    if (!$apiKey) return null;
    $url = 'https://api.deepseek.com/chat/completions';
    
    $data = [
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage]
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
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("DeepSeek API Error (callDeepSeek): Code $httpCode, Error: $curlError, Response: $response");
        return null;
    }

    $json = json_decode($response, true);
    return $json['choices'][0]['message']['content'] ?? null;
}

try {

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$title = $input['title'] ?? '';
$description = $input['description'] ?? '';

// Default response
$response = ['status' => 'error', 'message' => 'Acción no reconocida o error interno.'];

// 1. Fetch Settings
try {
    $stmt = $pdo->query("SELECT clave, valor FROM ajustes");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Error (tabla ajustes): ' . $e->getMessage()]);
    exit;
}

$apiKey = trim($settings['deepseek_api_key'] ?? '');

// Override with organization-specific key if available
if (!empty($user['organizacion_id'])) {
    $stmtOrg = $pdo->prepare("SELECT deepseek_api_key FROM organizaciones WHERE id = ?");
    $stmtOrg->execute([$user['organizacion_id']]);
    $orgKey = $stmtOrg->fetchColumn();
    if (!empty($orgKey)) {
        $apiKey = trim($orgKey);
    }
}

// Strip any invisible/non-ASCII characters that could break Auth header
$apiKey = preg_replace('/[^\x20-\x7E]/', '', $apiKey);
$customPromptSuggest = $settings['prompt_suggest'] ?? '';
$customPromptEstimate = $settings['prompt_estimate'] ?? '';

if ($action === 'test_key') {
    // Try a minimal API call to verify the key
    $testKey = trim($input['api_key'] ?? $apiKey);
    if (empty($testKey)) {
        echo json_encode(['status' => 'error', 'message' => 'No se proporcionó una API Key para probar.']);
        exit;
    }
    $url = 'https://api.deepseek.com/chat/completions';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'user', 'content' => 'Di solo "OK" en una sola palabra.']
            ],
            'max_tokens' => 5,
            'stream' => false
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $testKey
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $testResp = curl_exec($ch);
    $testCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($testCode === 200) {
        echo json_encode(['status' => 'success', 'message' => '✅ API Key válida. DeepSeek está listo.']);
    } else {
        $errorBody = json_decode($testResp, true);
        $errMsg = $errorBody['error']['message'] ?? "Error HTTP $testCode";
        echo json_encode(['status' => 'error', 'message' => "❌ API Key inválida: $errMsg"]);
    }
    exit;
}

if ($action === 'suggest') {
    $suggestion = null;

    if (!empty($apiKey)) {
        $systemPrompt = $customPromptSuggest ?: "Eres un asistente experto en gestión de proyectos. Tu tarea es analizar el título y descripción de una tarea y sugerir una lista de subtareas o mejoras para la descripción. Responde SOLO con el contenido sugerido, usando formato Markdown (listas).";
        $userMessage = "Título: $title\nDescripción: $description\n\nPor favor dame sugerencias:";
        $suggestion = callDeepSeek($apiKey, $systemPrompt, $userMessage);
    }

    if ($suggestion) {
        $response = ['status' => 'success', 'suggestion' => "\n\n" . $suggestion];
    } else {
        // Fallback Heuristics
        $suggestions = [];
        $suggestions[] = "\n\n### 📋 Sugerencias (Modo Básico):";
        $suggestions[] = "- [ ] Definir el alcance de '$title'";
        $suggestions[] = "- [ ] Investigar requisitos previos";
        
        if (stripos($title, 'diseño') !== false || stripos($description, 'diseño') !== false) {
            $suggestions[] = "- [ ] Buscar referencias visuales";
        }
        
        $response = [
            'status' => 'success',
            'suggestion' => implode("\n", $suggestions)
        ];
    }

} elseif ($action === 'estimate') {
    $aiResponse = null;

    if (!empty($apiKey)) {
        $systemPrompt = $customPromptEstimate ?: "Eres un experto en estimación de software. Analiza la tarea y estima: 1. Prioridad (baja, media, alta). 2. Días estimados (número). 3. Razonamiento breve. Responde en JSON puro: {\"priority\": \"...\", \"days\": X, \"reasoning\": \"...\"}";
        $userMessage = "Título: $title\nDescripción: $description";
        $raw = callDeepSeek($apiKey, $systemPrompt, $userMessage);
        
        // Clean markdown code blocks if present
        $raw = str_replace(['```json', '```'], '', $raw ?? '');
        $aiResponse = json_decode($raw, true);
    }

    if ($aiResponse) {
         $priority = strtolower($aiResponse['priority'] ?? 'media');
         $days = intval($aiResponse['days'] ?? 3);
         $reasoning = $aiResponse['reasoning'] ?? '';
         
         // Normalize priority
         if (!in_array($priority, ['baja', 'media', 'alta'])) $priority = 'media';
         
         $dueDate = date('Y-m-d', strtotime("+$days days"));
         
         $response = [
            'status' => 'success',
            'priority' => $priority,
            'due_date' => $dueDate,
            'reasoning' => $reasoning
        ];
    } else {
        // Fallback Heuristics
        $complexity = 'media';
        $days = 3;
        $combinedText = strtolower($title . ' ' . $description);
        $length = strlen($combinedText);

        if ($length > 500 || stripos($combinedText, 'complejo') !== false) {
            $complexity = 'alta';
            $days = 7;
        }

        $dueDate = date('Y-m-d', strtotime("+$days days"));

        $response = [
            'status' => 'success',
            'priority' => $complexity,
            'due_date' => $dueDate,
            'reasoning' => "Estimación básica (Sin API Key): Complejidad **$complexity** basada en longitud."
        ];
    }

} elseif ($action === 'chat') {
    $message = $input['message'] ?? '';
    $history = $input['history'] ?? [];
    $context = $input['context'] ?? null;
    
    if (empty($apiKey)) {
        echo json_encode(['status' => 'error', 'message' => 'API Key no configurada.']);
        exit;
    }

    $local_datetime = $input['local_datetime'] ?? date('Y-m-d H:i:s');
    $timezone = $input['timezone'] ?? 'UTC';

    $contextStr = $context ? "\nCONTEXTO DETALLADO (Úsalo para responder preguntas específicas sobre la agenda): " . json_encode($context, JSON_UNESCAPED_UNICODE) : "";

    $systemPrompt = "Eres 'TuDu Agent', un asistente de IA altamente inteligente y proactivo. 
    Tu objetivo es ayudar al usuario a gestionar su vida profesional y personal.
    
    PERSONALIDAD: 
    - Eres amable, eficiente y analítico.
    - No eres un robot; si el usuario te pregunta por mañana, revisa el contexto, analiza qué tareas tiene y dales prioridad.
    - Si ves muchas tareas, ofrece consejos de productividad.
    - Si no hay tareas, felicita al usuario.

    CONOCIMIENTO:
    Tienes acceso a estos datos en tiempo real: {$contextStr}
    
    REGLA DE RESPUESTAS:
    - Cuando te pregunten por 'hoy' o 'mañana', responde LISTANDO los elementos encontrados en el contexto de forma clara y organizada.
    - Sé proactivo: Si ves una tarea urgente mañana, menciónala aunque te pregunten por hoy.
    
    ACCIONES TÉCNICAS (Formato obligatorio para interactuar con la interfaz):
    - Para crear tareas: [[ACTION: {\"type\": \"CREATE_TASK\", \"data\": {\"title\": \"...\", \"project_id\": ID}}]]
    - Para crear eventos: [[ACTION: {\"type\": \"CREATE_EVENT\", \"data\": {\"title\": \"...\", \"start\": \"YYYY-MM-DD HH:MM:SS\", \"end\": \"YYYY-MM-DD HH:MM:SS\"}}]]
    - Para crear recordatorios/alertas: [[ACTION: {\"type\": \"CREATE_REMINDER\", \"data\": {\"title\": \"...\", \"reminder_at\": \"YYYY-MM-DD HH:MM:SS\"}}]]
    - Para cambiar de vista: [[ACTION: {\"type\": \"SHOW_VIEW\", \"data\": {\"view\": \"calendar|kanban|list\", \"project_id\": ID}}]]
    
    MANEJO DEL TIEMPO:
    - La hora local del usuario es: {$local_datetime} (zona horaria: {$timezone}).
    - USA SIEMPRE ESTA HORA para responder preguntas sobre la hora actual. Esta es la hora real del usuario, no la del servidor.
    - Si el usuario dice 'en 10 minutos', calcula la hora sumando 10 minutos a la hora local del usuario y usa el formato YYYY-MM-DD HH:MM:SS.
    - Si el usuario dice 'mañana a las 10am', calcula la fecha de mañana basándote en la hora local del usuario.
    
    NOTAS SOBRE PROYECTOS: Si el usuario menciona un proyecto, busca su ID en la lista de 'projects' del contexto.
    
    DATO IMPORTANTE: Responde siempre en español.";

    // Format history for DeepSeek
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($history as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $url = 'https://api.deepseek.com/chat/completions';
    $data = [
        'model' => 'deepseek-chat',
        'messages' => $messages,
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

    $responseTxt = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200) {
        $json = json_decode($responseTxt, true);
        $content = $json['choices'][0]['message']['content'] ?? '';
        $response = ['status' => 'success', 'message' => $content];
    } else {
        $errorMsg = "DeepSeek API Error (chat): Code $httpCode, Error: $curlError, Response: " . substr($responseTxt, 0, 500);
        file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] " . $errorMsg . "\n", FILE_APPEND);
        error_log($errorMsg);
        $response = ['status' => 'error', 'message' => 'Error al contactar con DeepSeek (Code: ' . $httpCode . ').'];
    }
} elseif ($action === 'briefing') {
    $context = $input['context'] ?? [];
    
    if (empty($apiKey)) {
        echo json_encode(['status' => 'error', 'message' => 'API Key no configurada.']);
        exit;
    }

    $systemPrompt = "Eres 'TuDu Copilot', el asistente proactivo de TuDu. 
    Tu tarea es recibir un contexto de tareas y eventos y generar un 'Morning Brief' o resumen de situación muy conciso, motivador y útil.
    Si hay algo muy urgente, resaltalo. Si la agenda está tranquila, felicita al usuario.
    Responde siempre en español, con tono positivo y profesional. Máximo 100 palabras.";

    $userMessage = "Contexto actual:\n" . json_encode($context, JSON_PRETTY_PRINT) . "\n\nPor favor, dame mi resumen del día:";

    $suggestion = callDeepSeek($apiKey, $systemPrompt, $userMessage);

    if ($suggestion) {
        $response = ['status' => 'success', 'message' => $suggestion];
    } else {
        $response = ['status' => 'error', 'message' => 'No se pudo generar el briefing.'];
    }
}

echo json_encode($response);

} catch (\Throwable $e) {
    // Note: NOT sending 500 here to avoid hosting replacing the body with generic HTML
    file_put_contents('api_trace.log', "[" . date('Y-m-d H:i:s') . "] FATAL: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'status'  => 'error',
        'message' => 'PHP Fatal: ' . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine()
    ]);
}
?>
