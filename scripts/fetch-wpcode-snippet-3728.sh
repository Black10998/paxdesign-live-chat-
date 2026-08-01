#!/usr/bin/env bash
set -euo pipefail
WP_PATH="${WP_PATH:?WP_PATH required}"
cd "$WP_PATH"
OUT="${1:-/tmp/cookie-consent-snippet-3728.php}"
wp post get 3728 --field=post_content > "$OUT"
echo "Fetched $(wc -l < "$OUT") lines to $OUT"
sed -n '518,526p' "$OUT"
