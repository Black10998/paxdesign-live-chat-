#!/usr/bin/env bash
# Compare iOS REST sync with the shortcode-equivalent backend source.
# Usage:
#   WP_URL=https://paxdesign.at WP_USER=admin WP_APP_PASS=xxxx ./scripts/compare-live-sync.sh

set -euo pipefail

WP_URL="${WP_URL:-https://paxdesign.at}"
WP_USER="${WP_USER:-}"
WP_APP_PASS="${WP_APP_PASS:-}"

if [[ -z "$WP_USER" || -z "$WP_APP_PASS" ]]; then
  echo "Set WP_USER and WP_APP_PASS (WordPress Application Password)." >&2
  exit 1
fi

BASE="${WP_URL%/}/wp-json/paxdesign/v1/live-admin"
AUTH=(-u "${WP_USER}:${WP_APP_PASS}")

echo "==> REST sessions (same source as iOS app list)"
SESSIONS_JSON="$(curl -fsS "${AUTH[@]}" "${BASE}/sessions?_=$(date +%s)")"
echo "$SESSIONS_JSON" | python3 - <<'PY'
import json,sys
data=json.load(sys.stdin)
sessions=data.get('sessions') or []
print('session_count =', len(sessions))
print('live_count =', data.get('live_count'))
if sessions:
    s=sessions[0]
    print('latest_session_id =', s.get('session_id'))
    print('latest_handler =', s.get('handler'))
    print('latest_preview =', (s.get('last_preview') or '')[:80])
else:
    print('latest_session_id = <none>')
PY

echo
echo "==> REST debug/parity (explicit shortcode-equivalent marker)"
PARITY_JSON="$(curl -fsS "${AUTH[@]}" "${BASE}/debug/parity?_=$(date +%s)")"
echo "$PARITY_JSON" | python3 - <<'PY'
import json,sys
data=json.load(sys.stdin)
print('parity_source =', data.get('parity_source'))
print('shortcode_equivalent =', data.get('shortcode_equivalent'))
print('session_count =', data.get('session_count'))
print('latest_session_id =', data.get('latest_session_id'))
print('plugin_version =', data.get('plugin_version'))
print('server_time =', data.get('server_time'))
PY

SID="$(echo "$SESSIONS_JSON" | python3 -c 'import json,sys; d=json.load(sys.stdin); s=d.get("sessions") or []; print(s[0]["session_id"] if s else "")')"
if [[ -n "$SID" ]]; then
  echo
  echo "==> REST poll for latest session: $SID"
  POLL_JSON="$(curl -fsS "${AUTH[@]}" "${BASE}/sessions/${SID}/poll?full=1&_=$(date +%s)")"
  echo "$POLL_JSON" | python3 - <<'PY'
import json,sys
data=json.load(sys.stdin)
msgs=data.get('messages') or []
print('handler =', data.get('handler'))
print('message_count =', len(msgs))
print('seq =', data.get('seq'))
if msgs:
    last=msgs[-1]
    print('last_message_role =', last.get('role'))
    print('last_message_preview =', (last.get('content') or '')[:80])
PY
fi

echo
echo "Compare these numbers with:"
echo "  1) Shortcode Live Admin list count in browser"
echo "  2) iOS Sync Debug screen: API session_count vs displayed sessions"
