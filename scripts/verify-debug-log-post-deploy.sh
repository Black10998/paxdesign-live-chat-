#!/usr/bin/env bash
# Post-deploy verification for v3.112.0 — no email_mapped_to_login spam.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
PLUGIN="$WP_ROOT/wp-content/plugins/paxdesign-booking/paxdesign-booking.php"
DEBUG_LOG="$WP_ROOT/wp-content/debug.log"
SITE="${PAX_SITE:-https://paxdesign.at}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"

fail() { echo "VERIFY_FAIL: $*"; exit 1; }
pass() { echo "VERIFY_OK: $*"; }

echo "=== Verify plugin 3.112.0 + debug.log hygiene ==="
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

LOG_BEFORE=0
LOG_AFTER=0
if [[ -f "$DEBUG_LOG" ]]; then
  LOG_BEFORE=$(wc -c < "$DEBUG_LOG" | tr -d ' ')
  echo "debug.log size before probe: ${LOG_BEFORE} bytes"
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
  LOG_AFTER=$(wc -c < "$DEBUG_LOG" | tr -d ' ')
  GROWTH=$((LOG_AFTER - LOG_BEFORE))
  echo "debug.log size after probe: ${LOG_AFTER} bytes (delta ${GROWTH} bytes)"
  NEW_LINES=$(tail -n 200 "$DEBUG_LOG" | grep -c 'email_mapped_to_login' || true)
  NEW_LINES=${NEW_LINES:-0}
  if [[ "$NEW_LINES" -gt 0 ]]; then
    fail "email_mapped_to_login appeared in last 200 debug.log lines ($NEW_LINES)"
  fi
  pass "No new email_mapped_to_login in debug.log tail"
  AUTH_SPAM=$(tail -n 200 "$DEBUG_LOG" | grep -c '\[PAXdesign Live Chat Mobile API\]' || true)
  AUTH_SPAM=${AUTH_SPAM:-0}
  echo "PAX Mobile API log lines in tail: $AUTH_SPAM"
  if [[ "$GROWTH" -gt 50000 ]]; then
    echo "WARN: debug.log grew ${GROWTH} bytes during short probe — check WP_DEBUG_LOG"
  else
    pass "debug.log growth within expected bounds (${GROWTH} bytes)"
  fi
else
  echo "debug.log not present (WP_DEBUG_LOG may be off) — OK for production"
  pass "No debug.log file"
fi

echo "=== Verification complete ==="
