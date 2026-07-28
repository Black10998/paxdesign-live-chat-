#!/usr/bin/env bash
# Update homepage Elementor HTML widgets with Apple-redesigned blocks.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
cd "$WP_ROOT"

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli not found" >&2
  exit 1
fi

PAGE_ID="$(wp post list --post_type=page --pagename=home --field=ID --format=ids 2>/dev/null | awk '{print $1}')"
if [[ -z "${PAGE_ID}" ]]; then
  PAGE_ID="$(wp post list --post_type=page --name=home --field=ID --format=ids 2>/dev/null | awk '{print $1}')"
fi
if [[ -z "${PAGE_ID}" ]]; then
  # Front page from Reading settings
  PAGE_ID="$(wp option get page_on_front 2>/dev/null || true)"
fi
if [[ -z "${PAGE_ID}" || "${PAGE_ID}" == "0" ]]; then
  PAGE_ID=2071
fi

THEME="$(wp theme path --dir 2>/dev/null || true)"
BLOCKS_DIR="${THEME}/scripts/homepage-apple-html-blocks"
if [[ ! -d "${BLOCKS_DIR}" ]]; then
  # Fallback when script is executed from theme/scripts with adjacent blocks
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  BLOCKS_DIR="${SCRIPT_DIR}/homepage-apple-html-blocks"
fi

if [[ ! -d "${BLOCKS_DIR}" ]]; then
  echo "Blocks directory not found: ${BLOCKS_DIR}" >&2
  exit 1
fi

BACKUP_DIR="${THEME}/.apple-app-page-backups/$(date -u +%Y%m%dT%H%M%SZ)-homepage-html"
mkdir -p "$BACKUP_DIR"
wp post meta get "$PAGE_ID" _elementor_data > "$BACKUP_DIR/_elementor_data.json" 2>/dev/null || true

export PAX_HOME_PAGE_ID="$PAGE_ID"
export PAX_HOME_BLOCKS_DIR="$BLOCKS_DIR"

wp eval-file "${BLOCKS_DIR}/../update-homepage-apple-html-blocks.php"

wp elementor flush-css >/dev/null 2>&1 || true
wp rewrite flush --hard >/dev/null 2>&1 || true
wp cache flush >/dev/null 2>&1 || true

echo "Updated homepage HTML blocks on page ID ${PAGE_ID}"
echo "Backup written to ${BACKUP_DIR}"
