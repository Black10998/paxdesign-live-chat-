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
cx_assert_true(strpos($auth_frontend, 'githubWebEnabled') !== false, 'Auth frontend must expose GitHub login config');
cx_assert_true(strpos($auth_frontend, 'customerLevel') !== false, 'Auth frontend must expose customer membership level');
cx_assert_true(strpos($auth_frontend, 'appleWebEnabled') !== false, 'Auth frontend must keep Sign in with Apple');

$auth_js = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/js/pax-auth.js');
cx_assert_true(strpos($auth_js, 'renderHeaderUserIdentityHtml') !== false, 'Logged-in header must render customer identity');
cx_assert_true(strpos($auth_js, 'pdx-auth-account-identity') !== false, 'Logged-in header must use account identity mount');
cx_assert_true(strpos($auth_js, 'renderCustomerLevelBadge') !== false, 'Logged-in header must show membership level badge');
cx_assert_true(strpos($auth_js, 'headerMembershipLabel') !== false, 'Header must render a compact Gold/Premium membership label');
cx_assert_true(strpos($auth_js, 'headerDisplayName') !== false, 'Header must use a non-email customer display name');
cx_assert_true(strpos($auth_js, "showName: true") !== false, 'Logged-in header must show name and membership on all viewports');
cx_assert_true(strpos($auth_js, 'githubSignInButtonInnerHtml') !== false, 'GitHub login button must remain in web auth');
cx_assert_true(strpos($auth_js, 'appleSignInButtonInnerHtml') !== false, 'Apple login button must remain in web auth');

$auth_css = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/css/pdx-auth.css');
cx_assert_true(strpos($auth_css, '.pdx-header-user-identity') !== false, 'Header CSS must style customer identity');
cx_assert_true(strpos($auth_css, '.pdx-account-level-badge--header') !== false, 'Header CSS must style membership badge');

$levels = file_get_contents($customer_dir . '/class-paxdesign-customer-levels.php');
cx_assert_true(strpos($levels, 'function profile_fields') !== false, 'Customer levels must expose profile_fields');
cx_assert_true(strpos($levels, "Level %1\$s %2\$s") !== false, 'Level badge text must be Level NN Metal without dashes');

$github_auth = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-github.php');
cx_assert_true(strpos($github_auth, '/pdx/v1/auth/github/callback') !== false, 'GitHub OAuth must use the registered HTTPS callback');
cx_assert_true(strpos($github_auth, "APP_CALLBACK_SCHEME   = 'paxlivechat'") !== false, 'GitHub iOS flow must return to the app scheme');
cx_assert_true(strpos($github_auth, '://auth/github') !== false, 'GitHub iOS callback must use auth/github path');
cx_assert_true(strpos($github_auth, 'mobile_login_for_user') !== false, 'GitHub iOS flow must mint a PAXDesign mobile session');

$auth_rest = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-rest.php');
cx_assert_true(strpos($auth_rest, '/auth/github/start') !== false, 'Missing GitHub OAuth start route');
cx_assert_true(strpos($auth_rest, '/auth/github/callback') !== false, 'Missing GitHub OAuth callback route');
cx_assert_true(strpos($auth_rest, '/auth/github/complete') !== false, 'Missing GitHub iOS complete route');
cx_assert_true(strpos($auth_rest, 'PAXdesign_Customer_Auth::user_payload') !== false, '/auth/me must return full customer identity payload');

$notifications = file_get_contents($customer_dir . '/class-paxdesign-customer-notifications.php');
cx_assert_true(strpos($notifications, 'mark_read_many') !== false, 'Notifications must support bulk mark-read');
cx_assert_true(strpos($notifications, 'function mark_all_read') !== false, 'Notifications must support mark-all-read');
cx_assert_true(strpos($notifications, 'is_read = 1') !== false, 'Mark-read must persist is_read');

cx_assert_true(strpos($rest, '/customer/notifications/read') !== false, 'Missing POST /customer/notifications/read alias');
cx_assert_true(strpos($rest, '/customer/notifications/read-all') !== false, 'Missing POST /customer/notifications/read-all');
cx_assert_true(strpos($rest, 'notification_ids_from_request') !== false, 'Mark-read must parse JSON ids robustly');
cx_assert_true(strpos($rest, 'notification_mark_all_from_request') !== false, 'Mark-read must accept all=true');

$ios_root = dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat';
$secret = '23161838e68470e36f9f6f38bf309d239fb988e5';
$swift_iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ios_root . '/PAXDesignLiveChat'));
foreach ($swift_iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'swift') {
        continue;
    }
    cx_assert_true(strpos((string) file_get_contents($file->getPathname()), $secret) === false, 'GitHub client secret must not appear in iOS source: ' . $file->getFilename());
}

$ios_home = file_get_contents($ios_root . '/PAXDesignLiveChat/Features/CustomerPortal/Features/Homepage/CustomerPremiumHomeExperience.swift');
cx_assert_true(strpos($ios_home, 'PAXQuickActionButton') !== false, 'Home quick actions must use circular PAXQuickActionButton');
cx_assert_true(strpos($ios_home, 'CustomerHomeUtilityRow') === false, 'Home must not use large horizontal utility chips');
cx_assert_true(strpos($ios_home, 'CustomerHomeMetricsBoard') !== false, 'Home must include a metrics board');

$ios_login = file_get_contents($ios_root . '/PAXDesignLiveChat/Features/Login/LoginView.swift');
cx_assert_true(strpos($ios_login, 'PAXContinueWithGitHubButton') !== false, 'Login must include Continue with GitHub');

$ios_api = file_get_contents($ios_root . '/PAXDesignLiveChat/Features/CustomerPortal/Core/CustomerAPIClient.swift');
cx_assert_true(strpos($ios_api, '/auth/github/complete') !== false, 'iOS client must complete GitHub login via backend ticket');
cx_assert_true(strpos($ios_api, '/customer/notifications/read') !== false, 'iOS client must POST mark-read fallback');
cx_assert_true(strpos($ios_api, '/customer/notifications/read-all') !== false, 'iOS client must POST mark-all-read');
cx_assert_true(strpos($ios_api, 'markAllNotificationsRead') !== false, 'iOS client must expose mark-all-read');

$ios_github_button = file_get_contents($ios_root . '/PAXDesignLiveChat/Features/Login/PAXContinueWithGitHubButton.swift');
cx_assert_true(strpos($ios_github_button, 'GitHubMark') !== false, 'Continue with GitHub must use the official GitHubMark asset');
cx_assert_true(strpos($ios_github_button, 'chevron.left.forwardslash.chevron.right') === false, 'Continue with GitHub must not use a generic SF Symbol');

$github_mark = $ios_root . '/PAXDesignLiveChat/Resources/Assets.xcassets/PAXIcons/GitHubMark.imageset/GitHubMark.svg';
cx_assert_true(is_file($github_mark), 'Official GitHub Invertocat SVG asset is missing');
$github_svg = (string) file_get_contents($github_mark);
cx_assert_true(strpos($github_svg, 'M12 .297c-6.63 0-12 5.373-12 12') !== false, 'GitHubMark.svg must use the official Invertocat path');
cx_assert_true(preg_match('/[^\x00-\x7F]/', $github_svg) !== 1, 'GitHubMark.svg must be ASCII so Xcode asset catalogs can parse it');
$github_imageset = (string) file_get_contents($ios_root . '/PAXDesignLiveChat/Resources/Assets.xcassets/PAXIcons/GitHubMark.imageset/Contents.json');
cx_assert_true(strpos($github_imageset, 'template-rendering-intent') !== false, 'GitHub mark must template-tint for Light/Dark');

$ios_badge_store = file_get_contents($ios_root . '/PAXDesignLiveChat/Features/CustomerPortal/Core/CustomerNotificationsBadgeStore.swift');
cx_assert_true(strpos($ios_badge_store, 'markAllRead') !== false, 'Badge store must persist a mark-all-read watermark');
cx_assert_true(strpos($ios_badge_store, 'clearAfterMarkAllRead') !== false, 'Badge store must zero immediately after mark-all-read');

$launch_view = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/Launch/PAXLaunchView.swift');
cx_assert_true(strpos($launch_view, 'PAXAnimatedLogoView') !== false, 'Launch screen must use PAXAnimatedLogoView');
cx_assert_true(strpos($launch_view, 'Color.black') !== false, 'Launch screen must use pure black background');

$animated_logo = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/Launch/PAXAnimatedLogoView.swift');
cx_assert_true(strpos($animated_logo, 'holdDuration') !== false && strpos($animated_logo, '1.2') !== false, 'Logo animation must preserve website hold timing');

echo "OK: customer platform static verification passed (" . count($files) . " modules)\n";
