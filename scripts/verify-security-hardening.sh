#!/usr/bin/env bash
# Live defensive checks after security hardening.
# Safe / non-destructive only. Never prints account identifiers or payloads.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE="${BASE:-https://paxdesign.at}"
STAMP="$(date +%s)"
FAIL=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ok() { echo "OK  $*"; }
fail() { echo "FAIL $*"; FAIL=$((FAIL + 1)); }

echo "Security hardening live verify against ${BASE}"

# Identity + CORS + media + login enumeration.
# shellcheck disable=SC1091
if ! BASE="$BASE" "$ROOT/scripts/verify-public-identity-hardening.sh"; then
  fail "identity hardening live verify failed"
fi

ajax() {
  local name="$1"
  local action="$2"
  shift 2
  curl -sS -o "$TMP/${name}.body" -w '%{http_code}' \
    -X POST "${BASE}/wp-admin/admin-ajax.php" \
    -d "action=${action}&n=${STAMP}" "$@"
}

nonce_code="$(ajax chat_nonce paxdesign_chat_nonce)"
if grep -q 'login_required' "$TMP/chat_nonce.body"; then
  ok "guest chat nonce requires login (HTTP ${nonce_code})"
else
  fail "guest chat nonce is still public (HTTP ${nonce_code})"
fi

attach_code="$(ajax chat_attach paxdesign_chat_live_user_attach --data-urlencode 'session_id=guest-probe')"
if grep -q 'login_required' "$TMP/chat_attach.body"; then
  ok "guest chat attach requires login (HTTP ${attach_code})"
else
  fail "guest chat attach is still reachable without login (HTTP ${attach_code})"
fi
if grep -Eiq 'please choose a file|wähle eine datei' "$TMP/chat_attach.body"; then
  fail "guest attach still reached the upload handler"
fi

disconnect_code="$(ajax chat_disconnect paxdesign_chat_disconnect --data-urlencode 'session_id=guest-probe')"
if grep -q 'login_required' "$TMP/chat_disconnect.body"; then
  ok "guest chat disconnect requires login (HTTP ${disconnect_code})"
else
  fail "guest chat disconnect is still reachable without login (HTTP ${disconnect_code})"
fi

readme_code="$(curl -sS -o "$TMP/readme.body" -w '%{http_code}' -H 'Cache-Control: no-cache' "${BASE}/readme.html?n=${STAMP}")"
if [ "$readme_code" = "200" ]; then
  fail "readme.html is still public (HTTP ${readme_code})"
else
  ok "readme.html is not public (HTTP ${readme_code})"
fi

llms_code="$(curl -sS -o "$TMP/llms.body" -w '%{http_code}' -H 'Cache-Control: no-cache' "${BASE}/llms.txt?n=${STAMP}")"
if [ "$llms_code" = "200" ]; then
  fail "llms.txt is still public (HTTP ${llms_code})"
else
  ok "llms.txt is not public (HTTP ${llms_code})"
fi

cf7_code="$(curl -sS -o "$TMP/cf7.body" -w '%{http_code}' -H 'Cache-Control: no-cache' \
  "${BASE}/wp-content/plugins/contact-form-7/readme.txt?n=${STAMP}")"
if [ "$cf7_code" = "200" ]; then
  fail "plugin readme.txt is still public (HTTP ${cf7_code})"
else
  ok "plugin readme.txt is not public (HTTP ${cf7_code})"
fi

if [ "$FAIL" -gt 0 ]; then
  echo "${FAIL} live security-hardening check(s) failed"
  exit 1
fi

echo "Security hardening live verification passed."
