<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rotation.php';
require_once __DIR__ . '/common.php';

function gb2_bonus_week_start(string $dayYmd): string {
  $d = DateTimeImmutable::createFromFormat('Y-m-d', $dayYmd) ?: new DateTimeImmutable('today');
  return gb2_week_start_monday($d)->format('Y-m-d');
}

function gb2_bonus_reset_week(string $weekStartYmd): void {
  gb2_db_init();
  $pdo = gb2_pdo();
  gb2_bonus_ensure_week_instances($pdo, $weekStartYmd);
}

/**
 * Ensure a week has the configured number of instances per active bonus.
 * Uses an IMMEDIATE transaction so concurrent requests cannot double-insert.
 */
function gb2_bonus_ensure_week_instances(PDO $pdo, string $weekStartYmd): void {
  $pdo->exec('BEGIN IMMEDIATE');
  try {
    $defs = $pdo->query("SELECT id, max_per_week FROM bonus_defs WHERE active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    $countSt = $pdo->prepare("SELECT COUNT(*) FROM bonus_instances WHERE week_start=? AND bonus_def_id=?");
    $insertSt = $pdo->prepare("INSERT INTO bonus_instances(week_start,bonus_def_id,status) VALUES(?,?,?)");

    foreach ($defs as $def) {
      $defId = (int)$def['id'];
      $max = max(1, (int)$def['max_per_week']);

      $countSt->execute([$weekStartYmd, $defId]);
      $have = (int)$countSt->fetchColumn();
      $missing = $max - $have;
      if ($missing <= 0) {
        continue;
      }

      for ($i = 0; $i < $missing; $i++) {
        $insertSt->execute([$weekStartYmd, $defId, 'available']);
      }
    }

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function gb2_bonus_list_week(string $weekStartYmd): array {
  $pdo = gb2_pdo();
  $st = $pdo->prepare("
    SELECT bi.id as instance_id, bi.week_start, bi.status, bi.claimed_by_kid, bi.claimed_at,
           bd.id as def_id, bd.title, bd.reward_cents, bd.reward_phone_min, bd.reward_games_min
    FROM bonus_instances bi
    JOIN bonus_defs bd ON bd.id=bi.bonus_def_id
    WHERE bi.week_start=?
    ORDER BY bd.sort_order ASC, bd.id ASC, bi.id ASC
  ");
  $st->execute([$weekStartYmd]);
  return $st->fetchAll();
}

function gb2_bonus_claim(int $kidId, int $instanceId): bool {
  $pdo = gb2_pdo();
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT * FROM bonus_instances WHERE id=?");
    $st->execute([$instanceId]);
    $bi = $st->fetch();
    if (!$bi || (string)$bi['status'] !== 'available') {
      $pdo->rollBack();
      return false;
    }
    $pdo->prepare("UPDATE bonus_instances
                   SET status='claimed', claimed_by_kid=?, claimed_at=?
                   WHERE id=? AND status='available'")
        ->execute([$kidId, gb2_now_iso(), $instanceId]);
    $pdo->commit();
    return true;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}
