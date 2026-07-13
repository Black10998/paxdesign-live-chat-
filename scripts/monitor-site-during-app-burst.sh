#!/usr/bin/env bash
# Continuous site health monitor during simulated iOS app load (10+ minutes).
# Read-only. Logs state transitions, block pages, and recovery events.
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
USER="${PAX_ADMIN_USER:-}"
PASS="${PAX_ADMIN_APP_PASSWORD:-}"
DURATION_SEC="${DURATION_SEC:-660}"
PROBE_INTERVAL="${PROBE_INTERVAL:-1}"
REPORT="${1:-./site-outage-monitor.txt}"
BODY_SNIP_LEN="${BODY_SNIP_LEN:-280}"

exec > >(tee "$REPORT") 2>&1

section() { echo; echo "######## $1 ########"; }

probe_one() {
  local id="$1"
  local url="$2"
  local use_auth="${3:-0}"
  local tmp_body tmp_hdr
  tmp_body="$(mktemp)"
  tmp_hdr="$(mktemp)"
  local curl_args=(-sS -D "$tmp_hdr" -o "$tmp_body" --max-time 15)
  if [[ "$use_auth" == "1" && -n "$USER" && -n "$PASS" ]]; then
    curl_args+=(-u "${USER}:${PASS}")
  fi
  local http_code
  http_code=$(curl "${curl_args[@]}" -w "%{http_code}" "$url" 2>/dev/null || echo "000")
  local status_line server powered cf_ray retry_after
  status_line=$(grep -m1 -i '^HTTP/' "$tmp_hdr" 2>/dev/null || echo "HTTP/? ?")
  server=$(grep -im1 '^server:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  powered=$(grep -im1 '^x-powered-by:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  cf_ray=$(grep -im1 '^cf-ray:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  retry_after=$(grep -im1 '^retry-after:' "$tmp_hdr" 2>/dev/null | cut -d: -f2- | xargs || true)
  local body_snip block_hint layer
  body_snip=$(head -c "$BODY_SNIP_LEN" "$tmp_body" 2>/dev/null | tr '\n' ' ' | tr '\r' ' ' || true)
  block_hint=""
  layer="unknown"
  if echo "$body_snip" | grep -qi 'Access to this resource on the server is denied'; then
    block_hint="litespeed/hostinger_static_403"
    layer="edge_litespeed"
  elif echo "$body_snip" | grep -qiE 'cloudflare|cf-browser-verification|Just a moment|Attention Required'; then
    block_hint="cloudflare_challenge_or_block"
    layer="edge_cloudflare"
  elif echo "$body_snip" | grep -qi 'ModSecurity'; then
    block_hint="modsecurity"
    layer="waf_modsecurity"
  elif [[ "$http_code" == "429" ]]; then
    block_hint="rate_limit"
    layer="rate_limit"
  elif [[ "$http_code" == "503" || "$http_code" == "502" || "$http_code" == "520" || "$http_code" == "521" || "$http_code" == "522" || "$http_code" == "524" ]]; then
    block_hint="upstream_or_gateway_error"
    layer="gateway"
  elif [[ -n "$powered" ]]; then
    layer="php_wordpress"
  elif [[ "$http_code" == "000" ]]; then
    block_hint="timeout_or_network"
    layer="network"
  fi
  rm -f "$tmp_body" "$tmp_hdr"
  printf '%s|%s|%s|%s|%s|%s|%s|%s|%s|%s\n' \
    "$id" "$http_code" "$layer" "$block_hint" "$server" "$powered" "$cf_ray" "$retry_after" "$status_line" "$body_snip"
}

declare -A LAST_STATE
declare -A LAST_CHANGE_TS
OUTAGE_START=""
OUTAGE_END=""
OUTAGE_ACTIVE=0

log_transition() {
  local ts="$1" id="$2" old="$3" new="$4" detail="$5"
  echo "[$ts] TRANSITION $id: $old -> $new"
  echo "  $detail"
  local code layer
  code="${new%%:*}"
  layer="${new#*:}"; layer="${layer%%:*}"
  local is_bad=0 is_good=0
  if [[ "$code" == "403" || "$code" == "429" || "$code" == "503" || "$code" == "502" || "$code" == "520" || "$code" == "521" || "$code" == "522" || "$code" == "524" || "$code" == "000" ]]; then
    is_bad=1
  fi
  if [[ "$layer" == edge_litespeed || "$layer" == edge_cloudflare || "$layer" == rate_limit || "$layer" == gateway || "$layer" == network || "$layer" == waf_modsecurity ]]; then
    is_bad=1
  fi
  if [[ "$code" == "200" || "$code" == "302" ]] && [[ "$layer" == "php_wordpress" ]]; then
    is_good=1
  fi
  if [[ "$is_bad" -eq 1 ]]; then
    if [[ "$OUTAGE_ACTIVE" -eq 0 ]]; then
      OUTAGE_START="$ts"
      OUTAGE_ACTIVE=1
      echo "[$ts] OUTAGE_START detected on probe=$id"
    fi
  elif [[ "$is_good" -eq 1 && "$OUTAGE_ACTIVE" -eq 1 ]]; then
    OUTAGE_END="$ts"
    OUTAGE_ACTIVE=0
    if [[ -n "$OUTAGE_START" ]]; then
      local start_epoch end_epoch duration
      start_epoch=$(date -d "$OUTAGE_START" +%s 2>/dev/null || echo 0)
      end_epoch=$(date -d "$OUTAGE_END" +%s 2>/dev/null || echo 0)
      duration=$((end_epoch - start_epoch))
      echo "[$ts] OUTAGE_RECOVERY duration_sec=$duration start=$OUTAGE_START end=$OUTAGE_END"
    fi
  fi
}

simulate_app_load() {
  local stop_file="$1"
  local round=0
  echo "[app-sim] Starting aggressive app simulation until $(date -d "+${DURATION_SEC} seconds" -u '+%H:%M:%S' 2>/dev/null || echo 'duration end')"
  # SSE in background
  if [[ -n "$USER" && -n "$PASS" ]]; then
    (
      while [[ ! -f "$stop_file" ]]; do
        curl -sS -N -u "${USER}:${PASS}" \
          -H 'Accept: text/event-stream' -H 'Cache-Control: no-cache' \
          --max-time 45 \
          "${SITE}/wp-json/paxdesign/v1/live-admin/events/stream?since=0&_=$(date +%s)" \
          -o /dev/null 2>/dev/null || true
        sleep 1
      done
    ) &
    SSE_PID=$!
    echo "[app-sim] SSE worker pid=$SSE_PID"
  fi
  while [[ ! -f "$stop_file" ]]; do
    round=$((round + 1))
    if [[ -n "$USER" && -n "$PASS" ]]; then
      for path in me sessions team/sessions team/requests/pending conversations/sync platform/sync devices; do
        [[ -f "$stop_file" ]] && break
        curl -sS -o /dev/null -u "${USER}:${PASS}" -H 'Accept: application/json' -H 'Cache-Control: no-cache' \
          "${SITE}/wp-json/paxdesign/v1/live-admin/${path}?_=$(date +%s)" 2>/dev/null || true
      done
      curl -sS -o /dev/null -u "${USER}:${PASS}" -H 'Content-Type: application/json' \
        -X POST "${SITE}/wp-json/paxdesign/v1/live-admin/team/presence?_=$(date +%s)" \
        --data '{"online":true}' 2>/dev/null || true
      curl -sS -o /dev/null -u "${USER}:${PASS}" -H 'Content-Type: application/json' \
        -X POST "${SITE}/wp-json/paxdesign/v1/live-admin/devices/heartbeat?_=$(date +%s)" \
        --data '{"device_id":"monitor-sim","device_name":"Outage Monitor"}' 2>/dev/null || true
    fi
    # Aggressive poll like open chat (~0.8s between rounds)
    sleep 0.8
  done
  if [[ -n "${SSE_PID:-}" ]]; then
    kill "$SSE_PID" 2>/dev/null || true
    wait "$SSE_PID" 2>/dev/null || true
  fi
  echo "[app-sim] Stopped after round=$round"
}

section "Site-wide outage monitor"
echo "Time start: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "Site: $SITE"
echo "Duration: ${DURATION_SEC}s, probe interval: ${PROBE_INTERVAL}s"
echo "Auth for REST probes: $([ -n "$USER" ] && echo yes || echo no)"

STOP_FILE="$(mktemp -u)"
trap 'touch "$STOP_FILE" 2>/dev/null || true' EXIT

simulate_app_load "$STOP_FILE" &
APP_SIM_PID=$!
echo "App simulator pid=$APP_SIM_PID"

PROBE_URLS=(
  "home|${SITE}/|0"
  "login|${SITE}/wp-login.php|0"
  "admin|${SITE}/wp-admin/|0"
  "users|${SITE}/wp-admin/users.php|0"
  "wpjson|${SITE}/wp-json/|0"
  "app_me|${SITE}/wp-json/paxdesign/v1/live-admin/me|1"
  "app_sessions|${SITE}/wp-json/paxdesign/v1/live-admin/sessions|1"
)

end_epoch=$(( $(date +%s) + DURATION_SEC ))
probe_count=0

section "Continuous probes (1 Hz)"
while [[ $(date +%s) -lt $end_epoch ]]; do
  ts="$(date -u '+%Y-%m-%d %H:%M:%S UTC')"
  probe_count=$((probe_count + 1))
  for entry in "${PROBE_URLS[@]}"; do
    IFS='|' read -r id url auth <<< "$entry"
    line=$(probe_one "$id" "$url" "$auth")
    IFS='|' read -r _ code layer hint server powered cf_ray retry status_line body <<< "$line"
    key="${id}"
    state="${code}:${layer}:${hint}"
    prev="${LAST_STATE[$key]:-}"
    if [[ "$prev" != "$state" ]]; then
      log_transition "$ts" "$id" "${prev:-INIT}" "$state" "status=$status_line server=${server:-?} php=${powered:-none} cf-ray=${cf_ray:-?} retry-after=${retry_after:-?} body=${body}"
      LAST_STATE[$key]="$state"
      LAST_CHANGE_TS[$key]="$ts"
    fi
    # Compact heartbeat every 30 probes per endpoint - only log if non-healthy
    if [[ "$probe_count" -eq 1 ]] || [[ $((probe_count % 30)) -eq 0 ]]; then
      if [[ "$layer" != "php_wordpress" && "$code" != "302" && "$code" != "200" ]]; then
        echo "[$ts] PERSISTENT_ISSUE $id code=$code layer=$layer hint=$hint"
      fi
    fi
  done
  sleep "$PROBE_INTERVAL"
done

touch "$STOP_FILE"
wait "$APP_SIM_PID" 2>/dev/null || true

section "Final snapshot"
for entry in "${PROBE_URLS[@]}"; do
  IFS='|' read -r id url auth <<< "$entry"
  line=$(probe_one "$id" "$url" "$auth")
  echo "FINAL $line"
done

section "Outage summary"
if [[ -n "$OUTAGE_START" && -n "$OUTAGE_END" ]]; then
  start_e=$(date -d "$OUTAGE_START" +%s 2>/dev/null || echo 0)
  end_e=$(date -d "$OUTAGE_END" +%s 2>/dev/null || echo 0)
  echo "Detected outage window: $OUTAGE_START -> $OUTAGE_END (${end_e}-${start_e}s)"
  if [[ $((end_e - start_e)) -ge 240 && $((end_e - start_e)) -le 420 ]]; then
    echo "Recovery timing matches ~5 minute automatic block/rate-limit window."
  fi
elif [[ "$OUTAGE_ACTIVE" -eq 1 ]]; then
  echo "Outage still active at end of monitoring window (started $OUTAGE_START)."
else
  echo "No site-wide outage detected during monitoring window from this probe IP."
fi

section "Recovery hypothesis guide"
cat <<'GUIDE'
~300s auto-recovery patterns:
  - retry-after header present during block -> explicit rate limit
  - 429 without PHP headers -> edge rate limit (Cloudflare/Hostinger)
  - LiteSpeed static 403 without x-powered-by -> WAF/mod_security temp ban
  - 502/503 with recovery -> PHP-FPM/LiteSpeed worker exhaustion then recycle
  - CPU/RAM spike in server monitor then drop -> resource exhaustion

Match server monitor timestamps to TRANSITION/OUTAGE_START lines.
GUIDE

echo
echo "REPORT_SAVED=$REPORT"
echo "Time end: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
