<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

$error = '';

$next = (string)($_GET['next'] ?? ($_POST['next'] ?? '/admin/review.php'));
if ($next === '' || $next[0] !== '/') $next = '/admin/review.php'; // basic safety

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();
  $pw = (string)($_POST['password'] ?? '');

  if (!gb2_admin_login($pw)) {
    $error = 'Invalid admin password.';
  } else {
    header("Location: {$next}");
    exit;
  }
}

gb2_page_start('Admin Login');
$tok = gb2_csrf_token();
?>
<div class="card">
  <div class="h1">Admin unlock</div>

  <?php if ($error): ?>
    <div class="status rejected"><?= gb2_h($error) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= gb2_h($tok) ?>">
    <input type="hidden" name="next" value="<?= gb2_h($next) ?>">

    <label class="small">Password</label>
    <input class="input" type="password" name="password" required>

    <div style="height:12px"></div>
    <button class="btn primary" type="submit">Unlock</button>
  </form>
</div>
<?php gb2_page_end(); ?>
