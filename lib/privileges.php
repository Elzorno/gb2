<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';

function gb2_priv_ensure_row(int $kidId): void {
  $pdo = gb2_pdo();
  $pdo->prepare("INSERT OR IGNORE INTO privileges(kid_id) VALUES(?)")
      ->execute([$kidId]);
}

function gb2_priv_parse_until(?string $iso): int {
  if (!$iso) return 0;
  $t = strtotime($iso);
  return $t ? (int)$t : 0;
}

function gb2_priv_iso_from_ts(int $ts): string {
  return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

/**
 * Auto-unlock any expired locks (deterministic, no cron).
 */
function gb2_priv_autounlock_if_needed(int $kidId, array $row): array {
  $now = time();

  $phoneUntil = gb2_priv_parse_until($row['phone_locked_until'] ?? null);
  $gamesUntil = gb2_priv_parse_until($row['games_locked_until'] ?? null);
  $otherUntil = gb2_priv_parse_until($row['other_locked_until'] ?? null);

  $phoneLocked = (int)($row['phone_locked'] ?? 0);
  $gamesLocked = (int)($row['games_locked'] ?? 0);
  $otherLocked = (int)($row['other_locked'] ?? 0);

  $updates = [];
  if ($phoneLocked === 1 && $phoneUntil > 0 && $phoneUntil <= $now) {
    $updates['phone_locked'] = 0;
    $updates['phone_locked_until'] = null;
  }
  if ($gamesLocked === 1 && $gamesUntil > 0 && $gamesUntil <= $now) {
    $updates['games_locked'] = 0;
    $updates['games_locked_until'] = null;
  }
  if ($otherLocked === 1 && $otherUntil > 0 && $otherUntil <= $now) {
    $updates['other_locked'] = 0;
    $updates['other_locked_until'] = null;
  }

  if ($updates) {
    $pdo = gb2_pdo();
    $sql = "UPDATE privileges SET ";
    $vals = [];
    $sets = [];
    foreach ($updates as $k => $v) {
      if ($v === null) {
        $sets[] = "{$k}=NULL";
      } else {
        $sets[] = "{$k}=?";
        $vals[] = $v;
      }
    }
    $sql .= implode(',', $sets) . " WHERE kid_id=?";
    $vals[] = $kidId;
    $pdo->prepare($sql)->execute($vals);

    foreach ($updates as $k => $v) {
      $row[$k] = $v;
    }
  }

  return $row;
}

function gb2_priv_get_for_kid(int $kidId): array {
  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();
  $st = $pdo->prepare("SELECT * FROM privileges WHERE kid_id=?");
  $st->execute([$kidId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);

  $row = $r ?: [
    'kid_id'=>$kidId,
    'phone_locked'=>0,'games_locked'=>0,'other_locked'=>0,
    'bank_phone_min'=>0,'bank_games_min'=>0,'bank_other_min'=>0,
    'phone_locked_until'=>null,'games_locked_until'=>null,'other_locked_until'=>null,
  ];

  $row = gb2_priv_autounlock_if_needed($kidId, $row);

  $row['phone_locked_until'] = $row['phone_locked_until'] ?? null;
  $row['games_locked_until'] = $row['games_locked_until'] ?? null;
  $row['other_locked_until'] = $row['other_locked_until'] ?? null;

  return $row;
}

function gb2_priv_set_locks(int $kidId, int $phoneLocked, int $gamesLocked, int $otherLocked): void {
  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();

  // If unlocking, also clear until timestamps (if columns exist)
  $hasUntil = true;
  try {
    $pdo->query("SELECT phone_locked_until FROM privileges LIMIT 1");
  } catch (Throwable $e) {
    $hasUntil = false;
  }

  if ($hasUntil) {
    $pdo->prepare("UPDATE privileges
                   SET phone_locked=?,
                       games_locked=?,
                       other_locked=?,
                       phone_locked_until = CASE WHEN ?=0 THEN NULL ELSE phone_locked_until END,
                       games_locked_until = CASE WHEN ?=0 THEN NULL ELSE games_locked_until END,
                       other_locked_until = CASE WHEN ?=0 THEN NULL ELSE other_locked_until END
                   WHERE kid_id=?")
        ->execute([$phoneLocked, $gamesLocked, $otherLocked,
                   $phoneLocked, $gamesLocked, $otherLocked,
                   $kidId]);
  } else {
    $pdo->prepare("UPDATE privileges
                   SET phone_locked=?, games_locked=?, other_locked=?
                   WHERE kid_id=?")
        ->execute([$phoneLocked, $gamesLocked, $otherLocked, $kidId]);
  }
}

function gb2_priv_set_banks(int $kidId, int $phoneMin, int $gamesMin, int $otherMin): void {
  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();
  $pdo->prepare("UPDATE privileges
                 SET bank_phone_min=?, bank_games_min=?, bank_other_min=?
                 WHERE kid_id=?")
      ->execute([$phoneMin, $gamesMin, $otherMin, $kidId]);
}

function gb2_priv_apply_bonus(int $kidId, int $phoneMin, int $gamesMin): void {
  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();
  $pdo->prepare("UPDATE privileges
                 SET bank_phone_min = bank_phone_min + ?,
                     bank_games_min = bank_games_min + ?
                 WHERE kid_id=?")
      ->execute([$phoneMin, $gamesMin, $kidId]);
}

/**
 * Extend a lock deterministically: only extends if new-until is later.
 * Also sets the corresponding *_locked flag to 1.
 *
 * NOTE: This extends from "now", not from existing until. Infractions "add"
 * should use gb2_priv_add_lock_minutes() instead.
 */
function gb2_priv_extend_lock_until(int $kidId, string $which, int $minutes): void {
  if (!in_array($which, ['phone','games','other'], true)) {
    throw new InvalidArgumentException('Invalid lock type');
  }
  if ($minutes <= 0) return;

  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();

  $colLocked = $which . "_locked";
  $colUntil  = $which . "_locked_until";

  try {
    $pdo->query("SELECT {$colUntil} FROM privileges LIMIT 1");
  } catch (Throwable $e) {
    $pdo->prepare("UPDATE privileges SET {$colLocked}=1 WHERE kid_id=?")->execute([$kidId]);
    return;
  }

  $now = time();
  $newUntilTs = $now + ($minutes * 60);
  $newUntilIso = gb2_priv_iso_from_ts($newUntilTs);

  $row = gb2_priv_get_for_kid($kidId);
  $curUntilTs = gb2_priv_parse_until($row[$colUntil] ?? null);

  if ($curUntilTs >= $newUntilTs) {
    $pdo->prepare("UPDATE privileges SET {$colLocked}=1 WHERE kid_id=?")->execute([$kidId]);
    return;
  }

  $pdo->prepare("UPDATE privileges
                 SET {$colLocked}=1, {$colUntil}=?
                 WHERE kid_id=?")
      ->execute([$newUntilIso, $kidId]);
}

/**
 * Set an exact lock-until timestamp. Also sets the corresponding *_locked flag to 1.
 * Returns the until ISO actually written.
 */
function gb2_priv_set_lock_until(int $kidId, string $which, string $untilIso): string {
  if (!in_array($which, ['phone','games','other'], true)) {
    throw new InvalidArgumentException('Invalid lock type');
  }

  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();

  $colLocked = $which . "_locked";
  $colUntil  = $which . "_locked_until";

  try {
    $pdo->query("SELECT {$colUntil} FROM privileges LIMIT 1");
  } catch (Throwable $e) {
    // No until columns -> fallback to simple boolean lock
    $pdo->prepare("UPDATE privileges SET {$colLocked}=1 WHERE kid_id=?")->execute([$kidId]);
    return $untilIso;
  }

  $pdo->prepare("UPDATE privileges
                 SET {$colLocked}=1, {$colUntil}=?
                 WHERE kid_id=?")
      ->execute([$untilIso, $kidId]);

  return $untilIso;
}

/**
 * Add minutes onto the lock, stacking from max(now, current_until).
 * Also sets the corresponding *_locked flag to 1.
 *
 * Returns the computed until ISO.
 */
function gb2_priv_add_lock_minutes(int $kidId, string $which, int $minutes): string {
  if (!in_array($which, ['phone','games','other'], true)) {
    throw new InvalidArgumentException('Invalid lock type');
  }
  if ($minutes <= 0) {
    $row = gb2_priv_get_for_kid($kidId);
    return (string)($row[$which . "_locked_until"] ?? '');
  }

  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();

  $colLocked = $which . "_locked";
  $colUntil  = $which . "_locked_until";

  try {
    $pdo->query("SELECT {$colUntil} FROM privileges LIMIT 1");
  } catch (Throwable $e) {
    $pdo->prepare("UPDATE privileges SET {$colLocked}=1 WHERE kid_id=?")->execute([$kidId]);
    return gb2_priv_iso_from_ts(time() + ($minutes * 60));
  }

  $row = gb2_priv_get_for_kid($kidId);
  $curUntilTs = gb2_priv_parse_until($row[$colUntil] ?? null);
  $base = max(time(), $curUntilTs);
  $newUntilTs = $base + ($minutes * 60);
  $newUntilIso = gb2_priv_iso_from_ts($newUntilTs);

  $pdo->prepare("UPDATE privileges
                 SET {$colLocked}=1, {$colUntil}=?
                 WHERE kid_id=?")
      ->execute([$newUntilIso, $kidId]);

  return $newUntilIso;
}
