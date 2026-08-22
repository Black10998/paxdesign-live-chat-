#!/usr/bin/env bash
# Live checks for the Apple-style account UI deploy.
set -euo pipefail

BASE="${BASE:-https://paxdesign.at}"
STAMP="$(date +%s)"
FAIL=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ok() { echo "OK  $*"; }
fail() { echo "FAIL $*"; FAIL=$((FAIL + 1)); }

code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/pax-auth.js" -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/customer-auth/js/pax-auth.js?n=${STAMP}")"
[ "$code" = "200" ] && ok "pax-auth.js HTTP 200" || fail "pax-auth.js HTTP ${code}"

grep -q "pdx-account-sidebar-profile" "$TMP/pax-auth.js" && ok "live JS has single sidebar profile control" || fail "live JS missing sidebar profile control"
grep -q "function renderAccountPreferencesSection" "$TMP/pax-auth.js" && ok "live JS has notification preferences section" || fail "live JS missing preferences section"
grep -q "Security & Privacy" "$TMP/pax-auth.js" && ok "live JS has Security & Privacy" || fail "live JS missing Security & Privacy"
if grep -q "pdx-account-profile-name" "$TMP/pax-auth.js"; then
  python3 - "$TMP/pax-auth.js" <<'PY' || fail "could not inspect personal renderer"
import re, sys
src = open(sys.argv[1], encoding="utf-8").read()
m = re.search(r"function renderAccountPersonalSection\([\s\S]*?\n  function ", src)
if not m:
    raise SystemExit(1)
if "pdx-account-profile-name" in m.group(0):
    raise SystemExit(2)
print("personal_ok")
PY
  if [ "${PIPESTATUS[0]}" = "0" ]; then
    ok "personal renderer does not repeat the username heading"
  else
    fail "personal renderer still repeats the username heading"
  fi
else
  ok "personal renderer does not repeat the username heading"
fi

css_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/account-app.css" -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/customer-auth/css/pdx-account-app.css?n=${STAMP}")"
[ "$css_code" = "200" ] && ok "pdx-account-app.css HTTP 200" || fail "pdx-account-app.css HTTP ${css_code}"
grep -q ".pdx-apple-group" "$TMP/account-app.css" && ok "live CSS has Apple grouped lists" || fail "live CSS missing Apple grouped lists"
grep -q "background: #f5f5f7" "$TMP/account-app.css" && ok "live CSS uses Apple gray canvas" || fail "live CSS missing Apple gray canvas"

auth_css_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/pdx-auth.css" -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/customer-auth/css/pdx-auth.css?n=${STAMP}")"
[ "$auth_css_code" = "200" ] && ok "pdx-auth.css HTTP 200" || fail "pdx-auth.css HTTP ${auth_css_code}"
grep -q "pdx-profile-overlay--apple" "$TMP/pdx-auth.css" && ok "live overlay CSS is Apple-styled" || fail "live overlay CSS missing Apple sheet"

hp_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/apple-homepage.css" -w '%{http_code}' "${BASE}/wp-content/themes/navein/assets/css/apple-homepage.css?n=${STAMP}")"
[ "$hp_code" = "200" ] && ok "homepage CSS HTTP 200" || fail "homepage CSS HTTP ${hp_code}"
grep -q 'Orbitron' "$TMP/apple-homepage.css" && ok "homepage Orbitron headings unchanged" || fail "homepage Orbitron missing"

chat_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/chat-script.js" -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/js/chat-script.js?n=${STAMP}")"
[ "$chat_code" = "200" ] && ok "chat JS HTTP 200" || fail "chat JS HTTP ${chat_code}"
grep -q "Version: 3.174.128" "$TMP/chat-script.js" && ok "chat remains 3.174.128" || fail "chat version changed"
grep -q "skipping stacked sync" "$TMP/chat-script.js" && fail "chat contains 3.176 stacked sync" || ok "chat has no stacked sync rewrite"
grep -q "Gespräch beenden" "$TMP/chat-script.js" && fail "chat contains Gespräch beenden" || ok "chat has no Gespräch beenden"

acct_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/account.html" -w '%{http_code}' "${BASE}/account/")"
[ "$acct_code" = "200" ] && ok "/account/ HTTP 200" || fail "/account/ HTTP ${acct_code}"

if [ "$FAIL" -ne 0 ]; then
  echo "$FAIL live account UI check(s) failed"
  exit 1
fi
echo "Live account Apple UI checks passed."
