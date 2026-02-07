<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';

gb2_db_init();
gb2_admin_require();

$pdo = gb2_pdo();

function gb2_post_int(string $k, int $min = 0, int $max = 999999): int {
  $v = (int)($_POST[$k] ?? 0);
  if ($v < $min) $v = $min;
  if ($v > $max) $v = $max;
  return $v;
}

function gb2_post_str(string $k): string {
  return trim((string)($_POST[$k] ?? ''));
}

function gb2_norm_mode(string $m): string {
  $m = strtolower(trim($m));
  return ($m === 'add') ? 'add' : 'set';
}

function gb2_norm_blocks(array $in): array {
  return [
    'phone' => isset($in['phone']) ? 1 : 0,
    'games' => isset($in['games']) ? 1 : 0,
    'other' => isset($in['other']) ? 1 : 0,
  ];
}

function gb2_parse_int_list(string $s): array {
  // Accept: "1,2,3" or "1 2 3" or "1|2|3"
  $s = trim($s);
  if ($s === '') return [];
  $parts = preg_split('/[,\s|]+/', $s) ?: [];
  $out = [];
  foreach ($parts as $p) {
    $n = (int)trim($p);
    if ($n > 0) $out[] = $n;
  }
  return $out;
}

function gb2_parse_lines(string $s): array {
  $s = str_replace("\r\n", "\n", $s);
  $lines = array_map('trim', explode("\n", $s));
  $out = [];
  foreach ($lines as $ln) {
    if ($ln !== '') $out[] = $ln;
  }
  return $out;
}

$err = '';
$ok = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();

  $action = gb2_post_str('action');

  if ($action === 'create' || $action === 'update') {
    $id = gb2_post_int('id', 0);
    $code = gb2_post_str('code');
    $label = gb2_post_str('label');
    $active = isset($_POST['active']) ? 1 : 0;
    $mode = gb2_norm_mode(gb2_post_str('mode'));
    $days = gb2_post_int('days', 0, 365);
    $reviewDays = gb2_post_int('review_days', 0, 365);
    $sortOrder = gb2_post_int('sort_order', 0, 99999);

    $blocks = gb2_norm_blocks((array)($_POST['blocks'] ?? []));
    $ladder = gb2_parse_int_list(gb2_post_str('ladder_csv'));
    $repairs = gb2_parse_lines(gb2_post_str('repairs_lines'));

    if ($code === '' || $label === '') {
      $err = 'Code and label are required.';
    } else {
      try {
        if ($action === 'create') {
          $pdo->prepare("
            INSERT INTO infraction_defs
              (code,label,active,mode,days,ladder_json,blocks_json,repairs_json,review_days,sort_order,created_at)
            VALUES
              (?,?,?,?,?,?,?,?,?,?,?)
          ")->execute([
            $code, $label, $active, $mode, $days,
            json_encode($ladder),
            json_encode($blocks),
            json_encode($repairs),
            $reviewDays,
            $sortOrder,
            gb2_now_iso(),
          ]);
          $ok = 'Definition created.';
        } else {
          if ($id <= 0) throw new RuntimeException('Missing id for update.');
          $pdo->prepare("
            UPDATE infraction_defs
            SET code=?, label=?, active=?, mode=?, days=?,
                ladder_json=?, blocks_json=?, repairs_json=?,
                review_days=?, sort_order=?
            WHERE id=?
          ")->execute([
            $code, $label, $active, $mode, $days,
            json_encode($ladder),
            json_encode($blocks),
            json_encode($repairs),
            $reviewDays,
            $sortOrder,
            $id,
          ]);
          $ok = 'Definition updated.';
        }
      } catch (Throwable $e) {
        $err = 'Save failed: ' . $e->getMessage();
      }
    }
  } elseif ($action === 'delete') {
    $id = gb2_post_int('id', 0);
    if ($id > 0) {
      try {
        $pdo->prepare("DELETE FROM infraction_defs WHERE id=?")->execute([$id]);
        $ok = 'Definition deleted.';
      } catch (Throwable $e) {
        $err = 'Delete failed: ' . $e->getMessage();
      }
    } else {
      $err = 'Missing id for delete.';
    }
  } else {
    $err = 'Unknown action.';
  }

  $qs = $ok ? ('ok=' . urlencode($ok)) : ($err ? ('err=' . urlencode($err)) : '');
  header('Location: /admin/infraction_defs.php' . ($qs ? ('?' . $qs) : ''));
  exit;
}

// load defs
$defs = $pdo->query("SELECT * FROM infraction_defs ORDER BY active DESC, sort_order ASC, label ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
  $st = $pdo->prepare("SELECT * FROM infraction_defs WHERE id=?");
  $st->execute([$editId]);
  $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function gb2_blocks_checked(array $b, string $k): string {
  return ((int)($b[$k] ?? 0) === 1) ? 'checked' : '';
}

function gb2_decode_arr(?string $json, array $fallback): array {
  if (!$json) return $fallback;
  $v = json_decode($json, true);
  return is_array($v) ? $v : $fallback;
}

$tok = gb2_csrf_token();
gb2_page_start('Infraction Definitions', null);
?>

<div class="card">
  <div class="h1">Infraction Definitions</div>
  <div class="h2">Create and edit rule violations and their consequences</div>

  <?php gb2_flash_render(); ?>

  <div class="note" style="margin-top:10px">
    Tips:
    <ul style="margin:6px 0 0 18px;">
      <li><strong>mode=set</strong>: sets lock-until from now</li>
      <li><strong>mode=add</strong>: adds time onto existing lock-until</li>
      <li><strong>ladder</strong>: comma-separated days per strike (e.g., <code>1,3,7</code>)</li>
      <li><strong>repairs</strong>: one per line (display-only for now)</li>
    </ul>
  </div>

  <div style="margin-top:12px">
    <a class="btn" href="/admin/infractions.php">Back to Apply</a>
    <a class="btn" href="/app/rules.php">View Rules Page</a>
  </div>
</div>

<?php
$e = $edit ?: [
  'id' => 0,
  'code' => '',
  'label' => '',
  'active' => 1,
  'mode' => 'set',
  'days' => 0,
  'ladder_json' => '[]',
  'blocks_json' => '{"phone":1,"games":1,"other":0}',
  'repairs_json' => '[]',
  'review_days' => 0,
  'sort_order' => 0,
];
$blocks = gb2_decode_arr((string)($e['blocks_json'] ?? ''), ['phone'=>0,'games'=>0,'other'=>0]);
$ladder = gb2_decode_arr((string)($e['ladder_json'] ?? ''), []);
$repairs = gb2_decode_arr((string)($e['repairs_json'] ?? ''), []);
$ladderCsv = $ladder ? implode(',', array_map('intval', $ladder)) : '';
$repairsLines = $repairs ? implode("\n", array_map('strval', $repairs)) : '';
?>

<form class="card" method="post" action="/admin/infraction_defs.php">
  <input type="hidden" name="_csrf" value="<?= gb2_h($tok) ?>">
  <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
  <input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">

  <div class="h1"><?= $edit ? 'Edit Definition' : 'Create Definition' ?></div>
  <div style="height:10px"></div>

  <div class="grid auto">
    <label>
      Code (unique)
      <input class="input" type="text" name="code" value="<?= gb2_h((string)($e['code'] ?? '')) ?>">
    </label>
    <label>
      Label
      <input class="input" type="text" name="label" value="<?= gb2_h((string)($e['label'] ?? '')) ?>">
    </label>
  </div>

  <div style="height:10px"></div>

  <div class="grid auto">
    <label class="check">
      <input type="checkbox" name="active" <?= ((int)($e['active'] ?? 0) === 1) ? 'checked' : '' ?>>
      Active
    </label>

    <label>
      Mode
      <select class="input" name="mode">
        <option value="set" <?= ((string)($e['mode'] ?? '') === 'set') ? 'selected' : '' ?>>set</option>
        <option value="add" <?= ((string)($e['mode'] ?? '') === 'add') ? 'selected' : '' ?>>add</option>
      </select>
    </label>

    <label>
      Sort order
      <input class="input" type="number" min="0" name="sort_order" value="<?= (int)($e['sort_order'] ?? 0) ?>">
    </label>
  </div>

  <div style="height:10px"></div>

  <div class="grid auto">
    <label>
      Days (default)
      <input class="input" type="number" min="0" max="365" name="days" value="<?= (int)($e['days'] ?? 0) ?>">
    </label>
    <label>
      Ladder days (CSV, optional)
      <input class="input" type="text" name="ladder_csv" value="<?= gb2_h($ladderCsv) ?>">
    </label>
    <label>
      Review days (0 = auto half)
      <input class="input" type="number" min="0" max="365" name="review_days" value="<?= (int)($e['review_days'] ?? 0) ?>">
    </label>
  </div>

  <div style="height:10px"></div>

  <div class="grid auto">
    <label class="check">
      <input type="checkbox" name="blocks[phone]" <?= gb2_blocks_checked($blocks, 'phone') ?>>
      Block phone
    </label>
    <label class="check">
      <input type="checkbox" name="blocks[games]" <?= gb2_blocks_checked($blocks, 'games') ?>>
      Block games
    </label>
    <label class="check">
      <input type="checkbox" name="blocks[other]" <?= gb2_blocks_checked($blocks, 'other') ?>>
      Block other
    </label>
  </div>

  <div style="height:10px"></div>

  <label>
    Repair options (one per line; display-only for now)
    <textarea class="input" name="repairs_lines" rows="5" style="width:100%"><?= gb2_h($repairsLines) ?></textarea>
  </label>

  <div style="height:12px"></div>
  <button class="btn primary" type="submit"><?= $edit ? 'Save changes' : 'Create' ?></button>
  <?php if ($edit): ?>
    <a class="btn" href="/admin/infraction_defs.php">Cancel</a>
  <?php endif; ?>
</form>

<div class="card">
  <div class="h1">Existing</div>
  <div class="h2">Click Edit to change a definition</div>

  <?php if (!$defs): ?>
    <div class="note" style="margin-top:10px">No definitions yet.</div>
  <?php else: ?>
    <div style="margin-top:12px">
      <?php foreach ($defs as $d): ?>
        <?php
          $b = gb2_decode_arr((string)($d['blocks_json'] ?? ''), ['phone'=>0,'games'=>0,'other'=>0]);
          $lad = gb2_decode_arr((string)($d['ladder_json'] ?? ''), []);
          $ladTxt = $lad ? implode('→', array_map('intval', $lad)) : (string)((int)($d['days'] ?? 0));
        ?>
        <div class="note" style="margin-bottom:10px">
          <div style="display:flex; gap:10px; align-items:center; justify-content:space-between;">
            <div>
              <strong><?= gb2_h((string)$d['label']) ?></strong>
              <span class="badge"><?= gb2_h((string)$d['code']) ?></span>
              <?php if ((int)($d['active'] ?? 0) !== 1): ?>
                <span class="badge">inactive</span>
              <?php endif; ?>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
              <a class="btn" href="/admin/infraction_defs.php?edit=<?= (int)$d['id'] ?>">Edit</a>
              <form method="post" action="/admin/infraction_defs.php" style="margin:0">
                <input type="hidden" name="_csrf" value="<?= gb2_h($tok) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <button class="btn" type="submit" onclick="return confirm('Delete this definition?');">Delete</button>
              </form>
            </div>
          </div>

          <div class="small" style="margin-top:6px">
            mode=<?= gb2_h((string)$d['mode']) ?>,
            days=<?= gb2_h($ladTxt) ?>,
            blocks:
            <?= ((int)($b['phone'] ?? 0) ? 'Phone ' : '') ?>
            <?= ((int)($b['games'] ?? 0) ? 'Games ' : '') ?>
            <?= ((int)($b['other'] ?? 0) ? 'Other ' : '') ?>
            <?php if (!(int)($b['phone'] ?? 0) && !(int)($b['games'] ?? 0) && !(int)($b['other'] ?? 0)): ?>None<?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php gb2_nav('infraction_defs'); gb2_page_end(); ?>
