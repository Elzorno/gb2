<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/bonuses.php';

gb2_db_init();
$kid = gb2_kid_require();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
gb2_csrf_verify();

$instanceId = (int)($_POST['instance_id'] ?? 0);
if ($instanceId <= 0) {
  header('Location: /app/bonuses.php?err=' . urlencode('Invalid bonus.'));
  exit;
}

$pdo = gb2_pdo();

// Load instance
$st = $pdo->prepare("SELECT * FROM bonus_instances WHERE id=?");
$st->execute([$instanceId]);
$bi = $st->fetch();

if (!$bi) {
  header('Location: /app/bonuses.php?err=' . urlencode('Bonus not found.'));
  exit;
}

if ((string)$bi['status'] !== 'available') {
  header('Location: /app/bonuses.php?err=' . urlencode('Bonus is not available.'));
  exit;
}

// Claim it (first-come)
$pdo->prepare("UPDATE bonus_instances
               SET status='claimed', claimed_by_kid=?, claimed_at=?
               WHERE id=? AND status='available'")
    ->execute([(int)$kid['kid_id'], gb2_now_iso(), $instanceId]);

if ($pdo->lastInsertId() === '0') {
  // sqlite lastInsertId isn't meaningful for UPDATE, so check rows instead
}
$changed = $pdo->query("SELECT changes() AS c")->fetch();
if ((int)($changed['c'] ?? 0) !== 1) {
  header('Location: /app/bonuses.php?err=' . urlencode('Someone else claimed it first.'));
  exit;
}

gb2_audit('kid', (int)$kid['kid_id'], 'claim_bonus', ['instance_id' => $instanceId]);

header('Location: /app/bonuses.php?ok=' . urlencode('Bonus claimed. Submit proof when done.'));
exit;
