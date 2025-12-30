<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

/**
 * Read a one-time, redirect-safe flash message from query params.
 * Supported:
 *   - ?ok=Message
 *   - ?err=Message
 *   - ?saved=1   (maps to ok="Saved.")
 *
 * Returns: ['ok'=>string,'err'=>string]
 */
function gb2_flash_from_query(): array {
  $ok = '';
  $err = '';

  if (isset($_GET['saved']) && (string)$_GET['saved'] === '1') {
    $ok = 'Saved.';
  }

  if (isset($_GET['ok'])) {
    $ok = trim((string)$_GET['ok']);
  }

  if (isset($_GET['err'])) {
    $err = trim((string)$_GET['err']);
  }

  // Keep messages short and safe.
  $ok  = mb_substr($ok, 0, 160);
  $err = mb_substr($err, 0, 160);

  return ['ok' => $ok, 'err' => $err];
}

/**
 * Render flash banners in the shared UI style.
 */
function gb2_flash_render(array $flash): void {
  $ok = (string)($flash['ok'] ?? '');
  $err = (string)($flash['err'] ?? '');

  if ($ok !== '') {
    echo '<div class="notice ok">'.gb2_h($ok).'</div>';
  }
  if ($err !== '') {
    echo '<div class="notice bad">'.gb2_h($err).'</div>';
  }
}

/**
 * User-facing status labels (do NOT change DB values).
 * Keeps CSS class as the raw status string (open/pending/approved/rejected),
 * but displays friendly text.
 */
function gb2_status_label(string $status): string {
  $s = strtolower(trim($status));
  return match ($s) {
    'open'     => 'Open',
    'pending'  => 'Waiting for review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    default    => ($status !== '' ? $status : '—'),
  };
}

/**
 * Friendly date helpers.
 */
function gb2_human_date(string $ymd): string {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return $ymd;
  $d = new DateTimeImmutable($ymd);
  return $d->format('D, M j');
}

function gb2_human_datetime(string $iso): string {
  $iso = trim($iso);
  if ($iso === '') return '';
  try {
    $d = new DateTimeImmutable($iso);
    return $d->format('D, M j g:ia');
  } catch (Throwable $e) {
    return $iso;
  }
}

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
 * Shared page chrome. Uses sitewide CSS at /assets/css/app.css.
 * Keeps UI calm + consistent across kid/admin pages.
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
