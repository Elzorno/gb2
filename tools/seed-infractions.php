<?php
declare(strict_types=1);

/**
 * Seed core infraction definitions (idempotent by code).
 *
 * Usage:
 *   php /var/www/tools/seed-infractions.php
 *
 * Behavior:
 * - Upserts by code (insert if missing, update if exists)
 * - Leaves created_at unchanged on update
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/common.php';

gb2_db_init();
$pdo = gb2_pdo();

function upsert_def(PDO $pdo, array $d): void {
  $code = (string)$d['code'];

  $st = $pdo->prepare("SELECT id, created_at FROM infraction_defs WHERE code=?");
  $st->execute([$code]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $pdo->prepare("
      INSERT INTO infraction_defs
        (code,label,active,mode,days,ladder_json,blocks_json,repairs_json,review_days,sort_order,created_at)
      VALUES
        (?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
      $d['code'],
      $d['label'],
      (int)$d['active'],
      $d['mode'],
      (int)$d['days'],
      json_encode($d['ladder']),
      json_encode($d['blocks']),
      json_encode($d['repairs']),
      (int)$d['review_days'],
      (int)$d['sort_order'],
      gb2_now_iso(),
    ]);
    echo "inserted: {$code}\n";
    return;
  }

  // Update everything but created_at
  $pdo->prepare("
    UPDATE infraction_defs
    SET label=?, active=?, mode=?, days=?,
        ladder_json=?, blocks_json=?, repairs_json=?,
        review_days=?, sort_order=?
    WHERE code=?
  ")->execute([
    $d['label'],
    (int)$d['active'],
    $d['mode'],
    (int)$d['days'],
    json_encode($d['ladder']),
    json_encode($d['blocks']),
    json_encode($d['repairs']),
    (int)$d['review_days'],
    (int)$d['sort_order'],
    $d['code'],
  ]);

  echo "updated: {$code}\n";
}

$defs = [
  [
    'code' => 'DISRESPECT',
    'label' => 'Disrespect',
    'active' => 1,
    'mode' => 'add',              // stack time
    'days' => 0,
    'ladder' => [1, 3, 7],
    'blocks' => ['phone'=>1,'games'=>1,'other'=>0],
    'repairs' => [
      'Apology (specific + sincere)',
      'Repair action (clean-up / make-right)',
      'Respectful tone for 24h',
    ],
    'review_days' => 0,            // auto-half
    'sort_order' => 10,
  ],
  [
    'code' => 'VIOLENCE',
    'label' => 'Violence / Threats',
    'active' => 1,
    'mode' => 'set',               // set from now
    'days' => 0,
    'ladder' => [3, 7, 14],
    'blocks' => ['phone'=>1,'games'=>1,'other'=>1],
    'repairs' => [
      'Cooling-off period',
      'Safety plan check-in',
      'Restitution / make-right (if applicable)',
    ],
    'review_days' => 0,
    'sort_order' => 20,
  ],
  [
    'code' => 'SKIP_CHORES',
    'label' => 'Skipping chores / refusing tasks',
    'active' => 1,
    'mode' => 'add',
    'days' => 1,                   // simple default
    'ladder' => [],
    'blocks' => ['phone'=>0,'games'=>1,'other'=>0],
    'repairs' => [
      'Complete the missed chore',
      'Complete 1 extra chore (optional)',
    ],
    'review_days' => 0,
    'sort_order' => 30,
  ],
  [
    'code' => 'VAPING',
    'label' => 'Vaping / nicotine',
    'active' => 1,
    'mode' => 'set',               // per your request
    'days' => 0,
    'ladder' => [14, 14, 21],      // set ladder 14,14,21
    'blocks' => ['phone'=>1,'games'=>1,'other'=>1],
    'repairs' => [
      'Confiscation + parent meeting',
      'Health / education conversation',
      'Restitution (cost/impact) if applicable',
    ],
    'review_days' => 0,
    'sort_order' => 40,
  ],
];

$pdo->beginTransaction();
try {
  foreach ($defs as $d) {
    // enforce mode whitelist
    $d['mode'] = ($d['mode'] === 'add') ? 'add' : 'set';

    // normalize arrays
    $d['ladder'] = array_values(array_filter(array_map('intval', $d['ladder']), fn($x) => $x > 0));
    $d['repairs'] = array_values(array_filter(array_map('strval', $d['repairs']), fn($x) => trim($x) !== ''));

    // blocks normalization
    $b = $d['blocks'];
    $d['blocks'] = [
      'phone' => (int)($b['phone'] ?? 0) ? 1 : 0,
      'games' => (int)($b['games'] ?? 0) ? 1 : 0,
      'other' => (int)($b['other'] ?? 0) ? 1 : 0,
    ];

    upsert_def($pdo, $d);
  }
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
  exit(1);
}

echo "OK: seed complete\n";
