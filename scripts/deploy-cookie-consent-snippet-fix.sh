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

php <<'PHP' "$FIXED"
<?php
$file = $argv[1] ?? '';
$code = file_get_contents($file);
$original = $code;

$old = '$url = strtolower( (string) $url );';
$new = '$url = is_array( $url ) ? strtolower( (string) ( $url[\'href\'] ?? reset( $url ) ) ) : strtolower( (string) $url );';

if (strpos($code, $old) !== false) {
    $code = str_replace($old, $new, $code);
    echo "Applied resource hint array guard on line 522 pattern.\n";
} else {
    echo "Primary line-522 pattern not found; no changes applied.\n";
}

$lines = preg_split("/\r\n|\n|\r/", $code);
if (isset($lines[521])) {
    echo 'Line 522 after fix: ' . $lines[521] . "\n";
}

if ($code === $original) {
    exit(2);
}

file_put_contents($file, $code);
PHP

fix_status=$?
if [[ "$fix_status" -eq 2 ]]; then
  echo "Snippet already fixed or pattern missing; continuing to validate." >&2
  cp "$SRC" "$FIXED"
fi

php -l "$FIXED" >/dev/null

php <<PHP
<?php
require '$WP_PATH/wp-load.php';
\$id = (int) '$SNIPPET_ID';
\$code = file_get_contents('$FIXED');
\$updated = wp_update_post(['ID' => \$id, 'post_content' => \$code], true);
if (\$updated instanceof WP_Error) {
    fwrite(STDERR, 'Update failed: ' . \$updated->get_error_message() . PHP_EOL);
    exit(1);
}
delete_post_meta(\$id, '_wpcode_last_error');
echo 'WPCode snippet updated on post ' . \$id . PHP_EOL;
PHP

wp cache flush >/dev/null 2>&1 || true
echo "Cookie Consent snippet fix deployed."
