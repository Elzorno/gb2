<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/common.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_session_start();
gb2_secure_headers();

gb2_kid_logout();

$_SESSION = [];
if (session_id() !== '') {
  session_regenerate_id(true);
}

header('Location: /app/login.php');
exit;
