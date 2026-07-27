#!/usr/bin/env bash
# Assign Apple Homepage template on production and park Elementor front render.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
cd "$WP_ROOT"

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli not found" >&2
  exit 1
fi

PAGE_ID="$(wp option get page_on_front 2>/dev/null || true)"
if [[ -z "${PAGE_ID}" || "${PAGE_ID}" == "0" ]]; then
  PAGE_ID="$(wp post list --post_type=page --pagename=home --field=ID --format=ids 2>/dev/null | awk '{print $1}')"
fi
if [[ -z "${PAGE_ID}" ]]; then
  PAGE_ID=2071
fi

THEME="$(wp theme path --dir 2>/dev/null || true)"
BACKUP_DIR="${THEME}/.apple-app-page-backups/$(date -u +%Y%m%dT%H%M%SZ)-homepage-complete"
mkdir -p "$BACKUP_DIR"

wp post meta get "$PAGE_ID" _wp_page_template > "$BACKUP_DIR/_wp_page_template.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_edit_mode > "$BACKUP_DIR/_elementor_edit_mode.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_data > "$BACKUP_DIR/_elementor_data.json" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_template_type > "$BACKUP_DIR/_elementor_template_type.txt" 2>/dev/null || true

wp post meta update "$PAGE_ID" _wp_page_template 'template-apple-homepage.php'
wp post meta delete "$PAGE_ID" _elementor_edit_mode 2>/dev/null || true
wp post meta update "$PAGE_ID" _elementor_template_type '' 2>/dev/null || true

wp rewrite flush --hard >/dev/null 2>&1 || true
wp cache flush >/dev/null 2>&1 || true

# Soften broken Customizer CSS that forced a transparent Sign Up button.
REWRITE_SCRIPT=""
for candidate in \
  "${THEME}/scripts/rewrite-apple-signup-custom-css.php" \
  "${WP_ROOT}/scripts/rewrite-apple-signup-custom-css.php" \
  "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/rewrite-apple-signup-custom-css.php"
do
  if [[ -f "$candidate" ]]; then
    REWRITE_SCRIPT="$candidate"
    break
  fi
done

if [[ -n "$REWRITE_SCRIPT" ]]; then
  wp eval-file "$REWRITE_SCRIPT" "$BACKUP_DIR" || echo "Customizer CSS rewrite skipped/failed"
else
  echo "rewrite-apple-signup-custom-css.php not found; skipping Customizer CSS rewrite"
fi

echo "Assigned Apple Homepage template to page ID ${PAGE_ID}"
echo "Backup meta written to ${BACKUP_DIR}"
wp post meta get "$PAGE_ID" _wp_page_template
