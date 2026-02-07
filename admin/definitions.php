<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';

gb2_admin_require();

gb2_page_start('Definitions', null);
?>

<div class="card">
  <h2>Definitions</h2>
  <p class="muted">Manage the “menu” of things kids can earn or lose.</p>
  <div class="grid two">
    <a class="biglink" href="/admin/infraction_defs.php">Infraction Definitions</a>
    <a class="biglink" href="/admin/bonus_defs.php">Bonus Projects</a>
  </div>
</div>

<?php gb2_nav('definitions'); gb2_page_end(); ?>
