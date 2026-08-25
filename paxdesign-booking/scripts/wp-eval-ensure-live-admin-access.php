<?php
/**
 * Ensure deploy CI admin and WordPress administrators can access live-admin REST.
 *
 * Usage from WordPress root:
 *   PAX_ADMIN_USER=login_or_email wp eval-file wp-content/plugins/paxdesign-booking/scripts/wp-eval-ensure-live-admin-access.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via wp eval-file from the WordPress root.\n");
    exit(1);
}

if (!class_exists('PAXdesign_Live_Chat_Permissions')) {
    fwrite(STDERR, "PAXdesign_Live_Chat_Permissions not loaded.\n");
    exit(1);
}

$admin_login_or_email = trim((string) getenv('PAX_ADMIN_USER'));
$provisioned          = 0;

if (class_exists('PAXdesign_Auth_Native') && method_exists('PAXdesign_Auth_Native', 'provision_owner_administrator')) {
    if (PAXdesign_Auth_Native::provision_owner_administrator()) {
        $owner = get_user_by('email', PAXdesign_Auth_Native::owner_email());
        echo 'owner_admin_ok user_id=' . ($owner instanceof WP_User ? (int) $owner->ID : 0) . ' email=' . PAXdesign_Auth_Native::owner_email() . "\n";
    } else {
        fwrite(STDERR, 'WARN: owner account not found for ' . PAXdesign_Auth_Native::owner_email() . "\n");
    }
}

if ($admin_login_or_email !== '') {
    $user = get_user_by('login', $admin_login_or_email);
    if (!$user instanceof WP_User) {
        $user = get_user_by('email', $admin_login_or_email);
    }
    if ($user instanceof WP_User) {
        PAXdesign_Live_Chat_Permissions::provision_staff_access((int) $user->ID, 'deploy_admin');
        $provisioned++;
        echo 'deploy_admin_ok user_id=' . (int) $user->ID . "\n";
    } else {
        fwrite(STDERR, "WARN: PAX_ADMIN_USER not found: {$admin_login_or_email}\n");
    }
}

$admins = get_users(array(
    'role__in' => array('administrator'),
    'fields'   => array('ID'),
));

foreach ($admins as $admin_user) {
    $uid = (int) $admin_user->ID;
    if ($uid <= 0) {
        continue;
    }
    if (PAXdesign_Live_Chat_Permissions::provision_staff_access($uid, 'administrator')) {
        $provisioned++;
    }
}

echo "live_admin_access_ok provisioned={$provisioned}\n";
