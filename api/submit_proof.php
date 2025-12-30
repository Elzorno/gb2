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

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo "Upload failed."; exit; }
if ((int)$_FILES['photo']['size'] > $maxBytes) { http_response_code(413); echo "File too large."; exit; }

$tmp = $_FILES['photo']['tmp_name'];
$mime = '';
if (class_exists('finfo')) { $fi = new finfo(FILEINFO_MIME_TYPE); $mime = (string)$fi->file($tmp); }
if (!$mime) $mime = (string)(mime_content_type($tmp) ?: '');
if (!in_array($mime, ['image/jpeg','image/png','image/heic','image/heif','image/webp'])) { http_response_code(400); echo "Unsupported image type."; exit; }

$dataDir = gb2_data_dir();
$upDir = rtrim($dataDir,'/') . '/uploads/' . date('Y') . '/' . date('m');
@mkdir($upDir, 0775, true);

$ext = 'jpg';
if ($mime === 'image/png') $ext='png';
elseif ($mime === 'image/webp') $ext='webp';
elseif (in_array($mime,['image/heic','image/heif'])) $ext='heic';

$fname = bin2hex(random_bytes(16)) . '.' . $ext;
$dest = $upDir . '/' . $fname;
if (!move_uploaded_file($tmp, $dest)) { http_response_code(500); echo "Save failed."; exit; }

$kind = (string)($_POST['kind'] ?? '');
$pdo = gb2_pdo();

if ($kind === 'base') {
  $day = (string)($_POST['day'] ?? '');
  $slotId = (int)($_POST['slot_id'] ?? 0);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $slotId<=0) { http_response_code(400); exit; }

  $pdo->prepare("INSERT INTO submissions(kind,day,kid_id,slot_id,photo_path,status,submitted_at)
                 VALUES('base',?,?,?,?, 'pending', ?)")
      ->execute([$day, (int)$kid['kid_id'], $slotId, str_replace($dataDir.'/', '', $dest), gb2_now_iso()]);
  $subId = (int)$pdo->lastInsertId();

  $pdo->prepare("UPDATE assignments SET status='pending', submission_id=? WHERE day=? AND kid_id=?")
      ->execute([$subId, $day, (int)$kid['kid_id']]);

  gb2_audit('kid', (int)$kid['kid_id'], 'submit_base_proof', ['day'=>$day,'slot_id'=>$slotId,'sub_id'=>$subId]);
  header('Location: /app/today.php'); exit;
}

if ($kind === 'bonus') {
  $weekStart = (string)($_POST['week_start'] ?? '');
  $instanceId = (int)($_POST['instance_id'] ?? 0);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart) || $instanceId<=0) { http_response_code(400); exit; }

  $st = $pdo->prepare("SELECT * FROM bonus_instances WHERE id=?");
  $st->execute([$instanceId]);
  $bi = $st->fetch();
  if (!$bi || (string)$bi['status']!=='claimed' || (int)$bi['claimed_by_kid'] !== (int)$kid['kid_id']) { http_response_code(403); echo "Not your bonus."; exit; }

  $pdo->prepare("INSERT INTO submissions(kind,week_start,kid_id,bonus_instance_id,photo_path,status,submitted_at)
                 VALUES('bonus',?,?,?,?, 'pending', ?)")
      ->execute([$weekStart, (int)$kid['kid_id'], $instanceId, str_replace($dataDir.'/', '', $dest), gb2_now_iso()]);
  $subId = (int)$pdo->lastInsertId();

  $pdo->prepare("UPDATE bonus_instances SET status='pending', submission_id=? WHERE id=?")
      ->execute([$subId, $instanceId]);

  gb2_audit('kid', (int)$kid['kid_id'], 'submit_bonus_proof', ['week_start'=>$weekStart,'instance_id'=>$instanceId,'sub_id'=>$subId]);
  header('Location: /app/bonuses.php'); exit;
}

http_response_code(400);
echo "Bad request.";
