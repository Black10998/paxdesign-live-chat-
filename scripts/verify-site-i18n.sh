#!/usr/bin/env bash
# Live checks for Arabic localization, language switcher, and RTL.
set -euo pipefail

BASE="${BASE:-https://paxdesign.at}"
STAMP="$(date +%s)"
FAIL=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ok() { echo "OK  $*"; }
fail() { echo "FAIL $*"; FAIL=$((FAIL + 1)); }

code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/apple-site-rtl.css" -w '%{http_code}' "${BASE}/wp-content/themes/navein/assets/css/apple-site-rtl.css?n=${STAMP}")"
[ "$code" = "200" ] && ok "apple-site-rtl.css HTTP 200" || fail "apple-site-rtl.css HTTP ${code}"
grep -q "pax-site-lang__btn" "$TMP/apple-site-rtl.css" && ok "live CSS has Apple language button" || fail "live CSS missing language button"
grep -q "pax-site-lang__menu" "$TMP/apple-site-rtl.css" && ok "live CSS has Apple language popover" || fail "live CSS missing language popover"

js_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/apple-site-i18n.js" -w '%{http_code}' "${BASE}/wp-content/themes/navein/assets/js/apple-site-i18n.js?n=${STAMP}")"
[ "$js_code" = "200" ] && ok "apple-site-i18n.js HTTP 200" || fail "apple-site-i18n.js HTTP ${js_code}"
grep -q "pax_site_lang" "$TMP/apple-site-i18n.js" && ok "live JS persists language cookie" || fail "live JS missing language cookie"

hp_code="$(curl -sS -A 'Mozilla/5.0' -H 'Accept-Language: ar' -o "$TMP/home-ar.html" -w '%{http_code}' "${BASE}/?lang=ar&n=${STAMP}")"
[ "$hp_code" = "200" ] && ok "Arabic homepage HTTP 200" || fail "Arabic homepage HTTP ${hp_code}"
grep -q 'id="pax-site-lang"' "$TMP/home-ar.html" && ok "homepage has desktop language switcher" || fail "homepage missing desktop language switcher"
grep -q 'id="pax-site-lang-mobile"' "$TMP/home-ar.html" && ok "homepage has mobile language switcher" || fail "homepage missing mobile language switcher"
grep -q 'dir="rtl"' "$TMP/home-ar.html" && ok "Arabic homepage is RTL" || fail "Arabic homepage is not RTL"
grep -qi 'lang="ar"' "$TMP/home-ar.html" && ok "Arabic homepage html lang is ar" || fail "Arabic homepage html lang is not ar"
grep -q "أنظمة رقمية" "$TMP/home-ar.html" && ok "Arabic homepage hero is translated" || fail "Arabic homepage hero is still German"
grep -q "dtr-search-modal-trigger" "$TMP/home-ar.html" && ok "Arabic homepage still has Search" || fail "Arabic homepage missing Search"

de_code="$(curl -sS -A 'Mozilla/5.0' -H 'Accept-Language: de' -o "$TMP/home-de.html" -w '%{http_code}' "${BASE}/?lang=de&n=${STAMP}")"
[ "$de_code" = "200" ] && ok "German homepage HTTP 200" || fail "German homepage HTTP ${de_code}"
grep -q 'dir="ltr"' "$TMP/home-de.html" && ok "German homepage is LTR" || fail "German homepage is not LTR"
grep -q "Angebot anfordern" "$TMP/home-de.html" && ok "German CTA remains Angebot anfordern" || fail "German CTA missing"

en_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/home-en.html" -w '%{http_code}' "${BASE}/?lang=en&n=${STAMP}")"
[ "$en_code" = "200" ] && ok "English homepage HTTP 200" || fail "English homepage HTTP ${en_code}"
grep -q "Request a quote" "$TMP/home-en.html" && ok "English CTA is translated" || fail "English CTA not translated"

tr_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/home-tr.html" -w '%{http_code}' "${BASE}/?lang=tr&n=${STAMP}")"
[ "$tr_code" = "200" ] && ok "Turkish homepage HTTP 200" || fail "Turkish homepage HTTP ${tr_code}"
grep -q "Teklif iste" "$TMP/home-tr.html" && ok "Turkish CTA is translated" || fail "Turkish CTA not translated"

chat_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/chat-script.js" -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/js/chat-script.js?n=${STAMP}")"
[ "$chat_code" = "200" ] && ok "chat JS HTTP 200" || fail "chat JS HTTP ${chat_code}"
grep -q "Version: 3.174.128" "$TMP/chat-script.js" && ok "chat remains 3.174.128" || fail "chat version changed"
grep -q "skipping stacked sync" "$TMP/chat-script.js" && fail "chat contains 3.176 stacked sync" || ok "chat has no stacked sync rewrite"
grep -q "Gespräch beenden" "$TMP/chat-script.js" && fail "chat contains Gespräch beenden" || ok "chat has no Gespräch beenden"

if [ "$FAIL" -ne 0 ]; then
  echo "$FAIL live site i18n check(s) failed"
  exit 1
fi
echo "Live site i18n checks passed."
