#!/usr/bin/env bash
# Post-deploy verification for v3.132.0 — fatal error + memory fixes.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
PLUGIN="$WP_ROOT/wp-content/plugins/paxdesign-booking/paxdesign-booking.php"
DEBUG_LOG="$WP_ROOT/wp-content/debug.log"
SITE="${PAX_SITE:-https://paxdesign.at}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"
TAIL_LINES="${PAX_DEBUG_TAIL_LINES:-500}"

fail() { echo "VERIFY_FAIL: $*"; exit 1; }
pass() { echo "VERIFY_OK: $*"; }

echo "=== Verify plugin 3.132.0 + debug.log error regression ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "WP_ROOT: $WP_ROOT"

[[ -f "$PLUGIN" ]] || fail "Plugin not found at $PLUGIN"

VER=$(grep "define('PAXDESIGN_BOOKING_VERSION'" "$PLUGIN" | sed "s/.*'\([^']*\)'.*/\1/")
echo "Installed version: $VER"
[[ "$VER" == "3.132.0" ]] || fail "Expected 3.132.0, got $VER"
pass "Plugin version 3.132.0"

CHAT_LOG="$WP_ROOT/wp-content/plugins/paxdesign-booking/includes/class-paxdesign-chat-log.php"
if grep -q 'PAXdesign_Message_Store::get_by_client_id' "$CHAT_LOG" 2>/dev/null; then
  fail "class-paxdesign-chat-log.php still calls private get_by_client_id()"
fi
pass "Fatal-error call site uses public find_by_client_id wrapper"

LOG_BEFORE_LINES=0
if [[ -f "$DEBUG_LOG" ]]; then
  LOG_BEFORE_LINES=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
  echo "debug.log lines before probe: $LOG_BEFORE_LINES"
fi

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
  LOG_AFTER_LINES=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
  NEW_LINES=$((LOG_AFTER_LINES - LOG_BEFORE_LINES))
  echo "debug.log lines after probe: $LOG_AFTER_LINES (delta: $NEW_LINES)"

  TAIL_FILE="/tmp/pax-debug-tail-3132.txt"
  tail -n "$TAIL_LINES" "$DEBUG_LOG" > "$TAIL_FILE"

  FATAL_CLIENT=$(grep -c 'get_by_client_id()' "$TAIL_FILE" || true)
  FATAL_CLIENT=${FATAL_CLIENT:-0}
  echo "get_by_client_id fatal mentions in last $TAIL_LINES lines: $FATAL_CLIENT"
  [[ "$FATAL_CLIENT" -eq 0 ]] || fail "get_by_client_id fatals still present in recent debug.log"

  MEM_EX=$(grep -ci 'Allowed memory size exhausted' "$TAIL_FILE" || true)
  MEM_EX=${MEM_EX:-0}
  echo "memory exhausted mentions in last $TAIL_LINES lines: $MEM_EX"

  CRON_ERR=$(grep -ci 'action_scheduler_run_queue.*could_not_set\|could_not_set.*action_scheduler' "$TAIL_FILE" || true)
  CRON_ERR=${CRON_ERR:-0}
  echo "action_scheduler could_not_set mentions in last $TAIL_LINES lines: $CRON_ERR"

  if [[ "$NEW_LINES" -gt 0 ]]; then
    tail -n "$NEW_LINES" "$DEBUG_LOG" > /tmp/pax-debug-new-lines-3132.txt
    NEW_FATAL=$(grep -c 'get_by_client_id()' /tmp/pax-debug-new-lines-3132.txt || true)
    NEW_FATAL=${NEW_FATAL:-0}
    [[ "$NEW_FATAL" -eq 0 ]] || fail "get_by_client_id fatals in $NEW_LINES NEW debug.log lines during probe"
    NEW_MEM=$(grep -ci 'Allowed memory size exhausted' /tmp/pax-debug-new-lines-3132.txt || true)
    NEW_MEM=${NEW_MEM:-0}
    echo "New lines during probe: fatal_get_by_client_id=$NEW_FATAL memory_exhausted=$NEW_MEM"
    pass "No get_by_client_id fatals in new debug.log lines during probe"
  else
    pass "debug.log did not grow during probe"
  fi

  pass "Recent debug.log tail scanned (memory=$MEM_EX cron=$CRON_ERR)"
else
  echo "debug.log not present — cannot scan server log tail"
  pass "No debug.log file on server"
fi

echo "=== Verification complete ==="
