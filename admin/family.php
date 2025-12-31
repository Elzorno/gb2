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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();

  $action = (string)($_POST['action'] ?? '');
  $kidId  = (int)($_POST['kid_id'] ?? 0);

  if ($kidId <= 0) {
    $err = 'Invalid kid.';
  } elseif ($action === 'reset_pin') {
    // IMPORTANT: pin_hash is NOT NULL — set to empty string, never NULL
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

    if (isset($r['kid_id']) && (int)$r['kid_id'] !== $kidId) continue;
    if (!isset($r['kid_id']) && isset($r['kid']) && (int)$r['kid'] !== $kidId) continue;

    if (isset($r['claimed_by_kid_id']) && (int)$r['claimed_by_kid_id'] === 0) { $count++; continue; }
    if (isset($r['claimed_by_kid']) && (int)$r['claimed_by_kid'] === 0) { $count++; continue; }
    if (isset($r['status']) && (string)$r['status'] === 'available') { $count++; continue; }
  }
  return $count;
}

/**
 * Normalize privileges into:
 *   ['locks'=>['phone'=>0/1,'games'=>0/1,'other'=>0/1],
 *    'until'=>['phone'=>iso|null,'games'=>iso|null,'other'=>iso|null],
 *    'banks'=>['phone'=>min,'games'=>min,'other'=>min]]
 *
 * Works whether gb2_priv_get_for_kid() returns:
 *  - normalized keys (locks/banks), or
 *  - raw DB columns (phone_locked, bank_phone_min, etc.)
 */
function gb2_norm_priv(array $priv): array {
  if (isset($priv['locks']) && is_array($priv['locks'])) {
    $locks = $priv['locks'];
    $banks = (isset($priv['banks']) && is_array($priv['banks'])) ? $priv['banks'] : [];
    $until = (isset($priv['until']) && is_array($priv['until'])) ? $priv['until'] : [];

    return [
      'locks' => [
        'phone' => (int)($locks['phone'] ?? $locks['phone_locked'] ?? 0),
        'games' => (int)($locks['games'] ?? $locks['games_locked'] ?? 0),
        'other' => (int)($locks['other'] ?? $locks['other_locked'] ?? 0),
      ],
      'until' => [
        'phone' => (string)($until['phone'] ?? $priv['phone_locked_until'] ?? ''),
        'games' => (string)($until['games'] ?? $priv['games_locked_until'] ?? ''),
        'other' => (string)($until['other'] ?? $priv['other_locked_until'] ?? ''),
      ],
      'banks' => [
        'phone' => (int)($banks['phone'] ?? $banks['bank_phone_min'] ?? 0),
        'games' => (int)($banks['games'] ?? $banks['bank_games_min'] ?? 0),
        'other' => (int)($banks['other'] ?? $banks['bank_other_min'] ?? 0),
      ],
    ];
  }

  return [
    'locks' => [
      'phone' => (int)($priv['phone_locked'] ?? 0),
      'games' => (int)($priv['games_locked'] ?? 0),
      'other' => (int)($priv['other_locked'] ?? 0),
    ],
    'until' => [
      'phone' => (string)($priv['phone_locked_until'] ?? ''),
      'games' => (string)($priv['games_locked_until'] ?? ''),
      'other' => (string)($priv['other_locked_until'] ?? ''),
    ],
    'banks' => [
      'phone' => (int)($priv['bank_phone_min'] ?? 0),
      'games' => (int)($priv['bank_games_min'] ?? 0),
      'other' => (int)($priv['bank_other_min'] ?? 0),
    ],
  ];
}

function gb2_until_ts(string $iso): int {
  if ($iso === '') return 0;
  $t = strtotime($iso);
  return $t ? (int)$t : 0;
}

gb2_page_start('Family', null);
?>

<div class="card">
  <div class="h1">Family Dashboard</div>
  <div class="h2">Quick view for today</div>

  <div class="note" style="margin-top:10px">
    Today: <?= gb2_h($today->format('l, M j')) ?>
  </div>

  <?php gb2_flash_render(); ?>

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
  $privRaw     = $kidId ? gb2_priv_get_for_kid($kidId) : [];
  $priv        = is_array($privRaw) ? gb2_norm_priv($privRaw) : ['locks'=>['phone'=>0,'games'=>0,'other'=>0], 'until'=>['phone'=>'','games'=>'','other'=>''], 'banks'=>['phone'=>0,'games'=>0,'other'=>0]];

  $bonusAvail  = $kidId ? bonus_available_count_for_kid($kidId, $weekBonusRows) : 0;

  $titles = [];
  foreach ($assignments as $a) {
    if (is_array($a)) $titles[] = (string)($a['slot_title'] ?? $a['title'] ?? $a['name'] ?? 'Chore');
    else $titles[] = (string)$a;
  }

  $locks = $priv['locks'];
  $until = $priv['until'];
  $banks = $priv['banks'];

  $anyLock = ((int)$locks['phone'] === 1) || ((int)$locks['games'] === 1) || ((int)$locks['other'] === 1);

  $phoneUntilIso = (string)($until['phone'] ?? '');
  $gamesUntilIso = (string)($until['games'] ?? '');
  $otherUntilIso = (string)($until['other'] ?? '');

  $phoneUntilTs = gb2_until_ts($phoneUntilIso);
  $gamesUntilTs = gb2_until_ts($gamesUntilIso);
  $otherUntilTs = gb2_until_ts($otherUntilIso);
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

        <?php if ((int)$locks['phone'] === 1): ?>
          <div class="badge badge-lock">
            Phone: Locked
            <?php if ($phoneUntilIso !== '' && $phoneUntilTs > 0): ?>
              <span class="lock-until">until <?= gb2_h($phoneUntilIso) ?></span>
              <span class="lock-countdown" data-gb2-until="<?= (int)$phoneUntilTs ?>"></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ((int)$locks['games'] === 1): ?>
          <div class="badge badge-lock">
            Games: Locked
            <?php if ($gamesUntilIso !== '' && $gamesUntilTs > 0): ?>
              <span class="lock-until">until <?= gb2_h($gamesUntilIso) ?></span>
              <span class="lock-countdown" data-gb2-until="<?= (int)$gamesUntilTs ?>"></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ((int)$locks['other'] === 1): ?>
          <div class="badge badge-lock">
            Other: Locked
            <?php if ($otherUntilIso !== '' && $otherUntilTs > 0): ?>
              <span class="lock-until">until <?= gb2_h($otherUntilIso) ?></span>
              <span class="lock-countdown" data-gb2-until="<?= (int)$otherUntilTs ?>"></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="badge">No active locks</div>
      <?php endif; ?>
    </div>

    <div style="height:12px"></div>

    <div class="small">Banked minutes</div>
    <div class="row" style="gap:10px; flex-wrap:wrap; margin-top:8px">
      <div class="badge">Phone: <?= (int)$banks['phone'] ?> min</div>
      <div class="badge">Games: <?= (int)$banks['games'] ?> min</div>
      <div class="badge">Other: <?= (int)$banks['other'] ?> min</div>
    </div>

    <div style="height:12px"></div>

    <div class="row" style="gap:10px; flex-wrap:wrap">
      <a class="btn" href="/admin/grounding.php">Edit privileges</a>
      <a class="btn" href="/admin/review.php">Review proofs</a>
      <a class="btn" href="/app/today.php">Open kid view</a>
    </div>
  </div>

<?php endforeach; ?>

<script>
(function(){
  function pad2(n){ n = Math.floor(n); return (n < 10 ? "0" : "") + n; }
  function fmt(secs){
    secs = Math.max(0, Math.floor(secs));
    var d = Math.floor(secs / 86400); secs -= d * 86400;
    var h = Math.floor(secs / 3600);  secs -= h * 3600;
    var m = Math.floor(secs / 60);    secs -= m * 60;
    var s = secs;

    var parts = [];
    if (d > 0) parts.push(d + "d");
    if (h > 0 || d > 0) parts.push(h + "h");
    parts.push(pad2(m) + "m");
    parts.push(pad2(s) + "s");
    return "(" + parts.join(" ") + " remaining)";
  }

  function tick(){
    var now = Math.floor(Date.now() / 1000);
    document.querySelectorAll("[data-gb2-until]").forEach(function(el){
      var until = parseInt(el.getAttribute("data-gb2-until") || "0", 10);
      if (!until || until <= 0) return;

      var left = until - now;
      if (left <= 0) {
        el.textContent = "(expired — refresh)";
        el.classList.add("expired");
      } else {
        el.textContent = fmt(left);
        el.classList.remove("expired");
      }
    });
  }

  tick();
  setInterval(tick, 1000);
})();
</script>

<?php gb2_nav('family'); gb2_page_end(); ?>
