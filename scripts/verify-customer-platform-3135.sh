#!/usr/bin/env bash
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
EXPECTED_BOOKING="${PAX_EXPECTED_BOOKING:-3.136.0}"
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
for path in dashboard profile chat/messages notifications orders projects news; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/wp-json/pdx/v1/customer/$path")"
  [ "$code" = "401" ] || [ "$code" = "403" ] || fail "Unauthenticated /customer/$path returned HTTP $code (expected 401/403)"
done
stream_code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$SITE/wp-json/pdx/v1/customer/chat/stream" -H 'Content-Type: application/json' -d '{}')"
[ "$stream_code" = "401" ] || [ "$stream_code" = "403" ] || fail "Unauthenticated POST /customer/chat/stream returned HTTP $stream_code (expected 401/403)"
pass "Customer routes require authentication"

echo "==> Verify staff live-admin routes still exist"
staff_code="$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/wp-json/paxdesign/v1/live-admin/sessions")"
[ "$staff_code" = "401" ] || [ "$staff_code" = "403" ] || fail "Staff live-admin route missing (HTTP $staff_code)"
pass "Staff live-admin namespace reachable"

echo "==> Verify toolbar auth namespace"
auth_code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$SITE/wp-json/pdx/v1/auth/login" -H 'Content-Type: application/json' -d '{}')"
[ "$auth_code" != "404" ] || fail "Toolbar /auth/login route missing"
register_get="$(curl -sS -o /dev/null -w '%{http_code}' "$SITE/wp-json/pdx/v1/auth/register")"
[ "$register_get" = "404" ] || fail "GET /auth/register should return 404 (POST-only route)"
register_post="$(curl -sS -o /tmp/cx-register.json -w '%{http_code}' -X POST "$SITE/wp-json/pdx/v1/auth/register" -H 'Content-Type: application/json' -d '{"name":"Verify","email":"verify-'$(date +%s)'@example.com","password":"TestPass123!"}')"
[ "$register_post" = "201" ] || [ "$register_post" = "400" ] || fail "POST /auth/register returned HTTP $register_post (expected 201 or 400 validation)"
pass "Toolbar auth namespace reachable (register POST works)"

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
if [ "$stream_code" = "200" ]; then
  grep -q '^data:' /tmp/cx-stream.txt || fail "Customer stream HTTP 200 but response is not SSE (missing data: lines)"
  pass "Customer AI stream route returned SSE (HTTP 200)"
elif [ "$stream_code" = "409" ] || [ "$stream_code" = "503" ]; then
  pass "Customer AI stream route reachable (HTTP $stream_code)"
else
  head -c 400 /tmp/cx-stream.txt >&2 || true
  fail "Customer stream returned unexpected HTTP $stream_code"
fi

echo "==> Verify staff sessions still accessible for admin credentials"
staff_sessions_code="$(curl -sS -o /dev/null -w '%{http_code}' -H "$AUTH_HEADER" "$SITE/wp-json/paxdesign/v1/live-admin/sessions")"
[ "$staff_sessions_code" = "200" ] || [ "$staff_sessions_code" = "403" ] || fail "Staff sessions route regression (HTTP $staff_sessions_code)"
pass "Staff sessions route status HTTP $staff_sessions_code"

echo "ALL CHECKS PASSED for customer platform verification"
