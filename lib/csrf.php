<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

function gb2_csrf_token(): string {
  gb2_session_start();
  $cfg = gb2_config();
  $key = (string)($cfg['session']['csrf_key'] ?? 'gb2_csrf');
  if (empty($_SESSION[$key])) $_SESSION[$key] = bin2hex(random_bytes(32));
  return (string)$_SESSION[$key];
}

function gb2_csrf_verify(): void {
  gb2_session_start();
  $cfg = gb2_config();
  $key = (string)($cfg['session']['csrf_key'] ?? 'gb2_csrf');
  $expected = (string)($_SESSION[$key] ?? '');
  $got = (string)($_POST['_csrf'] ?? '');
  if (!$expected || !$got || !hash_equals($expected, $got)) {
    http_response_code(403);
    echo "Security check failed (CSRF). Reload and try again.";
    exit;
  }
}
