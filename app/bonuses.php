<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();
$kid = gb2_kid_require();
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$weekStart = gb2_bonus_week_start($today);

$pdo = gb2_pdo();
$st = $pdo->prepare("SELECT COUNT(*) as c FROM bonus_instances WHERE week_start=?");
$st->execute([$weekStart]);
$c = (int)($st->fetch()['c'] ?? 0);
if ($c === 0) gb2_bonus_reset_week($weekStart);

$list = gb2_bonus_list_week($weekStart);

gb2_page_start('Bonuses', $kid);
$flash = gb2_flash_from_query();
?>
<div class="card">
  <div class="h1">This Week</div>
  <div class="h2">Week starting <?= gb2_h($weekStart) ?></div>

  <?php gb2_flash_render($flash); ?>

  <?php foreach ($list as $b): ?>
    <div class="card" style="margin:12px 0 0">
      <div class="row">
        <div class="kv">
          <div class="h1"><?= gb2_h((string)$b['title']) ?></div>
          <div class="small">
            Reward:
            <?php if ((int)$b['reward_cents'] > 0): ?> $<?= number_format(((int)$b['reward_cents'])/100, 2) ?><?php endif; ?>
            <?php if ((int)$b['reward_phone_min'] > 0): ?> · +<?= (int)$b['reward_phone_min'] ?> min phone<?php endif; ?>
            <?php if ((int)$b['reward_games_min'] > 0): ?> · +<?= (int)$b['reward_games_min'] ?> min games<?php endif; ?>
          </div>
        </div>
        <div class="status <?= gb2_h((string)$b['status']) ?>"><?= gb2_h((string)$b['status']) ?></div>
      </div>

      <?php if ($b['status'] === 'available'): ?>
        <form method="post" action="/api/claim_bonus.php" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
          <input type="hidden" name="instance_id" value="<?= (int)$b['instance_id'] ?>">
          <button class="btn ok" type="submit">Claim</button>
        </form>

      <?php elseif ($b['status'] === 'claimed' && (int)$b['claimed_by_kid'] === (int)$kid['kid_id']): ?>
        <form method="post" action="/api/submit_proof.php" enctype="multipart/form-data" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
          <input type="hidden" name="kind" value="bonus">
          <input type="hidden" name="week_start" value="<?= gb2_h($weekStart) ?>">
          <input type="hidden" name="instance_id" value="<?= (int)$b['instance_id'] ?>">

          <div class="small" style="margin-bottom:6px">Photo proof</div>

          <input class="input" type="file" name="photo" accept="image/*">

          <div class="note" style="margin-top:8px">
            If photo upload doesn’t work on iPhone, you can submit without a photo and a parent will review.
            <a href="/app/help_photos.php" style="text-decoration:underline">Can’t upload photos?</a>
          </div>

          <div style="height:10px"></div>

          <div class="row" style="gap:10px; flex-wrap:wrap; justify-content:flex-start">
            <button class="btn primary" type="submit">Submit proof</button>

            <button class="btn" type="submit" name="no_photo" value="1"
                    onclick="return confirm('Submit without a photo? A parent/guardian will review this proof manually.');">
              Submit without photo
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="note" style="margin-top:12px">Bonuses are first-come and reset every Monday.</div>
</div>

<?php gb2_nav('bonuses'); gb2_page_end(); ?>
