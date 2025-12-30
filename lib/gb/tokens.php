<?php
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/store.php';

function gb_tokens_get(string $dataDir, array $config): array {
  $t = $config['device_tokens'] ?? [];
  if (!is_array($t)) return [];
  // Normalize
  $out = [];
  foreach ($t as $row) {
    if (!is_array($row)) continue;
    $id = (string)($row['id'] ?? '');
    $hash = (string)($row['hash'] ?? '');
    $label = (string)($row['label'] ?? '');
    $scope = (string)($row['scope'] ?? 'kiosk');
    if ($id === '' || $hash === '') continue;
    $out[] = [
      'id' => $id,
      'hash' => $hash,
      'label' => $label,
      'scope' => ($scope === 'kid' ? 'kid' : 'kiosk'),
      'created' => (int)($row['created'] ?? 0),
      'revoked' => !empty($row['revoked']) ? 1 : 0,
    ];
  }
  return $out;
}

function gb_tokens_save(string $dataDir, array $tokens): void {
  gb_set_config($dataDir, 'device_tokens', array_values($tokens));
}

function gb_token_make(string $label, string $scope = 'kiosk'): array {
  // token shown once to the user
  $plain = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
  $hash = hash('sha256', $plain);
  $id = substr(bin2hex(random_bytes(8)), 0, 16);
  return [
    'plain' => $plain,
    'row' => [
      'id' => $id,
      'hash' => $hash,
      'label' => trim($label),
      'scope' => ($scope === 'kid' ? 'kid' : 'kiosk'),
      'created' => time(),
      'revoked' => 0,
    ]
  ];
}

function gb_token_verify(array $tokens, string $plain, string $scope): bool {
  $plain = trim($plain);
  if ($plain === '') return false;
  $hash = hash('sha256', $plain);
  $need = ($scope === 'kid' ? 'kid' : 'kiosk');
  foreach ($tokens as $t) {
    if (!is_array($t)) continue;
    if (!empty($t['revoked'])) continue;
    if (($t['scope'] ?? '') !== $need) continue;
    if (hash_equals((string)($t['hash'] ?? ''), $hash)) return true;
  }
  return false;
}

function gb_token_prune(array $tokens, int $max = 50): array {
  // Keep most recent non-revoked first, then revoked.
  usort($tokens, function($a,$b){
    $ac = (int)($a['created'] ?? 0);
    $bc = (int)($b['created'] ?? 0);
    return $bc <=> $ac;
  });
  return array_slice($tokens, 0, max(1,$max));
}
