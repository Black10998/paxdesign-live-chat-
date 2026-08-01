#!/usr/bin/env bash
# Fix "Array to string conversion" in Cookie Consent Banner snippet.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"
cd "$WP_PATH"

PREFIX="$(wp db prefix --skip-plugins --skip-themes 2>/dev/null || echo 'wp_')"
SNIPPETS_TABLE="${PREFIX}snippets"

echo "DB prefix: ${PREFIX}"
echo "Snippets table: ${SNIPPETS_TABLE}"
wp db query "SHOW TABLES LIKE '%snippet%';" 2>/dev/null || true

SNIPPET_ID=""
STORAGE=""
SRC="/tmp/cookie-consent-snippet.php"
FIXED="/tmp/cookie-consent-snippet-fixed.php"

if wp db query "SELECT 1 FROM ${SNIPPETS_TABLE} LIMIT 1;" --skip-column-names >/dev/null 2>&1; then
  SNIPPET_ID="$(wp db query "SELECT id FROM ${SNIPPETS_TABLE} WHERE code LIKE '%PAXConsent%' OR name LIKE '%Cookie%Consent%' ORDER BY id LIMIT 1;" --skip-column-names 2>/dev/null | tr -d '[:space:]' || true)"
  STORAGE="table"
fi

if [[ -z "$SNIPPET_ID" ]]; then
  SNIPPET_ID="$(wp db query "SELECT ID FROM ${PREFIX}posts WHERE post_type = 'snippet' AND (post_title LIKE '%Cookie%Consent%' OR post_content LIKE '%PAXConsent%') ORDER BY ID LIMIT 1;" --skip-column-names 2>/dev/null | tr -d '[:space:]' || true)"
  [[ -n "$SNIPPET_ID" ]] && STORAGE="post"
fi

if [[ -z "$SNIPPET_ID" ]]; then
  echo "Searching database content for PAXConsent..."
  wp db query "SELECT option_name FROM ${PREFIX}options WHERE option_value LIKE '%PAXConsent%' LIMIT 10;" 2>/dev/null || true
  wp db query "SELECT post_id, meta_key FROM ${PREFIX}postmeta WHERE meta_value LIKE '%PAXConsent%' LIMIT 10;" 2>/dev/null || true
fi

FILE_PATH=""
if [[ -z "$SNIPPET_ID" ]]; then
  echo "Searching filesystem for Cookie Consent Banner source..."
  while IFS= read -r candidate; do
    if grep -q "PAXConsent" "$candidate" 2>/dev/null; then
      FILE_PATH="$candidate"
      STORAGE="file"
      break
    fi
  done < <(find "$WP_PATH/wp-content" -type f \( -name '*.php' -o -name '*.inc' \) 2>/dev/null | head -5000)
fi

if [[ -z "$SNIPPET_ID" && -z "$FILE_PATH" ]]; then
  echo "Broad grep for PAXConsent under wp-content..."
  grep -Rsl "PAXConsent" "$WP_PATH/wp-content" --include='*.php' 2>/dev/null | head -20 || true
  FILE_PATH="$(grep -Rsl "PAXConsent" "$WP_PATH/wp-content" --include='*.php' 2>/dev/null | head -1 || true)"
  [[ -n "$FILE_PATH" ]] && STORAGE="file"
fi

if [[ -z "$SNIPPET_ID" && -z "$FILE_PATH" ]]; then
  echo "Cookie Consent Banner source not found" >&2
  exit 1
fi

if [[ "$STORAGE" == "file" ]]; then
  cp "$FILE_PATH" "$SRC"
  echo "Snippet file: $FILE_PATH"
elif [[ "$STORAGE" == "post" ]]; then
  wp db query "SELECT post_content FROM ${PREFIX}posts WHERE ID = ${SNIPPET_ID};" --skip-column-names > "$SRC"
  echo "Snippet post ID: $SNIPPET_ID"
else
  wp db query "SELECT code FROM ${SNIPPETS_TABLE} WHERE id = ${SNIPPET_ID};" --skip-column-names > "$SRC"
  echo "Snippet table ID: $SNIPPET_ID"
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

if [[ "$STORAGE" == "file" ]]; then
  install -m 644 "$FIXED" "$FILE_PATH"
  echo "Updated file: $FILE_PATH"
else
  php <<PHP
<?php
require '$WP_PATH/wp-load.php';
global \$wpdb;
\$id = (int) '${SNIPPET_ID:-0}';
\$code = file_get_contents('$FIXED');
\$storage = '$STORAGE';
if (\$storage === 'post') {
    \$updated = \$wpdb->update(\$wpdb->posts, ['post_content' => \$code], ['ID' => \$id], ['%s'], ['%d']);
} else {
    \$table = \$wpdb->prefix . 'snippets';
    \$updated = \$wpdb->update(\$table, ['code' => \$code], ['id' => \$id], ['%s'], ['%d']);
}
if (\$updated === false) {
    fwrite(STDERR, "DB update failed: " . \$wpdb->last_error . PHP_EOL);
    exit(1);
}
echo "Updated snippet row(s): " . (int) \$updated . PHP_EOL;
PHP
fi

wp cache flush >/dev/null 2>&1 || true
echo "Cookie Consent snippet fix deployed."
