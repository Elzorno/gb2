<?php
require_once __DIR__ . '/auth.php';

function gb_csrf_token(): string {
  gb_session_start();
  if (empty($_SESSION['gb_csrf'])) {
    $_SESSION['gb_csrf'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['gb_csrf'];
}

function gb_csrf_field(): string {
  $t = gb_csrf_token();
  return '<input type="hidden" name="gb_csrf" value="'.htmlspecialchars($t, ENT_QUOTES, 'UTF-8').'">';
}

function gb_csrf_check(): bool {
  gb_session_start();
  $sent = (string)($_POST['gb_csrf'] ?? '');
  $stored = (string)($_SESSION['gb_csrf'] ?? '');
  if ($sent === '' || $stored === '') return false;
  return hash_equals($stored, $sent);
}

function gb_require_csrf(): void {
  // IMPORTANT: when a locked admin page receives a POST, gb_require_auth() can
  // present the unlock form which POSTs back to the same URL. That unlock POST
  // should not be treated as an application action requiring gb_csrf.
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['gb_unlock_csrf']) || isset($_POST['gb_login_attempt'])) {
      return;
    }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !gb_csrf_check()) {
    http_response_code(400);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Bad Request</title><link rel="stylesheet" href="css/style.css">';
    echo '<div class="app"><div class="section"><h2>Security check failed</h2>';
    echo '<div class="small">This action was blocked to protect against cross-site request forgery. Please reload the page and try again.</div>';
    echo '</div></div>';
    exit;
  }
}
