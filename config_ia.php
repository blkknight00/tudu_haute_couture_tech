<?php
require_once 'config.php';
session_start();

// Seguridad: Solo administradores y super administradores
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['usuario_rol'], ['admin', 'super_admin'])) {
    die("Acceso denegado. Esta sección es solo para administradores.");
}

// Obtener configuraciones actuales de la base de datos
$stmt_config = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('GEMINI_API_KEY', 'GEMINI_PROMPT')");
$config_actual = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);

$api_key_actual = $config_actual['GEMINI_API_KEY'] ?? '';

$prompt_actual = $config_actual['GEMINI_PROMPT'] ?? "Eres un asistente experto en productividad. Basado en la siguiente idea, ayúdame a mejorarla.\n\nTítulo: {\$titulo}\nDescripción: {\$descripcion_actual}\n\nTu Tarea: Re-escribe o expande la descripción para que sea más clara y accionable. Devuelve únicamente el texto de la nueva descripción sugerida.";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de IA - TuDu</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { padding: 1.5rem; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <h4><i class="bi bi-robot"></i> Configuración de Inteligencia Artificial</h4>
    <hr>

    <div id="feedback" class="alert" style="display:none;"></div>

    <form id="formConfigIA">
        <div class="mb-3">
            <label for="gemini_prompt" class="form-label"><strong>Prompt para la IA:</strong></label>
            <textarea class="form-control" id="gemini_prompt" name="prompt" rows="10"><?= htmlspecialchars($prompt_actual) ?></textarea>
            <small class="form-text text-muted">
                Personaliza la instrucción para la IA. Usa <code>{$titulo}</code> y <code>{$descripcion_actual}</code>.
            </small>
        </div>

        <div class="mb-3">
            <label for="deepseek_api_key" class="form-label"><strong>Clave de API de DeepSeek:</strong></label>
            <input type="password" class="form-control" id="deepseek_api_key" name="api_key" value="<?= htmlspecialchars($api_key_actual) ?>" placeholder="Pega tu clave de API de DeepSeek aquí...">
            <small class="form-text text-muted">
                Obtén tu clave en <a href="https://platform.deepseek.com/api_keys" target="_blank">DeepSeek Platform</a>.
            </small>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-outline-info" id="testApiBtn">
                <i class="bi bi-plug"></i> Probar Conexión
            </button>
            <div>
                <button type="button" class="btn btn-secondary me-2" onclick="window.close();">Cerrar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Configuración
                </button>
            </div>
        </div>
    </form>

    <script>
        document.getElementById('formConfigIA').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const submitButton = form.querySelector('button[type="submit"]');
            const feedbackDiv = document.getElementById('feedback');
            submitButton.disabled = true;

            const formData = new FormData(form);

            // Usaremos el script que ya existe para guardar la API key, pero lo adaptaremos
            fetch('guardar_api_key.php', { // Asumimos que guardar_api_key.php puede manejar ambos campos
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                feedbackDiv.style.display = 'block';
                if (data.success) {
                    feedbackDiv.className = 'alert alert-success';
                    feedbackDiv.textContent = data.message || '¡Configuración guardada!';
                } else {
                    feedbackDiv.className = 'alert alert-danger';
                    feedbackDiv.textContent = 'Error: ' + (data.error || 'No se pudo guardar.');
                }
            })
            .catch(error => {
                feedbackDiv.style.display = 'block';
                feedbackDiv.className = 'alert alert-danger';
                feedbackDiv.textContent = 'Error de conexión.';
                console.error('Error:', error);
            })
            .finally(() => {
                submitButton.disabled = false;
                setTimeout(() => { feedbackDiv.style.display = 'none'; }, 4000);
            });
        });

        document.getElementById('testApiBtn').addEventListener('click', function() {
            const testButton = this;
            const apiKeyInput = document.getElementById('deepseek_api_key');
            const feedbackDiv = document.getElementById('feedback');
            const apiKey = apiKeyInput.value;

            if (!apiKey) {
                alert('Por favor, introduce una clave de API en el campo antes de probar.');
                return;
            }

            testButton.disabled = true;
            testButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Probando...';

            const formData = new FormData();
            formData.append('api_key', apiKey);

            fetch('test_gemini_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                feedbackDiv.style.display = 'block';
                feedbackDiv.className = data.success ? 'alert alert-success' : 'alert alert-danger';
                feedbackDiv.textContent = data.message;
            })
            .catch(error => {
                console.error('Error en la prueba:', error);
            })
            .finally(() => {
                testButton.disabled = false;
                testButton.innerHTML = '<i class="bi bi-plug"></i> Probar Conexión';
            });
        });
    </script>
</body>
</html>
