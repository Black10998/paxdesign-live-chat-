#!/usr/bin/env bash
# Boots iOS Simulator and runs shell layout UI tests (tab bar vs composer/scroll).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SCHEME="${LAYOUT_VERIFY_SCHEME:-PAXDesignLiveChatLayoutVerify}"
PROJECT_SPEC="${PROJECT_SPEC:-$ROOT/project.yml}"
DERIVED_DATA="${DERIVED_DATA:-$ROOT/build/LayoutVerifyDerivedData}"
SIMULATOR_NAME="${SIMULATOR_NAME:-iPhone 16}"

echo "==> Generating Xcode project"
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

echo "==> Running shell layout UI tests (Debug / Simulator)"
rm -rf "$DERIVED_DATA"
set +e
RESULTS="$(mktemp)"
xcodebuild test \
  -project "$ROOT/PAXDesignLiveChat.xcodeproj" \
  -scheme "$SCHEME" \
  -configuration Debug \
  -destination "platform=iOS Simulator,id=$UDID" \
  -derivedDataPath "$DERIVED_DATA" \
  -only-testing:PAXDesignLiveChatUITests/ShellLayoutUITests \
  2>&1 | tee "$RESULTS"
STATUS=${PIPESTATUS[0]}
set -e

if [[ "$STATUS" -ne 0 ]]; then
  echo "ERROR: Shell layout UI tests failed" >&2
  rg "error:|XCTAssert|failed" "$RESULTS" | tail -40 >&2 || grep -E "error:|XCTAssert|failed" "$RESULTS" | tail -40 >&2 || true
  exit "$STATUS"
fi

echo "Shell layout verification PASSED on iOS Simulator ($SIMULATOR_NAME)"
