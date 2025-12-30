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

$cfgPath  = __DIR__ . '/../config.php';
$cfg      = gb2_config();
$hasAdmin = !empty($cfg['admin_password_hash']);

if ($hasAdmin) { gb2_admin_require(); }

$note  = '';
$error = '';

$pdo = gb2_pdo();

// Current state
$kidsAll   = gb2_kids_all();
$rule      = gb2_rotation_rule_get_or_create_default();
$ruleKids  = json_decode((string)($rule['kids_json'] ?? '[]'), true) ?: [];
$ruleSlots = json_decode((string)($rule['slots_json'] ?? '[]'), true) ?: [];

// Prefer actual kids for UI order
$actualKidNames  = array_map(fn($k)=> (string)$k['name'], $kidsAll);
$kidNameSet      = array_fill_keys($actualKidNames, true);
$filteredRuleKids= array_values(array_filter($ruleKids, fn($n)=> isset($kidNameSet[(string)$n])));
$missing         = array_values(array_diff($actualKidNames, $filteredRuleKids));
$uiKidsOrder     = array_merge($filteredRuleKids, $missing);

// Slot defaults
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
      $note = 'Parent/guardian password saved.';
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
      foreach ($kidIds as $id) {
        if (isset($kidMap[$id])) $kidNames[] = $kidMap[$id];
      }

      $slots = $_POST['slot_titles'] ?? [];
      if (!is_array($slots)) $slots = [];
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
    $max   = (int)($_POST['bonus_max_per_week'] ?? 1);
    if ($max < 1) $max = 1;

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

  // Reload state after POST
  $kidsAll   = gb2_kids_all();
  $rule      = gb2_rotation_rule_get_or_create_default();
  $ruleKids  = json_decode((string)($rule['kids_json'] ?? '[]'), true) ?: [];
  $ruleSlots = json_decode((string)($rule['slots_json'] ?? '[]'), true) ?: [];
  $bonusDefs = $pdo->query("SELECT * FROM bonus_defs ORDER BY sort_order ASC, id ASC")->fetchAll();
}

gb2_page_start('Setup');
$tok = gb2_csrf_token();
?>

<div class="card">
  <div class="h1">Setup</div>
  <div class="h2">One calm step at a time</div>

  <?php if ($note): ?><div class="status approved" style="margin-top:12px"><?=gb2_h($note)?></div><?php endif; ?>
  <?php if ($error): ?><div class="status rejected" style="margin-top:12px"><?=gb2_h($error)?></div><?php endif; ?>

  <?php if (!$hasAdmin): ?>
    <div style="height:10px"></div>
    <div class="note">Set a parent/guardian password first.</div>

    <form method="post" style="margin-top:12px">
      <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
      <input type="hidden" name="action" value="set_admin">

      <label class="small">Parent/guardian password (min 10 chars)</label>
      <input class="input" type="password" name="admin_password" required>

      <div style="height:12px"></div>
      <button class="btn primary" type="submit">Save password</button>
    </form>
  <?php else: ?>
    <div class="status approved" style="margin-top:12px">Parent/guardian password is set.</div>
    <div class="note" style="margin-top:10px">You can lock/unlock from the bottom nav.</div>
  <?php endif; ?>
</div>

<?php if ($hasAdmin): ?>
  <div class="card">
    <div class="h1">Kids</div>
    <div class="h2">Names + PINs</div>

    <form method="post" style="margin-top:12px">
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

    <div style="height:12px"></div>
    <div class="small">Existing kids:</div>
    <div class="row" style="gap:10px; flex-wrap:wrap; margin-top:10px">
      <?php foreach (gb2_kids_all() as $k): ?>
        <div class="badge"><?=gb2_h((string)$k['name'])?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="h1">Weekday rotation</div>
    <div class="h2">Mon–Fri chore slots</div>
    <div class="note" style="margin-top:10px">
      Rotation uses kid <b>names</b>. Set the order once and it stays stable.
    </div>

    <?php
      $kidsAll = gb2_kids_all();
      $defaultIds = [];
      foreach ($uiKidsOrder as $nm) {
        foreach ($kidsAll as $k) if ((string)$k['name'] === (string)$nm) $defaultIds[] = (int)$k['id'];
      }
      if (count($defaultIds) < 1) foreach ($kidsAll as $k) $defaultIds[] = (int)$k['id'];
    ?>

    <form method="post" style="margin-top:12px">
      <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
      <input type="hidden" name="action" value="save_rotation">

      <div class="small">Kid order (top = first)</div>

      <div style="height:8px"></div>
      <?php foreach ($defaultIds as $i => $kidId): ?>
        <div class="row" style="gap:10px; align-items:center; margin-top:10px">
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

      <div style="height:16px"></div>
      <div class="small">Chore slot titles (Mon–Fri)</div>
      <div class="note" style="margin-top:6px">These are the labels kids will see.</div>

      <div style="height:8px"></div>
      <?php foreach ($uiSlots as $t): ?>
        <input class="input" name="slot_titles[]" value="<?=gb2_h((string)$t)?>" style="margin-top:10px">
      <?php endforeach; ?>

      <div style="height:14px"></div>
      <button class="btn primary" type="submit">Save rotation settings</button>
    </form>
  </div>

  <div class="card">
    <div class="h1">Bonus chores</div>
    <div class="h2">First-come, weekly</div>
    <div class="note" style="margin-top:10px">
      You can seed a starter set or add your own.
    </div>

    <form method="post" class="row" style="gap:10px; flex-wrap:wrap; margin-top:12px">
      <input type="hidden" name="_csrf" value="<?=gb2_h($tok)?>">
      <input type="hidden" name="action" value="seed_bonus_defaults">
      <button class="btn" type="submit">Seed defaults</button>
    </form>

    <div style="height:12px"></div>

    <form method="post" class="grid two" style="margin-top:6px">
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
      <div style="height:12px"></div>
      <div class="small">Current bonus chores:</div>
      <div class="row" style="gap:10px; flex-wrap:wrap; margin-top:10px">
        <?php foreach ($bonusDefs as $b): ?>
          <div class="badge"><?=gb2_h((string)$b['title'])?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="h1">Next</div>
    <div class="h2">Kid login + daily flow</div>

    <div class="row" style="gap:10px; flex-wrap:wrap; margin-top:12px">
      <a class="btn primary" href="/app/login.php">Kid Login</a>
      <a class="btn" href="/app/today.php">Today</a>
      <a class="btn" href="/admin/review.php">Review</a>
    </div>
  </div>
<?php endif; ?>

<?php gb2_nav('setup'); gb2_page_end(); ?>
