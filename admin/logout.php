<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
gb2_admin_logout();
header('Location: /');
