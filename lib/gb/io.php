<?php
function gb_load_json(string $path, $default){
  if (!file_exists($path)) return $default;
  $raw = file_get_contents($path);
  $data = json_decode($raw, true);
  return $data === null ? $default : $data;
}

function gb_save_json_atomic(string $path, $data, int $keepBackups = 3): bool {
  $dir = dirname($path);
  if (!is_dir($dir)) return false;
  if (!is_writable($dir)) return false;

  $json = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  if ($json === false) return false;

  // backups
  if (file_exists($path)) {
    $ts = date('Ymd_His');
    $bak = $path . '.bak.' . $ts;
    @copy($path, $bak);
    // prune
    $pattern = $path . '.bak.*';
    $baks = glob($pattern) ?: [];
    rsort($baks);
    for ($i=$keepBackups; $i<count($baks); $i++){
      @unlink($baks[$i]);
    }
  }

  $tmp = $path . '.tmp';
  $ok = file_put_contents($tmp, $json, LOCK_EX);
  if ($ok === false) return false;
  return @rename($tmp, $path);
}
