#!/usr/bin/env bash
set -euo pipefail

DB="/var/www/data/app.sqlite"

need_bin() { command -v "$1" >/dev/null 2>&1 || { echo "ERROR: missing $1" >&2; exit 1; }; }
need_file() { [[ -f "$1" ]] || { echo "WARN: missing file $1" >&2; return 1; }; }
hdr() { echo; echo "==================== $* ===================="; }

need_bin sqlite3
need_bin sed
need_bin ls
need_bin date

hdr "ENV"
echo "DATE_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "HOST=$(hostname || true)"
echo "PWD=$(pwd)"
echo "PHP=$(php -v 2>/dev/null | head -n 1 || true)"

hdr "REPO STATUS"
if command -v git >/dev/null 2>&1 && [[ -d /var/www/.git ]]; then
  (cd /var/www && git status --porcelain=v1 && git rev-parse --short HEAD && git log -1 --oneline) || true
else
  echo "WARN: git not available or /var/www not a git repo"
fi

hdr "DB: PRAGMA user_version + tables"
sqlite3 "$DB" <<'SQL'
.headers on
.mode column
PRAGMA user_version;
.tables
SQL

hdr "DB: schema for key tables"
sqlite3 "$DB" <<'SQL'
.headers on
.mode column
SELECT name, sql
FROM sqlite_master
WHERE type='table' AND name IN (
  'kids','privileges',
  'infraction_defs','infraction_strikes','infraction_events'
)
ORDER BY name;
SQL

hdr "DB: quick sanity row counts"
sqlite3 "$DB" <<'SQL'
.headers on
.mode column
SELECT 'kids' AS tbl, COUNT(*) AS n FROM kids
UNION ALL SELECT 'privileges', COUNT(*) FROM privileges
UNION ALL SELECT 'infraction_defs', COUNT(*) FROM infraction_defs
UNION ALL SELECT 'infraction_strikes', COUNT(*) FROM infraction_strikes
UNION ALL SELECT 'infraction_events', COUNT(*) FROM infraction_events;
SQL

# --- Files needed for A + B + C (and shared libs) ---
FILES=(
  "/var/www/admin/family.php"
  "/var/www/admin/today.php"
  "/var/www/lib/ui.php"
  "/var/www/lib/privileges.php"
  "/var/www/assets/css/app.css"
  "/var/www/app/rules.php"
  "/var/www/lib/infractions.php"
  "/var/www/lib/auth.php"
  "/var/www/lib/db.php"
)

# Candidate kid-facing page locations (we’ll include whichever exist)
CANDIDATES=(
  "/var/www/app/dashboard.php"
  "/var/www/app/kid.php"
  "/var/www/app/today.php"
)

hdr "FILES: listing"
ls -la "${FILES[@]}" 2>/dev/null || true
for f in "${CANDIDATES[@]}"; do
  [[ -f "$f" ]] && ls -la "$f" || true
done

dump_file() {
  local f="$1"
  if need_file "$f"; then
    hdr "FILE: $f"
    # show full file (no truncation). sed handles empty files too.
    sed -n '1,99999p' "$f"
  fi
}

for f in "${FILES[@]}"; do dump_file "$f"; done
for f in "${CANDIDATES[@]}"; do [[ -f "$f" ]] && dump_file "$f"; done

hdr "DONE"
