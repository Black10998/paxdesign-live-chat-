#!/usr/bin/env bash
# Builds a signed App Store IPA using project.yml (main app + widget extension).
set -euo pipefail

# Never trace commands that may include secrets.
set +x

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SCHEME="${SCHEME:-PAXDesignLiveChat}"
CONFIGURATION="${CONFIGURATION:-Release}"
PROJECT_SPEC="${PROJECT_SPEC:-$ROOT/project.yml}"
DERIVED_DATA="${DERIVED_DATA:-$ROOT/build/AppStore/DerivedData}"
ARCHIVE_PATH="${ARCHIVE_PATH:-$ROOT/build/AppStore/PAXDesignLiveChat.xcarchive}"
EXPORT_DIR="${EXPORT_DIR:-$ROOT/build/AppStore/export}"
IPA_NAME="${IPA_NAME:-PAXDesignLiveChat-AppStore.ipa}"
EXPORT_OPTIONS_PATH="${EXPORT_OPTIONS_PATH:-$ROOT/build/AppStore/ExportOptions.plist}"

MAIN_BUNDLE_ID="${MAIN_BUNDLE_ID:-at.paxdesign.livechat}"
WIDGET_BUNDLE_ID="${WIDGET_BUNDLE_ID:-at.paxdesign.livechat.widgets}"
APPLE_TEAM_ID="${APPLE_TEAM_ID:?APPLE_TEAM_ID is required}"
KEYCHAIN_PASSWORD="${KEYCHAIN_PASSWORD:?KEYCHAIN_PASSWORD is required}"
APPLE_CERTIFICATE_P12_BASE64="${APPLE_CERTIFICATE_P12_BASE64:?APPLE_CERTIFICATE_P12_BASE64 is required}"
APPLE_CERTIFICATE_PASSWORD="${APPLE_CERTIFICATE_PASSWORD:?APPLE_CERTIFICATE_PASSWORD is required}"
APPLE_PROVISIONING_PROFILE_MAIN_BASE64="${APPLE_PROVISIONING_PROFILE_MAIN_BASE64:?APPLE_PROVISIONING_PROFILE_MAIN_BASE64 is required}"
APPLE_PROVISIONING_PROFILE_WIDGET_BASE64="${APPLE_PROVISIONING_PROFILE_WIDGET_BASE64:?APPLE_PROVISIONING_PROFILE_WIDGET_BASE64 is required}"

SIGNING_DIR="${SIGNING_DIR:-$ROOT/build/AppStore/signing}"
KEYCHAIN_PATH="${KEYCHAIN_PATH:-$SIGNING_DIR/app-signing.keychain-db}"
MAIN_PROFILE_PATH="$SIGNING_DIR/main.mobileprovision"
WIDGET_PROFILE_PATH="$SIGNING_DIR/widget.mobileprovision"
CERTIFICATE_PATH="$SIGNING_DIR/distribution.p12"
VALIDATE_ARCHIVE_SCRIPT="$ROOT/scripts/validate-appstore-archive.sh"
VALIDATE_IPA_SCRIPT="$ROOT/scripts/validate-appstore-ipa.sh"

fail() {
  echo "ERROR: $1" >&2
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "$1 is required"
}

decode_base64_secret() {
  local secret_value="$1"
  local output_path="$2"
  local label="$3"

  local sanitized
  sanitized="$(printf '%s' "$secret_value" | tr -d '[:space:]')"
  [[ -n "$sanitized" ]] || fail "$label secret is empty"

  if ! printf '%s' "$sanitized" | base64 -D > "$output_path" 2>"$SIGNING_DIR/${label}.decode.err"; then
    rm -f "$output_path"
    fail "Failed to base64-decode $label (ensure the GitHub Secret contains only base64 text)"
  fi

  [[ -s "$output_path" ]] || fail "$label decoded to an empty file"
}

verify_certificate_payload() {
  if ! openssl pkcs12 -in "$CERTIFICATE_PATH" -noout -passin "pass:${APPLE_CERTIFICATE_PASSWORD}" \
    2>"$SIGNING_DIR/certificate.verify.err"; then
    fail "Invalid Apple Distribution .p12 or incorrect APPLE_CERTIFICATE_PASSWORD"
  fi
}

verify_mobileprovision_payload() {
  local profile_path="$1"
  local label="$2"
  if ! security cms -D -i "$profile_path" > "$SIGNING_DIR/${label}.plist" 2>"$SIGNING_DIR/${label}.verify.err"; then
    fail "Invalid $label provisioning profile payload (check base64 secret formatting)"
  fi
}

profile_field() {
  local plist_path="$1"
  local field="$2"
  /usr/libexec/PlistBuddy -c "Print :$field" "$plist_path" 2>/dev/null
}

install_provisioning_profile() {
  local profile_path="$1"
  local plist_path="$2"
  local expected_bundle_suffix="$3"
  local label="$4"

  local uuid name app_id team_id
  uuid="$(profile_field "$plist_path" UUID)"
  name="$(profile_field "$plist_path" Name)"
  app_id="$(profile_field "$plist_path" Entitlements:application-identifier)"
  team_id="$(profile_field "$plist_path" Entitlements:com.apple.developer.team-identifier)"

  [[ -n "$uuid" ]] || fail "Could not read UUID from $label provisioning profile"
  [[ -n "$name" ]] || fail "Could not read Name from $label provisioning profile"
  [[ -n "$app_id" ]] || fail "Could not read application-identifier from $label provisioning profile"

  if [[ "$label" == "Main" && "$app_id" == *".widgets"* ]]; then
    fail "Main provisioning profile secret appears to contain the widget profile"
  fi
  if [[ "$label" == "Widget" && "$app_id" != *".widgets"* ]]; then
    fail "Widget provisioning profile secret appears to contain the main app profile"
  fi

  [[ "$app_id" == *"$expected_bundle_suffix" ]] \
    || fail "$label provisioning profile bundle ID mismatch (expected *$expected_bundle_suffix*, got ${app_id:-<empty>}, team ${team_id:-<unknown>})"

  local profiles_dir="$HOME/Library/MobileDevice/Provisioning Profiles"
  mkdir -p "$profiles_dir"
  cp "$profile_path" "$profiles_dir/$uuid.mobileprovision"
}

setup_signing_assets() {
  echo "==> Preparing signing assets"
  mkdir -p "$SIGNING_DIR"
  rm -f "$KEYCHAIN_PATH" "$CERTIFICATE_PATH" "$MAIN_PROFILE_PATH" "$WIDGET_PROFILE_PATH"
  rm -f "$SIGNING_DIR"/*.err "$SIGNING_DIR"/*.plist 2>/dev/null || true

  echo "==> Decoding signing secrets"
  decode_base64_secret "$APPLE_CERTIFICATE_P12_BASE64" "$CERTIFICATE_PATH" "certificate"
  decode_base64_secret "$APPLE_PROVISIONING_PROFILE_MAIN_BASE64" "$MAIN_PROFILE_PATH" "main-profile"
  decode_base64_secret "$APPLE_PROVISIONING_PROFILE_WIDGET_BASE64" "$WIDGET_PROFILE_PATH" "widget-profile"

  echo "==> Validating decoded signing payloads"
  verify_certificate_payload
  verify_mobileprovision_payload "$MAIN_PROFILE_PATH" "main-profile"
  verify_mobileprovision_payload "$WIDGET_PROFILE_PATH" "widget-profile"

  echo "==> Creating temporary keychain"
  security delete-keychain "$KEYCHAIN_PATH" >/dev/null 2>&1 || true
  security create-keychain -p "$KEYCHAIN_PASSWORD" "$KEYCHAIN_PATH" \
    || fail "Failed to create temporary keychain"
  security set-keychain-settings -lut 21600 "$KEYCHAIN_PATH"
  security unlock-keychain -p "$KEYCHAIN_PASSWORD" "$KEYCHAIN_PATH" \
    || fail "Failed to unlock temporary keychain"

  existing_keychains="$(security list-keychains -d user | tr -d '"')"
  # shellcheck disable=SC2086
  security list-keychains -d user -s "$KEYCHAIN_PATH" $existing_keychains

  echo "==> Importing Apple Distribution certificate"
  if ! security import "$CERTIFICATE_PATH" \
    -k "$KEYCHAIN_PATH" \
    -P "$APPLE_CERTIFICATE_PASSWORD" \
    -A \
    -T /usr/bin/codesign \
    -T /usr/bin/security \
    -f pkcs12 \
    2>"$SIGNING_DIR/certificate.import.err"; then
    fail "Apple Distribution certificate import failed (verify .p12 secret and password)"
  fi

  if ! security set-key-partition-list -S apple-tool:,apple:,codesign: -s -k "$KEYCHAIN_PASSWORD" "$KEYCHAIN_PATH" \
    >"$SIGNING_DIR/keychain.partition.out" 2>"$SIGNING_DIR/keychain.partition.err"; then
    fail "Failed to configure keychain partition list for codesign"
  fi

  echo "==> Installing provisioning profiles"
  install_provisioning_profile "$MAIN_PROFILE_PATH" "$SIGNING_DIR/main-profile.plist" "$MAIN_BUNDLE_ID" "Main"
  MAIN_PROFILE_UUID="$(profile_field "$SIGNING_DIR/main-profile.plist" UUID)"
  MAIN_PROFILE_NAME="$(profile_field "$SIGNING_DIR/main-profile.plist" Name)"

  install_provisioning_profile "$WIDGET_PROFILE_PATH" "$SIGNING_DIR/widget-profile.plist" "$WIDGET_BUNDLE_ID" "Widget"
  WIDGET_PROFILE_UUID="$(profile_field "$SIGNING_DIR/widget-profile.plist" UUID)"
  WIDGET_PROFILE_NAME="$(profile_field "$SIGNING_DIR/widget-profile.plist" Name)"

  echo "==> Signing assets ready"
  echo "    Main profile: $MAIN_PROFILE_NAME ($MAIN_PROFILE_UUID)"
  echo "    Widget profile: $WIDGET_PROFILE_NAME ($WIDGET_PROFILE_UUID)"

  rm -f "$CERTIFICATE_PATH"
}

verify_entitlements_source() {
  local entitlements_file="$ROOT/PAXDesignLiveChat/PAXDesignLiveChat.entitlements"
  local aps_value
  aps_value="$(/usr/libexec/PlistBuddy -c 'Print :aps-environment' "$entitlements_file" 2>/dev/null || true)"
  [[ "$aps_value" == "production" ]] \
    || fail "PAXDesignLiveChat.entitlements must use aps-environment=production for App Store builds"
}

generate_export_options() {
  mkdir -p "$(dirname "$EXPORT_OPTIONS_PATH")"
  cat > "$EXPORT_OPTIONS_PATH" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>method</key>
	<string>app-store</string>
	<key>teamID</key>
	<string>${APPLE_TEAM_ID}</string>
	<key>signingStyle</key>
	<string>manual</string>
	<key>uploadSymbols</key>
	<true/>
	<key>compileBitcode</key>
	<false/>
	<key>provisioningProfiles</key>
	<dict>
		<key>${MAIN_BUNDLE_ID}</key>
		<string>${MAIN_PROFILE_NAME}</string>
		<key>${WIDGET_BUNDLE_ID}</key>
		<string>${WIDGET_PROFILE_NAME}</string>
	</dict>
</dict>
</plist>
EOF
}

echo "==> App Store build starting"
require_cmd xcodegen
require_cmd xcodebuild
require_cmd security
require_cmd openssl

setup_signing_assets
verify_entitlements_source

echo "==> Generating Xcode project from project.yml"
xcodegen generate --spec "$PROJECT_SPEC"

echo "==> Applying manual signing settings to generated Xcode project"
python3 - <<PY
from pathlib import Path

pbxproj = Path("$ROOT/PAXDesignLiveChat.xcodeproj/project.pbxproj")
text = pbxproj.read_text()
text = text.replace("CODE_SIGN_STYLE = Automatic;", "CODE_SIGN_STYLE = Manual;")
text = text.replace('DEVELOPMENT_TEAM = "";', 'DEVELOPMENT_TEAM = $APPLE_TEAM_ID;')
pbxproj.write_text(text)
PY

echo "==> Archiving $SCHEME ($CONFIGURATION)"
rm -rf "$DERIVED_DATA" "$ARCHIVE_PATH" "$EXPORT_DIR"
mkdir -p "$DERIVED_DATA" "$EXPORT_DIR"

xcodebuild archive \
  -project "$ROOT/PAXDesignLiveChat.xcodeproj" \
  -scheme "$SCHEME" \
  -configuration "$CONFIGURATION" \
  -destination 'generic/platform=iOS' \
  -archivePath "$ARCHIVE_PATH" \
  -derivedDataPath "$DERIVED_DATA" \
  CODE_SIGN_STYLE=Manual \
  DEVELOPMENT_TEAM="$APPLE_TEAM_ID" \
  CODE_SIGN_IDENTITY="Apple Distribution" \
  "PAXDesignLiveChat_CODE_SIGN_STYLE=Manual" \
  "PAXWidgets_CODE_SIGN_STYLE=Manual" \
  "PAXDesignLiveChat_DEVELOPMENT_TEAM=$APPLE_TEAM_ID" \
  "PAXWidgets_DEVELOPMENT_TEAM=$APPLE_TEAM_ID" \
  "PAXDesignLiveChat_CODE_SIGN_IDENTITY=Apple Distribution" \
  "PAXWidgets_CODE_SIGN_IDENTITY=Apple Distribution" \
  "PAXDesignLiveChat_PROVISIONING_PROFILE=$MAIN_PROFILE_UUID" \
  "PAXWidgets_PROVISIONING_PROFILE=$WIDGET_PROFILE_UUID" \
  "PAXDesignLiveChat_PROVISIONING_PROFILE_SPECIFIER=$MAIN_PROFILE_NAME" \
  "PAXWidgets_PROVISIONING_PROFILE_SPECIFIER=$WIDGET_PROFILE_NAME" \
  "PAXDesignLiveChatTests_CODE_SIGNING_ALLOWED=NO" \
  OTHER_CODE_SIGN_FLAGS="--keychain $KEYCHAIN_PATH"

[[ -x "$VALIDATE_ARCHIVE_SCRIPT" ]] || fail "Missing validator: $VALIDATE_ARCHIVE_SCRIPT"
echo "==> Validating archive before export"
"$VALIDATE_ARCHIVE_SCRIPT" "$ARCHIVE_PATH"

echo "==> Generating ExportOptions.plist"
generate_export_options

echo "==> Exporting signed App Store IPA"
xcodebuild -exportArchive \
  -archivePath "$ARCHIVE_PATH" \
  -exportPath "$EXPORT_DIR" \
  -exportOptionsPlist "$EXPORT_OPTIONS_PATH"

EXPORTED_IPA="$(find "$EXPORT_DIR" -maxdepth 1 -name '*.ipa' -type f | head -1)"
[[ -n "$EXPORTED_IPA" && -f "$EXPORTED_IPA" ]] || fail "Exported IPA not found in $EXPORT_DIR"

FINAL_IPA="$EXPORT_DIR/$IPA_NAME"
if [[ "$EXPORTED_IPA" != "$FINAL_IPA" ]]; then
  mv "$EXPORTED_IPA" "$FINAL_IPA"
fi

[[ -x "$VALIDATE_IPA_SCRIPT" ]] || fail "Missing validator: $VALIDATE_IPA_SCRIPT"
echo "==> Validating exported IPA"
"$VALIDATE_IPA_SCRIPT" "$FINAL_IPA"

echo "==> App Store build complete"
ls -lh "$FINAL_IPA"
echo "IPA_PATH=$FINAL_IPA"
echo "ARCHIVE_PATH=$ARCHIVE_PATH"
echo "EXPORT_OPTIONS_PATH=$EXPORT_OPTIONS_PATH"
