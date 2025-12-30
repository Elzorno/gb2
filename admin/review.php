<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

gb2_db_init();
gb2_admin_require();

$pdo = gb2_pdo();

// Pending submissions (last 50)
$pending = $pdo->query(
  "SELECT s.*, k.name
   FROM submissions s
   JOIN kids k ON k.id = s.kid_id
   WHERE s.status='pending'
   ORDER BY s.submitted_at ASC
   LIMIT 50"
)->fetchAll();

gb2_page_start('Review', null);
$tok = gb2_csrf_token();
$flash = gb2_flash_from_query();
?>

<div class="card">
  <div class="row">
    <div class="kv">
      <div class="h1">Review</div>
      <div class="h2">Approve or reject submitted proofs</div>
    </div>
  </div>

  <?php gb2_flash_render($flash); ?>

  <?php if (!$pending): ?>
    <div class="status approved" style="margin-top:12px">Nothing pending 🎉</div>
    <div class="note" style="margin-top:10px">
      When a kid submits proof, it will appear here until you approve or reject it.
    </div>
  <?php endif; ?>

  <?php foreach ($pending as $p): ?>
    <?php
      $photoPath = (string)($p['photo_path'] ?? '');
      $hasPhoto  = ($photoPath !== '' && $photoPath !== 'NO_PHOTO');

      $kindRaw = (string)($p['kind'] ?? '');
      $kindTxt = ($kindRaw === 'base') ? 'Base chore' : (($kindRaw === 'bonus') ? 'Bonus' : ($kindRaw !== '' ? $kindRaw : '—'));
      $whenTxt = gb2_human_datetime((string)($p['submitted_at'] ?? ''));
    ?>
    <div class="card" style="margin:12px 0 0">
      <div class="row">
        <div class="kv">
          <div class="h1"><?= gb2_h((string)$p['name']) ?></div>
          <div class="small">
            <?= gb2_h($kindTxt) ?>
            <?php if ($whenTxt !== ''): ?> · <?= gb2_h($whenTxt) ?><?php endif; ?>
          </div>
        </div>
        <div class="status pending"><?= gb2_h(gb2_status_label('pending')) ?></div>
      </div>

      <div style="margin-top:10px" class="row" style="gap:10px; flex-wrap:wrap; justify-content:flex-start">
        <?php if ($hasPhoto): ?>
          <a class="btn" href="/data/<?= gb2_h($photoPath) ?>" target="_blank" rel="noopener">View photo</a>
        <?php else: ?>
          <div class="badge">No photo submitted</div>
        <?php endif; ?>
      </div>

      <form method="post" action="/api/approve.php" style="margin-top:10px" class="grid two">
        <input type="hidden" name="_csrf" value="<?= gb2_h($tok) ?>">
        <input type="hidden" name="sub_id" value="<?= (int)$p['id'] ?>">

        <input class="input" name="note" placeholder="Optional note (visible to you)">

        <div class="row" style="justify-content:flex-end">
          <button class="btn bad" name="decision" value="rejected" type="submit"
                  onclick="return confirm('Reject this proof?');">Reject</button>
          <button class="btn ok" name="decision" value="approved" type="submit">Approve</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php gb2_nav('review'); gb2_page_end(); ?>
