#!/usr/bin/env bash
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
EXPECTED_BOOKING="${PAX_EXPECTED_BOOKING:-3.166.0}"
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
  grep -q '"last_role"' /tmp/cx-sync.json || fail "conversations/sync missing last_role on sessions"
  grep -q '"needs_reply"' /tmp/cx-sync.json || fail "conversations/sync missing needs_reply on sessions"
  pass "Staff sync exposes unread badge fields (last_role, needs_reply)"

  notif_code="$(curl -sS -o /tmp/cx-notif.json -w '%{http_code}' -u "${ADMIN_USER}:${ADMIN_PASS}" \
    "$SITE/wp-json/paxdesign/v1/platform/notifications")"
  [ "$notif_code" = "200" ] || fail "platform/notifications returned HTTP $notif_code"
  grep -q '"unread_chats"' /tmp/cx-notif.json || fail "platform/notifications missing unread_chats"
  pass "Platform notifications summary reachable"
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
