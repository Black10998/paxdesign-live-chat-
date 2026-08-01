#!/usr/bin/env bash
# Fetch Cookie Consent Banner snippet from production Code Snippets table.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"

cd "$WP_PATH"

wp db query "
SELECT id, name, scope, active, priority, LENGTH(code) AS code_len
FROM wp_snippets
WHERE name LIKE '%Cookie%Consent%'
   OR code LIKE '%PAXConsent%'
   OR code LIKE '%pax-cookie-consent%'
ORDER BY id;
" 2>/dev/null || wp db query "
SELECT ID, post_title, post_status
FROM wp_posts
WHERE post_type = 'snippet'
  AND (post_title LIKE '%Cookie%Consent%' OR post_content LIKE '%PAXConsent%');
"

SNIPPET_ID="$(wp db query "SELECT id FROM wp_snippets WHERE code LIKE '%PAXConsent%' OR name LIKE '%Cookie%Consent%' LIMIT 1;" --skip-column-names 2>/dev/null | tr -d '[:space:]' || true)"

if [[ -z "$SNIPPET_ID" ]]; then
  echo "Could not find snippet id in wp_snippets" >&2
  exit 1
fi

OUT="/tmp/cookie-consent-snippet-${SNIPPET_ID}.php"
wp db query "SELECT code FROM wp_snippets WHERE id = ${SNIPPET_ID};" --skip-column-names > "$OUT"

echo "Snippet id: $SNIPPET_ID"
echo "Saved to: $OUT"
wc -l "$OUT"
sed -n '510,535p' "$OUT"
