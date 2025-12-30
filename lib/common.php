<?php
declare(strict_types=1);

function gb2_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  $path = __DIR__ . '/../config.php';
  if (!file_exists($path)) {
    $cfg = require __DIR__ . '/../config.sample.php';
    $cfg['admin_password_hash'] = '';
    return $cfg;
  }
  $cfg = require $path;
  return $cfg;
}

function gb2_data_dir(): string {
  $cfg = gb2_config();
  return (string)($cfg['data_dir'] ?? (__DIR__ . '/../data'));
}

function gb2_session_start(): void {
  $cfg = gb2_config();
  $name = (string)($cfg['session']['cookie_name'] ?? 'gb2_sess');
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name($name);
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => false,
    'samesite' => 'Strict',
  ]);
  session_start();
}

function gb2_secure_headers(): void {
  header('X-Frame-Options: DENY');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: no-referrer');
  header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
}

function gb2_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function gb2_now_iso(): string { return gmdate('c'); }

function gb2_client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

function gb2_user_agent(): string { return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300); }
