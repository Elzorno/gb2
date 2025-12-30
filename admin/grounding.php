<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';

gb2_admin_require();
$pdo = gb2_pdo();

$kids = gb2_kids_all();

$csrf = gb2_csrf_token();

function pv_row(PDO $pdo, int $kidId): array {
  $st = $pdo->prepare("SELECT * FROM privileges WHERE kid_id=?");
  $st->execute([$kidId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: ['kid_id'=>$kidId,'phone_locked'=>0,'games_locked'=>0,'other_locked'=>0,'bank_phone_min'=>0,'bank_games_min'=>0,'bank_other_min'=>0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  gb2_csrf_require();
  $kidId = (int)($_POST['kid_id'] ?? 0);
  if ($kidId>0) {
    $phone = isset($_POST['phone_locked']) ? 1 : 0;
    $games = isset($_POST['games_locked']) ? 1 : 0;
    $other = isset($_POST['other_locked']) ? 1 : 0;
    $bank_phone = max(0, (int)($_POST['bank_phone_min'] ?? 0));
    $bank_games = max(0, (int)($_POST['bank_games_min'] ?? 0));
    $bank_other = max(0, (int)($_POST['bank_other_min'] ?? 0));
    $pdo->prepare("INSERT INTO privileges(kid_id,phone_locked,games_locked,other_locked,bank_phone_min,bank_games_min,bank_other_min)
                   VALUES(?,?,?,?,?,?,?)
                   ON CONFLICT(kid_id) DO UPDATE SET
                     phone_locked=excluded.phone_locked,
                     games_locked=excluded.games_locked,
                     other_locked=excluded.other_locked,
                     bank_phone_min=excluded.bank_phone_min,
                     bank_games_min=excluded.bank_games_min,
                     bank_other_min=excluded.bank_other_min")
        ->execute([$kidId,$phone,$games,$other,$bank_phone,$bank_games,$bank_other]);
  }
  header("Location: /admin/grounding.php?saved=1");
  exit;
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Grounding / Privileges</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php require __DIR__ . '/_nav.php'; ?>
<div class="container">
  <h1>Grounding / Privileges</h1>
  <p class="muted">This restores the core “grounding board” behavior: per-kid locks + minute banks. Next phase will bring back infractions ladders, undo, devices, and richer events.</p>

  <?php if (isset($_GET['saved'])): ?>
    <div class="notice ok">Saved.</div>
  <?php endif; ?>

  <?php foreach ($kids as $k): $pv = pv_row($pdo, (int)$k['id']); ?>
    <form class="card" method="post" action="/admin/grounding.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="kid_id" value="<?= (int)$k['id'] ?>">
      <h2><?= htmlspecialchars($k['name']) ?></h2>

      <div class="grid">
        <label class="check"><input type="checkbox" name="phone_locked" <?= ((int)$pv['phone_locked']? 'checked':'') ?>> Phone locked</label>
        <label class="check"><input type="checkbox" name="games_locked" <?= ((int)$pv['games_locked']? 'checked':'') ?>> Games locked</label>
        <label class="check"><input type="checkbox" name="other_locked" <?= ((int)$pv['other_locked']? 'checked':'') ?>> Other locked</label>
      </div>

      <div class="grid">
        <label>Bank phone (min)<input class="input" type="number" min="0" name="bank_phone_min" value="<?= (int)$pv['bank_phone_min'] ?>"></label>
        <label>Bank games (min)<input class="input" type="number" min="0" name="bank_games_min" value="<?= (int)$pv['bank_games_min'] ?>"></label>
        <label>Bank other (min)<input class="input" type="number" min="0" name="bank_other_min" value="<?= (int)$pv['bank_other_min'] ?>"></label>
      </div>

      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  <?php endforeach; ?>
</div>
</body>
</html>
