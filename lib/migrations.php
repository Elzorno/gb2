<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

/**
 * Lightweight, deterministic SQLite migrations using PRAGMA user_version.
 *
 * - Idempotent (checks columns before ALTER TABLE)
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
  // sqlite treats quoted string as identifier in PRAGMA table_info('table').
  $st = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
  $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

  foreach ($rows as $r) {
    if ((string)($r['name'] ?? '') === $col) return true;
  }
  return false;
}

/**
 * Apply migrations in order against the provided PDO.
 * Current plan: v1 adds privileges lock-until columns.
 */
function gb2_db_migrate(PDO $pdo): void {
  $pdo->exec("PRAGMA foreign_keys=ON;");
  $v = gb2_db_user_version($pdo);

  // --- v1: add *_locked_until columns to privileges ---
  if ($v < 1) {
    $pdo->beginTransaction();
    try {
      // Add columns only if missing (safe on existing installs)
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
}
