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

read_entitlements_value() {
  local target_path="$1"
  local plist_key="$2"
  local value=""
  local temp_plist

  if [[ -f "$target_path/embedded.mobileprovision" ]]; then
    temp_plist="$(mktemp)"
    if security cms -D -i "$target_path/embedded.mobileprovision" > "$temp_plist" 2>/dev/null; then
      value="$(/usr/libexec/PlistBuddy -c "Print :Entitlements:$plist_key" "$temp_plist" 2>/dev/null || true)"
    fi
    rm -f "$temp_plist"
  fi

  if [[ -z "$value" ]]; then
    temp_plist="$(mktemp)"
    if codesign -d --entitlements :- "$target_path" > "$temp_plist" 2>/dev/null && [[ -s "$temp_plist" ]]; then
      value="$(/usr/libexec/PlistBuddy -c "Print :$plist_key" "$temp_plist" 2>/dev/null || true)"
    fi
    rm -f "$temp_plist"
  fi

  [[ -n "$value" ]] || fail "Entitlement missing: $plist_key"
  printf '%s' "$value"
}

codesign --verify --deep --strict "$APP_PATH" 2>/dev/null || fail "Exported main app codesign verification failed"
codesign --verify --deep --strict "$WIDGET_PATH" 2>/dev/null || fail "Exported widget codesign verification failed"

MAIN_APS="$(read_entitlements_value "$APP_PATH" "aps-environment")"
[[ "$MAIN_APS" == "production" ]] || fail "Exported IPA aps-environment must be production"

echo "App Store IPA validation passed: $(basename "$IPA_PATH")"
