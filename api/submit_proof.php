<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/audit.php';

gb2_db_init();
$kid = gb2_kid_require();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
gb2_csrf_verify();

$cfg = gb2_config();
$maxBytes = (int)($cfg['uploads']['max_bytes'] ?? (7*1024*1024));
$noPhoto  = ((string)($_POST['no_photo'] ?? '')) === '1';

$dataDir = gb2_data_dir();
$photoRel = '';   // value stored in DB (relative under /data)

function gb2_pick_upload_field(): string {
  // Accept multiple field names for iPhone reliability
  foreach (['photo', 'photo_camera', 'photo_library'] as $k) {
    if (!empty($_FILES[$k]) && (int)($_FILES[$k]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      return $k;
    }
  }
  return 'photo';
}

if ($noPhoto) {
  // Sentinel: no file exists, but schema requires a value.
  $photoRel = 'uploads/NO_PHOTO';
} else {
  $field = gb2_pick_upload_field();

  if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400); echo "Upload failed."; exit;
  }
  if ((int)($_FILES[$field]['size'] ?? 0) > $maxBytes) { http_response_code(413); echo "File too large."; exit; }

  $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
  if ($tmp === '' || !is_file($tmp)) { http_response_code(400); echo "Upload failed."; exit; }

  $mime = '';
  if (class_exists('finfo')) {
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$fi->file($tmp);
  }
  if (!$mime) $mime = (string)(mime_content_type($tmp) ?: '');
  if (!in_array($mime, ['image/jpeg','image/png','image/heic','image/heif','image/webp'], true)) {
    http_response_code(400); echo "Unsupported image type."; exit;
  }

  $upDir = rtrim($dataDir,'/') . '/uploads/' . date('Y') . '/' . date('m');
  @mkdir($upDir, 0775, true);

  $ext = 'jpg';
  if ($mime === 'image/png') $ext='png';
  elseif ($mime === 'image/webp') $ext='webp';
  elseif (in_array($mime, ['image/heic','image/heif'], true)) $ext='heic';

  $fname = bin2hex(random_bytes(16)) . '.' . $ext;
  $destAbs = $upDir . '/' . $fname;

  if (!move_uploaded_file($tmp, $destAbs)) { http_response_code(500); echo "Save failed."; exit; }

  $photoRel = ltrim(str_replace(rtrim($dataDir,'/').'/', '', $destAbs), '/');
}

$kind = (string)($_POST['kind'] ?? '');
$pdo = gb2_pdo();

if ($kind === 'base') {
  $day = (string)($_POST['day'] ?? '');
  $slotId = (int)($_POST['slot_id'] ?? 0);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $slotId <= 0) { http_response_code(400); exit; }

  $pdo->prepare("INSERT INTO submissions(kind,day,kid_id,slot_id,photo_path,status,submitted_at)
                 VALUES('base',?,?,?,?, 'pending', ?)")
      ->execute([$day, (int)$kid['kid_id'], $slotId, $photoRel, gb2_now_iso()]);
  $subId = (int)$pdo->lastInsertId();

  $pdo->prepare("UPDATE assignments SET status='pending', submission_id=? WHERE day=? AND kid_id=?")
      ->execute([$subId, $day, (int)$kid['kid_id']]);

  gb2_audit('kid', (int)$kid['kid_id'], $noPhoto ? 'submit_base_no_photo' : 'submit_base_proof',
    ['day'=>$day,'slot_id'=>$slotId,'sub_id'=>$subId]);

  header('Location: /app/today.php?ok=' . urlencode($noPhoto ? 'Submitted (no photo). Waiting for approval.' : 'Submitted. Waiting for approval.'));
  exit;
}

if ($kind === 'bonus') {
  $weekStart = (string)($_POST['week_start'] ?? '');
  $instanceId = (int)($_POST['instance_id'] ?? 0);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart) || $instanceId <= 0) { http_response_code(400); exit; }

  $st = $pdo->prepare("SELECT * FROM bonus_instances WHERE id=?");
  $st->execute([$instanceId]);
  $bi = $st->fetch();
  if (!$bi || (string)$bi['status'] !== 'claimed' || (int)$bi['claimed_by_kid'] !== (int)$kid['kid_id']) {
    http_response_code(403); echo "Not your bonus."; exit;
  }

  $pdo->prepare("INSERT INTO submissions(kind,week_start,kid_id,bonus_instance_id,photo_path,status,submitted_at)
                 VALUES('bonus',?,?,?,?, 'pending', ?)")
      ->execute([$weekStart, (int)$kid['kid_id'], $instanceId, $photoRel, gb2_now_iso()]);
  $subId = (int)$pdo->lastInsertId();

  $pdo->prepare("UPDATE bonus_instances SET status='pending', submission_id=? WHERE id=?")
      ->execute([$subId, $instanceId]);

  gb2_audit('kid', (int)$kid['kid_id'], $noPhoto ? 'submit_bonus_no_photo' : 'submit_bonus_proof',
    ['week_start'=>$weekStart,'instance_id'=>$instanceId,'sub_id'=>$subId]);

  header('Location: /app/bonuses.php?ok=' . urlencode($noPhoto ? 'Submitted (no photo). Waiting for approval.' : 'Submitted. Waiting for approval.'));
  exit;
}

http_response_code(400);
echo "Bad request.";
