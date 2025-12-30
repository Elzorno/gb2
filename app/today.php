<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/rotation.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();
$kid = gb2_kid_require();

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$dObj = new DateTimeImmutable($today);

$items = [];
if (gb2_is_weekday($dObj)) {
  gb2_rotation_generate_for_day($today);
  $items = gb2_assignments_for_kid_day((int)$kid['kid_id'], $today);
}

gb2_page_start('Today', $kid);
?>
<div class="card">
  <div class="row">
    <div class="kv">
      <div class="h1"><?= gb2_h((new DateTimeImmutable('now'))->format('l')) ?></div>
      <div class="h2"><?= gb2_h($today) ?></div>
    </div>
  </div>

  <?php if (!gb2_is_weekday($dObj)): ?>
    <div class="status pending">Weekend mode — no base rotation today.</div>
    <div class="note" style="margin-top:10px">Check Bonuses for optional tasks.</div>
  <?php else: ?>
    <?php foreach ($items as $it): ?>
      <div class="card" style="margin:12px 0 0">
        <div class="row">
          <div class="kv">
            <div class="h1"><?= gb2_h($it['slot_title']) ?></div>
            <div class="small">Status: <span class="status <?=gb2_h($it['status'])?>"><?=gb2_h($it['status'])?></span></div>
          </div>
        </div>
        <form method="post" action="/api/submit_proof.php" enctype="multipart/form-data" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?=gb2_h(gb2_csrf_token())?>">
          <input type="hidden" name="kind" value="base">
          <input type="hidden" name="day" value="<?=gb2_h($today)?>">
          <input type="hidden" name="slot_id" value="<?= (int)$it['slot_id'] ?>">
          <label class="small">Photo proof</label>
          <input class="input" type="file" name="photo" accept="image/*" capture="environment" required>
          <div style="height:10px"></div>
          <button class="btn primary" type="submit">Submit proof</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php gb2_nav('today'); gb2_page_end(); ?>
