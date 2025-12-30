<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/privileges.php';
require_once __DIR__ . '/../lib/audit.php';

gb2_db_init();
gb2_admin_require();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
gb2_csrf_verify();

$subId = (int)($_POST['sub_id'] ?? 0);
$decision = (string)($_POST['decision'] ?? '');
$note = trim((string)($_POST['note'] ?? ''));

if ($subId<=0 || !in_array($decision, ['approved','rejected'], true)) { header('Location: /admin/review.php'); exit; }

$pdo = gb2_pdo();
$st = $pdo->prepare("SELECT * FROM submissions WHERE id=?");
$st->execute([$subId]);
$sub = $st->fetch();
if (!$sub || (string)$sub['status'] !== 'pending') { header('Location: /admin/review.php'); exit; }

$pdo->prepare("UPDATE submissions SET status=?, reviewed_at=?, reviewed_by_admin=1, notes=? WHERE id=?")
    ->execute([$decision, gb2_now_iso(), $note, $subId]);

$kidId = (int)$sub['kid_id'];

if ($sub['kind'] === 'base') {
  $pdo->prepare("UPDATE assignments SET status=? WHERE day=? AND kid_id=?")
      ->execute([$decision, (string)$sub['day'], $kidId]);
} else {
  $instanceId = (int)$sub['bonus_instance_id'];
  if ($decision === 'approved') {
    $row = $pdo->prepare("SELECT bi.*, bd.reward_cents, bd.reward_phone_min, bd.reward_games_min
                          FROM bonus_instances bi
                          JOIN bonus_defs bd ON bd.id=bi.bonus_def_id
                          WHERE bi.id=?");
    $row->execute([$instanceId]);
    $bi = $row->fetch();
    if ($bi) {
      $pmin  = (int)$bi['reward_phone_min'];
      $gmin  = (int)$bi['reward_games_min'];
      if ($pmin > 0 || $gmin > 0) gb2_priv_apply_bonus($kidId, $pmin, $gmin);
    }
    $pdo->prepare("UPDATE bonus_instances SET status='approved' WHERE id=?")->execute([$instanceId]);
  } else {
    $pdo->prepare("UPDATE bonus_instances SET status='rejected' WHERE id=?")->execute([$instanceId]);
  }
}

gb2_audit('admin', 0, 'review_decision', ['sub_id'=>$subId,'decision'=>$decision,'kid_id'=>$kidId]);
header('Location: /admin/review.php');
