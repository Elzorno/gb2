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
    echo '<div class="flash ok">'.gb2_h($ok).'</div>';
  }
  if ($err !== '') {
    echo '<div class="flash err">'.gb2_h($err).'</div>';
  }
}

/**
 * Keys used by pages:
 * - Kid: dashboard, today, bonuses, rules, history, logout
 * - Admin: admindash, family, grounding, kidview, reviews, infractions, definitions, setup, branding, logout
 * - Logged out: login
 */
function gb2_nav(string $active): void {
  $kid   = gb2_kid_current();
  $admin = gb2_admin_current();

  // Build nav items. Keep labels short (mobile), but still explicit.
  $items = [];

  if ($admin) {
    $items = [
    'Core' => [
      ['key'=>'admindash','href'=>'/admin/dashboard.php','label'=>'Dashboard'],
      ['key'=>'family','href'=>'/admin/family.php','label'=>'Family'],
      ['key'=>'grounding','href'=>'/admin/grounding.php','label'=>'Privileges'],
      ['key'=>'kidview','href'=>'/admin/kidview.php','label'=>'Kid View'],
    ],
    'Reviews' => [
      ['key'=>'reviews','href'=>'/admin/reviews.php','label'=>'Reviews'],
      ['key'=>'infractions','href'=>'/admin/infractions.php','label'=>'Infraction Log'],
    ],
    'Definitions' => [
      ['key'=>'definitions','href'=>'/admin/definitions.php','label'=>'Definitions'],
      ['key'=>'rules','href'=>'/app/rules.php','label'=>'Rules'],
    ],
    'Settings' => [
      ['key'=>'branding','href'=>'/admin/branding.php','label'=>'Branding'],
      ['key'=>'setup','href'=>'/admin/setup.php','label'=>'Setup'],
      ['key'=>'logout','href'=>'/admin/logout.php','label'=>'Logout'],
    ],
    ];
  } elseif ($kid) {
    $items = [
      'Kid' => [
        ['key'=>'dashboard','href'=>'/app/dashboard.php','label'=>'Dashboard'],
        ['key'=>'today','href'=>'/app/today.php','label'=>'Today'],
        ['key'=>'bonuses','href'=>'/app/bonuses.php','label'=>'Bonuses'],
        ['key'=>'rules','href'=>'/app/rules.php','label'=>'Rules'],
        ['key'=>'history','href'=>'/app/history.php','label'=>'History'],
        ['key'=>'logout','href'=>'/app/logout.php','label'=>'Logout'],
      ],
    ];
  } else {
    $items = [
      'Login' => [
        ['key'=>'login','href'=>'/app/login.php','label'=>'Login'],
      ],
    ];
  }

  // Inline nav (desktop/tablet)
  echo '<div class="nav-inline" role="navigation" aria-label="Primary">';
  foreach ($items as $group => $links) {
    foreach ($links as $it) {
  $cls = ($it['key'] === $active) ? 'active' : '';
  if (($it['key'] ?? '') === 'logout') {
    echo '<form class="nav-logout inline" method="post" action="/admin/logout.php">';
    echo '<input type="hidden" name="_csrf" value="'.gb2_h(gb2_csrf_token()).'">';
    echo '<button type="submit" class="'.$cls.'"><div>•</div><div>'.gb2_h($it['label']).'</div></button>';
    echo '</form>';
  } else {
    echo '<a class="'.$cls.'" href="'.gb2_h($it['href']).'"><div>•</div><div>'.gb2_h($it['label']).'</div></a>';
  }
}
  }
echo '</div>';

// Mobile "top actions" strip (admin only) to reduce reliance on the hamburger menu.
if ($admin) {
  echo '<div class="topactions" aria-label="Top actions">';
  echo '<a href="/admin/dashboard.php" class="'.($active==='admindash'?'active':'').'">Dashboard</a>';
  echo '<a href="/admin/grounding.php" class="'.($active==='grounding'?'active':'').'">Privileges</a>';
  echo '<a href="/admin/reviews.php" class="'.($active==='reviews'?'active':'').'">Reviews</a>';
  echo '<a href="/admin/definitions.php" class="'.($active==='definitions'?'active':'').'">Definitions</a>';
  echo '</div>';
}

// Drawer nav (mobile)
  echo '<div class="nav-drawer-wrap" id="gb2Nav" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Menu">';
  echo '  <div class="nav-backdrop" data-nav-close></div>';
  echo '  <nav class="nav-drawer" aria-label="Menu">';
  echo '    <div class="nav-drawer-head">';
  echo '      <div class="nav-drawer-title">Menu</div>';
  echo '      <button type="button" class="navbtn" data-nav-close aria-label="Close menu">✕</button>';
  echo '    </div>';
  echo '    <div class="nav-drawer-body">';
  foreach ($items as $group => $links) {
    echo '<div class="navgroup">';
    echo '<div class="navgroup-title">'.gb2_h($group).'</div>';
    foreach ($links as $it) {
  $cls = ($it['key'] === $active) ? 'active' : '';
  if (($it['key'] ?? '') === 'logout') {
    // Logout via POST to avoid CSRF-able logouts.
    echo '<form class="nav-logout" method="post" action="/admin/logout.php">';
    echo '<input type="hidden" name="_csrf" value="'.gb2_h(gb2_csrf_token()).'">';
    echo '<button type="submit" class="'.$cls.'">'.gb2_h($it['label']).'</button>';
    echo '</form>';
  } else {
    echo '<a class="'.$cls.'" href="'.gb2_h($it['href']).'">'.gb2_h($it['label']).'</a>';
  }
}
    echo '</div>';
  }
  echo '    </div>';
  echo '  </nav>';
  echo '</div>';
}

/**
 * Shared page chrome. Uses sitewide CSS at /assets/css/app.css.
 */
function gb2_page_start(string $title, ?array $kid = null): void {
  gb2_secure_headers();
  gb2_session_start();

  $cfg = gb2_config();
  $brand = (string)($cfg['branding']['brand'] ?? 'GB2');
  $family = trim((string)($cfg['branding']['family'] ?? ''));

  $admin = gb2_admin_current();
  $brandHref = $admin ? '/admin/dashboard.php' : '/app/dashboard.php';

  $who = '';
  $isImpersonating = false;
  if ($admin) {
    $who = 'Parent/Guardian';
    // If this is an admin impersonating a kid, show it in the badge.
    if ($kid && (int)($kid['kid_id'] ?? 0) > 0 && !empty($kid['impersonating'])) {
      $isImpersonating = true;
      $who = 'Parent/Guardian • Viewing: ' . (string)($kid['name'] ?? ('Kid #' . (int)$kid['kid_id']));
    }
  } elseif ($kid) {
    $who = (string)($kid['name'] ?? 'Kid');
  }

  $req = (string)($_SERVER['REQUEST_URI'] ?? '/');
  $nextEnc = rawurlencode($req);

  echo '<!doctype html><html><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>'.gb2_h($title).'</title>';
  echo '<link rel="stylesheet" href="/assets/css/app.css">';
  echo '</head><body><div class="container">';

  echo '<div class="topbar">';
  // Mobile menu button (drawer)
  echo '<button type="button" class="navbtn navbtn-menu" data-nav-toggle aria-controls="gb2Nav" aria-expanded="false" aria-label="Open menu">☰</button>';

  echo '<div class="brand"><a href="'.gb2_h($brandHref).'">'.gb2_h($brand).'</a></div>';
  if ($family !== '') {
    echo '<div class="brand family">'.gb2_h($family).'</div>';
  }
  echo '<div class="pagetitle">'.gb2_h($title).'</div>';

  // Kid pages: always show a Parent/Guardian login link so you don't have to log kid out.
  if (!$admin) {
    echo '<div class="spacer"></div>';
    echo '<a class="btn" style="height:36px; padding:0 12px; display:flex; align-items:center" href="/admin/login.php?next='.$nextEnc.'">Parent Login</a>';
  } else {
    echo '<div class="spacer"></div>';
    if ($who) echo '<div class="badge">'.gb2_h($who).'</div>';
    // When impersonating, add a quick exit link back to Kid View hub.
    if ($isImpersonating) {
      echo '<a class="btn" style="height:36px; padding:0 12px; display:flex; align-items:center; margin-left:10px" href="/admin/kidview.php">Switch kid</a>';
    }
  }

  echo '</div>';
}

function gb2_page_end(): void {
  echo '</div><script src="/assets/js/app.js"></script></body></html>';
}
