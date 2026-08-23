#!/usr/bin/env bash
# Live checks for the stable Apple header + isolated Search control.
set -euo pipefail

BASE="${BASE:-https://paxdesign.at}"
STAMP="$(date +%s)"
FAIL=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ok() { echo "OK  $*"; }
fail() { echo "FAIL $*"; FAIL=$((FAIL + 1)); }

code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/apple-header-stable.css" -w '%{http_code}' "${BASE}/wp-content/themes/navein/assets/css/apple-header-stable.css?n=${STAMP}")"
[ "$code" = "200" ] && ok "apple-header-stable.css HTTP 200" || fail "apple-header-stable.css HTTP ${code}"
grep -q "dtr-search-modal-trigger" "$TMP/apple-header-stable.css" && ok "live CSS isolates Search" || fail "live CSS missing Search isolation"
grep -q "max-height: var(--dtr-apple-header-height)" "$TMP/apple-header-stable.css" && ok "live CSS locks header height" || fail "live CSS missing header height lock"
grep -q "pdx-auth-menu--apple" "$TMP/apple-header-stable.css" && ok "live CSS keeps the profile dropdown fixed" || fail "live CSS missing dropdown exception"
grep -q "cybercrime-menu" "$TMP/apple-header-stable.css" && ok "live CSS keeps Cybercrime Support unclipped" || fail "live CSS missing Cybercrime Support guard"
grep -q "a.dtr-btn.dtr-header-btn" "$TMP/apple-header-stable.css" && ok "live CSS scales Angebot anfordern" || fail "live CSS missing compact CTA"
grep -q "pdx-header-user-name" "$TMP/apple-header-stable.css" && ok "live CSS scales the logged-in name" || fail "live CSS missing username scale"
grep -q "min-width: 0 !important" "$TMP/apple-header-stable.css" && ok "live CSS lets nav shrink" || fail "live CSS missing nav shrink guard"
grep -q "background-image: none !important" "$TMP/apple-header-stable.css" && ok "live CSS fixes unreadable gold level badge" || fail "live CSS missing level badge contrast fix"
grep -q "flex-direction: row !important" "$TMP/apple-header-stable.css" && ok "live CSS keeps identity on one row" || fail "live CSS missing horizontal identity layout"
grep -q "dtr-has-mega" "$TMP/apple-header-stable.css" && ok "live CSS keeps mega menus unclipped" || fail "live CSS missing mega-menu overflow restore"
if grep -A8 "#dtr-header-global .main-navigation {" "$TMP/apple-header-stable.css" | grep -q "overflow: hidden"; then
  fail "live CSS still clips .main-navigation"
else
  ok "live CSS does not clip .main-navigation"
fi

js_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/pax-auth.js" -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/customer-auth/js/pax-auth.js?n=${STAMP}")"
[ "$js_code" = "200" ] && ok "pax-auth.js HTTP 200" || fail "pax-auth.js HTTP ${js_code}"
grep -q "pdx-auth-menu--apple" "$TMP/pax-auth.js" && ok "live JS keeps the Apple profile dropdown" || fail "live JS missing Apple profile dropdown"
grep -q "#dtr-header-global .dtr-header-global-content" "$TMP/pax-auth.js" && ok "live JS mounts auth in the glass header" || fail "live JS missing glass header mount"

hp_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/home.html" -w '%{http_code}' "${BASE}/?n=${STAMP}")"
[ "$hp_code" = "200" ] && ok "homepage HTTP 200" || fail "homepage HTTP ${hp_code}"
grep -q "apple-header-stable.css" "$TMP/home.html" && ok "homepage loads stable header CSS" || fail "homepage missing stable header CSS"
grep -q "dtr-search-modal-trigger" "$TMP/home.html" && ok "homepage still has the Search trigger" || fail "homepage missing Search trigger"
grep -q "Cybercrime Support" "$TMP/home.html" && ok "homepage still has the full Cybercrime Support label" || fail "homepage missing Cybercrime Support label"
grep -q "dtr-has-mega" "$TMP/home.html" && ok "homepage still has mega-menu items" || fail "homepage missing mega-menu items"
grep -q "dtr-mega-panel" "$TMP/home.html" && ok "homepage still has mega-menu panels" || fail "homepage missing mega-menu panels"

chat_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/chat-script.js" -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/js/chat-script.js?n=${STAMP}")"
[ "$chat_code" = "200" ] && ok "chat JS HTTP 200" || fail "chat JS HTTP ${chat_code}"
grep -q "Version: 3.174.128" "$TMP/chat-script.js" && ok "chat remains 3.174.128" || fail "chat version changed"
grep -q "skipping stacked sync" "$TMP/chat-script.js" && fail "chat contains 3.176 stacked sync" || ok "chat has no stacked sync rewrite"
grep -q "Gespräch beenden" "$TMP/chat-script.js" && fail "chat contains Gespräch beenden" || ok "chat has no Gespräch beenden"

if [ "$FAIL" -ne 0 ]; then
  echo "$FAIL live header stability check(s) failed"
  exit 1
fi
echo "Live header stability checks passed."
