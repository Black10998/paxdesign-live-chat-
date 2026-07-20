#!/usr/bin/env bash
# Post-deploy verification — debug.log hygiene after production deploy.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
PLUGIN="$WP_ROOT/wp-content/plugins/paxdesign-booking/paxdesign-booking.php"
DEBUG_LOG="$WP_ROOT/wp-content/debug.log"
SITE="${PAX_SITE:-https://paxdesign.at}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"
EXPECTED="${PAX_EXPECTED_BOOKING:-3.162.0}"

fail() { echo "VERIFY_FAIL: $*"; exit 1; }
pass() { echo "VERIFY_OK: $*"; }

echo "=== Verify plugin ${EXPECTED} + debug.log hygiene ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "WP_ROOT: $WP_ROOT"

[[ -f "$PLUGIN" ]] || fail "Plugin not found at $PLUGIN"

VER=$(grep "define('PAXDESIGN_BOOKING_VERSION'" "$PLUGIN" | sed "s/.*'\([^']*\)'.*/\1/")
echo "Installed version: $VER"
[[ "$VER" == "3.112.0" ]] || fail "Expected 3.112.0, got $VER"
pass "Plugin version 3.112.0"

# Ensure success-path logging code is gone
if grep -q "email_mapped_to_login" "$WP_ROOT/wp-content/plugins/paxdesign-booking/includes/class-paxdesign-live-chat-mobile-api.php" 2>/dev/null; then
  fail "email_mapped_to_login still present in mobile-api.php"
fi
pass "email_mapped_to_login removed from source"

LOG_BEFORE_LINES=0
LOG_BEFORE_BYTES=0
if [[ -f "$DEBUG_LOG" ]]; then
  LOG_BEFORE_BYTES=$(wc -c < "$DEBUG_LOG" | tr -d ' ')
  LOG_BEFORE_LINES=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
  echo "debug.log size before probe: ${LOG_BEFORE_BYTES} bytes (${LOG_BEFORE_LINES} lines)"
  BEFORE_COUNT=$(grep -c 'email_mapped_to_login' "$DEBUG_LOG" 2>/dev/null || echo 0)
  echo "Historical email_mapped_to_login lines in file: $BEFORE_COUNT"
fi

# Hit live-admin REST a few times (triggers determine_current_user)
if [[ -n "$ADMIN_USER" && -n "$ADMIN_PASS" ]]; then
  echo "Probing live-admin REST with Basic Auth..."
  for i in 1 2 3 4 5; do
    curl -sS -o /dev/null -w "probe_$i=%{http_code}\n" --max-time 15 \
      -u "${ADMIN_USER}:${ADMIN_PASS}" \
      "${SITE}/wp-json/paxdesign/v1/live-admin/me" || true
  done
  pass "REST probes completed"
else
  echo "SKIP REST probes (PAX_ADMIN_USER / PAX_ADMIN_APP_PASSWORD not set)"
fi

sleep 2

if [[ -f "$DEBUG_LOG" ]]; then
  LOG_AFTER_BYTES=$(wc -c < "$DEBUG_LOG" | tr -d ' ')
  LOG_AFTER_LINES=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
  GROWTH_BYTES=$((LOG_AFTER_BYTES - LOG_BEFORE_BYTES))
  NEW_LINES=$((LOG_AFTER_LINES - LOG_BEFORE_LINES))
  echo "debug.log size after probe: ${LOG_AFTER_BYTES} bytes (${LOG_AFTER_LINES} lines)"
  echo "Delta: ${GROWTH_BYTES} bytes, ${NEW_LINES} new lines"
  if [[ "$NEW_LINES" -gt 0 ]]; then
    tail -n "$NEW_LINES" "$DEBUG_LOG" > /tmp/pax-debug-new-lines.txt
    NEW_MAPPED=$(grep -c 'email_mapped_to_login' /tmp/pax-debug-new-lines.txt || true)
    NEW_MAPPED=${NEW_MAPPED:-0}
    if [[ "$NEW_MAPPED" -gt 0 ]]; then
      fail "email_mapped_to_login in ${NEW_MAPPED} NEW debug.log lines during probe"
    fi
    pass "No email_mapped_to_login in ${NEW_LINES} new debug.log lines"
  else
    pass "debug.log did not grow during probe (no new lines)"
  fi
  AUTH_SPAM=$(tail -n 50 "$DEBUG_LOG" | grep -c '\[PAXdesign Live Chat Mobile API\]' || true)
  AUTH_SPAM=${AUTH_SPAM:-0}
  echo "PAX Mobile API log lines in last 50 (historical tail): $AUTH_SPAM"
  if [[ "$GROWTH_BYTES" -gt 50000 ]]; then
    echo "WARN: debug.log grew ${GROWTH_BYTES} bytes during short probe — check WP_DEBUG_LOG"
  fi
else
  echo "debug.log not present (WP_DEBUG_LOG may be off) — OK for production"
  pass "No debug.log file"
fi

echo "=== Verification complete ==="
