<?php
/**
 * Guards for website Sign in with GitHub (web OAuth).
 */
$root = dirname(__DIR__, 2);
$plugin = $root . '/paxdesign-booking';
$overlay = $root . '/deploy-patches/restored-chat-human-ui';
$fail = 0;

function gh_ok($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

$github = file_get_contents($plugin . '/includes/auth/class-paxdesign-auth-github.php');
$module = file_get_contents($plugin . '/includes/auth/class-paxdesign-auth-module.php');
$rest = file_get_contents($plugin . '/includes/auth/class-paxdesign-auth-rest.php');
$frontend = file_get_contents($plugin . '/includes/auth/class-paxdesign-auth-frontend.php');
$js = file_get_contents($plugin . '/assets/customer-auth/js/pax-auth.js');
$css = file_get_contents($plugin . '/assets/customer-auth/css/pdx-auth-page.css');
$settings = file_get_contents($plugin . '/templates/settings-page.php');
$boot = file_get_contents($plugin . '/paxdesign-booking.php');
$eval = file_get_contents($plugin . '/scripts/wp-eval-github-web-oauth-config.php');
$deploy = file_get_contents($root . '/.github/workflows/deploy-restored-chat-human-ui.yml');

gh_ok(strpos($github, 'class PAXdesign_Auth_GitHub') !== false, 'GitHub OAuth class exists');
gh_ok(strpos($github, 'https://github.com/login/oauth/authorize') !== false, 'GitHub authorize URL is present');
gh_ok(strpos($github, 'user:email') !== false, 'GitHub OAuth requests user:email');
gh_ok(strpos($github, 'paxdesign_github_oauth_client_id') !== false, 'Client ID option is used');
gh_ok(strpos($github, 'paxdesign_github_oauth_client_secret') !== false, 'Client secret option is used');
gh_ok(strpos($github, '/pdx/v1/auth/github/callback') !== false, 'Callback URL uses the REST route');
gh_ok(strpos($module, 'class-paxdesign-auth-github.php') !== false, 'Auth module loads GitHub class');
gh_ok(strpos($module, 'PAXdesign_Auth_GitHub::register_hooks') !== false, 'Auth module registers GitHub hooks');
gh_ok(strpos($rest, '/auth/github/start') !== false, 'REST start route is registered');
gh_ok(strpos($rest, '/auth/github/callback') !== false, 'REST callback route is registered');
gh_ok(strpos($frontend, 'githubWebEnabled') !== false, 'Frontend exposes githubWebEnabled');
gh_ok(strpos($frontend, 'githubStartUrl') !== false, 'Frontend exposes githubStartUrl');
gh_ok(strpos($js, 'Sign in with GitHub') !== false, 'Login UI has Sign in with GitHub');
gh_ok(strpos($js, 'data-pdx-github-signin') !== false, 'Login UI binds the GitHub button');
gh_ok(strpos($js, 'githubWebStartUrl') !== false, 'Login UI builds the GitHub start URL');
gh_ok(strpos($css, 'pdx-auth-github-btn') !== false, 'Auth page CSS styles the GitHub button');
gh_ok(strpos($settings, 'paxdesign_github_oauth_client_id') !== false, 'Settings page has Client ID field');
gh_ok(strpos($settings, 'paxdesign_github_oauth_client_secret') !== false, 'Settings page has Client Secret field');
gh_ok(strpos($boot, 'paxdesign_github_oauth_client_id') !== false, 'Bootstrap registers GitHub Client ID setting');
gh_ok(strpos($eval, 'PAX_GITHUB_OAUTH_CLIENT_ID') !== false, 'WP-CLI config script reads GitHub secrets');
gh_ok(strpos($deploy, 'class-paxdesign-auth-github.php') !== false, 'Surgical deploy copies the GitHub auth class');
gh_ok(strpos($deploy, 'wp-eval-github-web-oauth-config.php') !== false, 'Surgical deploy writes GitHub OAuth options');
gh_ok(strpos($js, 'skipping stacked sync') === false, 'Auth JS is not the 3.176 chat rewrite');

$overlay_files = array(
    'paxdesign-booking.php',
    'includes/auth/class-paxdesign-auth-github.php',
    'includes/auth/class-paxdesign-auth-module.php',
    'includes/auth/class-paxdesign-auth-rest.php',
    'includes/auth/class-paxdesign-auth-frontend.php',
    'assets/customer-auth/js/pax-auth.js',
    'assets/customer-auth/css/pdx-auth-page.css',
    'assets/customer-auth/css/pdx-auth.css',
    'templates/settings-page.php',
    'scripts/wp-eval-github-web-oauth-config.php',
);
foreach ($overlay_files as $rel) {
    $a = $overlay . '/' . $rel;
    $b = $plugin . '/' . $rel;
    gh_ok(is_file($a) && is_file($b) && md5_file($a) === md5_file($b), 'overlay matches plugin: ' . $rel);
}

$syntax = array(
    $plugin . '/includes/auth/class-paxdesign-auth-github.php',
    $plugin . '/includes/auth/class-paxdesign-auth-module.php',
    $plugin . '/includes/auth/class-paxdesign-auth-rest.php',
    $plugin . '/includes/auth/class-paxdesign-auth-frontend.php',
    $plugin . '/scripts/wp-eval-github-web-oauth-config.php',
    $plugin . '/templates/settings-page.php',
);
foreach ($syntax as $file) {
    $out = array();
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    gh_ok($code === 0, 'php -l ' . basename($file) . ' — ' . implode(' ', $out));
}

$jsCheck = array();
$jsCode = 0;
exec('node --check ' . escapeshellarg($plugin . '/assets/customer-auth/js/pax-auth.js') . ' 2>&1', $jsCheck, $jsCode);
gh_ok($jsCode === 0, 'node --check pax-auth.js ' . implode(' ', $jsCheck));

if ($fail > 0) {
    fwrite(STDERR, "$fail github-web-login assertion(s) failed\n");
    exit(1);
}
echo "GitHub website login guards passed.\n";
