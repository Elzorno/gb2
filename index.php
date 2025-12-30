<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/auth.php';

gb2_session_start();
gb2_secure_headers();

/**
 * Single entry router.
 * - Admin: go to Family dashboard
 * - Kid: go to Kid dashboard
 * - Nobody: go to combined login (kid-first) at /app/login.php
 *
 * Note: Admin unlock should be performed at /admin/login.php when needed by admin pages.
 */

if (gb2_admin_current()) {
  header('Location: /admin/family.php');
  exit;
}

if (gb2_kid_current()) {
  header('Location: /app/dashboard.php');
  exit;
}

header('Location: /app/login.php');
exit;
