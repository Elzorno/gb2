<?php
declare(strict_types=1);

/**
 * Repo config shim (tracked).
 *
 * Real secrets/settings should live in /var/www/data/config.local.php (NOT tracked).
 * /var/www/data/config.php is the runtime loader (NOT tracked).
 *
 * This shim ensures a repo checkout still runs even without local secrets.
 */

$runtime = __DIR__ . '/data/config.php';
if (is_file($runtime)) {
  $cfg = require $runtime;
  if (is_array($cfg)) return $cfg;
}

$sample = __DIR__ . '/config.sample.php';
$cfg = require $sample;
if (!is_array($cfg)) {
  throw new RuntimeException('config.sample.php must return an array');
}
return $cfg;
