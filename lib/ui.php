<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

/**
 * Bottom nav (pretty nav).
 * - Kid nav is default
 * - Admin nav appears only when admin is unlocked
 */
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
    $items[] = ['key'=>'family','href'=>'/admin/family.php','label'=>'Family'];
    $items[] = ['key'=>'grounding','href'=>'/admin/grounding.php','label'=>'Grounding'];
    $items[] = ['key'=>'review','href'=>'/admin/review.php','label'=>'Review'];
    $items[] = ['key'=>'setup','href'=>'/admin/setup.php','label'=>'Setup'];
    $items[] = ['key'=>'kidview','href'=>'/app/today.php','label'=>'Kid View'];
    $items[] = ['key'=>'logout','href'=>'/admin/logout.php','label'=>'Lock'];
  } elseif ($kid) {
    $items[] = ['key'=>'logout','href'=>'/app/logout.php','label'=>'Log out'];
  }

  // If nobody is logged in yet, show Login as a hint (non-blocking)
  if (!$kid && !$admin) {
    $items[] = ['key'=>'login','href'=>'/app/login.php','label'=>'Login'];
  }

  echo '<div class="nav"><div class="wrap">';
  foreach ($items as $it) {
    $cls = ($it['key'] === $active) ? 'active' : '';
    echo '<a class="'.$cls.'" href="'.gb2_h($it['href']).'"><div>•</div><div>'.gb2_h($it['label']).'</div></a>';
  }
  echo '</div></div>';
}

/**
 * Unified page shell.
 *
 * Header/nav consistency rules:
 * - Brand is always "GB2" and links to "/" (deterministic router)
 * - Screen title is shown consistently in the top bar
 * - Kid badge appears when a kid is logged in (or provided)
 */
function gb2_page_start(string $title, ?array $kid = null): void {
  gb2_secure_headers();
  gb2_session_start();

  // If kid wasn't provided explicitly, use current (helps pages that don't pass it)
  if ($kid === null) {
    $cur = gb2_kid_current();
    if (is_array($cur)) { $kid = $cur; }
  }

  $kidBadge = '';
  if ($kid && isset($kid['name'])) {
    $kidBadge = 'Kid: ' . (string)$kid['name'];
  }

  echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>'.gb2_h($title).'</title>';
  echo '<link rel="stylesheet" href="/assets/css/app.css">';
  echo '</head><body><div class="container">';

  // Top bar: consistent brand + screen title + optional kid badge
  echo '<div class="topbar">';
  echo '  <div class="brand"><a href="/">GB2</a></div>';
  echo '  <div class="badge">'.gb2_h($title).'</div>';
  if ($kidBadge !== '') {
    echo '  <div class="badge">'.gb2_h($kidBadge).'</div>';
  }
  echo '</div>';
}

function gb2_page_end(): void {
  echo '</div><script src="/assets/js/app.js"></script></body></html>';
}
