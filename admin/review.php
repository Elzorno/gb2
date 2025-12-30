<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

gb2_db_init();
gb2_admin_require();

$pdo = gb2_pdo();
$pending = $pdo->query("SELECT s.*, k.name FROM submissions s JOIN kids k ON k.id=s.kid_id WHERE s.status='pending' ORDER BY s.submitted_at ASC LIMIT 50")->fetchAll();

gb2_page_start('Review');
$tok = gb2_csrf_token();
?>
<div class="card">
  <div class="row">
    <div class="kv">
      <div class="h1">Pending proofs</div>
      <div class="h2">Approve or reject</div>
    </div>
    <a class="btn" href="/admin/logout.php">Lock</a>
  </div>

  <?php if (!$pending): ?><div class="status approved">Nothing pending 🎉</div><?php endif; ?>

  <?php foreach ($pending as $p): ?>
    <div class="card" style="margin:12px 0 0">
      <div class="row">
        <div class="kv">
          <div class="h1"><?=gb2_h($p['name'])?></div>
          <div class="small"><?=gb2_h($p['kind'])?> · <?=gb2_h($p['submitted_at'])?></div>
        </div>
        <div class="status pending">pending</div>
      </div>
      <div style="margin-top:10px">
        <a class="btn" href="/data/<?=gb2_h($p['photo_path'])?>" target="_blank">View photo</a>
      </div>
      <form method="post" action="/api/approve.php" style="margin-top:10px" class="grid two">
        <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
        <input type="hidden" name="sub_id" value="<?= (int)$p['id'] ?>">
        <input class="input" name="note" placeholder="Optional note">
        <div class="row" style="justify-content:flex-end">
          <button class="btn bad" name="decision" value="rejected" type="submit">Reject</button>
          <button class="btn ok" name="decision" value="approved" type="submit">Approve</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php gb2_nav('review'); gb2_page_end(); ?>
