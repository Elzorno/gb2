<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/privileges.php';

gb2_db_init();
gb2_admin_require();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
gb2_csrf_verify();

$subId = (int)($_POST['sub_id'] ?? 0);
$decision = (string)($_POST['decision'] ?? '');
$note = trim((string)($_POST['note'] ?? ''));

if ($subId <= 0 || !in_array($decision, ['approved','rejected'], true)) {
  header('Location: /admin/review.php?err=' . urlencode('Invalid action.'));
  exit;
}

$pdo = gb2_pdo();

$st = $pdo->prepare("SELECT * FROM submissions WHERE id=?");
$st->execute([$subId]);
$sub = $st->fetch();

if (!$sub || (string)$sub['status'] !== 'pending') {
  header('Location: /admin/review.php?err=' . urlencode('Submission is no longer pending.'));
  exit;
}

$kidId = (int)($sub['kid_id'] ?? 0);
$kind  = (string)($sub['kind'] ?? '');

$pdo->beginTransaction();
try {
  // Update submission status
  $pdo->prepare("UPDATE submissions
                 SET status=?, reviewed_at=?, reviewed_by_admin=1, notes=?
                 WHERE id=?")
      ->execute([$decision, gb2_now_iso(), ($note === '' ? null : $note), $subId]);

  // Apply rewards only on approve
  if ($decision === 'approved') {
    if ($kind === 'bonus') {
      // Mark instance approved
      $instanceId = (int)($sub['bonus_instance_id'] ?? 0);
      if ($instanceId > 0) {
        $pdo->prepare("UPDATE bonus_instances SET status='approved' WHERE id=?")->execute([$instanceId]);

        // Find rewards for this bonus instance
        $st2 = $pdo->prepare(
          "SELECT bd.reward_phone_min, bd.reward_games_min
           FROM bonus_instances bi
           JOIN bonus_defs bd ON bd.id = bi.bonus_def_id
           WHERE bi.id=?"
        );
        $st2->execute([$instanceId]);
        $rw = $st2->fetch();

        $phoneMin = (int)($rw['reward_phone_min'] ?? 0);
        $gamesMin = (int)($rw['reward_games_min'] ?? 0);

        if ($kidId > 0 && ($phoneMin > 0 || $gamesMin > 0)) {
          gb2_priv_apply_bonus($kidId, $phoneMin, $gamesMin);
        }
      }
    }

    if ($kind === 'base') {
      // Base chores: mark assignment approved
      $day = (string)($sub['day'] ?? '');
      if ($day !== '' && $kidId > 0) {
        $pdo->prepare("UPDATE assignments SET status='approved' WHERE day=? AND kid_id=?")
            ->execute([$day, $kidId]);
      }
    }
  } else {
    // rejected
    if ($kind === 'bonus') {
      $instanceId = (int)($sub['bonus_instance_id'] ?? 0);
      if ($instanceId > 0) {
        // Back to claimed so kid can resubmit proof
        $pdo->prepare("UPDATE bonus_instances SET status='claimed' WHERE id=?")->execute([$instanceId]);
      }
    }
    if ($kind === 'base') {
      $day = (string)($sub['day'] ?? '');
      if ($day !== '' && $kidId > 0) {
        // Back to open so kid can resubmit proof
        $pdo->prepare("UPDATE assignments SET status='open', submission_id=NULL WHERE day=? AND kid_id=?")
            ->execute([$day, $kidId]);
      }
    }
  }

  gb2_audit('admin', 0, 'review_submission', [
    'sub_id' => $subId,
    'decision' => $decision,
    'kid_id' => $kidId,
    'kind' => $kind,
  ]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  header('Location: /admin/review.php?err=' . urlencode('Save failed.'));
  exit;
}

$msg = ($decision === 'approved') ? 'Approved.' : 'Rejected.';
header('Location: /admin/review.php?ok=' . urlencode($msg));
exit;
