<?php
declare(strict_types=1);

/**
 * GB2 Kid Login (Kid-only)
 *
 * Source of truth:
 * - kids table: id, name, pin_hash, sort_order, created_at
 * - kid auth: gb2_kid_login(int $kidId, string $pin): bool  (lib/auth.php)
 * - site CSS: /assets/css/app.css (maps to /var/www/assets/css/app.css)
 *
 * Constraints:
 * - No admin auth changes here; admins unlock at /admin/login.php
 * - No guessing: use the known schema + known auth function
 */

require_once __DIR__ . '/../lib/auth.php';

// ---------- helpers ----------
function gb2_redirect(string $to): void {
  header('Location: ' . $to);
  exit;
}

// CSRF: use project helper if present, else local token
function gb2_login_csrf_field(): string {
  if (function_exists('gb2_csrf_token')) {
    $t = gb2_csrf_token();
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars((string)$t, ENT_QUOTES) . '">';
  }
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
  if (empty($_SESSION['gb2_csrf_token']) || !is_string($_SESSION['gb2_csrf_token'])) {
    $_SESSION['gb2_csrf_token'] = bin2hex(random_bytes(32));
  }
  return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['gb2_csrf_token'], ENT_QUOTES) . '">';
}

function gb2_login_csrf_verify(): void {
  if (function_exists('gb2_csrf_verify')) { gb2_csrf_verify(); return; }
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
  $sent = (string)($_POST['csrf'] ?? '');
  $have = (string)($_SESSION['gb2_csrf_token'] ?? '');
  if ($sent === '' || $have === '' || !hash_equals($have, $sent)) {
    http_response_code(400);
    echo "<h1>Security check failed</h1><p>Please reload the page and try again.</p>";
    exit;
  }
}

// If already logged in as kid, go to dashboard
if (function_exists('gb2_kid_current') && gb2_kid_current()) {
  gb2_redirect('/app/dashboard.php');
}

// ---------- load kids list ----------
$err = '';
$kidId = '';
$pin = '';
$kids = [];

try {
  $pdo = new PDO('sqlite:/var/www/data/app.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $kids = $pdo->query("SELECT id, name FROM kids ORDER BY sort_order ASC, name COLLATE NOCASE ASC")->fetchAll();
} catch (Throwable $e) {
  $kids = [];
  $err = 'Login is temporarily unavailable. Please tell a parent/guardian.';
}

// ---------- POST: authenticate via gb2_kid_login(int, pin) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  gb2_login_csrf_verify();

  $kidId = trim((string)($_POST['kid_id'] ?? ''));
  $pin   = trim((string)($_POST['pin'] ?? ''));

  if ($kidId === '' || !ctype_digit($kidId)) {
    $err = 'Please choose your name.';
  } elseif ($pin === '') {
    $err = 'Please enter your PIN.';
  } else {
    if (gb2_kid_login((int)$kidId, $pin)) {
      gb2_redirect('/app/dashboard.php');
    }
    $err = 'That didn’t match. Please try again.';
  }
}

$title = 'Kid Login';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?></title>

  <!-- Sitewide CSS (constant) -->
  <link rel="stylesheet" href="/assets/css/app.css">

  <!-- Minimal fallback only -->
  <style>
    :root { color-scheme: light dark; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; padding: 24px; max-width: 520px; }
    .card { border: 1px solid rgba(127,127,127,.35); border-radius: 12px; padding: 16px; }
    .row { margin: 12px 0; }
    label { display:block; font-weight: 600; margin-bottom: 6px; }
    select, input { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(127,127,127,.35); }
    button { width: 100%; padding: 12px 14px; border-radius: 10px; border: 0; font-weight: 700; cursor: pointer; }
    .hint { opacity: .8; font-size: 14px; margin-top: 8px; }
    .err { background: rgba(255,0,0,.10); border: 1px solid rgba(255,0,0,.25); padding: 10px 12px; border-radius: 10px; margin: 12px 0; }
    .topbar { display:flex; justify-content: space-between; align-items:center; margin-bottom: 14px; }
    a { text-decoration: none; }
  </style>
</head>
<body>
  <div class="topbar">
    <div><strong>GB2</strong></div>
    <div><a href="/admin/login.php" title="Parent/Guardian unlock">Parent/Guardian</a></div>
  </div>

  <div class="card">
    <h1 style="margin: 0 0 6px 0; font-size: 22px;"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
    <div class="hint">Choose your name, then enter your PIN.</div>

    <?php if ($err !== ''): ?>
      <div class="err"><?= htmlspecialchars($err, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <form method="post" action="/app/login.php" autocomplete="off">
      <?= gb2_login_csrf_field() ?>

      <div class="row">
        <label for="kid_id">Your name</label>
        <select id="kid_id" name="kid_id" required>
          <option value="">— Select —</option>
          <?php foreach ($kids as $k): ?>
            <?php
              $id = (string)$k['id'];
              $nm = (string)$k['name'];
              $sel = ($id === $kidId) ? ' selected' : '';
            ?>
            <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"<?= $sel ?>><?= htmlspecialchars($nm, ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row">
        <label for="pin">PIN</label>
        <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="current-password" placeholder="••••" required>
      </div>

      <div class="row">
        <button type="submit">Log in</button>
      </div>
    </form>
  </div>
</body>
</html>
