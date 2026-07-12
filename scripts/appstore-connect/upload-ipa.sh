#!/usr/bin/env bash
# Upload a signed IPA to App Store Connect / TestFlight using API key auth.
set -euo pipefail
set +x

IPA_PATH="${1:-}"
if [[ -z "$IPA_PATH" || ! -f "$IPA_PATH" ]]; then
  echo "Usage: upload-ipa.sh <path/to/app.ipa>" >&2
  exit 1
fi

ISSUER_ID="${APP_STORE_CONNECT_ISSUER_ID:-${APPSTORE_ISSUER_ID:-}}"
KEY_ID="${APP_STORE_CONNECT_API_KEY_ID:-${APPSTORE_API_KEY_ID:-}}"
PRIVATE_KEY="${APP_STORE_CONNECT_API_PRIVATE_KEY:-${APPSTORE_API_PRIVATE_KEY:-}}"

if [[ -z "$PRIVATE_KEY" && -n "${APP_STORE_CONNECT_API_KEY_P8_BASE64:-}" ]]; then
  PRIVATE_KEY="$(printf '%s' "$APP_STORE_CONNECT_API_KEY_P8_BASE64" | tr -d '[:space:]' | base64 -D 2>/dev/null || true)"
fi

if [[ -z "$ISSUER_ID" || -z "$KEY_ID" || -z "$PRIVATE_KEY" ]]; then
  echo "ERROR: Missing App Store Connect API secrets." >&2
  echo "Required one of each:" >&2
  echo "  APP_STORE_CONNECT_ISSUER_ID or APPSTORE_ISSUER_ID" >&2
  echo "  APP_STORE_CONNECT_API_KEY_ID or APPSTORE_API_KEY_ID" >&2
  echo "  APP_STORE_CONNECT_API_PRIVATE_KEY or APPSTORE_API_PRIVATE_KEY or APP_STORE_CONNECT_API_KEY_P8_BASE64" >&2
  exit 1
fi

if [[ "$PRIVATE_KEY" == *"\\n"* ]]; then
  PRIVATE_KEY="${PRIVATE_KEY//\\n/$'\n'}"
fi
PRIVATE_KEY="${PRIVATE_KEY//$'\r'/}"
PRIVATE_KEY="${PRIVATE_KEY#\"}"
PRIVATE_KEY="${PRIVATE_KEY%\"}"

if ! printf '%s' "$PRIVATE_KEY" | openssl pkey -noout >/dev/null 2>&1; then
  echo "ERROR: APP_STORE_CONNECT_API_PRIVATE_KEY is not a valid PKCS8 private key." >&2
  echo "Ensure the GitHub secret contains the full .p8 file, including BEGIN/END lines." >&2
  exit 1
fi

KEY_DIR="${HOME}/.appstoreconnect/private_keys"
mkdir -p "$KEY_DIR"
KEY_FILE="$KEY_DIR/AuthKey_${KEY_ID}.p8"
printf '%s' "$PRIVATE_KEY" > "$KEY_FILE"
chmod 600 "$KEY_FILE"

echo "Uploading IPA to App Store Connect: $(basename "$IPA_PATH")"
xcrun altool --upload-app \
  -f "$IPA_PATH" \
  -t ios \
  --apiKey "$KEY_ID" \
  --apiIssuer "$ISSUER_ID"

echo "Upload submitted to App Store Connect"
