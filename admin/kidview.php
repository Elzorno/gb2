<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();
gb2_admin_require();

$pdo = gb2_pdo();

$kids = $pdo->query("SELECT id, name FROM kids ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selKidId = isset($_GET['kid_id']) ? (int)$_GET['kid_id'] : 0;
if ($selKidId <= 0 && $kids) $selKidId = (int)$kids[0]['id'];

$selKidName = '';
foreach ($kids as $k) {
  if ((int)$k['id'] === $selKidId) { $selKidName = (string)$k['name']; break; }
}

function gb2_kidview_link(string $path, int $kidId): string {
  return $path . '?kid_id=' . (int)$kidId;
}

gb2_page_start('Kid View', null);
?>
<div class="card">
  <div class="h1">Kid View</div>
  <div class="h2">View any kid page without logging kids out</div>

  <div class="note" style="margin-top:10px">
    Select a kid, then open their pages. You are viewing as Parent/Guardian (read-only unless that page has kid actions).
  </div>

  <?php if (!$kids): ?>
    <div class="status rejected" style="margin-top:12px">No kids exist in the database.</div>
  <?php else: ?>
    <form method="get" class="row" style="gap:10px; flex-wrap:wrap; justify-content:flex-start; margin-top:12px">
      <select class="input select" name="kid_id" style="max-width:320px">
        <?php foreach ($kids as $k): ?>
          <?php $kidId = (int)$k['id']; ?>
          <option value="<?= $kidId ?>" <?= ($kidId === $selKidId) ? 'selected' : '' ?>>
            <?= gb2_h((string)$k['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn primary" type="submit">View</button>
    </form>

    <div style="height:14px"></div>

    <div class="small">Selected</div>
    <div class="badge" style="margin-top:8px">
      <?= gb2_h($selKidName !== '' ? $selKidName : ('Kid #' . $selKidId)) ?> (kid_id=<?= (int)$selKidId ?>)
    </div>

    <div style="height:14px"></div>

    <div class="small">Open pages</div>
    <div class="row" style="gap:10px; flex-wrap:wrap; justify-content:flex-start; margin-top:10px">
      <a class="btn" href="<?= gb2_h(gb2_kidview_link('/app/dashboard.php', $selKidId)) ?>">Dashboard</a>
      <a class="btn" href="<?= gb2_h(gb2_kidview_link('/app/today.php', $selKidId)) ?>">Today</a>
      <a class="btn" href="<?= gb2_h(gb2_kidview_link('/app/history.php', $selKidId)) ?>">History</a>
      <a class="btn" href="/app/rules.php">Rules</a>
    </div>

    <div class="note" style="margin-top:12px">
      Tip: you can also paste <b>?kid_id=</b> onto any kid URL while you’re logged in as admin.
    </div>
  <?php endif; ?>
</div>

<?php gb2_nav('kidview'); gb2_page_end(); ?>
