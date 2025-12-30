<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

/**
 * Bottom navigation: kid + admin share one consistent UI.
 *
 * Keys used by pages:
 * - Kid: dashboard, today, bonuses, history, logout
 * - Admin: admindash, family, grounding, review, setup, kidview, lock
 * - Logged out: login
 */
function gb2_nav(string $active): void {
  $kid   = gb2_kid_current();
  $admin = gb2_admin_current();

  $items = [];

  if ($admin) {
    $items = [
      ['key'=>'admindash','href'=>'/admin/dashboard.php','label'=>'Dashboard'],
      ['key'=>'family','href'=>'/admin/family.php','label'=>'Family'],
      ['key'=>'grounding','href'=>'/admin/grounding.php','label'=>'Privileges'],
      ['key'=>'review','href'=>'/admin/review.php','label'=>'Review'],
      ['key'=>'setup','href'=>'/admin/setup.php','label'=>'Setup'],
      ['key'=>'kidview','href'=>'/app/today.php','label'=>'Kid View'],
      ['key'=>'lock','href'=>'/admin/logout.php','label'=>'Lock'],
    ];
  } elseif ($kid) {
    $items = [
      ['key'=>'dashboard','href'=>'/app/dashboard.php','label'=>'Dashboard'],
      ['key'=>'today','href'=>'/app/today.php','label'=>'Today'],
      ['key'=>'bonuses','href'=>'/app/bonuses.php','label'=>'Bonuses'],
      ['key'=>'history','href'=>'/app/history.php','label'=>'History'],
      ['key'=>'logout','href'=>'/app/logout.php','label'=>'Log out'],
    ];
  } else {
    // Logged out: show a single non-blocking entry point.
    $items = [
      ['key'=>'login','href'=>'/app/login.php','label'=>'Kid Login'],
    ];
  }

  echo '<div class="nav"><div class="wrap">';
  foreach ($items as $it) {
    $cls = ($it['key'] === $active) ? 'active' : '';
    echo '<a class="'.$cls.'" href="'.gb2_h($it['href']).'"><div>•</div><div>'.gb2_h($it['label']).'</div></a>';
  }
  echo '</div></div>';
}

/**
 * Admin-only cache control to reduce stale-page confusion during rapid iteration.
 * No infra changes; headers are per-response.
 */
function gb2_admin_no_cache_headers(): void {
  if (headers_sent()) return;

  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Cache-Control: post-check=0, pre-check=0', false); // legacy proxies
  header('Pragma: no-cache');
  header('Expires: 0');
}

/**
 * Optional helper: read simple "flash" messages from query string.
 * (Not required for existing pages; safe to add now for later polish.)
 */
function gb2_flash_from_query(): array {
  $ok  = (string)($_GET['ok'] ?? '');
  $err = (string)($_GET['err'] ?? '');
  $saved = (string)($_GET['saved'] ?? '');

  if ($saved === '1' && $ok === '') $ok = 'Saved.';

  return ['ok' => $ok, 'err' => $err];
}

/**
 * Shared page chrome. Uses sitewide CSS at /assets/css/app.css.
 * Keeps UI calm + consistent across kid/admin pages.
 */
function gb2_page_start(string $title, ?array $kid = null): void {
  gb2_secure_headers();
  gb2_session_start();

  // Admin pages: aggressively disable caching at the PHP response level.
  $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
  if (strncmp($uri, '/admin/', 7) === 0) {
    gb2_admin_no_cache_headers();
  }

  $admin = gb2_admin_current();
  $brandHref = $admin ? '/admin/dashboard.php' : '/app/dashboard.php';

  $who = '';
  if ($admin) {
    $who = 'Parent/Guardian';
  } elseif ($kid && isset($kid['name'])) {
    $who = 'Kid: ' . (string)$kid['name'];
  }

  echo '<!doctype html><html><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>'.gb2_h($title).'</title>';
  echo '<link rel="stylesheet" href="/assets/css/app.css">';
  echo '</head><body><div class="container">';

  echo '<div class="topbar">';
  echo '<div class="brand"><a href="'.gb2_h($brandHref).'">GB2</a></div>';
  echo '<div class="brand" style="margin-left:10px">'.gb2_h($title).'</div>';
  if ($who) echo '<div class="badge">'.gb2_h($who).'</div>';
  echo '</div>';
}

function gb2_page_end(): void {
  echo '</div><script src="/assets/js/app.js"></script></body></html>';
}
