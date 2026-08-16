<?php
/**
 * Customer platform automated checks (CI-safe, no production credentials required).
 */

function cx_assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

echo "Customer platform checks\n";

$customer_dir = dirname(__DIR__, 2) . '/paxdesign-booking/includes/customer';
$files = glob($customer_dir . '/*.php');
cx_assert_true(is_array($files) && count($files) >= 10, 'Expected customer platform PHP modules');

foreach ($files as $file) {
    exec('php -l ' . escapeshellarg($file), $out, $code);
    cx_assert_true($code === 0, 'Syntax error in ' . basename($file));
}

$rest = file_get_contents($customer_dir . '/class-paxdesign-customer-rest.php');
cx_assert_true(strpos($rest, '/content/homepage') !== false, 'Missing /content/homepage route');
cx_assert_true(strpos($rest, '/content/about') !== false, 'Missing /content/about route');
cx_assert_true(strpos($rest, '/content/contact') !== false, 'Missing /content/contact route');
cx_assert_true(strpos($rest, '/content/site-menu') !== false, 'Missing /content/site-menu route');
cx_assert_true(strpos($rest, '/content/services-catalog') !== false, 'Missing /content/services-catalog route');
cx_assert_true(strpos($rest, '/content/portfolio-showcase') !== false, 'Missing /content/portfolio-showcase route');
cx_assert_true(strpos($rest, '/customer/news/') !== false, 'Missing /customer/news/{slug} route');
cx_assert_true(strpos($rest, '/customer/chat/messages/image') !== false, 'Missing customer chat image route');
cx_assert_true(strpos($rest, '/customer/projects/') !== false && strpos($rest, '/download') !== false, 'Missing project file download route');
cx_assert_true(strpos($rest, 'chat_stream') !== false, 'Missing chat_stream handler');

$staff = file_get_contents($customer_dir . '/class-paxdesign-customer-staff-rest.php');
cx_assert_true(strpos($staff, '/customer/staff/projects') !== false, 'Missing staff project routes');
cx_assert_true(strpos($staff, 'require_staff') !== false, 'Staff routes must use require_staff');

$auth = file_get_contents($customer_dir . '/class-paxdesign-customer-auth.php');
cx_assert_true(strpos($auth, 'pax_customer') === false, 'Legacy pax_customer role must not remain');
cx_assert_true(strpos($auth, 'PAXdesign_Auth') !== false, 'Customer auth must delegate to PAXdesign_Auth');

$forbidden_patterns = array(
    '/register_rest_route\s*\([^)]*(paypal|billing|checkout|commerce)/i',
    '/class\s+PDX_(Commerce|Billing|Access)/',
    '/\b(in_app_purchase|iap_checkout)\b/i',
);
foreach ($files as $file) {
    $contents = file_get_contents($file);
    foreach ($forbidden_patterns as $pattern) {
        cx_assert_true(!preg_match($pattern, $contents), basename($file) . ' exposes forbidden commerce pattern: ' . $pattern);
    }
}

$chat = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-chat.php');
cx_assert_true(strpos($chat, 'stream_authenticated_customer_chat') !== false, 'Missing authenticated customer AI stream method');
cx_assert_true(strpos($chat, 'build_openai_payload($model, $openai_messages, false)') !== false, 'Non-streaming OpenAI completion must disable stream mode');
cx_assert_true(strpos($chat, 'build_ai_system_prompt') !== false, 'Chat must build AI system prompt with account context');
cx_assert_true(strpos($chat, 'resolve_and_persist_customer_language') !== false, 'Chat must persist detected customer language per session');

$language_routing = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-language-routing.php');
cx_assert_true(strpos($language_routing, 'resolve_session_language') !== false, 'Language routing must resolve sticky session language');
cx_assert_true(strpos($language_routing, 'persist_session_language') !== false, 'Language routing must persist session language');

$chat_knowledge = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-chat-knowledge.php');
cx_assert_true(strpos($chat_knowledge, 'build_customer_account_context_block') !== false, 'Chat knowledge must build customer account context block');
cx_assert_true(strpos($chat, 'PAXdesign_Chat_Intent::detect') !== false, 'Chat must detect customer intent before answering');
cx_assert_true(strpos($chat_knowledge, 'request details:') !== false, 'Account context must include submitted request details');

$customer_orders = file_get_contents($customer_dir . '/class-paxdesign-customer-orders.php');
cx_assert_true(strpos($customer_orders, 'upcoming_bookings_for_user') !== false, 'Customer orders must expose upcoming bookings for AI context');

$link_scan = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-link-scan-service.php');
cx_assert_true(strpos($link_scan, 'format_customer_message') !== false, 'Link scan service must format customer-facing scan messages');
cx_assert_true(strpos($link_scan, 'scramble_url') !== false, 'Link scan service must scramble URLs for scan animation');
cx_assert_true(strpos($link_scan, 'emit_customer_scan_event') !== false, 'Link scan service must emit customer scan SSE updates');

$live = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-chat-live.php');
cx_assert_true(strpos($live, 'public function sanitize_session_id') !== false, 'PAXdesign_Chat_Live::sanitize_session_id must be public for cross-class use');

$db = file_get_contents($customer_dir . '/class-paxdesign-customer-db.php');
cx_assert_true(strpos($db, 'schema_complete') !== false, 'Customer DB must verify schema completeness');
cx_assert_true(strpos($db, 'service_categories') !== false && strpos($db, 'services') !== false, 'Customer DB must define services tables');

cx_assert_true(strpos($rest, '/content/legal/') !== false, 'Missing /content/legal route');
cx_assert_true(strpos($rest, '/customer/profile/avatar') !== false, 'Missing profile avatar route');
cx_assert_true(strpos($rest, 'chat_renew_session') !== false, 'Missing chat session renew handler');

$chat_bridge = file_get_contents($customer_dir . '/class-paxdesign-customer-chat-bridge.php');
cx_assert_true(strpos($chat_bridge, 'renew_closed_session') !== false, 'Chat bridge must renew closed sessions on send');

$showcase = file_get_contents($customer_dir . '/class-paxdesign-customer-portfolio-showcase.php');
cx_assert_true(strpos($showcase, 'portfolio-showcase-data.json') !== false, 'Portfolio showcase must load curated JSON data');
cx_assert_true(is_readable($customer_dir . '/data/portfolio-showcase-data.json'), 'Portfolio showcase JSON must exist');

$portfolio = file_get_contents($customer_dir . '/class-paxdesign-customer-portfolio.php');
cx_assert_true(strpos($portfolio, 'PAXdesign_Customer_Portfolio_Showcase') !== false, 'Portfolio must prefer curated showcase data');
cx_assert_true(strpos($portfolio, 'structured_from_blocks') !== false, 'Portfolio must build structured showcase payload');
cx_assert_true(strpos($portfolio, 'clean_title') !== false, 'Portfolio must sanitize titles for native UI');

$ios_portfolio = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/Features/CustomerPortfolioViews.swift');
cx_assert_true(strpos($ios_portfolio, 'CustomerPortfolioGalleryViewer') !== false, 'iOS portfolio must include gallery viewer');
cx_assert_true(strpos($ios_portfolio, 'CustomerPremiumEmptyState') !== false, 'iOS must ship premium empty states');
cx_assert_true(strpos($ios_portfolio, 'CustomerCalmSectionIntro') !== false, 'iOS portfolio must use calm showcase layout');

cx_assert_true(strpos(file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/customer/data/legal-data.php'), "'impressum'") !== false, 'Legal data must include impressum page');
cx_assert_true(strpos(file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/customer/data/legal-data.php'), "'service-dokumentation'") !== false, 'Legal data must include service-dokumentation page');

$ios_account_footer = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/Features/CustomerAccountFooterViews.swift');
cx_assert_true(strpos($ios_account_footer, 'CustomerAccountLegalTerminalView') !== false, 'Account page must include legal terminal footer');
cx_assert_true(strpos($ios_account_footer, 'service-dokumentation') !== false, 'Account legal links must include service-dokumentation');

$ios_api = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/Core/CustomerAPIClient.swift');
cx_assert_true(strpos($ios_api, 'renewChatSession') !== false, 'iOS client must support chat session renew');
cx_assert_true(strpos($ios_api, 'fetchLegalPage') !== false, 'iOS client must fetch legal pages');
cx_assert_true(strpos($ios_api, 'uploadProfileAvatar') !== false, 'iOS client must upload profile avatars');

$ios_legal = dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/Features/Pages/CustomerLegalPageView.swift';
cx_assert_true(is_readable($ios_legal), 'CustomerLegalPageView must exist');

cx_assert_true(strpos($ios_api, '/content/homepage') !== false, 'iOS client must call homepage endpoint');
cx_assert_true(strpos($ios_api, '/content/about') !== false, 'iOS client must call about endpoint');
cx_assert_true(strpos($ios_api, '/content/contact') !== false, 'iOS client must call contact endpoint');
cx_assert_true(strpos($ios_api, '/content/services-catalog') !== false, 'iOS client must call services catalog endpoint');
cx_assert_true(strpos($ios_api, '/content/portfolio-showcase') !== false, 'iOS client must call portfolio showcase endpoint');

$ios_skeleton = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/Features/CustomerSkeletonLoading.swift');
cx_assert_true(strpos($ios_skeleton, 'CustomerHomepageSkeleton') !== false, 'iOS must ship skeleton loading placeholders');
cx_assert_true(strpos($ios_skeleton, 'skeletonShimmer') !== false, 'Skeleton shimmer modifier must exist');
cx_assert_true(strpos($ios_api, '/customer/chat/stream') !== false, 'iOS client must call customer chat stream endpoint');
cx_assert_true(strpos($ios_api, '/auth/register') !== false, 'iOS client must support auth registration');
cx_assert_true(strpos($ios_api, 'endpointURL') !== false, 'iOS client must build REST URLs with endpointURL()');
cx_assert_true(strpos($ios_api, '/customer/push/register') !== false, 'iOS client must register push tokens');
cx_assert_true(strpos(strtolower($ios_api), 'paypal') === false, 'iOS customer client must not reference PayPal');

$ios_push = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/Core/CustomerPushService.swift');
cx_assert_true(strpos($ios_push, 'registerForRemoteNotifications') !== false, 'iOS push service must register for remote notifications');

$platform = file_get_contents($customer_dir . '/class-paxdesign-customer-platform.php');
cx_assert_true(strpos($platform, 'paxdesign_customer_require_login_for_chat') !== false, 'Login-required chat option must be configured');

$booking_auth = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-native.php');
cx_assert_true(strpos($booking_auth, 'pdx_user_logged_in') !== false, 'Booking auth must fire pdx_user_logged_in');

$auth_native = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-native.php');
cx_assert_true(strpos($auth_native, "current_user_can( 'manage_options' )") !== false || strpos($auth_native, "current_user_can('manage_options')") !== false, 'is_site_admin must use current_user_can(manage_options) for current user');
cx_assert_true(!preg_match('/user_can\s*\([^)]*,\s*manage_options\s*\)/', $auth_native), 'Auth native must not use unquoted manage_options constant');
cx_assert_true(!preg_match('/current_user_can\s*\(\s*manage_options\s*\)/', $auth_native), 'Auth native must not use unquoted manage_options in current_user_can');

$plugin_php_files = glob(dirname(__DIR__, 2) . '/paxdesign-booking/**/*.php') ?: [];
foreach ($plugin_php_files as $php_file) {
    $contents = file_get_contents($php_file);
    cx_assert_true(!preg_match('/(?:user_can|current_user_can)\s*\([^)]*,\s*manage_options\s*\)/', $contents), 'Unquoted manage_options in ' . basename($php_file));
    cx_assert_true(!preg_match('/current_user_can\s*\(\s*manage_options\s*\)/', $contents), 'Unquoted manage_options in current_user_can in ' . basename($php_file));
}

$auth_frontend = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-frontend.php');
cx_assert_true(strpos($auth_frontend, 'PAX_AUTH_CONFIG') !== false, 'Booking auth frontend must localize PAX_AUTH_CONFIG');

$launch_view = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/Launch/PAXLaunchView.swift');
cx_assert_true(strpos($launch_view, 'PAXAnimatedLogoView') !== false, 'Launch screen must use PAXAnimatedLogoView');
cx_assert_true(strpos($launch_view, 'Color.black') !== false, 'Launch screen must use pure black background');

$animated_logo = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/Launch/PAXAnimatedLogoView.swift');
cx_assert_true(strpos($animated_logo, 'holdDuration') !== false && strpos($animated_logo, '1.2') !== false, 'Logo animation must preserve website hold timing');

$ccs_root = dirname(__DIR__, 2) . '/paxdesign-booking';
$chat_knowledge = file_get_contents($ccs_root . '/includes/class-paxdesign-chat-knowledge.php');
cx_assert_true(!is_file($ccs_root . '/includes/class-paxdesign-cybercrime-ai-workflow.php'), '3.176 CCS AI workflow must not exist on the production baseline');
cx_assert_true(!is_file($ccs_root . '/includes/class-paxdesign-cybercrime-ai-operations.php'), '3.176 CCS AI operations must not exist on the production baseline');
cx_assert_true(!is_file($ccs_root . '/includes/class-paxdesign-cybercrime-ai-case.php'), '3.176 CCS AI case must not exist on the production baseline');
cx_assert_true(strpos($chat_knowledge, 'complete latest message') !== false || strpos($chat_knowledge, 'COMPLETE latest message') !== false, 'The CCS assistant must read the complete latest customer message');
cx_assert_true(strpos($chat_knowledge, 'ONE clear step at a time') !== false, 'The CCS assistant must give one step at a time');

$ccs_js = file_get_contents($ccs_root . '/assets/js/chat-script.js');
cx_assert_true(strpos($ccs_js, 'skipping stacked sync') === false, 'Chat JS must not contain the 3.176 stacked-sync rewrite');
cx_assert_true(strpos($ccs_js, 'Version: 3.174.103') !== false, 'Chat JS must be the 3.174.103 baseline');

$ccs_css = file_get_contents($ccs_root . '/assets/css/booking-styles.css');
cx_assert_true(strpos($ccs_css, '#063226') !== false, 'Human composer dark-green color must remain');

$avatar_presets = file_get_contents($customer_dir . '/class-paxdesign-customer-avatar-presets.php');
cx_assert_true(strpos($avatar_presets, 'const COUNT = 100') !== false, 'Avatar presets must support 100 GIF avatars');
cx_assert_true(strpos($avatar_presets, 'random_id') !== false, 'Avatar presets must expose random_id()');

$avatar = file_get_contents($customer_dir . '/class-paxdesign-customer-avatar.php');
cx_assert_true(strpos($avatar, 'ensure_preset_assigned') !== false, 'Customer avatar must auto-assign presets');
cx_assert_true(strpos($avatar, "add_action('pdx_user_logged_in'") !== false, 'Customer avatar must assign preset on login');
cx_assert_true(strpos($avatar, "add_action('user_register'") !== false, 'Customer avatar must assign preset on register');

$avatar_dir = dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/images/avatars';
$avatar_gifs = glob($avatar_dir . '/pax-*.gif') ?: array();
cx_assert_true(count($avatar_gifs) === 100, 'Expected 100 avatar GIF assets');

$pax_auth_js = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/js/pax-auth.js');
cx_assert_true(strpos($pax_auth_js, 'pax-\\d{2,3}') !== false, 'Account JS must support 3-digit avatar preset ids');
cx_assert_true(strpos($pax_auth_js, 'pdx-account-avatar-picker__item--locked') !== false, 'Account JS must render locked VIP avatars');
cx_assert_true(strpos($pax_auth_js, 'Sign in with GitHub') !== false, 'Account JS must offer Sign in with GitHub');

$vip_presets = file_get_contents($customer_dir . '/class-paxdesign-customer-avatar-vip-presets.php');
cx_assert_true(strpos($vip_presets, 'const COUNT = 10') !== false, 'VIP avatar presets must define 10 exclusive avatars');

$vip_avatar = file_get_contents($customer_dir . '/class-paxdesign-customer-avatar.php');
cx_assert_true(strpos($vip_avatar, 'grant_vip_avatar') !== false, 'Customer avatar must support VIP grant');
cx_assert_true(strpos($vip_avatar, 'META_VIP_GRANTS') !== false, 'Customer avatar must store VIP grants');

$vip_dir = dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/images/avatars-vip';
$vip_gifs = glob($vip_dir . '/pax-vip-*.gif') ?: array();
cx_assert_true(count($vip_gifs) === 10, 'Expected 10 VIP GIF assets');

$levels = file_get_contents($customer_dir . '/class-paxdesign-customer-levels.php');
cx_assert_true(strpos($levels, 'META_LEVEL') !== false, 'Customer levels must store level meta');

$master = file_get_contents($customer_dir . '/class-paxdesign-customer-master-admin.php');
cx_assert_true(strpos($master, 'awjime29@icloud.com') !== false, 'Master admin iCloud email must be configured');
cx_assert_true(strpos($master, 'ftbkvmfy6g@privaterelay.appleid.com') !== false, 'Master admin Apple relay email must be configured');
cx_assert_true(strpos($master, 'master_emails') !== false, 'Master admin must expose master_emails()');
cx_assert_true(strpos($master, 'find_master_user') !== false, 'Master admin must resolve canonical user by alias email');

$apple_auth = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-apple.php');
cx_assert_true(strpos($apple_auth, 'find_master_user') !== false, 'Apple auth must link master admin relay login to existing account');

$master_rest = file_get_contents($customer_dir . '/class-paxdesign-customer-master-rest.php');
cx_assert_true(strpos($master_rest, '/customer/master/customers') !== false, 'Master admin customer routes must exist');
cx_assert_true(strpos($master_rest, 'PAXdesign_Customer_Registry') !== false, 'Master admin list must use customer registry');

$registry = file_get_contents($customer_dir . '/class-paxdesign-customer-registry.php');
cx_assert_true(strpos($registry, 'account_email') !== false, 'Customer registry must resolve account email');
cx_assert_true(strpos($registry, 'ensure_portal_customer') !== false, 'Customer registry must auto-register portal customers');
cx_assert_true(strpos($registry, 'backfill_existing_portal_customers') !== false, 'Customer registry must backfill existing customers');

$apple_auth = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-apple.php');
cx_assert_true(strpos($apple_auth, 'maybe_sync_account_email') !== false, 'Apple auth must sync account email on login');

$vip_presets_php = file_get_contents($customer_dir . '/class-paxdesign-customer-avatar-vip-presets.php');
cx_assert_true(strpos($vip_presets_php, 'catalog_preview') !== false, 'VIP presets must expose admin preview catalog');

cx_assert_true(strpos($pax_auth_js, 'renderAdminCustomerPreviewPanel') !== false, 'Account JS must render admin customer preview');
cx_assert_true(strpos($pax_auth_js, 'pdx-account-name-line') !== false, 'Account JS must use dedicated name line wrapper');

$verified_css = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/css/pdx-verified-badge.css');
cx_assert_true(strpos($verified_css, 'pdx-account-name-line') !== false, 'Verified badge CSS must style account name line separately');
cx_assert_true(strpos($verified_css, 'pdx-account-name-text') !== false, 'Verified badge CSS must style account name text');
cx_assert_true(strpos($pax_auth_js, 'pdx-admin-page-next') !== false, 'Account JS must paginate admin customer list');
cx_assert_true(strpos($pax_auth_js, 'renderAdminCustomerAvatarPickers') !== false, 'Account JS must render admin avatar pickers');
cx_assert_true(strpos($pax_auth_js, 'isMasterAdminUser()') !== false && strpos($pax_auth_js, 'preset.locked && !isMasterAdminUser()') !== false, 'Master admin must preview unlocked VIP avatars');

cx_assert_true(strpos($pax_auth_js, 'pdx-account-level-badge') !== false, 'Account JS must render level badges');
cx_assert_true(strpos($pax_auth_js, 'administration') !== false, 'Account JS must include administration section');

$pdx_customers = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-customers.php');
cx_assert_true(strpos($pdx_customers, 'META_ADMIN_NOTES') !== false, 'Booking customers must support admin notes without toolbar');
cx_assert_true(strpos($pdx_customers, 'save_notes') !== false, 'Booking customers must save admin notes');

$migration = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/customer/class-paxdesign-toolbar-migration.php');
cx_assert_true(strpos($migration, 'PAXdesign_Toolbar_Migration') !== false, 'Toolbar migration class must exist');
cx_assert_true(strpos($migration, 'pax_toolbar_migration_backup') !== false, 'Toolbar migration must export backup');

$deploy = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/deploy-customer-platform-3135.yml');
cx_assert_true(strpos($deploy, 'rsync -az --delete -e "ssh ${SSH_OPTS[*]}" paxdesign-toolbar/') === false, 'Deploy workflow must not rsync toolbar');
cx_assert_true(strpos($deploy, 'wp-eval-toolbar-customer-migration.php') !== false, 'Deploy workflow must run toolbar customer migration');

$repo_root = dirname(__DIR__, 2);
cx_assert_true(!is_dir($repo_root . '/paxdesign-toolbar'), 'paxdesign-toolbar directory must be removed from repository');

echo "OK: customer platform static verification passed (" . count($files) . " modules)\n";
