<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/audit.php';

function gb2_pin_policy_ok(string $pin): bool {
  $cfg = gb2_config();
  $min = (int)($cfg['session']['pin_min_len'] ?? 6);
  $max = (int)($cfg['session']['pin_max_len'] ?? 6);
  if (!preg_match('/^[0-9]+$/', $pin)) return false;
  $len = strlen($pin);
  return ($len >= $min && $len <= $max);
}

function gb2_kid_current(): ?array {
  $cfg = gb2_config();
  $cookieName = (string)($cfg['session']['kid_device_cookie'] ?? 'gb2_dev');
  if (empty($_COOKIE[$cookieName])) return null;

  $token = (string)$_COOKIE[$cookieName];
  if (!preg_match('/^[A-Fa-f0-9]{64}$/', $token)) return null;
  $raw = hex2bin($token);
  if ($raw === false) return null;
  $tokenHash = hash('sha256', $raw);

  $pdo = gb2_pdo();
  $st = $pdo->prepare("SELECT d.id as dev_id, d.kid_id, k.name
                       FROM kid_devices d
                       JOIN kids k ON k.id=d.kid_id
                       WHERE d.token_hash=? AND d.revoked_at IS NULL
                       LIMIT 1");
  $st->execute([$tokenHash]);
  $row = $st->fetch();
  if (!$row) return null;

  $pdo->prepare("UPDATE kid_devices SET last_seen=?, ip=?, ua=? WHERE id=?")
      ->execute([gb2_now_iso(), gb2_client_ip(), gb2_user_agent(), (int)$row['dev_id']]);

  return ['kid_id'=>(int)$row['kid_id'], 'name'=>(string)$row['name'], 'dev_id'=>(int)$row['dev_id']];
}

/**
 * Admin impersonation helper:
 * If admin is logged in and a kid_id is provided, return that kid (without setting any kid cookies).
 * Used to allow admin to view kid pages without logging kids out.
 */
function gb2_admin_impersonate_kid_from_request(): ?array {
  if (!gb2_admin_current()) return null;

  $kidId = 0;
  if (isset($_GET['kid_id'])) $kidId = (int)$_GET['kid_id'];
  if ($kidId <= 0) return null;

  $pdo = gb2_pdo();
  $st = $pdo->prepare("SELECT id, name FROM kids WHERE id=? LIMIT 1");
  $st->execute([$kidId]);
  $k = $st->fetch(PDO::FETCH_ASSOC);
  if (!$k) return null;

  return ['kid_id'=>(int)$k['id'], 'name'=>(string)$k['name'], 'impersonating'=>true];
}

function gb2_kid_require(): array {
  // 1) If admin wants to view as a kid, allow it.
  $imp = gb2_admin_impersonate_kid_from_request();
  if ($imp) return $imp;

  // 2) Otherwise require real kid session.
  $kid = gb2_kid_current();
  if (!$kid) { header('Location: /app/login.php'); exit; }
  return $kid;
}

function gb2_kid_login(int $kidId, string $pin): bool {
  if (!gb2_pin_policy_ok($pin)) return false;
  $pdo = gb2_pdo();
  $st = $pdo->prepare("SELECT id, pin_hash, name FROM kids WHERE id=?");
  $st->execute([$kidId]);
  $k = $st->fetch();
  if (!$k || empty($k['pin_hash'])) return false;
  if (!password_verify($pin, (string)$k['pin_hash'])) return false;

  $cfg = gb2_config();
  $cookieName = (string)($cfg['session']['kid_device_cookie'] ?? 'gb2_dev');
  $days = (int)($cfg['session']['kid_session_days'] ?? 30);

  $raw = random_bytes(32);
  $token = bin2hex($raw);
  $tokenHash = hash('sha256', $raw);

  $pdo->prepare("INSERT INTO kid_devices(kid_id,token_hash,created_at,last_seen,ip,ua)
                 VALUES(?,?,?,?,?,?)")
      ->execute([$kidId, $tokenHash, gb2_now_iso(), gb2_now_iso(), gb2_client_ip(), gb2_user_agent()]);

  setcookie($cookieName, $token, [
    'expires' => time() + ($days*86400),
    'path' => '/',
    'httponly' => true,
    'secure' => false,
    'samesite' => 'Strict',
  ]);

  gb2_audit('kid', $kidId, 'kid_login', ['name'=>(string)$k['name']]);
  return true;
}

function gb2_kid_logout(): void {
  $cfg = gb2_config();
  $cookieName = (string)($cfg['session']['kid_device_cookie'] ?? 'gb2_dev');
  if (!empty($_COOKIE[$cookieName])) {
    $token = (string)$_COOKIE[$cookieName];
    if (preg_match('/^[A-Fa-f0-9]{64}$/', $token)) {
      $raw = hex2bin($token);
      if ($raw !== false) {
        $tokenHash = hash('sha256', $raw);
        $pdo = gb2_pdo();
        $pdo->prepare("UPDATE kid_devices SET revoked_at=? WHERE token_hash=?")
            ->execute([gb2_now_iso(), $tokenHash]);
      }
    }
  }
  setcookie($cookieName, '', ['expires'=>time()-3600,'path'=>'/','httponly'=>true,'secure'=>false,'samesite'=>'Strict']);
}

function gb2_admin_current(): bool {
  gb2_session_start();
  $cfg = gb2_config();
  $ttlMin = (int)($cfg['session']['admin_session_minutes'] ?? 20);
  $ts = (int)($_SESSION['gb2_admin_ts'] ?? 0);
  if (!$ts) return false;
  if (time() - $ts > ($ttlMin*60)) return false;
  $_SESSION['gb2_admin_ts'] = time();
  return true;
}

function gb2_admin_require(): void {
  if (!gb2_admin_current()) {
    $next = $_SERVER['REQUEST_URI'] ?? '/admin/review.php';
    $nextEnc = rawurlencode($next);
    header("Location: /admin/login.php?next={$nextEnc}");
    exit;
  }
}

function gb2_admin_login(string $password): bool {
  gb2_session_start();
  $cfg = gb2_config();
  $hash = (string)($cfg['admin_password_hash'] ?? '');
  if (!$hash) return false;
  if (!password_verify($password, $hash)) return false;
  $_SESSION['gb2_admin_ts'] = time();
  gb2_audit('admin', 0, 'admin_login', []);
  return true;
}

function gb2_admin_logout(): void {
  gb2_session_start();
  unset($_SESSION['gb2_admin_ts']);
}
