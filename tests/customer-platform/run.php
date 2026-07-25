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

$customer_orders = file_get_contents($customer_dir . '/class-paxdesign-customer-orders.php');
cx_assert_true(strpos($customer_orders, 'upcoming_bookings_for_user') !== false, 'Customer orders must expose upcoming bookings for AI context');

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

echo "OK: customer platform static verification passed (" . count($files) . " modules)\n";
