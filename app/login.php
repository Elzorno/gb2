<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();

// If already logged in as kid, go to dashboard
$kid = gb2_kid_current();
if ($kid) {
  header('Location: /app/dashboard.php');
  exit;
}

// Load kids list (known schema)
$err = '';
$kidId = '';
$pin = '';
$kids = [];

try {
  $pdo = gb2_pdo();
  $kids = $pdo->query("SELECT id, name FROM kids ORDER BY sort_order ASC, name COLLATE NOCASE ASC")->fetchAll();
} catch (Throwable $e) {
  $kids = [];
  $err = 'Login is temporarily unavailable. Please tell a parent/guardian.';
}

// POST: authenticate via gb2_kid_login(int, pin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  gb2_csrf_verify();

  $kidId = trim((string)($_POST['kid_id'] ?? ''));
  $pin   = trim((string)($_POST['pin'] ?? ''));

  if ($kidId === '' || !ctype_digit($kidId)) {
    $err = 'Please choose your name.';
  } elseif ($pin === '') {
    $err = 'Please enter your PIN.';
  } else {
    if (gb2_kid_login((int)$kidId, $pin)) {
      header('Location: /app/dashboard.php');
      exit;
    }
    $err = 'That didn’t match. Please try again.';
  }
}

gb2_page_start('Kid Login', null);
?>
<div class="card">
  <div class="h1">Kid Login</div>
  <div class="h2">Choose your name, then enter your PIN.</div>

  <?php if ($err !== ''): ?>
    <div class="status pending" style="margin-top:12px"><?= gb2_h($err) ?></div>
  <?php endif; ?>

  <form method="post" action="/app/login.php" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">

    <label class="small">Your name</label>
    <select class="input" name="kid_id" required>
      <option value="">— Select —</option>
      <?php foreach ($kids as $k): ?>
        <?php
          $id = (string)$k['id'];
          $nm = (string)$k['name'];
          $sel = ($id === $kidId) ? ' selected' : '';
        ?>
        <option value="<?= gb2_h($id) ?>"<?= $sel ?>><?= gb2_h($nm) ?></option>
      <?php endforeach; ?>
    </select>

    <div style="height:10px"></div>

    <label class="small">PIN</label>
    <input class="input" name="pin" type="password" inputmode="numeric" placeholder="••••" required>

    <div style="height:12px"></div>
    <button class="btn primary" type="submit">Log in</button>
  </form>

  <div class="note" style="margin-top:12px">
    Parents/guardians unlock from <a href="/admin/login.php">Parent/Guardian</a>.
  </div>
</div>

<?php gb2_nav('login'); gb2_page_end(); ?>
