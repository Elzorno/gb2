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

  <?php gb2_flash_render(); ?>

  <?php if (!gb2_is_weekday($dObj)): ?>
    <div class="status pending" style="margin-top:12px">Weekend mode — no base rotation today.</div>
    <div class="note" style="margin-top:10px">Check Bonuses for optional tasks.</div>
  <?php else: ?>

    <?php if (!$items): ?>
      <div class="note" style="margin-top:12px">No weekday chores are assigned for today.</div>
    <?php endif; ?>

    <?php foreach ($items as $it): ?>
      <?php
        $slotTitle = (string)($it['slot_title'] ?? 'Chore');
        $status    = (string)($it['status'] ?? 'open');
        $slotId    = (int)($it['slot_id'] ?? 0);
      ?>
      <div class="card" style="margin:12px 0 0">
        <div class="row">
          <div class="kv">
            <div class="h1"><?= gb2_h($slotTitle) ?></div>
            <div class="small">Status: <span class="status <?= gb2_h($status) ?>"><?= gb2_h($status) ?></span></div>
          </div>
        </div>

        <div class="note" style="margin-top:10px">
          Tap <b>Take photo</b> to use the camera, or <b>Choose photo</b> to pick from your library.
          After you choose, it will submit automatically.
        </div>

        <form method="post" action="/api/submit_proof.php" enctype="multipart/form-data" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
          <input type="hidden" name="kind" value="base">
          <input type="hidden" name="day" value="<?= gb2_h($today) ?>">
          <input type="hidden" name="slot_id" value="<?= (int)$slotId ?>">

          <div class="grid two" style="align-items:center">
            <div>
              <label class="btn" style="display:inline-block; text-align:center; width:100%">
                Take photo
                <input class="input" type="file" name="photo_camera" accept="image/*" capture="environment"
                       onchange="this.form.submit()"
                       style="display:none">
              </label>
            </div>
            <div>
              <label class="btn" style="display:inline-block; text-align:center; width:100%">
                Choose photo
                <input class="input" type="file" name="photo_library" accept="image/*"
                       onchange="this.form.submit()"
                       style="display:none">
              </label>
            </div>
          </div>
        </form>

        <form method="post" action="/api/submit_proof.php" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
          <input type="hidden" name="kind" value="base">
          <input type="hidden" name="day" value="<?= gb2_h($today) ?>">
          <input type="hidden" name="slot_id" value="<?= (int)$slotId ?>">
          <input type="hidden" name="no_photo" value="1">
          <button class="btn" type="submit">Verify without photo</button>
        </form>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<?php gb2_nav('today'); gb2_page_end(); ?>
