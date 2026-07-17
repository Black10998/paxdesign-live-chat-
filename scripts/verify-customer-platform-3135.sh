#!/usr/bin/env bash
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
EXPECTED_BOOKING="${PAX_EXPECTED_BOOKING:-3.135.0}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"

pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1" >&2; exit 1; }
skip() { echo "SKIP: $1"; }

echo "==> Verify public customer services route (no auth)"
services_code="$(curl -sS -o /tmp/cx-services.json -w '%{http_code}' "$SITE/wp-json/pdx/v1/customer/services")"
[ "$services_code" = "200" ] || fail "GET /customer/services returned HTTP $services_code"
grep -q '"services"' /tmp/cx-services.json || fail "Services payload missing services key"
grep -qi 'paypal\|billing\|checkout' /tmp/cx-services.json && fail "Services payload exposes billing keywords" || true
pass "Public services route healthy"

echo "==> Verify customer routes reject unauthenticated access"
for path in dashboard profile chat/messages chat/stream notifications orders projects news; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/wp-json/pdx/v1/customer/$path")"
  [ "$code" = "401" ] || [ "$code" = "403" ] || fail "Unauthenticated /customer/$path returned HTTP $code (expected 401/403)"
done
pass "Customer routes require authentication"

echo "==> Verify staff live-admin routes still exist"
staff_code="$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/wp-json/paxdesign/v1/live-admin/sessions")"
[ "$staff_code" = "401" ] || [ "$staff_code" = "403" ] || fail "Staff live-admin route missing (HTTP $staff_code)"
pass "Staff live-admin namespace reachable"

echo "==> Verify toolbar auth namespace"
auth_code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$SITE/wp-json/pdx/v1/auth/login" -H 'Content-Type: application/json' -d '{}')"
[ "$auth_code" != "404" ] || fail "Toolbar /auth/login route missing"
pass "Toolbar auth namespace reachable"

if [ -z "$ADMIN_USER" ] || [ -z "$ADMIN_PASS" ]; then
  skip "Authenticated customer chat/stream verification (missing PAX_ADMIN_USER or PAX_ADMIN_APP_PASSWORD)"
  exit 0
fi

AUTH_HEADER="Authorization: Basic $(printf '%s' "$ADMIN_USER:$ADMIN_PASS" | base64 -w0)"

echo "==> Verify authenticated customer profile"
profile_code="$(curl -sS -o /tmp/cx-profile.json -w '%{http_code}' -H "$AUTH_HEADER" "$SITE/wp-json/pdx/v1/customer/profile")"
[ "$profile_code" = "200" ] || fail "Authenticated profile returned HTTP $profile_code"
pass "Authenticated profile accessible"

echo "==> Verify authenticated chat messages"
chat_code="$(curl -sS -o /tmp/cx-chat.json -w '%{http_code}' -H "$AUTH_HEADER" "$SITE/wp-json/pdx/v1/customer/chat/messages?full=1")"
[ "$chat_code" = "200" ] || fail "Authenticated chat messages returned HTTP $chat_code"
grep -q '"handler"' /tmp/cx-chat.json || fail "Chat poll payload missing handler"
pass "Authenticated chat poll accessible"

echo "==> Verify customer stream route responds for authenticated user"
stream_code="$(curl -sS -o /tmp/cx-stream.txt -w '%{http_code}' -H "$AUTH_HEADER" -H 'Accept: text/event-stream' -H 'Content-Type: application/json' -X POST "$SITE/wp-json/pdx/v1/customer/chat/stream" -d '{"message":"Customer platform verification ping"}')"
[ "$stream_code" = "200" ] || [ "$stream_code" = "409" ] || [ "$stream_code" = "503" ] || fail "Customer stream returned unexpected HTTP $stream_code"
pass "Customer AI stream route reachable (HTTP $stream_code)"

echo "==> Verify staff sessions still accessible for admin credentials"
staff_sessions_code="$(curl -sS -o /dev/null -w '%{http_code}' -H "$AUTH_HEADER" "$SITE/wp-json/paxdesign/v1/live-admin/sessions")"
[ "$staff_sessions_code" = "200" ] || [ "$staff_sessions_code" = "403" ] || fail "Staff sessions route regression (HTTP $staff_sessions_code)"
pass "Staff sessions route status HTTP $staff_sessions_code"

echo "ALL CHECKS PASSED for customer platform verification"
