<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_admin_require();

gb2_page_start('Reviews', null);
?>

<div class="card">
  <h2>Reviews</h2>
  <p class="muted">Approve or review items that need a parent/guardian decision.</p>
  <div class="grid two">
    <a class="biglink" href="/admin/review.php">Bonus Review</a>
    <a class="biglink" href="/admin/infraction_review.php">Infraction Review</a>
  </div>
</div>

<?php gb2_nav('reviews'); gb2_page_end(); ?>
