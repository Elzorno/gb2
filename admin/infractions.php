<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/infractions.php';

gb2_db_init();
gb2_admin_require();

$kids = gb2_kids_all();
$defs = gb2_inf_defs_all(true);

$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();

  $kidId = (int)($_POST['kid_id'] ?? 0);
  $defId = (int)($_POST['infraction_def_id'] ?? 0);
  $note  = trim((string)($_POST['note'] ?? ''));

  if ($kidId <= 0 || $defId <= 0) {
    $err = 'Missing kid or infraction.';
  } else {
    try {
      // Actor id: deterministic placeholder until admin IDs exist.
      $actorId = 0;
      gb2_inf_apply($kidId, $defId, $note, 'admin', $actorId);
      header('Location: /admin/infractions.php?applied=1');
      exit;
    } catch (Throwable $e) {
      $err = 'Apply failed: ' . $e->getMessage();
    }
  }
}

$pdo = gb2_pdo();

// Recent events per kid (last 10)
function gb2_inf_events_for_kid(PDO $pdo, int $kidId): array {
  $st = $pdo->prepare("
    SELECT e.*, d.label AS def_label, d.code AS def_code
    FROM infraction_events e
    JOIN infraction_defs d ON d.id=e.infraction_def_id
    WHERE e.kid_id=?
    ORDER BY e.ts DESC
    LIMIT 10
  ");
  $st->execute([$kidId]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

gb2_page_start('Infractions', null);
$tok = gb2_csrf_token();
?>

<div class="card">
  <div class="h1">Infractions</div>
  <div class="h2">Apply rule violations with strike ladders and timed lock consequences</div>

  <?php if ($err): ?>
    <div class="status rejected" style="margin-top:12px"><?= gb2_h($err) ?></div>
  <?php elseif (isset($_GET['applied'])): ?>
    <div class="status approved" style="margin-top:12px">Infraction applied.</div>
  <?php endif; ?>

  <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
    <a class="btn" href="/admin/infraction_defs.php">Manage Definitions</a>
    <a class="btn" href="/app/rules.php">View Rules Page</a>
  </div>

  <?php if (!$defs): ?>
    <div class="note" style="margin-top:12px">
      No active infraction definitions exist yet. Use <strong>Manage Definitions</strong> to create some.
    </div>
  <?php endif; ?>

  <div class="note" style="margin-top:12px">
    Preview shows what will happen <em>before</em> you hit Apply: strike number, days, set/add mode, and lock-until.
  </div>
</div>

<?php foreach ($kids as $k): ?>
  <?php
    $kidId = (int)$k['id'];
    $events = gb2_inf_events_for_kid($pdo, $kidId);
    $selectId = 'def_' . $kidId;
    $previewId = 'preview_' . $kidId;
  ?>
  <div class="card">
    <div class="row" style="align-items:center; justify-content:space-between;">
      <div class="kv">
        <div class="h1"><?= gb2_h((string)$k['name']) ?></div>
        <div class="h2">Apply an infraction</div>
      </div>
    </div>

    <div style="height:12px"></div>

    <form method="post" action="/admin/infractions.php">
      <input type="hidden" name="_csrf" value="<?= gb2_h($tok) ?>">
      <input type="hidden" name="kid_id" value="<?= $kidId ?>">

      <div class="grid">
        <label>
          Infraction
          <select class="input inf-select" id="<?= gb2_h($selectId) ?>" data-kid="<?= $kidId ?>" <?= $defs ? '' : 'disabled' ?> name="infraction_def_id">
            <option value="0">Select…</option>
            <?php foreach ($defs as $d): ?>
              <option value="<?= (int)$d['id'] ?>">
                <?= gb2_h((string)$d['label']) ?> (<?= gb2_h((string)$d['code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          Note (optional)
          <input class="input" type="text" name="note" value="">
        </label>
      </div>

      <div style="height:10px"></div>

      <div id="<?= gb2_h($previewId) ?>" class="note" style="display:none"></div>

      <div style="height:12px"></div>
      <button class="btn primary" type="submit" <?= $defs ? '' : 'disabled' ?>>Apply</button>
    </form>

    <div style="height:16px"></div>

    <div class="h2">Recent</div>
    <?php if (!$events): ?>
      <div class="note" style="margin-top:8px">No infractions recorded.</div>
    <?php else: ?>
      <div style="margin-top:8px">
        <?php foreach ($events as $e): ?>
          <?php
            $until = json_decode((string)($e['computed_until_json'] ?? '{}'), true);
            if (!is_array($until)) $until = [];
          ?>
          <div class="note" style="margin-bottom:10px">
            <div><strong><?= gb2_h((string)$e['def_label']) ?></strong> (<?= gb2_h((string)$e['def_code']) ?>)</div>
            <div>
              <?= gb2_h((string)$e['ts']) ?> —
              strike <?= (int)$e['strike_before'] ?> → <?= (int)$e['strike_after'] ?>,
              days <?= (int)$e['days_applied'] ?>,
              mode <?= gb2_h((string)$e['mode']) ?>
            </div>
            <?php if (!empty($until)): ?>
              <div>
                until:
                <?php foreach (['phone','games','other'] as $w): ?>
                  <?php if (!empty($until[$w])): ?>
                    <?= gb2_h($w) ?>=<?= gb2_h((string)$until[$w]) ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($e['review_on'])): ?>
              <div>review: <?= gb2_h((string)$e['review_on']) ?></div>
            <?php endif; ?>
            <?php if (!empty($e['note'])): ?>
              <div>note: <?= gb2_h((string)$e['note']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<script>
(function(){
  function esc(s){
    return String(s)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#39;");
  }

  function renderPreview(box, data){
    if (!data || !data.ok) {
      box.style.display = 'block';
      box.innerHTML = '<div><strong>Preview:</strong> ' + esc((data && data.error) ? data.error : 'Unavailable') + '</div>';
      return;
    }

    var blocks = data.blocks || {};
    var until  = data.computed_until || {};
    var parts = [];

    parts.push('<div><strong>Preview:</strong> ' + esc(data.label) + ' (' + esc(data.code) + ')</div>');
    parts.push('<div>strike: ' + esc(data.strike_before) + ' → ' + esc(data.strike_after) +
               ' | days: ' + esc(data.days_applied) + ' | mode: ' + esc(data.mode) + '</div>');

    var btxt = [];
    if (blocks.phone === 1) btxt.push('phone');
    if (blocks.games === 1) btxt.push('games');
    if (blocks.other === 1) btxt.push('other');
    parts.push('<div>blocks: ' + (btxt.length ? esc(btxt.join(', ')) : 'none') + '</div>');

    var utxt = [];
    ['phone','games','other'].forEach(function(w){
      if (until[w]) utxt.push(w + '=' + until[w]);
    });
    if (utxt.length) {
      parts.push('<div>until: ' + esc(utxt.join(' | ')) + '</div>');
    } else {
      parts.push('<div>until: (no time applied)</div>');
    }

    if (data.review_on) {
      parts.push('<div>review: ' + esc(data.review_on) + '</div>');
    }

    box.style.display = 'block';
    box.innerHTML = parts.join('');
  }

  async function fetchPreview(kidId, defId){
    var url = '/api/infraction_preview.php?kid_id=' + encodeURIComponent(kidId) + '&def_id=' + encodeURIComponent(defId);
    var r = await fetch(url, { credentials: 'same-origin' });
    var j = await r.json();
    return j;
  }

  document.querySelectorAll('.inf-select').forEach(function(sel){
    var kidId = sel.getAttribute('data-kid');
    var box = document.getElementById('preview_' + kidId);
    if (!box) return;

    sel.addEventListener('change', async function(){
      var defId = parseInt(sel.value || '0', 10);
      if (!defId) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
      }
      box.style.display = 'block';
      box.textContent = 'Loading preview...';

      try {
        var data = await fetchPreview(kidId, defId);
        renderPreview(box, data);
      } catch (e) {
        renderPreview(box, { ok:false, error: String(e) });
      }
    });
  });
})();
</script>

<?php gb2_nav('infractions'); gb2_page_end(); ?>
