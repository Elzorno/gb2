<?php
// Copy to /data/config.php (outside the repo) and edit. config.php in the repo is a shim that loads it.

return [
  // Store absolute path to data dir; default uses this project's /data
  'data_dir' => __DIR__ . '/data',

  // Admin password hash (Argon2id). Set via /admin/setup.php or CLI helper.
  'admin_password_hash' => '',

  // Branding / titles
  'branding' => [
    // Short brand shown in header
    'brand' => 'GB2',
    // Optional family name shown next to brand (e.g., "Zornes Family")
    'family' => '',
  ],


  // Session settings
  'session' => [
    'cookie_name' => 'gb2_sess',
    'kid_device_cookie' => 'gb2_dev',
    'csrf_key' => 'gb2_csrf',
    'kid_session_days' => 30,
    'admin_session_minutes' => 20,
    'pin_min_len' => 6,
    'pin_max_len' => 6,
  ],

  // Rotation defaults
  'rotation' => [
    'kids_order' => ['Megan','Stacey','Barry','Brady','Parker'],
    'slot_titles' => ['Dishes','Trash + Bathrooms','Help Cook','Common Areas','Help Everybody'],
  ],

  // Upload limits
  'uploads' => [
    'max_bytes' => 7 * 1024 * 1024, // 7MB
  ],
];
