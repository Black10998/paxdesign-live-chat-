#!/usr/bin/env bash
# End-to-end verification: WordPress push/apns registration REST contract.
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
ADMIN_USER="${PAX_ADMIN_USER:-}"
ADMIN_PASS="${PAX_ADMIN_APP_PASSWORD:-}"

fail() { echo "VERIFY_FAIL: $*"; exit 1; }
pass() { echo "VERIFY_OK: $*"; }

echo "=== APNs registration E2E (WordPress REST) ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "Site: $SITE"

[[ -n "$ADMIN_USER" && -n "$ADMIN_PASS" ]] || fail "PAX_ADMIN_USER and PAX_ADMIN_APP_PASSWORD required"

FAKE_TOKEN="$(printf 'a%.0s' {1..64})"
PAYLOAD=$(cat <<JSON
{
  "device_token": "$FAKE_TOKEN",
  "sandbox": false,
  "bundle_id": "at.paxdesign.livechat",
  "device_id": "e2e-verify-$(date +%s)",
  "device_name": "E2E Verify",
  "device_model": "iPhone",
  "os_version": "iOS 18",
  "app_version": "1.64.0"
}
JSON
)

HTTP=$(curl -sS -w "\n%{http_code}" --max-time 20 \
  -u "${ADMIN_USER}:${ADMIN_PASS}" \
  -H "Content-Type: application/json" \
  -X POST \
  -d "$PAYLOAD" \
  "${SITE}/wp-json/paxdesign/v1/live-admin/push/apns")
BODY=$(echo "$HTTP" | sed '$d')
CODE=$(echo "$HTTP" | tail -1)

echo "HTTP $CODE"
echo "Body: $BODY"

[[ "$CODE" == "200" || "$CODE" == "201" ]] || fail "push/apns returned HTTP $CODE"

echo "$BODY" | grep -q '"ok":true' || fail "Response missing ok:true"
echo "$BODY" | grep -q '"accepted":true' || fail "Response missing accepted:true"
echo "$BODY" | grep -q '"push_registered":true' || fail "Response missing push_registered:true"
echo "$BODY" | grep -q '"token_stored":true' || fail "Response missing token_stored:true"

pass "WordPress push/apns accepts registration payload"

# Cleanup fake token
curl -sS -o /dev/null --max-time 15 \
  -u "${ADMIN_USER}:${ADMIN_PASS}" \
  -H "Content-Type: application/json" \
  -X DELETE \
  -d "{\"device_token\":\"$FAKE_TOKEN\"}" \
  "${SITE}/wp-json/paxdesign/v1/live-admin/push/apns" || true

pass "E2E verification complete"
