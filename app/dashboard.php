<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
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
  <div class="h2"><?= gb2_h($todayStr) ?></div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
    <a class="btn" href="/app/today.php">Today View</a>
    <a class="btn" href="/app/bonuses.php">Bonuses</a>
    <a class="btn" href="/app/history.php">History</a>
  </div>

  <div style="margin-top:12px">
    <div class="small" style="margin-bottom:6px">Your chores</div>
    <?php if (!$assignments): ?>
      <div class="note">No weekday assignments for today.</div>
    <?php else: ?>
      <ul style="margin:0;padding-left:18px">
        <?php foreach ($assignments as $a): ?>
          <li><?= gb2_h((string)($a['slot_title'] ?? $a['slot_label'] ?? $a['chore_title'] ?? 'Chore')) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <div class="h1">Grounding / Privileges</div>
  <div class="h2">What’s allowed right now</div>

  <div style="margin-top:10px">
    <div class="row">
      <div class="kv">
        <div class="small">Phone</div>
        <div class="h1"><?= ((int)$priv['phone_locked'] === 1) ? 'Locked' : 'Allowed' ?></div>
      </div>
    </div>

    <div class="row">
      <div class="kv">
        <div class="small">Games</div>
        <div class="h1"><?= ((int)$priv['games_locked'] === 1) ? 'Locked' : 'Allowed' ?></div>
      </div>
    </div>

    <div class="row">
      <div class="kv">
        <div class="small">Other</div>
        <div class="h1"><?= ((int)$priv['other_locked'] === 1) ? 'Locked' : 'Allowed' ?></div>
      </div>
    </div>

    <div class="note" style="margin-top:10px">
      Banks: Phone <?= (int)$priv['bank_phone_min'] ?> • Games <?= (int)$priv['bank_games_min'] ?> • Other <?= (int)$priv['bank_other_min'] ?>
    </div>

    <div class="small" style="margin-top:10px">
      Parent/Guardian controls are under <a href="/admin/login.php">Parent/Guardian</a>.
    </div>
  </div>
</div>

<?php gb2_nav('dashboard'); gb2_page_end(); ?>
