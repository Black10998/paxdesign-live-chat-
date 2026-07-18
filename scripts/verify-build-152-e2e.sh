#!/usr/bin/env bash
# Build 152 end-to-end verification against production APIs.
set -euo pipefail

BASE="${CUSTOMER_API_BASE:-https://paxdesign.at/wp-json/pdx/v1}"
STAFF_BASE="${STAFF_API_BASE:-https://paxdesign.at/wp-json/paxdesign/v1}"
SITE="${PAX_SITE:-https://paxdesign.at}"
EXPECTED_PLUGIN="${PAX_EXPECTED_PLUGIN:-3.152.2}"
CUSTOMER_USER="${PAX_CUSTOMER_USER:-}"
CUSTOMER_PASS="${PAX_CUSTOMER_APP_PASSWORD:-}"
STAFF_USER="${PAX_ADMIN_USER:-}"
STAFF_PASS="${PAX_ADMIN_APP_PASSWORD:-}"

FAIL=0
pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1"; FAIL=1; }

echo "== Build 152 production verification =="
echo "Plugin expected: $EXPECTED_PLUGIN"
echo

asset_ver="$(curl -fsSL "$SITE/" | grep -oE 'paxdesign-booking[^"]*ver=[0-9.]+' | head -1 | sed 's/.*ver=//' || true)"
if [[ "$asset_ver" == "$EXPECTED_PLUGIN"* ]] || [[ "$asset_ver" == "$EXPECTED_PLUGIN" ]]; then
  pass "Production plugin assets report v${asset_ver}"
else
  fail "Production plugin assets report v${asset_ver:-unknown}; expected v${EXPECTED_PLUGIN}"
fi

bash "$(dirname "$0")/verify-customer-platform-e2e.sh"

code="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE/customer/devices")"
if [[ "$code" == "401" || "$code" == "403" ]]; then
  pass "Customer devices endpoint protected ($code)"
else
  fail "Customer devices endpoint expected 401/403, got $code"
fi

if [[ -z "$CUSTOMER_USER" || -z "$CUSTOMER_PASS" ]]; then
  echo "SKIP: Authenticated order/device workflow (set PAX_CUSTOMER_USER + PAX_CUSTOMER_APP_PASSWORD)"
else
  echo "== Customer authenticated checks =="
  auth_header="$(printf '%s:%s' "$CUSTOMER_USER" "$CUSTOMER_PASS" | base64 | tr -d '\n')"
  orders_json="$(curl -fsSL -H "Authorization: Basic $auth_header" "$BASE/customer/orders")"
  python3 - <<PY
import json, sys
data = json.loads('''$orders_json''')
orders = data.get('orders') or []
print(f"Customer orders accessible: {len(orders)} item(s)")
PY
  pass "Customer can list orders"

  devices_json="$(curl -fsSL -H "Authorization: Basic $auth_header" "$BASE/customer/devices?current_device_id=e2e-test")"
  python3 - <<PY
import json
data = json.loads('''$devices_json''')
assert 'devices' in data
print(f"Customer devices endpoint OK ({len(data['devices'])} devices)")
PY
  pass "Customer devices API returns structured response"
fi

if [[ -z "$STAFF_USER" || -z "$STAFF_PASS" ]]; then
  echo "SKIP: Staff order workflow (set PAX_ADMIN_USER + PAX_ADMIN_APP_PASSWORD)"
else
  echo "== Staff authenticated checks =="
  staff_auth="$(printf '%s:%s' "$STAFF_USER" "$STAFF_PASS" | base64 | tr -d '\n')"
  staff_orders="$(curl -fsSL -H "Authorization: Basic $staff_auth" "$STAFF_BASE/customer/staff/orders")"
  python3 - <<PY
import json
data = json.loads('''$staff_orders''')
assert 'orders' in data
print(f"Staff orders endpoint OK ({len(data['orders'])} orders)")
PY
  pass "Staff can list customer orders"
fi

echo
if [[ "$FAIL" -ne 0 ]]; then
  echo "Verification failed."
  exit 1
fi
echo "Build 152 verification passed."
