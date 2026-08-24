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
grep -q "storedManualLang" "$TMP/apple-site-i18n.js" && ok "live JS restores a manual language choice" || fail "live JS missing manual language restore"

hp_code="$(curl -sS -A 'Mozilla/5.0' -H 'Accept-Language: ar' -o "$TMP/home-ar.html" -w '%{http_code}' "${BASE}/?lang=ar&n=${STAMP}")"
[ "$hp_code" = "200" ] && ok "Arabic homepage HTTP 200" || fail "Arabic homepage HTTP ${hp_code}"
ar_size="$(wc -c < "$TMP/home-ar.html")"
[ "$ar_size" -gt 20000 ] && ok "Arabic homepage is not empty (${ar_size} bytes)" || fail "Arabic homepage empty (${ar_size} bytes)"
grep -q 'id="pax-site-lang"' "$TMP/home-ar.html" && ok "homepage has desktop language switcher" || fail "homepage missing desktop language switcher"
grep -q 'id="pax-site-lang-mobile"' "$TMP/home-ar.html" && ok "homepage has mobile language switcher" || fail "homepage missing mobile language switcher"
grep -q 'data-lang="de"' "$TMP/home-ar.html" && grep -q 'data-lang="en"' "$TMP/home-ar.html" && grep -q 'data-lang="ar"' "$TMP/home-ar.html" && grep -q 'data-lang="tr"' "$TMP/home-ar.html" && ok "language switcher has DE/EN/AR/TR badges" || fail "language switcher missing a language badge"
grep -q 'الألمانية' "$TMP/home-ar.html" && grep -q 'الإنجليزية' "$TMP/home-ar.html" && grep -q 'العربية' "$TMP/home-ar.html" && grep -q 'التركية' "$TMP/home-ar.html" && ok "Arabic page shows every language badge in Arabic" || fail "Arabic page missing translated language badges"
grep -q 'dir="rtl"' "$TMP/home-ar.html" && ok "Arabic homepage is RTL" || fail "Arabic homepage is not RTL"
grep -qi 'lang="ar"' "$TMP/home-ar.html" && ok "Arabic homepage html lang is ar" || fail "Arabic homepage html lang is not ar"
grep -q "أنظمة رقمية" "$TMP/home-ar.html" && ok "Arabic homepage hero is translated" || fail "Arabic homepage hero is still German"
grep -q '>اطلب عرضاً<' "$TMP/home-ar.html" && ok "Arabic CTA is translated in HTML" || fail "Arabic CTA not translated in HTML"
grep -q "dtr-search-modal-trigger" "$TMP/home-ar.html" && ok "Arabic homepage still has Search" || fail "Arabic homepage missing Search"

de_code="$(curl -sS -A 'Mozilla/5.0' -H 'Accept-Language: de' -o "$TMP/home-de.html" -w '%{http_code}' "${BASE}/?lang=de&n=${STAMP}")"
[ "$de_code" = "200" ] && ok "German homepage HTTP 200" || fail "German homepage HTTP ${de_code}"
grep -q 'dir="ltr"' "$TMP/home-de.html" && ok "German homepage is LTR" || fail "German homepage is not LTR"
grep -q ">Angebot anfordern<" "$TMP/home-de.html" && ok "German CTA remains Angebot anfordern" || fail "German CTA missing"
grep -q 'data-lang="de"' "$TMP/home-de.html" && grep -q 'data-lang="tr"' "$TMP/home-de.html" && ok "German page still has all language badges" || fail "German page missing language badges"

en_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/home-en.html" -w '%{http_code}' "${BASE}/?lang=en&n=${STAMP}")"
[ "$en_code" = "200" ] && ok "English homepage HTTP 200" || fail "English homepage HTTP ${en_code}"
en_size="$(wc -c < "$TMP/home-en.html")"
[ "$en_size" -gt 20000 ] && ok "English homepage is not empty (${en_size} bytes)" || fail "English homepage empty (${en_size} bytes)"
grep -q '>Request a quote<' "$TMP/home-en.html" && ok "English CTA is translated in HTML" || fail "English CTA not translated in HTML"

tr_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/home-tr.html" -w '%{http_code}' "${BASE}/?lang=tr&n=${STAMP}")"
[ "$tr_code" = "200" ] && ok "Turkish homepage HTTP 200" || fail "Turkish homepage HTTP ${tr_code}"
tr_size="$(wc -c < "$TMP/home-tr.html")"
[ "$tr_size" -gt 20000 ] && ok "Turkish homepage is not empty (${tr_size} bytes)" || fail "Turkish homepage empty (${tr_size} bytes)"
grep -q '>Teklif iste<' "$TMP/home-tr.html" && ok "Turkish CTA is translated in HTML" || fail "Turkish CTA not translated in HTML"
grep -q 'Almanca' "$TMP/home-tr.html" && grep -q 'İngilizce' "$TMP/home-tr.html" && grep -q 'Arapça' "$TMP/home-tr.html" && grep -q 'Türkçe' "$TMP/home-tr.html" && ok "Turkish page shows every language badge in Turkish" || fail "Turkish page missing translated language badges"

sleep 2
svc_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/services-en.html" -w '%{http_code}' "${BASE}/leistungen/?lang=en&n=${STAMP}")"
[ "$svc_code" = "200" ] && ok "English services HTTP 200" || fail "English services HTTP ${svc_code}"
grep -q "How we work with you" "$TMP/services-en.html" && ok "English services body copy is translated" || fail "English services still missing How we work with you"
grep -q "So arbeiten wir mit Ihnen" "$TMP/services-en.html" && fail "English services still has German how-we-work heading" || ok "English services no longer has German how-we-work heading"
grep -q "Projekte &amp; Referenzen" "$TMP/services-en.html" && fail "English services still has encoded German mega-menu label" || ok "English services mega-menu ampersand label is translated"

sleep 2
imp_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/imprint-en.html" -w '%{http_code}' "${BASE}/impressum/?lang=en&n=${STAMP}")"
[ "$imp_code" = "200" ] && ok "English imprint HTTP 200" || fail "English imprint HTTP ${imp_code}"
grep -q "Managing director" "$TMP/imprint-en.html" && ok "English imprint translates Geschäftsführer" || fail "English imprint still has German Geschäftsführer"
grep -q ">Geschäftsführer<" "$TMP/imprint-en.html" && fail "English imprint still has Geschäftsführer markup" || ok "English imprint has no Geschäftsführer markup"

sleep 2
priv_code="$(curl -sS -A 'Mozilla/5.0' -o "$TMP/privacy-ar.html" -w '%{http_code}' "${BASE}/datenschutz/?lang=ar&n=${STAMP}")"
[ "$priv_code" = "200" ] && ok "Arabic privacy HTTP 200" || fail "Arabic privacy HTTP ${priv_code}"
grep -q "سياسة الخصوصية" "$TMP/privacy-ar.html" && ok "Arabic privacy policy heading is translated" || fail "Arabic privacy page missing سياسة الخصوصية"
grep -q 'dir="rtl"' "$TMP/privacy-ar.html" && ok "Arabic privacy page is RTL" || fail "Arabic privacy page is not RTL"

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
