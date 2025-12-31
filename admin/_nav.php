<?php
declare(strict_types=1);

/**
 * DEPRECATED: /admin/_nav.php
 *
 * GB2 no longer uses this file for navigation. The authoritative nav is rendered by:
 *   - gb2_page_start() + gb2_nav() in /var/www/lib/ui.php
 *
 * This file remains ONLY as a compatibility shim in case any older/admin pages still include it.
 * It forwards to gb2_nav() with a best-effort $active key.
 */

require_once __DIR__ . '/../lib/ui.php';   // provides gb2_nav()
require_once __DIR__ . '/../lib/auth.php'; // ensures session/auth helpers available

// If a caller set $active (string), use it; otherwise default to admin dashboard.
$activeKey = 'admindash';
if (isset($active) && is_string($active) && $active !== '') {
  $activeKey = $active;
}

// Render the unified nav (matches current UI).
gb2_nav($activeKey);
