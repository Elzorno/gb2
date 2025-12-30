<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function gb2_priv_apply_bonus(int $kidId, int $phoneMin, int $gamesMin): void {
  $pdo = gb2_pdo();
  $pdo->prepare("UPDATE privileges
                 SET bank_phone_min = bank_phone_min + ?,
                     bank_games_min = bank_games_min + ?
                 WHERE kid_id=?")
      ->execute([$phoneMin, $gamesMin, $kidId]);
}
