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
APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64="${APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64:-}"

SIGNING_DIR="${SIGNING_DIR:-$ROOT/build/AppStore/signing}"
KEYCHAIN_PATH="${KEYCHAIN_PATH:-$SIGNING_DIR/app-signing.keychain-db}"
PROFILE_PATH="$SIGNING_DIR/customer.mobileprovision"
CERTIFICATE_PATH="$SIGNING_DIR/distribution.p12"

fail() {
  echo "ERROR: $1" >&2
  exit 1
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
  if ! security cms -D -i "$PROFILE_PATH" > "$SIGNING_DIR/customer-profile.plist" 2>"$SIGNING_DIR/customer-profile.verify.err"; then
    fail "Invalid customer provisioning profile payload (check APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64 formatting)"
  fi
}

profile_field() {
  local plist_path="$1"
  local field="$2"
  /usr/libexec/PlistBuddy -c "Print :$field" "$plist_path" 2>/dev/null
}

install_provisioning_profile() {
  local uuid name app_id team_id aps_value
  uuid="$(profile_field "$SIGNING_DIR/customer-profile.plist" UUID)"
  name="$(profile_field "$SIGNING_DIR/customer-profile.plist" Name)"
  app_id="$(profile_field "$SIGNING_DIR/customer-profile.plist" Entitlements:application-identifier)"
  team_id="$(profile_field "$SIGNING_DIR/customer-profile.plist" Entitlements:com.apple.developer.team-identifier)"

  [[ -n "$uuid" ]] || fail "Could not read UUID from customer provisioning profile"
  [[ -n "$name" ]] || fail "Could not read Name from customer provisioning profile"
  [[ -n "$app_id" ]] || fail "Could not read application-identifier from customer provisioning profile"

  [[ "$app_id" == *"$MAIN_BUNDLE_ID" ]] \
    || fail "Customer provisioning profile bundle ID mismatch (expected *$MAIN_BUNDLE_ID*, got ${app_id:-<empty>}, team ${team_id:-<unknown>}). Do not reuse the Live Chat profile — add APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64 for at.paxdesign.customerportal."

  aps_value="$(profile_field "$SIGNING_DIR/customer-profile.plist" Entitlements:aps-environment)"
  [[ "$aps_value" == "production" ]] \
    || fail "Customer provisioning profile must include aps-environment=production"

  local profiles_dir="$HOME/Library/MobileDevice/Provisioning Profiles"
  mkdir -p "$profiles_dir"
  cp "$PROFILE_PATH" "$profiles_dir/$uuid.mobileprovision"
  CUSTOMER_PROFILE_UUID="$uuid"
  CUSTOMER_PROFILE_NAME="$name"
}

setup_signing_assets() {
  echo "==> Preparing Customer Portal signing assets"
  [[ -n "$APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64" ]] \
    || fail "APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64 is required for at.paxdesign.customerportal (Live Chat profile cannot be substituted)"

  mkdir -p "$SIGNING_DIR" "$EXPORT_DIR"
  rm -f "$KEYCHAIN_PATH" "$CERTIFICATE_PATH" "$PROFILE_PATH"
  rm -f "$SIGNING_DIR"/*.err "$SIGNING_DIR"/*.plist 2>/dev/null || true

  echo "==> Decoding signing secrets"
  decode_base64_secret "$APPLE_CERTIFICATE_P12_BASE64" "$CERTIFICATE_PATH" "certificate"
  decode_base64_secret "$APPLE_PROVISIONING_PROFILE_CUSTOMER_BASE64" "$PROFILE_PATH" "customer-profile"

  echo "==> Validating decoded signing payloads"
  verify_certificate_payload
  verify_mobileprovision_payload

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
    fail "Apple Distribution certificate import failed (verify .p12 secret and APPLE_CERTIFICATE_PASSWORD)"
  fi

  if ! security set-key-partition-list -S apple-tool:,apple:,codesign: -s -k "$KEYCHAIN_PASSWORD" "$KEYCHAIN_PATH" \
    >"$SIGNING_DIR/keychain.partition.out" 2>"$SIGNING_DIR/keychain.partition.err"; then
    fail "Failed to configure keychain partition list for codesign"
  fi

  echo "==> Installing customer provisioning profile"
  install_provisioning_profile
  echo "    Customer profile: $CUSTOMER_PROFILE_NAME ($CUSTOMER_PROFILE_UUID)"
  rm -f "$CERTIFICATE_PATH"
}

generate_export_options() {
  mkdir -p "$(dirname "$EXPORT_OPTIONS_PATH")"
  cat > "$EXPORT_OPTIONS_PATH" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>method</key>
  <string>app-store-connect</string>
  <key>teamID</key>
  <string>$APPLE_TEAM_ID</string>
  <key>signingStyle</key>
  <string>manual</string>
  <key>provisioningProfiles</key>
  <dict>
    <key>$MAIN_BUNDLE_ID</key>
    <string>$CUSTOMER_PROFILE_NAME</string>
  </dict>
</dict>
</plist>
EOF
}

setup_signing_assets
command -v xcodegen >/dev/null || brew install xcodegen
xcodegen generate --spec "$PROJECT_SPEC"

echo "==> Archiving $SCHEME"
xcodebuild -scheme "$SCHEME" -configuration "$CONFIGURATION" -derivedDataPath "$DERIVED_DATA" \
  -archivePath "$ARCHIVE_PATH" archive \
  CODE_SIGN_STYLE=Manual \
  DEVELOPMENT_TEAM="$APPLE_TEAM_ID" \
  PROVISIONING_PROFILE_SPECIFIER="$CUSTOMER_PROFILE_NAME" \
  CODE_SIGN_IDENTITY="Apple Distribution"

generate_export_options

echo "==> Exporting IPA"
xcodebuild -exportArchive -archivePath "$ARCHIVE_PATH" -exportPath "$EXPORT_DIR" -exportOptionsPlist "$EXPORT_OPTIONS_PATH"

if [[ -f "$EXPORT_DIR/$SCHEME.ipa" ]]; then
  cp "$EXPORT_DIR/$SCHEME.ipa" "$EXPORT_DIR/$IPA_NAME"
fi

[[ -f "$EXPORT_DIR/$IPA_NAME" ]] || fail "Export did not produce $EXPORT_DIR/$IPA_NAME"
echo "Built IPA: $EXPORT_DIR/$IPA_NAME"
