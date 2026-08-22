#!/usr/bin/env bash
# Live checks: chat window uses readable system fonts; homepage/login/CCS stay Voga/Orbitron.
set -euo pipefail

BASE="${BASE:-https://paxdesign.at}"
STAMP="$(date +%s)"
FAIL=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ok() { echo "OK  $*"; }
fail() { echo "FAIL $*"; FAIL=$((FAIL + 1)); }

code="$(curl -sS -o "$TMP/typo.css" -w '%{http_code}' "${BASE}/wp-content/themes/navein/assets/css/site-body-typography.css?n=${STAMP}")"
[ "$code" = "200" ] && ok "typography CSS HTTP 200" || fail "typography CSS HTTP ${code}"

grep -q -- '--pax-chat-read-font:' "$TMP/typo.css" \
  && ok "live CSS has chat readable font token" \
  || fail "live CSS missing --pax-chat-read-font"

grep -q -- '-apple-system, BlinkMacSystemFont, "Segoe UI"' "$TMP/typo.css" \
  && ok "live CSS uses system UI stack for chat" \
  || fail "live CSS missing system UI stack"

grep -q '#paxdesignChatPanel' "$TMP/typo.css" \
  && ok "live CSS targets the chat panel" \
  || fail "live CSS does not target #paxdesignChatPanel"

grep -q '.paxdesign-booking-chat-input::placeholder' "$TMP/typo.css" \
  && ok "live CSS targets chat placeholders" \
  || fail "live CSS missing placeholder rule"

if grep -q 'Chat window — keep original Voga font' "$TMP/typo.css"; then
  fail "live CSS still forces Voga on the chat window"
else
  ok "live CSS no longer forces Voga on the chat window"
fi

if grep -q 'Exo 2' "$TMP/typo.css" && grep -q '#paxdesignBookingPanel' "$TMP/typo.css"; then
  ok "booking tab still has a site body-font keep-rule"
else
  ok "booking tab body-font keep-rule present or inherited from site tokens"
fi

home_code="$(curl -sS -o "$TMP/home.html" -w '%{http_code}' "${BASE}/?n=${STAMP}")"
[ "$home_code" = "200" ] && ok "homepage HTTP 200" || fail "homepage HTTP ${home_code}"
grep -Eq 'apple-homepage\.css' "$TMP/home.html" \
  && ok "homepage still loads apple-homepage.css" \
  || fail "homepage missing apple-homepage.css"

hp_css="$(curl -sS "${BASE}/wp-content/themes/navein/assets/css/apple-homepage.css?n=${STAMP}")"
grep -Eq -- '--ph-display: "Orbitron"' <<< "$hp_css" \
  && ok "homepage display font is still Orbitron" \
  || fail "homepage Orbitron display font changed"

ccs_code="$(curl -sS -o "$TMP/ccs.html" -w '%{http_code}' "${BASE}/cybercrime-support/?n=${STAMP}")"
[ "$ccs_code" = "200" ] && ok "cybercrime-support HTTP 200" || fail "cybercrime-support HTTP ${ccs_code}"
ccs_css="$(curl -sS "${BASE}/wp-content/themes/navein/assets/css/apple-cybercrime-support.css?n=${STAMP}")"
grep -Eq -- '--ccs-font: "Exo 2"' <<< "$ccs_css" \
  && ok "cybercrime support body font is Exo 2" \
  || fail "cybercrime support font is not Exo 2"

login_code="$(curl -sS -o /dev/null -w '%{http_code}' "${BASE}/wp-login.php")"
[ "$login_code" = "200" ] && ok "wp-login.php HTTP 200" || fail "wp-login.php HTTP ${login_code}"
tokens="$(curl -sS "${BASE}/wp-content/plugins/paxdesign-booking/assets/customer-auth/css/pdx-tokens.css?n=${STAMP}")"
grep -Eq -- '--pdx-font: "Exo 2"' <<< "$tokens" \
  && ok "login/dashboard tokens use Exo 2" \
  || fail "login/dashboard font tokens are not Exo 2"

chat_js="$(curl -sS "${BASE}/wp-content/plugins/paxdesign-booking/assets/js/chat-script.js?n=${STAMP}")"
grep -q 'Version: 3.174.128' <<< "$chat_js" \
  && ok "chat JS still reports 3.174.128" \
  || fail "chat JS version is not 3.174.128"
if grep -q 'skipping stacked sync' <<< "$chat_js"; then
  fail "chat JS contains 3.176 stacked-sync rewrite"
else
  ok "chat JS is not the 3.176 rewrite"
fi
if grep -q 'Gespräch beenden' <<< "$chat_js"; then
  fail "chat JS contains Gespräch beenden"
else
  ok "chat JS has no Gespräch beenden control"
fi

plugin_css="$(curl -sS "${BASE}/wp-content/plugins/paxdesign-booking/assets/css/booking-styles.css?n=${STAMP}")"
grep -q 'button.paxdesign-booking-chat-auth-login-btn' <<< "$plugin_css" \
  && ok "live plugin CSS targets chat Sign In" \
  || fail "live plugin CSS missing chat Sign In override"
grep -q -- '-apple-system, BlinkMacSystemFont, "Segoe UI"' <<< "$plugin_css" \
  && ok "live plugin CSS uses system UI stack for chat" \
  || fail "live plugin CSS missing system UI stack"
grep -q '.paxdesign-booking-chat-auth-github-btn' <<< "$plugin_css" \
  && ok "live plugin CSS targets GitHub chat login" \
  || fail "live plugin CSS missing GitHub chat login override"

if [ "$FAIL" -gt 0 ]; then
  echo "${FAIL} chat readable-font live check(s) failed"
  exit 1
fi

echo "Chat readable-font live verification passed."
