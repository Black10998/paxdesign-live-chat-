#!/usr/bin/env bash
# Server-side resource + log sampler during app burst (read-only).
# Includes localhost vs public egress probes to distinguish IP ban vs server collapse.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
SITE="${PAX_SITE:-https://paxdesign.at}"
DURATION_SEC="${DURATION_SEC:-660}"
INTERVAL_SEC="${INTERVAL_SEC:-5}"
REPORT="${REPORT_PATH:-${1:-/tmp/server-outage-monitor.txt}}"

server_probe() {
  local label="$1"
  local url="$2"
  local tmp_body tmp_hdr
  tmp_body="$(mktemp)"
  tmp_hdr="$(mktemp)"
  local http_code
  http_code=$(curl -sS -D "$tmp_hdr" -o "$tmp_body" --max-time 12 -w "%{http_code}" "$url" 2>/dev/null || echo "000")
  local server powered cf_ray retry_after body_snip
  server=$(grep -im1 '^server:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  powered=$(grep -im1 '^x-powered-by:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  cf_ray=$(grep -im1 '^cf-ray:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  retry_after=$(grep -im1 '^retry-after:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  body_snip=$(head -c 200 "$tmp_body" 2>/dev/null | tr '\n' ' ' || true)
  rm -f "$tmp_body" "$tmp_hdr"
  echo "SERVER_PROBE $label|${http_code}|server=${server:-?}|php=${powered:-none}|cf-ray=${cf_ray:-?}|retry=${retry_after:-?}|body=${body_snip}"
}

{
echo "=== Server monitor start $(date -u '+%Y-%m-%d %H:%M:%S UTC') ==="
echo "WP_ROOT=$WP_ROOT"
echo "SITE=$SITE"
echo "Duration=${DURATION_SEC}s interval=${INTERVAL_SEC}s"
echo "Host=$(hostname 2>/dev/null || echo unknown)"
echo "Server egress IP: $(curl -sS --max-time 5 https://api.ipify.org 2>/dev/null || echo unknown)"
echo

end_epoch=$(( $(date +%s) + DURATION_SEC ))
sample=0

log_grep_hits() {
  local label="$1"
  local file="$2"
  [[ -f "$file" ]] || return 0
  local hits
  hits=$(tail -n 300 "$file" 2>/dev/null | grep -iE '403|429|503|ModSecurity|mod_security|denied|rate limit|fail2ban|litespeed|wp-fpm|max children|server reached|out of memory|killed process|throttl|ban |blocked' | tail -n 10 || true)
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
    echo "tcp_time_wait=$(ss -Htan state time-wait 2>/dev/null | wc -l || echo 0)"
  elif command -v netstat >/dev/null 2>&1; then
    echo "tcp_established=$(netstat -an 2>/dev/null | grep -c ESTABLISHED || echo 0)"
  fi

  # Compare public vs localhost reachability every sample
  server_probe "public_home" "${SITE}/"
  server_probe "public_users" "${SITE}/wp-admin/users.php"
  server_probe "public_wpjson" "${SITE}/wp-json/"
  server_probe "localhost_home" "http://127.0.0.1/"
  server_probe "localhost_users" "http://127.0.0.1/wp-admin/users.php" 2>/dev/null || server_probe "localhost_users" "http://localhost/wp-admin/users.php"

  for f in \
    "$HOME/error_log" \
    "$HOME/logs/error.log" \
    "$HOME/domains/paxdesign.at/logs/error.log" \
    "$HOME/domains/paxdesign.at/public_html/error_log" \
    "$WP_ROOT/error_log" \
    "$WP_ROOT/wp-content/debug.log"; do
    log_grep_hits "error_log" "$f"
  done
  for f in \
    "$HOME/logs/modsec_audit.log" \
    "$HOME/domains/paxdesign.at/logs/modsec_audit.log" \
    /var/log/modsec_audit.log \
    /usr/local/lsws/logs/error.log \
    /usr/local/lsws/logs/stderr.log \
    /usr/local/lsws/logs/access.log; do
    log_grep_hits "security_or_litespeed" "$f"
  done
  if command -v fail2ban-client >/dev/null 2>&1; then
    echo "--- fail2ban status ---"
    fail2ban-client status 2>/dev/null | head -25 || true
  fi
  if [[ -f /usr/local/lsws/admin/misc/lshttpd.pid ]]; then
    echo "litespeed_pid=$(cat /usr/local/lsws/admin/misc/lshttpd.pid 2>/dev/null || echo unknown)"
  fi
  echo
  sleep "$INTERVAL_SEC"
done

echo "=== Server monitor end $(date -u '+%Y-%m-%d %H:%M:%S UTC') ==="
echo "REPORT_SAVED=$REPORT"
} 2>&1 | tee "$REPORT"
