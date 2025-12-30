<?php
/**
 * SQLite database helper.
 *
 * SQLite keeps deployment simple (works well on shared hosting) while giving us:
 * - atomic writes / transactions
 * - less tamper‑prone storage than ad-hoc JSON files
 * - straightforward auditing (events table)
 */

function gb_db_path(string $dataDir): string {
  return rtrim($dataDir, '/').'/groundingboard.sqlite';
}

function gb_db_exists(string $dataDir): bool {
  return file_exists(gb_db_path($dataDir));
}

function gb_pdo(string $dataDir): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $db = gb_db_path($dataDir);
  $pdo = new PDO('sqlite:'.$db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  // Pragmas (best effort)
  try { $pdo->exec('PRAGMA foreign_keys = ON;'); } catch (Throwable $e) {}
  try { $pdo->exec('PRAGMA journal_mode = WAL;'); } catch (Throwable $e) {}
  try { $pdo->exec('PRAGMA busy_timeout = 3000;'); } catch (Throwable $e) {}
  return $pdo;
}

function gb_db_init(string $dataDir): void {
  if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);
  if (!is_writable($dataDir)) {
    throw new RuntimeException('data/ is not writable.');
  }

  $pdo = gb_pdo($dataDir);

  $pdo->exec('CREATE TABLE IF NOT EXISTS gb_config (
    k TEXT PRIMARY KEY,
    v TEXT NOT NULL
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS gb_kids (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    grounded_start TEXT,
    grounded_until TEXT,
    reason TEXT,
    review_on TEXT,
    last_infraction TEXT,
    blocks_json TEXT,
    strikes_json TEXT
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS gb_infractions (
    id TEXT PRIMARY KEY,
    label TEXT NOT NULL,
    days INTEGER NOT NULL DEFAULT 0,
    mode TEXT NOT NULL DEFAULT "set",
    blocks_json TEXT,
    ladder_json TEXT,
    repairs_json TEXT,
    review_days INTEGER NOT NULL DEFAULT 0
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS gb_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    when_ts TEXT NOT NULL,
    kid TEXT NOT NULL,
    action TEXT NOT NULL,
    details_json TEXT
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS gb_auth (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    password_hash TEXT,
    pin_hash TEXT,
    created TEXT,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT
  );');

  // Ensure auth row exists.
  $pdo->exec('INSERT OR IGNORE INTO gb_auth (id, created) VALUES (1, datetime("now"));');
}
