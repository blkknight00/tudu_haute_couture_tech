<?php
declare(strict_types=1);
// ═══════════════════════════════════════════════════════════════════════
// TuDu SaaS · Stripe Service
// Adaptado de RankPilot StripeService.php para TuDu
// El tenant es la ORGANIZACIÓN (no el usuario individual)
// Requiere: composer require stripe/stripe-php
// ═══════════════════════════════════════════════════════════════════════

class TuduStripeService
{
    private mixed $stripe;     // \Stripe\StripeClient
    private bool  $enabled;

    // ── Price IDs de Stripe (configurar en Stripe Dashboard) ─────────
    // Formato: edition_plan_cycle
    // Se sobreescriben con los valores de system_settings
    private const PRICE_IDS = [
        // Stand Alone
        'standalone_starter_monthly' => '',
        'standalone_starter_annual'  => '',
        'standalone_pro_monthly'     => '',
        'standalone_pro_annual'      => '',
        // Corp
        'corp_starter_monthly'       => '',
        'corp_starter_annual'        => '',
        'corp_pro_monthly'           => '',
        'corp_pro_annual'            => '',
        'corp_agency_monthly'        => '',
        'corp_agency_annual'         => '',
    ];

    // Precios en centavos MXN (sin IVA)
    // IVA se cobra en Stripe como tax_rate separado
    private const PRICES_MXN = [
        'standalone_starter_monthly' =>       0,  // Gratis
        'standalone_pro_monthly'     =>   19900,  // $199 MXN
        'standalone_pro_annual'      =>  199000,  // $1,990 MXN (2 meses gratis)
        'corp_starter_monthly'       =>   39900,  // $399 MXN
        'corp_starter_annual'        =>  399000,  // $3,990 MXN
        'corp_pro_monthly'           =>   99900,  // $999 MXN
        'corp_pro_annual'            =>  999000,  // $9,990 MXN
        'corp_agency_monthly'        =>  249900,  // $2,499 MXN
        'corp_agency_annual'         => 2499000,  // $24,990 MXN
    ];

    public function __construct()
    {
        global $pdo;

        $secretKey = $this->loadSetting('STRIPE_SECRET_KEY');
        $this->enabled = !empty($secretKey);

        if ($this->enabled) {
            // Cargar Stripe SDK si está disponible
            $autoload = __DIR__ . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
                $this->stripe = new \Stripe\StripeClient($secretKey);
            } else {
                $this->enabled = false;
                error_log('TuduStripe: vendor/autoload.php no encontrado en ' . $autoload);
            }
        }
    }

    // ── Crear o recuperar Customer de Stripe ────────────────────────
    public function getOrCreateCustomer(array $org, array $adminUser): string
    {
        // Si la org ya tiene customer_id, retornarlo
        if (!empty($org['stripe_customer_id'])) {
            return $org['stripe_customer_id'];
        }

        $customer = $this->stripe->customers->create([
            'email'    => $adminUser['email'],
            'name'     => $org['nombre'],
            'metadata' => [
                'organizacion_id' => $org['id'],
                'admin_user_id'   => $adminUser['id'],
            ],
        ]);

        // Guardar en BD
        global $pdo;
        $pdo->prepare("UPDATE organizaciones SET stripe_customer_id = ? WHERE id = ?")
            ->execute([$customer->id, $org['id']]);

        return $customer->id;
    }

    // ── Crear suscripción (con 14 días de trial) ────────────────────
    public function createSubscription(
        array  $org,
        array  $adminUser,
        string $edition,
        string $plan,
        string $cycle,
        string $paymentMethodId
    ): array {
        if (!$this->enabled) {
            throw new RuntimeException('Stripe no está configurado. Agrega STRIPE_SECRET_KEY en Configuración.');
        }

        $priceKey = "{$edition}_{$plan}_{$cycle}";
        $priceId  = $this->getPriceId($priceKey);

        if (!$priceId) {
            throw new RuntimeException("Price ID no configurado para: {$priceKey}. Configúralo en el Admin Panel.");
        }

        $customerId = $this->getOrCreateCustomer($org, $adminUser);

        // Adjuntar método de pago
        $this->stripe->paymentMethods->attach($paymentMethodId, [
            'customer' => $customerId,
        ]);

        $this->stripe->customers->update($customerId, [
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
        ]);

        // Crear suscripción con 14 días de trial
        $subscription = $this->stripe->subscriptions->create([
            'customer'          => $customerId,
            'items'             => [['price' => $priceId]],
            'trial_period_days' => 14,
            'metadata'          => [
                'organizacion_id' => $org['id'],
                'edition'         => $edition,
                'plan'            => $plan,
                'cycle'           => $cycle,
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        // Actualizar la org en BD
        global $pdo;
        $trialEnds = date('Y-m-d H:i:s', $subscription->trial_end);
        $pdo->prepare("
            UPDATE organizaciones SET
                plan = ?, edition = ?, plan_status = 'trialing',
                stripe_subscription_id = ?, trial_ends_at = ?
            WHERE id = ?
        ")->execute([$plan, $edition, $subscription->id, $trialEnds, $org['id']]);

        // Registrar en subscripciones
        $amount = self::PRICES_MXN[$priceKey] ?? 0;
        $pdo->prepare("
            INSERT INTO subscripciones
                (organizacion_id, edition, plan, status, payment_provider,
                 stripe_subscription_id, stripe_customer_id, amount_mxn, trial_ends_at)
            VALUES (?, ?, ?, 'trialing', 'stripe', ?, ?, ?, ?)
        ")->execute([
            $org['id'], $edition, $plan,
            $subscription->id, $customerId,
            $amount, $trialEnds,
        ]);

        return [
            'subscription_id' => $subscription->id,
            'trial_ends_at'   => $trialEnds,
            'client_secret'   => $subscription->latest_invoice?->payment_intent?->client_secret,
        ];
    }

    // ── Cancelar suscripción ────────────────────────────────────────
    public function cancelSubscription(array $org): void
    {
        if (!$this->enabled || empty($org['stripe_subscription_id'])) return;

        $this->stripe->subscriptions->update($org['stripe_subscription_id'], [
            'cancel_at_period_end' => true,
        ]);

        global $pdo;
        $pdo->prepare("UPDATE organizaciones SET plan_status = 'cancelled' WHERE id = ?")
            ->execute([$org['id']]);

        $pdo->prepare("
            UPDATE subscripciones SET status = 'cancelled', cancelled_at = NOW()
            WHERE organizacion_id = ? AND stripe_subscription_id = ?
        ")->execute([$org['id'], $org['stripe_subscription_id']]);
    }

    // ── Obtener facturas ────────────────────────────────────────────
    public function getInvoices(array $org): array
    {
        if (!$this->enabled || empty($org['stripe_customer_id'])) return [];

        $invoices = $this->stripe->invoices->all([
            'customer' => $org['stripe_customer_id'],
            'limit'    => 12,
        ]);

        return array_map(fn($inv) => [
            'id'          => $inv->id,
            'amount'      => $inv->amount_paid / 100,
            'currency'    => strtoupper($inv->currency),
            'status'      => $inv->status,
            'date'        => date('Y-m-d', $inv->created),
            'pdf_url'     => $inv->invoice_pdf,
            'description' => $inv->lines->data[0]->description ?? 'TuDu',
        ], $invoices->data);
    }

    // ── Webhook: pago exitoso ───────────────────────────────────────
    public function handlePaymentSucceeded(object $invoice): void
    {
        global $pdo;

        $orgId = $invoice->metadata->organizacion_id ?? null;
        if (!$orgId) return;

        $pdo->prepare("
            UPDATE organizaciones SET plan_status = 'active' WHERE id = ?
        ")->execute([$orgId]);

        $pdo->prepare("
            UPDATE subscripciones SET status = 'active',
                current_period_start = NOW(),
                current_period_end = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            WHERE organizacion_id = ? AND status != 'cancelled'
        ")->execute([$orgId]);

        error_log("TuDu: Stripe payment succeeded for org_id=$orgId amount={$invoice->amount_paid}");
    }

    // ── Webhook: pago fallido ───────────────────────────────────────
    public function handlePaymentFailed(object $invoice): void
    {
        global $pdo;

        $orgId = $invoice->metadata->organizacion_id ?? null;
        if (!$orgId) return;

        $pdo->prepare("UPDATE organizaciones SET plan_status = 'past_due' WHERE id = ?")
            ->execute([$orgId]);

        $pdo->prepare("UPDATE subscripciones SET status = 'past_due' WHERE organizacion_id = ? AND status = 'active'")
            ->execute([$orgId]);

        error_log("TuDu: Stripe payment FAILED for org_id=$orgId");
    }

    // ── Webhook: suscripción eliminada ──────────────────────────────
    public function handleSubscriptionDeleted(object $subscription): void
    {
        global $pdo;

        $orgId = $subscription->metadata->organizacion_id ?? null;
        if (!$orgId) return;

        $pdo->prepare("
            UPDATE organizaciones SET plan = 'starter', plan_status = 'inactive' WHERE id = ?
        ")->execute([$orgId]);

        error_log("TuDu: Subscription deleted for org_id=$orgId");
    }

    // ── Verificar firma del webhook ─────────────────────────────────
    public function constructWebhookEvent(string $payload, string $sigHeader): object
    {
        $secret = $this->loadSetting('STRIPE_WEBHOOK_SECRET');
        return \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
    }

    // ── Obtener Price ID (de BD o constante) ────────────────────────
    private function getPriceId(string $key): ?string
    {
        // Intentar desde system_settings primero
        $fromDb = $this->loadSetting('STRIPE_PRICE_' . strtoupper($key));
        if ($fromDb) return $fromDb;

        return self::PRICE_IDS[$key] ?? null;
    }

    // ── Cargar setting desde BD ─────────────────────────────────────
    private function loadSetting(string $key): string
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT `valor` FROM ajustes WHERE `clave` = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if (!$val) return '';
            // Desencriptar si empieza con 'enc:'
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

    private function getAppUrl(): string
    {
        return $this->loadSetting('APP_URL') ?: 'https://tudu.app/';
    }
}
