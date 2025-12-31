<?php
declare(strict_types=1);

function gb2_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;

  $fallback = function(): array {
    $cfg = require __DIR__ . '/../config.sample.php';
    $cfg['admin_password_hash'] = '';
    return $cfg;
  };

  $path = __DIR__ . '/../config.php';
  if (!file_exists($path)) {
    $cfg = $fallback();
    return $cfg;
  }

  // config.php should normally be a PHP file that returns an array.
  // Some zip tools flatten symlinks into a text file containing the target path,
  // e.g. "/var/www/data/config.php". Support that safely.
  $loaded = require $path;

  if (is_array($loaded)) {
    $cfg = $loaded;
    return $cfg;
  }

  if (is_string($loaded)) {
    $candidate = trim($loaded);
    if ($candidate !== '' && file_exists($candidate)) {
      $loaded2 = require $candidate;
      if (is_array($loaded2)) {
        $cfg = $loaded2;
        return $cfg;
      }
    }
  }

  // Last-resort: fall back to sample config to avoid fatal type errors.
  $cfg = $fallback();
  return $cfg;
}

function gb2_data_dir(): string {
  $cfg = gb2_config();
  return (string)($cfg['data_dir'] ?? (__DIR__ . '/../data'));
}

function gb2_is_https(): bool {
  $https = $_SERVER['HTTPS'] ?? '';
  if (is_string($https) && $https !== '' && strtolower($https) !== 'off') return true;

  $port = (string)($_SERVER['SERVER_PORT'] ?? '');
  if ($port === '443') return true;

  $xfp = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
  if (strtolower($xfp) === 'https') return true;

  $xfs = (string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '');
  if (strtolower($xfs) === 'on') return true;

  return false;
}

function gb2_session_start(): void {
  $cfg = gb2_config();
  $name = (string)($cfg['session']['cookie_name'] ?? 'gb2_sess');
  if (session_status() === PHP_SESSION_ACTIVE) return;

  $secure = $cfg['session']['cookie_secure'] ?? null;
  if (!is_bool($secure)) {
    $secure = gb2_is_https();
  }

  session_name($name);
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => $secure,
    'samesite' => 'Strict',
  ]);
  session_start();
}

function gb2_secure_headers(): void {
  header('X-Frame-Options: DENY');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: no-referrer');
  header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');

  // Conservative CSP; designed to work with current GB2 (external JS, some inline styles).
  header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'");
}

function gb2_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function gb2_now_iso(): string { return gmdate('c'); }

function gb2_client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

function gb2_user_agent(): string { return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300); }
