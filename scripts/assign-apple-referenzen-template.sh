#!/usr/bin/env bash
# Assign Apple Referenzen template on production and stop Elementor from owning the page.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
cd "$WP_ROOT"

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli not found" >&2
  exit 1
fi

PAGE_ID="$(wp post list --post_type=page --name=referenzen --field=ID --format=ids 2>/dev/null | awk '{print $1}')"
if [[ -z "${PAGE_ID}" ]]; then
  PAGE_ID="$(wp post list --post_type=page --post__in=791 --field=ID --format=ids 2>/dev/null | awk '{print $1}')"
fi
if [[ -z "${PAGE_ID}" ]]; then
  echo "Referenzen page not found" >&2
  exit 1
fi

THEME="$(wp theme path --dir 2>/dev/null || true)"
BACKUP_DIR="${THEME}/.apple-referenzen-backups/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

wp post meta get "$PAGE_ID" _wp_page_template > "$BACKUP_DIR/_wp_page_template.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_edit_mode > "$BACKUP_DIR/_elementor_edit_mode.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_template_type > "$BACKUP_DIR/_elementor_template_type.txt" 2>/dev/null || true

wp post meta update "$PAGE_ID" _wp_page_template 'template-apple-referenzen.php'
wp post meta delete "$PAGE_ID" _elementor_edit_mode 2>/dev/null || true
wp post meta update "$PAGE_ID" _elementor_template_type '' 2>/dev/null || true

wp rewrite flush --hard >/dev/null 2>&1 || true
wp cache flush >/dev/null 2>&1 || true

echo "Assigned Apple Referenzen template to page ID ${PAGE_ID}"
echo "Backup meta written to ${BACKUP_DIR}"
wp post meta get "$PAGE_ID" _wp_page_template
