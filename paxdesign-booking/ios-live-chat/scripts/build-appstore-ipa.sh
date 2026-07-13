#!/usr/bin/env bash
# Builds a signed App Store IPA using project.yml (main app + widget extension).
set -euo pipefail

# Never trace commands that may include secrets.
set +x

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
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

  if [[ "$label" == "Main" ]]; then
    local aps_value
    aps_value="$(profile_field "$plist_path" Entitlements:aps-environment)"
    [[ "$aps_value" == "production" ]] \
      || fail "Main provisioning profile must include aps-environment=production (enable Push Notifications for $expected_bundle_suffix in Apple Developer)"
  fi

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

APS_ENTITLEMENT_VALUE=""

verify_entitlements_source() {
  local entitlements_file="$ROOT/PAXDesignLiveChat/PAXDesignLiveChat.entitlements"
  local aps_value
  aps_value="$(/usr/libexec/PlistBuddy -c 'Print :aps-environment' "$entitlements_file" 2>/dev/null || true)"
  [[ "$aps_value" == "production" ]] \
    || fail "PAXDesignLiveChat.entitlements must use aps-environment=production for App Store builds"
  APS_ENTITLEMENT_VALUE="$aps_value"
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

select_xcode_26() {
  local xcode_app=""
  for candidate in Xcode_26.5.app Xcode_26.app Xcode_26.0.app; do
    if [[ -d "/Applications/$candidate" ]]; then
      xcode_app="/Applications/$candidate"
      break
    fi
  done
  if [[ -z "$xcode_app" ]]; then
    echo "ERROR: Xcode 26 is required for App Store Connect uploads." >&2
    ls -1 /Applications/Xcode*.app 2>/dev/null || true
    exit 1
  fi
  export DEVELOPER_DIR="$xcode_app/Contents/Developer"
  echo "Using DEVELOPER_DIR=$DEVELOPER_DIR"
  xcodebuild -version
}

echo "==> App Store build starting"
select_xcode_26
require_cmd xcodegen
require_cmd xcodebuild
require_cmd security
require_cmd openssl

setup_signing_assets
verify_entitlements_source

echo "==> Generating notification sound assets"
python3 "$SCRIPT_DIR/generate-notification-sounds.py"

echo "==> Generating Xcode project from project.yml"
xcodegen generate --spec "$PROJECT_SPEC"

echo "==> Syncing Info.plist build versions"
MARKETING_VERSION="$(grep 'MARKETING_VERSION:' "$PROJECT_SPEC" | head -1 | sed 's/.*"\([^"]*\)".*/\1/')"
BUILD_VERSION="$(grep 'CURRENT_PROJECT_VERSION:' "$PROJECT_SPEC" | head -1 | sed 's/.*"\([0-9]*\)".*/\1/')"
[[ -n "$MARKETING_VERSION" && -n "$BUILD_VERSION" ]] || fail "Could not read MARKETING_VERSION/CURRENT_PROJECT_VERSION from $PROJECT_SPEC"

sync_plist_version() {
  local plist_path="$1"
  [[ -f "$plist_path" ]] || fail "Missing plist: $plist_path"
  /usr/libexec/PlistBuddy -c "Delete :CFBundleShortVersionString" "$plist_path" >/dev/null 2>&1 || true
  /usr/libexec/PlistBuddy -c "Add :CFBundleShortVersionString string $MARKETING_VERSION" "$plist_path"
  /usr/libexec/PlistBuddy -c "Delete :CFBundleVersion" "$plist_path" >/dev/null 2>&1 || true
  /usr/libexec/PlistBuddy -c "Add :CFBundleVersion string $BUILD_VERSION" "$plist_path"
}

inject_signed_aps_environment() {
  local plist_path="$1"
  [[ -n "$APS_ENTITLEMENT_VALUE" ]] || fail "APS entitlement value not captured during build validation"
  /usr/libexec/PlistBuddy -c "Delete :PAXSignedAPSEnvironment" "$plist_path" >/dev/null 2>&1 || true
  /usr/libexec/PlistBuddy -c "Add :PAXSignedAPSEnvironment string $APS_ENTITLEMENT_VALUE" "$plist_path"
}

sync_plist_version "$ROOT/PAXDesignLiveChat/Info.plist"
inject_signed_aps_environment "$ROOT/PAXDesignLiveChat/Info.plist"
sync_plist_version "$ROOT/PAXWidgets/Info.plist"
echo "    Synced CFBundleVersion=$BUILD_VERSION CFBundleShortVersionString=$MARKETING_VERSION"

echo "==> Applying manual signing settings to generated Xcode project"
export ROOT MAIN_BUNDLE_ID WIDGET_BUNDLE_ID APPLE_TEAM_ID \
  MAIN_PROFILE_UUID MAIN_PROFILE_NAME WIDGET_PROFILE_UUID WIDGET_PROFILE_NAME
python3 - <<'PY'
import os
import re
from pathlib import Path

root = Path(os.environ["ROOT"])
pbxproj = root / "PAXDesignLiveChat.xcodeproj/project.pbxproj"
text = pbxproj.read_text()

targets = [
    {
        "target_name": "PAXDesignLiveChat",
        "bundle_id": os.environ["MAIN_BUNDLE_ID"],
        "team_id": os.environ["APPLE_TEAM_ID"],
        "profile_uuid": os.environ["MAIN_PROFILE_UUID"],
        "profile_name": os.environ["MAIN_PROFILE_NAME"],
        "entitlements_path": "PAXDesignLiveChat/PAXDesignLiveChat.entitlements",
    },
    {
        "target_name": "PAXWidgets",
        "bundle_id": os.environ["WIDGET_BUNDLE_ID"],
        "team_id": os.environ["APPLE_TEAM_ID"],
        "profile_uuid": os.environ["WIDGET_PROFILE_UUID"],
        "profile_name": os.environ["WIDGET_PROFILE_NAME"],
        "entitlements_path": "PAXWidgets/PAXWidgets.entitlements",
    },
]

def upsert_setting(block: str, key: str, value: str) -> str:
    line = f"{key} = {value};"
    pattern = re.compile(rf"^\s*{re.escape(key)} = .*?;$", re.MULTILINE)
    if pattern.search(block):
        return pattern.sub(line, block, count=1)
    return block.replace("buildSettings = {", "buildSettings = {\n\t\t\t\t" + line, 1)

def patch_release_block(block: str, team_id: str, profile_uuid: str, profile_name: str, entitlements_path: str) -> str:
    block = upsert_setting(block, "CODE_SIGN_STYLE", "Manual")
    block = upsert_setting(block, "DEVELOPMENT_TEAM", team_id)
    block = upsert_setting(block, "CODE_SIGN_IDENTITY", '"Apple Distribution"')
    block = upsert_setting(block, "PROVISIONING_PROFILE", profile_uuid)
    block = upsert_setting(block, "PROVISIONING_PROFILE_SPECIFIER", f'"{profile_name}"')
    block = upsert_setting(block, "CODE_SIGN_ENTITLEMENTS", f'"{entitlements_path}"')
    return block

def release_config_ids_for_target(text: str, target_name: str) -> list[str]:
    target_match = re.search(
        rf"buildConfigurationList = ([A-F0-9]+) /\* Build configuration list for PBXNativeTarget \"{re.escape(target_name)}\" \*/;",
        text,
    )
    if not target_match:
        raise SystemExit(f"Could not find build configuration list for target {target_name}")
    config_list_id = target_match.group(1)
    list_match = re.search(
        rf"{config_list_id} /\* Build configuration list for PBXNativeTarget \"{re.escape(target_name)}\" \*/ = \{{\n\t\t\tisa = XCConfigurationList;\n\t\t\tbuildConfigurations = \((.*?)\);",
        text,
        re.DOTALL,
    )
    if not list_match:
        raise SystemExit(f"Could not parse configuration list for target {target_name}")
    return re.findall(r"([A-F0-9]+) /\* (Debug|Release) \*/", list_match.group(1))

def patch_target(text: str, target_name: str, team_id: str, profile_uuid: str, profile_name: str, entitlements_path: str) -> str:
    release_ids = {
        config_id
        for config_id, config_name in release_config_ids_for_target(text, target_name)
        if config_name == "Release"
    }
    if not release_ids:
        raise SystemExit(f"No Release configuration found for target {target_name}")

    patched = 0
    for config_id in release_ids:
        pattern = re.compile(
            rf"({config_id} /\* Release \*/ = \{{\n\t\t\tisa = XCBuildConfiguration;\n\t\t\tbuildSettings = \{{.*?\n\t\t\}};)",
            re.DOTALL,
        )

        def repl(match):
            nonlocal patched
            patched += 1
            return patch_release_block(match.group(1), team_id, profile_uuid, profile_name, entitlements_path)

        text, count = pattern.subn(repl, text, count=1)
        if count != 1:
            raise SystemExit(f"Failed to patch Release block {config_id} for target {target_name}")

    if patched == 0:
        raise SystemExit(f"Failed to patch Release config for target {target_name}")
    return text

for target in targets:
    text = patch_target(
        text,
        target["target_name"],
        target["team_id"],
        target["profile_uuid"],
        target["profile_name"],
        target["entitlements_path"],
    )

text = text.replace("CODE_SIGN_STYLE = Automatic;", "CODE_SIGN_STYLE = Manual;")
text = text.replace('DEVELOPMENT_TEAM = "";', f'DEVELOPMENT_TEAM = {os.environ["APPLE_TEAM_ID"]};')
pbxproj.write_text(text)
print("Patched manual signing for targets: PAXDesignLiveChat, PAXWidgets")
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
  "PAXDesignLiveChat_CODE_SIGN_ENTITLEMENTS=PAXDesignLiveChat/PAXDesignLiveChat.entitlements" \
  "PAXWidgets_CODE_SIGN_ENTITLEMENTS=PAXWidgets/PAXWidgets.entitlements" \
  "PAXDesignLiveChatTests_CODE_SIGNING_ALLOWED=NO" \
  OTHER_CODE_SIGN_FLAGS="--keychain $KEYCHAIN_PATH"

[[ -x "$VALIDATE_ARCHIVE_SCRIPT" ]] || fail "Missing validator: $VALIDATE_ARCHIVE_SCRIPT"

ARCHIVE_APP="$(find "$ARCHIVE_PATH/Products/Applications" -maxdepth 1 -name '*.app' -type d | head -1)"
[[ -n "$ARCHIVE_APP" && -d "$ARCHIVE_APP" ]] || fail "Archived app bundle not found"
echo "==> Injecting PAXSignedAPSEnvironment into archived app Info.plist"
inject_signed_aps_environment "$ARCHIVE_APP/Info.plist"
APS_MIRROR="$(/usr/libexec/PlistBuddy -c 'Print :PAXSignedAPSEnvironment' "$ARCHIVE_APP/Info.plist" 2>/dev/null || true)"
[[ "$APS_MIRROR" == "$APS_ENTITLEMENT_VALUE" ]] || fail "Archived Info.plist missing PAXSignedAPSEnvironment mirror"

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
