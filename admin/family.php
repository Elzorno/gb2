<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/common.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/rotation.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/privileges.php';
require_once __DIR__ . '/../lib/csrf.php';

gb2_db_init();
gb2_admin_require();

$pdo = gb2_pdo();

$today = new DateTimeImmutable('now');
$todayYmd = $today->format('Y-m-d');
$weekStartYmd = gb2_bonus_week_start($todayYmd);

$flash = '';
$err   = '';

// --- POST actions (admin-only, CSRF protected) ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();

  $action = (string)($_POST['action'] ?? '');
  $kidId  = (int)($_POST['kid_id'] ?? 0);

  if ($kidId <= 0) {
    $err = 'Invalid kid.';
  } elseif ($action === 'reset_pin') {
    // IMPORTANT: kids.pin_hash is NOT NULL in your schema, default ''.
    // Reset by setting to empty string, so next login triggers "create PIN" flow.
    $st = $pdo->prepare("UPDATE kids SET pin_hash='' WHERE id=?");
    $st->execute([$kidId]);

    $flash = 'PIN reset. On next login, they will be prompted to create a new PIN.';
  } else {
    $err = 'Unknown action.';
  }
}

$kids = gb2_kids_all();
$weekBonusRows = gb2_bonus_list_week($weekStartYmd);

function bonus_available_count_for_kid(int $kidId, array $rows): int {
  $count = 0;
  foreach ($rows as $r) {
    if (!is_array($r)) continue;

    // If the row contains kid_id-like fields, respect them; otherwise treat as global.
    if (isset($r['kid_id']) && (int)$r['kid_id'] !== $kidId) continue;
    if (!isset($r['kid_id']) && isset($r['kid']) && (int)$r['kid'] !== $kidId) continue;

    // Determine availability by best-known fields
    if (isset($r['claimed_by_kid_id']) && (int)$r['claimed_by_kid_id'] === 0) { $count++; continue; }
    if (isset($r['claimed_by_kid']) && (int)$r['claimed_by_kid'] === 0) { $count++; continue; }
    if (isset($r['status']) && (string)$r['status'] === 'available') { $count++; continue; }
  }
  return $count;
}

gb2_page_start('Family', null);
?>

<div class="card">
  <div class="h1">Family Dashboard</div>
  <div class="h2">Quick view for today</div>

  <div class="note" style="margin-top:10px">
    Today: <?= gb2_h($today->format('l, M j')) ?>
  </div>

  <?php if ($flash): ?>
    <div class="status approved" style="margin-top:12px"><?= gb2_h($flash) ?></div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="status rejected" style="margin-top:12px"><?= gb2_h($err) ?></div>
  <?php endif; ?>
</div>

<?php foreach ($kids as $kidRow): ?>
<?php
  $kidId   = (int)($kidRow['id'] ?? 0);
  $kidName = (string)($kidRow['name'] ?? ('Kid #' . $kidId));

  $assignments = $kidId ? gb2_assignments_for_kid_day($kidId, $todayYmd) : [];
  $priv        = $kidId ? gb2_priv_get_for_kid($kidId) : ['locks'=>[], 'banks'=>[]];

  $bonusAvail  = $kidId ? bonus_available_count_for_kid($kidId, $weekBonusRows) : 0;

  $titles = [];
  foreach ($assignments as $a) {
    if (is_array($a)) $titles[] = (string)($a['slot_title'] ?? $a['title'] ?? $a['name'] ?? 'Chore');
    else $titles[] = (string)$a;
  }

  $locks = is_array($priv['locks'] ?? null) ? $priv['locks'] : [];
  $banks = is_array($priv['banks'] ?? null) ? $priv['banks'] : [];

  $anyLock = false;
  foreach ($locks as $k => $on) { if ((int)$on === 1) { $anyLock = true; break; } }
?>

  <div class="card">
    <div class="row" style="justify-content:space-between; align-items:center">
      <div>
        <div class="h1"><?= gb2_h($kidName) ?></div>
        <div class="h2">Today + privileges</div>
      </div>

      <form method="post" style="margin:0"
            onsubmit="return confirm('Reset PIN for <?= gb2_h($kidName) ?>? They will set a new PIN next time they log in.');">
        <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
        <input type="hidden" name="action" value="reset_pin">
        <input type="hidden" name="kid_id" value="<?= (int)$kidId ?>">
        <button class="btn" type="submit">Reset PIN</button>
      </form>
    </div>

    <div style="height:10px"></div>

    <div class="small">Today’s chores</div>
    <?php if (!empty($titles)): ?>
      <ul style="margin:8px 0 0 1.2rem">
        <?php foreach ($titles as $t): ?>
          <li><?= gb2_h($t) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="note" style="margin-top:8px">No chores assigned for today.</div>
    <?php endif; ?>

    <div style="height:14px"></div>

    <div class="small">Bonus chores</div>
    <?php if ($bonusAvail > 0): ?>
      <div class="note" style="margin-top:8px"><?= (int)$bonusAvail ?> available this week</div>
    <?php else: ?>
      <div class="note" style="margin-top:8px">None available right now.</div>
    <?php endif; ?>

    <div style="height:14px"></div>

    <div class="small">Locks</div>
    <div class="row" style="gap:10px; flex-wrap:wrap; margin-top:8px">
      <?php if ($anyLock): ?>
        <?php foreach ($locks as $k => $on): ?>
          <?php if ((int)$on === 1): ?>
            <div class="badge"><?= gb2_h((string)$k) ?>: Locked</div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="badge">No active locks</div>
      <?php endif; ?>
    </div>

    <div style="height:12px"></div>

    <div class="small">Banked minutes</div>
    <div class="row" style="gap:10px; flex-wrap:wrap; margin-top:8px">
      <?php if (!empty($banks)): ?>
        <?php foreach ($banks as $bank => $min): ?>
          <div class="badge"><?= gb2_h((string)$bank) ?>: <?= (int)$min ?> min</div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="badge">No banked time</div>
      <?php endif; ?>
    </div>

    <div style="height:12px"></div>

    <div class="row" style="gap:10px; flex-wrap:wrap">
      <a class="btn" href="/admin/grounding.php">Edit privileges</a>
      <a class="btn" href="/admin/review.php">Review proofs</a>
      <a class="btn" href="/app/today.php">Open kid view</a>
    </div>
  </div>

<?php endforeach; ?>

<?php gb2_nav('family'); gb2_page_end(); ?>
