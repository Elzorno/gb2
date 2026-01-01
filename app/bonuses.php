<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/ledger.php';
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

  <?php
    $kidId = (int)($kid['kid_id'] ?? 0);
    $earnedWeek = gb2_ledger_sum_cents_for_kid($kidId, 'bonus_reward', $weekStart);
    $earnedAll  = gb2_ledger_sum_cents_for_kid($kidId, 'bonus_reward', null);
    $recentEarn = gb2_ledger_list_for_kid($kidId, 10, 'bonus_reward');
  ?>
  <div class="card" style="margin-top:12px">
    <div class="h2">Your earnings</div>
    <div class="kv">This week: <b><?= gb2_h(gb2_money($earnedWeek)) ?></b> · Total: <b><?= gb2_h(gb2_money($earnedAll)) ?></b></div>
    <?php if ($recentEarn): ?>
      <div class="note" style="margin-top:8px">Recent approvals</div>
      <div class="list" style="margin-top:8px">
        <?php foreach ($recentEarn as $e): ?>
          <div class="row" style="justify-content:space-between">
            <div>
              <div class="k"><?= gb2_h((string)($e['note'] ?? 'Bonus')) ?></div>
              <div class="v"><?= gb2_h(substr((string)$e['ts'], 0, 10)) ?></div>
            </div>
            <div class="badge"><?= gb2_h(gb2_money((int)$e['amount_cents'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="note" style="margin-top:8px">No bonus earnings yet. Claim a bonus, do the work, and submit proof.</div>
    <?php endif; ?>
  </div>

  <?php foreach ($list as $b): ?>
    <?php
      $title   = (string)($b['title'] ?? 'Bonus');
      $status  = (string)($b['status'] ?? 'available');
      $instId  = (int)($b['instance_id'] ?? 0);

      $rewardCents = (int)($b['reward_cents'] ?? 0);

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
              <?= gb2_h(gb2_money($rewardCents)) ?>
            <?php endif; ?>
            <?php if ($rewardCents <= 0): ?>
              (no cash reward configured)
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
