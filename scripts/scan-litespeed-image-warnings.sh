#!/usr/bin/env bash
# Scan debug.log and WordPress DB for LiteSpeed getimagesize() problem URLs.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
DEBUG_LOG="$WP_ROOT/wp-content/debug.log"
REPORT="${REPORT_PATH:-$WP_ROOT/wp-content/pax-litespeed-image-scan-$(date +%Y%m%d-%H%M%S).txt}"

exec > >(tee "$REPORT") 2>&1

echo "=== LiteSpeed getimagesize() image URL scan ==="
echo "Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "WP_ROOT: $WP_ROOT"
echo "Report: $REPORT"
echo

section() { echo; echo "--- $* ---"; }

section "debug.log — getimagesize warnings (last 500 matching lines)"
if [[ -f "$DEBUG_LOG" ]]; then
  LOG_BYTES=$(wc -c < "$DEBUG_LOG" | tr -d ' ')
  LOG_LINES=$(wc -l < "$DEBUG_LOG" | tr -d ' ')
  echo "debug.log: ${LOG_BYTES} bytes, ${LOG_LINES} lines"
  GETIMG_COUNT=$(grep -c 'getimagesize(' "$DEBUG_LOG" 2>/dev/null || echo 0)
  echo "Total getimagesize() mentions: $GETIMG_COUNT"
  grep 'getimagesize(' "$DEBUG_LOG" 2>/dev/null | tail -n 500 | sed -E 's/.*getimagesize\(([^)]+)\).*/\1/' | sort | uniq -c | sort -rn | head -30 || echo "(none)"
else
  echo "debug.log not found at $DEBUG_LOG"
fi

section "Known problematic URL fragments in debug.log"
for frag in tailus.io upload.wikimedia.org wikimedia 38319D43-77FD LEGACY_BROKEN; do
  [[ "$frag" == LEGACY_BROKEN ]] && continue
  if [[ -f "$DEBUG_LOG" ]]; then
    c=$(grep -ci "$frag" "$DEBUG_LOG" 2>/dev/null || echo 0)
    echo "$frag: $c lines"
  fi
done
if [[ -f "$DEBUG_LOG" ]]; then
  c=$(grep -c '38319D43-77FD' "$DEBUG_LOG" 2>/dev/null || echo 0)
  echo "broken team AVIF: $c lines"
fi

section "WordPress DB search (requires wp-cli)"
if command -v wp >/dev/null 2>&1 && [[ -f "$WP_ROOT/wp-config.php" ]]; then
  cd "$WP_ROOT"
  for pattern in 'tailus.io' 'upload.wikimedia.org' 'wikimedia.org' '38319D43-77FD-42D8-91BA-69E23BE7879C'; do
    echo "Pattern: $pattern"
    wp db search "$pattern" --all-tables --precise --stats 2>/dev/null || echo "  (search failed or no matches)"
  done

  section "Elementor / postmeta sample hits"
  wp db query "SELECT post_id, meta_key, LEFT(meta_value, 120) AS snippet FROM wp_postmeta WHERE meta_value LIKE '%tailus.io%' OR meta_value LIKE '%wikimedia%' OR meta_value LIKE '%38319D43-77FD%' LIMIT 20;" 2>/dev/null || true
  wp db query "SELECT ID, post_title, post_type FROM wp_posts WHERE post_content LIKE '%tailus.io%' OR post_content LIKE '%wikimedia%' OR post_content LIKE '%38319D43-77FD%' LIMIT 20;" 2>/dev/null || true
else
  echo "wp-cli not available or wp-config.php missing — skip DB search"
fi

section "Traffic impact estimate from debug.log timestamps"
if [[ -f "$DEBUG_LOG" ]]; then
  LAST_HOUR=$(grep 'getimagesize(' "$DEBUG_LOG" 2>/dev/null | tail -n 2000 | wc -l | tr -d ' ')
  echo "getimagesize lines in last 2000 log matches: $LAST_HOUR"
  echo "Note: Each page cache rebuild can re-trigger probes per <img> without width/height."
  echo "Remote 404/403 URLs cause outbound HTTP from PHP on every probe — filter + URL cleanup stops repeats."
fi

echo
echo "=== Scan complete ==="
echo "Report saved: $REPORT"
