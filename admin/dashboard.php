<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kids.php';

gb2_admin_require();

$kids = gb2_kids_all();

gb2_page_start('Admin Dashboard', null);
?>
<div class="card">
  <div class="h1">Parent/Guardian</div>
  <div class="h2">Quick links</div>

  <div class="row" style="margin-top:12px; gap:10px; flex-wrap:wrap">
    <a class="btn primary" href="/admin/family.php">Family Dashboard</a>
    <a class="btn" href="/admin/review.php">Review proofs</a>
    <a class="btn" href="/admin/grounding.php">Privileges</a>
    <a class="btn" href="/admin/setup.php">Setup</a>
    <a class="btn" href="/app/dashboard.php">Kid Dashboard</a>
  </div>
</div>

<div class="card">
  <div class="h1">Kids</div>
  <div class="h2">Who’s in the system</div>

  <?php if (!$kids): ?>
    <div class="note">No kids yet. Go to <a href="/admin/setup.php">Setup</a> to add them.</div>
  <?php else: ?>
    <div class="row" style="gap:10px; flex-wrap:wrap; margin-top:10px">
      <?php foreach ($kids as $k): ?>
        <div class="badge"><?= gb2_h((string)$k['name']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php gb2_nav('admindash'); gb2_page_end(); ?>
