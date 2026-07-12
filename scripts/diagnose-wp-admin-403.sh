#!/usr/bin/env bash
# Read-only diagnosis for LiteSpeed/Hostinger 403 on wp-admin/users.php.
# Saves evidence to: wp-content/pax-admin-403-diagnosis-YYYYMMDD-HHMMSS.txt
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
SITE="${PAX_SITE:-https://paxdesign.at}"
REPORT="${WP_ROOT}/wp-content/pax-admin-403-diagnosis-$(date +%Y%m%d-%H%M%S).txt"

section() { echo; echo "######## $1 ########"; }

LOG_PATTERNS='403|Forbidden|ModSecurity|mod_security|Access denied|client denied|Rule ID|Authorization|users\.php|wp-admin'

run_diagnosis() {
echo "=== PAXdesign wp-admin 403 diagnosis (read-only) ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "WP_ROOT: $WP_ROOT"
echo "SITE: $SITE"
echo "Host: $(hostname 2>/dev/null || echo unknown)"
echo

section "WordPress core + active plugins"
if command -v wp >/dev/null 2>&1; then
  wp core version --path="$WP_ROOT" 2>/dev/null || true
  echo "--- Active plugins ---"
  wp plugin list --path="$WP_ROOT" --status=active --fields=name,version 2>/dev/null || true
  echo "--- Security-related plugins (name match) ---"
  wp plugin list --path="$WP_ROOT" --fields=name,status,version 2>/dev/null \
    | grep -iE 'wordfence|ithemes|security|bulletproof|sucuri|litespeed|all-in-one|shield|wp-cerber|imunify|jetpack' || echo "(none matched by name)"
else
  echo "wp-cli not found"
fi

section "mu-plugins"
MU_DIR="$WP_ROOT/wp-content/mu-plugins"
if [[ -d "$MU_DIR" ]]; then
  ls -la "$MU_DIR" 2>/dev/null || true
  find "$MU_DIR" -maxdepth 1 -type f -name '*.php' -print 2>/dev/null || true
else
  echo "No mu-plugins directory"
fi

section ".htaccess — root"
HT_ROOT="$WP_ROOT/.htaccess"
if [[ -f "$HT_ROOT" ]]; then
  echo "EXISTS: $HT_ROOT ($(wc -l < "$HT_ROOT") lines, mode $(stat -c '%a %U:%G' "$HT_ROOT" 2>/dev/null || stat -f '%OLp %Su:%Sg' "$HT_ROOT"))"
  cat "$HT_ROOT"
else
  echo "MISSING: $HT_ROOT"
fi

section ".htaccess — wp-admin"
HT_ADMIN="$WP_ROOT/wp-admin/.htaccess"
if [[ -f "$HT_ADMIN" ]]; then
  echo "EXISTS: $HT_ADMIN ($(wc -l < "$HT_ADMIN") lines, mode $(stat -c '%a %U:%G' "$HT_ADMIN" 2>/dev/null || stat -f '%OLp %Su:%Sg' "$HT_ADMIN"))"
  cat "$HT_ADMIN"
else
  echo "MISSING: $HT_ADMIN"
fi

section ".htaccess — wp-content"
HT_CONTENT="$WP_ROOT/wp-content/.htaccess"
if [[ -f "$HT_CONTENT" ]]; then
  echo "EXISTS: $HT_CONTENT ($(wc -l < "$HT_CONTENT") lines)"
  cat "$HT_CONTENT"
else
  echo "MISSING: $HT_CONTENT"
fi

section "Permissions / ownership — wp-admin/users.php"
USERS_PHP="$WP_ROOT/wp-admin/users.php"
if [[ -f "$USERS_PHP" ]]; then
  ls -la "$USERS_PHP" 2>/dev/null || true
  stat "$USERS_PHP" 2>/dev/null || true
else
  echo "MISSING: $USERS_PHP"
fi
echo "--- wp-admin directory ---"
ls -lad "$WP_ROOT/wp-admin" 2>/dev/null || true

section "HTTP response headers — users.php (unauthenticated)"
echo "--- GET ${SITE}/wp-admin/users.php ---"
HDR_USERS=$(curl -sI "${SITE}/wp-admin/users.php" 2>/dev/null || true)
echo "$HDR_USERS"
echo "--- Analysis ---"
if echo "$HDR_USERS" | grep -qi 'x-powered-by:.*php'; then
  echo "PHP_REACHED: likely yes (x-powered-by: PHP present in response headers)"
else
  echo "PHP_REACHED: likely no (no x-powered-by: PHP — blocked before PHP or static error page)"
fi
if echo "$HDR_USERS" | grep -qi '^HTTP/.* 403'; then
  echo "STATUS: 403 Forbidden"
elif echo "$HDR_USERS" | grep -qi '^HTTP/.* 302'; then
  echo "STATUS: 302 redirect (WordPress auth redirect when not logged in)"
fi

section "HTTP probes — other admin paths (unauthenticated)"
PATHS=(
  "/wp-admin/"
  "/wp-admin/plugins.php"
  "/wp-admin/update-core.php"
  "/wp-admin/profile.php"
  "/wp-login.php"
)
for p in "${PATHS[@]}"; do
  echo "--- GET ${SITE}${p} ---"
  curl -sI "${SITE}${p}" 2>/dev/null | grep -iE '^HTTP/|location:|x-powered-by:|x-redirect-by:|server:|cf-cache-status:' || true
done

section "Authorization header probe — users.php"
echo "--- GET ${SITE}/wp-admin/users.php + Authorization: Basic (test) ---"
HDR_AUTH=$(curl -sI -H "Authorization: Basic dGVzdDp0ZXN0" "${SITE}/wp-admin/users.php" 2>/dev/null || true)
echo "$HDR_AUTH"
if echo "$HDR_AUTH" | grep -qi 'x-powered-by:.*php'; then
  echo "PHP_REACHED_WITH_AUTH_HEADER: yes"
else
  echo "PHP_REACHED_WITH_AUTH_HEADER: no"
fi

section "Log discovery"
declare -a ERROR_LOGS=()
declare -a ACCESS_LOGS=()
declare -a MODSEC_LOGS=()

while IFS= read -r -d '' f; do ERROR_LOGS+=("$f"); done < <(
  find "$HOME" -maxdepth 5 -type f \( -name 'error.log' -o -name 'error_log' -o -name 'stderr.log' \) 2>/dev/null | head -20 | tr '\n' '\0'
)
while IFS= read -r -d '' f; do ACCESS_LOGS+=("$f"); done < <(
  find "$HOME" -maxdepth 5 -type f \( -name 'access.log' -o -name 'access_log' \) 2>/dev/null | head -20 | tr '\n' '\0'
)
while IFS= read -r -d '' f; do MODSEC_LOGS+=("$f"); done < <(
  { find "$HOME" -maxdepth 5 -type f \( -iname '*modsec*' -o -iname '*imunify*' \) 2>/dev/null
    find /var/log /usr/local/lsws/logs -maxdepth 4 -type f \( -name 'modsec_audit.log' -o -name 'audit.log' \) 2>/dev/null; } \
    | head -20 | tr '\n' '\0'
) || true

# Common Hostinger paths
for p in \
  "$HOME/logs/error.log" \
  "$HOME/logs/access.log" \
  "$HOME/domains/paxdesign.at/logs/error.log" \
  "$HOME/domains/paxdesign.at/logs/access.log" \
  "$WP_ROOT/error_log"; do
  [[ -f "$p" ]] && ERROR_LOGS+=("$p")
done

echo "Error log candidates (${#ERROR_LOGS[@]}):"
printf '  %s\n' "${ERROR_LOGS[@]}" 2>/dev/null | sort -u || true
echo "Access log candidates (${#ACCESS_LOGS[@]}):"
printf '  %s\n' "${ACCESS_LOGS[@]}" 2>/dev/null | sort -u || true
echo "ModSecurity/Imunify candidates (${#MODSEC_LOGS[@]}):"
printf '  %s\n' "${MODSEC_LOGS[@]}" 2>/dev/null | sort -u || true

section "Error logs — grep: 403/users.php/ModSecurity/Authorization"
found_err=0
while IFS= read -r log; do
  [[ -z "$log" || ! -f "$log" ]] && continue
  matches=$(grep -iE "$LOG_PATTERNS" "$log" 2>/dev/null | tail -120 || true)
  if [[ -n "$matches" ]]; then
    echo "=== $log ==="
    echo "$matches"
    found_err=1
  fi
done < <(printf '%s\n' "${ERROR_LOGS[@]}" 2>/dev/null | sort -u)
[[ "$found_err" -eq 0 ]] && echo "(no matching lines in discovered error logs)"

section "Access logs — grep: users.php / 403"
found_acc=0
while IFS= read -r log; do
  [[ -z "$log" || ! -f "$log" ]] && continue
  matches=$(grep -iE 'users\.php| 403 |" 403 |wp-admin/users' "$log" 2>/dev/null | tail -80 || true)
  if [[ -n "$matches" ]]; then
    echo "=== $log ==="
    echo "$matches"
    found_acc=1
  fi
done < <(printf '%s\n' "${ACCESS_LOGS[@]}" 2>/dev/null | sort -u)
[[ "$found_acc" -eq 0 ]] && echo "(no matching lines in discovered access logs)"

section "ModSecurity / Imunify360 logs — grep"
found_mod=0
while IFS= read -r log; do
  [[ -z "$log" || ! -f "$log" ]] && continue
  matches=$(grep -iE "$LOG_PATTERNS" "$log" 2>/dev/null | tail -80 || true)
  if [[ -n "$matches" ]]; then
    echo "=== $log ==="
    echo "$matches"
    found_mod=1
  fi
done < <(printf '%s\n' "${MODSEC_LOGS[@]}" 2>/dev/null | sort -u)
[[ "$found_mod" -eq 0 ]] && echo "(no ModSecurity/Imunify log files with matches found)"

section "Interpretation"
cat <<'GUIDE'
Unauthenticated users.php:
  302 + x-powered-by: PHP + x-redirect-by: WordPress => request reached PHP; WordPress auth redirect (normal).
  403 + no x-powered-by: PHP + "Access to this resource on the server is denied!" => LiteSpeed/Hostinger/WAF/.htaccess block BEFORE WordPress.

If user sees 403 only when logged in:
  - Compare authenticated vs unauthenticated headers in browser DevTools.
  - Check wp-admin/.htaccess for Deny/FilesMatch rules.
  - Check ModSecurity for Authorization + cookie combinations.
  - Do NOT disable WAF globally; exclude only the confirmed false-positive rule.
GUIDE

echo
echo "REPORT_PATH=$REPORT"
echo "Diagnosis complete (read-only, no cache purge, no plugin changes)."
}

mkdir -p "${WP_ROOT}/wp-content"
run_diagnosis 2>&1 | tee "$REPORT"
