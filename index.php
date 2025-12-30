<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/auth.php';

gb2_session_start();
gb2_secure_headers();

if (gb2_admin_current()) {
  header('Location: /admin/family.php');
  exit;
}

if (gb2_kid_current()) {
  header('Location: /app/dashboard.php');
  exit;
}

// Logged out → kid/admin combined login page (exists)
header('Location: /app/login.php');
exit;
