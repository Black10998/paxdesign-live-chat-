#!/usr/bin/env bash
# Compare browser vs iOS-like HTTP profiles against live-admin REST (403 diagnosis).
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
USER="${PAX_ADMIN_USER:-}"
PASS="${PAX_ADMIN_APP_PASSWORD:-}"
IOS_UA="PAXDesignLiveChat/1.61.0 (iOS; Build 100; CFNetwork)"
BROWSER_UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36"

probe() {
  local label="$1"
  local ua="$2"
  local auth="$3"
  local path="$4"
  local url="${SITE}/wp-json/paxdesign/v1/live-admin/${path}?_=$(date +%s)"
  local tmp_body tmp_hdr
  tmp_body="$(mktemp)"
  tmp_hdr="$(mktemp)"
  local args=(-sS -D "$tmp_hdr" -o "$tmp_body" --max-time 15 -H "Accept: application/json" -H "Cache-Control: no-cache" -A "$ua")
  if [[ "$auth" == "basic" && -n "$USER" && -n "$PASS" ]]; then
    args+=(-u "${USER}:${PASS}")
  fi
  local code
  code=$(curl "${args[@]}" -w '%{http_code}' "$url" 2>/dev/null | tail -c 3 || echo "000")
  local cf_ray server powered retry body
  cf_ray=$(grep -im1 '^cf-ray:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || echo "?")
  server=$(grep -im1 '^server:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || echo "?")
  powered=$(grep -im1 '^x-powered-by:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || echo "none")
  retry=$(grep -im1 '^retry-after:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || echo "?")
  body=$(head -c 200 "$tmp_body" 2>/dev/null | tr '\n' ' ' || true)
  rm -f "$tmp_body" "$tmp_hdr"
  echo "PROFILE $label|path=$path|code=$code|ua=${ua:0:40}|auth=$auth|server=$server|php=$powered|cf-ray=$cf_ray|retry=$retry"
  echo "  body=$body"
}

echo "=== iOS vs Browser HTTP profile trace ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "Site: $SITE"
echo "Egress IP: $(curl -sS --max-time 5 https://api.ipify.org 2>/dev/null || echo unknown)"
echo

echo "--- Public homepage (no auth) ---"
probe "browser-home" "$BROWSER_UA" "none" "../../../../../" 2>/dev/null || true
curl -sS -o /dev/null -w "GET / → %{http_code}\n" -A "$BROWSER_UA" "${SITE}/" || true

echo
echo "--- live-admin/me: browser UA, no auth (expect 401) ---"
probe "browser-noauth" "$BROWSER_UA" "none" "me"

echo
echo "--- live-admin/me: iOS UA + Basic Auth (app login) ---"
probe "ios-basic" "$IOS_UA" "basic" "me"

echo
echo "--- iOS startup burst (5 rounds) ---"
if [[ -z "$USER" || -z "$PASS" ]]; then
  echo "SKIP burst — no PAX_ADMIN_USER/PAX_ADMIN_APP_PASSWORD"
else
  for i in 1 2 3 4 5; do
    for p in me sessions team/sessions conversations/sync events/stream platform/sync; do
      probe "ios-burst-$i" "$IOS_UA" "basic" "$p"
    done
    sleep 0.3
  done
  echo "--- After burst: GET / (site-wide block check) ---"
  curl -sS -o /dev/null -w "GET / → %{http_code}\n" -A "$BROWSER_UA" "${SITE}/" || true
fi

echo
cat <<'GUIDE'
Interpretation:
  - Website browse: HTML/JS, cookies, no Authorization on /. No live-admin burst.
  - iOS app: ephemeral session (no WP cookies), Authorization: Basic on EVERY live-admin call,
    no X-WP-Nonce, default CFNetwork User-Agent, parallel SSE + REST → edge rate limit on client IP.
  - 403 + no x-powered-by + "Access to this resource..." = LiteSpeed/Hostinger WAF (not WordPress REST).
  - Capture cf-ray from app: Settings → Netzwerk-Diagnose → Letzter 403 (Build 100+).
GUIDE
