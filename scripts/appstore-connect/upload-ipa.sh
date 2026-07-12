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
KEY_DIR="${HOME}/.appstoreconnect/private_keys"
KEY_FILE="$KEY_DIR/AuthKey_${KEY_ID}.p8"

if [[ -z "$ISSUER_ID" || -z "$KEY_ID" ]]; then
  echo "ERROR: Missing APP_STORE_CONNECT_ISSUER_ID or APP_STORE_CONNECT_API_KEY_ID." >&2
  exit 1
fi

if [[ -f "$KEY_FILE" ]]; then
  echo "Using prepared App Store Connect API key file"
elif [[ -n "${APP_STORE_CONNECT_API_KEY_P8_BASE64:-}" ]]; then
  PRIVATE_KEY="$(printf '%s' "$APP_STORE_CONNECT_API_KEY_P8_BASE64" | tr -d '[:space:]' | base64 -D 2>/dev/null || true)"
  if ! printf '%s' "$PRIVATE_KEY" | openssl pkey -noout >/dev/null 2>&1; then
    echo "ERROR: APP_STORE_CONNECT_API_KEY_P8_BASE64 is not a valid PKCS8 private key." >&2
    exit 1
  fi
  mkdir -p "$KEY_DIR"
  printf '%s' "$PRIVATE_KEY" > "$KEY_FILE"
  chmod 600 "$KEY_FILE"
else
  PRIVATE_KEY="${APP_STORE_CONNECT_API_PRIVATE_KEY:-${APPSTORE_API_PRIVATE_KEY:-}}"
  if [[ "$PRIVATE_KEY" == *"\\n"* ]]; then
    PRIVATE_KEY="${PRIVATE_KEY//\\n/$'\n'}"
  fi
  PRIVATE_KEY="${PRIVATE_KEY//$'\r'/}"
  PRIVATE_KEY="${PRIVATE_KEY#\"}"
  PRIVATE_KEY="${PRIVATE_KEY%\"}"
  if ! printf '%s' "$PRIVATE_KEY" | openssl pkey -noout >/dev/null 2>&1; then
    echo "ERROR: No valid App Store Connect API private key found." >&2
    exit 1
  fi
  mkdir -p "$KEY_DIR"
  printf '%s' "$PRIVATE_KEY" > "$KEY_FILE"
  chmod 600 "$KEY_FILE"
fi

echo "Uploading IPA to App Store Connect: $(basename "$IPA_PATH")"
xcrun altool --upload-app \
  -f "$IPA_PATH" \
  -t ios \
  --apiKey "$KEY_ID" \
  --apiIssuer "$ISSUER_ID"

echo "Upload submitted to App Store Connect"
