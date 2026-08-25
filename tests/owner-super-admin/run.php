<?php
/**
 * Guards: sarah.gta1995@gmail.com is the site owner / super admin.
 * That account has the WordPress administration dashboard AND the full
 * customer portal with master-admin customer management. It must not be
 * treated as a regular customer or employee.
 */

$root = dirname(__DIR__, 2);
$plugin = $root . '/paxdesign-booking';
$overlay = $root . '/deploy-patches/restored-chat-human-ui';
$fail = 0;

function owner_ok($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

$owner_email = 'sarah.gta1995@gmail.com';

$auth = file_get_contents($plugin . '/includes/auth/class-paxdesign-auth-native.php');
$customers = file_get_contents($plugin . '/includes/auth/class-paxdesign-customers.php');
$master = file_get_contents($plugin . '/includes/customer/class-paxdesign-customer-master-admin.php');
$registry = file_get_contents($plugin . '/includes/customer/class-paxdesign-customer-registry.php');
$customer_auth = file_get_contents($plugin . '/includes/customer/class-paxdesign-customer-auth.php');
$permissions = file_get_contents($plugin . '/includes/class-paxdesign-live-chat-permissions.php');
$apple = file_get_contents($plugin . '/includes/auth/class-paxdesign-auth-apple.php');
$github = file_get_contents($plugin . '/includes/auth/class-paxdesign-auth-github.php');
$eval = file_get_contents($plugin . '/scripts/wp-eval-ensure-live-admin-access.php');
$js = file_get_contents($plugin . '/assets/customer-auth/js/pax-auth.js');
$overlay_js = file_get_contents($overlay . '/assets/customer-auth/js/pax-auth.js');
$overlay_github = file_get_contents($overlay . '/includes/auth/class-paxdesign-auth-github.php');
$workflow = file_get_contents($root . '/.github/workflows/deploy-owner-super-admin.yml');
$l10n = file_get_contents($plugin . '/includes/customer/data/account-ui-l10n.php');

owner_ok(strpos($permissions, "SUPER_ADMIN_EMAIL = '" . $owner_email . "'") !== false, 'Live Chat super-admin email is the owner account');
owner_ok(strpos($auth, $owner_email) !== false, 'Auth native knows the owner email');
owner_ok(strpos($auth, 'function owner_email') !== false, 'Auth native exposes owner_email()');
owner_ok(strpos($auth, 'function is_owner_account') !== false, 'Auth native exposes is_owner_account()');
owner_ok(strpos($auth, 'function provision_owner_administrator') !== false, 'Owner is promoted to WordPress administrator');
owner_ok(strpos($auth, "set_role( 'administrator' )") !== false, 'Owner provision assigns the administrator role');
owner_ok(strpos($auth, 'function owner_can_use_customer_portal') !== false, 'Owner is allowed to use the customer portal');
owner_ok(strpos($auth, 'redirect_owner_from_customer_portal') === false, 'Owner is not redirected away from /account/');
owner_ok(strpos($auth, "add_action( 'template_redirect'") === false, 'Auth native does not bounce the owner off the account page');
owner_ok(strpos($auth, 'admin_url()') !== false, 'WordPress login still sends the owner to wp-admin');
owner_ok(strpos($auth, 'provision_owner_administrator( (int) $signed->ID )') !== false, 'Password login provisions the owner before assigning a customer role');
owner_ok(strpos($auth, 'is_owner_account( $user_id ) || self::is_site_admin( $user_id )') !== false, 'Customer role assignment skips the owner');
owner_ok(strpos($auth, "'is_owner'") !== false && strpos($auth, "'is_admin'") !== false, 'Session payload flags is_owner and is_admin');
owner_ok(strpos($auth, "'is_master_admin'") !== false, 'Session payload flags is_master_admin so the account UI unlocks customer management');
owner_ok(strpos($auth, "current_user_can( 'manage_options' )") !== false, 'is_site_admin still uses current_user_can(manage_options)');

owner_ok(strpos($customers, 'is_owner_account') !== false, 'Customers module excludes the owner from is_customer()');
owner_ok(strpos($registry, 'is_owner_account') !== false, 'Customer registry excludes the owner from the managed-customer list');
owner_ok(strpos($customer_auth, 'is_owner_account') !== false, 'Portal role resolver treats the owner as administrator, not employee');
owner_ok(strpos($customer_auth, "'is_owner'") !== false && strpos($customer_auth, "'is_master_admin'") !== false, 'Customer auth payload marks the owner as owner and master admin');
owner_ok(strpos($master, "OWNER_EMAIL = '" . $owner_email . "'") !== false, 'Customer master-admin includes the owner email');
owner_ok(strpos($master, 'awjime29@icloud.com') !== false, 'iCloud master-admin email is preserved');
owner_ok(strpos($master, 'ftbkvmfy6g@privaterelay.appleid.com') !== false, 'Apple relay master-admin email is preserved');

owner_ok(strpos($apple, 'is_owner_account') === false || strpos($apple, 'admin_url()') === false, 'Apple login does not force the owner away from /account/');
owner_ok(strpos($github, '#/overview') !== false, 'GitHub login can return to the account overview');
owner_ok($github === $overlay_github, 'Overlay GitHub auth matches the plugin copy');

owner_ok(strpos($js, 'function redirectOwnerToWpAdmin') === false, 'Account JS does not bounce the owner to wp-admin');
owner_ok(strpos($js, 'user.is_owner') !== false, 'Account JS reads the is_owner flag');
owner_ok(strpos($js, 'user.is_master_admin || user.is_owner') !== false, 'Owner is treated as master admin in the account UI');
owner_ok(strpos($js, 'nav_overview') !== false && strpos($js, 'nav_personal') !== false && strpos($js, 'nav_settings') !== false, 'Owner still has Overview, Personal Information, and Account Settings');
owner_ok(strpos($js, 'nav_administration') !== false, 'Owner account UI includes Customer Management');
owner_ok(strpos($js, 'nav_wordpress_admin') !== false, 'Owner account UI includes a WordPress Admin link');
owner_ok(strpos($js, 'owner_super_admin') !== false, 'Account status labels the owner as Owner / Super Admin, not a customer');
owner_ok($js === $overlay_js, 'Overlay pax-auth.js matches the plugin copy');

owner_ok(strpos($l10n, 'owner_super_admin') !== false, 'Account l10n includes the owner status label');
owner_ok(strpos($l10n, 'nav_wordpress_admin') !== false, 'Account l10n includes the WordPress Admin nav label');

owner_ok(strpos($eval, 'provision_owner_administrator') !== false, 'Deploy eval-file promotes the owner account');

owner_ok(is_file($root . '/.github/workflows/deploy-owner-super-admin.yml'), 'Surgical owner-admin deploy workflow exists');
owner_ok(strpos($workflow, 'rsync --delete') === false && strpos($workflow, 'rsync -az --delete') === false, 'Owner-admin deploy must not rsync --delete the plugin tree');
owner_ok(strpos($workflow, 'class-paxdesign-auth-native.php') !== false, 'Owner-admin deploy rsyncs auth-native.php');
owner_ok(strpos($workflow, 'wp-eval-ensure-live-admin-access.php') !== false, 'Owner-admin deploy runs live owner promotion');
owner_ok(strpos($workflow, 'Version: 3.174.128') !== false, 'Owner-admin deploy still verifies chat 3.174.128');

$syntax = array(
    $plugin . '/includes/auth/class-paxdesign-auth-native.php',
    $plugin . '/includes/auth/class-paxdesign-customers.php',
    $plugin . '/includes/auth/class-paxdesign-auth-apple.php',
    $plugin . '/includes/auth/class-paxdesign-auth-github.php',
    $plugin . '/includes/customer/class-paxdesign-customer-master-admin.php',
    $plugin . '/includes/customer/class-paxdesign-customer-registry.php',
    $plugin . '/includes/customer/class-paxdesign-customer-auth.php',
    $plugin . '/scripts/wp-eval-ensure-live-admin-access.php',
);
foreach ($syntax as $file) {
    $out = array();
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    owner_ok($code === 0, 'php -l ' . basename($file) . ' — ' . implode(' ', $out));
}

$jsCheck = array();
$jsCode = 0;
exec('node --check ' . escapeshellarg($plugin . '/assets/customer-auth/js/pax-auth.js') . ' 2>&1', $jsCheck, $jsCode);
owner_ok($jsCode === 0, 'node --check pax-auth.js ' . implode(' ', $jsCheck));

if ($fail > 0) {
    fwrite(STDERR, "$fail assertion(s) failed\n");
    exit(1);
}

echo "All owner / super-admin guards passed.\n";
