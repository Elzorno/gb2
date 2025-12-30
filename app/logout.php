<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
gb2_kid_logout();
header('Location: /app/login.php');
