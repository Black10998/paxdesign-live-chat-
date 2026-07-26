#!/usr/bin/env bash
# Scope Authorization passthrough to /wp-json/ only (Hostinger/LiteSpeed Application Passwords).
# Removes legacy global passthrough rules that leak auth into wp-admin and trigger WAF 403.
set -euo pipefail

WP_ROOT="${WP_PATH:-$(pwd)}"
HTACCESS="$WP_ROOT/.htaccess"
MARKER_BEGIN="# BEGIN PAXdesign Hostinger REST auth"
MARKER_END="# END PAXdesign Hostinger REST auth"

[[ -f "$HTACCESS" ]] || { echo "SKIP: no .htaccess at $HTACCESS"; exit 0; }

BACKUP="$HTACCESS.bak-pax-rest-auth-$(date +%Y%m%d-%H%M%S)"
cp "$HTACCESS" "$BACKUP"
echo "Backup: $BACKUP"

HTACCESS="$HTACCESS" export HTACCESS
php <<'PHP'
<?php
$path = getenv('HTACCESS') ?: '';
if ($path === '' || !is_readable($path)) {
    fwrite(STDERR, "ERROR: unreadable htaccess\n");
    exit(1);
}

$begin = '# BEGIN PAXdesign Hostinger REST auth';
$end   = '# END PAXdesign Hostinger REST auth';

$block = <<<'BLOCK'
# BEGIN PAXdesign Hostinger REST auth
# Pass Authorization to PHP only for REST (Application Passwords).
# Global passthrough can trigger Hostinger/LiteSpeed WAF 403 on wp-admin.
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteCond %{REQUEST_URI} ^/wp-json/ [NC,OR]
RewriteCond %{REQUEST_URI} wp-json/ [NC]
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
</IfModule>
# END PAXdesign Hostinger REST auth
BLOCK;

$text = file_get_contents($path);

// Drop previous PAX block (idempotent).
$text = preg_replace(
    '/# BEGIN PAXdesign Hostinger REST auth.*?# END PAXdesign Hostinger REST auth\s*/s',
    '',
    $text
);

// Remove common global Authorization passthrough snippets (pre-plugin).
$global_patterns = array(
    '/^\s*RewriteRule \.\* - \[E=HTTP_AUTHORIZATION:%\{HTTP:Authorization\}\]\s*$/m',
    '/^\s*RewriteRule \^\(\.\*\)\$ - \[E=HTTP_AUTHORIZATION:%\{HTTP:Authorization\}\]\s*$/m',
    '/^\s*SetEnvIf Authorization "\(\.\*\)" HTTP_AUTHORIZATION=\$1\s*$/m',
);
foreach ($global_patterns as $pattern) {
    $text = preg_replace($pattern, '', $text);
}

$text = rtrim($text) . "\n\n" . trim($block) . "\n";

file_put_contents($path, $text);
echo "Updated $path with scoped REST Authorization passthrough\n";
PHP

echo "Done."
