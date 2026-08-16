#!/usr/bin/env bash
# Verify the surgically deployed restored-baseline chat / CCS patch on paxdesign.at.
set -euo pipefail

SITE="${PAX_SITE:-https://paxdesign.at}"
STAMP="$(date +%s)"
FAIL=0

fail() {
  echo "FAIL: $*"
  FAIL=1
}

ok() {
  echo "OK: $*"
}

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/js/chat-script.js?v=${STAMP}" \
  -o "${tmpdir}/chat-script.js"
curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/js/booking-script.js?v=${STAMP}" \
  -o "${tmpdir}/booking-script.js"
curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/css/booking-styles.css?v=${STAMP}" \
  -o "${tmpdir}/booking-styles.css"
curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/js/cybercrime-admin.js?v=${STAMP}" \
  -o "${tmpdir}/cybercrime-admin.js"
curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/paxdesign-booking.php?v=${STAMP}" \
  -o "${tmpdir}/paxdesign-booking.php" || true

grep -q "Version: 3.174.101" "${tmpdir}/chat-script.js" \
  && ok "chat-script.js cache-bust version 3.174.101" \
  || fail "chat-script.js is not the patched 3.174.101 file"

grep -q "function pinToLatestMessage" "${tmpdir}/chat-script.js" \
  && ok "instant pin-to-latest is present" \
  || fail "pinToLatestMessage missing"

grep -q -- "min(420px, 58svh" "${tmpdir}/booking-styles.css" \
  && ok "mobile chat uses a compact svh card" \
  || fail "mobile chat is not using compact svh card height"

grep -q "width: 360px" "${tmpdir}/booking-styles.css" \
  && grep -q "height: 420px" "${tmpdir}/booking-styles.css" \
  && ok "desktop chat is a compact 360x420 card" \
  || fail "desktop chat is not the compact 360x420 card"

grep -qF ".paxdesign-booking-chat-auth-gate[hidden]" "${tmpdir}/booking-styles.css" \
  && grep -A2 -F ".paxdesign-booking-chat-auth-gate[hidden]" "${tmpdir}/booking-styles.css" | grep -q "display: none !important" \
  && ok "hidden login overlay is display:none so it cannot cover messages" \
  || fail "hidden login overlay can still cover messages"

grep -q 'data-widget-mode="booking"' "${tmpdir}/chat-script.js" \
  && ok "plus menu contains Termin buchen" \
  || fail "plus menu is missing Termin buchen"

grep -q "function applyCompactWidgetFrame" "${tmpdir}/booking-script.js" \
  && ok "compact widget frame is applied on desktop and mobile" \
  || fail "applyCompactWidgetFrame missing from live booking-script.js"

grep -q "COMPACT_HEIGHT = 420" "${tmpdir}/booking-script.js" \
  && ok "compact card height is 420px" \
  || fail "COMPACT_HEIGHT 420 missing from live booking-script.js"

grep -q "kb > 50" "${tmpdir}/booking-script.js" \
  && ok "keyboard-open waits for real visualViewport occlusion" \
  || fail "kb > 50 missing from live booking-script.js"

grep -q "function visualViewportBox" "${tmpdir}/booking-script.js" \
  && ok "mobile layout reads the visual viewport box" \
  || fail "visualViewportBox missing from live booking-script.js"

grep -q "text-align: center !important" "${tmpdir}/booking-styles.css" \
  && ok "login title/button is centered" \
  || fail "login control is not centered"

grep -q "paxdesign-chat-mode-active.paxdesign-mobile-chat-mode" "${tmpdir}/booking-styles.css" \
  && ok "mobile sheet overrides the 520px desktop chat height" \
  || fail "mobile sheet still loses to the 520px desktop chat height"

grep -q "bottom: Math.round" "${tmpdir}/booking-script.js" \
  && ok "widget is bottom-pinned instead of jumping via offsetTop" \
  || fail "bottom-pinned compact frame missing from live booking-script.js"

grep -q "paxdesign-booking-chat-auth-gate" "${tmpdir}/booking-styles.css" \
  && grep -q "background: #fff" "${tmpdir}/booking-styles.css" \
  && ok "chat login panel has Apple light styling" \
  || fail "chat login panel is not Apple-styled"

if grep -q "scroll-behavior: smooth" "${tmpdir}/booking-styles.css"; then
  fail "live CSS still uses smooth history scrolling"
else
  ok "live CSS pins instantly without smooth scroll"
fi

grep -q "uploadHumanAttachFile" "${tmpdir}/chat-script.js" \
  && ok "plus-button upload handler present" \
  || fail "uploadHumanAttachFile missing"

grep -q "var stickToBottom" "${tmpdir}/chat-script.js" \
  && ok "WhatsApp stick-to-bottom present" \
  || fail "stickToBottom missing"

grep -q "background: true, blockUi: false" "${tmpdir}/chat-script.js" \
  && ok "open does not block on history/sync" \
  || fail "background open path missing"

grep -q "paxdesign-chat-admin-active" "${tmpdir}/chat-script.js" \
  && ok "human takeover class toggle present" \
  || fail "admin-active class toggle missing"

grep -q "function canCustomerEndChat" "${tmpdir}/chat-script.js" \
  && grep -A2 "function canCustomerEndChat" "${tmpdir}/chat-script.js" | grep -q "return false" \
  && ok "customer end-chat is disabled" \
  || fail "canCustomerEndChat is not disabled"

grep -q "#063226" "${tmpdir}/booking-styles.css" \
  && ok "dark-green composer color present" \
  || fail "dark-green composer color missing"

grep -q "paxdesign-booking-chat-attach-menu" "${tmpdir}/booking-styles.css" \
  && ok "attach menu styles present" \
  || fail "attach menu styles missing"

grep -q "paxdesign-booking-chat-end-wrap" "${tmpdir}/booking-styles.css" \
  && grep -q "display: none !important" "${tmpdir}/booking-styles.css" \
  && ok "end-chat UI is hidden in CSS" \
  || fail "end-chat CSS hide missing"

grep -q "saveStatus('rejected')" "${tmpdir}/cybercrime-admin.js" \
  && ok "admin JS includes rejected/مرفوض action" \
  || fail "rejected action missing from live cybercrime-admin.js"

# Must still be the restored baseline, not the later GitHub chat freeze/unfreeze work.
if grep -q "skipping stacked sync" "${tmpdir}/chat-script.js"; then
  fail "live chat-script.js looks like a newer GitHub chat rewrite"
else
  ok "live chat-script.js is not the later GitHub chat rewrite"
fi

curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/customer-auth/js/pax-auth.js?v=${STAMP}" \
  -o "${tmpdir}/pax-auth.js"

grep -q "Sign in with GitHub" "${tmpdir}/pax-auth.js" \
  && ok "website login includes Sign in with GitHub" \
  || fail "Sign in with GitHub missing from live pax-auth.js"

grep -q "githubWebStartUrl" "${tmpdir}/pax-auth.js" \
  && ok "GitHub OAuth start helper is present" \
  || fail "githubWebStartUrl missing from live pax-auth.js"

curl -fsSL "${SITE}/?pax_chat_ui=${STAMP}" -o "${tmpdir}/home.html" || true
if grep -q "KI-generierte Antworten" "${tmpdir}/home.html" 2>/dev/null; then
  fail "live homepage still contains KI disclaimer"
else
  ok "live homepage has no KI disclaimer"
fi

if [ "$FAIL" -ne 0 ]; then
  echo "Verification failed."
  exit 1
fi

echo "Restored chat / CCS patch verified on ${SITE}"
