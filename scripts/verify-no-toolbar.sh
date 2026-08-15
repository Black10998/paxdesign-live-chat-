#!/usr/bin/env bash
# Fail CI/release if paxdesign-toolbar references reappear in this repository.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PATTERNS=(
  'paxdesign-toolbar'
  'paxdesign_toolbar'
  'PDXDock'
  'pdx-dock'
  '#pdx-root'
  'Black10998/paxdesign-toolbar'
)

EXCLUDES=(
  '--glob=!production-plugin/**'
  '--glob=!.gitignore'
  '--glob=!docs/**'
  '--glob=!scripts/DOCK-*'
  '--glob=!scripts/IOS-*'
  '--glob=!scripts/verify-no-toolbar.*'
  '--glob=!scripts/wp-uninstall-toolbar.php'
  '--glob=!paxdesign-booking/scripts/wp-uninstall-toolbar.php'
  '--glob=!.cursor/rules/no-paxdesign-toolbar.mdc'
  '--glob=!.github/workflows/**'
)

FAIL=0
for pattern in "${PATTERNS[@]}"; do
  if rg -n -i "${EXCLUDES[@]}" "$pattern" . >/tmp/pdx-toolbar-hit.txt 2>/dev/null; then
    echo "ERROR: forbidden toolbar reference ($pattern):" >&2
    cat /tmp/pdx-toolbar-hit.txt >&2
    FAIL=1
  fi
done

if [[ "$FAIL" -ne 0 ]]; then
  echo "Toolbar guard failed. paxdesign-toolbar must not exist in this repository." >&2
  exit 1
fi

echo "OK: no paxdesign-toolbar references in repository."
