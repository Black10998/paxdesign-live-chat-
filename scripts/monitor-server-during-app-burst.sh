#!/usr/bin/env bash
# Server-side resource + log sampler during app burst (read-only).
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
DURATION_SEC="${DURATION_SEC:-660}"
INTERVAL_SEC="${INTERVAL_SEC:-5}"
REPORT="${REPORT_PATH:-${1:-/tmp/server-outage-monitor.txt}}"

{
echo "=== Server monitor start $(date -u '+%Y-%m-%d %H:%M:%S UTC') ==="
echo "WP_ROOT=$WP_ROOT"
echo "Duration=${DURATION_SEC}s interval=${INTERVAL_SEC}s"
echo "Host=$(hostname 2>/dev/null || echo unknown)"
echo

end_epoch=$(( $(date +%s) + DURATION_SEC ))
sample=0

log_grep_hits() {
  local label="$1"
  local file="$2"
  [[ -f "$file" ]] || return 0
  local hits
  hits=$(tail -n 200 "$file" 2>/dev/null | grep -iE '403|429|503|ModSecurity|mod_security|denied|rate limit|fail2ban|litespeed|wp-fpm|max children|server reached|out of memory|killed process' | tail -n 8 || true)
  if [[ -n "$hits" ]]; then
    echo "--- $label ($file) ---"
    echo "$hits"
  fi
}

while [[ $(date +%s) -lt $end_epoch ]]; do
  sample=$((sample + 1))
  ts="$(date -u '+%Y-%m-%d %H:%M:%S UTC')"
  echo "[$ts] SAMPLE #$sample"
  echo "load: $(uptime 2>/dev/null || true)"
  if command -v free >/dev/null 2>&1; then
    free -m 2>/dev/null | awk '/Mem:|Swap:/' || true
  fi
  php_count=$(ps aux 2>/dev/null | grep -E '[p]hp|[l]sphp' | wc -l || echo 0)
  echo "php_processes=$php_count"
  if command -v ss >/dev/null 2>&1; then
    echo "tcp_established=$(ss -Htan state established 2>/dev/null | wc -l || echo 0)"
  elif command -v netstat >/dev/null 2>&1; then
    echo "tcp_established=$(netstat -an 2>/dev/null | grep -c ESTABLISHED || echo 0)"
  fi
  for f in \
    "$HOME/error_log" \
    "$HOME/logs/error.log" \
    "$HOME/domains/paxdesign.at/logs/error.log" \
    "$WP_ROOT/error_log"; do
    log_grep_hits "error_log" "$f"
  done
  for f in \
    "$HOME/logs/modsec_audit.log" \
    /var/log/modsec_audit.log \
    /usr/local/lsws/logs/error.log \
    /usr/local/lsws/logs/stderr.log; do
    log_grep_hits "security_or_litespeed" "$f"
  done
  if command -v fail2ban-client >/dev/null 2>&1; then
    fail2ban-client status 2>/dev/null | head -20 || true
  fi
  echo
  sleep "$INTERVAL_SEC"
done

echo "=== Server monitor end $(date -u '+%Y-%m-%d %H:%M:%S UTC') ==="
echo "REPORT_SAVED=$REPORT"
} 2>&1 | tee "$REPORT"
