<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/privileges.php';
require_once __DIR__ . '/audit.php';

function gb2_inf_defs_all(bool $activeOnly = true): array {
  $pdo = gb2_pdo();
  if ($activeOnly) {
    $st = $pdo->query("SELECT * FROM infraction_defs WHERE active=1 ORDER BY sort_order ASC, label ASC");
  } else {
    $st = $pdo->query("SELECT * FROM infraction_defs ORDER BY active DESC, sort_order ASC, label ASC");
  }
  return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

function gb2_inf_def_by_id(int $defId): ?array {
  $pdo = gb2_pdo();
  $st = $pdo->prepare("SELECT * FROM infraction_defs WHERE id=?");
  $st->execute([$defId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

function gb2_inf_get_strikes(int $kidId, int $defId): int {
  $pdo = gb2_pdo();
  $st = $pdo->prepare("SELECT strike_count FROM infraction_strikes WHERE kid_id=? AND infraction_def_id=?");
  $st->execute([$kidId, $defId]);
  $v = $st->fetchColumn();
  return $v === false ? 0 : (int)$v;
}

function gb2_inf_set_strikes(int $kidId, int $defId, int $count): void {
  $pdo = gb2_pdo();
  $pdo->prepare("
    INSERT INTO infraction_strikes(kid_id, infraction_def_id, strike_count, updated_at)
    VALUES(?,?,?,?)
    ON CONFLICT(kid_id, infraction_def_id) DO UPDATE SET
      strike_count=excluded.strike_count,
      updated_at=excluded.updated_at
  ")->execute([$kidId, $defId, $count, gb2_now_iso()]);
}

function gb2_inf_reset_strike_for_event(int $kidId, int $defId): void {
  $pdo = gb2_pdo();
  $pdo->prepare("
    INSERT INTO infraction_strikes(kid_id, infraction_def_id, strike_count, updated_at)
    VALUES(?,?,0,?)
    ON CONFLICT(kid_id, infraction_def_id) DO UPDATE SET
      strike_count=0,
      updated_at=excluded.updated_at
  ")->execute([$kidId, $defId, gb2_now_iso()]);

  gb2_audit('admin', 0, 'infraction.strike_reset', [
    'kid_id' => $kidId,
    'infraction_def_id' => $defId,
  ]);
}

function gb2_inf_decode_json_array(string $json): array {
  $v = json_decode($json, true);
  return is_array($v) ? $v : [];
}

function gb2_inf_decode_blocks(string $json): array {
  $v = json_decode($json, true);
  if (!is_array($v)) {
    return ['phone'=>0,'games'=>0,'other'=>0];
  }
  return [
    'phone' => (int)($v['phone'] ?? 0),
    'games' => (int)($v['games'] ?? 0),
    'other' => (int)($v['other'] ?? 0),
  ];
}

/**
 * Compute days to apply given definition and strike_after.
 * - ladder: use ladder[min(strike_after-1, lastIndex)]
 * - else: use days
 */
function gb2_inf_compute_days(array $def, int $strikeAfter): int {
  $days = (int)($def['days'] ?? 0);
  $ladder = gb2_inf_decode_json_array((string)($def['ladder_json'] ?? '[]'));
  $ladderVals = [];
  foreach ($ladder as $x) {
    $n = (int)$x;
    if ($n > 0) $ladderVals[] = $n;
  }
  if ($ladderVals) {
    $idx = max(0, $strikeAfter - 1);
    if ($idx >= count($ladderVals)) $idx = count($ladderVals) - 1;
    return (int)$ladderVals[$idx];
  }
  return max(0, $days);
}

/** v1 parity: if review_days==0 then half-days (ceil), min 1 when days>0. */
function gb2_inf_compute_review_days(array $def, int $daysApplied): int {
  $rd = (int)($def['review_days'] ?? 0);
  if ($rd > 0) return $rd;
  if ($daysApplied <= 0) return 0;
  $half = (int)ceil($daysApplied / 2);
  return max(1, $half);
}

/**
 * Mark an infraction event as reviewed (and record what resolution was done).
 * Requires v3 migration columns.
 */
function gb2_inf_mark_event_reviewed(
  int $eventId,
  string $actorType,
  int $actorId,
  string $reviewNote,
  string $reviewAction,
  array $resolvedUntil
): void {
  $pdo = gb2_pdo();

  $reviewAction = trim($reviewAction);
  if (!in_array($reviewAction, ['review_only','unlock','shorten'], true)) {
    $reviewAction = 'review_only';
  }

  $pdo->prepare("
    UPDATE infraction_events
    SET reviewed_at=?,
        reviewed_by_actor_type=?,
        reviewed_by_actor_id=?,
        review_note=?,
        review_action=?,
        review_resolved_until_json=?
    WHERE id=?
  ")->execute([
    gb2_now_iso(),
    $actorType,
    $actorId,
    $reviewNote,
    $reviewAction,
    json_encode($resolvedUntil),
    $eventId
  ]);

  gb2_audit($actorType, $actorId, 'infraction.review', [
    'event_id' => $eventId,
    'review_action' => $reviewAction,
    'review_note' => $reviewNote,
    'resolved_until' => $resolvedUntil,
  ]);
}

/**
 * Preview an infraction application without mutating DB.
 */
function gb2_inf_preview(int $kidId, int $defId): array {
  $def = gb2_inf_def_by_id($defId);
  if (!$def) throw new RuntimeException('Infraction not found');

  $strikeBefore = gb2_inf_get_strikes($kidId, $defId);
  $strikeAfter = $strikeBefore + 1;

  $daysApplied = gb2_inf_compute_days($def, $strikeAfter);
  $reviewDays = gb2_inf_compute_review_days($def, $daysApplied);
  $reviewOn = $reviewDays > 0 ? gmdate('Y-m-d', time() + ($reviewDays * 86400)) : null;

  $mode = (string)($def['mode'] ?? 'set');
  if ($mode !== 'set' && $mode !== 'add') $mode = 'set';

  $blocks = gb2_inf_decode_blocks((string)($def['blocks_json'] ?? '{}'));

  // Compute would-be untils based on current privileges
  $pv = gb2_priv_get_for_kid($kidId);
  $minutes = $daysApplied * 1440;
  $computed = [];

  foreach (['phone','games','other'] as $w) {
    if ((int)($blocks[$w] ?? 0) !== 1) continue;

    if ($mode === 'set') {
      $computed[$w] = gb2_priv_iso_from_ts(time() + ($minutes * 60));
    } else {
      $cur = gb2_priv_parse_until($pv[$w . '_locked_until'] ?? null);
      $base = max(time(), $cur);
      $computed[$w] = gb2_priv_iso_from_ts($base + ($minutes * 60));
    }
  }

  return [
    'def' => $def,
    'strike_before' => $strikeBefore,
    'strike_after' => $strikeAfter,
    'days_applied' => $daysApplied,
    'mode' => $mode,
    'blocks' => $blocks,
    'computed_until' => $computed,
    'review_on' => $reviewOn,
  ];
}

/**
 * Apply an infraction:
 * - increments strikes
 * - applies locks via privileges helpers
 * - records event + audit
 *
 * Returns a summary array for UI.
 */
function gb2_inf_apply(
  int $kidId,
  int $defId,
  string $note = '',
  string $actorType = 'admin',
  int $actorId = 0
): array {
  $pdo = gb2_pdo();

  $def = gb2_inf_def_by_id($defId);
  if (!$def) throw new RuntimeException('Infraction not found');

  $mode = (string)($def['mode'] ?? 'set');
  if ($mode !== 'set' && $mode !== 'add') $mode = 'set';

  $blocks = gb2_inf_decode_blocks((string)($def['blocks_json'] ?? '{}'));

  $strikeBefore = gb2_inf_get_strikes($kidId, $defId);
  $strikeAfter = $strikeBefore + 1;

  $daysApplied = gb2_inf_compute_days($def, $strikeAfter);
  $minutes = $daysApplied * 1440;

  $reviewDays = gb2_inf_compute_review_days($def, $daysApplied);
  $reviewOn = $reviewDays > 0 ? gmdate('Y-m-d', time() + ($reviewDays * 86400)) : null;

  $computedUntil = [];

  $pdo->beginTransaction();
  try {
    // Update strikes
    gb2_inf_set_strikes($kidId, $defId, $strikeAfter);

    // Apply locks
    foreach (['phone','games','other'] as $w) {
      if ((int)($blocks[$w] ?? 0) !== 1) continue;
      if ($minutes <= 0) continue;

      if ($mode === 'set') {
        $untilIso = gb2_priv_iso_from_ts(time() + ($minutes * 60));
        $computedUntil[$w] = gb2_priv_set_lock_until($kidId, $w, $untilIso);
      } else {
        $computedUntil[$w] = gb2_priv_add_lock_minutes($kidId, $w, $minutes);
      }
    }

    // Record event
    $pdo->prepare("
      INSERT INTO infraction_events(
        kid_id, infraction_def_id, ts,
        actor_type, actor_id,
        strike_before, strike_after,
        days_applied, mode, blocks_json,
        computed_until_json, review_on, note
      ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
      $kidId, $defId, gb2_now_iso(),
      $actorType, $actorId,
      $strikeBefore, $strikeAfter,
      $daysApplied, $mode, json_encode($blocks),
      json_encode($computedUntil), $reviewOn, $note
    ]);

    // Audit (existing table)
    gb2_audit($actorType, $actorId, 'infraction.apply', [
      'kid_id' => $kidId,
      'infraction_def_id' => $defId,
      'infraction_code' => (string)($def['code'] ?? ''),
      'label' => (string)($def['label'] ?? ''),
      'strike_before' => $strikeBefore,
      'strike_after' => $strikeAfter,
      'days_applied' => $daysApplied,
      'mode' => $mode,
      'blocks' => $blocks,
      'computed_until' => $computedUntil,
      'review_on' => $reviewOn,
      'note' => $note,
    ]);

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }

  return [
    'def' => $def,
    'strike_before' => $strikeBefore,
    'strike_after' => $strikeAfter,
    'days_applied' => $daysApplied,
    'mode' => $mode,
    'blocks' => $blocks,
    'computed_until' => $computedUntil,
    'review_on' => $reviewOn,
    'note' => $note,
  ];
}
