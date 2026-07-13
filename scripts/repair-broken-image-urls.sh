#!/usr/bin/env bash
# Replace known broken image URLs in WordPress DB (Elementor, posts, options).
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
DRY_RUN="${DRY_RUN:-1}"
REPLACEMENT="${REPLACEMENT_URL:-https://paxdesign.at/wp-content/uploads/2026/06/unnamed.jpg}"

BROKEN_AVIF='https://paxdesign.at/wp-content/uploads/2025/12/38319D43-77FD-42D8-91BA-69E23BE7879C-e1767119492655.avif'

echo "=== Repair broken image URLs ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "WP_ROOT: $WP_ROOT"
echo "DRY_RUN: $DRY_RUN (set DRY_RUN=0 to apply)"
echo "Replacement: $REPLACEMENT"
echo

if ! command -v wp >/dev/null 2>&1 || [[ ! -f "$WP_ROOT/wp-config.php" ]]; then
  echo "ERROR: wp-cli and wp-config.php required"
  exit 1
fi

cd "$WP_ROOT"

run_replace() {
  local from="$1"
  local to="$2"
  echo "Replace: $from"
  echo "     -> $to"
  if [[ "$DRY_RUN" == "1" ]]; then
    wp db search "$from" --all-tables --precise --stats 2>/dev/null || echo "  (no matches)"
  else
    wp search-replace "$from" "$to" --all-tables --precise --report-changed-only
  fi
  echo
}

# Confirmed broken local team photo (served as text/plain; getimagesize fails).
run_replace "$BROKEN_AVIF" "$REPLACEMENT"

section_scan() {
  echo "--- Remaining problematic URL patterns in DB ---"
  for pattern in 'tailus.io' 'upload.wikimedia.org' '38319D43-77FD'; do
    echo "Pattern: $pattern"
    wp db search "$pattern" --all-tables --precise --stats 2>/dev/null || echo "  (none)"
  done
}

echo "--- Discovering tailus.io / wikimedia image URLs in DB ---"
DISCOVER=$(wp eval '
$patterns = array("tailus.io", "upload.wikimedia.org");
$found = array();
global $wpdb;
foreach ($patterns as $pattern) {
  $like = "%" . $wpdb->esc_like($pattern) . "%";
  $rows = $wpdb->get_results($wpdb->prepare(
    "SELECT meta_value AS val FROM {$wpdb->postmeta} WHERE meta_value LIKE %s
     UNION
     SELECT post_content AS val FROM {$wpdb->posts} WHERE post_content LIKE %s
     LIMIT 500",
    $like, $like
  ));
  foreach ($rows as $row) {
    if (preg_match_all("#https?://[^\\s\"\\'<>]+#i", $row->val, $m)) {
      foreach ($m[0] as $url) {
        if (stripos($url, $pattern) !== false && preg_match("/\\.(svg|png|jpe?g|webp|avif|gif)(\\?|$)/i", $url)) {
          $found[$url] = true;
        }
      }
    }
  }
}
echo implode("\n", array_keys($found));
' 2>/dev/null || true)

if [[ -n "$DISCOVER" ]]; then
  while IFS= read -r url; do
    [[ -z "$url" ]] && continue
    run_replace "$url" "$REPLACEMENT"
  done <<< "$DISCOVER"
else
  echo "No tailus.io / wikimedia image URLs found in postmeta/posts."
fi

section_scan

if [[ "$DRY_RUN" == "1" ]]; then
  echo "DRY RUN complete. Re-run with DRY_RUN=0 to apply changes, then: wp cache flush && wp litespeed-purge all"
else
  wp cache flush 2>/dev/null || true
  wp litespeed-purge all 2>/dev/null || true
  echo "Applied replacements and purged caches."
fi

echo "=== Repair complete ==="
