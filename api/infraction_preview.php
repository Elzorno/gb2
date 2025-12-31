<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/infractions.php';

gb2_db_init();
gb2_admin_require();

header('Content-Type: application/json; charset=utf-8');

$kidId = (int)($_GET['kid_id'] ?? 0);
$defId = (int)($_GET['def_id'] ?? 0);

if ($kidId <= 0 || $defId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing kid_id or def_id']);
  exit;
}

try {
  $p = gb2_inf_preview($kidId, $defId);

  $def = $p['def'];
  $label = (string)($def['label'] ?? '');
  $code  = (string)($def['code'] ?? '');

  echo json_encode([
    'ok' => true,
    'kid_id' => $kidId,
    'def_id' => $defId,
    'label' => $label,
    'code' => $code,
    'strike_before' => (int)$p['strike_before'],
    'strike_after' => (int)$p['strike_after'],
    'days_applied' => (int)$p['days_applied'],
    'mode' => (string)$p['mode'],
    'blocks' => $p['blocks'],
    'computed_until' => $p['computed_until'],
    'review_on' => $p['review_on'],
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
