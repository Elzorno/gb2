<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/ledger.php';
require_once __DIR__ . '/../lib/privileges.php';
require_once __DIR__ . '/../lib/audit.php';

gb2_db_init();
gb2_admin_require();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
gb2_csrf_verify();

$subId = (int)($_POST['sub_id'] ?? 0);
$decision = (string)($_POST['decision'] ?? '');
$note = trim((string)($_POST['note'] ?? ''));

if ($subId <= 0 || !in_array($decision, ['approved','rejected'], true)) {
  header('Location: /admin/review.php?err=' . urlencode('Invalid request.'));
  exit;
}

$pdo = gb2_pdo();

$st = $pdo->prepare("SELECT * FROM submissions WHERE id=?");
$st->execute([$subId]);
$sub = $st->fetch(PDO::FETCH_ASSOC);

if (!$sub || (string)$sub['status'] !== 'pending') {
  header('Location: /admin/review.php?err=' . urlencode('Already handled.'));
  exit;
}

$kidId = (int)$sub['kid_id'];

$pdo->beginTransaction();
try {
  $pdo->prepare("UPDATE submissions SET status=?, reviewed_at=?, reviewed_by_admin=1, notes=? WHERE id=?")
      ->execute([$decision, gb2_now_iso(), $note, $subId]);

  if ($decision === 'rejected') {
    if ((string)$sub['kind'] === 'base') {
      $pdo->prepare("UPDATE assignments SET status='open', submission_id=NULL WHERE submission_id=?")
          ->execute([$subId]);
    } elseif ((string)$sub['kind'] === 'bonus') {
      $pdo->prepare("UPDATE bonus_instances SET status='claimed', submission_id=NULL WHERE submission_id=?")
          ->execute([$subId]);
    }
    gb2_audit('admin', 0, 'reject_submission', ['sub_id'=>$subId,'kid_id'=>$kidId,'kind'=>$sub['kind']]);
    $pdo->commit();
    header('Location: /admin/review.php?ok=' . urlencode('Rejected.'));
    exit;
  }

  // approved
  if ((string)$sub['kind'] === 'base') {
    $pdo->prepare("UPDATE assignments SET status='done' WHERE submission_id=?")
        ->execute([$subId]);
  } elseif ((string)$sub['kind'] === 'bonus') {
    $pdo->prepare("UPDATE bonus_instances SET status='done' WHERE submission_id=?")
        ->execute([$subId]);

    $weekStart = (string)($sub['week_start'] ?? '');
    $instanceId = (int)($sub['bonus_instance_id'] ?? 0);
    if ($weekStart !== '' && $instanceId > 0) {
      $info = gb2_bonus_instance_with_def($weekStart, $instanceId);
      if ($info) {
        $rewardCents = (int)($info['reward_cents'] ?? 0);
        $rewardPhone = (int)($info['reward_phone_min'] ?? 0);
        $rewardGames = (int)($info['reward_games_min'] ?? 0);

        if ($rewardCents !== 0) {
          gb2_ledger_add($kidId, 'bonus_reward', $rewardCents, 0, 0, (string)($info['title'] ?? 'Bonus'), 'bonus:' . $instanceId);
        }
        if ($rewardPhone !== 0 || $rewardGames !== 0) {
          gb2_priv_apply_bonus($kidId, $rewardPhone, $rewardGames);
        }
      }
    }
  }

  gb2_audit('admin', 0, 'approve_submission', ['sub_id'=>$subId,'kid_id'=>$kidId,'kind'=>$sub['kind']]);
  $pdo->commit();
  header('Location: /admin/review.php?ok=' . urlencode('Approved.'));
  exit;
} catch (Throwable $e) {
  $pdo->rollBack();
  header('Location: /admin/review.php?err=' . urlencode('Approve failed.'));
  exit;
}
