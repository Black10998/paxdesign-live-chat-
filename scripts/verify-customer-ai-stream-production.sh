#!/usr/bin/env bash
# Verify authenticated customer AI stream returns HTTP 200 SSE on production.
# Resets the admin user's primary chat session handler to "ai" before testing.
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"
WP_SSH_HOST="${WP_SSH_HOST:-}"
WP_SSH_USER="${WP_SSH_USER:-}"
WP_SSH_PRIVATE_KEY="${WP_SSH_PRIVATE_KEY:-}"
WP_SSH_PORT="${WP_SSH_PORT:-22}"
WP_PATH="${WP_PATH:-}"

pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1" >&2; exit 1; }

if [ -z "$ADMIN_USER" ] || [ -z "$ADMIN_PASS" ]; then
  fail "Missing PAX_ADMIN_USER or PAX_ADMIN_APP_PASSWORD"
fi

if [ -z "$WP_SSH_HOST" ] || [ -z "$WP_SSH_USER" ] || [ -z "$WP_SSH_PRIVATE_KEY" ] || [ -z "$WP_PATH" ]; then
  fail "Missing WP SSH deployment secrets for session reset"
fi

AUTH_HEADER="Authorization: Basic $(printf '%s' "$ADMIN_USER:$ADMIN_PASS" | base64 -w0)"

echo "==> Reset primary customer chat session handler to ai on production"
PORT="$WP_SSH_PORT"
SSH_OPTS=(-i ~/.ssh/id_deploy -p "$PORT" -o StrictHostKeyChecking=yes -o BatchMode=yes)
install -m 600 -D /dev/null ~/.ssh/id_deploy
printf '%s\n' "$WP_SSH_PRIVATE_KEY" > ~/.ssh/id_deploy
ssh-keyscan -p "$PORT" -H "$WP_SSH_HOST" >> ~/.ssh/known_hosts 2>/dev/null || true

REMOTE_LOGIN="$(printf '%q' "$ADMIN_USER")"
SESSION_ID="$(ssh "${SSH_OPTS[@]}" "${WP_SSH_USER}@${WP_SSH_HOST}" \
  "cd '$(printf '%q' "$WP_PATH")' && PAX_RESET_LOGIN=${REMOTE_LOGIN} wp eval-file wp-content/plugins/paxdesign-booking/scripts/wp-eval-reset-customer-chat-session.php")"

[ -n "$SESSION_ID" ] || fail "Could not resolve or reset customer chat session"
echo "Using session: $SESSION_ID"

echo "==> POST /customer/chat/stream (expect HTTP 200 SSE)"
stream_code="$(curl -sS -o /tmp/cx-stream-live.txt -w '%{http_code}' \
  -H "$AUTH_HEADER" \
  -H 'Accept: text/event-stream' \
  -H 'Content-Type: application/json' \
  -X POST "$SITE/wp-json/pdx/v1/customer/chat/stream" \
  -d "{\"session_id\":\"$SESSION_ID\",\"message\":\"Production AI stream verification $(date -u +%Y%m%dT%H%M%SZ)\"}")"

if [ "$stream_code" != "200" ]; then
  head -c 600 /tmp/cx-stream-live.txt >&2 || true
  fail "Customer AI stream returned HTTP $stream_code (expected 200 SSE)"
fi

grep -q '^data:' /tmp/cx-stream-live.txt || fail "Stream HTTP 200 but response lacks SSE data: lines"
pass "Customer AI stream returned HTTP 200 with SSE payload"
echo "ALL CHECKS PASSED for production AI stream verification"
