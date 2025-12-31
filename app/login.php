<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

gb2_db_init();

// If already logged in as kid, go to dashboard
$kid = gb2_kid_current();
if ($kid) {
  header('Location: /app/dashboard.php');
  exit;
}

$cfg = gb2_config();
$pinMin = (int)($cfg['session']['pin_min_len'] ?? 6);
$pinMax = (int)($cfg['session']['pin_max_len'] ?? 6);
$pinDesc = ($pinMin === $pinMax) ? "{$pinMin}-digit" : "{$pinMin}–{$pinMax} digits";

$err = '';
$mode = 'login'; // 'login' or 'setpin'
$kidId = trim((string)($_POST['kid_id'] ?? ($_GET['kid_id'] ?? '')));
$kids = [];
$kidRow = null;

try {
  $pdo = gb2_pdo();
  $kids = $pdo->query("SELECT id, name FROM kids ORDER BY sort_order ASC, name COLLATE NOCASE ASC")
              ->fetchAll(PDO::FETCH_ASSOC);

  if ($kidId !== '' && ctype_digit($kidId)) {
    $st = $pdo->prepare("SELECT id, name, pin_hash FROM kids WHERE id=?");
    $st->execute([(int)$kidId]);
    $kidRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    // Determine mode: if no PIN set yet, we switch to set-pin mode
    if ($kidRow && trim((string)($kidRow['pin_hash'] ?? '')) === '') {
      $mode = 'setpin';
    }
  }
} catch (Throwable $e) {
  $kids = [];
  $err = 'Login is temporarily unavailable. Please tell a parent/guardian.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();

  $kidId = trim((string)($_POST['kid_id'] ?? ''));

  if ($kidId === '' || !ctype_digit($kidId)) {
    $err = 'Please choose your name.';
  } else {
    try {
      $pdo = gb2_pdo();
      $st = $pdo->prepare("SELECT id, name, pin_hash FROM kids WHERE id=?");
      $st->execute([(int)$kidId]);
      $kidRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
      $kidRow = null;
      $err = 'Login is temporarily unavailable. Please tell a parent/guardian.';
    }

    if (!$kidRow) {
      $err = 'Please choose your name.';
    } else {
      $existingHash = trim((string)($kidRow['pin_hash'] ?? ''));

      if ($existingHash === '') {
        // --- First-time / reset PIN flow ---
        $mode = 'setpin';
        $new1 = trim((string)($_POST['new_pin'] ?? ''));
        $new2 = trim((string)($_POST['confirm_pin'] ?? ''));

        if ($new1 === '' || $new2 === '') {
          $err = 'Please enter and confirm your new PIN.';
        } elseif (!ctype_digit($new1) || !ctype_digit($new2)) {
          $err = 'PIN must be numbers only.';
        } elseif (!gb2_pin_policy_ok($new1)) {
          $err = "PIN must be {$pinDesc}.";
        } elseif ($new1 !== $new2) {
          $err = 'Those didn’t match. Please try again.';
        } else {
          // Save hash
          try {
            $hash = password_hash($new1, PASSWORD_ARGON2ID);
            $up = $pdo->prepare("UPDATE kids SET pin_hash=? WHERE id=?");
            $up->execute([$hash, (int)$kidRow['id']]);

            // Now log in using existing auth path
            if (gb2_kid_login((int)$kidRow['id'], $new1)) {
              header('Location: /app/dashboard.php');
              exit;
            }

            // If we ever hit this, something else is wrong (cookie write, DB, etc.)
            $err = 'PIN saved, but login failed. Please tell a parent/guardian.';
          } catch (Throwable $e) {
            $err = 'Could not save your PIN. Please tell a parent/guardian.';
          }
        }
      } else {
        // --- Normal login flow ---
        $mode = 'login';
        $pin = trim((string)($_POST['pin'] ?? ''));

        if ($pin === '') {
          $err = 'Please enter your PIN.';
        } elseif (!ctype_digit($pin)) {
          $err = 'PIN must be numbers only.';
        } elseif (!gb2_pin_policy_ok($pin)) {
          $err = "PIN must be {$pinDesc}.";
        } else {
          if (gb2_kid_login((int)$kidRow['id'], $pin)) {
            header('Location: /app/dashboard.php');
            exit;
          }
          $err = 'That didn’t match. Please try again.';
        }
      }
    }
  }
}

gb2_page_start('Kid Login', null);
?>
<div class="card">
  <div class="h1">Kid Login</div>

  <?php if ($mode === 'setpin' && $kidRow): ?>
    <div class="h2">Set a new PIN for <?= gb2_h((string)$kidRow['name']) ?>.</div>
  <?php else: ?>
    <div class="h2">Choose your name, then enter your PIN.</div>
  <?php endif; ?>

  <?php if ($err !== ''): ?>
    <div class="status pending" style="margin-top:12px"><?= gb2_h($err) ?></div>
  <?php endif; ?>

  <form method="post" action="/app/login.php" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">

    <label class="small">Your name</label>
    <select class="input" name="kid_id" required onchange="if (this.value) { window.location.href='/app/login.php?kid_id=' + encodeURIComponent(this.value); }">
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

    <?php if ($mode === 'setpin' && $kidId !== ''): ?>
      <label class="small">New PIN (<?= gb2_h($pinDesc) ?>)</label>
      <input class="input" name="new_pin" type="password" inputmode="numeric" pattern="[0-9]*" placeholder="••••••" required>

      <div style="height:10px"></div>

      <label class="small">Confirm new PIN</label>
      <input class="input" name="confirm_pin" type="password" inputmode="numeric" pattern="[0-9]*" placeholder="••••••" required>

      <div style="height:12px"></div>
      <button class="btn primary" type="submit">Save PIN &amp; Log in</button>

      <div class="note" style="margin-top:10px">
        If you forgot your PIN, ask a parent/guardian to reset it from Family Dashboard.
      </div>
    <?php else: ?>
      <label class="small">PIN (<?= gb2_h($pinDesc) ?>)</label>
      <input class="input" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" placeholder="••••••" required>

      <div style="height:12px"></div>
      <button class="btn primary" type="submit">Log in</button>

      <div class="note" style="margin-top:10px">
        If you forgot your PIN, ask a parent/guardian to reset it.
      </div>
    <?php endif; ?>
  </form>

  <div class="note" style="margin-top:12px">
    Parents/guardians unlock from <a href="/admin/login.php">Parent/Guardian</a>.
  </div>
</div>

<?php gb2_nav('login'); gb2_page_end(); ?>
