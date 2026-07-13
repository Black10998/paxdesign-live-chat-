#!/usr/bin/env bash
# Measure live-admin REST request rate during an iOS app session (403 retest protocol).
# Run on server via SSH while user opens the iOS app on the same network.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
DEBUG_LOG="$WP_ROOT/wp-content/debug.log"
WINDOW_SEC="${WINDOW_SEC:-120}"
SITE="${PAX_SITE:-https://paxdesign.at}"

echo "=== REST traffic measurement window (${WINDOW_SEC}s) ==="
echo "Start: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "Instructions: Open iOS Live Chat app NOW on test Wi-Fi and keep it foreground."
echo "Site: $SITE"
echo

LOG_BEFORE=0
if [[ -f "$DEBUG_LOG" ]]; then
  LOG_BEFORE=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
fi

# Probe site health every 5s
end=$((SECONDS + WINDOW_SEC))
probes_ok=0
probes_403=0
while [[ $SECONDS -lt $end ]]; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 8 "${SITE}/" 2>/dev/null || echo "000")
  ts=$(date -u '+%H:%M:%S')
  if [[ "$code" == "200" ]]; then
    probes_ok=$((probes_ok + 1))
    echo "[$ts] GET / → $code"
  else
    probes_403=$((probes_403 + 1))
    echo "[$ts] GET / → $code *** OUTAGE ***"
  fi
  sleep 5
done

echo
echo "=== Results ==="
echo "Homepage probes OK: $probes_ok"
echo "Homepage probes non-200: $probes_403"
if [[ -f "$DEBUG_LOG" ]]; then
  LOG_AFTER=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
  DELTA=$((LOG_AFTER - LOG_BEFORE))
  echo "debug.log line delta during window: $DELTA"
  tail -n 50 "$DEBUG_LOG" | grep -c 'email_mapped_to_login' && echo "WARN: email_mapped_to_login still in tail" || echo "OK: no email_mapped_to_login in tail"
fi
echo
echo "Note: Log line count ≠ HTTP request count (WordPress may log other events)."
echo "For true iOS request count, use Network Diagnostics in app build 99+ or Xcode Network Instruments on Build 98."
