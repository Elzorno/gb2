#!/usr/bin/env bash
set -euo pipefail

echo "== PHP SAPI / Version =="
php -v | head -n 2
echo

echo "== PHP ini files (CLI) =="
php --ini
echo

echo "== Effective PHP upload-related settings (CLI) =="
php -i | egrep -i '^(file_uploads|max_execution_time|max_input_time|max_file_uploads|memory_limit|post_max_size|upload_max_filesize) =>'
echo

echo "== Apache presence =="
if command -v apache2ctl >/dev/null 2>&1; then
  apache2ctl -V | egrep -i 'server version|server mpm|httpd_root|server_config_file' || true
  echo
  echo "== Apache loaded modules (grep body-related) =="
  apache2ctl -M 2>/dev/null | egrep -i 'reqtimeout|rewrite|php|mpm|http2' || true
  echo
  echo "== Apache config: any LimitRequestBody =="
  grep -R --line-number --no-messages -E 'LimitRequestBody' /etc/apache2 2>/dev/null || true
else
  echo "apache2ctl not found."
fi
echo

echo "== Nginx presence (if any) =="
if command -v nginx >/dev/null 2>&1; then
  nginx -V 2>&1 | head -n 1 || true
  echo
  echo "== Nginx config: any client_max_body_size =="
  grep -R --line-number --no-messages -E 'client_max_body_size' /etc/nginx 2>/dev/null || true
else
  echo "nginx not found."
fi
