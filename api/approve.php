<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/common.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/ledger.php';
require_once __DIR__ . '/../lib/privileges.php';

gb2_db_init();
gb2_admin_require();

function gb2_approve_log(string $msg): void {
  $dir = gb2_data_dir();
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  @file_put_contents($dir . '/approve_fail.log', '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
}

function gb2_try_delete_submission_photo(array $sub): void {
  $photo = (string)($sub['photo_path'] ?? '');
  if ($photo === '' || $photo === 'uploads/NO_PHOTO') return;

  $dataDir = rtrim(gb2_data_dir(), '/');
  $abs = $dataDir . '/' . ltrim($photo, '/');

  // Safety: ensure path stays under dataDir
  $realData = @realpath($dataDir);
  $realAbs  = @realpath($abs);
  if (!$realData || !$realAbs) {
    // If file doesn't exist, nothing to do.
    return;
  }
  if (strpos($realAbs, $realData . DIRECTORY_SEPARATOR) !== 0) {
    gb2_approve_log('photo_delete_refused path=' . $abs);
    return;
  }

  if (is_file($realAbs)) {
    if (!@unlink($realAbs)) {
      gb2_approve_log('photo_delete_failed path=' . $realAbs);
    }
  }
}

function gb2_bonus_instance_info(PDO $pdo, string $weekStart, int $instanceId): ?array {
  $st = $pdo->prepare("
    SELECT
      bi.id            AS instance_id,
      bi.week_start    AS week_start,
      bi.status        AS status,
      bi.claimed_by_kid AS claimed_by_kid,
      bi.submission_id AS submission_id,
      bd.id            AS bonus_def_id,
      bd.title         AS title,
      bd.reward_cents  AS reward_cents,
      bd.reward_phone_min AS reward_phone_min,
      bd.reward_games_min AS reward_games_min
    FROM bonus_instances bi
    JOIN bonus_defs bd ON bd.id = bi.bonus_def_id
    WHERE bi.week_start = ?
      AND bi.id = ?
    LIMIT 1
  ");
  $st->execute([$weekStart, $instanceId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function gb2_try_delete_photo(string $photoPath): void {
  if ($photoPath === '' || $photoPath === 'uploads/NO_PHOTO') return;

  $dataDir = rtrim(gb2_data_dir(), '/');
  $rel = ltrim($photoPath, '/');

  // Only allow deletes under uploads/
  if (strpos($rel, 'uploads/') !== 0) {
    gb2_approve_log('skip_delete_non_uploads photo_path=' . $photoPath);
    return;
  }

  $abs = $dataDir . '/' . $rel;

  // Realpath safety: ensure abs resolves under dataDir/uploads
  $uploadsDir = realpath($dataDir . '/uploads');
  $absReal = realpath($abs);
  if ($uploadsDir === false || $absReal === false) return;
  if (strpos($absReal, $uploadsDir) !== 0) {
    gb2_approve_log('skip_delete_outside_uploads abs=' . $absReal);
    return;
  }

  if (is_file($absReal)) {
    if (!@unlink($absReal)) {
      gb2_approve_log('delete_failed abs=' . $absReal);
    }
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Location: /admin/review.php');
  exit;
}

// CSRF
try {
  gb2_csrf_verify();
} catch (Throwable $e) {
  gb2_approve_log('csrf_fail ip=' . gb2_client_ip() . ' ua=' . gb2_user_agent() . ' err=' . $e->getMessage());
  header('Location: /admin/review.php?err=' . urlencode('Security check failed.'));
  exit;
}

// Inputs (match review.php)
$subId    = (int)($_POST['sub_id'] ?? 0);
$decision = (string)($_POST['decision'] ?? '');
$noteRaw  = (string)($_POST['note'] ?? '');
$note     = trim(substr($noteRaw, 0, 500));

if ($subId <= 0 || ($decision !== 'approved' && $decision !== 'rejected')) {
  gb2_approve_log('invalid_input sub_id=' . $subId . ' decision=' . $decision . ' post_keys=' . implode(',', array_keys($_POST)));
  header('Location: /admin/review.php?err=' . urlencode('Invalid submission.'));
  exit;
}

$pdo = gb2_pdo();
$photoPathToDelete = '';

try {
  $pdo->beginTransaction();

  $st = $pdo->prepare("SELECT * FROM submissions WHERE id=?");
  $st->execute([$subId]);
  $sub = $st->fetch(PDO::FETCH_ASSOC);

  if (!$sub) throw new RuntimeException('Submission not found id=' . $subId);

  $kind   = (string)($sub['kind'] ?? '');
  $status = (string)($sub['status'] ?? '');
  $kidId  = (int)($sub['kid_id'] ?? 0);

  $photoPathToDelete = (string)($sub['photo_path'] ?? '');

  if ($kidId <= 0) throw new RuntimeException('Invalid kid_id on submission id=' . $subId);
  if ($status !== 'pending') throw new RuntimeException('Not pending id=' . $subId . ' status=' . $status);

  // Update submission status + review metadata
  $pdo->prepare("UPDATE submissions
                   SET status=?,
                       reviewed_at=?,
                       reviewed_by_admin=1,
                       notes=?
                 WHERE id=?")
      ->execute([$decision, gb2_now_iso(), ($note === '' ? null : $note), $subId]);

  if ($kind === 'base') {
    if ($decision === 'approved') {
      $pdo->prepare("UPDATE assignments SET status='done' WHERE submission_id=?")
          ->execute([$subId]);
    } else {
      $pdo->prepare("UPDATE assignments
                       SET status='open', submission_id=NULL
                     WHERE submission_id=?")
          ->execute([$subId]);
    }

  } elseif ($kind === 'bonus') {
    if ($decision === 'approved') {
      $pdo->prepare("UPDATE bonus_instances SET status='approved' WHERE submission_id=?")
          ->execute([$subId]);

      $weekStart  = (string)($sub['week_start'] ?? '');
      $instanceId = (int)($sub['bonus_instance_id'] ?? 0);

      if ($weekStart !== '' && $instanceId > 0) {
        $info = gb2_bonus_instance_info($pdo, $weekStart, $instanceId);
        if ($info) {
          $rewardCents = (int)($info['reward_cents'] ?? 0);
          $rewardPhone = (int)($info['reward_phone_min'] ?? 0);
          $rewardGames = (int)($info['reward_games_min'] ?? 0);

          if ($rewardCents !== 0) {
            gb2_ledger_add(
              $kidId,
              'bonus_reward',
              $rewardCents,
              0,
              0,
              (string)($info['title'] ?? 'Bonus'),
              'bonus:' . $instanceId
            );
          }

          if ($rewardPhone !== 0 || $rewardGames !== 0) {
            gb2_priv_apply_bonus($kidId, $rewardPhone, $rewardGames);
          }
        } else {
          gb2_approve_log('bonus_info_missing sub_id=' . $subId . ' week_start=' . $weekStart . ' instance_id=' . $instanceId);
        }
      }

    } else {
      $pdo->prepare("UPDATE bonus_instances
                       SET status='claimed', submission_id=NULL
                     WHERE submission_id=?")
          ->execute([$subId]);
    }

  } else {
    throw new RuntimeException('Unknown kind=' . $kind . ' sub_id=' . $subId);
  }

  gb2_audit('admin', 0, 'review_submission', [
    'sub_id'   => $subId,
    'kid_id'   => $kidId,
    'kind'     => $kind,
    'decision' => $decision,
    'has_note' => ($note !== ''),
  ]);

  $pdo->commit();
  // Delete uploaded photo after final decision (approve or reject)
  gb2_try_delete_submission_photo($sub);


} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  gb2_approve_log('review_failed sub_id=' . $subId . ' decision=' . $decision . ' err=' . $e->getMessage());
  header('Location: /admin/review.php?err=' . urlencode('Approve failed.'));
  exit;
}

// After DB commit: delete the photo (best effort; never blocks the approval flow)
try {
  gb2_try_delete_photo($photoPathToDelete);
} catch (Throwable $e) {
  gb2_approve_log('delete_exception sub_id=' . $subId . ' err=' . $e->getMessage());
}

header('Location: /admin/review.php?ok=' . urlencode($decision === 'approved' ? 'Approved.' : 'Rejected.'));
exit;
