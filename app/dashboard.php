<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/rotation.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/db.php';

gb2_kid_require();
$kid = gb2_kid_current();

$pdo = gb2_pdo();
$priv = $pdo->prepare("SELECT * FROM privileges WHERE kid_id=?");
$priv->execute([(int)$kid['id']]);
$pv = $priv->fetch() ?: ['phone_locked'=>0,'games_locked'=>0,'other_locked'=>0,'bank_phone_min'=>0,'bank_games_min'=>0,'bank_other_min'=>0];

$today = gb2_today_assignments();
$week = gb2_week_key();
$bonus = gb2_bonus_instances_for_week($week);

function yn($v){ return ((int)$v) ? 'Locked' : 'OK'; }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php gb2_ui_topbar('Dashboard', ['Today'=>'/app/today.php','Bonuses'=>'/app/bonuses.php','History'=>'/app/history.php','Logout'=>'/app/logout.php']); ?>

<div class="container">
  <div class="card">
    <h2>Today</h2>
    <p><strong><?= htmlspecialchars($kid['name']) ?></strong></p>
    <div class="grid">
      <div class="pill">Phone: <?= yn($pv['phone_locked']) ?></div>
      <div class="pill">Games: <?= yn($pv['games_locked']) ?></div>
      <div class="pill">Other: <?= yn($pv['other_locked']) ?></div>
    </div>
    <div class="grid">
      <div class="pill">Bank Phone: <?= (int)$pv['bank_phone_min'] ?> min</div>
      <div class="pill">Bank Games: <?= (int)$pv['bank_games_min'] ?> min</div>
      <div class="pill">Bank Other: <?= (int)$pv['bank_other_min'] ?> min</div>
    </div>
  </div>

  <div class="card">
    <h2>Chores</h2>
    <p>Assigned today: <strong><?= htmlspecialchars($today['slot_title'] ?? '') ?></strong></p>
    <p>Status: <?= htmlspecialchars($today['status'] ?? '—') ?></p>
    <a class="btn" href="/app/today.php">Open Today View</a>
  </div>

  <div class="card">
    <h2>Bonus chores (this week)</h2>
    <?php if (!$bonus): ?>
      <p>No bonus chores yet.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($bonus as $b): ?>
          <li><?= htmlspecialchars($b['title']) ?> — <?= $b['claimed_by_kid_id'] ? 'Claimed' : 'Available' ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <a class="btn" href="/app/bonuses.php">Bonus chores</a>
  </div>
</div>
</body>
</html>
