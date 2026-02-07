<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/common.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

gb2_session_start();
gb2_secure_headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  // Encourage logout via POST to avoid CSRF-able logouts.
  header('Location: /admin/dashboard.php');
  exit;
}

gb2_csrf_verify();

gb2_admin_logout();

// Ensure session is fully cleared (prevents "sticky" redirects)
$_SESSION = [];
if (session_id() !== '') {
  session_regenerate_id(true);
}

header('Location: /app/login.php');
exit;
