#!/usr/bin/env bash
set -euo pipefail

BASE="${BASE:-https://paxdesign.at}"
STAMP="$(date +%s)"

curl_asset() {
  local url="$1"
  local out="$2"
  local code
  code="$(curl -sS -o "$out" -w '%{http_code}' "${url}?n=${STAMP}")"
  echo "$code"
}

HOME_CODE="$(curl -sS -o /tmp/footer-home.html -w '%{http_code}' "${BASE}/?n=${STAMP}")"
INNER_CODE="$(curl -sS -o /tmp/footer-inner.html -w '%{http_code}' "${BASE}/webentwicklung/?n=${STAMP}")"
FOOTER_CSS_CODE="$(curl_asset "${BASE}/wp-content/themes/navein/assets/css/apple-footer.css" /tmp/apple-footer.css)"
HP_CSS_CODE="$(curl_asset "${BASE}/wp-content/themes/navein/assets/css/apple-homepage.css" /tmp/apple-homepage.css)"

echo "HOME ${HOME_CODE} | INNER ${INNER_CODE} | FOOTER_CSS ${FOOTER_CSS_CODE} | HP_CSS ${HP_CSS_CODE}"

test "$HOME_CODE" = "200"
test "$INNER_CODE" = "200"
test "$FOOTER_CSS_CODE" = "200"
test "$HP_CSS_CODE" = "200"

grep -Eq 'never inherit global/page img sizing' /tmp/apple-footer.css
grep -Eq 'height: 22px !important' /tmp/apple-footer.css
grep -Eq 'max-width: 88px !important' /tmp/apple-footer.css
grep -Eq 'Footer subscribe — compact Apple mobile form' /tmp/apple-homepage.css
grep -Eq 'body\.home \.paxmc-footer-subscribe \.input-wrapper' /tmp/apple-homepage.css

grep -Eq 'apple-footer\.css' /tmp/footer-home.html
grep -Eq 'apple-footer\.css' /tmp/footer-inner.html
grep -Eq 'apple-homepage\.css' /tmp/footer-home.html
! grep -Eq 'apple-homepage\.css' /tmp/footer-inner.html

echo "Footer responsive production verification passed."
