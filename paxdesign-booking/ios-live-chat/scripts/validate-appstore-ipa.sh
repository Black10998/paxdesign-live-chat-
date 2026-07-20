#!/usr/bin/env bash
# Validates an exported App Store IPA.
set -euo pipefail

IPA_PATH="${1:-}"
MAIN_BUNDLE_ID="${MAIN_BUNDLE_ID:-at.paxdesign.livechat}"
WIDGET_BUNDLE_ID="${WIDGET_BUNDLE_ID:-at.paxdesign.livechat.widgets}"
WIDGET_EXTENSION_NAME="${WIDGET_EXTENSION_NAME:-PAXWidgets.appex}"

fail() {
  echo "ERROR: $1" >&2
  exit 1
}

if [[ -z "$IPA_PATH" || ! -f "$IPA_PATH" ]]; then
  fail "IPA not found: ${IPA_PATH:-<empty>}"
fi

MAX_IPA_BYTES="${MAX_IPA_BYTES:-90000000}"
IPA_BYTES="$(stat -c%s "$IPA_PATH" 2>/dev/null || stat -f%z "$IPA_PATH")"
if [[ "${IPA_BYTES:-0}" -gt "$MAX_IPA_BYTES" ]]; then
  fail "IPA size ${IPA_BYTES} bytes exceeds budget ${MAX_IPA_BYTES} bytes — check for bundled duplicates or unstripped assets"
fi

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

unzip -q "$IPA_PATH" -d "$WORKDIR"
APP_PATH="$(find "$WORKDIR/Payload" -maxdepth 1 -name '*.app' -type d | head -1)"
[[ -n "$APP_PATH" && -d "$APP_PATH" ]] || fail "No .app bundle found in IPA"

WIDGET_PATH="$APP_PATH/PlugIns/$WIDGET_EXTENSION_NAME"
[[ -d "$WIDGET_PATH" ]] || fail "Widget extension not embedded in exported IPA"

MAIN_ID="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleIdentifier' "$APP_PATH/Info.plist" 2>/dev/null || true)"
WIDGET_ID="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleIdentifier' "$WIDGET_PATH/Info.plist" 2>/dev/null || true)"
[[ "$MAIN_ID" == "$MAIN_BUNDLE_ID" ]] || fail "Main bundle ID mismatch in IPA"
[[ "$WIDGET_ID" == "$WIDGET_BUNDLE_ID" ]] || fail "Widget bundle ID mismatch in IPA"

[[ -f "$APP_PATH/embedded.mobileprovision" ]] || fail "embedded.mobileprovision missing from main app"
[[ -f "$WIDGET_PATH/embedded.mobileprovision" ]] || fail "embedded.mobileprovision missing from widget extension"

read_codesign_entitlement() {
  local target_path="$1"
  local plist_key="$2"
  local temp_plist value=""
  temp_plist="$(mktemp)"
  if codesign -d --entitlements :- "$target_path" > "$temp_plist" 2>/dev/null && [[ -s "$temp_plist" ]]; then
    value="$(/usr/libexec/PlistBuddy -c "Print :$plist_key" "$temp_plist" 2>/dev/null || true)"
  fi
  if [[ -z "$value" ]]; then
    rm -f "$temp_plist"
    temp_plist="$(mktemp)"
    if codesign -d --entitlements "$temp_plist" "$target_path" 2>/dev/null && [[ -s "$temp_plist" ]]; then
      value="$(/usr/libexec/PlistBuddy -c "Print :$plist_key" "$temp_plist" 2>/dev/null || true)"
    fi
  fi
  rm -f "$temp_plist"
  printf '%s' "$value"
}

read_profile_entitlement() {
  local target_path="$1"
  local plist_key="$2"
  local temp_plist value=""
  if [[ -f "$target_path/embedded.mobileprovision" ]]; then
    temp_plist="$(mktemp)"
    if security cms -D -i "$target_path/embedded.mobileprovision" > "$temp_plist" 2>/dev/null; then
      value="$(/usr/libexec/PlistBuddy -c "Print :Entitlements:$plist_key" "$temp_plist" 2>/dev/null || true)"
    fi
    rm -f "$temp_plist"
  fi
  printf '%s' "$value"
}

codesign --verify --deep --strict "$APP_PATH" 2>/dev/null || fail "Exported main app codesign verification failed"
codesign --verify --deep --strict "$WIDGET_PATH" 2>/dev/null || fail "Exported widget codesign verification failed"

MAIN_APS="$(read_codesign_entitlement "$APP_PATH" "aps-environment")"
[[ -n "$MAIN_APS" ]] || fail "Codesign entitlements missing aps-environment in exported IPA (Push not embedded in signature)"
[[ "$MAIN_APS" == "production" ]] || fail "Exported IPA codesign aps-environment must be production (got ${MAIN_APS})"

PROFILE_APS="$(read_profile_entitlement "$APP_PATH" "aps-environment")"
[[ "$PROFILE_APS" == "production" ]] || fail "embedded.mobileprovision aps-environment must be production (got ${PROFILE_APS:-<missing>})"

APS_MIRROR="$(/usr/libexec/PlistBuddy -c 'Print :PAXSignedAPSEnvironment' "$APP_PATH/Info.plist" 2>/dev/null || true)"
[[ "$APS_MIRROR" == "production" ]] || fail "Info.plist missing PAXSignedAPSEnvironment=production (got ${APS_MIRROR:-<missing>})"

if /usr/libexec/PlistBuddy -c "Print :UIBackgroundModes" "$APP_PATH/Info.plist" >/dev/null 2>&1; then
  /usr/libexec/PlistBuddy -c "Print :UIBackgroundModes" "$APP_PATH/Info.plist" | grep -q "remote-notification" \
    || fail "UIBackgroundModes must include remote-notification for production push"
else
  fail "UIBackgroundModes missing from exported IPA Info.plist"
fi

for key in \
  NSFaceIDUsageDescription \
  NSCameraUsageDescription \
  NSPhotoLibraryUsageDescription \
  NSUserNotificationsUsageDescription \
  NSLocationWhenInUseUsageDescription \
  CFBundleDisplayName; do
  if ! /usr/libexec/PlistBuddy -c "Print :$key" "$APP_PATH/Info.plist" >/dev/null 2>&1; then
    fail "Info.plist missing required key: $key"
  fi
done

echo "App Store IPA validation passed: $(basename "$IPA_PATH")"
echo "  codesign aps-environment=$MAIN_APS"
echo "  profile aps-environment=$PROFILE_APS"
echo "  PAXSignedAPSEnvironment=$APS_MIRROR"
