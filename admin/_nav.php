<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
$me = gb2_admin_current();
?>
<div class="topbar">
  <div class="brand"><a href="/admin/dashboard.php">GB2 Admin</a></div>
  <div class="nav">
    <a href="/admin/dashboard.php">Dashboard</a>
    <a href="/admin/setup.php">Setup</a>
    <a href="/admin/review.php">Review</a>
    <a href="/admin/grounding.php">Grounding</a>
    <a href="/app/today.php">Kid View</a>
    <a href="/admin/logout.php">Logout</a>
  </div>
</div>
