<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';

function gb2_week_start_monday(DateTimeImmutable $d): DateTimeImmutable {
  $dow = (int)$d->format('N');
  return $d->modify('-' . ($dow-1) . ' days')->setTime(0,0,0);
}

function gb2_is_weekday(DateTimeImmutable $d): bool {
  $n = (int)$d->format('N');
  return $n >= 1 && $n <= 5;
}

function gb2_weekday_offset(DateTimeImmutable $d): int {
  return (int)$d->format('N') - 1; // Mon=0
}

function gb2_rotation_rule_get_or_create_default(): array {
  $pdo = gb2_pdo();
  $row = $pdo->query("SELECT * FROM rotation_rules ORDER BY id DESC LIMIT 1")->fetch();
  if ($row) return $row;

  $cfg = gb2_config();
  $kids = $cfg['rotation']['kids_order'] ?? ['Megan','Stacey','Barry','Brady','Parker'];
  $slots = $cfg['rotation']['slot_titles'] ?? ['Dishes','Trash + Bathrooms','Help Cook','Common Areas','Help Everybody'];
  $anchor = (new DateTimeImmutable('now'))->modify('monday this week')->format('Y-m-d');

  $pdo->prepare("INSERT INTO rotation_rules(name,kids_json,slots_json,anchor_monday,created_at)
                 VALUES(?,?,?,?,?)")
      ->execute(['Default Weekday Rotation', json_encode($kids), json_encode($slots), $anchor, gb2_now_iso()]);
  return $pdo->query("SELECT * FROM rotation_rules ORDER BY id DESC LIMIT 1")->fetch();
}

function gb2_rotation_ensure_slots_exist(array $slotTitles): void {
  $pdo = gb2_pdo();
  foreach ($slotTitles as $i => $t) {
    $t = trim((string)$t);
    if ($t === '') continue;
    $st = $pdo->prepare("SELECT id FROM chore_slots WHERE title=? LIMIT 1");
    $st->execute([$t]);
    $r = $st->fetch();
    if (!$r) {
      $pdo->prepare("INSERT INTO chore_slots(title,active,sort_order) VALUES(?,?,?)")
          ->execute([$t, 1, $i]);
    }
  }
}

function gb2_rotation_generate_for_day(string $dayYmd): void {
  gb2_db_init();
  $pdo = gb2_pdo();

  $d = DateTimeImmutable::createFromFormat('Y-m-d', $dayYmd) ?: new DateTimeImmutable('today');
  if (!gb2_is_weekday($d)) return;

  $rule = gb2_rotation_rule_get_or_create_default();
  $kidNames = json_decode((string)$rule['kids_json'], true) ?: [];
  $slotTitles = json_decode((string)$rule['slots_json'], true) ?: [];

  gb2_rotation_ensure_slots_exist($slotTitles);

  $kids = $pdo->query("SELECT id,name FROM kids ORDER BY sort_order ASC, name ASC")->fetchAll();
  $kidMap = [];
  foreach ($kids as $k) $kidMap[(string)$k['name']] = (int)$k['id'];

  $slotIds = [];
  foreach ($slotTitles as $t) {
    $st = $pdo->prepare("SELECT id FROM chore_slots WHERE title=? LIMIT 1");
    $st->execute([$t]);
    $r = $st->fetch();
    if ($r) $slotIds[] = (int)$r['id'];
  }
  if (count($slotIds) < 1) return;

  $offset = gb2_weekday_offset($d);

  foreach ($kidNames as $i => $name) {
    if (!isset($kidMap[$name])) continue;
    $kidId = $kidMap[$name];
    $slotId = $slotIds[($i + $offset) % count($slotIds)];

    $st = $pdo->prepare("SELECT 1 FROM assignments WHERE day=? AND kid_id=?");
    $st->execute([$dayYmd, $kidId]);
    if (!$st->fetch()) {
      $pdo->prepare("INSERT INTO assignments(day,kid_id,slot_id,status) VALUES(?,?,?,?)")
          ->execute([$dayYmd, $kidId, $slotId, 'open']);
    }
  }
}

function gb2_assignments_for_kid_day(int $kidId, string $dayYmd): array {
  $pdo = gb2_pdo();
  $st = $pdo->prepare("SELECT a.day,a.status,a.submission_id,s.title as slot_title,a.slot_id
                       FROM assignments a
                       JOIN chore_slots s ON s.id=a.slot_id
                       WHERE a.day=? AND a.kid_id=?
                       ORDER BY s.sort_order ASC, s.title ASC");
  $st->execute([$dayYmd, $kidId]);
  return $st->fetchAll();
}
