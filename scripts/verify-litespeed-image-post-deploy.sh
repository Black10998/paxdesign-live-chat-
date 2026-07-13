#!/usr/bin/env bash
# Post-deploy verification for v3.113.0 — LiteSpeed getimagesize hygiene + no REST log spam.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
PLUGIN="$WP_ROOT/wp-content/plugins/paxdesign-booking/paxdesign-booking.php"
COMPAT="$WP_ROOT/wp-content/plugins/paxdesign-booking/includes/class-paxdesign-litespeed-compat.php"
DEBUG_LOG="$WP_ROOT/wp-content/debug.log"
SITE="${PAX_SITE:-https://paxdesign.at}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"
BROKEN_AVIF='38319D43-77FD-42D8-91BA-69E23BE7879C-e1767119492655.avif'

fail() { echo "VERIFY_FAIL: $*"; exit 1; }
pass() { echo "VERIFY_OK: $*"; }

echo "=== Verify plugin 3.113.0 + LiteSpeed image hygiene ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "WP_ROOT: $WP_ROOT"

[[ -f "$PLUGIN" ]] || fail "Plugin not found at $PLUGIN"

VER=$(grep "define('PAXDESIGN_BOOKING_VERSION'" "$PLUGIN" | sed "s/.*'\([^']*\)'.*/\1/")
echo "Installed version: $VER"
[[ "$VER" == "3.113.0" ]] || fail "Expected 3.113.0, got $VER"
pass "Plugin version 3.113.0"

[[ -f "$COMPAT" ]] || fail "LiteSpeed compat class missing"
grep -q 'litespeed_media_ignore_remote_missing_sizes' "$COMPAT" || fail "Remote missing-sizes filter not registered"
pass "LiteSpeed compat class present"

if grep -q "$BROKEN_AVIF" "$PLUGIN" 2>/dev/null; then
  fail "Broken AVIF still hardcoded in paxdesign-booking.php"
fi
pass "Broken AVIF removed from plugin source"

LOG_BEFORE_LINES=0
LOG_BEFORE_BYTES=0
if [[ -f "$DEBUG_LOG" ]]; then
  LOG_BEFORE_BYTES=$(wc -c < "$DEBUG_LOG" | tr -d ' ')
  LOG_BEFORE_LINES=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
  echo "debug.log size before probe: ${LOG_BEFORE_BYTES} bytes (${LOG_BEFORE_LINES} lines)"
  BEFORE_GETIMG=$(grep -c 'getimagesize(' "$DEBUG_LOG" 2>/dev/null || echo 0)
  echo "Historical getimagesize() lines: $BEFORE_GETIMG"
fi

# Warm a few public pages (triggers LiteSpeed HTML optimization)
echo "Warming public pages..."
for path in "" "anmelden/" "kontakt/" "booking/"; do
  curl -sS -o /dev/null -w "page_${path:-home}=%{http_code}\n" --max-time 20 "${SITE}/${path}" || true
done
pass "Public page warm completed"

if [[ -n "$ADMIN_USER" && -n "$ADMIN_PASS" ]]; then
  echo "Probing live-admin REST with Basic Auth..."
  for i in 1 2 3; do
    curl -sS -o /dev/null -w "probe_$i=%{http_code}\n" --max-time 15 \
      -u "${ADMIN_USER}:${ADMIN_PASS}" \
      "${SITE}/wp-json/paxdesign/v1/live-admin/me" || true
  done
  pass "REST probes completed"
else
  echo "SKIP REST probes (PAX_ADMIN_USER / PAX_ADMIN_APP_PASSWORD not set)"
fi

sleep 3

if [[ -f "$DEBUG_LOG" ]]; then
  LOG_AFTER_BYTES=$(wc -c < "$DEBUG_LOG" | tr -d ' ')
  LOG_AFTER_LINES=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
  NEW_LINES=$((LOG_AFTER_LINES - LOG_BEFORE_LINES))
  echo "debug.log delta: $((LOG_AFTER_BYTES - LOG_BEFORE_BYTES)) bytes, ${NEW_LINES} new lines"
  if [[ "$NEW_LINES" -gt 0 ]]; then
    tail -n "$NEW_LINES" "$DEBUG_LOG" > /tmp/pax-debug-new-lines.txt
    NEW_GETIMG=$(grep -c 'getimagesize(' /tmp/pax-debug-new-lines.txt || true)
    NEW_GETIMG=${NEW_GETIMG:-0}
    NEW_MAPPED=$(grep -c 'email_mapped_to_login' /tmp/pax-debug-new-lines.txt || true)
    NEW_MAPPED=${NEW_MAPPED:-0}
    if [[ "$NEW_GETIMG" -gt 0 ]]; then
      echo "WARN: ${NEW_GETIMG} new getimagesize() warnings — run scripts/scan-litespeed-image-warnings.sh"
      grep 'getimagesize(' /tmp/pax-debug-new-lines.txt | tail -n 5
    else
      pass "No new getimagesize() warnings during probe"
    fi
    if [[ "$NEW_MAPPED" -gt 0 ]]; then
      fail "email_mapped_to_login in ${NEW_MAPPED} NEW debug.log lines"
    fi
  else
    pass "debug.log did not grow during probe"
  fi
else
  pass "No debug.log file (production OK)"
fi

echo "=== Verification complete ==="
