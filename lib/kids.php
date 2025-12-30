<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';

function gb2_kids_all(): array {
  $pdo = gb2_pdo();
  return $pdo->query("SELECT id,name,sort_order FROM kids ORDER BY sort_order ASC, name ASC")->fetchAll();
}

function gb2_kid_set_pin(int $kidId, string $pin): void {
  if (!preg_match('/^[0-9]+$/', $pin)) throw new RuntimeException("PIN must be numeric.");
  $hash = password_hash($pin, PASSWORD_ARGON2ID);
  $pdo = gb2_pdo();
  $pdo->prepare("UPDATE kids SET pin_hash=? WHERE id=?")->execute([$hash, $kidId]);
}

function gb2_kid_create(string $name, int $sortOrder=0): int {
  $pdo = gb2_pdo();
  $pdo->prepare("INSERT INTO kids(name,pin_hash,sort_order,created_at) VALUES(?,?,?,?)")
      ->execute([$name, '', $sortOrder, gb2_now_iso()]);
  $id = (int)$pdo->lastInsertId();
  $pdo->prepare("INSERT OR IGNORE INTO privileges(kid_id) VALUES(?)")->execute([$id]);
  return $id;
}
