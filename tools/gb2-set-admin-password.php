<?php
declare(strict_types=1);

/**
 * One-shot admin password setter.
 * Writes bcrypt hash into /var/www/data/config.php (array config).
 *
 * Usage:
 *   php /var/www/tools/gb2-set-admin-password.php "YourPasswordHere"
 */

$pw = $argv[1] ?? '';
if (!is_string($pw) || $pw === '') {
  fwrite(STDERR, "Usage: php gb2-set-admin-password.php \"YourPasswordHere\"\n");
  exit(2);
}

$configPath = '/var/www/data/config.php';
if (!is_file($configPath)) {
  fwrite(STDERR, "Missing {$configPath}\n");
  exit(2);
}

$cfg = require $configPath;
if (!is_array($cfg)) {
  fwrite(STDERR, "{$configPath} did not return an array\n");
  exit(2);
}

$cfg['admin_password_hash'] = password_hash($pw, PASSWORD_DEFAULT);

$out = "<?php\nreturn " . var_export($cfg, true) . ";\n";
file_put_contents($configPath, $out);

echo "OK wrote admin_password_hash (len=" . strlen($cfg['admin_password_hash']) . ")\n";
