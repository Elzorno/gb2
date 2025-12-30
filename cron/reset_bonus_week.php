<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bonuses.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/audit.php';

gb2_db_init();
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$weekStart = gb2_bonus_week_start($today);

$pdo = gb2_pdo();
$pdo->prepare("DELETE FROM bonus_instances WHERE week_start=?")->execute([$weekStart]);
gb2_bonus_reset_week($weekStart);
gb2_audit('system', 0, 'cron_reset_bonus_week', ['week_start'=>$weekStart]);
