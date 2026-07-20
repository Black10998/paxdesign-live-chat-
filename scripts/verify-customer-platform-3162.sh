#!/usr/bin/env bash
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
EXPECTED_BOOKING="${PAX_EXPECTED_BOOKING:-3.165.0}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"

pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1" >&2; exit 1; }
skip() { echo "SKIP: $1"; }

echo "==> Verify customer platform ${EXPECTED_BOOKING}"

services_code="$(curl -sS -o /tmp/cx-services.json -w '%{http_code}' "$SITE/wp-json/pdx/v1/customer/services")"
[ "$services_code" = "200" ] || fail "GET /customer/services returned HTTP $services_code"
pass "Public services route healthy"

for path in dashboard profile chat/messages notifications orders projects news; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/wp-json/pdx/v1/customer/$path")"
  [ "$code" = "401" ] || [ "$code" = "403" ] || fail "Unauthenticated /customer/$path returned HTTP $code"
done
pass "Customer routes require authentication"

staff_code="$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/wp-json/paxdesign/v1/live-admin/sessions")"
[ "$staff_code" = "401" ] || [ "$staff_code" = "403" ] || fail "Staff live-admin route missing (HTTP $staff_code)"
pass "Staff live-admin namespace reachable"

if [[ -n "$ADMIN_USER" && -n "$ADMIN_PASS" ]]; then
  sync_code="$(curl -sS -o /tmp/cx-sync.json -w '%{http_code}' -u "${ADMIN_USER}:${ADMIN_PASS}" \
    "$SITE/wp-json/paxdesign/v1/live-admin/conversations/sync")"
  [ "$sync_code" = "200" ] || fail "conversations/sync returned HTTP $sync_code"
  grep -q '"sessions"' /tmp/cx-sync.json || fail "conversations/sync missing sessions key"
  if grep -q '"threads":{' /tmp/cx-sync.json && grep -q '"threads":{}' /tmp/cx-sync.json; then
    pass "conversations/sync returns lightweight payload (empty threads map)"
  else
    thread_keys="$(python3 - <<'PY'
import json
data=json.load(open("/tmp/cx-sync.json"))
print(len((data.get("threads") or {})))
PY
)"
    if [[ "$thread_keys" -gt 20 ]]; then
      fail "conversations/sync still embeds ${thread_keys} full threads (expected lightweight sync)"
    fi
    pass "conversations/sync thread count acceptable (${thread_keys})"
  fi
else
  skip "Authenticated sync probe (credentials not set)"
fi

echo "==> Verify installed plugin version on server (when WP_PATH available)"
if [[ -n "${WP_PATH:-}" && -f "${WP_PATH}/wp-content/plugins/paxdesign-booking/paxdesign-booking.php" ]]; then
  VER="$(grep "define('PAXDESIGN_BOOKING_VERSION'" "${WP_PATH}/wp-content/plugins/paxdesign-booking/paxdesign-booking.php" | sed "s/.*'\([^']*\)'.*/\1/")"
  [ "$VER" = "$EXPECTED_BOOKING" ] || fail "Expected plugin $EXPECTED_BOOKING, found $VER"
  pass "Plugin version $VER"
else
  skip "Local WP_PATH plugin version check"
fi

pass "Customer platform ${EXPECTED_BOOKING} verification complete"
