#!/usr/bin/env bash
# End-to-end verification of public customer platform APIs (no credentials required).
set -euo pipefail

BASE="${CUSTOMER_API_BASE:-https://paxdesign.at/wp-json/pdx/v1}"
LANGS=(de en ar)
FAIL=0

check() {
  local name="$1"
  local url="$2"
  local expect="${3:-200}"
  local code
  code="$(curl -sS -o /tmp/pdx-verify-body.json -w '%{http_code}' "$url" || echo 000)"
  if [[ "$code" != "$expect" ]]; then
    echo "FAIL $name ($code != $expect): $url"
    FAIL=1
    return
  fi
  if ! python3 -c 'import json,sys; json.load(open("/tmp/pdx-verify-body.json"))' 2>/dev/null; then
    echo "FAIL $name (invalid JSON): $url"
    FAIL=1
    return
  fi
  echo "OK   $name ($code)"
}

echo "Customer platform E2E verification"
echo "Base: $BASE"
echo

for lang in "${LANGS[@]}"; do
  check "homepage/$lang" "$BASE/content/homepage?lang=$lang"
  check "about/$lang" "$BASE/content/about?lang=$lang"
  check "contact/$lang" "$BASE/content/contact?lang=$lang"
  check "services-catalog/$lang" "$BASE/content/services-catalog?lang=$lang"
done

for slug in datenschutz agb ueber-uns; do
  check "legal/$slug" "$BASE/content/legal/$slug?lang=de"
done

check "portfolio list" "$BASE/customer/portfolio?limit=5"
check "services list" "$BASE/customer/services"
check "site menu" "$BASE/content/site-menu?lang=de"

# Auth-protected routes must reject unauthenticated access (401/403).
for path in \
  "/customer/profile" \
  "/customer/dashboard" \
  "/customer/chat/session" \
  "/customer/notifications" \
  "/customer/projects" \
  "/customer/orders"; do
  code="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE$path" || echo 000)"
  if [[ "$code" == "401" || "$code" == "403" ]]; then
    echo "OK   protected $path ($code)"
  else
    echo "FAIL protected $path (expected 401/403, got $code)"
    FAIL=1
  fi
done

echo
if [[ "$FAIL" -ne 0 ]]; then
  echo "Verification failed."
  exit 1
fi
echo "All public customer platform checks passed."
