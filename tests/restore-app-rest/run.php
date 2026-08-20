<?php
/**
 * Guards: the iOS/customer app talks to pretty-permalink REST (/wp-json/...).
 * A trailing LiteSpeed RewriteEngine On after WordPress rules 404s that path.
 */
$root = dirname(__DIR__, 2);
$fail = 0;

function rar_ok($cond, $message) {
	global $fail;
	if ($cond) {
		echo "OK  $message\n";
		return;
	}
	echo "FAIL $message\n";
	$fail++;
}

$patcher = $root . '/scripts/patch-wp-htaccess-security.sh';
rar_ok(is_file($patcher), 'htaccess patcher exists');
$src = file_get_contents($patcher);

rar_ok(strpos($src, 'FilesMatch') !== false, 'security block uses FilesMatch');
rar_ok(strpos($src, 'rest_route') !== false, 'patcher restores /wp-json/ pretty permalinks');
rar_ok(strpos($src, 'BEGIN PAXDesign REST permalinks') !== false, 'REST permalinks are a dedicated htaccess block');
rar_ok(strpos($src, 'rsync --delete') === false, 'patcher does not rsync-delete the plugin');
rar_ok(preg_match('/^\s*python3\b/m', $src) !== 1, 'patcher stays bash-only for Hostinger');

$fixture = sys_get_temp_dir() . '/pax-htaccess-app-rest-' . getmypid();
@mkdir($fixture, 0777, true);
$wp_rules = <<<'HT'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress

# BEGIN PAXdesign Hostinger REST auth
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
</IfModule>
# END PAXdesign Hostinger REST auth

# BEGIN PAXDesign security
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteRule ^readme\.html$ - [F,L]
</IfModule>
# END PAXDesign security
HT;
file_put_contents($fixture . '/.htaccess', $wp_rules);
file_put_contents($fixture . '/readme.html', 'leak');
file_put_contents($fixture . '/llms.txt', 'leak');

$cmd = 'WP_PATH=' . escapeshellarg($fixture) . ' bash ' . escapeshellarg($patcher);
exec($cmd . ' 2>&1', $out, $code);
echo implode("\n", $out) . "\n";
rar_ok($code === 0, 'patcher exits 0 on a WordPress htaccess fixture');

$ht = file_get_contents($fixture . '/.htaccess');
rar_ok(strpos($ht, '# BEGIN WordPress') !== false, 'WordPress rewrite block is preserved');
rar_ok(strpos($ht, 'RewriteRule . /index.php') !== false, 'WordPress front controller is preserved');
rar_ok(preg_match('/RewriteRule \^wp-json\/\?\$ \/index\.php\?rest_route=\//', $ht) === 1, 'pretty /wp-json/ rewrites to rest_route');
rar_ok(strpos($ht, '<FilesMatch') !== false, 'patched htaccess uses FilesMatch');
rar_ok(!is_file($fixture . '/readme.html'), 'readme.html is removed from disk');

$sec = '';
if (preg_match('/# BEGIN PAXDesign security.*?# END PAXDesign security/s', $ht, $m)) {
	$sec = $m[0];
}
rar_ok($sec !== '', 'security block is present after patch');
rar_ok(strpos($sec, 'RewriteEngine On') === false, 'security block does not start a new RewriteEngine context');
rar_ok(strpos($sec, 'RewriteRule') === false, 'security block has no RewriteRule');

$rest_pos = strpos($ht, '# BEGIN PAXDesign REST permalinks');
$wp_pos = strpos($ht, '# BEGIN WordPress');
rar_ok($rest_pos !== false && $wp_pos !== false && $rest_pos < $wp_pos, 'REST permalinks are inserted before the WordPress block');
rar_ok(strpos($ht, '# BEGIN PAXdesign Hostinger REST auth') === false, 'legacy Hostinger REST auth rewrite block is removed');
rar_ok(strpos($ht, 'HTTP_AUTHORIZATION') !== false, 'Authorization passthrough stays in the first rewrite block');
$after_wp = substr($ht, strpos($ht, '# END WordPress') ?: 0);
rar_ok(preg_match('/^[[:space:]]*RewriteEngine[[:space:]]+On[[:space:]]*$/m', $after_wp) !== 1, 'no RewriteEngine On after the WordPress block');

foreach (glob($fixture . '/.htaccess*') ?: array() as $leftover) {
	@unlink($leftover);
}
@unlink($fixture . '/readme.html');
@unlink($fixture . '/llms.txt');
@rmdir($fixture);

if ($fail > 0) {
	fwrite(STDERR, "$fail restore-app-rest assertion(s) failed\n");
	exit(1);
}

echo "App REST htaccess guards passed.\n";
