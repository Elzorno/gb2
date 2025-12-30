<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/rotation.php';
require_once __DIR__ . '/../lib/privileges.php';

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

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php
if (function_exists('gb2_ui_topbar')) {
  echo gb2_ui_topbar('Dashboard', $kid);
} else {
  echo '<header style="padding:12px;border-bottom:1px solid #ddd"><strong>Dashboard</strong></header>';
}
?>

<main class="container" style="max-width:900px;margin:0 auto;padding:16px">

  <section class="card" style="padding:14px;margin-bottom:14px">
    <h2 style="margin:0 0 8px 0">Today</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="/app/today.php">Today View</a>
      <a class="btn" href="/app/bonus.php">Bonus Chores</a>
      <a class="btn" href="/app/history.php">History</a>
    </div>

    <div style="margin-top:12px">
      <h3 style="margin:0 0 6px 0;font-size:1rem">Your chores</h3>
      <?php if (!$assignments): ?>
        <div class="muted">No weekday assignments for today.</div>
      <?php else: ?>
        <ul>
          <?php foreach ($assignments as $a): ?>
            <li><?= h((string)($a['slot_label'] ?? $a['chore_title'] ?? 'Chore')) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" style="padding:14px;margin-bottom:14px">
    <h2 style="margin:0 0 8px 0">Grounding / Privileges</h2>

    <ul style="margin:0;padding-left:18px">
      <li>Phone: <?= ((int)$priv['phone_locked'] === 1) ? 'Locked' : 'Allowed' ?></li>
      <li>Games: <?= ((int)$priv['games_locked'] === 1) ? 'Locked' : 'Allowed' ?></li>
      <li>Other: <?= ((int)$priv['other_locked'] === 1) ? 'Locked' : 'Allowed' ?></li>
    </ul>

    <div class="muted" style="margin-top:8px">
      Banks: Phone <?= (int)$priv['bank_phone_min'] ?> • Games <?= (int)$priv['bank_games_min'] ?> • Other <?= (int)$priv['bank_other_min'] ?>
    </div>

    <div style="margin-top:10px">
      <a class="btn" href="/admin/grounding.php">Admin: Manage Privileges</a>
    </div>
  </section>

</main>
</body>
</html>
