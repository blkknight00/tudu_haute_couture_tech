<?php
// ═══════════════════════════════════════════════════════════════════════
// TuDu SaaS · Run Migration Script (v2 - fixed SQL parser)
// Abre en: http://localhost/tudu_haute_couture_tech/run_migration.php
// ═══════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/config.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'])) {
    die('<h2>⛔ Solo accesible desde localhost</h2>');
}

$sqlFile = __DIR__ . '/api/saas_migration.sql';
$rawSql  = file_get_contents($sqlFile);

// ── Parser SQL robusto ────────────────────────────────────────────────
// 1. Quitar líneas que son SOLO comentarios
$lines = explode("\n", $rawSql);
$cleanLines = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    // Conservar si NO es una línea de solo comentario
    if (!str_starts_with($trimmed, '--')) {
        $cleanLines[] = $line;
    }
}
$cleanSql = implode("\n", $cleanLines);

// 2. Dividir por ; pero solo si no está dentro de un string
$statements = [];
$current    = '';
$inStr      = false;
$strChar    = '';

for ($i = 0; $i < strlen($cleanSql); $i++) {
    $char = $cleanSql[$i];

    if (!$inStr && ($char === "'" || $char === '"')) {
        $inStr   = true;
        $strChar = $char;
        $current .= $char;
    } elseif ($inStr && $char === $strChar && ($i === 0 || $cleanSql[$i-1] !== '\\')) {
        $inStr   = false;
        $current .= $char;
    } elseif (!$inStr && $char === ';') {
        $stmt = trim($current);
        if (!empty($stmt)) {
            $statements[] = $stmt;
        }
        $current = '';
    } else {
        $current .= $char;
    }
}
// Último statement sin ; final
if (!empty(trim($current))) {
    $statements[] = trim($current);
}

// ── Ejecutar cada statement ───────────────────────────────────────────
$results = [];
$errors  = [];
$skipped = [];

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;

    // Label legible: primera línea no vacía del statement
    $label = '';
    foreach (explode("\n", $stmt) as $l) {
        $l = trim($l);
        if (!empty($l)) { $label = substr($l, 0, 90); break; }
    }

    try {
        $pdo->exec($stmt);
        $results[] = $label;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Ignorar errores "ya existe" / "duplicate" → idempotente
        if (
            stripos($msg, 'Duplicate column name') !== false ||
            stripos($msg, 'already exists')        !== false ||
            stripos($msg, 'Duplicate entry')       !== false ||
            stripos($msg, 'Duplicate key name')    !== false
        ) {
            $skipped[] = '⏭️ (ya existe) ' . htmlspecialchars(substr($label, 0, 70));
        } else {
            $errors[] = [
                'msg'  => htmlspecialchars($msg),
                'stmt' => htmlspecialchars(substr($stmt, 0, 150)),
            ];
        }
    }
}

$totalOk = count($results);
$totalSk = count($skipped);
$totalEr = count($errors);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>TuDu · Migración SaaS</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, sans-serif; background: #09090b; color: #e4e4e7; padding: 32px; }
  .card { background: #18181b; border: 1px solid #27272a; border-radius: 16px; padding: 28px; max-width: 720px; margin: 0 auto; }
  h1 { font-size: 18px; margin-bottom: 4px; }
  .sub { font-size: 12px; color: #71717a; margin-bottom: 20px; }
  .summary { display: flex; gap: 10px; margin-bottom: 16px; }
  .badge { flex: 1; text-align: center; padding: 10px 6px; border-radius: 10px; font-size: 12px; font-weight: 600; }
  .badge.ok   { background: rgba(5,150,105,0.1);  color: #34d399; border: 1px solid rgba(5,150,105,0.25); }
  .badge.skip { background: rgba(161,161,170,0.08); color: #a1a1aa; border: 1px solid #3f3f46; }
  .badge.err  { background: rgba(220,38,38,0.1);  color: #f87171; border: 1px solid rgba(220,38,38,0.25); }
  .log { background: #09090b; border: 1px solid #27272a; border-radius: 10px; padding: 14px; font-family: monospace; font-size: 11px; max-height: 360px; overflow-y: auto; margin-bottom: 16px; line-height: 1.9; }
  .log .ok   { color: #34d399; display: block; }
  .log .skip { color: #52525b; display: block; }
  .log .err  { color: #f87171; display: block; background: rgba(220,38,38,0.06); padding: 3px 6px; border-radius: 4px; margin: 3px 0; }
  .log .err small { color: #a1a1aa; display: block; margin-top: 2px; font-size: 10px; }
  .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
  .btn { padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none; display: inline-block; }
  .btn-primary   { background: #7c3aed; color: white; }
  .btn-secondary { background: #27272a; color: #e4e4e7; }
  .btn-orange    { background: rgba(234,88,12,0.15); color: #fb923c; border: 1px solid rgba(234,88,12,0.3); }
  .warning { background: rgba(217,119,6,0.08); border: 1px solid rgba(217,119,6,0.2); border-radius: 8px; padding: 10px 14px; font-size: 11px; color: #fbbf24; }
</style>
</head>
<body>
<div class="card">
  <h1>🗄️ TuDu SaaS · Migración v2</h1>
  <div class="sub">api/saas_migration.sql → tudu_v3 · <?= $totalOk + $totalSk + $totalEr ?> statements procesados</div>

  <div class="summary">
    <div class="badge ok">✅ <?= $totalOk ?> ejecutados</div>
    <div class="badge skip">⏭️ <?= $totalSk ?> ya existían</div>
    <div class="badge err"><?= $totalEr > 0 ? '❌' : '✅' ?> <?= $totalEr ?> errores</div>
  </div>

  <div class="log">
    <?php foreach ($results as $r): ?>
      <span class="ok">✓ <?= htmlspecialchars($r) ?></span>
    <?php endforeach; ?>
    <?php foreach ($skipped as $s): ?>
      <span class="skip"><?= $s ?></span>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
      <span class="err">✗ <?= $e['msg'] ?><small><?= $e['stmt'] ?>...</small></span>
    <?php endforeach; ?>
  </div>

  <div class="actions">
    <?php if ($totalEr === 0 && ($totalOk + $totalSk) > 0): ?>
      <a class="btn btn-primary" href="setup_superadmins.php">→ Paso 2: Crear Super Admins</a>
      <a class="btn btn-secondary" href="http://localhost:5173">Abrir TuDu</a>
    <?php elseif ($totalOk + $totalSk === 0): ?>
      <a class="btn btn-orange" href="run_migration.php">🔄 Reintentar</a>
    <?php else: ?>
      <a class="btn btn-primary" href="setup_superadmins.php">→ Paso 2 de todos modos</a>
      <a class="btn btn-orange" href="run_migration.php">🔄 Reintentar errores</a>
    <?php endif; ?>
  </div>

  <div class="warning">⚠️ Elimina <code>run_migration.php</code> y <code>setup_superadmins.php</code> del servidor de producción.</div>
</div>
</body>
</html>
