<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();
$kid = gb2_kid_require();

$today    = (new DateTimeImmutable('today'))->format('Y-m-d');
$weekStart = gb2_bonus_week_start($today);

// Ensure weekly instances exist
$pdo = gb2_pdo();
$st = $pdo->prepare("SELECT COUNT(*) as c FROM bonus_instances WHERE week_start=?");
$st->execute([$weekStart]);
$c = (int)($st->fetch()['c'] ?? 0);
if ($c === 0) {
  gb2_bonus_reset_week($weekStart);
}

$list = gb2_bonus_list_week($weekStart);

gb2_page_start('Bonuses', $kid);
?>
<div class="card">
  <div class="h1">This Week</div>
  <div class="h2">Week starting <?= gb2_h($weekStart) ?></div>

  <?php gb2_flash_render(); ?>

  <?php foreach ($list as $b): ?>
    <?php
      $title   = (string)($b['title'] ?? 'Bonus');
      $status  = (string)($b['status'] ?? 'available');
      $instId  = (int)($b['instance_id'] ?? 0);

      $rewardCents = (int)($b['reward_cents'] ?? 0);
      $rPhone      = (int)($b['reward_phone_min'] ?? 0);
      $rGames      = (int)($b['reward_games_min'] ?? 0);

      $claimedBy   = (int)($b['claimed_by_kid'] ?? 0);
      $kidId       = (int)($kid['kid_id'] ?? 0);
    ?>
    <div class="card" style="margin:12px 0 0">
      <div class="row">
        <div class="kv">
          <div class="h1"><?= gb2_h($title) ?></div>
          <div class="small">
            Reward:
            <?php if ($rewardCents > 0): ?>
              $<?= number_format($rewardCents / 100, 2) ?>
            <?php endif; ?>
            <?php if ($rPhone > 0): ?>
              · +<?= (int)$rPhone ?> min phone
            <?php endif; ?>
            <?php if ($rGames > 0): ?>
              · +<?= (int)$rGames ?> min games
            <?php endif; ?>
            <?php if ($rewardCents <= 0 && $rPhone <= 0 && $rGames <= 0): ?>
              (no reward configured)
            <?php endif; ?>
          </div>
        </div>

        <div class="status <?= gb2_h($status) ?>"><?= gb2_h($status) ?></div>
      </div>

      <?php if ($status === 'available'): ?>
        <form method="post" action="/api/claim_bonus.php" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
          <input type="hidden" name="instance_id" value="<?= (int)$instId ?>">
          <button class="btn ok" type="submit">Claim</button>
        </form>

      <?php elseif ($status === 'claimed' && $claimedBy === $kidId): ?>
        <div class="note" style="margin-top:10px">
          Tap <b>Take photo</b> to use the camera, or <b>Choose photo</b> to pick from your library.
          After you choose, it will submit automatically.
        </div>

        <form method="post" action="/api/submit_proof.php" enctype="multipart/form-data" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
          <input type="hidden" name="kind" value="bonus">
          <input type="hidden" name="week_start" value="<?= gb2_h($weekStart) ?>">
          <input type="hidden" name="instance_id" value="<?= (int)$instId ?>">

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
          <input type="hidden" name="kind" value="bonus">
          <input type="hidden" name="week_start" value="<?= gb2_h($weekStart) ?>">
          <input type="hidden" name="instance_id" value="<?= (int)$instId ?>">
          <input type="hidden" name="no_photo" value="1">
          <button class="btn" type="submit">Verify without photo</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="note" style="margin-top:12px">Bonuses are first-come and reset every Monday.</div>
</div>

<?php gb2_nav('bonuses'); gb2_page_end(); ?>
