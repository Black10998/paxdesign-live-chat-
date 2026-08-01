#!/usr/bin/env bash
# Fix "Array to string conversion" on line 522 in Cookie Consent Banner (WPCode).
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"
cd "$WP_PATH"

PREFIX="$(wp db prefix --skip-plugins --skip-themes 2>/dev/null || echo 'wp_')"
SNIPPET_ID="$(wp db query "SELECT ID FROM ${PREFIX}posts WHERE post_type = 'wpcode' AND (post_title LIKE '%Cookie%Consent%' OR post_content LIKE '%PAXConsent%') ORDER BY ID DESC LIMIT 1;" --skip-column-names 2>/dev/null | tr -d '[:space:]' || true)"

if [[ -z "$SNIPPET_ID" ]]; then
  SNIPPET_ID="$(wp db query "SELECT post_id FROM ${PREFIX}postmeta WHERE meta_key = '_wpcode_last_error' AND meta_value LIKE '%Array to string%' ORDER BY post_id DESC LIMIT 1;" --skip-column-names 2>/dev/null | tr -d '[:space:]' || true)"
fi

if [[ -z "$SNIPPET_ID" ]]; then
  echo "Cookie Consent Banner WPCode snippet not found" >&2
  exit 1
fi

SRC="/tmp/cookie-consent-snippet.php"
FIXED="/tmp/cookie-consent-snippet-fixed.php"

wp post get "$SNIPPET_ID" --field=post_content > "$SRC"

echo "WPCode snippet post ID: $SNIPPET_ID"
wp post get "$SNIPPET_ID" --field=post_title 2>/dev/null || true
echo "Line count: $(wc -l < "$SRC")"
echo "=== Line 522 (before fix) ==="
sed -n '522p' "$SRC"

cp "$SRC" "$FIXED"

php -r '
$file = $argv[1];
$code = file_get_contents($file);
$replacement = "\$url = is_array( \$url ) ? strtolower( (string) ( \$url[\"href\"] ?? reset( \$url ) ) ) : strtolower( (string) \$url );";
$updated = preg_replace("/\\\$url\\s*=\\s*strtolower\\(\\s*\\(\\s*string\\s*\\)\\s*\\\$url\\s*\\)\\s*;/", $replacement, $code, 1, $count);
if ($count < 1) {
    fwrite(STDERR, "Resource hint pattern not found\n");
    exit(1);
}
file_put_contents($file, $updated);
echo "Applied resource hint array guard.\n";
' "$FIXED"

echo "=== Line 522 (after fix) ==="
sed -n '522p' "$FIXED"

if cmp -s "$SRC" "$FIXED"; then
  echo "No changes applied; pattern not found." >&2
  exit 1
fi

php <<PHP
<?php
require '$WP_PATH/wp-load.php';
global \$wpdb;
\$id = (int) '$SNIPPET_ID';
\$code = file_get_contents('$FIXED');
if (\$code === false || \$code === '') {
    fwrite(STDERR, "Fixed snippet file is empty\n");
    exit(1);
}
\$updated = \$wpdb->update(
    \$wpdb->posts,
    ['post_content' => \$code],
    ['ID' => \$id],
    ['%s'],
    ['%d']
);
if (\$updated === false) {
    fwrite(STDERR, 'DB update failed: ' . \$wpdb->last_error . PHP_EOL);
    exit(1);
}
delete_post_meta(\$id, '_wpcode_last_error');
clean_post_cache(\$id);
echo 'WPCode snippet updated on post ' . \$id . ' (rows: ' . (int) \$updated . ')' . PHP_EOL;
PHP

wp cache flush >/dev/null 2>&1 || true
echo "Cookie Consent snippet fix deployed."
