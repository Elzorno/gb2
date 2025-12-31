#!/usr/bin/env bash
set -euo pipefail

# Desired limits (tune as you like)
UPLOAD_MAX="24M"
POST_MAX="26M"
MEM_LIMIT="256M"
MAX_FILE_UPLOADS="20"
MAX_EXEC_TIME="60"
MAX_INPUT_TIME="60"

PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
APACHE_INI_DIR="/etc/php/${PHPV}/apache2/conf.d"
DROP_IN="${APACHE_INI_DIR}/99-gb2-uploads.ini"

echo "Detected PHP version: ${PHPV}"
echo "Apache PHP conf.d dir: ${APACHE_INI_DIR}"

if [[ ! -d "${APACHE_INI_DIR}" ]]; then
  echo "ERROR: ${APACHE_INI_DIR} not found. Is php/apache2 SAPI installed for this PHP version?"
  exit 1
fi

echo "Writing PHP drop-in: ${DROP_IN}"
install -d -m 0755 "${APACHE_INI_DIR}"

cat >"${DROP_IN}" <<EOF
; GB2 uploads override (drop-in)
file_uploads = On
upload_max_filesize = ${UPLOAD_MAX}
post_max_size = ${POST_MAX}
memory_limit = ${MEM_LIMIT}
max_file_uploads = ${MAX_FILE_UPLOADS}
max_execution_time = ${MAX_EXEC_TIME}
max_input_time = ${MAX_INPUT_TIME}
EOF

echo "PHP drop-in written."
echo

# Optional Apache limit (only matters if Apache is rejecting large requests)
if command -v apache2ctl >/dev/null 2>&1; then
  AP_CONF_AVAIL="/etc/apache2/conf-available"
  AP_CONF="${AP_CONF_AVAIL}/gb2-uploads.conf"

  echo "Writing optional Apache conf: ${AP_CONF}"
  install -d -m 0755 "${AP_CONF_AVAIL}"

  cat >"${AP_CONF}" <<'EOF'
# GB2 uploads override
# If you see 413 errors from Apache, raise this.
# 0 means unlimited; set to e.g. 31457280 for 30MB.
LimitRequestBody 0
EOF

  if command -v a2enconf >/dev/null 2>&1; then
    a2enconf gb2-uploads >/dev/null
  fi

  echo "Reloading Apache..."
  systemctl reload apache2 || systemctl restart apache2
else
  echo "apache2ctl not found; skipped Apache LimitRequestBody config."
fi

echo
echo "Done. Verify effective settings with:"
echo "  php -i | egrep -i 'upload_max_filesize|post_max_size|memory_limit|max_file_uploads|max_execution_time|max_input_time'"
echo "Then test an upload from iPhone."
