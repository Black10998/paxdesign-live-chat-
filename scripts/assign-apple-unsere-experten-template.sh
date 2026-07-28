#!/usr/bin/env bash
# Assign Apple Unsere Experten template on production and park Elementor rendering.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
cd "$WP_ROOT"

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli not found" >&2
  exit 1
fi

PAGE_ID="$(wp post list --post_type=page --name=unsere-experten --field=ID --format=ids 2>/dev/null | awk '{print $1}')"
if [[ -z "${PAGE_ID}" ]]; then
  PAGE_ID=2818
fi

THEME="$(wp theme path --dir 2>/dev/null || true)"
BACKUP_DIR="${THEME}/.apple-experts-backups/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

wp post meta get "$PAGE_ID" _wp_page_template > "$BACKUP_DIR/_wp_page_template.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_edit_mode > "$BACKUP_DIR/_elementor_edit_mode.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_data > "$BACKUP_DIR/_elementor_data.json" 2>/dev/null || true
wp post meta get "$PAGE_ID" _elementor_template_type > "$BACKUP_DIR/_elementor_template_type.txt" 2>/dev/null || true
wp post meta get "$PAGE_ID" _thumbnail_id > "$BACKUP_DIR/_thumbnail_id.txt" 2>/dev/null || true

wp post meta update "$PAGE_ID" _wp_page_template 'template-apple-unsere-experten.php'
wp post meta delete "$PAGE_ID" _elementor_edit_mode 2>/dev/null || true
wp post meta update "$PAGE_ID" _elementor_template_type '' 2>/dev/null || true

PORTRAIT_URL='https://paxdesign.at/wp-content/uploads/2025/12/38319D43-77FD-42D8-91BA-69E23BE7879C-e1767119492655.avif'
DESC='Ahmad Al-Khalaf, Gründer und Webentwickler bei PAXdesign. Moderne Webentwicklung, Performance und klare digitale Systeme.'
EXCERPT='Die Expertise hinter PAXdesign: Ahmad Al-Khalaf.'

wp post update "$PAGE_ID" --post_excerpt="$EXCERPT" >/dev/null

ATTACH_ID="$(wp post list --post_type=attachment --field=ID --format=ids --s='38319D43-77FD-42D8-91BA-69E23BE7879C' 2>/dev/null | awk '{print $1}')"
if [[ -n "${ATTACH_ID}" ]]; then
  wp post meta update "$PAGE_ID" _thumbnail_id "$ATTACH_ID" >/dev/null
else
  wp post meta delete "$PAGE_ID" _thumbnail_id 2>/dev/null || true
fi

for key in \
  _yoast_wpseo_opengraph-image \
  _yoast_wpseo_twitter-image \
  _yoast_wpseo_metadesc \
  _yoast_wpseo_opengraph-description \
  _yoast_wpseo_twitter-description \
  rank_math_facebook_image \
  rank_math_twitter_image \
  rank_math_description \
  rank_math_facebook_description \
  rank_math_twitter_description \
  _seopress_social_fb_img \
  _seopress_social_twitter_img \
  _seopress_titles_desc
do
  case "$key" in
    *desc*|*description*)
      wp post meta update "$PAGE_ID" "$key" "$DESC" >/dev/null 2>&1 || true
      ;;
    *)
      wp post meta update "$PAGE_ID" "$key" "$PORTRAIT_URL" >/dev/null 2>&1 || true
      ;;
  esac
done

export PAX_EXPERTEN_PAGE_ID="$PAGE_ID"
export PAX_EXPERTEN_PORTRAIT_URL="$PORTRAIT_URL"
wp eval '
$page_id = (int) getenv("PAX_EXPERTEN_PAGE_ID");
$portrait = (string) getenv("PAX_EXPERTEN_PORTRAIT_URL");
if ($page_id < 1) {
  echo "Missing page id\n";
  return;
}
$data = get_post_meta($page_id, "_elementor_data", true);
if (!is_string($data) || $data === "") {
  echo "No Elementor data to sanitize\n";
  return;
}
$orig = $data;
$data = preg_replace("~https://images\\.unsplash\\.com/[^\"\\\\]+~i", $portrait, $data);
$data = str_ireplace(array("Sophia Hartmann", "Lena Mayer"), array("", ""), $data);
if ($data !== $orig) {
  update_post_meta($page_id, "_elementor_data", wp_slash($data));
  echo "Sanitized Elementor data for page {$page_id}\n";
} else {
  echo "Elementor data unchanged\n";
}
'

wp rewrite flush --hard >/dev/null 2>&1 || true
wp cache flush >/dev/null 2>&1 || true

echo "Assigned Apple Unsere Experten template to page ID ${PAGE_ID}"
echo "Backup meta written to ${BACKUP_DIR}"
wp post meta get "$PAGE_ID" _wp_page_template
