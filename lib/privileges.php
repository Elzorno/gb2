<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function gb2_priv_ensure_row(int $kidId): void {
  $pdo = gb2_pdo();
  $pdo->prepare("INSERT OR IGNORE INTO privileges(kid_id) VALUES(?)")
      ->execute([$kidId]);
}

function gb2_priv_get_for_kid(int $kidId): array {
  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();
  $st = $pdo->prepare("SELECT * FROM privileges WHERE kid_id=?");
  $st->execute([$kidId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: [
    'kid_id'=>$kidId,
    'phone_locked'=>0,'games_locked'=>0,'other_locked'=>0,
    'bank_phone_min'=>0,'bank_games_min'=>0,'bank_other_min'=>0
  ];
}

function gb2_priv_set_locks(int $kidId, int $phoneLocked, int $gamesLocked, int $otherLocked): void {
  gb2_priv_ensure_row($kidId);
  $pdo = gb2_pdo();
  $pdo->prepare("UPDATE privileges
                 SET phone_locked=?, games_locked=?, other_locked=?
                 WHERE kid_id=?")
      ->execute([$phoneLocked, $gamesLocked, $otherLocked, $kidId]);
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
