<?php
require_once __DIR__ . '/store.php';

/**
 * Append-only audit log. (Phase 5: stored in SQLite)
 */
function gb_log_event(string $dataDir, string $kid, string $action, array $details = []): void {
  gb_log_event_db($dataDir, $kid, $action, $details);
}
