<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/audit.php';

gb2_db_init();
$kid = gb2_kid_require();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
gb2_csrf_verify();

$instanceId = (int)($_POST['instance_id'] ?? 0);
if ($instanceId <= 0) {
  header('Location: /app/bonuses.php?err=' . urlencode('Invalid bonus.'));
  exit;
}

try {
  $ok = gb2_bonus_claim((int)$kid['kid_id'], $instanceId);
  if ($ok) {
    gb2_audit('kid', (int)$kid['kid_id'], 'claim_bonus', ['instance_id'=>$instanceId]);
    header('Location: /app/bonuses.php?ok=' . urlencode('Claimed. Upload proof when ready.'));
    exit;
  }
  header('Location: /app/bonuses.php?err=' . urlencode('That bonus is not available.'));
  exit;
} catch (Throwable $e) {
  header('Location: /app/bonuses.php?err=' . urlencode('Claim failed.'));
  exit;
}
