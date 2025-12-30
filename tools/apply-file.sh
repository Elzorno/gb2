#!/usr/bin/env bash
set -euo pipefail

# apply-file.sh — safe, atomic file replacement from stdin (root-native, no sudo)
#
# Usage:
#   /var/www/tools/apply-file.sh /var/www/app/login.php <<'PHP'
#   ...full file contents...
#   PHP
#
# Behavior:
# - Creates a timestamped backup alongside the target: <file>.bak.<timestamp>
# - Writes new content to a temp file and moves into place atomically
# - If target ends with .php, runs: php -l <tempfile> before applying
# - Sets ownership to www-data:www-data for files under /var/www if that user exists
# - Sets perms to 0644 for files (does not change directory perms)

TARGET="${1:-}"
[[ -n "$TARGET" ]] || { echo "ERROR: missing target path" >&2; exit 2; }

TS="$(date +%F_%H%M%S)"
DIR="$(dirname "$TARGET")"
BASE="$(basename "$TARGET")"
TMP="$(mktemp "$DIR/.${BASE}.tmp.XXXXXX")"
BAK="${TARGET}.bak.${TS}"

cleanup() { rm -f "$TMP" 2>/dev/null || true; }
trap cleanup EXIT

# Read stdin into temp
cat > "$TMP"

# If PHP file, lint before apply
if [[ "$TARGET" == *.php ]]; then
  php -l "$TMP" >/dev/null
fi

# Backup existing if present
if [[ -e "$TARGET" ]]; then
  cp -a -- "$TARGET" "$BAK"
fi

# Atomic replace
mv -f -- "$TMP" "$TARGET"

# Ownership: only if www-data exists
if [[ "$TARGET" == /var/www/* ]] && getent passwd www-data >/dev/null 2>&1; then
  chown www-data:www-data "$TARGET" || true
fi

# Ensure readable
chmod 0644 "$TARGET" || true

echo "OK: applied $TARGET"
if [[ -e "$BAK" ]]; then
  echo "   backup: $BAK"
fi
