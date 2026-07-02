#!/usr/bin/env bash
# Validates the permanent WordPress auto-update release contract.
# Run before every production tag push and on main-branch plugin changes.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN="$ROOT/paxdesign-booking/paxdesign-booking.php"
UPDATE_CHECKER="$ROOT/paxdesign-booking/includes/class-paxdesign-update-checker.php"
BUILD_SCRIPT="$ROOT/scripts/build-release.sh"
RELEASE_WORKFLOW="$ROOT/.github/workflows/release.yml"

if [[ ! -f "$PLUGIN" ]]; then
  echo "ERROR: Missing plugin bootstrap: $PLUGIN"
  exit 1
fi

HEADER_VERSION="$(grep -m1 '^Version:' "$PLUGIN" | sed 's/^Version:[[:space:]]*//')"
DEFINE_VERSION="$(grep "define('PAXDESIGN_BOOKING_VERSION'" "$PLUGIN" | sed "s/.*'\([^']*\)'.*/\1/")"

if [[ -z "$HEADER_VERSION" || -z "$DEFINE_VERSION" ]]; then
  echo "ERROR: Could not read plugin version from $PLUGIN"
  exit 1
fi

if [[ "$HEADER_VERSION" != "$DEFINE_VERSION" ]]; then
  echo "ERROR: Plugin header Version ($HEADER_VERSION) must equal PAXDESIGN_BOOKING_VERSION ($DEFINE_VERSION)"
  exit 1
fi

if [[ ! -f "$UPDATE_CHECKER" ]]; then
  echo "ERROR: Missing update checker: $UPDATE_CHECKER"
  exit 1
fi

if ! grep -q "require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-update-checker.php';" "$PLUGIN"; then
  echo "ERROR: Update checker is not required in paxdesign-booking.php"
  exit 1
fi

if ! grep -q 'PAXdesign_Booking_Update_Checker::init();' "$PLUGIN"; then
  echo "ERROR: PAXdesign_Booking_Update_Checker::init() must be called in paxdesign-booking.php"
  exit 1
fi

for constant in GITHUB_REPO SLUG CACHE_KEY; do
  if ! grep -q "const $constant" "$UPDATE_CHECKER"; then
    echo "ERROR: Update checker missing required constant: $constant"
    exit 1
  fi
done

if ! grep -q "pre_set_site_transient_update_plugins" "$UPDATE_CHECKER"; then
  echo "ERROR: Update checker must hook pre_set_site_transient_update_plugins"
  exit 1
fi

if ! grep -q "paxdesign-booking-v" "$BUILD_SCRIPT"; then
  echo "ERROR: build-release.sh must produce paxdesign-booking-v{version}.zip"
  exit 1
fi

if ! grep -q 'paxdesign-booking-v\${{ steps.version.outputs.version }}.zip' "$RELEASE_WORKFLOW"; then
  echo "ERROR: release.yml must attach paxdesign-booking-v{version}.zip"
  exit 1
fi

TAG="${1:-}"
if [[ -n "$TAG" ]]; then
  EXPECTED_TAG="v${DEFINE_VERSION}"
  if [[ "$TAG" != "$EXPECTED_TAG" ]]; then
    echo "ERROR: Git tag ($TAG) must match plugin version ($EXPECTED_TAG)"
    exit 1
  fi
fi

echo "Release contract OK (plugin v${DEFINE_VERSION})"
