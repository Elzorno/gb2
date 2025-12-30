<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

function gb2_nav(string $active): void {
  $kid = gb2_kid_current();
  $admin = gb2_admin_current();

  // Kid navigation (default)
  $items = [
    ['key'=>'today','href'=>'/app/today.php','label'=>'Today'],
    ['key'=>'bonuses','href'=>'/app/bonuses.php','label'=>'Bonuses'],
    ['key'=>'history','href'=>'/app/history.php','label'=>'History'],
  ];

  // Admin-only items
  if ($admin) {
    $items[] = ['key'=>'review','href'=>'/admin/review.php','label'=>'Review'];
    $items[] = ['key'=>'setup','href'=>'/admin/setup.php','label'=>'Setup'];
  }

  // If nobody is logged in yet, show Login as a hint (non-blocking)
  if (!$kid && !$admin) {
    $items[] = ['key'=>'login','href'=>'/app/login.php','label'=>'Login'];
  }

  echo '<div class="nav"><div class="wrap">';
  foreach ($items as $it) {
    $cls = ($it['key']===$active) ? 'active' : '';
    echo '<a class="'.$cls.'" href="'.gb2_h($it['href']).'"><div>•</div><div>'.gb2_h($it['label']).'</div></a>';
  }
  echo '</div></div>';
}


function gb2_page_start(string $title, ?array $kid = null): void {
  gb2_secure_headers();
  gb2_session_start();
  $who = $kid ? ('Kid: '.$kid['name']) : '';
  echo '<!doctype html><html><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>'.gb2_h($title).'</title>';
  echo '<link rel="stylesheet" href="/assets/css/app.css">';
  echo '</head><body><div class="container">';
  echo '<div class="topbar"><div class="brand">'.gb2_h($title).'</div>';
  if ($who) echo '<div class="badge">'.gb2_h($who).'</div>';
  echo '</div>';
}

function gb2_page_end(): void {
  echo '</div><script src="/assets/js/app.js"></script></body></html>';
}
