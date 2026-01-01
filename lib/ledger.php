<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';

/**
 * Ledger helpers.
 *
 * Ledger is used for:
 *  - Bonus earnings (cash rewards) when a bonus submission is approved
 *  - (Optional) other money/time adjustments in future
 *
 * Schema is created in lib/db.php (CREATE TABLE IF NOT EXISTS ledger ...).
 */

function gb2_ledger_add(
  int $kidId,
  string $kind,
  int $amountCents = 0,
  int $phoneMin = 0,
  int $gamesMin = 0,
  string $note = '',
  string $ref = ''
): void {
  gb2_db_init();
  $pdo = gb2_pdo();

  $st = $pdo->prepare(
    "INSERT INTO ledger(kid_id, ts, kind, amount_cents, phone_min, games_min, note, ref)
     VALUES(?,?,?,?,?,?,?,?)"
  );
  $st->execute([
    $kidId,
    gb2_now_iso(),
    $kind,
    $amountCents,
    $phoneMin,
    $gamesMin,
    $note !== '' ? $note : null,
    $ref !== '' ? $ref : null,
  ]);
}

/** Sum amount_cents for a kid. Optionally filter by kind and/or since (inclusive ISO or Y-m-d). */
function gb2_ledger_sum_cents_for_kid(int $kidId, ?string $kind = null, ?string $since = null): int {
  gb2_db_init();
  $pdo = gb2_pdo();

  $where = "kid_id=?";
  $args = [$kidId];

  if ($kind !== null && $kind !== '') {
    $where .= " AND kind=?";
    $args[] = $kind;
  }

  if ($since !== null && $since !== '') {
    // Accept YYYY-mm-dd or full ISO. Compare lexicographically (ISO format).
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
      $since = $since . "T00:00:00+00:00";
    }
    $where .= " AND ts >= ?";
    $args[] = $since;
  }

  $st = $pdo->prepare("SELECT COALESCE(SUM(amount_cents),0) AS s FROM ledger WHERE {$where}");
  $st->execute($args);
  return (int)($st->fetchColumn() ?? 0);
}

/** List recent ledger rows for a kid (most recent first). */
function gb2_ledger_list_for_kid(int $kidId, int $limit = 25, ?string $kind = null): array {
  gb2_db_init();
  $pdo = gb2_pdo();

  $limit = max(1, min(200, $limit));
  $args = [$kidId];

  $where = "kid_id=?";
  if ($kind !== null && $kind !== '') {
    $where .= " AND kind=?";
    $args[] = $kind;
  }

  $st = $pdo->prepare(
    "SELECT id, ts, kind, amount_cents, phone_min, games_min, note, ref
     FROM ledger
     WHERE {$where}
     ORDER BY ts DESC, id DESC
     LIMIT {$limit}"
  );
  $st->execute($args);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Format cents to dollars like $1.23 (supports negative). */
function gb2_money(int $cents): string {
  $neg = $cents < 0;
  $cents = abs($cents);
  $dollars = intdiv($cents, 100);
  $rem = $cents % 100;
  return ($neg ? '-' : '') . '$' . $dollars . '.' . str_pad((string)$rem, 2, '0', STR_PAD_LEFT);
}
