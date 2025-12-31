<?php
declare(strict_types=1);

/**
 * gb2-set-admin-password.php
 *
 * Usage:
 *   php /var/www/tools/gb2-set-admin-password.php "NewPasswordHere"
 *
 * Writes admin_password_hash into /var/www/data/config.local.php (NOT committed).
 * Does not modify /var/www/data/config.php (loader) or any repo-tracked files.
 */

function dieerr(string $msg, int $code = 1): never {
  fwrite(STDERR, "ERROR: {$msg}\n");
  exit($code);
}

$pw = $argv[1] ?? '';
if ($pw === '') dieerr('Password argument required.');

$localPath = __DIR__ . '/../data/config.local.php';
$samplePath = __DIR__ . '/../config.sample.php';

$cfg = [];
if (is_file($localPath)) {
  $cfg = require $localPath;
  if (!is_array($cfg)) dieerr("Local config must return an array: {$localPath}");
} else {
  $cfg = require $samplePath;
  if (!is_array($cfg)) dieerr("Sample config must return an array: {$samplePath}");
}

$hash = password_hash($pw, PASSWORD_DEFAULT);
if ($hash === false) dieerr('password_hash() failed.');

$cfg['admin_password_hash'] = $hash;

$tmp = $localPath . '.tmp';
$php = "<?php\n"
     . "declare(strict_types=1);\n\n"
     . "/**\n"
     . " * Local runtime config (NOT committed).\n"
     . " * Generated/updated by tools/gb2-set-admin-password.php\n"
     . " */\n\n"
     . "return " . var_export($cfg, true) . ";\n";

if (file_put_contents($tmp, $php) === false) dieerr("Failed writing temp file: {$tmp}");
if (!rename($tmp, $localPath)) dieerr("Failed replacing local config: {$localPath}");

chmod($localPath, 0660);
echo "OK wrote admin_password_hash into {$localPath} (len=" . strlen($cfg['admin_password_hash']) . ")\n";
