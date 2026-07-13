#!/usr/bin/env bash
# Reproduce: iOS app REST burst → probe wp-admin/users.php
# Triggered manually after PAX_ADMIN_USER + PAX_ADMIN_APP_PASSWORD secrets were added.
# Read-only. Does not modify server config, plugins, or cache.
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
USER="${PAX_ADMIN_USER:-}"
PASS="${PAX_ADMIN_APP_PASSWORD:-}"
REPORT="${1:-./app-burst-reproduction.txt}"
BURST_ROUNDS="${BURST_ROUNDS:-20}"
BURST_SLEEP="${BURST_SLEEP:-0.35}"

exec > >(tee "$REPORT") 2>&1

section() { echo; echo "######## $1 ########"; }

probe_admin() {
  local label="$1"
  echo "--- $label: GET ${SITE}/wp-admin/users.php ---"
  local headers
  headers=$(curl -sI "${SITE}/wp-admin/users.php" 2>/dev/null || true)
  echo "$headers" | grep -iE '^HTTP/|x-powered-by:|x-redirect-by:|location:|server:|cf-ray:' || true
  if echo "$headers" | grep -qi '^HTTP/.* 403'; then
    if echo "$headers" | grep -qi 'x-powered-by:.*php'; then
      echo "RESULT: 403 WITH PHP (WordPress/plugin layer)"
    else
      echo "RESULT: 403 WITHOUT PHP (LiteSpeed/WAF/edge block)"
    fi
  elif echo "$headers" | grep -qi '^HTTP/.* 302'; then
    echo "RESULT: 302 redirect (normal unauthenticated wp-admin)"
  fi
}

rest_call() {
  local method="$1"
  local path="$2"
  local body="${3:-}"
  local url="${SITE}/wp-json/paxdesign/v1/live-admin/${path}"
  url="${url}?_=$(date +%s)"
  local args=(-sS -o /dev/null -w "%{http_code}" -X "$method" -H "Accept: application/json" -H "Cache-Control: no-cache")
  if [[ -n "$USER" && -n "$PASS" ]]; then
    args+=(-u "${USER}:${PASS}")
  fi
  if [[ -n "$body" ]]; then
    args+=(-H "Content-Type: application/json" --data "$body")
  fi
  local code
  code=$(curl "${args[@]}" "$url" 2>/dev/null || echo "000")
  echo "$method $path → HTTP $code"
}

simulate_app_startup_burst() {
  local round="$1"
  echo "=== Burst round $round (simulating iOS login + startLoggedInServices) ==="
  rest_call GET "me"
  rest_call GET "platform/sync"
  rest_call GET "sessions"
  rest_call GET "team/sessions"
  rest_call GET "team/requests/pending"
  rest_call POST "team/presence" '{"online":true}'
  rest_call GET "conversations/sync"
  rest_call GET "platform/sync"
  rest_call POST "devices/heartbeat" '{"device_id":"diag-sim","device_name":"Diagnosis Simulator"}'
  rest_call GET "devices"
}

section "App-then-admin 403 reproduction"
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "Site: $SITE"
echo "Burst: ${BURST_ROUNDS} rounds, sleep ${BURST_SLEEP}s"
echo "Auth: $([ -n "$USER" ] && echo 'Application Password configured' || echo 'NO credentials — burst will return 401')"

section "Baseline — users.php BEFORE app burst"
probe_admin "before_burst"

if [[ -z "$USER" || -z "$PASS" ]]; then
  section "SKIP burst — missing PAX_ADMIN_USER or PAX_ADMIN_APP_PASSWORD"
else
  section "Simulating iOS REST traffic (Authorization: Basic on every call)"
  for i in $(seq 1 "$BURST_ROUNDS"); do
    simulate_app_startup_burst "$i"
    sleep "$BURST_SLEEP"
  done
fi

section "Probe — users.php IMMEDIATELY AFTER app burst"
probe_admin "after_burst"

section "Probe — other admin paths after burst"
for path in "/wp-admin/" "/wp-admin/plugins.php" "/wp-login.php"; do
  echo "--- GET ${SITE}${path} ---"
  curl -sI "${SITE}${path}" 2>/dev/null | grep -iE '^HTTP/|x-powered-by:|location:' || true
done

section "Probe — users.php with Authorization: Basic (browser credential leak simulation)"
echo "--- GET users.php + Authorization: Basic (test credentials) ---"
curl -sI -H "Authorization: Basic dGVzdDp0ZXN0" "${SITE}/wp-admin/users.php" 2>/dev/null \
  | grep -iE '^HTTP/|x-powered-by:|x-redirect-by:|location:' || true

section "Interpretation"
cat <<'GUIDE'
If after_burst shows:
  403 WITHOUT x-powered-by: PHP → WAF/LiteSpeed rate-limit after REST burst (matches "after using app").
  403 WITH x-powered-by: PHP → WordPress auth/capability issue (list_users), not edge block.
  302 + x-powered-by: PHP → burst did NOT reproduce server block in this test IP/window.

iOS app facts:
  - Uses ephemeral URLSession (no WordPress cookies).
  - Sends Authorization: Basic on EVERY live-admin REST call.
  - Does NOT send Origin/Referer; default CFNetwork User-Agent.
  - Startup fires ~10+ endpoints; polling can exceed 6 req/s while active.

Plugin facts (v3.110.0 production):
  - bootstrap_basic_auth runs on ALL requests (not only /wp-json/).
  - Root .htaccess passes HTTP_AUTHORIZATION to PHP on every request.
  - Device sessions do NOT clear wordpress_logged_in cookies.
GUIDE

echo
echo "REPORT_SAVED=$REPORT"
