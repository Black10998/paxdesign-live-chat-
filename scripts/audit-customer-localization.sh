#!/usr/bin/env bash
# Fail if CustomerPortal contains obvious hardcoded user-facing strings.
set -euo pipefail

ROOT="paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal"
FAIL=0

patterns=(
  'Text\("[A-Za-z][^"]*"\)'
  'navigationTitle\("[A-Za-z][^"]*"\)'
  'Button\("[A-Za-z][^"]*"\)'
  'Label\("[A-Za-z][^"]*"\)'
  'placeholder:\s*"[A-Za-z][^"]*"'
)

echo "Customer portal localization audit"
for pattern in "${patterns[@]}"; do
  while IFS= read -r line; do
    file="${line%%:*}"
    # Allow numeric-only interpolation like Text("\(count)")
    if echo "$line" | rg -q 'Text\("\\\(' ; then
      continue
    fi
    echo "HARDCODED: $line"
    FAIL=1
  done < <(rg -n "$pattern" "$ROOT" || true)
done

if [[ "$FAIL" -ne 0 ]]; then
  echo "Localization audit failed."
  exit 1
fi
echo "No hardcoded CustomerPortal UI strings detected."
