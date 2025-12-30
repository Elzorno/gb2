<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';

function gb2_audit(string $actorType, int $actorId, string $action, array $details = []): void {
  $pdo = gb2_pdo();
  $pdo->prepare("INSERT INTO audit(ts,actor_type,actor_id,action,details_json,ip,ua)
                 VALUES(?,?,?,?,?,?,?)")
      ->execute([gb2_now_iso(), $actorType, $actorId, $action, json_encode($details), gb2_client_ip(), gb2_user_agent()]);
}
