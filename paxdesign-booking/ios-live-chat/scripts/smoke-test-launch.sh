#!/usr/bin/env bash
# Boots an iOS Simulator, installs the sideload build, launches it, and verifies
# the process stays alive through the launch splash + initialization window.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SCHEME="PAXDesignLiveChat"
BUNDLE_ID="at.paxdesign.livechat"
PROJECT_SPEC="${PROJECT_SPEC:-$ROOT/project.sideload.yml}"
DERIVED_DATA="${DERIVED_DATA:-$ROOT/build/SmokeDerivedData}"
SIMULATOR_NAME="${SIMULATOR_NAME:-iPhone 16}"
LAUNCH_SETTLE_SECONDS="${LAUNCH_SETTLE_SECONDS:-12}"

echo "==> Generating sideload Xcode project for smoke test"
if ! command -v xcodegen >/dev/null 2>&1; then
  echo "xcodegen is required (brew install xcodegen)" >&2
  exit 1
fi
xcodegen generate --spec "$PROJECT_SPEC"

echo "==> Selecting simulator: $SIMULATOR_NAME"
UDID="$(python3 <<'PY'
import json, os, subprocess, sys
name = os.environ.get("SIMULATOR_NAME", "iPhone 16")
data = json.loads(subprocess.check_output(["xcrun", "simctl", "list", "devices", "available", "-j"], text=True))
candidates = []
for runtime, devices in data.get("devices", {}).items():
    if "iOS" not in runtime:
        continue
    for device in devices:
        if not device.get("isAvailable", False):
            continue
        if name in device.get("name", ""):
            candidates.append((runtime, device["name"], device["udid"]))
if not candidates:
    print("ERROR: No available simulator matching name", file=sys.stderr)
    sys.exit(1)
candidates.sort(reverse=True)
print(candidates[0][2])
PY
)"

echo "Using simulator UDID: $UDID"
xcrun simctl boot "$UDID" 2>/dev/null || true
xcrun simctl bootstatus "$UDID" -b

echo "==> Building for iOS Simulator (SIDELOAD configuration)"
rm -rf "$DERIVED_DATA"
xcodebuild \
  -project "$ROOT/PAXDesignLiveChat.xcodeproj" \
  -scheme "$SCHEME" \
  -configuration Debug \
  -destination "platform=iOS Simulator,id=$UDID" \
  -derivedDataPath "$DERIVED_DATA" \
  build

APP_PATH="$(find "$DERIVED_DATA/Build/Products" -type d -name '*.app' | grep 'iphonesimulator' | head -1)"
if [[ -z "$APP_PATH" || ! -d "$APP_PATH" ]]; then
  APP_PATH="$(find "$DERIVED_DATA/Build/Products" -type d -name 'PAXDesignLiveChat.app' | head -1)"
fi
if [[ -z "$APP_PATH" || ! -d "$APP_PATH" ]]; then
  echo "Simulator .app not found" >&2
  exit 1
fi

echo "==> Installing clean copy on simulator"
xcrun simctl uninstall "$UDID" "$BUNDLE_ID" 2>/dev/null || true
xcrun simctl install "$UDID" "$APP_PATH"

echo "==> Launching $BUNDLE_ID"
LAUNCH_OUTPUT="$(xcrun simctl launch "$UDID" "$BUNDLE_ID" 2>&1)"
echo "$LAUNCH_OUTPUT"
SIM_PID="$(echo "$LAUNCH_OUTPUT" | awk '{print $NF}')"
if [[ -z "$SIM_PID" || ! "$SIM_PID" =~ ^[0-9]+$ ]]; then
  echo "ERROR: Could not parse simulator launch PID" >&2
  exit 1
fi

echo "==> Waiting ${LAUNCH_SETTLE_SECONDS}s for launch splash + startup sequence"
sleep "$LAUNCH_SETTLE_SECONDS"

if xcrun simctl spawn "$UDID" ps -p "$SIM_PID" >/dev/null 2>&1; then
  echo "Smoke test PASSED: app process $SIM_PID still running after startup window"
else
  echo "ERROR: App process exited during startup (likely launch crash)" >&2
  echo "==> Recent simulator crash reports (if any)" >&2
  CRASH_DIR="$HOME/Library/Logs/DiagnosticReports"
  if [[ -d "$CRASH_DIR" ]]; then
    ls -t "$CRASH_DIR" | rg -i 'PAXDesignLiveChat|livechat' | head -3 | while read -r report; do
      echo "--- $report ---" >&2
      head -40 "$CRASH_DIR/$report" >&2
    done
  fi
  exit 1
fi

echo "Smoke test complete"
