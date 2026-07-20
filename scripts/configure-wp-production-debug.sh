#!/usr/bin/env bash
# Disable unnecessary WordPress debug output on production (run AFTER diagnostics).
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
CONFIG="$WP_ROOT/wp-config.php"

[[ -f "$CONFIG" ]] || { echo "ERROR: wp-config.php not found at $CONFIG"; exit 1; }

BACKUP="$CONFIG.bak-pax-debug-$(date +%Y%m%d-%H%M%S)"
cp "$CONFIG" "$BACKUP"
echo "Backup: $BACKUP"

php <<PHP
<?php
\$path = '$CONFIG';
\$text = file_get_contents(\$path);
\$replacements = [
    "define('WP_DEBUG', true)" => "define('WP_DEBUG', false)",
    "define('WP_DEBUG', true);" => "define('WP_DEBUG', false);",
    "define( 'WP_DEBUG', true )" => "define( 'WP_DEBUG', false )",
    "define('WP_DEBUG_LOG', true)" => "define('WP_DEBUG_LOG', false)",
    "define('WP_DEBUG_LOG', true);" => "define('WP_DEBUG_LOG', false);",
    "define( 'WP_DEBUG_LOG', true )" => "define( 'WP_DEBUG_LOG', false )",
    "define('WP_DEBUG_DISPLAY', true)" => "define('WP_DEBUG_DISPLAY', false)",
    "define('WP_DEBUG_DISPLAY', true);" => "define('WP_DEBUG_DISPLAY', false);",
];
foreach (\$replacements as \$from => \$to) {
    \$text = str_replace(\$from, \$to, \$text);
}
if (!preg_match("/define\\s*\\(\\s*['\"]WP_DEBUG['\"]/", \$text)) {
    \$text = preg_replace("/(\\/\\* That's all, stop editing!)/", "define('WP_DEBUG', false);\\ndefine('WP_DEBUG_LOG', false);\\ndefine('WP_DEBUG_DISPLAY', false);\\n\\n\$1", \$text, 1);
}
if (!preg_match("/define\\s*\\(\\s*['\"]WP_POST_REVISIONS['\"]/", \$text)) {
    \$text = preg_replace("/(\\/\\* That's all, stop editing!)/", "define('WP_POST_REVISIONS', 5);\\n\\n\$1", \$text, 1);
} else {
    \$text = preg_replace("/define\\s*\\(\\s*['\"]WP_POST_REVISIONS['\"]\s*,\s*(?:true|false|\\d+)\s*\)/", "define('WP_POST_REVISIONS', 5)", \$text);
}
file_put_contents(\$path, \$text);
echo "Updated wp-config.php:\\n";
echo "  WP_DEBUG = false\\n";
echo "  WP_DEBUG_LOG = false\\n";
echo "  WP_DEBUG_DISPLAY = false\\n";
echo "  WP_POST_REVISIONS = 5\\n";
PHP

echo "Done. Remove backup manually when satisfied: $BACKUP"
