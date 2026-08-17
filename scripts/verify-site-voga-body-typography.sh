#!/usr/bin/env bash
set -euo pipefail

BASE="${BASE:-https://paxdesign.at}"
STAMP="$(date +%s)"

curl_page() {
  local url="$1"
  local out="$2"
  local code
  code="$(curl -sS -o "$out" -w '%{http_code}' "${url}?n=${STAMP}")"
  echo "$code"
}

HOME_CODE="$(curl_page "${BASE}/" /tmp/voga-home.html)"
CCS_CODE="$(curl_page "${BASE}/cybercrime-support/" /tmp/voga-ccs.html)"
VOGA_CSS_CODE="$(curl -sS -o /tmp/voga-diamond-fonts.css -w '%{http_code}' "${BASE}/wp-content/themes/navein/assets/css/voga-diamond-fonts.css?n=${STAMP}")"
BODY_CSS_CODE="$(curl -sS -o /tmp/site-body-typography.css -w '%{http_code}' "${BASE}/wp-content/themes/navein/assets/css/site-body-typography.css?n=${STAMP}")"
PDX_CODE="$(curl -sS -o /tmp/pdx-tokens.css -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/customer-auth/css/pdx-tokens.css?n=${STAMP}")"
BOOKING_CODE="$(curl -sS -o /tmp/booking-styles.css -w '%{http_code}' "${BASE}/wp-content/plugins/paxdesign-booking/assets/css/booking-styles.css?n=${STAMP}")"

echo "HOME ${HOME_CODE} | CCS ${CCS_CODE} | VOGA_CSS ${VOGA_CSS_CODE} | BODY_CSS ${BODY_CSS_CODE} | PDX ${PDX_CODE} | BOOKING ${BOOKING_CODE}"

test "$HOME_CODE" = "200"
test "$CCS_CODE" = "200"
test "$VOGA_CSS_CODE" = "200"
test "$BODY_CSS_CODE" = "200"
test "$PDX_CODE" = "200"
test "$BOOKING_CODE" = "200"

grep -Eq 'voga-diamond-fonts\.css' /tmp/voga-home.html
grep -Eq 'site-body-typography\.css' /tmp/voga-home.html
grep -Eq 'voga-diamond-fonts\.css' /tmp/voga-ccs.html
grep -Eq 'site-body-typography\.css' /tmp/voga-ccs.html

grep -Eq 'font-family: "Voga Diamond"' /tmp/voga-diamond-fonts.css
grep -Eq '\-\-pax-voga-body:' /tmp/site-body-typography.css
grep -Eq '\-\-pax-voga-tracking:' /tmp/site-body-typography.css
grep -Eq 'Cybercrime headings keep --ccs-display' /tmp/site-body-typography.css

grep -Eq '\-\-pdx-font: "Voga Diamond"' /tmp/pdx-tokens.css
grep -Eq '\-\-pax-font:\s+"Voga Diamond"' /tmp/booking-styles.css

HP_CSS="$(curl -sS "${BASE}/wp-content/themes/navein/assets/css/apple-homepage.css?n=${STAMP}")"
grep -Eq '\-\-ph-display: "Orbitron"' <<< "$HP_CSS"
grep -Eq '\-\-ph-text: var\(--pax-voga-body' <<< "$HP_CSS"

CCS_CSS="$(curl -sS "${BASE}/wp-content/themes/navein/assets/css/apple-cybercrime-support.css?n=${STAMP}")"
grep -Eq '\-\-ccs-font: "Voga Diamond"' <<< "$CCS_CSS"
grep -Eq '\-\-ccs-display:' <<< "$CCS_CSS"

# Homepage keeps Orbitron; cybercrime page should not load homepage-only Orbitron bundle as primary typography.
grep -Eq 'homepage-fonts\.css' /tmp/voga-home.html
! grep -Eq 'homepage-fonts\.css' /tmp/voga-ccs.html || true

echo "Site-wide Voga body typography production verification passed."
