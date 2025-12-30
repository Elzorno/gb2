<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/kids.php';
require_once __DIR__ . '/../lib/rotation.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/audit.php';

gb2_db_init();

$cfgPath = __DIR__ . '/../config.php';
$cfg = gb2_config();
$hasAdmin = !empty($cfg['admin_password_hash']);

// After admin is set, require an unlock BEFORE showing any setup actions.
// This prevents losing POST data due to auth redirects.
if ($hasAdmin) { gb2_admin_require(); }

$note = '';
$error = '';

$pdo = gb2_pdo();

// Load current state for setup screens
$kidsAll = gb2_kids_all(); // id, name
$rule = gb2_rotation_rule_get_or_create_default();
$ruleKids = json_decode((string)($rule['kids_json'] ?? '[]'), true) ?: [];
$ruleSlots = json_decode((string)($rule['slots_json'] ?? '[]'), true) ?: [];

// If the rule kids don't match actual kids, prefer actual kids for the UI.
$actualKidNames = array_map(fn($k)=> (string)$k['name'], $kidsAll);
$kidNameSet = array_fill_keys($actualKidNames, true);
$filteredRuleKids = array_values(array_filter($ruleKids, fn($n)=> isset($kidNameSet[(string)$n])));
$missing = array_values(array_diff($actualKidNames, $filteredRuleKids));
$uiKidsOrder = array_merge($filteredRuleKids, $missing);

$uiSlots = $ruleSlots;
if (count($uiSlots) < 5) {
  $fallback = ['Dishes','Trash + Bathrooms','Help Cook','Common Areas','Help Everybody'];
  $uiSlots = array_slice(array_merge($uiSlots, $fallback), 0, 5);
}

// Bonus defs
$bonusDefs = $pdo->query("SELECT * FROM bonus_defs ORDER BY sort_order ASC, id ASC")->fetchAll();


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  gb2_csrf_verify();
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'set_admin') {
    $pw = (string)($_POST['admin_password'] ?? '');
    if (strlen($pw) < 10) {
      $error = 'Use at least 10 characters.';
    } else {
      $sample = require __DIR__ . '/../config.sample.php';
      $sample['admin_password_hash'] = password_hash($pw, PASSWORD_ARGON2ID);
      if (file_exists($cfgPath)) {
        $existing = require $cfgPath;
        $sample['data_dir'] = $existing['data_dir'] ?? $sample['data_dir'];
      }
      $export = "<?php\nreturn " . var_export($sample, true) . ";\n";
      file_put_contents($cfgPath, $export);
      $note = 'Admin password set. Reload page.';
      gb2_audit('admin', 0, 'setup_set_admin', []);
      $hasAdmin = true;
    }

  } elseif ($action === 'add_kid') {
    gb2_admin_require();
    $name = trim((string)($_POST['kid_name'] ?? ''));
    $pin  = trim((string)($_POST['kid_pin'] ?? ''));
    if ($name === '') {
      $error = 'Kid name required.';
    } else {
      $id = gb2_kid_create($name, 0);
      if ($pin !== '') { gb2_kid_set_pin($id, $pin); }
      gb2_audit('admin', 0, 'setup_add_kid', ['kid'=>$name]);
      $note = 'Kid added.';
    }

  } elseif ($action === 'save_rotation') {
    gb2_admin_require();

    $kidIds = $_POST['kid_order'] ?? [];
    if (!is_array($kidIds) || count($kidIds) < 1) {
      $error = 'Choose a kid order.';
    } else {
      $kidIds = array_map('intval', $kidIds);
      $kidIds = array_values(array_filter($kidIds, fn($x)=> $x > 0));
      $kidIds = array_values(array_unique($kidIds));

      $kidMap = [];
      foreach (gb2_kids_all() as $k) { $kidMap[(int)$k['id']] = (string)$k['name']; }
      $kidNames = [];
      foreach ($kidIds as $id) { if (isset($kidMap[$id])) { $kidNames[] = $kidMap[$id]; } }

      $slots = $_POST['slot_titles'] ?? [];
      if (!is_array($slots)) { $slots = []; }
      $slots = array_map(fn($s)=>trim((string)$s), $slots);
      $slots = array_values(array_filter($slots, fn($s)=>$s !== ''));
      $slots = array_slice($slots, 0, 10);

      if (count($kidNames) < 1) {
        $error = 'Could not resolve kid names for rotation.';
      } elseif (count($slots) < 1) {
        $error = 'Enter at least one chore slot title.';
      } else {
        $rule = gb2_rotation_rule_get_or_create_default();
        $pdo = gb2_pdo();
        $pdo->prepare("UPDATE rotation_rules SET kids_json=?, slots_json=? WHERE id=?")
            ->execute([json_encode($kidNames), json_encode($slots), (int)$rule['id']]);

        gb2_rotation_ensure_slots_exist($slots);
        $note = 'Rotation settings saved.';
        gb2_audit('admin', 0, 'setup_save_rotation', ['kids'=>$kidNames,'slots'=>$slots]);
      }
    }

  } elseif ($action === 'add_bonus_def') {
    gb2_admin_require();

    $title = trim((string)($_POST['bonus_title'] ?? ''));
    $max = (int)($_POST['bonus_max_per_week'] ?? 1);
    if ($max < 1) { $max = 1; }

    if ($title === '') {
      $error = 'Bonus title is required.';
    } else {
      $pdo = gb2_pdo();
      $sort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM bonus_defs")->fetchColumn();
      $pdo->prepare("INSERT INTO bonus_defs(title,active,reward_cents,reward_phone_min,reward_games_min,max_per_week,sort_order)
                     VALUES(?,?,?,?,?,?,?)")
          ->execute([$title,1,0,0,0,$max,$sort]);
      $note = 'Bonus chore added.';
      gb2_audit('admin', 0, 'setup_add_bonus_def', ['title'=>$title,'max_per_week'=>$max]);
    }

  } elseif ($action === 'seed_bonus_defaults') {
    gb2_admin_require();

    $pdo = gb2_pdo();
    $existing = (int)$pdo->query("SELECT COUNT(*) FROM bonus_defs")->fetchColumn();
    if ($existing > 0) {
      $note = 'Bonus chores already exist (no changes made).';
    } else {
      $defaults = ['Laundry helper','Vacuum a room','Wipe down kitchen counters','Take out recycling','Help with pet care'];
      foreach ($defaults as $i => $t) {
        $pdo->prepare("INSERT INTO bonus_defs(title,active,reward_cents,reward_phone_min,reward_games_min,max_per_week,sort_order)
                       VALUES(?,?,?,?,?,?,?)")
            ->execute([$t,1,0,0,0,1,$i]);
      }
      $note = 'Seeded default bonus chores.';
      gb2_audit('admin', 0, 'setup_seed_bonus_defaults', ['count'=>count($defaults)]);
    }
  }
}
gb2_page_start('Setup');
$tok = gb2_csrf_token();
?>
<div class="card">
  <div class="h1">Initial setup</div>
  <div class="note">Run once to set admin password and create kids.</div>
  <?php if ($note): ?><div class="status approved"><?=gb2_h($note)?></div><?php endif; ?>
  <?php if ($error): ?><div class="status rejected"><?=gb2_h($error)?></div><?php endif; ?>

  <?php if (!$hasAdmin): ?>
    <hr>
    <div class="h2">1) Set admin password</div>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
      <input type="hidden" name="action" value="set_admin">
      <label class="small">Admin password (min 10 chars)</label>
      <input class="input" type="password" name="admin_password" required>
      <div style="height:12px"></div>
      <button class="btn primary" type="submit">Save admin password</button>
    </form>
  <?php else: ?>
    <div class="status approved">Admin password is set.</div>
    <div class="note">Unlock at <a href="/admin/login.php">Admin Login</a>.</div>
  <?php endif; ?>
</div>

<?php if ($hasAdmin): ?>
<div class="card">
  <div class="h1">Kids</div>
  <div class="note">Add kids and set PINs. Recommended: 6 digits.</div>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
    <input type="hidden" name="action" value="add_kid">
    <label class="small">Name</label>
    <input class="input" name="kid_name" required>
    <div style="height:10px"></div>
    <label class="small">PIN (optional now)</label>
    <input class="input" name="kid_pin" inputmode="numeric" pattern="[0-9]*">
    <div style="height:12px"></div>
    <button class="btn ok" type="submit">Add kid</button>
  </form>
  <hr>
  <div class="small">Existing kids:</div>
  <?php foreach (gb2_kids_all() as $k): ?>
    <div class="badge" style="margin-top:8px"><?=gb2_h($k['name'])?></div>
  <?php endforeach; ?>

  <hr>
  <div class="h2">Next: Weekday rotation</div>
  <div class="note">Rotation uses kid <b>names</b>. If your names don’t match the default config, set the order here so assignments generate correctly.</div>

  <form method="post" class="grid">
    <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
    <input type="hidden" name="action" value="save_rotation">

    <label class="small">Kid order (top = first in rotation)</label>
    <div class="note">Tip: set this once, then rotation stays stable. You can change it later if needed.</div>

    <?php
      $kidsAll = gb2_kids_all();
      $byId = [];
      foreach ($kidsAll as $k) $byId[(int)$k['id']] = (string)$k['name'];
      $defaultIds = [];
      foreach ($uiKidsOrder as $nm) {
        foreach ($kidsAll as $k) if ((string)$k['name'] === (string)$nm) $defaultIds[] = (int)$k['id'];
      }
      // If something weird happened, fall back to current kids in DB order.
      if (count($defaultIds) < 1) {
        foreach ($kidsAll as $k) $defaultIds[] = (int)$k['id'];
      }
    ?>

    <?php foreach ($defaultIds as $i => $kidId): ?>
      <div class="row" style="gap:10px; align-items:center; margin-top:8px">
        <div class="badge" style="min-width:38px; text-align:center"><?= (int)($i+1) ?></div>
        <select class="input" name="kid_order[]">
          <?php foreach ($kidsAll as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= ((int)$k['id']===(int)$kidId)?'selected':'' ?>>
              <?= gb2_h((string)$k['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endforeach; ?>

    <div style="height:12px"></div>
    <label class="small">Chore slot titles (Mon–Fri)</label>
    <?php foreach ($uiSlots as $t): ?>
      <input class="input" name="slot_titles[]" value="<?=gb2_h((string)$t)?>">
      <div style="height:8px"></div>
    <?php endforeach; ?>

    <button class="btn primary" type="submit">Save rotation settings</button>
  </form>

  <hr>
  <div class="h2">Next: Bonus chores</div>
  <div class="note">These are first-come weekly claims. You can seed defaults or add your own.</div>

  <form method="post" class="row" style="gap:10px; flex-wrap:wrap">
    <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
    <input type="hidden" name="action" value="seed_bonus_defaults">
    <button class="btn" type="submit">Seed defaults</button>
  </form>

  <div style="height:12px"></div>
  <form method="post" class="grid two">
    <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
    <input type="hidden" name="action" value="add_bonus_def">
    <div>
      <label class="small">Bonus title</label>
      <input class="input" name="bonus_title" placeholder="e.g., Vacuum living room">
    </div>
    <div>
      <label class="small">Max per week</label>
      <input class="input" name="bonus_max_per_week" inputmode="numeric" pattern="[0-9]*" value="1">
    </div>
    <div class="row" style="justify-content:flex-end">
      <button class="btn ok" type="submit">Add bonus</button>
    </div>
  </form>

  <?php if (!empty($bonusDefs)): ?>
    <div style="height:10px"></div>
    <div class="small">Current bonus chores:</div>
    <?php foreach ($bonusDefs as $b): ?>
      <div class="badge" style="margin-top:8px"><?=gb2_h((string)$b['title'])?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <hr>
  <div class="h2">Finish</div>
  <div class="note">Next steps: kid login → Today view → (optional) admin review queue.</div>
  <div class="row" style="gap:10px; flex-wrap:wrap">
    <a class="btn primary" href="/app/login.php">Kid Login</a>
    <a class="btn" href="/app/today.php">Today</a>
    <a class="btn" href="/admin/review.php">Admin Review</a>
  </div>

</div>
<?php endif; ?>

<?php gb2_nav('setup'); gb2_page_end(); ?>
