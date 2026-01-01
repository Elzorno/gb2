<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';

gb2_db_init();
gb2_admin_require();

$pdo = gb2_pdo();

$flash = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  try {
    gb2_csrf_verify();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
      $title = trim((string)($_POST['title'] ?? ''));
      if ($title === '') throw new RuntimeException('Title is required.');

      $rewardCents = (int)($_POST['reward_cents'] ?? 0);
      $maxPerWeek  = max(1, (int)($_POST['max_per_week'] ?? 1));
      $sortOrder   = (int)($_POST['sort_order'] ?? 0);
      $active      = isset($_POST['active']) ? 1 : 0;

      $st = $pdo->prepare("INSERT INTO bonus_defs(title, active, reward_cents, reward_phone_min, reward_games_min, max_per_week, sort_order)
                           VALUES(?,?,?,?,?,?,?)");
      // reward_phone_min / reward_games_min are legacy and remain zero.
      $st->execute([$title, $active, $rewardCents, 0, 0, $maxPerWeek, $sortOrder]);

      $flash = 'Created bonus.';
    } elseif ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new RuntimeException('Missing id.');

      $title = trim((string)($_POST['title'] ?? ''));
      if ($title === '') throw new RuntimeException('Title is required.');

      $rewardCents = (int)($_POST['reward_cents'] ?? 0);
      $maxPerWeek  = max(1, (int)($_POST['max_per_week'] ?? 1));
      $sortOrder   = (int)($_POST['sort_order'] ?? 0);
      $active      = isset($_POST['active']) ? 1 : 0;

      $st = $pdo->prepare("UPDATE bonus_defs
                           SET title=?, active=?, reward_cents=?, reward_phone_min=0, reward_games_min=0, max_per_week=?, sort_order=?
                           WHERE id=?");
      $st->execute([$title, $active, $rewardCents, $maxPerWeek, $sortOrder, $id]);

      $flash = 'Saved changes.';
    } else {
      throw new RuntimeException('Unknown action.');
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$defs = $pdo->query("SELECT * FROM bonus_defs ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

gb2_page_start('Bonus Definitions');
?>

<div class="card">
  <div class="h1">Bonus Chores (Projects)</div>
  <div class="note" style="margin-top:8px">
    Use these to incentivize special projects. Kids can claim and submit proof. When approved, GB2 records cash earnings.
    (Device minutes are disabled.)
  </div>

  <?php if ($flash): ?>
    <div class="status approved" style="margin-top:12px"><?= gb2_h($flash) ?></div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="status rejected" style="margin-top:12px"><?= gb2_h($err) ?></div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:12px">
  <div class="h2">Add a bonus</div>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <div class="grid2">
      <label class="kv">
        <div class="k">Title</div>
        <input class="input" name="title" placeholder="e.g., Clean garage shelves" required>
      </label>

      <label class="kv">
        <div class="k">Cash reward (cents)</div>
        <input class="input" type="number" name="reward_cents" min="0" value="0">
      </label>

      <label class="kv">
        <div class="k">Max per week</div>
        <input class="input" type="number" name="max_per_week" min="1" value="1">
      </label>

      <label class="kv">
        <div class="k">Sort order</div>
        <input class="input" type="number" name="sort_order" value="0">
      </label>
    </div>

    <label class="check" style="margin-top:10px">
      <input type="checkbox" name="active" checked> Active
    </label>

    <div style="height:12px"></div>
    <button class="btn primary" type="submit">Create</button>
  </form>
</div>

<div class="card" style="margin-top:12px">
  <div class="h2">Existing bonuses</div>

  <?php if (!$defs): ?>
    <div class="note" style="margin-top:10px">No bonus definitions yet.</div>
  <?php else: ?>
    <div style="margin-top:10px">
      <?php foreach ($defs as $d): ?>
        <?php
          $id = (int)($d['id'] ?? 0);
          $title = (string)($d['title'] ?? '');
          $active = (int)($d['active'] ?? 0);
          $reward = (int)($d['reward_cents'] ?? 0);
          $max = (int)($d['max_per_week'] ?? 1);
          $sort = (int)($d['sort_order'] ?? 0);
        ?>
        <div class="card" style="margin:12px 0 0">
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= gb2_h(gb2_csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="grid2">
              <label class="kv">
                <div class="k">Title</div>
                <input class="input" name="title" value="<?= gb2_h($title) ?>" required>
              </label>

              <label class="kv">
                <div class="k">Cash reward (cents)</div>
                <input class="input" type="number" name="reward_cents" min="0" value="<?= (int)$reward ?>">
              </label>

              <label class="kv">
                <div class="k">Max per week</div>
                <input class="input" type="number" name="max_per_week" min="1" value="<?= (int)$max ?>">
              </label>

              <label class="kv">
                <div class="k">Sort order</div>
                <input class="input" type="number" name="sort_order" value="<?= (int)$sort ?>">
              </label>
            </div>

            <label class="check" style="margin-top:10px">
              <input type="checkbox" name="active" <?= ($active ? 'checked' : '') ?>> Active
            </label>

            <div style="height:12px"></div>
            <button class="btn primary" type="submit">Save</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php gb2_nav('bonus_defs'); gb2_page_end(); ?>
