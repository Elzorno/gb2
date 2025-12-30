<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';

gb2_db_init();
gb2_admin_require();

$pdo = gb2_pdo();
$kids = gb2_kids_all();

function pv_row(PDO $pdo, int $kidId): array {
  $st = $pdo->prepare("SELECT * FROM privileges WHERE kid_id=?");
  $st->execute([$kidId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: [
    'kid_id'=>$kidId,
    'phone_locked'=>0,'games_locked'=>0,'other_locked'=>0,
    'bank_phone_min'=>0,'bank_games_min'=>0,'bank_other_min'=>0
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  gb2_csrf_verify();

  $kidId = (int)($_POST['kid_id'] ?? 0);
  if ($kidId > 0) {
    $phone = isset($_POST['phone_locked']) ? 1 : 0;
    $games = isset($_POST['games_locked']) ? 1 : 0;
    $other = isset($_POST['other_locked']) ? 1 : 0;
    $bank_phone = max(0, (int)($_POST['bank_phone_min'] ?? 0));
    $bank_games = max(0, (int)($_POST['bank_games_min'] ?? 0));
    $bank_other = max(0, (int)($_POST['bank_other_min'] ?? 0));

    $pdo->prepare(
      "INSERT INTO privileges(kid_id,phone_locked,games_locked,other_locked,bank_phone_min,bank_games_min,bank_other_min)
       VALUES(?,?,?,?,?,?,?)
       ON CONFLICT(kid_id) DO UPDATE SET
         phone_locked=excluded.phone_locked,
         games_locked=excluded.games_locked,
         other_locked=excluded.other_locked,
         bank_phone_min=excluded.bank_phone_min,
         bank_games_min=excluded.bank_games_min,
         bank_other_min=excluded.bank_other_min"
    )->execute([$kidId,$phone,$games,$other,$bank_phone,$bank_games,$bank_other]);

    header("Location: /admin/grounding.php?saved=1");
    exit;
  }
}

gb2_page_start('Grounding / Privileges');
$tok = gb2_csrf_token();
?>
<div class="card">
  <div class="h1">Grounding / Privileges</div>
  <div class="note">Per-kid locks + minute banks.</div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="status approved" style="margin-top:10px">Saved.</div>
  <?php endif; ?>
</div>

<?php foreach ($kids as $k): $pv = pv_row($pdo, (int)$k['id']); ?>
  <form class="card" method="post" action="/admin/grounding.php" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= gb2_h($tok) ?>">
    <input type="hidden" name="kid_id" value="<?= (int)$k['id'] ?>">

    <div class="row">
      <div class="kv">
        <div class="h1"><?= gb2_h((string)$k['name']) ?></div>
        <div class="h2">Locks and banks</div>
      </div>
    </div>

    <div class="grid" style="margin-top:10px">
      <label class="check"><input type="checkbox" name="phone_locked" <?= ((int)$pv['phone_locked'] ? 'checked' : '') ?>> Phone locked</label>
      <label class="check"><input type="checkbox" name="games_locked" <?= ((int)$pv['games_locked'] ? 'checked' : '') ?>> Games locked</label>
      <label class="check"><input type="checkbox" name="other_locked" <?= ((int)$pv['other_locked'] ? 'checked' : '') ?>> Other locked</label>
    </div>

    <div class="grid" style="margin-top:10px">
      <label>Bank phone (min)<input class="input" type="number" min="0" name="bank_phone_min" value="<?= (int)$pv['bank_phone_min'] ?>"></label>
      <label>Bank games (min)<input class="input" type="number" min="0" name="bank_games_min" value="<?= (int)$pv['bank_games_min'] ?>"></label>
      <label>Bank other (min)<input class="input" type="number" min="0" name="bank_other_min" value="<?= (int)$pv['bank_other_min'] ?>"></label>
    </div>

    <div style="height:10px"></div>
    <button class="btn primary" type="submit">Save</button>
  </form>
<?php endforeach; ?>

<?php gb2_nav('grounding'); gb2_page_end(); ?>
