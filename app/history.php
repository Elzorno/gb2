<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();
$kid = gb2_kid_require();
$pdo = gb2_pdo();

$kidId = (int)$kid['kid_id'];

/** Decode JSON into array safely. */
function gb2_hist_json_arr(?string $json): array {
  if ($json === null) return [];
  $json = trim($json);
  if ($json === '') return [];
  $v = json_decode($json, true);
  return is_array($v) ? $v : [];
}

function gb2_hist_blocks_label(array $blocks): string {
  $on = [];
  if ((int)($blocks['phone'] ?? 0) === 1) $on[] = 'Phone';
  if ((int)($blocks['games'] ?? 0) === 1) $on[] = 'Games';
  if ((int)($blocks['other'] ?? 0) === 1) $on[] = 'Other';
  return $on ? implode(', ', $on) : 'None';
}

function gb2_hist_until_label(array $until): string {
  // until is like {"phone":"...Z","games":"...Z"} or empty
  $parts = [];
  foreach (['phone'=>'Phone', 'games'=>'Games', 'other'=>'Other'] as $k => $label) {
    $iso = (string)($until[$k] ?? '');
    if ($iso !== '') $parts[] = "{$label}→{$iso}";
  }
  return $parts ? implode(' • ', $parts) : '—';
}

function gb2_hist_review_status(array $e): array {
  $reviewedAt = (string)($e['reviewed_at'] ?? '');
  $action = (string)($e['review_action'] ?? '');

  if ($reviewedAt === '') {
    return ['cls' => 'pending', 'text' => 'Pending review'];
  }

  if ($action === 'unlock') return ['cls' => 'approved', 'text' => 'Reviewed: unlock'];
  if ($action === 'shorten') return ['cls' => 'approved', 'text' => 'Reviewed: shorten'];
  if ($action === 'review_only') return ['cls' => 'open', 'text' => 'Reviewed: review-only'];

  return ['cls' => 'open', 'text' => 'Reviewed'];
}

// --- Submissions history (existing behavior) ---
$st = $pdo->prepare("SELECT * FROM submissions WHERE kid_id=? ORDER BY submitted_at DESC LIMIT 30");
$st->execute([$kidId]);
$submissions = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

// --- Infraction timeline (new) ---
$st2 = $pdo->prepare("
  SELECT
    e.id,
    e.ts,
    e.infraction_def_id,
    d.code AS def_code,
    d.label AS def_label,
    e.strike_before,
    e.strike_after,
    e.days_applied,
    e.mode,
    e.blocks_json,
    e.computed_until_json,
    e.review_on,
    e.reviewed_at,
    e.review_action,
    e.review_resolved_until_json
  FROM infraction_events e
  JOIN infraction_defs d ON d.id = e.infraction_def_id
  WHERE e.kid_id=?
  ORDER BY e.ts DESC
  LIMIT 50
");
$st2->execute([$kidId]);
$infractions = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

gb2_page_start('History', $kid);
?>
<div class="card">
  <div class="h1">History</div>
  <div class="h2">Recent submissions + infractions</div>

  <div class="note" style="margin-top:10px">
    This page shows your recent activity. Parent/guardian notes are not shown here.
  </div>
</div>

<div class="card">
  <div class="h1">Recent submissions</div>
  <div class="h2">Last 30</div>

  <?php if (!$submissions): ?>
    <div class="note">No submissions yet.</div>
  <?php else: ?>
    <?php foreach ($submissions as $r): ?>
      <?php
        $kind = (string)($r['kind'] ?? '');
        $kindLabel = ($kind === 'bonus') ? 'Bonus' : 'Base';
        $submittedAt = (string)($r['submitted_at'] ?? '');
        $status = (string)($r['status'] ?? 'open');
        $statusCls = preg_replace('/[^a-z_]/', '', strtolower($status));
        if ($statusCls === '') $statusCls = 'open';
      ?>
      <div class="card" style="margin:12px 0 0">
        <div class="row">
          <div class="kv">
            <div class="h1"><?= gb2_h($kindLabel) ?></div>
            <div class="small"><?= gb2_h($submittedAt) ?></div>
          </div>
          <div class="status <?= gb2_h($statusCls) ?>"><?= gb2_h($status) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="h1">Infractions</div>
  <div class="h2">Last 50</div>

  <?php if (!$infractions): ?>
    <div class="note">No infractions recorded yet.</div>
  <?php else: ?>
    <?php foreach ($infractions as $e): ?>
      <?php
        $ts = (string)($e['ts'] ?? '');
        $label = (string)($e['def_label'] ?? 'Infraction');
        $strikeAfter = (int)($e['strike_after'] ?? 0);
        $daysApplied = (int)($e['days_applied'] ?? 0);
        $mode = (string)($e['mode'] ?? 'set');
        if ($mode !== 'set' && $mode !== 'add') $mode = 'set';

        $blocks = gb2_hist_json_arr((string)($e['blocks_json'] ?? ''));
        $computedUntil = gb2_hist_json_arr((string)($e['computed_until_json'] ?? ''));
        $reviewOn = (string)($e['review_on'] ?? '');
        $resolvedUntil = gb2_hist_json_arr((string)($e['review_resolved_until_json'] ?? ''));

        $review = gb2_hist_review_status($e);
      ?>

      <div class="card" style="margin:12px 0 0">
        <div class="row" style="align-items:flex-start">
          <div class="kv">
            <div class="h1" style="font-size:18px; margin-top:0"><?= gb2_h($label) ?></div>
            <div class="small"><?= gb2_h($ts) ?></div>

            <div class="note" style="margin-top:8px">
              <b>Strike:</b> <?= (int)$strikeAfter ?>
              &nbsp;&nbsp; <b>Days:</b> <?= (int)$daysApplied ?>
              &nbsp;&nbsp; <b>Mode:</b> <?= gb2_h($mode) ?>
            </div>

            <div class="note" style="margin-top:6px">
              <b>Blocks:</b> <?= gb2_h(gb2_hist_blocks_label($blocks)) ?>
            </div>

            <div class="note" style="margin-top:6px">
              <b>Applied until:</b> <?= gb2_h(gb2_hist_until_label($computedUntil)) ?>
            </div>

            <?php if ($reviewOn !== ''): ?>
              <div class="note" style="margin-top:6px">
                <b>Review on:</b> <?= gb2_h($reviewOn) ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($resolvedUntil)): ?>
              <div class="note" style="margin-top:6px">
                <b>After review:</b> <?= gb2_h(gb2_hist_until_label($resolvedUntil)) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="status <?= gb2_h($review['cls']) ?>"><?= gb2_h($review['text']) ?></div>
        </div>
      </div>

    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php gb2_nav('history'); gb2_page_end(); ?>
