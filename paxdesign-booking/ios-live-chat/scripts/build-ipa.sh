#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SCHEME="PAXDesignLiveChat"
CONFIGURATION="${CONFIGURATION:-Release}"
DERIVED_DATA="${DERIVED_DATA:-$ROOT/build/DerivedData}"
OUTPUT_DIR="${OUTPUT_DIR:-$ROOT/build/output}"
IPA_NAME="${IPA_NAME:-PAXDesignLiveChat.ipa}"

echo "==> Generating Xcode project"
if ! command -v xcodegen >/dev/null 2>&1; then
  echo "xcodegen is required (brew install xcodegen)" >&2
  exit 1
fi
xcodegen generate

echo "==> Building unsigned iOS app ($CONFIGURATION)"
rm -rf "$DERIVED_DATA" "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"

xcodebuild \
  -project "$ROOT/PAXDesignLiveChat.xcodeproj" \
  -scheme "$SCHEME" \
  -configuration "$CONFIGURATION" \
  -destination "generic/platform=iOS" \
  -derivedDataPath "$DERIVED_DATA" \
  CODE_SIGNING_ALLOWED=NO \
  CODE_SIGNING_REQUIRED=NO \
  CODE_SIGN_IDENTITY="" \
  DEVELOPMENT_TEAM="" \
  PROVISIONING_PROFILE_SPECIFIER="" \
  build

APP_PATH="$(find "$DERIVED_DATA/Build/Products/$CONFIGURATION-iphoneos" -maxdepth 1 -name '*.app' -type d | head -1)"
if [[ -z "$APP_PATH" || ! -d "$APP_PATH" ]]; then
  echo "Built .app not found under $DERIVED_DATA" >&2
  exit 1
fi

echo "==> Packaging IPA: $IPA_NAME"
STAGE="$OUTPUT_DIR/ipa-stage"
rm -rf "$STAGE"
mkdir -p "$STAGE/Payload"
ditto "$APP_PATH" "$STAGE/Payload/$(basename "$APP_PATH")"
(
  cd "$STAGE"
  zip -qr "$OUTPUT_DIR/$IPA_NAME" Payload
)

echo "==> Done"
ls -lh "$OUTPUT_DIR/$IPA_NAME"
echo "IPA_PATH=$OUTPUT_DIR/$IPA_NAME"
