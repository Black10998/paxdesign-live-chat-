#!/usr/bin/env bash
# Diagnose LiteSpeed/Hostinger 403 on WordPress wp-admin pages (e.g. users.php).
# Run from Hostinger SSH after: cd ~/domains/paxdesign.at/public_html  (or your WP_PATH)
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
SITE="${PAX_SITE:-https://paxdesign.at}"
REPORT="${WP_ROOT}/wp-content/pax-admin-403-diagnosis-$(date +%Y%m%d-%H%M%S).txt"

exec > >(tee -a "$REPORT") 2>&1

echo "=== PAXdesign wp-admin 403 diagnosis ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "WP_ROOT: $WP_ROOT"
echo "SITE: $SITE"
echo

section() { echo; echo "######## $1 ########"; }

section "WordPress + plugin versions"
if command -v wp >/dev/null 2>&1; then
  wp core version --path="$WP_ROOT" || true
  wp plugin list --path="$WP_ROOT" --fields=name,status,version | grep -i pax || true
  wp user list --path="$WP_ROOT" --role=administrator --fields=ID,user_login,user_email --format=table || true
else
  echo "wp-cli not found — install or run from Hostinger WordPress SSH."
fi

section ".htaccess files (root + wp-admin + wp-content)"
for f in "$WP_ROOT/.htaccess" "$WP_ROOT/wp-admin/.htaccess" "$WP_ROOT/wp-content/.htaccess"; do
  echo "--- $f ---"
  if [[ -f "$f" ]]; then
    echo "EXISTS ($(wc -l < "$f") lines)"
    grep -nE 'Deny|Require all denied|FilesMatch|users\.php|wp-admin|AuthType|ModSecurity|RewriteRule' "$f" || echo "(no suspicious directives matched)"
  else
    echo "MISSING"
  fi
done

section "mu-plugins"
if [[ -d "$WP_ROOT/wp-content/mu-plugins" ]]; then
  ls -la "$WP_ROOT/wp-content/mu-plugins" || true
else
  echo "No mu-plugins directory"
fi

section "Plugin isolation test (rename folder)"
PLUGIN_DIR="$WP_ROOT/wp-content/plugins/paxdesign-booking"
DISABLED_DIR="$WP_ROOT/wp-content/plugins/paxdesign-booking.disabled-diagnosis"
if [[ -d "$PLUGIN_DIR" ]]; then
  echo "To test without the plugin, run manually:"
  echo "  wp plugin deactivate paxdesign-booking --path='$WP_ROOT'"
  echo "  mv '$PLUGIN_DIR' '$DISABLED_DIR'"
  echo "  # open $SITE/wp-admin/users.php in a private window while logged in"
  echo "  mv '$DISABLED_DIR' '$PLUGIN_DIR' && wp plugin activate paxdesign-booking --path='$WP_ROOT'"
else
  echo "Plugin directory not found: $PLUGIN_DIR"
fi

section "HTTP probes (unauthenticated — expect 302 to wp-login.php)"
PATHS=(
  "/wp-admin/"
  "/wp-admin/users.php"
  "/wp-admin/plugins.php"
  "/wp-admin/update-core.php"
  "/wp-admin/profile.php"
  "/wp-login.php"
)
for p in "${PATHS[@]}"; do
  echo "--- GET $SITE$p ---"
  curl -sI "$SITE$p" | grep -iE 'HTTP/|location:|x-powered-by:|server:|cf-ray:' || true
done

section "Authorization header probe (WAF/ModSecurity trigger test)"
echo "--- GET $SITE/wp-admin/users.php with Authorization: Basic ---"
curl -sI -H "Authorization: Basic dGVzdDp0ZXN0" "$SITE/wp-admin/users.php" \
  | grep -iE 'HTTP/|location:|x-powered-by:|server:' || true

section "Recent web server error log (last 80 lines mentioning 403/users/wp-admin)"
LOG_CANDIDATES=(
  "$HOME/logs/error.log"
  "$HOME/logs/paxdesign.at/error.log"
  "$HOME/domains/paxdesign.at/logs/error.log"
  "/var/log/apache2/error.log"
  "/var/log/httpd/error_log"
)
found_log=0
for log in "${LOG_CANDIDATES[@]}"; do
  if [[ -f "$log" ]]; then
    echo "--- $log ---"
    grep -iE '403|users\.php|wp-admin|ModSecurity|mod_security|denied' "$log" | tail -80 || echo "(no matches)"
    found_log=1
  fi
done
if [[ "$found_log" -eq 0 ]]; then
  echo "No standard error log found. In hPanel: Websites → Manage → Logs → Error log."
fi

section "ModSecurity audit log (if present)"
MODSEC_CANDIDATES=(
  "$HOME/logs/modsec_audit.log"
  "/var/log/modsec_audit.log"
  "/usr/local/lsws/logs/audit.log"
)
found_modsec=0
for log in "${MODSEC_CANDIDATES[@]}"; do
  if [[ -f "$log" ]]; then
    echo "--- $log ---"
    grep -iE 'users\.php|wp-admin|403' "$log" | tail -40 || echo "(no matches)"
    found_modsec=1
  fi
done
if [[ "$found_modsec" -eq 0 ]]; then
  echo "ModSecurity audit log not found in common paths."
  echo "Ask Hostinger support for the Rule ID when accessing /wp-admin/users.php."
fi

section "LiteSpeed cache"
if command -v wp >/dev/null 2>&1; then
  wp litespeed-purge all --path="$WP_ROOT" 2>/dev/null || wp cache flush --path="$WP_ROOT" || true
  echo "Cache flush attempted."
fi

section "Interpretation guide"
cat <<'GUIDE'
- 302 + x-powered-by: PHP/... + location: wp-login.php  => WordPress (normal, not logged in).
- 403 + HTML "Access to this resource on the server is denied!" WITHOUT x-powered-by: PHP
  => LiteSpeed/Hostinger/WAF/ModSecurity/.htaccess — NOT a WordPress capability message.
- If 403 only when logged in: check wp-admin/.htaccess, ModSecurity, IP block, browser-stored HTTP Basic Auth.
- If 403 disappears after renaming paxdesign-booking: plugin hook — re-enable and bisect.
- If 403 persists with plugin disabled: server/WAF/.htaccess — not the plugin.
GUIDE

echo
echo "Report saved: $REPORT"
