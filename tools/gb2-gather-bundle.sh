#!/usr/bin/env bash
set -euo pipefail

OUT_DIR="/var/www/data/support"
TS="$(date -u +%Y%m%dT%H%M%SZ)"
BUNDLE="$OUT_DIR/gb2_gather_${TS}.tar.gz"
WORK="$(mktemp -d)"

cleanup(){ rm -rf "$WORK"; }
trap cleanup EXIT

mkdir -p "$OUT_DIR"

# --- capture environment / git info ---
{
  echo "DATE_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "HOST=$(hostname || true)"
  echo "PHP=$(php -v 2>/dev/null | head -n 1 || true)"
} > "$WORK/env.txt"

if command -v git >/dev/null 2>&1 && [[ -d /var/www/.git ]]; then
  (cd /var/www && {
    git rev-parse --short HEAD
    git log -1 --oneline
    git status --porcelain=v1
  }) > "$WORK/git.txt" || true
else
  echo "git not available or /var/www not a git repo" > "$WORK/git.txt"
fi

# --- capture DB schema (NOT the full DB content) ---
DB="/var/www/data/app.sqlite"
sqlite3 "$DB" <<'SQL' > "$WORK/db_schema.txt"
.headers on
.mode column
PRAGMA user_version;
.tables
SELECT name, sql
FROM sqlite_master
WHERE type='table' AND name IN (
  'kids','privileges',
  'infraction_defs','infraction_strikes','infraction_events'
)
ORDER BY name;
SQL

sqlite3 "$DB" <<'SQL' > "$WORK/db_counts.txt"
.headers on
.mode column
SELECT 'kids' AS tbl, COUNT(*) AS n FROM kids
UNION ALL SELECT 'privileges', COUNT(*) FROM privileges
UNION ALL SELECT 'infraction_defs', COUNT(*) FROM infraction_defs
UNION ALL SELECT 'infraction_strikes', COUNT(*) FROM infraction_strikes
UNION ALL SELECT 'infraction_events', COUNT(*) FROM infraction_events;
SQL

# --- copy required files ---
mkdir -p "$WORK/var/www"
copy_if_exists() {
  local src="$1"
  local dst="$WORK${src}"
  if [[ -f "$src" ]]; then
    mkdir -p "$(dirname "$dst")"
    cp -a "$src" "$dst"
  fi
}

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
  "/var/www/app/dashboard.php"
  "/var/www/app/kid.php"
  "/var/www/app/today.php"
)

for f in "${FILES[@]}"; do
  copy_if_exists "$f"
done

# manifest
{
  echo "Bundle created: $TS"
  echo
  echo "Included files (if present):"
  for f in "${FILES[@]}"; do
    [[ -f "$f" ]] && echo "  $f"
  done
} > "$WORK/manifest.txt"

tar -czf "$BUNDLE" -C "$WORK" .
echo "OK: created $BUNDLE"
