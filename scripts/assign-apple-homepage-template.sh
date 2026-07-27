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
CUSTOM_CSS_ID="$(wp option get custom_css_post_id 2>/dev/null || true)"
if [[ -n "${CUSTOM_CSS_ID}" && "${CUSTOM_CSS_ID}" != "0" ]]; then
  wp post get "$CUSTOM_CSS_ID" --field=post_content > "$BACKUP_DIR/custom-css-before.txt" 2>/dev/null || true
  if [[ -s "$BACKUP_DIR/custom-css-before.txt" ]] && grep -q "pdx-auth-signup-btn" "$BACKUP_DIR/custom-css-before.txt"; then
    python3 - "$BACKUP_DIR/custom-css-before.txt" "$BACKUP_DIR/custom-css-after.txt" <<'PY'
import pathlib, re, sys
src, dst = map(pathlib.Path, sys.argv[1:3])
content = src.read_text(encoding='utf-8', errors='replace')
replacement = """/* Apple Sign Up pill */
.pdx-auth-signup-btn {
    background: #000 !important;
    background-color: #000 !important;
    background-image: none !important;
    color: #fff !important;
    border: 0 !important;
    box-shadow: none !important;
    border-radius: 980px !important;
}
"""
pattern = re.compile(
    r"/\*[^*]*Sign Up[^*]*\*/\s*\.pdx-auth-signup-btn\s*\{[^}]*\}",
    re.I | re.S,
)
new_content, n = pattern.subn(replacement, content)
if n == 0:
    new_content = content.rstrip() + "\n\n" + replacement
dst.write_text(new_content, encoding='utf-8')
print(f"custom css rewritten matches={n}")
PY
    wp post update "$CUSTOM_CSS_ID" "$BACKUP_DIR/custom-css-after.txt" >/dev/null
    echo "Updated Customizer CSS for Apple Sign Up button (post ${CUSTOM_CSS_ID})"
  fi
fi

echo "Assigned Apple Homepage template to page ID ${PAGE_ID}"
echo "Backup meta written to ${BACKUP_DIR}"
wp post meta get "$PAGE_ID" _wp_page_template
