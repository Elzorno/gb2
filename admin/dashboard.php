<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/rotation.php';
require_once __DIR__ . '/../lib/bonuses.php';
gb2_admin_require();

$kids = gb2_kids_all();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>GB2 Admin Dashboard</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; ?>
<div class="container">
  <h1>Admin Dashboard</h1>
  <div class="card">
    <h2>Quick links</h2>
    <div class="grid">
      <a class="btn" href="/admin/setup.php">Setup / Config</a>
      <a class="btn" href="/admin/review.php">Review / Approvals</a>
      <a class="btn" href="/admin/grounding.php">Grounding / Privileges</a>
      <a class="btn" href="/app/dashboard.php">Family Dashboard</a>
    </div>
  </div>

  <div class="card">
    <h2>Kids</h2>
    <?php if (!$kids): ?>
      <p>No kids yet. Go to <a href="/admin/setup.php">Setup</a>.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($kids as $k): ?>
          <li><?= htmlspecialchars($k['name']) ?><?= ((int)($k['active'] ?? 1) === 1)?'':' (inactive)' ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
