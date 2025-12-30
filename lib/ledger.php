<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Minimal ledger implementation.
 * Used when approving bonus submissions (money/time rewards).
 * Safe for base submissions (approve.php requires this file unconditionally).
 */

function gb2_ledger_init(PDO $pdo): void {
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS ledger(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      kid_id INTEGER NOT NULL,
      kind TEXT NOT NULL,
      cents INTEGER NOT NULL DEFAULT 0,
      phone_min INTEGER NOT NULL DEFAULT 0,
      games_min INTEGER NOT NULL DEFAULT 0,
      memo TEXT,
      ref TEXT,
      created_at TEXT NOT NULL
    )"
  );
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ledger_kid_time ON ledger(kid_id, created_at)");
}

function gb2_ledger_add(
  int $kidId,
  string $kind,
  int $cents,
  int $phoneMin,
  int $gamesMin,
  string $memo = '',
  string $ref = ''
): void {
  $pdo = gb2_pdo();
  gb2_ledger_init($pdo);

  $st = $pdo->prepare(
    "INSERT INTO ledger(kid_id, kind, cents, phone_min, games_min, memo, ref, created_at)
     VALUES(?,?,?,?,?,?,?,?)"
  );
  $st->execute([
    $kidId,
    $kind,
    $cents,
    $phoneMin,
    $gamesMin,
    $memo,
    $ref,
    gb2_now_iso(),
  ]);
}
