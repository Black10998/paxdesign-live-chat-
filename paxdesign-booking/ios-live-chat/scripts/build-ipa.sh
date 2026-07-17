#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SCHEME="PAXDesignLiveChat"
CONFIGURATION="${CONFIGURATION:-Release}"
DERIVED_DATA="${DERIVED_DATA:-$ROOT/build/DerivedData}"
OUTPUT_DIR="${OUTPUT_DIR:-$ROOT/build/output}"
IPA_NAME="${IPA_NAME:-PAXDesignLiveChat.ipa}"
PROJECT_SPEC="${PROJECT_SPEC:-$ROOT/project.sideload.yml}"
SIDELOAD_ENTITLEMENTS="$ROOT/PAXDesignLiveChat/PAXDesignLiveChat.sideload.entitlements"
VALIDATE_SCRIPT="$ROOT/scripts/validate-ipa.sh"

echo "==> Generating sideload Xcode project from $PROJECT_SPEC"
if ! command -v xcodegen >/dev/null 2>&1; then
  echo "xcodegen is required (brew install xcodegen)" >&2
  exit 1
fi
xcodegen generate --spec "$PROJECT_SPEC"

echo "==> Building unsigned sideload iOS app ($CONFIGURATION)"
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
  CODE_SIGN_ENTITLEMENTS="$SIDELOAD_ENTITLEMENTS" \
  build

APP_PATH="$(find "$DERIVED_DATA/Build/Products/$CONFIGURATION-iphoneos" -maxdepth 1 -name '*.app' -type d | head -1)"
if [[ -z "$APP_PATH" || ! -d "$APP_PATH" ]]; then
  echo "Built .app not found under $DERIVED_DATA" >&2
  exit 1
fi

echo "==> Sanitizing sideload app bundle"
# Unsigned embedded extensions crash sideloaded installs immediately.
if [[ -d "$APP_PATH/PlugIns" ]]; then
  echo "Removing unsigned PlugIns"
  rm -rf "$APP_PATH/PlugIns"
fi

# App Intents metadata can crash unsigned/resigned sideload builds during linkd indexing.
if [[ -d "$APP_PATH/Metadata.appintents" ]]; then
  echo "Removing Metadata.appintents (not supported for sideload IPA)"
  rm -rf "$APP_PATH/Metadata.appintents"
fi

find "$APP_PATH" -type d -name 'nlu.appintents' -print0 | while IFS= read -r -d '' dir; do
  echo "Removing $dir"
  rm -rf "$dir"
done

# Push background mode without a valid push entitlement can terminate sideloaded apps.
/usr/libexec/PlistBuddy -c "Delete :UIBackgroundModes" "$APP_PATH/Info.plist" 2>/dev/null || true

# Ensure privacy usage descriptions are present in the shipped bundle.
/usr/libexec/PlistBuddy -c "Add :CFBundleDisplayName string 'PAXDesign'" "$APP_PATH/Info.plist" 2>/dev/null \
  || /usr/libexec/PlistBuddy -c "Set :CFBundleDisplayName 'PAXDesign'" "$APP_PATH/Info.plist"
/usr/libexec/PlistBuddy -c "Add :NSFaceIDUsageDescription string 'Face ID is used to unlock the app after inactivity.'" "$APP_PATH/Info.plist" 2>/dev/null \
  || /usr/libexec/PlistBuddy -c "Set :NSFaceIDUsageDescription 'Face ID is used to unlock the app after inactivity.'" "$APP_PATH/Info.plist"
/usr/libexec/PlistBuddy -c "Add :NSCameraUsageDescription string 'Take photos to send in live chat conversations.'" "$APP_PATH/Info.plist" 2>/dev/null \
  || /usr/libexec/PlistBuddy -c "Set :NSCameraUsageDescription 'Take photos to send in live chat conversations.'" "$APP_PATH/Info.plist"
/usr/libexec/PlistBuddy -c "Add :NSPhotoLibraryUsageDescription string 'Select photos from your library to send in live chat.'" "$APP_PATH/Info.plist" 2>/dev/null \
  || /usr/libexec/PlistBuddy -c "Set :NSPhotoLibraryUsageDescription 'Select photos from your library to send in live chat.'" "$APP_PATH/Info.plist"
/usr/libexec/PlistBuddy -c "Add :NSUserNotificationsUsageDescription string 'Notifications for live agent requests and new customer messages.'" "$APP_PATH/Info.plist" 2>/dev/null \
  || /usr/libexec/PlistBuddy -c "Set :NSUserNotificationsUsageDescription 'Notifications for live agent requests and new customer messages.'" "$APP_PATH/Info.plist"
/usr/libexec/PlistBuddy -c "Add :NSLocationWhenInUseUsageDescription string 'Location helps secure account sign-ins and device audits.'" "$APP_PATH/Info.plist" 2>/dev/null \
  || /usr/libexec/PlistBuddy -c "Set :NSLocationWhenInUseUsageDescription 'Location helps secure account sign-ins and device audits.'" "$APP_PATH/Info.plist"
/usr/libexec/PlistBuddy -c "Add :NSLocationAlwaysAndWhenInUseUsageDescription string 'Location helps secure account sign-ins and device audits.'" "$APP_PATH/Info.plist" 2>/dev/null \
  || /usr/libexec/PlistBuddy -c "Set :NSLocationAlwaysAndWhenInUseUsageDescription 'Location helps secure account sign-ins and device audits.'" "$APP_PATH/Info.plist"

echo "==> Packaging IPA: $IPA_NAME"
STAGE="$OUTPUT_DIR/ipa-stage"
rm -rf "$STAGE"
mkdir -p "$STAGE/Payload"
ditto "$APP_PATH" "$STAGE/Payload/$(basename "$APP_PATH")"
(
  cd "$STAGE"
  zip -qr "$OUTPUT_DIR/$IPA_NAME" Payload
)

if [[ -x "$VALIDATE_SCRIPT" ]]; then
  echo "==> Validating sideload IPA"
  "$VALIDATE_SCRIPT" "$OUTPUT_DIR/$IPA_NAME"
fi

echo "==> Done"
ls -lh "$OUTPUT_DIR/$IPA_NAME"
echo "IPA_PATH=$OUTPUT_DIR/$IPA_NAME"
