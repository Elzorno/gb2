#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-/var/www}"

say(){ printf '==> %s\n' "$*"; }

say "GB2 preflight (root=${ROOT})"

php -v | head -n 1

test -d "${ROOT}" || { echo "ERROR: ${ROOT} not found" >&2; exit 1; }

test -f "${ROOT}/config.php" || { echo "ERROR: ${ROOT}/config.php missing" >&2; exit 1; }

say "Config sanity"
php -r '$_=require "'"${ROOT}"'/config.php"; if (!is_array($_)) { fwrite(STDERR,"CONFIG_NOT_ARRAY\n"); exit(2);} echo "CONFIG_OK\n";'

say "Resolve data_dir + db path"
php -r '
require "'"${ROOT}"'/lib/common.php";
require "'"${ROOT}"'/lib/db.php";
$cfg = gb2_config();
$dir = gb2_data_dir();
$db  = gb2_db_path();
echo "DATA_DIR={$dir}\n";
echo "DB={$db}\n";
'

DB_PATH="$(php -r 'require "'"${ROOT}"'/lib/common.php"; require "'"${ROOT}"'/lib/db.php"; echo gb2_db_path();')"

if [[ ! -f "${DB_PATH}" ]]; then
  echo "ERROR: sqlite db not found at ${DB_PATH}" >&2
  exit 3
fi

say "SQLite tables"
sqlite3 "${DB_PATH}" ".tables" || { echo "ERROR: sqlite3 failed" >&2; exit 4; }

say "Kids list"
sqlite3 "${DB_PATH}" "SELECT id,name,sort_order FROM kids ORDER BY sort_order;" || true

say "Auth self-check (hash present?)"
php -r '
require "'"${ROOT}"'/lib/common.php";
$cfg = gb2_config();
$hash = (string)($cfg["admin_password_hash"] ?? "");
echo "HASH_LEN=" . strlen($hash) . "\n";
echo "HASH_PREFIX=" . substr($hash,0,6) . "\n";
'

say "DONE"
