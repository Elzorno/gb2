<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/common.php';

gb2_admin_require();

function gb2_mb_strlen(string $s): int {
  if (function_exists('mb_strlen')) return (int)mb_strlen($s);
  return strlen($s);
}

function gb2_mb_substr(string $s, int $start, int $len): string {
  if (function_exists('mb_substr')) return (string)mb_substr($s, $start, $len);
  return substr($s, $start, $len);
}

function gb2_safe_text(string $s, int $maxLen): string {
  $s = trim($s);
  // Remove control chars
  $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
  if (gb2_mb_strlen($s) > $maxLen) $s = gb2_mb_substr($s, 0, $maxLen);
  return $s;
}


function gb2_load_local_config(string $path): array {
  if (!is_file($path)) return [];
  $v = require $path;
  return is_array($v) ? $v : [];
}

function gb2_write_local_config(string $path, array $cfg): void {
  $dir = dirname($path);
  if (!is_dir($dir)) throw new RuntimeException('Data directory missing');
  $tmp = $path . '.tmp';
  $php = "<?php\n// GB2 runtime config (local only). This file is NOT committed.\n\nreturn " . var_export($cfg, true) . ";\n";
  if (file_put_contents($tmp, $php, LOCK_EX) === false) {
    throw new RuntimeException('Failed writing config');
  }
  // Best effort permissions: readable by web server, writable by root.
  @chmod($tmp, 0640);
  if (!rename($tmp, $path)) {
    @unlink($tmp);
    throw new RuntimeException('Failed saving config');
  }
}

$cfg = gb2_config();
$dataDir = gb2_data_dir();
$localPath = rtrim($dataDir, '/') . '/config.local.php';

$local = gb2_load_local_config($localPath);

$brand  = (string)($cfg['branding']['brand'] ?? 'GB2');
$family = (string)($cfg['branding']['family'] ?? '');

$flash = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    gb2_csrf_verify();

    $newBrand  = gb2_safe_text((string)($_POST['brand'] ?? ''), 16);
    $newFamily = gb2_safe_text((string)($_POST['family'] ?? ''), 32);

    if ($newBrand === '') $newBrand = 'GB2';

    $local['branding'] = is_array($local['branding'] ?? null) ? $local['branding'] : [];
    $local['branding']['brand'] = $newBrand;
    $local['branding']['family'] = $newFamily;

    gb2_write_local_config($localPath, $local);

    // Reload merged config for display on this request
    $brand = $newBrand;
    $family = $newFamily;

    $flash = 'Branding saved.';
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

gb2_page_start('Branding');
?>

<div class="card">
  <div class="h1">Branding</div>
  <div class="note" style="margin-top:8px">
    Customize the header text shown on every page. This writes to <code><?= gb2_h($localPath) ?></code> (local-only; not committed).
  </div>

  <?php if ($flash): ?>
    <div class="status approved" style="margin-top:12px"><?= gb2_h($flash) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="status rejected" style="margin-top:12px"><?= gb2_h($err) ?></div>
  <?php endif; ?>

  <form method="post" style="margin-top:14px">
    <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">

    <label class="label">Brand (short)</label>
    <input class="input" name="brand" value="<?= gb2_h($brand) ?>" placeholder="GB2" maxlength="16">

    <div style="height:10px"></div>

    <label class="label">Family name (optional)</label>
    <input class="input" name="family" value="<?= gb2_h($family) ?>" placeholder="e.g., Zornes Family" maxlength="32">

    <div style="height:14px"></div>
    <button class="btn primary" type="submit">Save</button>
  </form>
</div>

<?php gb2_nav('branding'); gb2_page_end(); ?>
