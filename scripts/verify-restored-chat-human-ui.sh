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

grep -q "Version: 3.174.106" "${tmpdir}/chat-script.js" \
  && ok "chat-script.js cache-bust version 3.174.106" \
  || fail "chat-script.js is not the patched 3.174.106 file"

grep -q "var appliedMessageSeq" "${tmpdir}/chat-script.js" \
  && grep -q "function getIncrementalSince" "${tmpdir}/chat-script.js" \
  && grep -q "resync_required" "${tmpdir}/chat-script.js" \
  && grep -q "function shouldPreserveHistoryDom" "${tmpdir}/chat-script.js" \
  && ok "website unified sync merge cursors present" \
  || fail "unified sync client markers missing from chat-script.js"

grep -q "function scheduleUnifiedSync" "${tmpdir}/chat-script.js" \
  && ok "website unified sync coordinator present" \
  || fail "scheduleUnifiedSync missing"

grep -q "function getSiteHeaderBottom" "${tmpdir}/booking-script.js" \
  && ok "mobile keyboard header clamp present" \
  || fail "getSiteHeaderBottom missing from booking-script.js"

grep -q "function transitionAfterLogin" "${tmpdir}/chat-script.js" \
  && ok "instant login transition present" \
  || fail "transitionAfterLogin missing"

grep -q "function pinToLatestMessage" "${tmpdir}/chat-script.js" \
  && ok "instant pin-to-latest is present" \
  || fail "pinToLatestMessage missing"

if grep -q "scroll-behavior: smooth" "${tmpdir}/booking-styles.css"; then
  fail "live CSS still uses smooth history scrolling"
else
  ok "live CSS pins instantly without smooth scroll"
fi

grep -q "flex: 0 0 auto" "${tmpdir}/booking-styles.css" \
  && ok "single message scroller layout present" \
  || fail "chat thread flex fix missing"

grep -q "uploadHumanAttachFile" "${tmpdir}/chat-script.js" \
  && ok "plus-button upload handler present" \
  || fail "uploadHumanAttachFile missing"

grep -q "var stickToBottom" "${tmpdir}/chat-script.js" \
  && ok "WhatsApp stick-to-bottom present" \
  || fail "stickToBottom missing"

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

if grep -q "skipping stacked sync" "${tmpdir}/chat-script.js"; then
  fail "live chat-script.js looks like a newer GitHub chat rewrite"
else
  ok "live chat-script.js is not the later GitHub chat rewrite"
fi

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
