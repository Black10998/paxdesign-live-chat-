#!/usr/bin/env bash
# Fix "Array to string conversion" in Cookie Consent Banner (WPCode snippet).
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"
cd "$WP_PATH"

PREFIX="$(wp db prefix --skip-plugins --skip-themes 2>/dev/null || echo 'wp_')"

echo "DB prefix: ${PREFIX}"

SNIPPET_ID=""
STORAGE=""
SRC="/tmp/cookie-consent-snippet.php"
FIXED="/tmp/cookie-consent-snippet-fixed.php"
META_KEY=""

# WPCode stores snippets as the wpcode post type.
SNIPPET_ID="$(wp db query "SELECT ID FROM ${PREFIX}posts WHERE post_type = 'wpcode' AND (post_title LIKE '%Cookie%Consent%' OR post_content LIKE '%PAXConsent%') ORDER BY ID DESC LIMIT 1;" --skip-column-names 2>/dev/null | tr -d '[:space:]' || true)"

if [[ -z "$SNIPPET_ID" ]]; then
  SNIPPET_ID="$(wp db query "SELECT post_id FROM ${PREFIX}postmeta WHERE meta_key = '_wpcode_last_error' AND meta_value LIKE '%Array to string%' ORDER BY post_id DESC LIMIT 1;" --skip-column-names 2>/dev/null | tr -d '[:space:]' || true)"
fi

if [[ -z "$SNIPPET_ID" ]]; then
  echo "Listing WPCode candidates..."
  wp db query "SELECT ID, post_title, post_status FROM ${PREFIX}posts WHERE post_type = 'wpcode' AND (post_title LIKE '%Cookie%' OR post_content LIKE '%PAXConsent%' OR post_content LIKE '%pax-cookie-consent%');" 2>/dev/null || true
  echo "Cookie Consent Banner WPCode snippet not found" >&2
  exit 1
fi

echo "WPCode snippet post ID: $SNIPPET_ID"
wp post get "$SNIPPET_ID" --field=post_title 2>/dev/null || true

# WPCode keeps executable code in post meta when present.
for key in _wpcode_code code _wpcode_snippet_code snippet_code; do
  if wp post meta get "$SNIPPET_ID" "$key" >/dev/null 2>&1; then
    META_KEY="$key"
    wp post meta get "$SNIPPET_ID" "$key" > "$SRC"
    STORAGE="wpcode_meta"
    echo "Loaded code from post meta: $META_KEY"
    break
  fi
done

if [[ -z "$STORAGE" ]]; then
  wp post get "$SNIPPET_ID" --field=post_content > "$SRC"
  STORAGE="wpcode_post"
  echo "Loaded code from post_content"
fi

echo "Line count: $(wc -l < "$SRC")"
echo "=== Lines 510-535 (before fix) ==="
sed -n '510,535p' "$SRC"

cp "$SRC" "$FIXED"

php <<'PHP' "$FIXED"
<?php
$file = $argv[1] ?? '';
if ($file === '' || !is_readable($file)) {
    fwrite(STDERR, "Missing snippet file\n");
    exit(1);
}

$code = file_get_contents($file);
$original = $code;

if (strpos($code, 'function pax_consent_scalar') === false) {
    $helper = <<<'HELPER'

if (!function_exists('pax_consent_scalar')) {
    function pax_consent_scalar($value, $default = '') {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item !== null && $item !== '') {
                    return (string) $item;
                }
            }
            return (string) $default;
        }
        if ($value === null || $value === false) {
            return (string) $default;
        }
        return (string) $value;
    }
}

HELPER;

    if (preg_match('/^\s*<\?php\s*\n/', $code)) {
        $code = preg_replace('/^(\s*<\?php\s*\n)/', '$1' . $helper, $code, 1);
    } else {
        $code = "<?php\n" . $helper . ltrim($code);
    }
}

$replacements = [
    "get_query_var('pagename') ?: get_query_var('page')" =>
        "pax_consent_scalar(get_query_var('pagename')) ?: pax_consent_scalar(get_query_var('page'))",
    'get_query_var("pagename") ?: get_query_var("page")' =>
        'pax_consent_scalar(get_query_var("pagename")) ?: pax_consent_scalar(get_query_var("page"))',
    "get_query_var('pagename') ?: get_query_var('name')" =>
        "pax_consent_scalar(get_query_var('pagename')) ?: pax_consent_scalar(get_query_var('name'))",
    "get_query_var('name') ?: get_query_var('pagename')" =>
        "pax_consent_scalar(get_query_var('name')) ?: pax_consent_scalar(get_query_var('pagename'))",
    '$slug = get_query_var(\'pagename\') ?: get_query_var(\'page\');' =>
        '$slug = pax_consent_scalar(get_query_var(\'pagename\')) ?: pax_consent_scalar(get_query_var(\'page\'));',
    'esc_url( get_privacy_policy_url() )' => 'esc_url( pax_consent_scalar( get_privacy_policy_url() ) )',
    'esc_url(get_privacy_policy_url())' => 'esc_url(pax_consent_scalar(get_privacy_policy_url()))',
    'home_url( add_query_arg( $_GET' => 'home_url( add_query_arg( array_map(\'pax_consent_scalar\', (array) $_GET',
    'add_query_arg( $_GET' => 'add_query_arg( array_map(\'pax_consent_scalar\', (array) $_GET',
];

foreach ($replacements as $search => $replace) {
    $code = str_replace($search, $replace, $code);
}

$code = preg_replace(
    '/esc_(html|attr|url)\(\s*\$_GET\[(\'[^\']+\'|"[^"]+")\]\s*\)/',
    'esc_$1( pax_consent_scalar( $_GET[$2] ) )',
    $code
);
$code = preg_replace(
    '/esc_(html|attr|url)\(\s*\$_COOKIE\[(\'[^\']+\'|"[^"]+")\]\s*\)/',
    'esc_$1( pax_consent_scalar( $_COOKIE[$2] ) )',
    $code
);
$code = preg_replace(
    '/\.\s*get_query_var\(\s*([\'"])(page|pagename|name)\1\s*\)/',
    '. pax_consent_scalar(get_query_var($1$2$1))',
    $code
);
$code = preg_replace(
    '/(\$[a-zA-Z_][\w]*)\s*=\s*get_query_var\(\s*([\'"])(page|pagename|name)\2\s*\)\s*;/',
    '$1 = pax_consent_scalar(get_query_var($2$3$2));',
    $code
);

$code = preg_replace('/pax_consent_scalar\(pax_consent_scalar\(/', 'pax_consent_scalar(', $code);

$lines = preg_split("/\r\n|\n|\r/", $code);
if (isset($lines[521])) {
    echo "Line 522 after fix: {$lines[521]}\n";
}

if ($code === $original) {
    echo "Warning: no replacements matched.\n";
    if (isset($lines[521])) {
        echo "Original line 522: {$lines[521]}\n";
    }
}

file_put_contents($file, $code);
PHP

echo "=== Lines 510-535 (after fix) ==="
sed -n '510,535p' "$FIXED"

php -l "$FIXED" >/dev/null

php <<PHP
<?php
require '$WP_PATH/wp-load.php';
\$id = (int) '$SNIPPET_ID';
\$code = file_get_contents('$FIXED');
\$storage = '$STORAGE';
\$meta_key = '$META_KEY';

if (\$storage === 'wpcode_meta' && \$meta_key !== '') {
    \$updated = update_post_meta(\$id, \$meta_key, \$code);
} else {
    \$updated = wp_update_post(['ID' => \$id, 'post_content' => \$code], true);
}

if (\$updated instanceof WP_Error) {
    fwrite(STDERR, 'Update failed: ' . \$updated->get_error_message() . PHP_EOL);
    exit(1);
}

delete_post_meta(\$id, '_wpcode_last_error');
echo 'WPCode snippet updated on post ' . \$id . PHP_EOL;
PHP

wp cache flush >/dev/null 2>&1 || true
echo "Cookie Consent snippet fix deployed."
