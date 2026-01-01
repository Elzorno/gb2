<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/rotation.php';

gb2_db_init();

// Allow kid OR admin to view; otherwise show login nav.
$kid = gb2_kid_current();
$admin = gb2_admin_current();

$pdo = gb2_pdo();
$defs = $pdo->query("SELECT * FROM infraction_defs WHERE active=1 ORDER BY sort_order ASC, label ASC")
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

function gb2_rules_decode_arr(string $json, array $fallback): array {
  $json = trim($json);
  if ($json === '') return $fallback;
  $x = json_decode($json, true);
  return is_array($x) ? $x : $fallback;
}

function gb2_rules_blocks_text(array $blocks): string {
  $on = [];
  foreach (['phone'=>'Phone', 'games'=>'Games', 'other'=>'Other'] as $k => $label) {
    if ((int)($blocks[$k] ?? 0) === 1) $on[] = $label;
  }
  if (!$on) return 'None';
  return implode(', ', $on);
}

function gb2_rules_mode_text(string $mode): string {
  $mode = strtolower(trim($mode));
  if ($mode !== 'set' && $mode !== 'add') $mode = 'set';
  return $mode;
}

function gb2_rules_ladder_text(array $ladder, int $defaultDays): string {
  $vals = [];
  foreach ($ladder as $x) {
    $n = (int)$x;
    if ($n > 0) $vals[] = $n;
  }

  if ($vals) {
    // Example: "1st→2, 2nd→4, 3rd→7 (caps at 7)"
    $parts = [];
    for ($i = 0; $i < count($vals); $i++) {
      $strike = $i + 1;
      $suffix = 'th';
      if ($strike % 10 === 1 && $strike % 100 !== 11) $suffix = 'st';
      elseif ($strike % 10 === 2 && $strike % 100 !== 12) $suffix = 'nd';
      elseif ($strike % 10 === 3 && $strike % 100 !== 13) $suffix = 'rd';
      $parts[] = "{$strike}{$suffix}→{$vals[$i]}";
    }
    $cap = $vals[count($vals) - 1];
    return implode(', ', $parts) . " (caps at {$cap})";
  }

  return (string)max(0, $defaultDays);
}

function gb2_rules_review_text(array $def, int $daysAppliedExample): string {
  $explicit = (int)($def['review_days'] ?? 0);
  if ($explicit > 0) {
    return "{$explicit} day(s) after resolution (explicit)";
  }
  if ($daysAppliedExample <= 0) {
    return "No review period";
  }
  // Matches gb2_inf_compute_review_days(): ceil(days/2), min 1
  $half = (int)ceil($daysAppliedExample / 2);
  $half = max(1, $half);
  return "{$half} day(s) after resolution (default = about half, rounded up)";
}


// --- Chore chart (read-only) ---
$pdo = gb2_pdo();
$today = new DateTimeImmutable('today');
$todayYmd = $today->format('Y-m-d');
$weekStart = gb2_week_start_monday($today);

$dayCols = [];
$dayKeys = [];
$labels = ['Mon','Tue','Wed','Thu','Fri'];
for ($i=0; $i<5; $i++) {
  $d = $weekStart->modify('+' . $i . ' days');
  $ymd = $d->format('Y-m-d');
  $dayKeys[] = $ymd;
  $dayCols[] = $labels[$i] . " " . $d->format('m/d');
}

$weekendSat = $weekStart->modify('+5 days')->format('Y-m-d');
$weekendSun = $weekStart->modify('+6 days')->format('Y-m-d');

$kids = $pdo->query("SELECT id,name FROM kids ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$map = []; // [kid_id][day] = ['title'=>..., 'status'=>...]
if ($dayKeys) {
  $place = implode(',', array_fill(0, count($dayKeys), '?'));
  $st = $pdo->prepare(
    "SELECT a.day, a.kid_id, a.status, s.title AS slot_title
     FROM assignments a
     JOIN chore_slots s ON s.id=a.slot_id
     WHERE a.day IN ($place)
     ORDER BY a.day ASC"
  );
  $st->execute($dayKeys);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $kidId = (int)($r['kid_id'] ?? 0);
    $day = (string)($r['day'] ?? '');
    if ($kidId && $day !== '') {
      $map[$kidId][$day] = [
        'title' => (string)($r['slot_title'] ?? ''),
        'status' => (string)($r['status'] ?? 'open'),
      ];
    }
  }
}

// weekend (optional future use)
$wkMap = []; // [kid_id] => array of lines
$stWk = $pdo->prepare(
  "SELECT a.day, a.kid_id, a.status, s.title AS slot_title
   FROM assignments a
   JOIN chore_slots s ON s.id=a.slot_id
   WHERE a.day IN (?,?)
   ORDER BY a.day ASC"
);
$stWk->execute([$weekendSat, $weekendSun]);
foreach ($stWk->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $kidId = (int)($r['kid_id'] ?? 0);
  $day = (string)($r['day'] ?? '');
  $label = ($day === $weekendSat) ? 'Sat' : (($day === $weekendSun) ? 'Sun' : 'Wknd');
  $title = (string)($r['slot_title'] ?? '');
  $status = (string)($r['status'] ?? 'open');
  if ($kidId && $title !== '') {
    $wkMap[$kidId][] = $label . ': ' . $title . ' (' . $status . ')';
  }
}

$todayCol = (int)$today->format('N'); // 1=Mon..7=Sun
$highlightWeekend = ($todayCol >= 6);

// Page title depends on role (kid or admin)
$title = 'Rules';
gb2_page_start($title, $kid ?: null);
?>

<div class="card">
  <div class="h1">House Rules</div>
  <div class="h2">How the board works</div>

  <div class="note" style="margin-top:10px">
    This page explains how <b>infractions</b> (rule breaks) translate into <b>locks</b> (loss of privileges),
    and how <b>review</b> works after a parent/guardian resolves an infraction.
  </div>

  <div style="margin-top:14px">
    <div class="small">Key ideas</div>
    <ul style="margin:8px 0 0 18px;">
      <li><b>Strike</b> = each time a specific rule is broken, the strike count for that rule goes up.</li>
      <li><b>Days applied</b> = how long the lock lasts for that strike (based on the ladder below).</li>
      <li><b>Blocks</b> = which categories are locked: Phone, Games, and/or Other.</li>
      <li><b>Review</b> = after an infraction is resolved (unlock/shorten), there may be a short “check-in” period.</li>
    </ul>
  </div>

  <div style="margin-top:14px">
    <div class="small">What parents/guardians do</div>
    <ul style="margin:8px 0 0 18px;">
      <li>They apply an infraction (adds a strike and computes the lock length).</li>
      <li>Later, they review it and choose an action (unlock, shorten, or review-only).</li>
      <li>Unlock/shorten updates the lock timers deterministically and records the review.</li>
    </ul>
  </div>
</div>


<div class="card">
  <div class="h1">Chore Chart</div>
  <div class="h2">This week at a glance</div>

  <div class="note" style="margin-top:10px">
    This is a read-only view of the weekly rotation. The highlighted column is <b>today</b>.
  </div>

  <?php if (!$kids): ?>
    <div class="note" style="margin-top:10px">No kids configured yet.</div>
  <?php else: ?>
    <div class="table-wrap" style="margin-top:12px">
      <table class="grid-table chore-grid">
        <thead>
          <tr>
            <th>Kid</th>
            <?php foreach ($dayCols as $idx => $label): ?>
              <?php $isToday = (!$highlightWeekend && $todayCol === ($idx + 1)); ?>
              <th class="<?= $isToday ? 'today-col' : '' ?>"><?= gb2_h($label) ?></th>
            <?php endforeach; ?>
            <th class="<?= $highlightWeekend ? 'today-col' : '' ?>">Weekend</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($kids as $krow): ?>
            <?php
              $kidId = (int)($krow['id'] ?? 0);
              $kidName = (string)($krow['name'] ?? '');
            ?>
            <tr>
              <td class="kid"><?= gb2_h($kidName) ?></td>
              <?php foreach ($dayKeys as $d): ?>
                <?php
                  $cell = $map[$kidId][$d] ?? null;
                  $title = $cell ? (string)($cell['title'] ?? '') : '';
                  $status = $cell ? (string)($cell['status'] ?? 'open') : '';
                ?>
                <td>
                  <?php if ($title !== ''): ?>
                    <div class="cell-title"><?= gb2_h($title) ?></div>
                    <div class="cell-status <?= gb2_h($status) ?>"><?= gb2_h($status) ?></div>
                  <?php else: ?>
                    <div class="cell-empty">—</div>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td>
                <?php if (!empty($wkMap[$kidId])): ?>
                  <div class="cell-title"><?= gb2_h(implode(' · ', $wkMap[$kidId])) ?></div>
                <?php else: ?>
                  <div class="cell-empty">—</div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="h1">Infractions</div>
  <div class="h2">Defaults, strike ladders, and what gets locked</div>

  <?php if (!$defs): ?>
    <div class="note" style="margin-top:10px">No active infraction definitions yet.</div>
  <?php else: ?>
    <div style="margin-top:10px">
      <?php foreach ($defs as $d): ?>
        <?php
          $label = (string)($d['label'] ?? 'Infraction');
          $defaultDays = (int)($d['days'] ?? 0);

          $blocks = gb2_rules_decode_arr((string)($d['blocks_json'] ?? ''), ['phone'=>0,'games'=>0,'other'=>0]);
          $ladder = gb2_rules_decode_arr((string)($d['ladder_json'] ?? ''), []);
          $repairs = gb2_rules_decode_arr((string)($d['repairs_json'] ?? ''), []);

          $mode = gb2_rules_mode_text((string)($d['mode'] ?? 'set'));

          // Pick a reasonable example for describing default review behavior:
          // If ladder exists, use the first rung. Otherwise use default days.
          $exampleDays = $defaultDays;
          if (is_array($ladder) && count($ladder) > 0) {
            $exampleDays = (int)($ladder[0] ?? $defaultDays);
          }
          $exampleDays = max(0, $exampleDays);
        ?>

        <div class="card" style="margin:12px 0; background:rgba(255,255,255,.03)">
          <div class="h1" style="font-size:18px; margin-top:0"><?= gb2_h($label) ?></div>

          <div class="small" style="margin-top:6px">
            <b>Blocks:</b> <?= gb2_h(gb2_rules_blocks_text($blocks)) ?>
          </div>

          <div class="small" style="margin-top:6px">
            <b>Strike ladder (days):</b>
            <?= gb2_h(gb2_rules_ladder_text($ladder, $defaultDays)) ?>
          </div>

          <div class="small" style="margin-top:6px">
            <b>Mode:</b> <?= gb2_h($mode) ?>
            <?php if ($mode === 'set'): ?>
              — sets lock-until from “now” (replaces existing lock time for blocked categories).
            <?php else: ?>
              — adds time onto the existing lock-until (stacks time for blocked categories).
            <?php endif; ?>
          </div>

          <div class="small" style="margin-top:6px">
            <b>Review:</b> <?= gb2_h(gb2_rules_review_text($d, $exampleDays)) ?>
          </div>

          <?php if (is_array($repairs) && count($repairs)): ?>
            <div class="small" style="margin-top:10px">
              <b>Repair options</b> (optional ways to make things right):
              <ul style="margin:6px 0 0 18px;">
                <?php foreach ($repairs as $r): ?>
                  <?php
                    if (!is_array($r)) continue;
                    $rLabel = (string)($r['label'] ?? '');
                    $rNote  = (string)($r['note'] ?? '');
                    if ($rLabel === '' && $rNote === '') continue;
                  ?>
                  <li>
                    <?= gb2_h($rLabel !== '' ? $rLabel : 'Repair') ?>
                    <?php if ($rNote !== ''): ?>
                      <span class="note">— <?= gb2_h($rNote) ?></span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php
            $details = trim((string)($d['details'] ?? ''));
            if ($details !== ''):
          ?>
            <div class="note" style="margin-top:10px">
              <?= nl2br(gb2_h($details)) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="h1">Review</div>
  <div class="h2">What “review-only / shorten / unlock” means</div>

  <ul style="margin:8px 0 0 18px;">
    <li><b>Review-only</b> — records the review, but does not change lock timers.</li>
    <li><b>Shorten</b> — reduces the lock time (deterministically) and records what the new lock-until becomes.</li>
    <li><b>Unlock</b> — removes the lock for the blocked categories and clears their lock-until timestamps.</li>
  </ul>

  <div class="note" style="margin-top:10px">
    Reviews are recorded with an ISO timestamp, and the “resolved until” values are stored per category so the result is auditable.
  </div>

  <?php if ($admin): ?>
    <div class="row" style="gap:10px; flex-wrap:wrap; margin-top:12px">
      <a class="btn" href="/admin/infraction_review.php">Open Review Queue</a>
      <a class="btn" href="/admin/infraction_defs.php">Edit Definitions</a>
    </div>
  <?php endif; ?>
</div>

<?php
// nav key depends on role
if ($admin) gb2_nav('rules');
elseif ($kid) gb2_nav('rules');
else gb2_nav('login');
gb2_page_end();
