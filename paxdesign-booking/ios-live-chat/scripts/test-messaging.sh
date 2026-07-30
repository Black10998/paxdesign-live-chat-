#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
xcodegen generate --spec "$ROOT/project.yml"

UDID="$(python3 <<'PY'
import json, subprocess
data = json.loads(subprocess.check_output(["xcrun", "simctl", "list", "devices", "available", "-j"], text=True))
items = []
for runtime, devices in data.get("devices", {}).items():
    if "iOS" not in runtime:
        continue
    for device in devices:
        if device.get("isAvailable") and "iPhone" in device.get("name", ""):
            items.append((runtime, device["name"], device["udid"]))
items.sort(reverse=True)
if not items:
    raise SystemExit("No iPhone simulator available")
print(items[0][2])
PY
)"

xcrun simctl boot "$UDID" 2>/dev/null || true
xcrun simctl bootstatus "$UDID" -b
xcodebuild \
  -project "$ROOT/PAXDesignLiveChat.xcodeproj" \
  -scheme PAXDesignLiveChat \
  -destination "platform=iOS Simulator,id=$UDID" \
  -derivedDataPath "$ROOT/build/TestDerivedData" \
  test
