<?php
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function iso_now(): string { return (new DateTimeImmutable('now'))->format('c'); }

function parse_until($until): ?DateTimeImmutable {
  if (!$until) return null;
  if (is_string($until)) {
    $t = strtotime($until);
    if ($t === false) return null;
    return (new DateTimeImmutable())->setTimestamp($t);
  }
  return null;
}

function is_grounded_until($until): bool {
  $dt = parse_until($until);
  if (!$dt) return false;
  return $dt->getTimestamp() > time();
}

function days_left($until): int {
  $dt = parse_until($until);
  if (!$dt) return 0;
  $sec = $dt->getTimestamp() - time();
  if ($sec <= 0) return 0;
  return (int)ceil($sec / 86400);
}

function add_seconds($until, int $sec): string {
  $base = parse_until($until);
  if (!$base) $base = new DateTimeImmutable('now');
  return $base->modify(($sec>=0?'+':'').$sec.' seconds')->format('Y-m-d H:i:s');
}

function set_days_from_now(int $days): string {
  $days = max(0, $days);
  return (new DateTimeImmutable('now'))->modify('+'.$days.' days')->format('Y-m-d H:i:s');
}

function clamp_int($v, int $min, int $max): int {
  $n = (int)$v;
  return max($min, min($max, $n));
}

function compute_days(array $inf, int $priorStrikes): int {
  $mode = (string)($inf['mode'] ?? 'set'); // 'set' or 'add'
  $days = (int)($inf['days'] ?? 0);
  $ladder = $inf['ladder'] ?? null;
  if (is_array($ladder) && count($ladder)){
    $idx = min(max($priorStrikes+1, 1), count($ladder)) - 1;
    $days = (int)$ladder[$idx];
  }
  return max(0, $days);
}

function find_inf(array $infractions, string $id): ?array {
  foreach ($infractions as $inf){
    if (($inf['id'] ?? '') === $id) return $inf;
  }
  return null;
}
