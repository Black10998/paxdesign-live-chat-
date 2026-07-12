#!/usr/bin/env bash
# Validates a sideload IPA before release — fails CI if known launch-crash triggers remain.
set -euo pipefail

IPA="${1:-}"
if [[ -z "$IPA" || ! -f "$IPA" ]]; then
  echo "Usage: validate-ipa.sh <path/to/PAXDesignLiveChat.ipa>" >&2
  exit 1
fi

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

unzip -q "$IPA" -d "$WORKDIR"
APP_PATH="$(find "$WORKDIR/Payload" -maxdepth 1 -name '*.app' -type d | head -1)"
if [[ -z "$APP_PATH" || ! -d "$APP_PATH" ]]; then
  echo "ERROR: No .app bundle found in IPA" >&2
  exit 1
fi

fail() {
  echo "ERROR: $1" >&2
  exit 1
}

[[ ! -d "$APP_PATH/PlugIns" ]] || fail "PlugIns directory must be absent for sideload stability ($APP_PATH/PlugIns)"
[[ ! -d "$APP_PATH/Metadata.appintents" ]] || fail "Metadata.appintents must be absent for sideload stability"
find "$APP_PATH" -type d -name 'nlu.appintents' | grep -q . && fail "Localized nlu.appintents bundles must be absent for sideload stability"

PLIST="$APP_PATH/Info.plist"
for key in NSFaceIDUsageDescription NSCameraUsageDescription NSPhotoLibraryUsageDescription NSUserNotificationsUsageDescription NSLocationWhenInUseUsageDescription CFBundleDisplayName; do
  /usr/libexec/PlistBuddy -c "Print :$key" "$PLIST" >/dev/null 2>&1 || fail "Info.plist missing required key: $key"
done

if /usr/libexec/PlistBuddy -c "Print :UIBackgroundModes" "$PLIST" >/dev/null 2>&1; then
  fail "UIBackgroundModes must be removed from sideload Info.plist"
fi

if [[ ! -f "$APP_PATH/PAXDesignLiveChat" ]]; then
  fail "Main executable missing from app bundle"
fi

echo "Sideload IPA validation passed: $(basename "$IPA")"
