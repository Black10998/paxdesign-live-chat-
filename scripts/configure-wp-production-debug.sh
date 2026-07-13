#!/usr/bin/env bash
# Disable unnecessary WordPress debug output on production (run AFTER diagnostics).
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
CONFIG="$WP_ROOT/wp-config.php"

[[ -f "$CONFIG" ]] || { echo "ERROR: wp-config.php not found at $CONFIG"; exit 1; }

BACKUP="$CONFIG.bak-pax-debug-$(date +%Y%m%d-%H%M%S)"
cp "$CONFIG" "$BACKUP"
echo "Backup: $BACKUP"

python3 - <<'PY' "$CONFIG"
import re, sys
path = sys.argv[1]
text = open(path, encoding="utf-8", errors="replace").read()

def set_define(name, value, body):
    pat = rf"define\s*\(\s*['\"]{re.escape(name)}['\"]\s*,\s*[^)]+\)\s*;"
    repl = f"define('{name}', {value});"
    if re.search(pat, body):
        return re.sub(pat, repl, body, count=1)
    # Insert before "That's all, stop editing"
    marker = "/* That's all, stop editing!"
    if marker in body:
        return body.replace(marker, f"{repl}\n\n{marker}", 1)
    return body + f"\n{repl}\n"

text = set_define("WP_DEBUG", "false", text)
text = set_define("WP_DEBUG_LOG", "false", text)
text = set_define("WP_DEBUG_DISPLAY", "false", text)
open(path, "w", encoding="utf-8").write(text)
print("Updated wp-config.php:")
print("  WP_DEBUG = false")
print("  WP_DEBUG_LOG = false")
print("  WP_DEBUG_DISPLAY = false")
PY

echo "Done. Remove backup manually when satisfied: $BACKUP"
