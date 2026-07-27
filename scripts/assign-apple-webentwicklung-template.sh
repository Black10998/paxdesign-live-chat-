#!/usr/bin/env bash
# Assign Apple Webentwicklung template on production and park Elementor rendering.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
cd "$WP_ROOT"

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli not found" >&2
  exit 1
fi

PAGE_ID="$(wp post list --post_type=page --name=webentwicklung --field=ID --format=ids 2>/dev/null | awk '{print $1}')"
if [[ -z "${PAGE_ID}" ]]; then
  echo "Could not resolve page slug webentwicklung" >&2
  exit 1
fi

THEME="$(wp theme path --dir 2>/dev/null || true)"
BACKUP_DIR="${THEME}/.apple-app-page-backups/$(date -u +%Y%m%dT%H%M%SZ)-webentwicklung"
mkdir -p "$BACKUP_DIR"

wp post meta get "$PAGE_ID" _wp_page_template > "$BACKUP_DIR/_wp_page_template.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_edit_mode > "$BACKUP_DIR/_elementor_edit_mode.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_data > "$BACKUP_DIR/_elementor_data.json" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_template_type > "$BACKUP_DIR/_elementor_template_type.txt" 2>/dev/null || true

wp post meta update "$PAGE_ID" _wp_page_template 'template-apple-webentwicklung.php'
wp post meta delete "$PAGE_ID" _elementor_edit_mode 2>/dev/null || true
wp post meta update "$PAGE_ID" _elementor_template_type '' 2>/dev/null || true

wp rewrite flush --hard >/dev/null 2>&1 || true
wp cache flush >/dev/null 2>&1 || true

echo "Assigned Apple Webentwicklung template to page ID ${PAGE_ID}"
echo "Backup meta written to ${BACKUP_DIR}"
wp post meta get "$PAGE_ID" _wp_page_template
