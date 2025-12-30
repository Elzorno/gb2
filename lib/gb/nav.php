<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/utils.php';

function gb_nav(string $active, string $dataDir, bool $kidPage, array $config): void {
  $authed = gb_is_authed($dataDir);

  // Phase 6 UX: always show kid navigation (Board/Kiosk/Rules/Events/Today),
  // and only reveal admin links when unlocked.

  $kidItems = [
    ['href'=>'index.php','label'=>'Board','key'=>'board'],
    ['href'=>'kiosk.php','label'=>'Kiosk','key'=>'kiosk'],
    ['href'=>'kid.php','label'=>'Kid view','key'=>'kid'],
    ['href'=>'rules.php','label'=>'Rules','key'=>'rules'],
    ['href'=>'today.php','label'=>'Today','key'=>'today'],
    ['href'=>'events.php','label'=>'Events','key'=>'events'],
  ];
  $adminItems = [
    ['href'=>'edit.php','label'=>'Admin','key'=>'edit'],
    ['href'=>'kids.php','label'=>'Kids','key'=>'kids'],
    ['href'=>'settings.php','label'=>'Config','key'=>'settings'],
    ['href'=>'devices.php','label'=>'Devices','key'=>'devices'],
    ['href'=>'wizard.php','label'=>'Wizard','key'=>'wizard'],
    ['href'=>'backup.php','label'=>'Backup','key'=>'backup'],
    ['href'=>'setup.php','label'=>'Setup','key'=>'setup'],
    ['href'=>'health.php','label'=>'Health','key'=>'health'],
    ['href'=>'status.php','label'=>'Status','key'=>'status'],
  ];

  $items = $kidItems;
  if ($authed) $items = array_merge($items, $adminItems);

  $family = (string)($config['family_name'] ?? 'Family');

  echo '<nav class="nav" aria-label="Primary">';
  echo '<div class="nav-left" aria-label="Navigation">';
  foreach ($items as $it){
    $is = ($active === $it['key']) ? ' aria-current="page"' : '';
    $cls = ($active === $it['key']) ? 'pill active' : 'pill';
    echo '<a class="'.$cls.'" href="'.h($it['href']).'"'.$is.'>'.h($it['label']).'</a>';
  }
  echo '</div>';

  // Right side: status + theme
  echo '<div class="nav-right">';
  echo '<span class="nav-family">'.h($family).'</span>';
  echo $authed
    ? '<span class="chip ok" title="Admin session is unlocked">UNLOCKED</span>'
    : '<span class="chip" title="Admin session is locked">LOCKED</span>';
  echo '<a class="pill" href="edit.php">Admin</a>';
  if ($authed) echo '<a class="pill" href="lock.php">Lock</a>';

  echo '<label class="theme" title="Theme">';
  echo '<span class="small" style="margin-right:6px;">Theme</span>';
  echo '<select data-gb-theme aria-label="Theme">'
      .'<option value="auto">Auto</option>'
      .'<option value="light">Light</option>'
      .'<option value="dark">Dark</option>'
    .'</select>';
  echo '</label>';

  echo '</div>';
  echo '</nav>';
  // Mobile bottom navigation (Apple-style tab bar). Keep it simple and predictable.
  $bottomItems = [
    ['href'=>'index.php','label'=>'Board','key'=>'board'],
    ['href'=>'today.php','label'=>'Today','key'=>'today'],
    ['href'=>'kid.php','label'=>'Kid','key'=>'kid'],
    ['href'=>'rules.php','label'=>'Rules','key'=>'rules'],
  ];
  if ($authed) {
    $bottomItems[] = ['href'=>'edit.php','label'=>'Admin','key'=>'edit'];
  }

  echo '<nav class="nav-bottom" aria-label="Primary (bottom)">';
  foreach ($bottomItems as $it){
    $is = ($active === $it['key']) ? ' aria-current="page"' : '';
    $cls = ($active === $it['key']) ? 'tab active' : 'tab';
    echo '<a class="'.$cls.'" href="'.h($it['href']).'"'.$is.'><span class="tab-label">'.h($it['label']).'</span></a>';
  }
  echo '</nav>';

}
