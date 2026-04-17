<?php
declare(strict_types=1);
// ═══════════════════════════════════════════════════════════════════════
// TuDu SaaS · Payments API
// Adaptado de RankPilot PaymentController.php
// Endpoints: subscribe, cancel, stripe_webhook, conekta_webhook, invoices, plans
// ═══════════════════════════════════════════════════════════════════════

require_once '../config.php';
require_once 'auth_middleware.php';
require_once 'saas_limits.php';
require_once 'TuduStripeService.php';
require_once 'TuduConektaService.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── Webhooks son públicos (no requieren sesión) ───────────────────────
if ($action === 'stripe_webhook' || $action === 'conekta_webhook') {
    handleWebhook($action);
    exit;
}

// ── El resto requiere autenticación ──────────────────────────────────
if ($action !== 'plans') {
    $user    = checkAuth();
    $org_id  = (int)($_SESSION['organizacion_id'] ?? 0);
}

// ════════════════════════════════════════════════════════════════════════
try {

    switch ($action) {

        // ── GET ?action=config ─────────────────────────────────────────
        case 'config':
            $stmt = $pdo->prepare("SELECT `valor` FROM ajustes WHERE `clave` = 'STRIPE_PUBLIC_KEY'");
            $stmt->execute();
            $val = $stmt->fetchColumn() ?: '';
            echo json_encode(['status' => 'success', 'public_key' => $val]);
            break;

        // ── GET ?action=plans ──────────────────────────────────────────
        // Lista pública de planes con precios (para Pricing Page)
        case 'plans':
            $plans = tuduPlanDefinitions();
            // Filtrar enterprise del listado público
            $public = array_filter($plans, fn($p) => $p['plan'] !== 'enterprise');
            echo json_encode(['status' => 'success', 'data' => array_values($public)]);
            break;


        // ── POST ?action=subscribe ─────────────────────────────────────
        case 'subscribe':
            if ($method !== 'POST') throw new RuntimeException('Método no permitido');

            $input          = json_decode(file_get_contents('php://input'), true) ?? [];
            $edition        = $input['edition']        ?? 'corp';
            $plan           = $input['plan']           ?? 'starter';
            $cycle          = $input['billing_cycle']  ?? 'monthly';
            $paymentMethod  = $input['payment_method'] ?? 'card'; // 'card' | 'oxxo'
            $pmId           = $input['payment_method_id'] ?? '';   // Stripe PaymentMethod ID

            // Validar
            $allowedEditions = ['standalone', 'corp'];
            $allowedPlans    = ['starter', 'pro', 'agency'];
            $allowedCycles   = ['monthly', 'annual'];

            if (!in_array($edition, $allowedEditions)) throw new RuntimeException('Edición no válida');
            if (!in_array($plan, $allowedPlans))       throw new RuntimeException('Plan no válido');
            if (!in_array($cycle, $allowedCycles))     throw new RuntimeException('Ciclo no válido');

            // Obtener datos de la org
            $orgStmt = $pdo->prepare("SELECT * FROM organizaciones WHERE id = ?");
            $orgStmt->execute([$org_id]);
            $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
            if (!$org) throw new RuntimeException('Organización no encontrada');

            // Obtener admin user (usuario en sesión)
            $adminStmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $adminStmt->execute([$user['id']]);
            $adminUser = $adminStmt->fetch(PDO::FETCH_ASSOC);

            if ($paymentMethod === 'oxxo') {
                // ── Conekta OXXO ──────────────────────────────────────
                $conekta = new TuduConektaService();
                $result  = $conekta->createOxxoOrder($org, $adminUser, $edition, $plan, $cycle);

                echo json_encode([
                    'status'      => 'pending_oxxo',
                    'reference'   => $result['reference'],
                    'expires_at'  => $result['expires_at'],
                    'amount_mxn'  => $result['amount_mxn'],
                    'ticket_url'  => $result['ticket_url'],
                    'message'     => 'Paga en cualquier OXXO con esta referencia. Se activa en minutos.',
                ]);

            } else {
                // ── Stripe Tarjeta ────────────────────────────────────
                if (empty($pmId))  throw new RuntimeException('payment_method_id requerido');

                $stripe = new TuduStripeService();
                $result = $stripe->createSubscription($org, $adminUser, $edition, $plan, $cycle, $pmId);

                echo json_encode([
                    'status'          => 'trialing',
                    'subscription_id' => $result['subscription_id'],
                    'trial_ends_at'   => $result['trial_ends_at'],
                    'client_secret'   => $result['client_secret'],
                    'message'         => '14 días de prueba gratis. No se cobra nada hasta que termine el trial.',
                ]);
            }
            break;


        // ── POST ?action=cancel ────────────────────────────────────────
        case 'cancel':
            if ($method !== 'POST') throw new RuntimeException('Método no permitido');

            // Solo el admin de la org puede cancelar
            if (!authIsAdmin()) throw new RuntimeException('Solo administradores pueden cancelar el plan');

            $orgStmt = $pdo->prepare("SELECT * FROM organizaciones WHERE id = ?");
            $orgStmt->execute([$org_id]);
            $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
            if (!$org) throw new RuntimeException('Organización no encontrada');

            $stripe = new TuduStripeService();
            $stripe->cancelSubscription($org);

            registrarAuditoria($user['id'], 'SAAS_CANCEL_SUBSCRIPTION', 'organizaciones', $org_id, 'Cancelado por usuario');

            echo json_encode([
                'status'  => 'success',
                'message' => 'Suscripción cancelada. Tu plan estará activo hasta el fin del período actual.',
            ]);
            break;


        // ── GET ?action=invoices ───────────────────────────────────────
        case 'invoices':
            $orgStmt = $pdo->prepare("SELECT * FROM organizaciones WHERE id = ?");
            $orgStmt->execute([$org_id]);
            $org = $orgStmt->fetch(PDO::FETCH_ASSOC);

            // Facturas de Stripe
            $stripeInvoices = [];
            try {
                $stripe = new TuduStripeService();
                $stripeInvoices = $stripe->getInvoices($org);
            } catch (Exception $e) {
                // Stripe no configurado → OK, mostrar lo que hay
            }

            // Historial de subscripciones de la BD (incluye OXXO)
            $dbSubs = $pdo->prepare("
                SELECT plan, edition, status, payment_provider, amount_mxn,
                       current_period_start, current_period_end, created_at
                FROM subscripciones WHERE organizacion_id = ?
                ORDER BY created_at DESC LIMIT 20
            ");
            $dbSubs->execute([$org_id]);
            $history = $dbSubs->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status'   => 'success',
                'invoices' => $stripeInvoices,
                'history'  => $history,
            ]);
            break;
        // --- POST ?action=cancel ---
        case 'cancel':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
                exit;
            }
            $org = getOrgData($org_id);
            if (!$org || empty($org['stripe_subscription_id'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No hay suscripción activa para cancelar']);
                exit;
            }
            require_once 'TuduStripeService.php';
            $stripeService = new TuduStripeService();
            $stripeService->cancelSubscription($org);
            
            echo json_encode(['status' => 'success', 'message' => 'Suscripción cancelada exitosamente']);
            break;


        // ── GET ?action=status ─────────────────────────────────────────
        // Estado actual del plan de la organización
        case 'status':
            $orgPlan  = getOrgPlan($org_id);
            $usage    = getOrgUsageSummary($org_id);
            $isActive = hasActivePlan($org_id);

            echo json_encode([
                'status'      => 'success',
                'plan_active' => $isActive,
                'data'        => array_merge($orgPlan, [
                    'usage'       => $usage,
                    'org_id'      => $org_id,
                    'is_super'    => authIsSuperAdmin(),
                ]),
            ]);
            break;


        default:
            throw new RuntimeException("Acción no válida: '$action'");
    }

} catch (Throwable $e) {
    $msg = $e->getMessage();
    error_log("PaymentError: " . $msg);
    $code = match(true) {
        str_contains($msg, 'card_declined')     => 422,
        str_contains($msg, 'insufficient_funds')=> 422,
        str_contains($msg, 'no válid')          => 400,
        default                                  => 422,
    };
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => humanizePaymentError($msg)]);
}

// ═══════════════════════════════════════════════════════════════════════
// WEBHOOK HANDLERS
// ═══════════════════════════════════════════════════════════════════════
function handleWebhook(string $type): void
{
    require_once 'TuduStripeService.php';
    require_once 'TuduConektaService.php';

    header('Content-Type: application/json');

    if ($type === 'stripe_webhook') {
        $payload   = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $stripe = new TuduStripeService();
            $event  = $stripe->constructWebhookEvent($payload, $sigHeader);

            match ($event->type) {
                'invoice.payment_succeeded'       => $stripe->handlePaymentSucceeded($event->data->object),
                'invoice.payment_failed'          => $stripe->handlePaymentFailed($event->data->object),
                'customer.subscription.deleted'   => $stripe->handleSubscriptionDeleted($event->data->object),
                default => null,
            };

            echo json_encode(['received' => true]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        return;
    }

    if ($type === 'conekta_webhook') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $event   = $payload['type'] ?? '';
        $order   = $payload['data']['object'] ?? [];

        if ($event === 'order.paid') {
            $conekta = new TuduConektaService();
            $conekta->handleOxxoPaid($order);
        }

        echo json_encode(['received' => true]);
        return;
    }
}

// ── Humanizar errores de pago para el usuario ─────────────────────────
function humanizePaymentError(string $error): string
{
    if (str_contains($error, 'card_declined'))      return 'Tarjeta rechazada. Verifica los datos o usa otra tarjeta.';
    if (str_contains($error, 'insufficient_funds')) return 'Fondos insuficientes en la tarjeta.';
    if (str_contains($error, 'expired_card'))       return 'La tarjeta está vencida.';
    if (str_contains($error, 'incorrect_cvc'))      return 'CVV incorrecto.';
    if (str_contains($error, 'no configurado'))     return $error; // Mensajes propios pasan directo
    if (str_contains($error, 'no válid'))           return $error;
    return 'Error al procesar el pago. Intenta de nuevo o contacta soporte.';
}
