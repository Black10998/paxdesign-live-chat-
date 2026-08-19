#!/usr/bin/env bash
# Insert or replace the PAXDesign security block in WordPress .htaccess
# and remove public disclosure files from disk. Pure bash (no python).
# Intended to run on the production host with WP_PATH set. Idempotent.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH is required}"
ROOT="${WP_PATH%/}"
HTACCESS="${ROOT}/.htaccess"
START='# BEGIN PAXDesign security'
END='# END PAXDesign security'

echo "Cleaning public disclosure files under ${ROOT}"
rm -f \
  "${ROOT}/readme.html" \
  "${ROOT}/license.txt" \
  "${ROOT}/llms.txt" \
  "${ROOT}/wp-config-sample.php"

if [ -d "${ROOT}/wp-content/plugins" ]; then
  find "${ROOT}/wp-content/plugins" -type f -name 'readme.txt' -delete 2>/dev/null || true
fi
if [ -d "${ROOT}/wp-content/themes" ]; then
  find "${ROOT}/wp-content/themes" -type f -name 'readme.txt' -delete 2>/dev/null || true
fi

BLOCK="${START}
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteRule ^readme\\.html$ - [F,L]
  RewriteRule ^license\\.txt$ - [F,L]
  RewriteRule ^llms\\.txt$ - [F,L]
  RewriteRule (^|/)readme\\.txt$ - [F,L]
</IfModule>
<IfModule mod_headers.c>
  Header always unset X-Powered-By
  SetEnvIf Origin \"^https://(www\\.)?paxdesign\\.at$\" PAX_CORS_ORIGIN=\$0
  Header always unset Access-Control-Allow-Origin
  Header always unset Access-Control-Allow-Credentials
  Header always set Access-Control-Allow-Origin \"%{PAX_CORS_ORIGIN}e\" env=PAX_CORS_ORIGIN
  Header always set Access-Control-Allow-Credentials \"true\" env=PAX_CORS_ORIGIN
  Header always append Vary Origin
</IfModule>
${END}
"

TMP="$(mktemp "${ROOT}/.htaccess.pax.XXXXXX")"
cleanup() { rm -f "$TMP"; }
trap cleanup EXIT

if [ -f "$HTACCESS" ] && grep -Fqx "$START" "$HTACCESS"; then
  skip=0
  while IFS= read -r line || [ -n "$line" ]; do
    if [ "$line" = "$START" ]; then
      skip=1
      continue
    fi
    if [ "$skip" -eq 1 ]; then
      if [ "$line" = "$END" ]; then
        skip=0
      fi
      continue
    fi
    printf '%s\n' "$line"
  done < "$HTACCESS" > "$TMP"
else
  if [ -f "$HTACCESS" ]; then
    cat "$HTACCESS" > "$TMP"
    printf '\n' >> "$TMP"
  else
    : > "$TMP"
  fi
fi

printf '%s\n' "$BLOCK" >> "$TMP"
mv "$TMP" "$HTACCESS"
trap - EXIT
echo "updated PAXDesign security block in .htaccess"
grep -Fqx "$START" "$HTACCESS"
test ! -f "${ROOT}/readme.html"
test ! -f "${ROOT}/llms.txt"
echo "OK: disclosure files removed and htaccess patched"
