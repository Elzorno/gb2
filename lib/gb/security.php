<?php

/**
 * Security headers and safe session defaults.
 * Call gb_secure_headers() near the top of every page.
 */

function gb_is_https(): bool {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
  if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
  return false;
}

function gb_secure_headers(bool $allowFraming = false): void {
  if (headers_sent()) return;

  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
  header('Cross-Origin-Opener-Policy: same-origin');
  header('Cross-Origin-Resource-Policy: same-origin');

  if (!$allowFraming) {
    header('X-Frame-Options: DENY');
  }

  // CSP: keep it simple; this app uses a small amount of inline style.
  $csp = [
    "default-src 'self'",
    "img-src 'self' data:",
    "style-src 'self' 'unsafe-inline'",
    "script-src 'self'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ];
  header('Content-Security-Policy: '.implode('; ', $csp));

  if (gb_is_https()) {
    header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
  }
}
