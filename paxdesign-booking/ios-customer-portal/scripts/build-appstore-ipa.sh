#!/usr/bin/env bash
# Build signed App Store IPA for PAXDesign Customer Portal iOS app.
set -euo pipefail
set +x

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SCHEME="${SCHEME:-PAXCustomerPortal}"
CONFIGURATION="${CONFIGURATION:-Release}"
PROJECT_SPEC="${PROJECT_SPEC:-$ROOT/project.yml}"
DERIVED_DATA="${DERIVED_DATA:-$ROOT/build/AppStore/DerivedData}"
ARCHIVE_PATH="${ARCHIVE_PATH:-$ROOT/build/AppStore/PAXCustomerPortal.xcarchive}"
EXPORT_DIR="${EXPORT_DIR:-$ROOT/build/AppStore/export}"
IPA_NAME="${IPA_NAME:-PAXCustomerPortal-AppStore.ipa}"
EXPORT_OPTIONS_PATH="${EXPORT_OPTIONS_PATH:-$ROOT/build/AppStore/ExportOptions.plist}"

MAIN_BUNDLE_ID="${MAIN_BUNDLE_ID:-at.paxdesign.customerportal}"
APPLE_TEAM_ID="${APPLE_TEAM_ID:?APPLE_TEAM_ID is required}"
KEYCHAIN_PASSWORD="${KEYCHAIN_PASSWORD:?KEYCHAIN_PASSWORD is required}"
APPLE_CERTIFICATE_P12_BASE64="${APPLE_CERTIFICATE_P12_BASE64:?APPLE_CERTIFICATE_P12_BASE64 is required}"
APPLE_CERTIFICATE_PASSWORD="${APPLE_CERTIFICATE_PASSWORD:?APPLE_CERTIFICATE_PASSWORD is required}"
APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64="${APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64:-${APPLE_PROVISIONING_PROFILE_MAIN_BASE64:-}}"

if [[ -z "$APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64" ]]; then
  echo "ERROR: APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64 or APPLE_PROVISIONING_PROFILE_MAIN_BASE64 is required" >&2
  exit 1
fi

SIGNING_DIR="${SIGNING_DIR:-$ROOT/build/AppStore/signing}"
KEYCHAIN_PATH="${KEYCHAIN_PATH:-$SIGNING_DIR/app-signing.keychain-db}"
PROFILE_PATH="$SIGNING_DIR/customer.mobileprovision"
CERTIFICATE_PATH="$SIGNING_DIR/distribution.p12"

mkdir -p "$SIGNING_DIR" "$EXPORT_DIR"
printf '%s' "$APPLE_CERTIFICATE_P12_BASE64" | tr -d '[:space:]' | base64 -D > "$CERTIFICATE_PATH"
printf '%s' "$APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64" | tr -d '[:space:]' | base64 -D > "$PROFILE_PATH"

security create-keychain -p "$KEYCHAIN_PASSWORD" "$KEYCHAIN_PATH"
security set-keychain-settings -lut 21600 "$KEYCHAIN_PATH"
security unlock-keychain -p "$KEYCHAIN_PASSWORD" "$KEYCHAIN_PATH"
security import "$CERTIFICATE_PATH" -k "$KEYCHAIN_PATH" -P "$APPLE_CERTIFICATE_PASSWORD" -T /usr/bin/codesign -T /usr/bin/security
security set-key-partition-list -S apple-tool:,apple:,codesign: -s -k "$KEYCHAIN_PASSWORD" "$KEYCHAIN_PATH"
security list-keychains -d user -s "$KEYCHAIN_PATH" $(security list-keychains -d user | tr -d '"')

mkdir -p ~/Library/MobileDevice/Provisioning\ Profiles
cp "$PROFILE_PATH" ~/Library/MobileDevice/Provisioning\ Profiles/

command -v xcodegen >/dev/null || brew install xcodegen
xcodegen generate --spec "$PROJECT_SPEC"

xcodebuild -scheme "$SCHEME" -configuration "$CONFIGURATION" -derivedDataPath "$DERIVED_DATA" \
  -archivePath "$ARCHIVE_PATH" archive \
  CODE_SIGN_STYLE=Manual \
  DEVELOPMENT_TEAM="$APPLE_TEAM_ID" \
  PROVISIONING_PROFILE_SPECIFIER="" \
  CODE_SIGN_IDENTITY="Apple Distribution"

/usr/libexec/PlistBuddy -c "Add :method string app-store-connect" "$EXPORT_OPTIONS_PATH" 2>/dev/null || \
  /usr/libexec/PlistBuddy -c "Set :method app-store-connect" "$EXPORT_OPTIONS_PATH"
/usr/libexec/PlistBuddy -c "Add :teamID string $APPLE_TEAM_ID" "$EXPORT_OPTIONS_PATH" 2>/dev/null || \
  /usr/libexec/PlistBuddy -c "Set :teamID $APPLE_TEAM_ID" "$EXPORT_OPTIONS_PATH"
/usr/libexec/PlistBuddy -c "Add :signingStyle string manual" "$EXPORT_OPTIONS_PATH" 2>/dev/null || true
/usr/libexec/PlistBuddy -c "Add :provisioningProfiles dict" "$EXPORT_OPTIONS_PATH" 2>/dev/null || true
/usr/libexec/PlistBuddy -c "Add :provisioningProfiles:$MAIN_BUNDLE_ID string customer" "$EXPORT_OPTIONS_PATH" 2>/dev/null || \
  /usr/libexec/PlistBuddy -c "Set :provisioningProfiles:$MAIN_BUNDLE_ID customer" "$EXPORT_OPTIONS_PATH"

xcodebuild -exportArchive -archivePath "$ARCHIVE_PATH" -exportPath "$EXPORT_DIR" -exportOptionsPlist "$EXPORT_OPTIONS_PATH"

if [[ -f "$EXPORT_DIR/$SCHEME.ipa" ]]; then
  cp "$EXPORT_DIR/$SCHEME.ipa" "$EXPORT_DIR/$IPA_NAME"
fi

echo "Built IPA: $EXPORT_DIR/$IPA_NAME"
