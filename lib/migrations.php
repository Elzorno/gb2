<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

/**
 * Lightweight, deterministic SQLite migrations using PRAGMA user_version.
 *
 * - Idempotent (checks table/columns before creating/altering)
 * - Transactional
 * - Recorded in repo as code (not external/manual steps)
 */

function gb2_db_user_version(PDO $pdo): int {
  $v = (int)($pdo->query("PRAGMA user_version;")->fetchColumn() ?: 0);
  return $v;
}

function gb2_db_set_user_version(PDO $pdo, int $v): void {
  $pdo->exec("PRAGMA user_version=" . (int)$v . ";");
}

/** Return true if table exists. */
function gb2_db_table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
  $st->execute([$table]);
  return (bool)$st->fetchColumn();
}

/** Return true if a column exists in a table. */
function gb2_db_column_exists(PDO $pdo, string $table, string $col): bool {
  if (!gb2_db_table_exists($pdo, $table)) return false;

  // PRAGMA table_info can't be parameterized; quote() is safe for identifiers in this limited context.
  $st = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
  $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

  foreach ($rows as $r) {
    if ((string)($r['name'] ?? '') === $col) return true;
  }
  return false;
}

/**
 * Apply migrations in order against the provided PDO.
 *
 * v1: add privileges lock-until columns.
 * v2: infractions (defs, strikes, events).
 */
function gb2_db_migrate(PDO $pdo): void {
  $pdo->exec("PRAGMA foreign_keys=ON;");
  $v = gb2_db_user_version($pdo);

  // --- v1: add *_locked_until columns to privileges ---
  if ($v < 1) {
    $pdo->beginTransaction();
    try {
      if (!gb2_db_column_exists($pdo, 'privileges', 'phone_locked_until')) {
        $pdo->exec("ALTER TABLE privileges ADD COLUMN phone_locked_until TEXT;");
      }
      if (!gb2_db_column_exists($pdo, 'privileges', 'games_locked_until')) {
        $pdo->exec("ALTER TABLE privileges ADD COLUMN games_locked_until TEXT;");
      }
      if (!gb2_db_column_exists($pdo, 'privileges', 'other_locked_until')) {
        $pdo->exec("ALTER TABLE privileges ADD COLUMN other_locked_until TEXT;");
      }

      gb2_db_set_user_version($pdo, 1);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
  }

  // --- v2: infractions tables ---
  if ($v < 2) {
    $pdo->beginTransaction();
    try {
      // Definitions
      $pdo->exec("
CREATE TABLE IF NOT EXISTS infraction_defs(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  mode TEXT NOT NULL DEFAULT 'set', -- 'set' or 'add'
  days INTEGER NOT NULL DEFAULT 0,
  ladder_json TEXT NOT NULL DEFAULT '[]',
  blocks_json TEXT NOT NULL DEFAULT '{\"phone\":1,\"games\":1,\"other\":0}',
  repairs_json TEXT NOT NULL DEFAULT '[]',
  review_days INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
");

      // Per-kid strike counters
      $pdo->exec("
CREATE TABLE IF NOT EXISTS infraction_strikes(
  kid_id INTEGER NOT NULL,
  infraction_def_id INTEGER NOT NULL,
  strike_count INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL,
  PRIMARY KEY(kid_id, infraction_def_id),
  FOREIGN KEY(kid_id) REFERENCES kids(id) ON DELETE CASCADE,
  FOREIGN KEY(infraction_def_id) REFERENCES infraction_defs(id) ON DELETE CASCADE
);
");

      // History / instances
      $pdo->exec("
CREATE TABLE IF NOT EXISTS infraction_events(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kid_id INTEGER NOT NULL,
  infraction_def_id INTEGER NOT NULL,
  ts TEXT NOT NULL,
  actor_type TEXT NOT NULL,
  actor_id INTEGER NOT NULL DEFAULT 0,
  strike_before INTEGER NOT NULL DEFAULT 0,
  strike_after INTEGER NOT NULL DEFAULT 0,
  days_applied INTEGER NOT NULL DEFAULT 0,
  mode TEXT NOT NULL,
  blocks_json TEXT NOT NULL,
  computed_until_json TEXT NOT NULL,
  review_on TEXT,
  note TEXT,
  FOREIGN KEY(kid_id) REFERENCES kids(id) ON DELETE CASCADE,
  FOREIGN KEY(infraction_def_id) REFERENCES infraction_defs(id) ON DELETE CASCADE
);
");

      // Useful indexes
      $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inf_events_kid_ts ON infraction_events(kid_id, ts);");
      $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inf_defs_active_sort ON infraction_defs(active, sort_order, label);");

      gb2_db_set_user_version($pdo, 2);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
  }
}
