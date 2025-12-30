<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/common.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

gb2_session_start();
gb2_secure_headers();

$pdo = gb2_pdo();
$error = '';

// If already logged in, route appropriately
if (gb2_admin_current()) { header('Location: /admin/family.php'); exit; }
if (gb2_kid_current())   { header('Location: /app/dashboard.php'); exit; }

// Load kids list (includes pin_set flag)
$kids = $pdo->query("
  SELECT id,name,sort_order,
         (CASE WHEN pin_hash IS NULL OR pin_hash='' THEN 0 ELSE 1 END) AS pin_set
  FROM kids
  ORDER BY sort_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? 'kid');

  if ($action === 'admin') {
    $pw = (string)($_POST['admin_password'] ?? '');
    if ($pw === '') $error = 'Enter admin password.';
    elseif (!gb2_admin_login($pw)) $error = 'Invalid admin password.';
    else { header('Location: /admin/family.php'); exit; }
  } else {
    // --- Kid login flow (existing behavior) ---
    $kidId   = (int)($_POST['kid_id'] ?? 0);
    $pin     = (string)($_POST['pin'] ?? '');
    $newPin  = (string)($_POST['new_pin'] ?? '');
    $newPin2 = (string)($_POST['new_pin2'] ?? '');

    $st = $pdo->prepare("SELECT pin_hash FROM kids WHERE id=?");
    $st->execute([$kidId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $pinSet = $row && !empty($row['pin_hash']);

    if ($kidId <= 0) {
      $error = 'Choose your name.';
    } elseif ($pinSet) {
      if ($pin === '') $error = 'Enter your PIN.';
      elseif (!gb2_kid_login($kidId, $pin)) $error = 'Invalid PIN.';
      else { header('Location: /app/dashboard.php'); exit; }
    } else {
      // First-time setup: kid creates their own PIN.
      if ($newPin === '' || $newPin2 === '') $error = 'Create a PIN and confirm it.';
      elseif ($newPin !== $newPin2) $error = 'PINs do not match.';
      elseif (!gb2_pin_policy_ok($newPin)) $error = 'PIN must be 6 digits.';
      else {
        gb2_kid_set_pin($kidId, $newPin);
        if (!gb2_kid_login($kidId, $newPin)) $error = 'Could not log in. Try again.';
        else { header('Location: /app/dashboard.php'); exit; }
      }
    }
  }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<main class="container" style="max-width:900px;margin:0 auto;padding:16px">

  <header style="padding:12px;border-bottom:1px solid #ddd;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center">
    <strong>GB2</strong>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn" href="/app/logout.php">Kid Logout</a>
      <a class="btn" href="/admin/logout.php">Admin Logout</a>
    </div>
  </header>

  <?php if ($error): ?>
    <div class="card" style="padding:12px;margin-bottom:12px;border:1px solid #f3c6c6;background:#fff5f5">
      <?= gb2_h($error) ?>
    </div>
  <?php endif; ?>

  <!-- Admin login -->
  <section class="card" style="padding:14px;margin-bottom:14px">
    <div class="h2" style="margin-bottom:8px">Admin</div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="action" value="admin">
      <label class="small">Admin password</label>
      <input class="input" type="password" name="admin_password" autocomplete="current-password">
      <div style="margin-top:10px">
        <button class="btn" type="submit">Login as Admin</button>
      </div>
    </form>
  </section>

  <!-- Kid login -->
  <section class="card" style="padding:14px;margin-bottom:14px">
    <div class="h2">Kids</div>

    <form method="post" id="kidForm" autocomplete="off">
      <input type="hidden" name="action" value="kid">

      <label class="small">Your name</label>
      <select class="input select" name="kid_id" id="kidSelect" required>
        <option value="">Select…</option>
        <?php foreach ($kids as $k): ?>
          <option value="<?= (int)$k['id'] ?>" data-pinset="<?= (int)$k['pin_set'] ?>"><?= gb2_h($k['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <div style="margin-top:10px">
        <label class="small" id="pinLabel">PIN</label>
        <input class="input" id="pinInput" type="password" inputmode="numeric" pattern="[0-9]*" name="pin" autocomplete="one-time-code">
      </div>

      <div id="newPinWrap" style="display:none;margin-top:10px">
        <label class="small">Create PIN (6 digits)</label>
        <input class="input" id="newPin" type="password" inputmode="numeric" pattern="[0-9]*" name="new_pin" autocomplete="new-password">

        <div style="margin-top:10px">
          <label class="small">Confirm PIN</label>
          <input class="input" id="newPin2" type="password" inputmode="numeric" pattern="[0-9]*" name="new_pin2" autocomplete="new-password">
        </div>
      </div>

      <div style="margin-top:10px">
        <button class="btn" type="submit">Login</button>
      </div>
    </form>
  </section>

</main>

<script>
const sel = document.getElementById('kidSelect');
const pinLabel = document.getElementById('pinLabel');
const pinInput = document.getElementById('pinInput');
const newWrap = document.getElementById('newPinWrap');
const newPin = document.getElementById('newPin');
const newPin2 = document.getElementById('newPin2');

function updateMode() {
  const opt = sel.options[sel.selectedIndex];
  const pinset = opt ? (opt.getAttribute('data-pinset') === '1') : true;

  if (!opt || !opt.value) {
    pinLabel.textContent = 'PIN';
    pinInput.style.display = '';
    pinInput.required = false;
    newWrap.style.display = 'none';
    return;
  }

  if (pinset) {
    pinLabel.textContent = 'PIN';
    pinInput.style.display = '';
    pinInput.required = true;
    newWrap.style.display = 'none';
    newPin.value = '';
    newPin2.value = '';
  } else {
    pinLabel.textContent = 'First time here? Create your PIN';
    pinInput.style.display = 'none';
    pinInput.required = false;
    pinInput.value = '';
    newWrap.style.display = '';
  }
}

sel.addEventListener('change', updateMode);
updateMode();
</script>

</body>
</html>
