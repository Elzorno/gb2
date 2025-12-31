<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

/**
 * Render flash messages from querystring (?ok=... or ?err=...).
 * Deterministic + curl-testable.
 */
function gb2_flash_render(): void {
  $ok  = isset($_GET['ok'])  ? trim((string)$_GET['ok'])  : '';
  $err = isset($_GET['err']) ? trim((string)$_GET['err']) : '';

  if ($ok !== '') {
    echo '<div class="status approved" style="margin-top:12px">' . gb2_h($ok) . '</div>';
  }
  if ($err !== '') {
    echo '<div class="status rejected" style="margin-top:12px">' . gb2_h($err) . '</div>';
  }
}

/**
 * Bottom navigation: kid + admin share one consistent UI.
 *
 * Keys used by pages:
 * - Kid: dashboard, today, bonuses, rules, history, logout
 * - Admin: admindash, family, grounding, infractions, infraction_defs, rules, review, setup, kidview, lock
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
      ['key'=>'infractions','href'=>'/admin/infractions.php','label'=>'Infractions'],
      ['key'=>'infraction_defs','href'=>'/admin/infraction_defs.php','label'=>'Defs'],
      ['key'=>'rules','href'=>'/app/rules.php','label'=>'Rules'],
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
      ['key'=>'rules','href'=>'/app/rules.php','label'=>'Rules'],
      ['key'=>'history','href'=>'/app/history.php','label'=>'History'],
      ['key'=>'logout','href'=>'/app/logout.php','label'=>'Log out'],
    ];
  } else {
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
 * Shared page chrome. Uses sitewide CSS at /assets/css/app.css.
 */
function gb2_page_start(string $title, ?array $kid = null): void {
  gb2_secure_headers();
  gb2_session_start();

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
