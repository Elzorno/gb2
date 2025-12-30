<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

function gb2_db_path(): string {
  $dir = gb2_data_dir();
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return rtrim($dir,'/') . '/app.sqlite';
}

function gb2_pdo(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $db = gb2_db_path();
  $pdo = new PDO('sqlite:' . $db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec("PRAGMA foreign_keys=ON;");
  return $pdo;
}

function gb2_db_init(): void {
  $pdo = gb2_pdo();
  $pdo->exec("
CREATE TABLE IF NOT EXISTS kids(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  pin_hash TEXT NOT NULL DEFAULT '',
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS kid_devices(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kid_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  last_seen TEXT NOT NULL,
  revoked_at TEXT,
  ip TEXT,
  ua TEXT,
  FOREIGN KEY(kid_id) REFERENCES kids(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS chore_slots(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS rotation_rules(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  kids_json TEXT NOT NULL,
  slots_json TEXT NOT NULL,
  anchor_monday TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS assignments(
  day TEXT NOT NULL,
  kid_id INTEGER NOT NULL,
  slot_id INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'open',
  submission_id INTEGER,
  PRIMARY KEY(day,kid_id),
  FOREIGN KEY(kid_id) REFERENCES kids(id) ON DELETE CASCADE,
  FOREIGN KEY(slot_id) REFERENCES chore_slots(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS submissions(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT NOT NULL,
  day TEXT,
  week_start TEXT,
  kid_id INTEGER NOT NULL,
  slot_id INTEGER,
  bonus_instance_id INTEGER,
  photo_path TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  submitted_at TEXT NOT NULL,
  reviewed_at TEXT,
  reviewed_by_admin INTEGER NOT NULL DEFAULT 0,
  notes TEXT,
  FOREIGN KEY(kid_id) REFERENCES kids(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS bonus_defs(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  reward_cents INTEGER NOT NULL DEFAULT 0,
  reward_phone_min INTEGER NOT NULL DEFAULT 0,
  reward_games_min INTEGER NOT NULL DEFAULT 0,
  max_per_week INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS bonus_instances(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  week_start TEXT NOT NULL,
  bonus_def_id INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'available',
  claimed_by_kid INTEGER,
  claimed_at TEXT,
  submission_id INTEGER,
  FOREIGN KEY(bonus_def_id) REFERENCES bonus_defs(id) ON DELETE CASCADE,
  FOREIGN KEY(claimed_by_kid) REFERENCES kids(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS privileges(
  kid_id INTEGER PRIMARY KEY,
  phone_locked INTEGER NOT NULL DEFAULT 0,
  games_locked INTEGER NOT NULL DEFAULT 0,
  other_locked INTEGER NOT NULL DEFAULT 0,
  bank_phone_min INTEGER NOT NULL DEFAULT 0,
  bank_games_min INTEGER NOT NULL DEFAULT 0,
  bank_other_min INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY(kid_id) REFERENCES kids(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS ledger(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kid_id INTEGER NOT NULL,
  ts TEXT NOT NULL,
  kind TEXT NOT NULL,
  amount_cents INTEGER NOT NULL DEFAULT 0,
  phone_min INTEGER NOT NULL DEFAULT 0,
  games_min INTEGER NOT NULL DEFAULT 0,
  note TEXT,
  ref TEXT,
  FOREIGN KEY(kid_id) REFERENCES kids(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS audit(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ts TEXT NOT NULL,
  actor_type TEXT NOT NULL,
  actor_id INTEGER NOT NULL DEFAULT 0,
  action TEXT NOT NULL,
  details_json TEXT,
  ip TEXT,
  ua TEXT
);
");
}
