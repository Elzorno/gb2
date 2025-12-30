<?php
require_once __DIR__ . '/db.php';

/**
 * Storage layer that keeps the rest of the app mostly unchanged:
 * pages deal in the same arrays as the JSON version, but persistence is SQLite.
 */

function gb_json_decode_maybe($raw, $fallback) {
  if ($raw === null || $raw === '') return $fallback;
  $d = json_decode((string)$raw, true);
  return (json_last_error() === JSON_ERROR_NONE) ? $d : $fallback;
}

function gb_json_encode($v): string {
  $j = json_encode($v, JSON_UNESCAPED_SLASHES);
  return $j === false ? '[]' : $j;
}

function gb_store_ready(string $dataDir): bool {
  return gb_db_exists($dataDir);
}

function gb_store_require_ready(string $dataDir): void {
  if (!gb_store_ready($dataDir)) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Not configured</title><link rel="stylesheet" href="css/style.css">';
    echo '<div class="app"><div class="section"><h2>Grounding Board isn\'t set up yet</h2>';
    echo '<div class="small">Run <code>setup.php</code> to initialize the database and create an admin password/PIN.</div>';
    echo '<div class="buttons" style="margin-top:12px;"><a class="btn primary" href="setup.php">Open setup</a></div>';
    echo '</div></div>';
    exit;
  }
}

function gb_get_config(string $dataDir, array $fallbackConfig): array {
  if (!gb_store_ready($dataDir)) return $fallbackConfig;
  $pdo = gb_pdo($dataDir);
  $rows = $pdo->query('SELECT k, v FROM gb_config')->fetchAll();
  $cfg = $fallbackConfig;
  $jsonKeys = ['default_blocks','favorites_global','favorites_per_kid','device_tokens'];
  foreach ($rows as $r) {
    $k = (string)($r['k'] ?? '');
    $v = (string)($r['v'] ?? '');
    if ($k === '') continue;
    if (in_array($k, $jsonKeys, true)) {
      $cfg[$k] = gb_json_decode_maybe($v, $cfg[$k] ?? []);
    } else {
      // store booleans as strings ("1"/"0") and keep them stable
      if ($v === '1' && in_array($k, ['require_kiosk_token','require_kid_token','setup_locked'], true)) {
        $cfg[$k] = true;
      } elseif ($v === '0' && in_array($k, ['require_kiosk_token','require_kid_token','setup_locked'], true)) {
        $cfg[$k] = false;
      } else {
        $cfg[$k] = $v;
      }
    }
  }
  return $cfg;
}

function gb_set_config(string $dataDir, string $key, $value): void {
  $pdo = gb_pdo($dataDir);
  $jsonKeys = ['default_blocks','favorites_global','favorites_per_kid','device_tokens'];
  if (in_array($key, $jsonKeys, true)) {
    $value = gb_json_encode($value);
  } elseif (in_array($key, ['require_kiosk_token','require_kid_token','setup_locked'], true)) {
    $value = $value ? '1' : '0';
  } else {
    $value = (string)$value;
  }
  $st = $pdo->prepare('INSERT INTO gb_config (k, v) VALUES (:k, :v) ON CONFLICT(k) DO UPDATE SET v=excluded.v');
  $st->execute([':k'=>$key, ':v'=>$value]);
}

function gb_load_kids(string $dataDir): array {
  if (!gb_store_ready($dataDir)) return [];
  $pdo = gb_pdo($dataDir);
  $rows = $pdo->query('SELECT * FROM gb_kids ORDER BY id ASC')->fetchAll();
  $kids = [];
  foreach ($rows as $r) {
    $kids[] = [
      'name' => (string)($r['name'] ?? ''),
      'grounded_start' => (string)($r['grounded_start'] ?? ''),
      'grounded_until' => (string)($r['grounded_until'] ?? ''),
      'reason' => (string)($r['reason'] ?? ''),
      'review_on' => (string)($r['review_on'] ?? ''),
      'last_infraction' => (string)($r['last_infraction'] ?? ''),
      'blocks' => gb_json_decode_maybe($r['blocks_json'] ?? null, []),
      'strikes' => gb_json_decode_maybe($r['strikes_json'] ?? null, []),
    ];
  }
  return $kids;
}

function gb_save_kids(string $dataDir, array $kids): bool {
  $pdo = gb_pdo($dataDir);
  try {
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM gb_kids;');
    $st = $pdo->prepare('INSERT INTO gb_kids (name, grounded_start, grounded_until, reason, review_on, last_infraction, blocks_json, strikes_json)
      VALUES (:name, :gs, :gu, :reason, :ro, :li, :blocks, :strikes)');
    foreach ($kids as $k) {
      if (!is_array($k)) continue;
      $name = trim((string)($k['name'] ?? ''));
      if ($name === '') continue;
      $st->execute([
        ':name' => $name,
        ':gs' => (string)($k['grounded_start'] ?? ''),
        ':gu' => (string)($k['grounded_until'] ?? ''),
        ':reason' => (string)($k['reason'] ?? ''),
        ':ro' => (string)($k['review_on'] ?? ''),
        ':li' => (string)($k['last_infraction'] ?? ''),
        ':blocks' => gb_json_encode($k['blocks'] ?? []),
        ':strikes' => gb_json_encode($k['strikes'] ?? []),
      ]);
    }
    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    return false;
  }
}

function gb_load_infractions(string $dataDir): array {
  if (!gb_store_ready($dataDir)) return [];
  $pdo = gb_pdo($dataDir);
  $rows = $pdo->query('SELECT * FROM gb_infractions ORDER BY label ASC')->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'id' => (string)($r['id'] ?? ''),
      'label' => (string)($r['label'] ?? ''),
      'days' => (int)($r['days'] ?? 0),
      'mode' => (string)($r['mode'] ?? 'set'),
      'blocks' => gb_json_decode_maybe($r['blocks_json'] ?? null, []),
      'ladder' => gb_json_decode_maybe($r['ladder_json'] ?? null, []),
      'repairs' => gb_json_decode_maybe($r['repairs_json'] ?? null, []),
      'review_days' => (int)($r['review_days'] ?? 0),
    ];
  }
  return $out;
}

function gb_save_infractions(string $dataDir, array $infractions): bool {
  $pdo = gb_pdo($dataDir);
  try {
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM gb_infractions;');
    $st = $pdo->prepare('INSERT INTO gb_infractions (id, label, days, mode, blocks_json, ladder_json, repairs_json, review_days)
      VALUES (:id,:label,:days,:mode,:blocks,:ladder,:repairs,:review_days)');
    foreach ($infractions as $inf) {
      if (!is_array($inf)) continue;
      $id = trim((string)($inf['id'] ?? ''));
      $label = trim((string)($inf['label'] ?? ''));
      if ($id === '' || $label === '') continue;
      $st->execute([
        ':id' => $id,
        ':label' => $label,
        ':days' => (int)($inf['days'] ?? 0),
        ':mode' => ((string)($inf['mode'] ?? 'set') === 'add') ? 'add' : 'set',
        ':blocks' => gb_json_encode($inf['blocks'] ?? []),
        ':ladder' => gb_json_encode($inf['ladder'] ?? []),
        ':repairs' => gb_json_encode($inf['repairs'] ?? []),
        ':review_days' => (int)($inf['review_days'] ?? 0),
      ]);
    }
    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    return false;
  }
}

function gb_log_event_db(string $dataDir, string $kid, string $action, array $details = []): void {
  if (!gb_store_ready($dataDir)) return;
  $pdo = gb_pdo($dataDir);
  $st = $pdo->prepare('INSERT INTO gb_events (when_ts, kid, action, details_json) VALUES (:w,:kid,:act,:d)');
  $st->execute([
    ':w' => date('Y-m-d H:i:s'),
    ':kid' => $kid,
    ':act' => $action,
    ':d' => gb_json_encode($details),
  ]);
}

function gb_load_events(string $dataDir, int $limit = 150): array {
  if (!gb_store_ready($dataDir)) return [];
  $pdo = gb_pdo($dataDir);
  $st = $pdo->prepare('SELECT when_ts, kid, action, details_json FROM gb_events ORDER BY id DESC LIMIT :lim');
  $st->bindValue(':lim', max(1, min(1000, $limit)), PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'when' => (string)($r['when_ts'] ?? ''),
      'kid' => (string)($r['kid'] ?? ''),
      'action' => (string)($r['action'] ?? ''),
      'details' => gb_json_decode_maybe($r['details_json'] ?? null, []),
    ];
  }
  return $out;
}
