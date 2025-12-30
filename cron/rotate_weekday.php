<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/rotation.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/audit.php';

gb2_db_init();
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
gb2_rotation_generate_for_day($today);
gb2_audit('system', 0, 'cron_rotate_weekday', ['day'=>$today]);
