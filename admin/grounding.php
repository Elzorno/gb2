<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/privileges.php';

gb2_db_init();
gb2_admin_require();

$kids = gb2_kids_all();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();

  $kidId = (int)($_POST['kid_id'] ?? 0);
  if ($kidId > 0) {
    $phone = isset($_POST['phone_locked']) ? 1 : 0;
    $games = isset($_POST['games_locked']) ? 1 : 0;
    $other = isset($_POST['other_locked']) ? 1 : 0;

    $bank_phone = max(0, (int)($_POST['bank_phone_min'] ?? 0));
    $bank_games = max(0, (int)($_POST['bank_games_min'] ?? 0));
    $bank_other = max(0, (int)($_POST['bank_other_min'] ?? 0));

    // Use helpers so unlock clears *_locked_until deterministically.
    gb2_priv_set_locks($kidId, $phone, $games, $other);
    gb2_priv_set_banks($kidId, $bank_phone, $bank_games, $bank_other);
  }

  header("Location: /admin/grounding.php?saved=1");
  exit;
}

gb2_page_start('Grounding', null);
$tok = gb2_csrf_token();
?>

<div class="card">
  <div class="h1">Grounding / Privileges</div>
  <div class="h2">Locks and banked minutes for each kid</div>

  <div class="note" style="margin-top:10px">
    This is the “grounding board” core: per-kid locks + minute banks.
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="status approved" style="margin-top:12px">Saved.</div>
  <?php endif; ?>
</div>

<?php foreach ($kids as $k): $pv = gb2_priv_get_for_kid((int)$k['id']); ?>
  <form class="card" method="post" action="/admin/grounding.php">
    <input type="hidden" name="_csrf" value="<?= gb2_h($tok) ?>">
    <input type="hidden" name="kid_id" value="<?= (int)$k['id'] ?>">

    <div class="row">
      <div class="kv">
        <div class="h1"><?= gb2_h((string)$k['name']) ?></div>
        <div class="h2">Locks + banks</div>
      </div>
    </div>

    <div style="height:10px"></div>

    <div class="grid">
      <label class="check">
        <input type="checkbox" name="phone_locked" <?= ((int)$pv['phone_locked'] ? 'checked' : '') ?>>
        Phone locked
      </label>
      <label class="check">
        <input type="checkbox" name="games_locked" <?= ((int)$pv['games_locked'] ? 'checked' : '') ?>>
        Games locked
      </label>
      <label class="check">
        <input type="checkbox" name="other_locked" <?= ((int)$pv['other_locked'] ? 'checked' : '') ?>>
        Other locked
      </label>
    </div>

    <div style="height:10px"></div>

    <div class="grid">
      <label>
        Bank phone (min)
        <input class="input" type="number" min="0" name="bank_phone_min" value="<?= (int)$pv['bank_phone_min'] ?>">
      </label>
      <label>
        Bank games (min)
        <input class="input" type="number" min="0" name="bank_games_min" value="<?= (int)$pv['bank_games_min'] ?>">
      </label>
      <label>
        Bank other (min)
        <input class="input" type="number" min="0" name="bank_other_min" value="<?= (int)$pv['bank_other_min'] ?>">
      </label>
    </div>

    <div style="height:12px"></div>
    <button class="btn primary" type="submit">Save</button>
  </form>
<?php endforeach; ?>

<?php gb2_nav('grounding'); gb2_page_end(); ?>
