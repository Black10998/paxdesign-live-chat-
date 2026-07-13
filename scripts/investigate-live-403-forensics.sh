#!/usr/bin/env bash
# Live 403 forensics — read-only. Captures WAF/ModSecurity/IP-ban evidence after outage.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
SITE="${PAX_SITE:-https://paxdesign.at}"
INCIDENT_TS="${INCIDENT_TS:-$(date -u '+%Y-%m-%d %H:%M:%S UTC')}"
REPORT="${WP_ROOT}/wp-content/pax-live-403-forensics-$(date +%Y%m%d-%H%M%S).txt"

section() { echo; echo "######## $1 ########"; }

probe_url() {
  local label="$1"
  local url="$2"
  shift 2
  local tmp_body tmp_hdr
  tmp_body="$(mktemp)"
  tmp_hdr="$(mktemp)"
  local http_code
  http_code=$(curl -sS -D "$tmp_hdr" -o "$tmp_body" --max-time 15 "$@" "$url" 2>/dev/null || echo "000")
  local server powered cf_ray retry_after status_line
  status_line=$(grep -m1 -i '^HTTP/' "$tmp_hdr" 2>/dev/null || echo "HTTP/? ?")
  server=$(grep -im1 '^server:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  powered=$(grep -im1 '^x-powered-by:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  cf_ray=$(grep -im1 '^cf-ray:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  retry_after=$(grep -im1 '^retry-after:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  local body_snip layer block_hint
  body_snip=$(head -c 320 "$tmp_body" 2>/dev/null | tr '\n' ' ' || true)
  layer="unknown"
  block_hint=""
  if echo "$body_snip" | grep -qi 'Access to this resource on the server is denied'; then
    block_hint="litespeed_hostinger_static_403"
    layer="edge_litespeed"
  elif echo "$body_snip" | grep -qiE 'cloudflare|Just a moment|Attention Required'; then
    block_hint="cloudflare_block"
    layer="edge_cloudflare"
  elif [[ -n "$powered" ]]; then
    layer="php_wordpress"
  fi
  rm -f "$tmp_body" "$tmp_hdr"
  echo "PROBE $label|code=$http_code|layer=$layer|hint=$block_hint|server=${server:-?}|php=${powered:-none}|cf-ray=${cf_ray:-?}|retry=${retry_after:-?}|status=$status_line"
  echo "  body=${body_snip}"
}

log_slice() {
  local label="$1"
  local file="$2"
  local since_min="${3:-120}"
  [[ -f "$file" ]] || return 0
  echo "--- $label ($file) last ${since_min}m ---"
  # Hostinger/apache combined logs often lack year; grep recent 403/denied lines
  tail -n 2000 "$file" 2>/dev/null | grep -iE \
    '403|Forbidden|denied|ModSecurity|mod_security|Rule ID|\[id "|rate.?limit|throttl|ban |blocked|wp-json|live-admin|Authorization|/wp-admin|paxdesign' \
    | tail -n 80 || true
}

{
echo "=== PAXdesign LIVE 403 forensics (read-only) ==="
echo "Capture time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "Reported incident (user): $INCIDENT_TS"
echo "WP_ROOT: $WP_ROOT"
echo "SITE: $SITE"
echo "Server hostname: $(hostname 2>/dev/null || echo unknown)"
echo "Server egress IP: $(curl -sS --max-time 5 https://api.ipify.org 2>/dev/null || echo unknown)"
echo
echo "User screenshot evidence:"
echo "  - GET / returned 403 Forbidden"
echo "  - Body: Access to this resource on the server is denied!"
echo "  - favicon.ico also 403"
echo "  - Pattern: LiteSpeed/Hostinger static block BEFORE PHP (no x-powered-by)"
echo

section "Current reachability from server egress"
probe_url "public_home" "${SITE}/"
probe_url "public_users" "${SITE}/wp-admin/users.php"
probe_url "public_wpjson" "${SITE}/wp-json/"
probe_url "public_rest_me" "${SITE}/wp-json/paxdesign/v1/live-admin/me"
probe_url "localhost_vhost" "http://127.0.0.1/" -H "Host: paxdesign.at"

section "fail2ban / IP ban status"
if command -v fail2ban-client >/dev/null 2>&1; then
  fail2ban-client status 2>/dev/null || true
  for jail in $(fail2ban-client status 2>/dev/null | awk -F: '/Jail list/{gsub(/,/," "); print $2}'); do
    [[ -n "$jail" ]] || continue
    echo "--- jail: $jail ---"
    fail2ban-client status "$jail" 2>/dev/null | head -30 || true
  done
else
  echo "fail2ban-client not available"
fi

section "LiteSpeed / Hostinger log discovery"
ERROR_LOGS=()
ACCESS_LOGS=()
MODSEC_LOGS=()
append() { local n="$1"; local v="$2"; eval "local a=(\"\${${n}[@]-}\"); a+=(\"\$v\"); eval \"${n}=(\${a[@]})\" 2>/dev/null || true"; }
for p in \
  "$HOME/error_log" \
  "$HOME/logs/error.log" \
  "$HOME/domains/paxdesign.at/logs/error.log" \
  "$HOME/domains/paxdesign.at/public_html/error_log" \
  "$WP_ROOT/error_log" \
  "$WP_ROOT/wp-content/debug.log" \
  /usr/local/lsws/logs/error.log \
  /usr/local/lsws/logs/stderr.log \
  /usr/local/lsws/logs/access.log; do
  [[ -f "$p" ]] && append ERROR_LOGS "$p"
done
for p in \
  "$HOME/logs/access.log" \
  "$HOME/domains/paxdesign.at/logs/access.log" \
  "$HOME/domains/paxdesign.at/logs/ssl_access.log" \
  /usr/local/lsws/logs/access.log; do
  [[ -f "$p" ]] && append ACCESS_LOGS "$p"
done
for p in \
  "$HOME/logs/modsec_audit.log" \
  "$HOME/domains/paxdesign.at/logs/modsec_audit.log" \
  /var/log/modsec_audit.log \
  /usr/local/lsws/logs/audit.log; do
  [[ -f "$p" ]] && append MODSEC_LOGS "$p"
done
echo "Error logs: $(printf '%s\n' "${ERROR_LOGS[@]}" 2>/dev/null | sort -u | wc -l)"
printf '  %s\n' $(printf '%s\n' "${ERROR_LOGS[@]}" 2>/dev/null | sort -u) || true
echo "Access logs: $(printf '%s\n' "${ACCESS_LOGS[@]}" 2>/dev/null | sort -u | wc -l)"
printf '  %s\n' $(printf '%s\n' "${ACCESS_LOGS[@]}" 2>/dev/null | sort -u) || true
echo "ModSec logs: $(printf '%s\n' "${MODSEC_LOGS[@]}" 2>/dev/null | sort -u | wc -l)"
printf '  %s\n' $(printf '%s\n' "${MODSEC_LOGS[@]}" 2>/dev/null | sort -u) || true

section "Access logs — 403 bursts and client IPs (last ~2000 lines)"
for log in $(printf '%s\n' "${ACCESS_LOGS[@]}" 2>/dev/null | sort -u); do
  log_slice "access" "$log" 180
  echo "--- Top client IPs with 403 in tail ---"
  tail -n 3000 "$log" 2>/dev/null | grep ' 403 ' | awk '{print $1}' | sort | uniq -c | sort -rn | head -15 || true
  echo "--- REST burst IPs (live-admin/wp-json) ---"
  tail -n 3000 "$log" 2>/dev/null | grep -iE 'wp-json/paxdesign|live-admin' | awk '{print $1}' | sort | uniq -c | sort -rn | head -15 || true
done

section "Error / ModSecurity — Rule ID and deny reasons"
for log in $(printf '%s\n' "${ERROR_LOGS[@]}" "${MODSEC_LOGS[@]}" 2>/dev/null | sort -u); do
  log_slice "security" "$log" 180
done

section "PHP / WordPress error_log"
for log in $(printf '%s\n' "${ERROR_LOGS[@]}" 2>/dev/null | sort -u); do
  log_slice "php" "$log" 180
done

section "Resource state at capture"
echo "load: $(uptime 2>/dev/null || true)"
free -m 2>/dev/null | awk '/Mem:|Swap:/' || true
echo "php_processes=$(ps aux 2>/dev/null | grep -E '[p]hp|[l]sphp' | wc -l || echo 0)"
if command -v ss >/dev/null 2>&1; then
  echo "tcp_established=$(ss -Htan state established 2>/dev/null | wc -l || echo 0)"
fi

section "Interpretation checklist"
cat <<'GUIDE'
To answer "who issued 403":
  1) No x-powered-by: PHP + "Access to this resource on the server is denied" => LiteSpeed/Hostinger layer (NOT WordPress).
  2) cf-ray present but 403 without PHP => Cloudflare may proxy; origin or edge rule still possible — check cf-ray in Security Events.
  3) ModSecurity log with [id "NNNN"] => Rule ID and matched URI.
  4) Access log: same client IP gets 403 on / and /favicon.ico => IP-scoped ban/rate limit.
  5) Server egress OK + user IP 403 => client IP/subnet ban (matches ~5min auto-recovery).
  6) retry-after header => explicit TTL seconds.
GUIDE

echo
echo "REPORT_PATH=$REPORT"
} 2>&1 | tee "$REPORT"
