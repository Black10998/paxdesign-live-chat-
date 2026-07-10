#!/usr/bin/env bash
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
EXPECTED_PLUGIN="${PAX_EXPECTED_PLUGIN:-3.99.0}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"

fail() {
  echo "FAIL: $1" >&2
  exit 1
}

pass() {
  echo "PASS: $1"
}

echo "==> Checking production plugin asset version"
asset_ver="$(curl -fsSL "$SITE/" | grep -oE 'chat-script\.js\?ver=[0-9.]+' | head -1 | cut -d= -f2 || true)"
if [[ "$asset_ver" != "$EXPECTED_PLUGIN" ]]; then
  fail "Production plugin assets report v${asset_ver:-unknown}; expected v${EXPECTED_PLUGIN}"
fi
pass "Production assets report plugin v${asset_ver}"

if [[ -z "$ADMIN_USER" || -z "$ADMIN_PASS" ]]; then
  echo "SKIP: Admin/iOS live verification requires PAX_ADMIN_USER and PAX_ADMIN_APP_PASSWORD"
  exit 0
fi

session_id="e2e-$(date +%s)-$RANDOM"
client_msg_id="$(python3 - <<'PY'
import uuid; print(uuid.uuid4())
PY
)"
message="E2E production verify $(date -u +%Y-%m-%dT%H:%M:%SZ)"

echo "==> Creating customer session and syncing message"
page_html="$(curl -fsSL "$SITE/")"
nonce="$(printf '%s' "$page_html" | grep -oE '"nonce":"[a-f0-9]+"' | head -1 | sed 's/.*"\([a-f0-9]*\)".*/\1/')"
[[ -n "$nonce" ]] || fail "Could not extract chat nonce from homepage"

sync_payload=$(python3 - <<PY
import json
print(json.dumps([
  {"role":"user","content":"$message","id":1,"client_msg_id":"$client_msg_id"}
]))
PY
)

curl -fsSL -X POST "$SITE/wp-admin/admin-ajax.php" \
  -d "action=paxdesign_chat_log" \
  -d "nonce=$nonce" \
  -d "session_id=$session_id" \
  --data-urlencode "messages=$sync_payload" \
  -d "detected_service=E2E" >/dev/null

echo "==> Waiting for durable write"
sleep 2

echo "==> Verifying staff REST poll sees customer message"
poll_json="$(curl -fsSL -u "${ADMIN_USER}:${ADMIN_PASS}" \
  "$SITE/wp-json/paxdesign/v1/live-admin/sessions/${session_id}/poll?since=0&full=1")"

python3 - <<PY
import json, sys
data = json.loads('''$poll_json''')
messages = data.get('messages') or data.get('data', {}).get('messages') or []
roles = [m.get('role') for m in messages]
contents = [m.get('content','') for m in messages]
if 'user' not in roles:
    raise SystemExit('customer message missing from staff poll')
if "$message" not in contents:
    raise SystemExit('expected customer message text missing from staff poll')
print('PASS: staff poll contains customer message')
PY

pass "End-to-end production messaging verification complete for session ${session_id}"
