<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/db.php';

gb2_db_init();
$kid = gb2_kid_require();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
gb2_csrf_verify();

$instanceId = (int)($_POST['instance_id'] ?? 0);
if ($instanceId <= 0) { header('Location: /app/bonuses.php'); exit; }

$ok = gb2_bonus_claim((int)$kid['kid_id'], $instanceId);
gb2_audit('kid', (int)$kid['kid_id'], 'bonus_claim', ['instance_id'=>$instanceId,'ok'=>$ok]);
header('Location: /app/bonuses.php');
