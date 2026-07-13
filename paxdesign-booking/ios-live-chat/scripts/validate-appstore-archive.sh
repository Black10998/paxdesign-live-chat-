#!/usr/bin/env bash
# Validates an App Store .xcarchive before export.
set -euo pipefail

ARCHIVE_PATH="${1:-}"
MAIN_BUNDLE_ID="${MAIN_BUNDLE_ID:-at.paxdesign.livechat}"
WIDGET_BUNDLE_ID="${WIDGET_BUNDLE_ID:-at.paxdesign.livechat.widgets}"
WIDGET_EXTENSION_NAME="${WIDGET_EXTENSION_NAME:-PAXWidgets.appex}"

fail() {
  echo "ERROR: $1" >&2
  exit 1
}

if [[ -z "$ARCHIVE_PATH" || ! -d "$ARCHIVE_PATH" ]]; then
  fail "Archive path not found: ${ARCHIVE_PATH:-<empty>}"
fi

APP_PATH="$(find "$ARCHIVE_PATH/Products/Applications" -maxdepth 1 -name '*.app' -type d | head -1)"
[[ -n "$APP_PATH" && -d "$APP_PATH" ]] || fail "No .app bundle found in archive"

WIDGET_PATH="$APP_PATH/PlugIns/$WIDGET_EXTENSION_NAME"
[[ -d "$WIDGET_PATH" ]] || fail "Widget extension not embedded at PlugIns/$WIDGET_EXTENSION_NAME"

MAIN_EXECUTABLE="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleExecutable' "$APP_PATH/Info.plist" 2>/dev/null || true)"
[[ -n "$MAIN_EXECUTABLE" && -f "$APP_PATH/$MAIN_EXECUTABLE" ]] || fail "Main executable missing from archived app"

WIDGET_EXECUTABLE="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleExecutable' "$WIDGET_PATH/Info.plist" 2>/dev/null || true)"
[[ -n "$WIDGET_EXECUTABLE" && -f "$WIDGET_PATH/$WIDGET_EXECUTABLE" ]] || fail "Widget executable missing from archived extension"

MAIN_BUILD="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleVersion' "$APP_PATH/Info.plist" 2>/dev/null || true)"
WIDGET_BUILD="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleVersion' "$WIDGET_PATH/Info.plist" 2>/dev/null || true)"
[[ -n "$MAIN_BUILD" && "$MAIN_BUILD" == "$WIDGET_BUILD" ]] \
  || fail "Widget CFBundleVersion ($WIDGET_BUILD) must match main app ($MAIN_BUILD)"

MAIN_ID="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleIdentifier' "$APP_PATH/Info.plist" 2>/dev/null || true)"
WIDGET_ID="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleIdentifier' "$WIDGET_PATH/Info.plist" 2>/dev/null || true)"
[[ "$MAIN_ID" == "$MAIN_BUNDLE_ID" ]] || fail "Main bundle ID mismatch (expected $MAIN_BUNDLE_ID, got ${MAIN_ID:-<empty>})"
[[ "$WIDGET_ID" == "$WIDGET_BUNDLE_ID" ]] || fail "Widget bundle ID mismatch (expected $WIDGET_BUNDLE_ID, got ${WIDGET_ID:-<empty>})"

if /usr/libexec/PlistBuddy -c "Print :UIBackgroundModes" "$APP_PATH/Info.plist" >/dev/null 2>&1; then
  /usr/libexec/PlistBuddy -c "Print :UIBackgroundModes" "$APP_PATH/Info.plist" | grep -q "remote-notification" \
    || fail "UIBackgroundModes must include remote-notification for production push"
else
  fail "UIBackgroundModes missing from archived app Info.plist"
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

read_codesign_entitlement() {
  local target_path="$1"
  local plist_key="$2"
  local temp_plist value=""
  temp_plist="$(mktemp)"
  if codesign -d --entitlements :- "$target_path" > "$temp_plist" 2>/dev/null && [[ -s "$temp_plist" ]]; then
    value="$(/usr/libexec/PlistBuddy -c "Print :$plist_key" "$temp_plist" 2>/dev/null || true)"
  fi
  rm -f "$temp_plist"
  printf '%s' "$value"
}

read_entitlements_value() {
  local target_path="$1"
  local plist_key="$2"
  local value=""
  local temp_plist

  temp_plist="$(mktemp)"
  if codesign -d --entitlements :- "$target_path" > "$temp_plist" 2>/dev/null && [[ -s "$temp_plist" ]]; then
    value="$(/usr/libexec/PlistBuddy -c "Print :$plist_key" "$temp_plist" 2>/dev/null || true)"
  fi
  rm -f "$temp_plist"

  if [[ -z "$value" ]] && [[ -f "$target_path/embedded.mobileprovision" ]]; then
    temp_plist="$(mktemp)"
    if security cms -D -i "$target_path/embedded.mobileprovision" > "$temp_plist" 2>/dev/null; then
      value="$(/usr/libexec/PlistBuddy -c "Print :Entitlements:$plist_key" "$temp_plist" 2>/dev/null || true)"
    fi
    rm -f "$temp_plist"
  fi

  [[ -n "$value" ]] || fail "Entitlement missing: $plist_key"
  printf '%s' "$value"
}

codesign --verify --deep --strict "$APP_PATH" 2>/dev/null || fail "Main app codesign verification failed"
codesign --verify --deep --strict "$WIDGET_PATH" 2>/dev/null || fail "Widget extension codesign verification failed"

MAIN_APS="$(read_codesign_entitlement "$APP_PATH" "aps-environment")"
[[ -n "$MAIN_APS" ]] || fail "Codesign entitlements missing aps-environment in archive (Push not embedded in signature)"
[[ "$MAIN_APS" == "production" ]] || fail "Main app codesign aps-environment must be production (got ${MAIN_APS})"

APS_MIRROR="$(/usr/libexec/PlistBuddy -c 'Print :PAXSignedAPSEnvironment' "$APP_PATH/Info.plist" 2>/dev/null || true)"
[[ "$APS_MIRROR" == "production" ]] || fail "Archived Info.plist missing PAXSignedAPSEnvironment=production"

MAIN_GROUPS="$(read_entitlements_value "$APP_PATH" "com.apple.security.application-groups:0")"
[[ "$MAIN_GROUPS" == "group.at.paxdesign.livechat" ]] || fail "Main app App Group entitlement missing or incorrect"

WIDGET_GROUPS="$(read_entitlements_value "$WIDGET_PATH" "com.apple.security.application-groups:0")"
[[ "$WIDGET_GROUPS" == "group.at.paxdesign.livechat" ]] || fail "Widget App Group entitlement missing or incorrect"

echo "App Store archive validation passed"
echo "  Archive: $ARCHIVE_PATH"
echo "  Main app: $MAIN_ID"
echo "  Widget: $WIDGET_ID (embedded)"
echo "  codesign aps-environment=$MAIN_APS"
echo "  PAXSignedAPSEnvironment=$APS_MIRROR"
