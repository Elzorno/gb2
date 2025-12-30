<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

function gb2_nav(string $active): void {
  $kid = gb2_kid_current();
  $admin = gb2_admin_current();

  $items = [];

  if ($admin) {
    // Admin nav (admin-first, same "pretty" style as kid nav)
    $items = [
      ['key'=>'dashboard','href'=>'/admin/dashboard.php','label'=>'Dashboard'],
      ['key'=>'family','href'=>'/admin/family.php','label'=>'Family'],
      ['key'=>'setup','href'=>'/admin/setup.php','label'=>'Setup'],
      ['key'=>'review','href'=>'/admin/review.php','label'=>'Review'],
      ['key'=>'grounding','href'=>'/admin/grounding.php','label'=>'Grounding'],
      ['key'=>'kidview','href'=>'/app/dashboard.php','label'=>'Kid View'],
      ['key'=>'logout','href'=>'/admin/logout.php','label'=>'Lock'],
    ];
  } elseif ($kid) {
    // Kid nav
    $items = [
      ['key'=>'dashboard','href'=>'/app/dashboard.php','label'=>'Dashboard'],
      ['key'=>'today','href'=>'/app/today.php','label'=>'Today'],
      ['key'=>'bonuses','href'=>'/app/bonuses.php','label'=>'Bonuses'],
      ['key'=>'history','href'=>'/app/history.php','label'=>'History'],
      ['key'=>'logout','href'=>'/app/logout.php','label'=>'Log out'],
    ];
  } else {
    // Not logged in: keep roles explicit
    $items = [
      ['key'=>'login','href'=>'/app/login.php','label'=>'Kid Login'],
      ['key'=>'admin','href'=>'/admin/login.php','label'=>'Parent/Guardian'],
    ];
  }

  echo '<div class="nav"><div class="wrap">';
  foreach ($items as $it) {
    $cls = ($it['key'] === $active) ? 'active' : '';
    echo '<a class="'.$cls.'" href="'.gb2_h($it['href']).'"><div>•</div><div>'.gb2_h($it['label']).'</div></a>';
  }
  echo '</div></div>';
}

function gb2_page_start(string $title, ?array $kid = null): void {
  gb2_secure_headers();
  gb2_session_start();

  $kidName = ($kid && isset($kid['name'])) ? (string)$kid['name'] : '';
  $isAdmin = (function_exists('gb2_admin_current') && gb2_admin_current());

  echo '<!doctype html><html><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>'.gb2_h($title).'</title>';
  echo '<link rel="stylesheet" href="/assets/css/app.css">';
  echo '</head><body><div class="container">';

  // Simple topbar: brand + optional badge (nav remains the pretty bar)
  echo '<div class="topbar">';
  echo '<div class="brand"><a href="'.($isAdmin ? '/admin/dashboard.php' : '/app/dashboard.php').'">'.gb2_h($isAdmin ? 'GB2 Admin' : 'GB2').'</a></div>';
  if ($kidName !== '') {
    echo '<div class="badge">Kid: '.gb2_h($kidName).'</div>';
  }
  echo '</div>';
}

function gb2_page_end(): void {
  echo '</div><script src="/assets/js/app.js"></script></body></html>';
}
