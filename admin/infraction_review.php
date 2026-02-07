<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/infractions.php';
require_once __DIR__ . '/../lib/privileges.php';

gb2_db_init();
gb2_admin_require();

$pdo = gb2_pdo();

function gb2_json_arr(?string $s, array $fallback): array {
  if (!$s) return $fallback;
  $v = json_decode($s, true);
  return is_array($v) ? $v : $fallback;
}

$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();

  $eventId = (int)($_POST['event_id'] ?? 0);
  $action  = trim((string)($_POST['action'] ?? 'review_only')); // review_only | unlock | shorten
  $note    = trim((string)($_POST['review_note'] ?? ''));
  $keepMin = max(0, (int)($_POST['keep_minutes'] ?? 0));
  $resetStrike = isset($_POST['reset_strike']) ? 1 : 0;

  if ($eventId <= 0) {
    $err = 'Missing event id.';
  } else {
    try {
      $st = $pdo->prepare("
        SELECT e.*, d.code AS def_code, d.label AS def_label, k.name AS kid_name
        FROM infraction_events e
        JOIN infraction_defs d ON d.id=e.infraction_def_id
        JOIN kids k ON k.id=e.kid_id
        WHERE e.id=?
        LIMIT 1
      ");
      $st->execute([$eventId]);
      $ev = $st->fetch(PDO::FETCH_ASSOC);
      if (!$ev) throw new RuntimeException('Event not found.');

      $blocks = gb2_json_arr((string)($ev['blocks_json'] ?? ''), ['phone'=>0,'games'=>0,'other'=>0]);
      $kidId = (int)$ev['kid_id'];

      $resolvedUntil = [];

      if ($action === 'unlock') {
        foreach (['phone','games','other'] as $w) {
          if ((int)($blocks[$w] ?? 0) === 1) {
            gb2_priv_set_lock_until($kidId, $w, null); // unlock
            $resolvedUntil[$w] = null;
          }
        }
      } elseif ($action === 'shorten') {
        $targetTs = time() + ($keepMin * 60);
        $targetIso = gb2_priv_iso_from_ts($targetTs);

        $pv = gb2_priv_get_for_kid($kidId);
        foreach (['phone','games','other'] as $w) {
          if ((int)($blocks[$w] ?? 0) !== 1) continue;

          $colUntil = $w . '_locked_until';
          $curIso = (string)($pv[$colUntil] ?? '');
          $curTs = $curIso ? strtotime($curIso) : 0;

          // If current lock ends sooner than the target, keep it (don't extend)
          if ($curTs > 0 && $curTs < $targetTs) {
            $resolvedUntil[$w] = $curIso;
            continue;
          }

          gb2_priv_set_lock_until($kidId, $w, $targetIso);
          $resolvedUntil[$w] = $targetIso;
        }
      } else {
        $action = 'review_only';
      }

      if ($resetStrike === 1) {
        gb2_inf_reset_strike_for_event((int)$ev['kid_id'], (int)$ev['infraction_def_id']);
      }

      $actorType = 'admin';
      $actorId = 0;
      gb2_inf_mark_event_reviewed($eventId, $actorType, $actorId, $note, $action, $resolvedUntil);

      header('Location: /admin/infraction_review.php?ok=Reviewed');
      exit;
    } catch (Throwable $e) {
      $err = 'Review failed: ' . $e->getMessage();
    }
  }
}

// We store review_on as ISO timestamps in this system (matches gb2_priv_iso_from_ts()).
$nowIso = gb2_priv_iso_from_ts(time());
$in7Iso = gb2_priv_iso_from_ts(time() + 7*86400);

$dueNow = [];
$upcoming = [];

try {
  $st = $pdo->prepare("
    SELECT e.*, d.code AS def_code, d.label AS def_label, k.name AS kid_name
    FROM infraction_events e
    JOIN infraction_defs d ON d.id=e.infraction_def_id
    JOIN kids k ON k.id=e.kid_id
    WHERE e.review_on IS NOT NULL
      AND e.review_on <= ?
      AND (e.reviewed_at IS NULL OR e.reviewed_at='')
    ORDER BY e.review_on ASC, e.ts DESC
    LIMIT 200
  ");
  $st->execute([$nowIso]);
  $dueNow = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $st = $pdo->prepare("
    SELECT e.*, d.code AS def_code, d.label AS def_label, k.name AS kid_name
    FROM infraction_events e
    JOIN infraction_defs d ON d.id=e.infraction_def_id
    JOIN kids k ON k.id=e.kid_id
    WHERE e.review_on IS NOT NULL
      AND e.review_on > ?
      AND e.review_on <= ?
      AND (e.reviewed_at IS NULL OR e.reviewed_at='')
    ORDER BY e.review_on ASC, e.ts DESC
    LIMIT 200
  ");
  $st->execute([$nowIso, $in7Iso]);
  $upcoming = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $err = $err ?: 'Review schema not ready.';
}

$tok = gb2_csrf_token();
gb2_page_start('Infraction Review', null);
?>

<div class="card">
  <div class="h1">Infraction Review</div>
  <div class="h2">Due items and quick resolution</div>

  <?php if ($err): ?>
    <div class="status rejected" style="margin-top:12px"><?= gb2_h($err) ?></div>
  <?php else: ?>
    <?php gb2_flash_render(); ?>
  <?php endif; ?>

  <div class="note" style="margin-top:10px">
    Review is due at <strong><?= gb2_h($nowIso) ?></strong>. Upcoming shows the next 7 days.
  </div>
</div>

<?php
function render_events(string $title, array $rows, string $tok): void {
  ?>
  <div class="card">
    <div class="h1"><?= gb2_h($title) ?></div>
    <div class="h2"><?= count($rows) ?> item(s)</div>

    <?php if (!$rows): ?>
      <div class="note" style="margin-top:10px">None.</div>
    <?php else: ?>
      <div style="margin-top:10px">
        <?php foreach ($rows as $r): ?>
          <?php
            $blocks = gb2_json_arr((string)($r['blocks_json'] ?? ''), ['phone'=>0,'games'=>0,'other'=>0]);
            $until  = gb2_json_arr((string)($r['computed_until_json'] ?? ''), []);
            $b = [];
            foreach (['phone','games','other'] as $w) if ((int)($blocks[$w] ?? 0) === 1) $b[] = $w;
          ?>
          <div class="note" style="margin-bottom:12px">
            <div style="display:flex; gap:10px; justify-content:space-between; align-items:center; flex-wrap:wrap;">
              <div>
                <strong><?= gb2_h((string)$r['kid_name']) ?></strong>
                — <?= gb2_h((string)$r['def_label']) ?> (<?= gb2_h((string)$r['def_code']) ?>)
              </div>
              <div class="small">review_on: <?= gb2_h((string)($r['review_on'] ?? '')) ?></div>
            </div>

            <div class="small" style="margin-top:6px">
              ts: <?= gb2_h((string)$r['ts']) ?> |
              strike <?= (int)$r['strike_before'] ?> → <?= (int)$r['strike_after'] ?> |
              days <?= (int)$r['days_applied'] ?> |
              mode <?= gb2_h((string)$r['mode']) ?> |
              blocks: <?= gb2_h($b ? implode(', ', $b) : 'none') ?>
            </div>

            <?php if (!empty($until)): ?>
              <div class="small" style="margin-top:6px">
                until:
                <?php foreach (['phone','games','other'] as $w): ?>
                  <?php if (!empty($until[$w])): ?>
                    <?= gb2_h($w) ?>=<?= gb2_h((string)$until[$w]) ?>&nbsp;
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($r['note'])): ?>
              <div class="small" style="margin-top:6px">note: <?= gb2_h((string)$r['note']) ?></div>
            <?php endif; ?>

            <form method="post" action="/admin/infraction_review.php" style="margin-top:10px">
              <input type="hidden" name="_csrf" value="<?= gb2_h($tok) ?>">
              <input type="hidden" name="event_id" value="<?= (int)$r['id'] ?>">

              <div class="grid auto">
                <label>
                  Action
                  <select class="input" name="action">
                    <option value="review_only">Review only</option>
                    <option value="unlock">Unlock</option>
                    <option value="shorten">Shorten</option>
                  </select>
                </label>

                <label>
                  Keep minutes (Shorten)
                  <input class="input" type="number" min="0" max="10080" name="keep_minutes" value="240">
                </label>

                <label class="check" style="align-self:end;">
                  <input type="checkbox" name="reset_strike">
                  Reset strike
                </label>
              </div>

              <div style="height:10px"></div>
              <label>
                Review note (optional)
                <input class="input" type="text" name="review_note" value="">
              </label>

              <div style="height:12px"></div>
              <button class="btn primary" type="submit">Mark reviewed</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php
}
render_events('Due now', $dueNow, $tok);
render_events('Upcoming (next 7 days)', $upcoming, $tok);

gb2_nav('inf_review');
gb2_page_end();
