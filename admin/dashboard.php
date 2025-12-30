<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/kids.php';

gb2_db_init();
gb2_admin_require();

$kids = gb2_kids_all();

gb2_page_start('Admin Dashboard');
?>
<div class="card">
  <div class="h1">Admin Dashboard</div>
  <div class="h2">Quick links</div>

  <div class="grid" style="margin-top:10px">
    <a class="btn" href="/admin/setup.php">Setup / Config</a>
    <a class="btn" href="/admin/review.php">Review / Approvals</a>
    <a class="btn" href="/admin/grounding.php">Grounding / Privileges</a>
    <a class="btn" href="/admin/family.php">Family Dashboard</a>
    <a class="btn" href="/app/dashboard.php">Kid View</a>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <div class="h1">Kids</div>
  <?php if (!$kids): ?>
    <div class="note">No kids yet. Go to <a href="/admin/setup.php">Setup</a>.</div>
  <?php else: ?>
    <ul style="margin:8px 0 0;padding-left:18px">
      <?php foreach ($kids as $k): ?>
        <li><?= gb2_h((string)$k['name']) ?><?= ((int)($k['active'] ?? 1) === 1) ? '' : ' (inactive)' ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php gb2_nav('dashboard'); gb2_page_end(); ?>
