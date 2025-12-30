<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();

$pdo = gb2_pdo();
$kids = $pdo->query("SELECT id,name,sort_order, (CASE WHEN pin_hash IS NULL OR pin_hash='' THEN 0 ELSE 1 END) AS pin_set FROM kids ORDER BY sort_order ASC, name ASC")->fetchAll();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();
  $kidId = (int)($_POST['kid_id'] ?? 0);
  $pin = (string)($_POST['pin'] ?? '');
  $newPin = (string)($_POST['new_pin'] ?? '');
  $newPin2 = (string)($_POST['new_pin2'] ?? '');

  // Determine whether this kid already has a PIN set.
  $st = $pdo->prepare("SELECT pin_hash FROM kids WHERE id=?");
  $st->execute([$kidId]);
  $row = $st->fetch();
  $pinSet = $row && !empty($row['pin_hash']);

  if ($kidId <= 0) {
    $error = 'Select your name.';
  } elseif ($pinSet) {
    if ($pin === '') $error = 'Enter your PIN.';
    elseif (!gb2_kid_login($kidId, $pin)) $error = 'Invalid PIN.';
    else { header('Location: /app/today.php'); exit; }
  } else {
    // First-time setup: let the kid create their own PIN.
    if ($newPin === '' || $newPin2 === '') $error = 'Create a PIN and confirm it.';
    elseif ($newPin !== $newPin2) $error = 'PINs do not match.';
    elseif (!gb2_pin_policy_ok($newPin)) $error = 'PIN must be 6 digits.';
    else {
      gb2_kid_set_pin($kidId, $newPin);
      if (!gb2_kid_login($kidId, $newPin)) $error = 'Could not log in. Try again.';
      else { header('Location: /app/today.php'); exit; }
    }
  }
}

gb2_page_start('GB2 Login');
$tok = gb2_csrf_token();
?>
<div class="card">
  <div class="h1">Sign in</div>
  <div class="h2">Pick your name, enter your PIN.</div>
  <?php if ($error): ?><div class="status rejected"><?=gb2_h($error)?></div><?php endif; ?>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
    <label class="small">Name</label>
    <select id="kidSel" class="input select" name="kid_id" required>
      <option value="">Choose…</option>
      <?php foreach ($kids as $k): ?>
        <option value="<?= (int)$k['id'] ?>" data-pinset="<?= (int)$k['pin_set'] ?>"><?= gb2_h($k['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div style="height:10px"></div>
    <label class="small" id="pinLabel">PIN</label>
    <input class="input" id="pinInput" type="password" inputmode="numeric" pattern="[0-9]*" name="pin" autocomplete="one-time-code">

    <div id="newPinWrap" style="display:none">
      <div style="height:10px"></div>
      <label class="small">Create PIN (6 digits)</label>
      <input class="input" id="newPin" type="password" inputmode="numeric" pattern="[0-9]*" name="new_pin" autocomplete="new-password">
      <div style="height:10px"></div>
      <label class="small">Confirm PIN</label>
      <input class="input" id="newPin2" type="password" inputmode="numeric" pattern="[0-9]*" name="new_pin2" autocomplete="new-password">
    </div>
    <div style="height:12px"></div>
    <button class="btn primary" type="submit">Login</button>
  </form>
  <hr>
  <div class="note">Tip: add this page to your iPhone home screen.</div>
</div>
<script>
(function(){
  const sel = document.getElementById('kidSel');
  const pinLabel = document.getElementById('pinLabel');
  const pinInput = document.getElementById('pinInput');
  const newWrap = document.getElementById('newPinWrap');
  const newPin = document.getElementById('newPin');
  const newPin2 = document.getElementById('newPin2');

  function apply(){
    const opt = sel.options[sel.selectedIndex];
    const pinset = opt ? (opt.getAttribute('data-pinset') === '1') : true;
    if (pinset) {
      pinLabel.textContent = 'PIN';
      pinInput.style.display = '';
      pinInput.required = true;
      newWrap.style.display = 'none';
      newPin.required = false;
      newPin2.required = false;
    } else {
      pinLabel.textContent = 'First time here? Create your PIN';
      pinInput.style.display = 'none';
      pinInput.required = false;
      pinInput.value = '';
      newWrap.style.display = '';
      newPin.required = true;
      newPin2.required = true;
    }
  }
  if (sel) {
    sel.addEventListener('change', apply);
    apply();
  }
})();
</script>
<?php gb2_page_end(); ?>
