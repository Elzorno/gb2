<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ui.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

gb2_db_init();

// Allow kid OR admin to view; otherwise show login nav.
$kid = gb2_kid_current();
$admin = gb2_admin_current();

$pdo = gb2_pdo();
$defs = $pdo->query("SELECT * FROM infraction_defs WHERE active=1 ORDER BY sort_order ASC, label ASC")
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

function gb2_decode_arr(?string $json, array $fallback): array {
  if (!$json) return $fallback;
  $v = json_decode($json, true);
  return is_array($v) ? $v : $fallback;
}

function gb2_blocks_text(array $b): string {
  $t = [];
  if (!empty($b['phone'])) $t[] = 'Phone';
  if (!empty($b['games'])) $t[] = 'Games';
  if (!empty($b['other'])) $t[] = 'Other';
  return count($t) ? implode(', ', $t) : 'None';
}

gb2_page_start('Rules', $kid ?: null);
?>
<div class="card">
  <div class="h1">Rules & Consequences</div>
  <div class="h2">Predictable, consistent, and visible to everyone</div>

  <?php if (!$admin && !$kid): ?>
    <div class="note" style="margin-top:12px">Please log in to view rules.</div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="h1">Infractions</div>
  <div class="h2">Defaults and strike ladders</div>

  <?php if (!$defs): ?>
    <div class="note" style="margin-top:10px">No active infraction definitions yet.</div>
  <?php else: ?>
    <div style="margin-top:10px">
      <?php foreach ($defs as $d): ?>
        <?php
          $blocks = gb2_decode_arr((string)($d['blocks_json'] ?? ''), ['phone'=>0,'games'=>0,'other'=>0]);
          $ladder = gb2_decode_arr((string)($d['ladder_json'] ?? ''), []);
          $repairs = gb2_decode_arr((string)($d['repairs_json'] ?? ''), []);
        ?>
        <div class="note" style="margin-bottom:12px">
          <div style="display:flex; gap:10px; align-items:center; justify-content:space-between;">
            <div>
              <strong><?= gb2_h((string)$d['label']) ?></strong>
              <span class="badge"><?= gb2_h((string)$d['code']) ?></span>
            </div>
            <div class="small">mode: <?= gb2_h((string)$d['mode']) ?></div>
          </div>

          <div class="small" style="margin-top:6px">
            days:
            <?php if (is_array($ladder) && count($ladder)): ?>
              <?= gb2_h(implode(' → ', array_map('intval', $ladder))) ?>
            <?php else: ?>
              <?= (int)($d['days'] ?? 0) ?>
            <?php endif; ?>
            &nbsp;|&nbsp; blocks: <?= gb2_h(gb2_blocks_text($blocks)) ?>
          </div>

          <?php if (is_array($repairs) && count($repairs)): ?>
            <div class="small" style="margin-top:6px">
              repair options:
              <ul style="margin:6px 0 0 18px;">
                <?php foreach ($repairs as $r): ?>
                  <li><?= gb2_h((string)$r) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php
// nav key depends on role
if ($admin) gb2_nav('rules');
elseif ($kid) gb2_nav('rules');
else gb2_nav('login');
gb2_page_end();
