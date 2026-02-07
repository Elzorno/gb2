<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/privileges.php';

gb2_db_init();
gb2_admin_require();

$kids = gb2_kids_all();

$flash = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    gb2_csrf_verify();

    $kidId = (int)($_POST['kid_id'] ?? 0);
    if ($kidId <= 0) throw new RuntimeException('Missing kid_id');

    // Action routing (default = save)
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'extend24') {
      // Extend any currently active locks by 24 hours.
      gb2_priv_extend_all_locked($kidId, 24 * 60);
      $flash = 'Extended active locks by 24 hours.';
    } else {
      $phone_locked = isset($_POST['phone_locked']) ? 1 : 0;
      $games_locked = isset($_POST['games_locked']) ? 1 : 0;
      $other_locked = isset($_POST['other_locked']) ? 1 : 0;

      gb2_priv_set_locks($kidId, $phone_locked, $games_locked, $other_locked);
      $flash = 'Saved.';
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

gb2_page_start('Privileges');
?>

<div class="card">
  <div class="h1">Privileges</div>
  <div class="note" style="margin-top:8px">
    Locks here are on/off. Bonus chores award cash-only earnings; banked device minutes are disabled in GB2.
  </div>

  <?php if ($flash): ?>
    <div class="status approved" style="margin-top:12px"><?= gb2_h($flash) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="status rejected" style="margin-top:12px"><?= gb2_h($err) ?></div>
  <?php endif; ?>
</div>

<?php foreach ($kids as $k): ?>
  <?php
    $kidId = (int)($k['id'] ?? 0);
    if ($kidId <= 0) continue;
    $pv = gb2_priv_get_for_kid($kidId);
    $name = (string)($k['name'] ?? ('Kid #' . $kidId));

    $hasActive =
      ((int)($pv['phone_locked'] ?? 0) === 1) ||
      ((int)($pv['games_locked'] ?? 0) === 1) ||
      ((int)($pv['other_locked'] ?? 0) === 1);
  ?>
  <div class="card" style="margin-top:12px">
    <div class="h2"><?= gb2_h($name) ?></div>

    <form method="post" style="margin-top:10px">
      <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
      <input type="hidden" name="kid_id" value="<?= (int)$kidId ?>">

      <div class="row" style="gap:16px; flex-wrap:wrap">
        <label class="check">
          <input type="checkbox" name="phone_locked" <?= ((int)($pv['phone_locked'] ?? 0) ? 'checked' : '') ?>>
          Phone locked
        </label>
        <label class="check">
          <input type="checkbox" name="games_locked" <?= ((int)($pv['games_locked'] ?? 0) ? 'checked' : '') ?>>
          Games locked
        </label>
        <label class="check">
          <input type="checkbox" name="other_locked" <?= ((int)($pv['other_locked'] ?? 0) ? 'checked' : '') ?>>
          Other locked
        </label>
      </div>

      <div style="height:12px"></div>

      <div class="row" style="gap:10px; flex-wrap:wrap">
        <button class="btn primary" type="submit" name="action" value="save">Save</button>
        <button class="btn" type="submit" name="action" value="extend24" <?= $hasActive ? '' : 'disabled' ?>>
          +24h active locks
        </button>
      </div>
      <?php if (!$hasActive): ?>
        <div class="note" style="margin-top:8px">No active locks to extend.</div>
      <?php endif; ?>
    </form>
  </div>
<?php endforeach; ?>

<?php gb2_nav('grounding'); gb2_page_end(); ?>
