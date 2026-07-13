#!/usr/bin/env bash
# Analyze site + server monitor reports for 5-minute auto-recovery patterns.
# Read-only post-processing. No fixes applied.
set -euo pipefail

SITE_REPORT="${1:?site monitor report}"
SERVER_REPORT="${2:-}"

section() { echo; echo "=== $1 ==="; }

section "Outage timeline (external probes)"
grep -E 'OUTAGE_START|OUTAGE_RECOVERY|Recovery timing|No site-wide outage' "$SITE_REPORT" || echo "(none)"

section "All state transitions"
grep -E 'TRANSITION|PERSISTENT_ISSUE' "$SITE_REPORT" || echo "(none)"

section "Block layer signals during transitions"
grep -E 'TRANSITION' "$SITE_REPORT" | grep -iE 'retry-after|cloudflare|litespeed|modsecurity|429|503|502|520|521|522|524|000|denied' || echo "(none)"

section "Recovery hypothesis (external)"
{
  transitions=$(grep -c 'OUTAGE_RECOVERY' "$SITE_REPORT" 2>/dev/null || echo 0)
  if [[ "$transitions" -eq 0 ]]; then
    echo "No external outage/recovery window captured in this run."
    echo "If user still sees ~5min blocks on local network, likely causes:"
    echo "  1) Edge rate limit scoped to client IP/subnet (not GitHub Actions ORD IP)"
    echo "  2) Wi-Fi router or ISP-level throttling after burst from iPhone"
    echo "  3) Cloudflare/Hostinger per-IP ban not triggered by CI egress IP"
  else
    duration_line=$(grep 'OUTAGE_RECOVERY' "$SITE_REPORT" | tail -1)
    echo "$duration_line"
    if echo "$duration_line" | grep -q 'duration_sec=30[0-9]\|duration_sec=29[0-9]\|duration_sec=31[0-9]\|duration_sec=32[0-9]'; then
      echo "LIKELY: Temporary edge rate limit / WAF ban (~300s TTL)"
    fi
    if grep -E 'TRANSITION' "$SITE_REPORT" | grep -qi 'retry-after'; then
      echo "LIKELY: Explicit Retry-After rate limit header present"
    fi
    if grep -E 'TRANSITION' "$SITE_REPORT" | grep -qi 'litespeed/hostinger_static_403\|edge_litespeed'; then
      echo "LIKELY: LiteSpeed/Hostinger static 403 (no PHP) — WAF or server deny rule"
    fi
    if grep -E 'TRANSITION' "$SITE_REPORT" | grep -qi 'cloudflare'; then
      echo "LIKELY: Cloudflare challenge or block page"
    fi
    if grep -E 'TRANSITION' "$SITE_REPORT" | grep -qi '503\|502\|gateway\|upstream'; then
      echo "LIKELY: PHP-FPM/LiteSpeed worker exhaustion with automatic recycle"
    fi
  fi
} 

if [[ -n "$SERVER_REPORT" && -f "$SERVER_REPORT" ]]; then
  section "Server resource peaks"
  grep -E 'load:|php_processes=|tcp_established=|Mem:' "$SERVER_REPORT" | tail -60 || true

  section "Server log hits (403/429/503/WAF)"
  grep -iE '403|429|503|ModSecurity|denied|rate limit|max children|out of memory|fail2ban|wp-fpm' "$SERVER_REPORT" | tail -40 || echo "(none)"

  section "Server-side probe comparison (localhost vs public)"
  grep -E 'SERVER_PROBE|localhost|public_egress' "$SERVER_REPORT" || echo "(server probes not present in this report version)"

  section "Recovery hypothesis (server correlation)"
  {
    if grep -qiE 'max children|server reached|out of memory|killed process' "$SERVER_REPORT"; then
      echo "CORRELATION: PHP-FPM/LiteSpeed worker limit messages in server logs"
    fi
    if grep -qiE 'ModSecurity|mod_security' "$SERVER_REPORT"; then
      echo "CORRELATION: ModSecurity audit/error entries during window"
    fi
    if grep -qiE 'fail2ban|Ban ' "$SERVER_REPORT"; then
      echo "CORRELATION: Fail2Ban ban activity"
    fi
    peak_load=$(grep -oE 'load average: [0-9.]+' "$SERVER_REPORT" | awk '{print $3}' | sort -rn | head -1 || true)
    if [[ -n "${peak_load:-}" ]] && awk -v l="$peak_load" 'BEGIN{exit !(l>20)}'; then
      echo "CORRELATION: Very high load average ($peak_load) on shared host — resource contention possible"
    fi
    if grep -q 'SERVER_PROBE' "$SERVER_REPORT"; then
      if grep 'SERVER_PROBE public' "$SERVER_REPORT" | grep -qE '\|403\||\|429\||\|503\||\|000\|'; then
        if grep 'SERVER_PROBE localhost_vhost' "$SERVER_REPORT" | grep -qvE '\|403\||\|429\||\|503\||\|000\|'; then
          echo "DIAGNOSIS: Public egress blocked but localhost vhost OK -> edge/IP rate limit (not app crash)"
        fi
      fi
      if grep 'SERVER_PROBE localhost_vhost' "$SERVER_REPORT" | grep -qE '\|403\||\|429\||\|503\||\|000\|'; then
        echo "DIAGNOSIS: Localhost vhost also failing -> site-wide server/WAF block or resource collapse"
      fi
    fi
  }
fi

section "Recommended next steps (no fix deploy)"
cat <<'NEXT'
1. If no outage from CI IP: run scripts/monitor-site-during-app-burst.sh from user Wi-Fi (same network as iPhone).
2. Ask Hostinger for LiteSpeed WAF / rate-limit rule ID and ban TTL for affected client IP.
3. Check Cloudflare Security Events (if proxied) for 5-minute timed blocks.
4. Correlate OUTAGE_START timestamp with server SAMPLE lines and ModSecurity logs.
5. Do NOT deploy plugin auth-scope fix until recovery cause is confirmed.
NEXT
