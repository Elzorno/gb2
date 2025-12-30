<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_db_init();
$kid = gb2_kid_require();

gb2_page_start('Photo Upload Help', $kid);
?>
<div class="card">
  <div class="h1">If photo upload doesn’t work on iPhone</div>
  <div class="note" style="margin-top:10px">
    Some iPhones block the camera/photo picker in certain browser contexts or with Screen Time restrictions.
    If tapping “Choose File” does nothing, try this checklist.
  </div>

  <div style="height:12px"></div>

  <div class="h2">Quick checklist</div>
  <ul style="margin:8px 0 0 1.2rem; line-height:1.6">
    <li><b>Use Safari</b> (not an in-app browser inside Messages/Discord/Facebook/Instagram, etc.).</li>
    <li><b>Screen Time</b>: Settings → Screen Time → Content &amp; Privacy Restrictions (camera/photos/Safari can be blocked).</li>
    <li><b>Website settings</b>: In Safari, tap <b>AA</b> → Website Settings → allow camera/photos if available.</li>
    <li><b>Try another device</b> (PC works), or use “Submit without photo” and a parent can review.</li>
  </ul>

  <div style="height:14px"></div>

  <div class="row" style="gap:10px; flex-wrap:wrap; justify-content:flex-start">
    <a class="btn" href="/app/today.php">Back to Today</a>
    <a class="btn" href="/app/bonuses.php">Back to Bonuses</a>
  </div>
</div>

<?php gb2_nav('today'); gb2_page_end(); ?>
