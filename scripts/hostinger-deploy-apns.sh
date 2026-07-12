#!/usr/bin/env bash
# Deploy PAXdesign Booking v3.108.6+ and configure APNs on Hostinger production.
# Run from Hostinger hPanel → Advanced → SSH Terminal (WordPress root = public_html).
set -euo pipefail

RELEASE="${PAX_RELEASE_VERSION:-3.108.6}"
TEAM_ID="${PAX_APNS_TEAM_ID:-4ZSP8S5A7B}"
BUNDLE_ID="${PAX_APNS_BUNDLE_ID:-at.paxdesign.livechat}"
ZIP_URL="https://github.com/Black10998/paxdesign-live-chat-/releases/download/v${RELEASE}/paxdesign-booking-v${RELEASE}.zip"

if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: wp-cli not found. Enable SSH on Hostinger and run from the WordPress root (public_html)." >&2
  exit 1
fi

WP_ROOT="$(wp eval 'echo ABSPATH;' 2>/dev/null | tr -d '\r\n')"
if [[ -z "$WP_ROOT" || ! -d "$WP_ROOT/wp-content/plugins" ]]; then
  echo "ERROR: Could not resolve WordPress root. cd to public_html first." >&2
  exit 1
fi

echo "==> Downloading plugin v${RELEASE}"
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
curl -fsSL "$ZIP_URL" -o "$tmpdir/paxdesign-booking.zip"
unzip -q "$tmpdir/paxdesign-booking.zip" -d "$tmpdir"

echo "==> Installing plugin files"
rsync -a --delete "$tmpdir/paxdesign-booking/" "$WP_ROOT/wp-content/plugins/paxdesign-booking/"
wp plugin activate paxdesign-booking
wp cache flush

if [[ -n "${PAX_APNS_KEY_ID:-}" && -n "${PAX_APNS_KEY_P8:-}" ]]; then
  echo "==> Configuring APNs credentials"
  wp option update paxdesign_apns_key_id "$PAX_APNS_KEY_ID"
  wp option update paxdesign_apns_team_id "$TEAM_ID"
  wp option update paxdesign_apns_bundle_id "$BUNDLE_ID"
  wp option update paxdesign_apns_key_p8 "$PAX_APNS_KEY_P8"
fi

if [[ -f "$WP_ROOT/wp-content/plugins/paxdesign-booking/scripts/setup-production-apns.php" ]]; then
  echo "==> Verifying APNs configuration"
  wp eval-file wp-content/plugins/paxdesign-booking/scripts/setup-production-apns.php || true
fi

installed="$(grep "define('PAXDESIGN_BOOKING_VERSION'" "$WP_ROOT/wp-content/plugins/paxdesign-booking/paxdesign-booking.php | sed "s/.*'\([^']*\)'.*/\1/")"
echo "==> Done. Installed plugin version: ${installed}"
