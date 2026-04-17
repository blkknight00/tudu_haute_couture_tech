<?php
/**
 * TuduClient.php
 * Copia este archivo en tu aplicación "LexData" (ej: /classes/TuduClient.php)
 * 
 * Uso básico:
 * $tudu = new TuduClient('https://tu-dominio.com/tudu', 'TU_API_KEY_AQUI');
 * $resultado = $tudu->createTask('Revisar Contrato', 'Detalles...', '2024-12-31');
 */

class TuduClient {
    private $baseUrl;
    private $apiKey;

    public function __construct($tuduUrl, $apiKey) {
        $this->baseUrl = rtrim($tuduUrl, '/'); // Asegura que no tenga slash final
        $this->apiKey = $apiKey;
    }

    /**
     * Crea una tarea en Tudu
     * 
     * @param string $title Título de la tarea (Requerido)
     * @param string $description Descripción (Opcional)
     * @param string $dueDate Fecha término YYYY-MM-DD (Opcional)
     * @param string $priority Prioridad: 'baja', 'media', 'alta' (Opcional)
     * @param string $externalId ID de referencia en tu sistema (ej: 'exp-123')
     * @return array Respuesta de la API (success, task_id, etc.)
     */
    public function createTask($title, $description = '', $dueDate = null, $priority = 'media', $externalId = null) {
        $endpoint = $this->baseUrl . '/api/v1/create_task.php';
        
        $payload = [
            'title' => $title,
            'description' => $description,
            'due_date' => $dueDate,
            'priority' => $priority,
            'external_id' => $externalId
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);

        // Desactivar verificación SSL solo para desarrollo local (quitar en producción si tienes SSL válido)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            return ['success' => false, 'error' => 'cURL Error: ' . curl_error($ch)];
        }
        
        curl_close($ch);

        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            return [
                'success' => false, 
                'error' => $decoded['error'] ?? 'HTTP Error ' . $httpCode,
                'details' => $decoded
            ];
        }

        return $decoded;
    }
}
?>
