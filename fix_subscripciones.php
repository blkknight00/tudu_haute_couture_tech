<?php
// Fix: crear tabla subscripciones sin FK constraint (compatible con cualquier tipo de ID)
require_once __DIR__ . '/config.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'])) die('Solo localhost');

$results = [];
$errors  = [];

// 1. Detectar el tipo real del id de organizaciones
$colInfo = $pdo->query("
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organizaciones' AND COLUMN_NAME = 'id'
")->fetch(PDO::FETCH_ASSOC);

$idType = $colInfo['COLUMN_TYPE'] ?? 'int';
$results[] = "🔍 organizaciones.id es: $idType";

// 2. Crear subscripciones con el tipo correcto (sin FK, solo índice)
$sql = "CREATE TABLE IF NOT EXISTS subscripciones (
    id                      $idType AUTO_INCREMENT PRIMARY KEY,
    organizacion_id         $idType NOT NULL,
    edition                 ENUM('standalone','corp') NOT NULL DEFAULT 'standalone',
    plan                    ENUM('starter','pro','agency','enterprise') NOT NULL,
    status                  ENUM('active','cancelled','past_due','trialing') NOT NULL DEFAULT 'trialing',
    payment_provider        ENUM('stripe','conekta') NULL,
    stripe_subscription_id  VARCHAR(100) NULL,
    stripe_customer_id      VARCHAR(100) NULL,
    conekta_order_id        VARCHAR(100) NULL,
    amount_mxn              INT UNSIGNED NOT NULL DEFAULT 0,
    members_limit           SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    projects_limit          SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    tasks_limit             INT UNSIGNED NOT NULL DEFAULT 100,
    storage_limit_mb        INT UNSIGNED NOT NULL DEFAULT 500,
    trial_ends_at           TIMESTAMP NULL,
    current_period_start    TIMESTAMP NULL,
    current_period_end      TIMESTAMP NULL,
    cancelled_at            TIMESTAMP NULL,
    created_at              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subs_org_status (organizacion_id, status),
    INDEX idx_subs_stripe_sub (stripe_subscription_id),
    INDEX idx_subs_period_end (current_period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $pdo->exec($sql);
    $results[] = "✅ Tabla 'subscripciones' creada correctamente";
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'already exists') !== false) {
        $results[] = "⏭️ Tabla 'subscripciones' ya existía";
    } else {
        $errors[] = $e->getMessage();
    }
}

// 3. Verificar todas las tablas creadas
$tables = ['organizaciones','subscripciones','system_settings','whatsapp_messages','guest_access'];
foreach ($tables as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
    $results[] = ($exists ? "✅" : "❌") . " Tabla '$t': " . ($exists ? 'OK' : 'NO ENCONTRADA');
}

?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>TuDu · Fix Subscripciones</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,sans-serif;background:#09090b;color:#e4e4e7;padding:32px}
  .card{background:#18181b;border:1px solid #27272a;border-radius:16px;padding:28px;max-width:620px;margin:0 auto}
  h1{font-size:18px;margin-bottom:4px}
  .sub{font-size:12px;color:#71717a;margin-bottom:20px}
  .log{background:#09090b;border:1px solid #27272a;border-radius:10px;padding:14px;font-family:monospace;font-size:12px;margin-bottom:16px;line-height:2}
  .log .ok{color:#34d399;display:block}
  .log .err{color:#f87171;display:block}
  .btn{display:inline-block;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:500;text-decoration:none;margin-right:8px}
  .btn-primary{background:#7c3aed;color:white}
  .btn-second{background:#27272a;color:#e4e4e7}
</style>
</head>
<body>
<div class="card">
  <h1>🔧 Fix · Tabla subscripciones</h1>
  <div class="sub">Crea la tabla con el tipo de ID correcto (sin FK rígido)</div>
  <div class="log">
    <?php foreach($results as $r): ?>
      <span class="<?= str_starts_with($r,'❌') ? 'err' : 'ok' ?>"><?= htmlspecialchars($r) ?></span>
    <?php endforeach; ?>
    <?php foreach($errors as $e): ?>
      <span class="err">❌ <?= htmlspecialchars($e) ?></span>
    <?php endforeach; ?>
  </div>
  <?php if(empty($errors)): ?>
    <a class="btn btn-primary" href="setup_superadmins.php">→ Crear Super Admins</a>
  <?php endif; ?>
  <a class="btn btn-second" href="http://localhost:5173">Abrir TuDu</a>
</div>
</body>
</html>
