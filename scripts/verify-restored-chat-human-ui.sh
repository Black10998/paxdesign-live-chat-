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
curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/css/booking-styles.css?v=${STAMP}" \
  -o "${tmpdir}/booking-styles.css"
curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/js/cybercrime-admin.js?v=${STAMP}" \
  -o "${tmpdir}/cybercrime-admin.js"
curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/paxdesign-booking.php?v=${STAMP}" \
  -o "${tmpdir}/paxdesign-booking.php" || true

grep -q "Version: 3.174.128" "${tmpdir}/chat-script.js" \
  && ok "chat-script.js cache-bust version 3.174.128" \
  || fail "chat-script.js is not the patched 3.174.128 file"

grep -q "function pinToLatestMessage" "${tmpdir}/chat-script.js" \
  && ok "instant pin-to-latest is present" \
  || fail "pinToLatestMessage missing"

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

grep -q "forceOpen: true" "${tmpdir}/chat-script.js" \
  && ok "open fetches server history on widget open" \
  || fail "forceOpen history path missing"

grep -q "startVoiceWaveformFromHeldStream" "${tmpdir}/chat-script.js" \
  && ok "mic waveform uses held stream (no second getUserMedia during speech)" \
  || fail "held-stream waveform missing"

curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/js/booking-script.js?v=${STAMP}" \
  -o "${tmpdir}/booking-script.js" || true
grep -q "showChatShellLoading" "${tmpdir}/booking-script.js" 2>/dev/null \
  && ok "booking-script shell loader present" \
  || fail "booking-script shell loader missing"

grep -q "syncGesture: true" "${tmpdir}/chat-script.js" \
  && ok "speech recognition starts synchronously in user gesture" \
  || fail "syncGesture mic flow missing"

if grep -q "shouldUseDesktopSpeechFlow" "${tmpdir}/chat-script.js"; then
  fail "live chat still uses obsolete Windows mic release workaround"
else
  ok "obsolete Windows mic release workaround removed"
fi

grep -q "requestMicrophoneFromUserGesture" "${tmpdir}/chat-script.js" \
  && ok "mic is requested from user gesture via getUserMedia" \
  || fail "requestMicrophoneFromUserGesture missing"

grep -q "getUserMedia({ audio: true })" "${tmpdir}/chat-script.js" \
  && ok "native audio getUserMedia constraint present" \
  || fail "getUserMedia audio constraint missing"

grep -q "hideShellLoader" "${tmpdir}/chat-script.js" \
  && ok "chat hides shell loader when ready" \
  || fail "hideShellLoader integration missing"

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

grep -q "paxdesign-booking-chat-auth-login-btn" "${tmpdir}/booking-styles.css" \
  && ok "chat login gate uses header-style Sign In button" \
  || fail "chat login Sign In button styles missing"

grep -q "paxdesign-booking-chat-auth-gate-card" "${tmpdir}/booking-styles.css" \
  && ok "chat login gate uses centered card layout" \
  || fail "chat login gate card layout missing"

grep -q "renderChatAuthSocialButtons" "${tmpdir}/chat-script.js" \
  && ok "chat auth gate renders social login buttons" \
  || fail "chat social login rendering missing"

curl -fsSL "${SITE}/wp-content/plugins/paxdesign-booking/assets/customer-auth/js/pax-auth.js?v=${STAMP}" \
  -o "${tmpdir}/pax-auth.js" || true
grep -q "githubWebStartUrl" "${tmpdir}/pax-auth.js" \
  && ok "GitHub login support present in live pax-auth.js" \
  || fail "GitHub login support missing from live pax-auth.js"

grep -q "maybeOpenChatFromReturnUrl" "${tmpdir}/booking-script.js" 2>/dev/null \
  && ok "booking-script reopens chat after login return" \
  || fail "booking-script chat return reopen missing"

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

