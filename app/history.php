<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();
$kid = gb2_kid_require();
$pdo = gb2_pdo();

$st = $pdo->prepare("SELECT * FROM submissions WHERE kid_id=? ORDER BY submitted_at DESC LIMIT 30");
$st->execute([(int)$kid['kid_id']]);
$rows = $st->fetchAll();

gb2_page_start('History', $kid);
?>
<div class="card">
  <div class="h1">Recent submissions</div>
  <div class="h2">Last 30</div>
  <?php if (!$rows): ?>
    <div class="note">No submissions yet.</div>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <div class="card" style="margin:12px 0 0">
        <div class="row">
          <div class="kv">
            <div class="h1"><?=gb2_h(($r['kind']==='base'?'Base':'Bonus'))?></div>
            <div class="small"><?=gb2_h($r['submitted_at'])?></div>
          </div>
          <div class="status <?=gb2_h($r['status'])?>"><?=gb2_h($r['status'])?></div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php gb2_nav('history'); gb2_page_end(); ?>
