<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
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
$err = '';

// --- POST actions (admin-only, CSRF protected) ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();

  $action = (string)($_POST['action'] ?? '');
  $kidId  = (int)($_POST['kid_id'] ?? 0);

  try {
    if ($kidId <= 0) {
      $err = 'Invalid kid.';
    } elseif ($action === 'reset_pin') {
      // kids.pin_hash is TEXT NOT NULL DEFAULT '' -> reset to empty string, never NULL
      $st = $pdo->prepare("UPDATE kids SET pin_hash='' WHERE id=?");
      $st->execute([$kidId]);
      $flash = 'Kid PIN reset. They will create a new PIN on next login.';
    } else {
      $err = 'Unknown action.';
    }
  } catch (Throwable $e) {
    // Don’t 500. Show a safe message.
    $err = 'Action failed. Please try again.';
  }
}

$kids = gb2_kids_all();
$weekBonusRows = gb2_bonus_list_week($weekStartYmd);

function bonus_available_count_for_kid(int $kidId, array $rows): int {
  $count = 0;
  foreach ($rows as $r) {
    if (!is_array($r)) continue;

    if (isset($r['kid_id']) && (int)$r['kid_id'] !== $kidId) continue;
    if (!isset($r['kid_id']) && isset($r['kid']) && (int)$r['kid'] !== $kidId) continue;

    if (isset($r['claimed_by_kid_id']) && (int)$r['claimed_by_kid_id'] === 0) { $count++; continue; }
    if (isset($r['claimed_by_kid']) && (int)$r['claimed_by_kid'] === 0) { $count++; continue; }
    if (isset($r['status']) && (string)$r['status'] === 'available') { $count++; continue; }
  }
  return $count;
}

gb2_page_start('Family Dashboard');
?>
<div style="margin:.25rem 0 1rem 0; color:#666; font-size:.95rem;">
  Today: <?= gb2_h($today->format('l, M j')) ?>
</div>

<?php if ($flash): ?>
  <div class="status approved" style="margin:0 0 1rem 0;"><?= gb2_h($flash) ?></div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="status pending" style="margin:0 0 1rem 0;"><?= gb2_h($err) ?></div>
<?php endif; ?>

<style>
.gb2-card{background:#fff;border-radius:14px;padding:1rem;margin:1rem 0;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.gb2-card h2{margin:0 0 .5rem 0}
.gb2-subtle{color:#666;font-size:.9rem}
.gb2-badge{display:inline-block;padding:.25rem .6rem;border-radius:999px;background:#eee;font-size:.8rem;margin:.15rem .25rem .15rem 0}
.gb2-badge.locked{background:#ddd}
.gb2-badge.bank{background:#e7f0ff}
.gb2-section{margin-top:.8rem}
.gb2-list{margin:.25rem 0 0 1.2rem}
.btn-sm{display:inline-block;border:1px solid #ccc;background:#f7f7f8;padding:.35rem .6rem;border-radius:10px;text-decoration:none;color:#111;font-size:.85rem}
.btn-sm:hover{background:#eee}
.row{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
</style>

<?php foreach ($kids as $kid): ?>
<?php
  $kidId = (int)($kid['id'] ?? 0);
  $kidName = (string)($kid['name'] ?? ('Kid #' . $kidId));

  $assignments = $kidId ? gb2_assignments_for_kid_day($kidId, $todayYmd) : [];
  $priv = $kidId ? gb2_priv_get_for_kid($kidId) : ['locks'=>[], 'banks'=>[]];

  $bonusAvail = $kidId ? bonus_available_count_for_kid($kidId, $weekBonusRows) : 0;

  $titles = [];
  foreach ($assignments as $a) {
    if (is_array($a)) $titles[] = (string)($a['slot_title'] ?? $a['title'] ?? $a['name'] ?? json_encode($a));
    else $titles[] = (string)$a;
  }
?>
  <div class="gb2-card">
    <div class="row" style="justify-content:space-between">
      <h2><?= gb2_h($kidName) ?></h2>

      <form method="post" style="margin:0" onsubmit="return confirm('Reset PIN for <?= gb2_h($kidName) ?>? They will need to create a new PIN next login.');">
        <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
        <input type="hidden" name="action" value="reset_pin">
        <input type="hidden" name="kid_id" value="<?= (int)$kidId ?>">
        <button class="btn-sm" type="submit">Reset PIN</button>
      </form>
    </div>

    <div class="gb2-section">
      <strong>Today’s chores</strong>
      <?php if (!empty($titles)): ?>
        <ul class="gb2-list">
          <?php foreach ($titles as $t): ?>
            <li><?= gb2_h($t) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="gb2-subtle">No assigned chores today</div>
      <?php endif; ?>
    </div>

    <div class="gb2-section">
      <strong>Bonus</strong><br>
      <?php if ($bonusAvail > 0): ?>
        <span class="gb2-badge"><?= (int)$bonusAvail ?> available this week</span>
      <?php else: ?>
        <span class="gb2-subtle">None available</span>
      <?php endif; ?>
    </div>

    <div class="gb2-section">
      <strong>Grounding locks</strong><br>
      <?php
        $locks = is_array($priv['locks'] ?? null) ? $priv['locks'] : [];
        $any = false;
        foreach ($locks as $k => $on) { if ((int)$on === 1) { $any = true; break; } }
      ?>
      <?php if ($any): ?>
        <?php foreach ($locks as $k => $on): ?>
          <?php if ((int)$on === 1): ?>
            <span class="gb2-badge locked"><?= gb2_h((string)$k) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <span class="gb2-subtle">No active locks</span>
      <?php endif; ?>
    </div>

    <div class="gb2-section">
      <strong>Bank minutes</strong><br>
      <?php $banks = is_array($priv['banks'] ?? null) ? $priv['banks'] : []; ?>
      <?php if (!empty($banks)): ?>
        <?php foreach ($banks as $bank => $min): ?>
          <span class="gb2-badge bank"><?= gb2_h((string)$bank) ?>: <?= (int)$min ?> min</span>
        <?php endforeach; ?>
      <?php else: ?>
        <span class="gb2-subtle">No banked time</span>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php gb2_nav('family'); gb2_page_end(); ?>
