<?php
declare(strict_types=1);
// ═══════════════════════════════════════════════════════════════════════
// TuDu SaaS · Conekta Service (OXXO Pay)
// Adaptado de RankPilot ConektaService.php para TuDu
// No requiere SDK — usa la REST API directamente con cURL
// ═══════════════════════════════════════════════════════════════════════

class TuduConektaService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.conekta.io';
    private bool   $enabled;

    // Precios en centavos MXN (con IVA incluido)
    // $399 × 1.16 IVA = $462.84 → 46284 centavos
    private const PRICES_MXN = [
        'standalone_starter_monthly' =>       0,
        'standalone_pro_monthly'     =>   23084,  // $199 + IVA
        'standalone_pro_annual'      =>  231200,  // $1,990 + IVA (2 meses gratis)
        'corp_starter_monthly'       =>   46284,  // $399 + IVA
        'corp_starter_annual'        =>  462840,  // $3,990 + IVA
        'corp_pro_monthly'           =>  115884,  // $999 + IVA
        'corp_pro_annual'            => 1158840,  // $9,990 + IVA
        'corp_agency_monthly'        =>  289884,  // $2,499 + IVA
        'corp_agency_annual'         => 2898840,  // $24,990 + IVA
    ];

    public function __construct()
    {
        $this->apiKey  = $this->loadSetting('CONEKTA_SECRET_KEY');
        $this->enabled = !empty($this->apiKey);
    }

    // ── Crear orden OXXO Pay ─────────────────────────────────────────
    public function createOxxoOrder(
        array  $org,
        array  $adminUser,
        string $edition,
        string $plan,
        string $cycle
    ): array {
        if (!$this->enabled) {
            throw new RuntimeException('Conekta no está configurado. Agrega CONEKTA_SECRET_KEY en Configuración.');
        }

        $priceKey = "{$edition}_{$plan}_{$cycle}";
        $amount   = self::PRICES_MXN[$priceKey] ?? null;

        if ($amount === null || $amount === 0) {
            throw new RuntimeException("Precio no encontrado para: {$priceKey}");
        }

        $expiresAt = time() + (3 * 24 * 3600); // 3 días para pagar en OXXO

        $body = [
            'line_items' => [[
                'name'       => "TuDu {$edition} · {$plan} ({$cycle})",
                'unit_price' => $amount,
                'quantity'   => 1,
            ]],
            'currency'      => 'MXN',
            'customer_info' => [
                'name'  => $adminUser['nombre'],
                'email' => $adminUser['email'],
                'phone' => $adminUser['whatsapp'] ?: ($adminUser['telefono'] ?: '+521234567890'),
            ],
            'charges' => [[
                'payment_method' => [
                    'type'       => 'oxxo_cash',
                    'expires_at' => $expiresAt,
                ],
            ]],
            'metadata' => [
                'organizacion_id' => (string)$org['id'],
                'edition'         => $edition,
                'plan'            => $plan,
                'cycle'           => $cycle,
            ],
        ];

        $response = $this->request('POST', '/orders', $body);

        if (!isset($response['id'])) {
            $errorMsg = $response['details'][0]['message'] ?? 'Error al crear referencia OXXO';
            throw new RuntimeException($errorMsg);
        }

        $charge   = $response['charges']['data'][0] ?? [];
        $oxxoData = $charge['payment_method'] ?? [];
        $orderId  = $response['id'];

        // Guardar la referencia en BD (status pending)
        $this->saveConektaOrder($org['id'], $edition, $plan, $orderId, $amount);

        return [
            'reference'  => $oxxoData['reference'] ?? ('REF-' . strtoupper(substr(md5($org['id'] . time()), 0, 10))),
            'expires_at' => date('d/m/Y H:i', $expiresAt),
            'amount_mxn' => $amount / 100,
            'order_id'   => $orderId,
            // URL del ticket OXXO (Conekta genera PDF)
            'ticket_url' => $oxxoData['barcode_url'] ?? null,
        ];
    }

    // ── Webhook: pago OXXO confirmado ───────────────────────────────
    public function handleOxxoPaid(array $order): void
    {
        global $pdo;

        $orgId   = $order['metadata']['organizacion_id'] ?? null;
        $edition = $order['metadata']['edition']         ?? 'corp';
        $plan    = $order['metadata']['plan']            ?? 'starter';
        $orderId = $order['id'] ?? null;

        if (!$orgId) {
            error_log('TuDuConekta: webhook sin organizacion_id');
            return;
        }

        // Activar plan de la org
        $pdo->prepare("
            UPDATE organizaciones SET
                plan = ?, edition = ?,
                plan_status = 'active',
                conekta_order_id = ?,
                plan_renews_at = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            WHERE id = ?
        ")->execute([$plan, $edition, $orderId, $orgId]);

        // Actualizar subscripciones
        $pdo->prepare("
            UPDATE subscripciones SET status = 'active',
                current_period_start = NOW(),
                current_period_end = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            WHERE organizacion_id = ? AND conekta_order_id = ?
        ")->execute([$orgId, $orderId]);

        error_log("TuDu: OXXO payment confirmed for org_id=$orgId plan=$plan order=$orderId");
    }

    // ── Guardar orden en BD ─────────────────────────────────────────
    private function saveConektaOrder(int $orgId, string $edition, string $plan, string $orderId, int $amount): void
    {
        global $pdo;

        // Guardar en org para referencia
        $pdo->prepare("UPDATE organizaciones SET conekta_order_id = ? WHERE id = ?")
            ->execute([$orderId, $orgId]);

        // Crear registro en subscripciones
        $pdo->prepare("
            INSERT INTO subscripciones
                (organizacion_id, edition, plan, status, payment_provider, conekta_order_id, amount_mxn)
            VALUES (?, ?, ?, 'trialing', 'conekta', ?, ?)
            ON DUPLICATE KEY UPDATE conekta_order_id = VALUES(conekta_order_id)
        ")->execute([$orgId, $edition, $plan, $orderId, $amount]);
    }

    // ── Verificar firma del webhook de Conekta ──────────────────────
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // Conekta usa HMAC-SHA256 con el API key como secreto
        $expected = hash_hmac('sha256', $payload, $this->apiKey);
        return hash_equals($expected, $signature);
    }

    // ── cURL helper ─────────────────────────────────────────────────
    private function request(string $method, string $endpoint, array $body = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.conekta-v2.0.0+json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Accept-Language: es',
            ],
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Error de conexión con Conekta');
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $errorMsg = $decoded['details'][0]['message'] ?? $decoded['message'] ?? 'Error Conekta';
            throw new RuntimeException("Conekta ($httpCode): $errorMsg");
        }

        return $decoded ?? [];
    }

    // ── Cargar setting desde BD ─────────────────────────────────────
    private function loadSetting(string $key): string
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if (!$val) return '';
            if (str_starts_with($val, 'enc:')) {
                $encKey  = substr(hash('sha256', 'tudu-saas'), 0, 32);
                $decoded = base64_decode(substr($val, 4));
                $iv      = substr($decoded, 0, 16);
                $data    = substr($decoded, 16);
                return openssl_decrypt($data, 'AES-256-CBC', $encKey, 0, $iv) ?: '';
            }
            return $val;
        } catch (Exception $e) {
            return '';
        }
    }

    // ── Notificaciones ahora se manejarán in-app o por correo ───────
}
