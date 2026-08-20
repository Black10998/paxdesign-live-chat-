#!/usr/bin/env bash
# Insert or replace the PAXDesign security FilesMatch block in WordPress .htaccess
# and restore /wp-json/ pretty permalinks used by the iOS app.
# Pure bash (no Python). Idempotent. WP_PATH required.
#
# Do NOT append a second "RewriteEngine On" after the WordPress block.
# LiteSpeed treats that as a new rewrite context, drops the WP front-controller
# rules, and /wp-json/* then 404s at the edge — which disconnects the app.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH is required}"
ROOT="${WP_PATH%/}"
HTACCESS="${ROOT}/.htaccess"
SEC_START='# BEGIN PAXDesign security'
SEC_END='# END PAXDesign security'
REST_START='# BEGIN PAXDesign REST permalinks'
REST_END='# END PAXDesign REST permalinks'

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

strip_marked_block() {
  local start="$1"
  local end="$2"
  local skip=0
  local line
  while IFS= read -r line || [ -n "$line" ]; do
    if [ "$line" = "$start" ]; then
      skip=1
      continue
    fi
    if [ "$skip" -eq 1 ]; then
      if [ "$line" = "$end" ]; then
        skip=0
      fi
      continue
    fi
    printf '%s\n' "$line"
  done
}

REST_BLOCK="${REST_START}
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^wp-json/?$ /index.php?rest_route=/ [QSA,L]
RewriteRule ^wp-json/(.*)\$ /index.php?rest_route=/\$1 [QSA,L]
</IfModule>
${REST_END}
"

SEC_BLOCK="${SEC_START}
<FilesMatch \"^(readme\\.html|readme\\.txt|license\\.txt|llms\\.txt)$\">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>
<IfModule mod_headers.c>
  Header always unset X-Powered-By
  SetEnvIf Origin \"^https://(www\\.)?paxdesign\\.at$\" PAX_CORS_ORIGIN=\$0
  Header always unset Access-Control-Allow-Origin
  Header always unset Access-Control-Allow-Credentials
  Header always set Access-Control-Allow-Origin \"%{PAX_CORS_ORIGIN}e\" env=PAX_CORS_ORIGIN
  Header always set Access-Control-Allow-Credentials \"true\" env=PAX_CORS_ORIGIN
  Header always append Vary Origin
</IfModule>
${SEC_END}
"

TMP="$(mktemp "${ROOT}/.htaccess.pax.XXXXXX")"
cleanup() { rm -f "$TMP"; }
trap cleanup EXIT

if [ -f "$HTACCESS" ]; then
  strip_marked_block "$SEC_START" "$SEC_END" < "$HTACCESS" \
    | strip_marked_block "$REST_START" "$REST_END" \
    > "$TMP.body"
else
  : > "$TMP.body"
fi

{
  printf '%s\n' "$REST_BLOCK"
  if [ -s "$TMP.body" ]; then
    cat "$TMP.body"
    printf '\n'
  fi
  printf '%s\n' "$SEC_BLOCK"
} > "$TMP"
rm -f "$TMP.body"

mv "$TMP" "$HTACCESS"
trap - EXIT

echo "updated PAXDesign REST permalinks and security FilesMatch in .htaccess"
grep -Fqx "$REST_START" "$HTACCESS"
grep -Fqx "$SEC_START" "$HTACCESS"
grep -q 'rest_route' "$HTACCESS"
grep -q 'FilesMatch' "$HTACCESS"
# Trailing security block must not start a new LiteSpeed rewrite context.
if awk '
  $0 == "# BEGIN PAXDesign security" { in_sec=1; next }
  $0 == "# END PAXDesign security" { in_sec=0; next }
  in_sec && $0 ~ /RewriteEngine[[:space:]]+On/ { found=1 }
  END { exit found ? 0 : 1 }
' "$HTACCESS"; then
  echo "ERROR: security block still contains RewriteEngine On" >&2
  exit 1
fi
test ! -f "${ROOT}/readme.html"
test ! -f "${ROOT}/llms.txt"
echo "OK: disclosure files removed, /wp-json/ rewrite restored, htaccess patched"
