#!/usr/bin/env bash
set -euo pipefail

F="/var/www/api/approve.php"
[[ -f "$F" ]] || { echo "ERROR: missing $F"; exit 1; }

ts="$(date -u +%Y%m%dT%H%M%SZ)"
cp -a "$F" "${F}.bak.${ts}"

python3 - <<'PY'
import re, pathlib, sys

p = pathlib.Path("/var/www/api/approve.php")
s = p.read_text(encoding="utf-8")

if "function gb2_try_delete_submission_photo(" not in s:
  # Insert helper right after gb2_approve_log() function
  m = re.search(r"(function\s+gb2_approve_log\s*\([^)]*\)\s*:\s*void\s*\{\s*.*?\n\}\n)", s, flags=re.S)
  if not m:
    print("ERROR: couldn't find gb2_approve_log() block to anchor insertion", file=sys.stderr)
    sys.exit(2)

  helper = r"""
function gb2_try_delete_submission_photo(array $sub): void {
  $photo = (string)($sub['photo_path'] ?? '');
  if ($photo === '' || $photo === 'uploads/NO_PHOTO') return;

  $dataDir = rtrim(gb2_data_dir(), '/');
  $abs = $dataDir . '/' . ltrim($photo, '/');

  // Safety: ensure path stays under dataDir
  $realData = @realpath($dataDir);
  $realAbs  = @realpath($abs);
  if (!$realData || !$realAbs) {
    // If file doesn't exist, nothing to do.
    return;
  }
  if (strpos($realAbs, $realData . DIRECTORY_SEPARATOR) !== 0) {
    gb2_approve_log('photo_delete_refused path=' . $abs);
    return;
  }

  if (is_file($realAbs)) {
    if (!@unlink($realAbs)) {
      gb2_approve_log('photo_delete_failed path=' . $realAbs);
    }
  }
}
"""
  s = s[:m.end()] + helper + s[m.end():]

# Insert call after commit (idempotent)
needle = r"$pdo->commit();"
call = r"""
  // Delete uploaded photo after final decision (approve or reject)
  gb2_try_delete_submission_photo($sub);
"""
if call.strip() not in s:
  s = s.replace(needle, needle + call)

p.write_text(s, encoding="utf-8")
print("OK: patched approve.php")
PY

php -l /var/www/api/approve.php

echo
echo "Patch complete."
echo "Tip: tail approve_fail.log if you want to confirm deletes:"
echo "  tail -n 50 /var/www/data/approve_fail.log 2>/dev/null || true"
