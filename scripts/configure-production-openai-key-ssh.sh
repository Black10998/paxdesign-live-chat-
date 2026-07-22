#!/usr/bin/env bash
# Update production WordPress OpenAI API key via SSH + wp-cli (key from env, never from git).
set -euo pipefail

KEY="${PAX_OPENAI_API_KEY:-}"
WP_SSH_HOST="${WP_SSH_HOST:-}"
WP_SSH_USER="${WP_SSH_USER:-}"
WP_SSH_PRIVATE_KEY="${WP_SSH_PRIVATE_KEY:-}"
WP_PATH="${WP_PATH:-}"
WP_SSH_PORT="${WP_SSH_PORT:-22}"

fail() { echo "FAIL: $1" >&2; exit 1; }
pass() { echo "PASS: $1"; }

[ -n "$KEY" ] || fail "Missing PAX_OPENAI_API_KEY"
[ -n "$WP_SSH_HOST" ] || fail "Missing WP_SSH_HOST"
[ -n "$WP_SSH_USER" ] || fail "Missing WP_SSH_USER"
[ -n "$WP_SSH_PRIVATE_KEY" ] || fail "Missing WP_SSH_PRIVATE_KEY"
[ -n "$WP_PATH" ] || fail "Missing WP_PATH"

PORT="$WP_SSH_PORT"
SSH_OPTS=(-i ~/.ssh/id_deploy -p "$PORT" -o StrictHostKeyChecking=yes -o BatchMode=yes)
install -m 600 -D /dev/null ~/.ssh/id_deploy
printf '%s\n' "$WP_SSH_PRIVATE_KEY" > ~/.ssh/id_deploy
ssh-keyscan -p "$PORT" -H "$WP_SSH_HOST" >> ~/.ssh/known_hosts 2>/dev/null || true

REMOTE_SCRIPT="wp-content/plugins/paxdesign-booking/scripts/wp-eval-update-openai-key.php"
ESCAPED_KEY="$(printf '%q' "$KEY")"
ESCAPED_PATH="$(printf '%q' "$WP_PATH")"

OUTPUT="$(ssh "${SSH_OPTS[@]}" "${WP_SSH_USER}@${WP_SSH_HOST}" \
  "cd ${ESCAPED_PATH} && PAX_OPENAI_API_KEY=${ESCAPED_KEY} wp eval-file ${REMOTE_SCRIPT}")"

echo "$OUTPUT"
echo "$OUTPUT" | grep -q '^OK:' || fail "Remote OpenAI key update did not succeed"
pass "Production OpenAI API key updated"
