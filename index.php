<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';

$kid = gb2_kid_current();
if ($kid) { header('Location: /app/today.php'); exit; }
header('Location: /app/login.php');
