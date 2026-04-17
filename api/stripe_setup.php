<?php
require_once '../config.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die("Error: vendor/autoload.php no encontrado. Ejecuta composer require stripe/stripe-php");
}
require_once $autoload;

$sk = getenv('STRIPE_SECRET_KEY') ?: '';
$pk = getenv('STRIPE_PUBLIC_KEY') ?: '';

// Update keys
if ($sk && $pk) {
    $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES ('STRIPE_SECRET_KEY', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$sk]);
    $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES ('STRIPE_PUBLIC_KEY', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$pk]);
    echo "Keys registradas en DB.\n";
} else {
    echo "Stripe keys no encontradas en el entorno.\n";
}

$stripe = new \Stripe\StripeClient($sk);

// Pricing catalog MXN
$catalog = [
    'standalone_pro_monthly'     => ['name' => 'TuDu Pro (Mensual)', 'amount' => 19900, 'interval' => 'month'],
    'standalone_pro_annual'      => ['name' => 'TuDu Pro (Anual)', 'amount' => 199000, 'interval' => 'year'],
    'corp_starter_monthly'       => ['name' => 'TuDu Corp Starter (Mensual)', 'amount' => 39900, 'interval' => 'month'],
    'corp_starter_annual'        => ['name' => 'TuDu Corp Starter (Anual)', 'amount' => 399000, 'interval' => 'year'],
    'corp_pro_monthly'           => ['name' => 'TuDu Corp Pro (Mensual)', 'amount' => 99900, 'interval' => 'month'],
    'corp_pro_annual'            => ['name' => 'TuDu Corp Pro (Anual)', 'amount' => 999000, 'interval' => 'year'],
    'corp_agency_monthly'        => ['name' => 'TuDu Corp Agency (Mensual)', 'amount' => 249900, 'interval' => 'month'],
    'corp_agency_annual'         => ['name' => 'TuDu Corp Agency (Anual)', 'amount' => 2499000, 'interval' => 'year'],
];

foreach ($catalog as $key => $settings) {
    try {
        // Try creating product.
        $product = $stripe->products->create([
            'name' => $settings['name']
        ]);

        $price = $stripe->prices->create([
            'unit_amount' => $settings['amount'],
            'currency' => 'mxn',
            'recurring' => ['interval' => $settings['interval']],
            'product' => $product->id,
            'lookup_key' => $key
        ]);

        $settingKey = 'STRIPE_PRICE_' . strtoupper($key);
        $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")
            ->execute([$settingKey, $price->id]);
        
        echo "Exito: {$key} -> {$price->id}\n";
    } catch (Exception $e) {
        echo "Error en {$key}: " . $e->getMessage() . "\n";
    }
}
echo "\n¡Setup de Stripe Completado!\n";
