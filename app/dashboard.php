<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/rotation.php';
require_once __DIR__ . '/../lib/privileges.php';

gb2_db_init();
$kid = gb2_kid_require();

$todayStr = (new DateTimeImmutable('today'))->format('Y-m-d');
$dObj     = new DateTimeImmutable($todayStr);

$assignments = [];
if (function_exists('gb2_is_weekday') && gb2_is_weekday($dObj)) {
  if (function_exists('gb2_rotation_generate_for_day')) {
    gb2_rotation_generate_for_day($todayStr);
  }
  if (function_exists('gb2_assignments_for_kid_day')) {
    $assignments = gb2_assignments_for_kid_day((int)$kid['kid_id'], $todayStr);
  }
}

$priv = gb2_priv_get_for_kid((int)$kid['kid_id']);

gb2_page_start('Dashboard', $kid);
?>
<div class="card">
  <div class="h1">Today</div>
  <div class="h2"><?= gb2_h((new DateTimeImmutable('now'))->format('l, M j')) ?></div>

  <div class="row" style="margin-top:12px; gap:10px; flex-wrap:wrap">
    <a class="btn primary" href="/app/today.php">Open Today</a>
    <a class="btn" href="/app/bonuses.php">Bonuses</a>
    <a class="btn" href="/app/history.php">History</a>
  </div>

  <div style="margin-top:14px">
    <div class="small">Your chores</div>
    <?php if (!$assignments): ?>
      <div class="note" style="margin-top:8px">No weekday chores are assigned for today.</div>
    <?php else: ?>
      <ul style="margin:8px 0 0 1.2rem">
        <?php foreach ($assignments as $a): ?>
          <?php
            $label = (string)($a['slot_title'] ?? $a['slot_label'] ?? $a['chore_title'] ?? 'Chore');
          ?>
          <li><?= gb2_h($label) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="h1">Privileges</div>
  <div class="h2">Current access</div>

  <div style="margin-top:10px">
    <div class="row" style="gap:10px; flex-wrap:wrap">
      <div class="badge">Phone: <?= ((int)$priv['phone_locked'] === 1) ? 'Locked' : 'Allowed' ?></div>
      <div class="badge">Games: <?= ((int)$priv['games_locked'] === 1) ? 'Locked' : 'Allowed' ?></div>
      <div class="badge">Other: <?= ((int)$priv['other_locked'] === 1) ? 'Locked' : 'Allowed' ?></div>
    </div>

    <div class="note" style="margin-top:10px">
      Banked time — Phone <?= (int)$priv['bank_phone_min'] ?> min • Games <?= (int)$priv['bank_games_min'] ?> min • Other <?= (int)$priv['bank_other_min'] ?> min
    </div>

    <div class="note" style="margin-top:10px">
      Need help? A parent/guardian can unlock from <a href="/admin/login.php">Parent/Guardian</a>.
    </div>
  </div>
</div>

<?php gb2_nav('dashboard'); gb2_page_end(); ?>
