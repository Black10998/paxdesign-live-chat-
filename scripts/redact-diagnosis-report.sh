#!/usr/bin/env bash
# Redact sensitive values from diagnosis report before publishing as CI artifact.
set -euo pipefail

IN="${1:?input report path}"
OUT="${2:?output report path}"

cp "$IN" "$OUT"

# Authorization / Basic auth
sed -i -E \
  -e 's/(Authorization:[[:space:]]*Basic[[:space:]]+)[A-Za-z0-9+\/=]+/\1[REDACTED]/gi' \
  -e 's/(Authorization:[[:space:]]*Bearer[[:space:]]+)[A-Za-z0-9._\-]+/\1[REDACTED]/gi' \
  -e 's/(PHP_AUTH_PW[[:space:]]*=[[:space:]]*)[^[:space:]]+/\1[REDACTED]/g' \
  -e 's/(password[[:space:]]*[=:][[:space:]]*)[^[:space:]]+/\1[REDACTED]/gi' \
  -e 's/(passwd[[:space:]]*[=:][[:space:]]*)[^[:space:]]+/\1[REDACTED]/gi' \
  -e 's/(-----BEGIN[^-]+-----)[[:space:]]+(-----END)/\1 [REDACTED KEY] \2/g' \
  -e 's/(wordpress_logged_in_[^=;[:space:]]+)[^;[:space:]]*/\1[REDACTED]/g' \
  -e 's/(wordpress_[^=;[:space:]]*cookie[^=;[:space:]]*)[^;[:space:]]*/\1[REDACTED]/gi' \
  -e 's/(_wpnonce[[:space:]]*[=:][[:space:]]*)[^[:space:]"&]+/\1[REDACTED]/gi' \
  -e 's/([A-Za-z0-9._%+-]+)@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/[REDACTED_EMAIL]/g' \
  -e 's/(AuthUserFile[[:space:]]+)[^[:space:]]+/\1[REDACTED_PATH]/g' \
  "$OUT" 2>/dev/null || \
sed -E \
  -e 's/(Authorization:[[:space:]]*Basic[[:space:]]+)[A-Za-z0-9+\/=]+/\1[REDACTED]/gi' \
  -e 's/(Authorization:[[:space:]]*Bearer[[:space:]]+)[A-Za-z0-9._\-]+/\1[REDACTED]/gi' \
  -e 's/([A-Za-z0-9._%+-]+)@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/[REDACTED_EMAIL]/g' \
  "$IN" > "$OUT"

echo "Redacted report written: $OUT"
