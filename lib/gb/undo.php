<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store.php';

function gb_set_undo_snapshot(string $dataDir, array $kids, string $label = ''): void {
  gb_session_start();
  $_SESSION['gb_undo'] = ['ts'=>time(),'label'=>$label,'kids_json'=>gb_json_encode($kids)];
}

function gb_undo_available(int $seconds = 120): bool {
  gb_session_start();
  $u = $_SESSION['gb_undo'] ?? null;
  if (!is_array($u)) return false;
  $ts = (int)($u['ts'] ?? 0);
  return $ts > 0 && (time() - $ts) <= $seconds;
}

function gb_get_undo_label(): string {
  gb_session_start();
  $u = $_SESSION['gb_undo'] ?? [];
  return (string)($u['label'] ?? '');
}

function gb_apply_undo(string $dataDir): bool {
  gb_session_start();
  if (!gb_undo_available()) return false;
  $raw = (string)(($_SESSION['gb_undo']['kids_json'] ?? ''));
  $data = json_decode($raw, true);
  if (!is_array($data)) return false;
  return gb_save_kids($dataDir, $data);
}

function gb_clear_undo(): void {
  gb_session_start();
  unset($_SESSION['gb_undo']);
}
