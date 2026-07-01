#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_DIR="$ROOT/paxdesign-booking"
VERSION="$(grep "define('PAXDESIGN_BOOKING_VERSION'" "$PLUGIN_DIR/paxdesign-booking.php" | sed "s/.*'\([^']*\)'.*/\1/")"
DIST="$ROOT/dist"
ZIP_NAME="paxdesign-booking-v${VERSION}.zip"

echo "==> Building WordPress plugin release v${VERSION}"
mkdir -p "$DIST"
rm -f "$DIST/$ZIP_NAME"

(
  cd "$ROOT"
  zip -qr "$DIST/$ZIP_NAME" paxdesign-booking \
    -x "paxdesign-booking/ios-live-chat/build/*" \
    -x "paxdesign-booking/ios-live-chat/.build/*" \
    -x "paxdesign-booking/ios-live-chat/DerivedData/*" \
    -x "*.DS_Store"
)

SHA256="$(sha256sum "$DIST/$ZIP_NAME" | awk '{print $1}')"
echo "SHA256: $SHA256"
echo "ZIP_PATH=$DIST/$ZIP_NAME"

if [[ "${BUILD_IOS:-0}" == "1" ]] && command -v xcodebuild >/dev/null 2>&1; then
  echo "==> Building iOS IPA"
  (cd "$PLUGIN_DIR/ios-live-chat" && ./scripts/build-ipa.sh)
  IPA_SRC="$PLUGIN_DIR/ios-live-chat/build/output/PAXDesignLiveChat.ipa"
  if [[ -f "$IPA_SRC" ]]; then
    cp "$IPA_SRC" "$DIST/PAXDesignLiveChat-v${VERSION}.ipa"
    echo "IPA_PATH=$DIST/PAXDesignLiveChat-v${VERSION}.ipa"
  fi
else
  echo "==> Skipping iOS IPA (set BUILD_IOS=1 on macOS with Xcode to build)"
fi

echo "==> Done"
