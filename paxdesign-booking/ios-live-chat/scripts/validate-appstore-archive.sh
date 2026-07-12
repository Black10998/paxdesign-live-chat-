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

read_entitlements_value() {
  local target_path="$1"
  local plist_key="$2"
  local value=""

  if [[ -f "$target_path/embedded.mobileprovision" ]]; then
    value="$(security cms -D -i "$target_path/embedded.mobileprovision" 2>/dev/null \
      | /usr/libexec/PlistBuddy -c "Print :Entitlements:$plist_key" /dev/stdin 2>/dev/null || true)"
  fi

  if [[ -z "$value" ]]; then
    value="$(codesign -d --entitlements :- "$target_path" 2>/dev/null \
      | /usr/libexec/PlistBuddy -c "Print :$plist_key" /dev/stdin 2>/dev/null || true)"
  fi

  [[ -n "$value" ]] || fail "Entitlement missing: $plist_key"
  printf '%s' "$value"
}

codesign --verify --deep --strict "$APP_PATH" 2>/dev/null || fail "Main app codesign verification failed"
codesign --verify --deep --strict "$WIDGET_PATH" 2>/dev/null || fail "Widget extension codesign verification failed"

MAIN_APS="$(read_entitlements_value "$APP_PATH" "aps-environment")"
[[ "$MAIN_APS" == "production" ]] || fail "Main app aps-environment must be production (got ${MAIN_APS:-<missing>})"

MAIN_GROUPS="$(read_entitlements_value "$APP_PATH" "com.apple.security.application-groups:0")"
[[ "$MAIN_GROUPS" == "group.at.paxdesign.livechat" ]] || fail "Main app App Group entitlement missing or incorrect"

WIDGET_GROUPS="$(read_entitlements_value "$WIDGET_PATH" "com.apple.security.application-groups:0")"
[[ "$WIDGET_GROUPS" == "group.at.paxdesign.livechat" ]] || fail "Widget App Group entitlement missing or incorrect"

echo "App Store archive validation passed"
echo "  Archive: $ARCHIVE_PATH"
echo "  Main app: $MAIN_ID"
echo "  Widget: $WIDGET_ID (embedded)"
echo "  APNs: production"
