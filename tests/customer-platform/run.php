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
cx_assert_true(strpos($chat, 'prepare_authenticated_llm_messages') !== false, 'Authenticated CCS chat must keep session history instead of isolating the latest message');
$chat_js = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/js/chat-script.js');
cx_assert_true(strpos($chat_js, 'PAXdesignPageContext.referenceId = report.reference_id') !== false, 'Chat must pin the new CCS reference so the next message does not reuse the previous case');

$language_routing = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-language-routing.php');
cx_assert_true(strpos($language_routing, 'resolve_session_language') !== false, 'Language routing must resolve sticky session language');
cx_assert_true(strpos($language_routing, 'persist_session_language') !== false, 'Language routing must persist session language');
cx_assert_true(strpos($language_routing, 'detect_language_preference') !== false, 'Language routing must detect language-preference messages');

$chat_knowledge = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-chat-knowledge.php');
cx_assert_true(strpos($chat_knowledge, 'build_customer_account_context_block') !== false, 'Chat knowledge must build customer account context block');
cx_assert_true(strpos($chat_knowledge, 'this is NEVER a new conversation') !== false, 'CCS prompt must forbid generic greetings while a case is active');
cx_assert_true(strpos($chat_knowledge, 'language-preference message') !== false, 'CCS prompt must treat language names as a switch on the same case');

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
cx_assert_true(strpos($auth_frontend, 'pdx-auth-mobile-header-fix') !== false, 'Auth frontend must override leftover mobile Sign trigger CSS');

$auth_js = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/js/pax-auth.js');
cx_assert_true(strpos($auth_js, 'renderHeaderUserIdentityHtml') !== false, 'Logged-in header must render customer identity');
cx_assert_true(strpos($auth_js, 'pdx-auth-account-identity') !== false, 'Logged-in header must use account identity mount');
cx_assert_true(strpos($auth_js, 'renderCustomerLevelBadge') !== false, 'Logged-in header must show membership level badge');
cx_assert_true(strpos($auth_js, 'headerMembershipLabel') !== false, 'Header must render a compact Gold/Premium membership label');
cx_assert_true(strpos($auth_js, 'headerDisplayName') !== false, 'Header must use a non-email customer display name');
cx_assert_true(strpos($auth_js, "showName: true") !== false, 'Logged-in header must show name and membership on all viewports');
cx_assert_true(strpos($auth_js, 'syncHeaderAuthCtas') !== false, 'Header must keep Sign In as the primary logged-out CTA');
cx_assert_true(strpos($auth_js, 'sanitizeHeaderAuthControls') === false, 'Header sanitizer must not delete Sign In');
cx_assert_true(strpos($auth_js, 'pdx-auth-signin-btn') !== false, 'Logged-out header must include a Sign In control');
cx_assert_true(strpos($auth_js, 'class="pdx-auth-signup-btn') === false, 'Homepage header must not include a Sign Up button');
cx_assert_true(strpos($auth_js, "navigateToAuthPage('login')") !== false, 'Sign In must open the login authentication flow');
cx_assert_true(strpos($auth_js, "navigateToAuthPage('register')") !== false, 'Account page registration flow must remain available');
cx_assert_true(strpos($auth_js, 'githubSignInButtonInnerHtml') !== false, 'GitHub login button must remain in web auth');
cx_assert_true(strpos($auth_js, 'appleSignInButtonInnerHtml') !== false, 'Apple login button must remain in web auth');
cx_assert_true(strpos($auth_js, 'syncAccountHeaderOffset') !== false, 'Account drawer must sit below the measured header');
cx_assert_true(strpos($auth_js, 'class="pdx-auth-trigger"') === false, 'Auth bar markup must not include the legacy Sign trigger');

$auth_css = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/css/pdx-auth.css');
cx_assert_true(strpos($auth_css, '.pdx-header-user-identity') !== false, 'Header CSS must style customer identity');
cx_assert_true(strpos($auth_css, '.pdx-account-level-badge--header') !== false, 'Header CSS must style membership badge');
cx_assert_true(strpos($auth_css, 'html body .pdx-auth-trigger') !== false, 'Header CSS must hide the leftover Sign trigger');
cx_assert_true(strpos($auth_css, 'html body .pdx-auth-signup-btn') !== false, 'Header CSS must hide leftover Sign Up controls');
cx_assert_true(strpos($auth_css, 'text-overflow: clip') !== false, 'Sign In must not be clipped on compact headers');
cx_assert_true(strpos($auth_css, '.pdx-auth-signin-btn:not([hidden])') !== false, 'Compact header CSS must keep Sign In visible when logged out');

$auth_frontend = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-frontend.php');
cx_assert_true(strpos($auth_frontend, '.pdx-auth-signin-btn{display:none') === false, 'Footer CSS must not hide the homepage Sign In control');
cx_assert_true(strpos($auth_frontend, '.pdx-auth-signin-btn:not([hidden])') !== false, 'Footer CSS must keep Sign In visible on compact headers');
cx_assert_true(strpos($auth_frontend, '#pdx-auth-bar .pdx-auth-signup-btn') !== false, 'Footer CSS must hide leftover Sign Up controls');

$account_app_css = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/css/pdx-account-app.css');
cx_assert_true(strpos($account_app_css, '--pdx-account-header-height') !== false, 'Mobile account drawer must start below the header');
cx_assert_true(strpos($account_app_css, '.pdx-account-sidebar-user') !== false, 'Mobile account drawer must not duplicate header identity');

$auth_page_css = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/assets/customer-auth/css/pdx-auth-page.css');
cx_assert_true(strpos($auth_page_css, '--pdx-account-header-height') !== false, 'Account overlay must start below the header');

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

$ccs_root = dirname(__DIR__, 2) . '/paxdesign-booking';
$ccs_checks = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-document-checks.php');
cx_assert_true(strpos($ccs_checks, 'class PAXdesign_Cybercrime_Document_Checks') !== false, 'Document quality checks class must exist');
cx_assert_true(strpos($ccs_checks, 'legal_verification') !== false, 'Document checks must record they are not legal verification');
cx_assert_true(strpos($ccs_checks, 'appears_expired') !== false, 'Document checks must flag expired-looking files');
cx_assert_true(strpos($ccs_checks, 'duplicate_in_submission') !== false, 'Document checks must detect duplicate uploads');
cx_assert_true(strpos($ccs_checks, 'customer_corrections') !== false, 'Document checks must tell the customer what to correct');

$ccs_remind = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-admin-reminders.php');
cx_assert_true(strpos($ccs_remind, 'A Cybercrime Support request requires your review.') !== false, 'Admin reminder email must use the required review subject');
cx_assert_true(strpos($ccs_remind, 'paxdesign_cybercrime_admin_review_reminders') !== false, 'Admin reminder cron hook must be registered');
cx_assert_true(strpos($ccs_remind, 'tab=cybercrime&reference=') !== false, 'Admin reminder must link to the exact case');

$ccs_chat = file_get_contents($ccs_root . '/includes/class-paxdesign-chat-knowledge.php');
cx_assert_true(strpos($ccs_chat, 'TWO interfaces on the SAME CCS case') !== false, 'Live Chat must write into the real CCS case');
cx_assert_true(strpos($ccs_chat, 'Never restart the questionnaire') !== false, 'Assistant must stay on the existing reference');
cx_assert_true(strpos($ccs_chat, 'Do not ask for information that is already listed') !== false, 'Assistant must not re-ask saved fields');
cx_assert_true(strpos($ccs_chat, 'never greet as a new visitor') !== false, 'Assistant must not restart on follow-up status messages');

$ccs_ai = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-ai-case.php');
cx_assert_true(strpos($ccs_ai, 'class PAXdesign_Cybercrime_AI_Case') !== false, 'AI case sync class must exist');
cx_assert_true(strpos($ccs_ai, 'ingest_chat_message') !== false, 'Chat must ingest facts into the CCS case');
cx_assert_true(strpos($ccs_ai, 'login_required_payload') !== false, 'CCS chat must require authentication');
cx_assert_true(strpos($ccs_ai, 'user_can_view_report') !== false, 'AI case writes must be ownership-checked');
cx_assert_true(strpos($ccs_ai, 'get_reference_for_session') !== false, 'CCS chat must stay bound to the same case after follow-up messages');

$ccs_ops_src = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-ai-operations.php');
cx_assert_true(strpos($ccs_ops_src, 'class PAXdesign_Cybercrime_AI_Operations') !== false, 'AI operations class must exist');
cx_assert_true(strpos($ccs_ops_src, 'start_document_check') !== false, 'File checks must create a tracked operation');
cx_assert_true(strpos($ccs_ops_src, 'still_running_copy') !== false, 'Running operations must have a status reply');
cx_assert_true(strpos($ccs_ops_src, 'Your files are still being checked') !== false, 'Status probe during a check must not restart the chat');
cx_assert_true(strpos($ccs_ops_src, 'ai_operations') !== false, 'Operations must persist on the same CCS case payload');

$ccs_chat_php = file_get_contents($ccs_root . '/includes/class-paxdesign-chat.php');
cx_assert_true(strpos($ccs_chat_php, 'ingest_ccs_case_from_chat') !== false, 'Authenticated chat must persist CCS facts before the model replies');
cx_assert_true(strpos($ccs_chat_php, 'ccs_case') !== false, 'Chat must emit case sync events to the page');
cx_assert_true(strpos($ccs_chat_php, 'apply_ccs_operation_turn') !== false, 'Chat must load CCS operation state before replying');
cx_assert_true(strpos($ccs_chat_php, 'ccs_operation') !== false, 'Chat must emit a visible processing operation to the conversation');
cx_assert_true(strpos($ccs_chat_php, 'paxdesign_chat_ccs_attach') !== false, 'CCS chat must accept evidence uploads on the same case');

$ccs_chat_js = file_get_contents($ccs_root . '/assets/js/chat-script.js');
cx_assert_true(strpos($ccs_chat_js, 'uploadCcsChatFile') !== false, 'CCS chat + button must upload evidence in-conversation');
cx_assert_true(strpos($ccs_chat_js, 'initCcsAttachButton') !== false, 'CCS chat must use an attachment + button');
cx_assert_true(strpos($ccs_chat_js, 'paxdesign_chat_ccs_attach') !== false, 'Website CCS chat must post uploads to the same CCS case');

$ccs_intake = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-intake.php');
cx_assert_true(strpos($ccs_intake, 'document_checks') !== false, 'Intake must store document check results on the case');
cx_assert_true(strpos($ccs_intake, 'document_check_failed') !== false, 'Intake must reject unreadable identity documents before creating a new case');
cx_assert_true(strpos($ccs_intake, 'create_draft_for_user') !== false, 'Chat must open one draft CCS case instead of a second case');
cx_assert_true(strpos($ccs_intake, 'complete_draft_report') !== false, 'The case page form must complete the same draft reference');

$ccs_tickets = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-tickets.php');
cx_assert_true(strpos($ccs_tickets, 'paxdesign_cybercrime_customer_resubmit') !== false, 'Customers must resubmit files on the same reference');
cx_assert_true(strpos($ccs_tickets, 'append_customer_evidence') !== false, 'Same-reference evidence corrections must be implemented');
cx_assert_true(strpos($ccs_tickets, 'original_request') !== false, 'Case payload must expose the original request');
cx_assert_true(strpos($ccs_tickets, 'needs_human_review') !== false, 'Tickets must expose human-review flags');
cx_assert_true(strpos($ccs_tickets, "'draft'") !== false, 'Draft status must remain an active CCS case');
cx_assert_true(strpos($ccs_tickets, 'missing_case_fields') !== false, 'Case page must expose missing fields to chat');
cx_assert_true(strpos($ccs_tickets, "'rejected'") !== false, 'Rejected must be a supported CCS case status');
cx_assert_true(strpos($ccs_tickets, 'display_case_description') !== false, 'Case display must replace pasted chat dumps with structured facts');

$ccs_admin = file_get_contents($ccs_root . '/includes/customer/class-paxdesign-customer-admin.php');
cx_assert_true(strpos($ccs_admin, 'alerts.human_review') !== false, 'Admin list must flag cases that need human review');
cx_assert_true(strpos($ccs_admin, 'detail.checks') !== false, 'Admin case view must show preliminary document checks');
cx_assert_true(strpos($ccs_admin, 'detail.evidence') !== false, 'Admin case view must show an evidence gallery');
cx_assert_true(strpos($ccs_admin, 'pax-cc-evidence') !== false, 'Admin evidence must use preview cards');
cx_assert_true(strpos($ccs_admin, 'pax-cc-reject-ticket') !== false, 'Admin must be able to reject a CCS case');
cx_assert_true(strpos($ccs_admin, 'pax-cc-reject-panel') !== false, 'Admin rejection must collect a reason');
cx_assert_true(strpos($ccs_admin, 'cc_t(') !== false, 'Admin CCS dashboard must use the shared localization helper');

$ccs_i18n = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-i18n.php');
cx_assert_true(strpos($ccs_i18n, 'class PAXdesign_Cybercrime_I18n') !== false, 'CCS admin i18n class must exist');
cx_assert_true(strpos($ccs_i18n, "'ar' => 'مرفوض'") !== false, 'Arabic rejected status must be localized');
cx_assert_true(strpos($ccs_i18n, "'ar' => 'تمت الموافقة'") !== false, 'Arabic approved status must be localized');
cx_assert_true(strpos($ccs_i18n, 'status_icon_svg') !== false, 'Professional status icons must be SVG');
cx_assert_true(strpos($ccs_i18n, 'reject.reason.unclear_document') !== false, 'Rejection reasons must be localized');

cx_assert_true(strpos($ccs_tickets, 'public_case_sync_for_session') !== false, 'Chat poll must be able to read the same CCS case status');
cx_assert_true(strpos($ccs_tickets, 'send_customer_nocache_headers') !== false, 'Customer case AJAX must not be cached');
cx_assert_true(strpos($ccs_tickets, 'LIVE STATUS') !== false, 'AI chat must read the live CCS status on every reply');
cx_assert_true(strpos($ccs_tickets, 'public_rejection') !== false, 'Customer payload must expose the rejection decision');
cx_assert_true(strpos($ccs_tickets, "'reason_i18n'") !== false, 'Rejection reasons must be stored in AR/DE/EN');

$ccs_js = file_get_contents(dirname(__DIR__, 2) . '/navein/assets/js/apple-cybercrime-support.js');
cx_assert_true(strpos($ccs_js, 'initGuidedInterview') !== false, 'Website intake must run a guided interview');
cx_assert_true(strpos($ccs_js, 'renderCaseDossier') !== false, 'Returning customers must see the existing case dossier');
cx_assert_true(strpos($ccs_js, 'paxdesign_cybercrime_customer_resubmit') !== false, 'Website must allow same-case file corrections');
cx_assert_true(strpos($ccs_js, 'pax-ccs-case-updated') !== false, 'Case page must refresh when chat saves the same case');
cx_assert_true(strpos($ccs_js, "setPageContext(root.getAttribute('data-ccs-lang') || 'ar', merged.reference_id)") !== false, 'A new CCS reference from chat must become the page conversation reference');
cx_assert_true(strpos($ccs_js, "cache: 'no-store'") !== false, 'Case status polling must bypass cached GET responses');
cx_assert_true(strpos($ccs_js, "method: 'POST'") !== false, 'Case status polling must use POST');
cx_assert_true(strpos($ccs_js, 'applyIncomingReport') !== false, 'Live admin status changes must apply without a manual refresh');
cx_assert_true(strpos($ccs_js, 'pollActiveReport') !== false, 'The open CCS case must keep polling while it is on screen');
cx_assert_true(strpos($ccs_js, 'REPORT_POLL_MS') !== false, 'Customer case polling interval must be defined');
cx_assert_true(strpos($ccs_js, 'caseSyncTimestamp') !== false, 'Live case updates must ignore stale overlapping responses');
cx_assert_true(strpos($ccs_js, 'statusLabelForReport') !== false, 'Customer status labels must follow the selected portal language');
cx_assert_true(strpos($ccs_js, 'appendLocale(body)') !== false, 'Customer AJAX must send the selected portal language');
cx_assert_true(strpos($ccs_js, 'continueDraftOnPage') !== false, 'Draft cases must remain completable on the existing page');
cx_assert_true(strpos($ccs_js, 'isStructuredCaseDescription') !== false, 'Case dossier must hide pasted chat paragraphs');
cx_assert_true(strpos($ccs_js, 'rejected') !== false, 'Customer case view must treat Rejected as a closed status');
cx_assert_true(strpos($ccs_js, 'statusIconSvg') !== false, 'Customer status must use professional SVG icons');
cx_assert_true(strpos($ccs_js, 'renderDecisionCard') !== false, 'Customer case page must present the rejection decision clearly');
cx_assert_true(strpos($ccs_js, 'emoji') === false, 'Customer status badges must not use random emojis');

$ccs_page = file_get_contents(dirname(__DIR__, 2) . '/navein/template-parts/pages/cybercrime-support.php');
cx_assert_true(strpos($ccs_page, 'pax-ccs-category-cards') !== false, 'Intake must present category as guided choices');
cx_assert_true(strpos($ccs_page, 'pax-ccs-case-dossier') !== false, 'Active case view must include the case dossier');
cx_assert_true(strpos($ccs_page, 'name="identity_document"') !== false, 'Identity document upload must remain in the intake form');
cx_assert_true(strpos($ccs_page, 'pax-ccs-continue-form') !== false, 'Existing case page must keep a continue path');
cx_assert_true(strpos($ccs_page, 'pax-ccs-decision-card') !== false, 'Case page must include an Apple-style decision card');

$ccs_widget = file_get_contents($ccs_root . '/assets/js/chat-script.js');
cx_assert_true(strpos($ccs_widget, 'isCybercrimeCaseChat') !== false, 'CCS chat must force the Sign In gate');
cx_assert_true(strpos($ccs_widget, 'dispatchCcsCaseUpdate') !== false, 'Chat must notify the case page after a save');
cx_assert_true(strpos($ccs_widget, "dispatchCcsCaseUpdate(data.ccs_case);") !== false, 'Chat poll must push the live CCS case status to the website');
cx_assert_true(strpos($ccs_widget, 'ccs_operation') !== false, 'Chat must render a processing state for CCS operations');
cx_assert_true(strpos($ccs_widget, 'upsertCcsOperationMessage') !== false, 'Chat must keep the same processing message across follow-ups');

$plugin_bootstrap = file_get_contents($ccs_root . '/paxdesign-booking.php');
cx_assert_true(strpos($plugin_bootstrap, "define('PAXDESIGN_BOOKING_VERSION', '3.175.13')") !== false, 'Plugin version must be 3.175.13');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-i18n.php') !== false, 'Plugin must load CCS localization');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-document-checks.php') !== false, 'Plugin must load document checks');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-ai-case.php') !== false, 'Plugin must load AI case sync');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-ai-operations.php') !== false, 'Plugin must load AI operation state');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-ai-workflow.php') !== false, 'Plugin must load the 4-step CCS AI workflow');
cx_assert_true(strpos($plugin_bootstrap, 'PAXdesign_Cybercrime_Admin_Reminders::init') !== false, 'Plugin must boot admin review reminders');

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        return trim(strip_tags((string) $value));
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($value) {
        return strtolower(preg_replace('/[^a-z0-9_]/', '', (string) $value));
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html')) {
    function esc_html($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('__')) {
    function __($text, $domain = '') {
        unset($domain);
        return (string) $text;
    }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle) {
        $pos = strpos((string) $haystack, (string) $needle);
        return $pos === false ? false : $pos;
    }
}
require_once $ccs_root . '/includes/class-paxdesign-cybercrime-i18n.php';
require_once $ccs_root . '/includes/class-paxdesign-cybercrime-ai-case.php';
cx_assert_true(PAXdesign_Cybercrime_I18n::t('status.rejected', 'ar') === 'مرفوض', 'Arabic rejected status must read مرفوض');
cx_assert_true(PAXdesign_Cybercrime_I18n::t('status.resolved', 'ar') === 'تمت الموافقة', 'Arabic approved status must read تمت الموافقة');
cx_assert_true(PAXdesign_Cybercrime_I18n::t('status.in_review', 'de') === 'In Prüfung', 'German under-review status must remain available');
cx_assert_true(strpos(PAXdesign_Cybercrime_I18n::status_icon_svg('rejected'), '<circle') !== false, 'Rejected status must use a circular SVG icon');
cx_assert_true(strpos(PAXdesign_Cybercrime_I18n::rejection_reason_text('unclear_document', 'ar'), 'غير واضح') !== false, 'Arabic rejection reason must explain the decision');
cx_assert_true(PAXdesign_Cybercrime_I18n::t('next.rejected', 'ar') !== PAXdesign_Cybercrime_I18n::t('next.rejected', 'en'), 'Rejected next-action copy must be localized');
cx_assert_true(strpos(PAXdesign_Cybercrime_I18n::status_changed_text('rejected', 'ar'), 'مرفوض') !== false, 'Arabic timeline must say the case was rejected');
cx_assert_true(PAXdesign_Cybercrime_I18n::localize_canned('Please sign in.', 'ar') === PAXdesign_Cybercrime_I18n::t('error.login', 'ar'), 'Canned English customer errors must localize');
$extracted = PAXdesign_Cybercrime_AI_Case::extract_fields_from_message(
    'My account was compromised on August 12. GitHub and my email were affected. I did not lose any money.',
    array()
);
cx_assert_true(($extracted['category'] ?? '') === 'account_takeover', 'Extractor must detect incident type from a natural message');
cx_assert_true(($extracted['incident_date'] ?? '') === gmdate('Y') . '-08-12', 'Extractor must detect the incident date');
cx_assert_true(strpos((string) ($extracted['platforms'] ?? ''), 'GitHub') !== false, 'Extractor must detect GitHub');
cx_assert_true(stripos((string) ($extracted['platforms'] ?? ''), 'Email') !== false, 'Extractor must detect email as an affected platform');
cx_assert_true(($extracted['financial_loss'] ?? '') === 'No', 'Extractor must detect that no money was lost');
cx_assert_true(strpos((string) ($extracted['description'] ?? ''), 'My account was compromised') === false, 'Case description must not copy the full customer chat message');
cx_assert_true(strpos((string) ($extracted['description'] ?? ''), 'Date:') !== false, 'Case description must be a structured summary');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_chat_dump_description('My account was compromised on August 12. GitHub and my email were affected. I did not lose any money.') === false, 'A single short message is not treated as a dump by length');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_chat_dump_description(str_repeat('Customer chat dump. ', 40)) === true, 'Long pasted chat transcripts must be treated as dumps');

$merged = PAXdesign_Cybercrime_AI_Case::merge_platform_list('GitHub', array('Gmail'), false);
cx_assert_true($merged === 'GitHub, Gmail' || strpos($merged, 'Gmail') !== false, 'Additional platforms must merge into the same case');

if (!function_exists('mb_strlen')) {
    function mb_strlen($string) {
        return strlen((string) $string);
    }
}
require_once $ccs_root . '/includes/class-paxdesign-language-routing.php';
require_once $ccs_root . '/includes/class-paxdesign-cybercrime-ai-operations.php';
cx_assert_true(PAXdesign_Language_Routing::detect_language_preference('arabic') === 'ar', 'arabic must switch session language to Arabic');
cx_assert_true(PAXdesign_Language_Routing::detect_language_preference('English') === 'en', 'English must switch session language to English');
cx_assert_true(PAXdesign_Language_Routing::detect_language_preference('Deutsch') === 'de', 'Deutsch must switch session language to German');
cx_assert_true(PAXdesign_Language_Routing::detect_language_preference('العربية') === 'ar', 'العربية must switch session language to Arabic');
cx_assert_true(PAXdesign_Language_Routing::detect_language_preference('in Arabic') === 'ar', 'in Arabic must be a language preference, not a new chat');
cx_assert_true(PAXdesign_Language_Routing::detect_language_preference('auf Deutsch') === 'de', 'auf Deutsch must be a language preference, not a new chat');
cx_assert_true(PAXdesign_Language_Routing::detect_language_preference('Ich wohne in Deutschland') === '', 'A German address must not be treated as a language switch');
cx_assert_true(PAXdesign_Language_Routing::resolve_session_language('', 'arabic') === 'ar', 'Session language must persist Arabic from a preference message');
cx_assert_true(PAXdesign_Language_Routing::resolve_session_language('', 'English') === 'en', 'Session language must persist English from a preference message');
cx_assert_true(PAXdesign_Language_Routing::resolve_session_language('', 'Deutsch') === 'de', 'Session language must persist German from a preference message');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('?') === true, 'A lone ? must be treated as a status probe on the same case');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('still checking') === true, 'A checking follow-up must stay on the running operation');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('Guten Tag! Wie kann ich Ihnen helfen?') === false, 'A greeting must not be classified as a status probe');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('ماذا حدث؟') === true, 'ماذا حدث؟ must recap the same CCS case');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('تابع') === true, 'تابع must continue the same CCS workflow');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('نعم') === true, 'نعم must stay on the same CCS case');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_same_case_continuation('arabic') === true, 'arabic must continue the same CCS case after switching language');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_same_case_continuation('English') === true, 'English must continue the same CCS case after switching language');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_same_case_continuation('Deutsch') === true, 'Deutsch must continue the same CCS case after switching language');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_same_case_continuation('ماذا بقي؟') === true, 'ماذا بقي؟ must ask what remains on the same case');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('Start a new case') === true, 'Start a new case must be recognized as an explicit new-case request');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('New report') === true, 'New report must be recognized as an explicit new-case request');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('أريد فتح بلاغ جديد') === true, 'أريد فتح بلاغ جديد must be recognized as an explicit new-case request');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('ابدأ من الصفر') === true, 'ابدأ من الصفر must open a brand-new CCS reference');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('أبدأ من الصفر') === true, 'أبدأ من الصفر must open a brand-new CCS reference');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('Start from scratch') === true, 'Start from scratch must open a brand-new CCS reference');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('Submit a new report') === true, 'Submit a new report must open a brand-new CCS reference');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('Von vorne') === true, 'Von vorne must open a brand-new CCS reference');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('arabic') === false, 'A language switch must not open a new CCS case');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('My GitHub account was taken over') === false, 'Incident facts must not be treated as a new-case command');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('أريد تقديم بلاغ جديد') === true, 'أريد تقديم بلاغ جديد must open a brand-new CCS reference');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('Help me submit a report') === false, 'Help submitting the current report must not create a new CCS reference');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request('أريد تقديم بلاغ') === false, 'أريد تقديم بلاغ without جديد must continue the current case');
$long_new_case = 'أريد تقديم بلاغ جديد من فضلك. الحالة السابقة CCS-20260815-18FF0B59 اكتملت وأريد البدء من الصفر بهوية جديدة وبدون استخدام أي ملفات أو إجابات سابقة. ' . str_repeat('x', 80);
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request($long_new_case) === true, 'A long explicit new-report request must still open a new CCS case');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_same_case_continuation('ابدأ من الصفر') === false, 'ابدأ من الصفر must not continue the previous CCS reference');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_same_case_continuation('تابع') === true, 'تابع must continue the current CCS reference');
$old_ref = 'CCS-20260815-18FF0B59';
$new_ref = 'CCS-20260815-NEWCASE01';
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_verified_new_draft(array(
    'reference_id' => $old_ref,
    'status' => 'in_review',
    'payload' => json_encode(array()),
), $old_ref) === false, 'The previous CCS reference must never count as a verified new draft');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_verified_new_draft(array(
    'reference_id' => $old_ref,
    'status' => 'draft',
    'payload' => json_encode(array('fresh_start' => true)),
), $old_ref) === false, 'A draft that still has the previous CCS reference is not a new case');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_verified_new_draft(array(
    'reference_id' => $new_ref,
    'status' => 'draft',
    'payload' => json_encode(array('fresh_start' => true, 'replaces_reference' => $old_ref)),
), $old_ref) === true, 'A new draft is verified only when its CCS reference differs from the previous case');
cx_assert_true(PAXdesign_Cybercrime_AI_Case::is_verified_new_draft(array(
    'reference_id' => $new_ref,
    'status' => 'draft',
    'fresh_start' => true,
    'replaces_reference' => $old_ref,
), $old_ref) === true, 'Formatted new-case payloads must verify without a raw JSON payload');
$ensure_src = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-ai-case.php');
$intake_src = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-intake.php');
$ticket_src = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-tickets.php');
$chat_src = file_get_contents($ccs_root . '/includes/class-paxdesign-chat.php');
$ops_src = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-ai-operations.php');
cx_assert_true(strpos($ensure_src, 'function open_new_case_for_user') !== false, 'Explicit new-case requests must create a new CCS row in the backend');
cx_assert_true(strpos($ensure_src, 'function is_verified_new_draft') !== false, 'The backend must verify the new CCS reference before continuing');
cx_assert_true(strpos($ensure_src, 'function peek_current_reference') !== false, 'The previous CCS reference must be read from session state before opening a new case');
cx_assert_true(strpos($ensure_src, 'reuse_blocked') !== false, 'Reusing the previous CCS reference must be blocked');
cx_assert_true(strpos($intake_src, '$force_new = false') !== false && strpos($intake_src, 'supersede_open_draft_for_user') !== false, 'Creating a draft with force_new must close the previous draft instead of reusing it');
cx_assert_true(strpos($intake_src, 'strcasecmp($reference, $replaced_reference)') !== false, 'A forced new draft must generate a CCS reference different from the previous case');
cx_assert_true(strpos($ticket_src, 'ORDER BY updated_at DESC, created_at DESC LIMIT 1') !== false, 'Session binding must prefer the newest CCS reference');
cx_assert_true(strpos($ticket_src, 'function detach_chat_session') !== false, 'A new case must unbind the chat session from the previous CCS reference');
cx_assert_true(strpos($ticket_src, 'is_explicit_new_case_request((string) $content)') !== false, 'Appending a new-case message must not rebind the chat to the previous CCS reference');
cx_assert_true(strpos($ops_src, '$known_report = null') !== false, 'decide_turn must receive the case returned by ingest');
cx_assert_true(strpos($ops_src, 'is_verified_new_draft($row, $previous)') !== false, 'decide_turn must verify the new CCS reference before the model replies');
cx_assert_true(strpos($chat_src, 'is_array($ccs_report) ? $ccs_report : null') !== false, 'The chat pipeline must pass the ingested CCS case into decide_turn');
cx_assert_true(strpos($chat_src, 'reset_ccs_conversation_epoch') !== false, 'A new case must drop previous conversation context from the model prompt');
cx_assert_true(strpos($chat_src, 'explicitly started a NEW Cybercrime Support case') !== false, 'The model prompt for a new case must forbid reusing the previous CCS reference');
cx_assert_true(strpos($chat_src, 'get_reference_for_session($session_id)') !== false, 'Stale page_reference must not override the session-bound CCS case');
cx_assert_true(strpos($chat_src, 'function matching_user_turn') !== false, 'The same customer text must not be processed as a second turn');
cx_assert_true(strpos($chat_src, 'function assistant_following_user') !== false, 'An assistant reply must be bound to the customer message it answers');
cx_assert_true(strpos($chat_src, 'find_by_client_id($session_id, $client_msg_id)') !== false, 'Retries must look up the original customer message by client_msg_id');
cx_assert_true(strpos($chat_src, 'function lock_customer_turn') !== false, 'Mobile and desktop must not generate two replies for one customer turn');
cx_assert_true(strpos($chat_src, 'function assistant_client_id_for_turn') !== false, 'Each customer turn must have a stable assistant message id');
cx_assert_true(strpos($chat_src, "isset(\$_POST['message'])") !== false, 'The latest posted customer message must be processed, not a stale history item');
cx_assert_true(strpos($chat_src, "'text' => \$reply") === false, 'Skip-LLM CCS replies must not also emit a duplicate streaming text event');
cx_assert_true(strpos($ops_src, '$client_msg_id = \'\'') !== false, 'Assistant CCS replies must persist with a client_msg_id');
cx_assert_true(strpos($ops_src, 'find_by_client_id($session_id, $client_msg_id)') !== false, 'A retry with the same assistant client_msg_id must not insert a second row');
cx_assert_true(strpos($ops_src, 'function assistant_for_latest_user_turn') !== false, 'A second persist for the same latest customer turn must reuse the existing assistant row');
cx_assert_true(strpos($ops_src, 'trim((string) ($msg[\'content\'] ?? \'\')) === $content') === false, 'Identical assistant text from a previous turn must not be reused as the reply to a new customer message');
$wf_src = file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-ai-workflow.php');
cx_assert_true(strpos($wf_src, "\$state['fresh_start'] = false") !== false, 'Follow-up CCS turns must not replay the new-case opening copy');
cx_assert_true(strpos($ccs_widget, "formData.append('message', text)") !== false, 'Website chat must send the latest customer message explicitly');
cx_assert_true(strpos($ccs_widget, "formData.append('client_msg_id', clientMsgId)") !== false, 'Website chat must send a unique customer message id');
cx_assert_true(strpos($ccs_widget, 'if (persistedAssistantMessage)') !== false, 'Streaming text events must not redraw an assistant reply that was already finalized');
cx_assert_true(strpos($ccs_widget, 'var alreadyShown = isDuplicateMessage(data.message)') !== false, 'A reused previous assistant payload must not create another bubble');
cx_assert_true(strpos($ccs_widget, 'function adoptServerMessageIdentity') !== false, 'Poll and WebSocket must adopt the server id instead of drawing a second copy');
cx_assert_true(strpos($ccs_widget, 'pollSeq = Math.max(pollSeq, localMsgId)') === false, 'Optimistic local ids must not skip the latest server messages during poll');
$prompt_state = PAXdesign_Cybercrime_AI_Operations::prompt_state_block(array(
    'payload' => json_encode(array(
        'ai_workflow' => array('step' => 'review'),
        'ai_operations' => array(
            array(
                'id' => 'op-test',
                'type' => 'document_check',
                'status' => 'complete',
                'label' => 'Checking uploaded files…',
                'result_summary' => 'id.pdf passed preliminary checks',
            ),
        ),
    )),
));
cx_assert_true(strpos($prompt_state, 'Never greet as a new chat') !== false, 'Operation prompt must forbid a generic greeting');
cx_assert_true(strpos($prompt_state, 'language-preference message') !== false, 'Operation prompt must keep language switches on the same case');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_check_request('Please check the uploaded files') === true, 'A verify-files request must start a tracked check');
cx_assert_true(
    PAXdesign_Cybercrime_AI_Operations::attachments_need_check(
        array(array('name' => 'id.pdf', 'sha256' => 'aaa')),
        array()
    ) === true,
    'New uploads without saved checks must start a document-check operation'
);
cx_assert_true(
    PAXdesign_Cybercrime_AI_Operations::attachments_need_check(
        array(array('name' => 'id.pdf', 'original_name' => 'id.pdf', 'sha256' => 'aaa')),
        array('files' => array(array('filename' => 'id.pdf', 'sha256' => 'aaa')))
    ) === false,
    'Already checked files must not restart a new verification'
);
$public_op = PAXdesign_Cybercrime_AI_Operations::public_operation(array(
    'id' => 'op-test',
    'type' => 'document_check',
    'status' => 'running',
    'label' => 'Checking uploaded files…',
    'reference_id' => 'CCS-20260815-18FF0B59',
    'started_at' => '2026-08-15 17:00:00',
));
cx_assert_true(($public_op['status'] ?? '') === 'running', 'Public operation payload must expose running status');
cx_assert_true(($public_op['reference_id'] ?? '') === 'CCS-20260815-18FF0B59', 'Public operation payload must stay on the same CCS reference');

require_once $ccs_root . '/includes/class-paxdesign-cybercrime-ai-workflow.php';
$en_id = PAXdesign_Cybercrime_AI_Workflow::extract_from_message(
    'My name is Jane Doe. Email jane@example.com. Phone +43 6601234567. I live in Austria.'
);
cx_assert_true(($en_id['reporter_name'] ?? '') === 'Jane Doe', 'English identity extract must save the legal name');
cx_assert_true(($en_id['reporter_email'] ?? '') === 'jane@example.com', 'English identity extract must save the email');
cx_assert_true(strpos((string) ($en_id['reporter_phone'] ?? ''), '6601234567') !== false, 'English identity extract must save the phone');
cx_assert_true(($en_id['country_code'] ?? '') === 'AT', 'English identity extract must save Austria as AT');
$ar_id = PAXdesign_Cybercrime_AI_Workflow::extract_from_message(
    'اسمي محمد علي. البريد ali@example.com. هاتف +201001234567. البلد مصر.'
);
cx_assert_true(($ar_id['reporter_name'] ?? '') === 'محمد علي', 'Arabic identity extract must save the legal name');
cx_assert_true(($ar_id['country_code'] ?? '') === 'EG', 'Arabic identity extract must save Egypt as EG');
$de_id = PAXdesign_Cybercrime_AI_Workflow::extract_from_message(
    'Mein Name ist Anna Schmidt. Ich wohne in Deutschland. Tel +49 1701234567.'
);
cx_assert_true(($de_id['reporter_name'] ?? '') === 'Anna Schmidt', 'German identity extract must save the legal name');
cx_assert_true(($de_id['country_code'] ?? '') === 'DE', 'German identity extract must save Germany as DE');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::is_submit_intent('Submit report') === true, 'Submit report must be a submit intent');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::is_submit_intent('أرسل البلاغ') === true, 'أرسل البلاغ must be a submit intent');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::is_submit_intent('Bericht absenden') === true, 'Bericht absenden must be a submit intent');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::is_workflow_help_intent('Help me submit a report') === true, 'Help submitting a report must start the website workflow');

$empty_row = array(
    'reference_id' => 'CCS-20260815-WFLOW001',
    'status' => 'draft',
    'reporter_name' => '',
    'reporter_email' => '',
    'reporter_phone' => '',
    'reporter_country' => '',
    'category' => '',
    'urgency' => '',
    'incident_at' => '',
    'payload' => json_encode(array()),
    'attachments' => json_encode(array()),
);
$empty_state = PAXdesign_Cybercrime_AI_Workflow::state_from_row($empty_row);
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($empty_state) === 1, 'An empty CCS draft must start at Identity');
$identity_state = array_merge($empty_state, array(
    'full_name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'phone' => '+43 6601234567',
    'phone_digits' => '436601234567',
    'country' => 'Austria',
    'country_code' => 'AT',
    'identity_document' => true,
    'identity_accuracy' => true,
));
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($identity_state) === 2, 'Complete Identity must advance to Incident');
$incident_state = array_merge($identity_state, array(
    'category' => 'account_takeover',
    'incident_date' => '2026-08-12',
    'incident_at' => '2026-08-12 00:00:00',
    'platforms' => 'GitHub, Gmail',
    'description' => 'My GitHub and Gmail accounts were taken over on August 12.',
    'urgency' => 'high',
));
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($incident_state) === 3, 'Complete Incident must advance to Evidence');
$evidence_state = array_merge($incident_state, array(
    'has_evidence' => true,
    'evidence_files' => array('screenshot.png'),
));
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($evidence_state) === 4, 'Complete Evidence must advance to Review');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::can_submit($evidence_state) === false, 'Review declarations are required before submit');
$ready_state = array_merge($evidence_state, array(
    'decl_truthful' => true,
    'decl_false_reports' => true,
    'decl_verification' => true,
));
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::can_submit($ready_state) === true, 'All four website steps must allow submit of the same CCS case');
$post = PAXdesign_Cybercrime_AI_Workflow::build_submit_post($ready_state, 'en');
cx_assert_true(($post['full_name'] ?? '') === 'Jane Doe', 'Submit payload must include the legal name from Identity');
cx_assert_true(($post['country'] ?? '') === 'AT', 'Submit payload must send the ISO country code used by the website form');
cx_assert_true(($post['category'] ?? '') === 'account_takeover', 'Submit payload must include the incident category');
cx_assert_true(($post['identity_accuracy'] ?? 0) === 1, 'Submit payload must confirm identity accuracy');
cx_assert_true(!empty($post['decl_truthful']) && !empty($post['decl_false_reports']) && !empty($post['decl_verification']), 'Submit payload must include the three Review declarations');
$review_ar = PAXdesign_Cybercrime_AI_Workflow::assistant_copy(
    PAXdesign_Cybercrime_AI_Workflow::snapshot(array(
        'reference_id' => 'CCS-20260815-WFLOW001',
        'status' => 'draft',
        'reporter_name' => 'Jane Doe',
        'reporter_email' => 'jane@example.com',
        'reporter_phone' => '+43 6601234567',
        'reporter_country' => 'Austria',
        'category' => 'account_takeover',
        'urgency' => 'high',
        'incident_at' => '2026-08-12 00:00:00',
        'payload' => json_encode(array(
            'country_code' => 'AT',
            'identity_accuracy' => true,
            'incident_date' => '2026-08-12',
            'platforms' => 'GitHub',
            'description' => 'My GitHub account was taken over on August 12.',
            'declarations' => array('truthful' => true, 'false_reports' => true, 'verification' => true),
        )),
        'attachments' => json_encode(array(
            array('field' => 'identity_document', 'name' => 'id.pdf'),
            array('field' => 'evidence_screenshots', 'name' => 'shot.png'),
        )),
    ), 'ar'),
    $ready_state,
    'ar',
    false
);
cx_assert_true(strpos($review_ar, 'المراجعة') !== false || strpos($review_ar, 'سيتم إرساله') !== false, 'Arabic Review copy must summarize the same CCS case');
cx_assert_true(strpos($chat_knowledge, 'Identity → Incident → Evidence → Review') !== false, 'CCS prompt must follow the website 4-step workflow');
$ccs_js_src = file_get_contents(dirname(__DIR__, 2) . '/navein/assets/js/apple-cybercrime-support.js');
cx_assert_true(strpos($ccs_js_src, 'workflow.step') !== false, 'The CCS page must follow the AI workflow step without replacing the 4-step form');
cx_assert_true(strpos($ccs_js_src, 'setPhoneFromStored') !== false, 'The CCS page must prefill phone from the same AI-filled case');
cx_assert_true(strpos($ccs_page, 'data-step="1"') !== false && strpos($ccs_page, 'data-step="4"') !== false, 'The existing 4-step page must remain the source of truth');
$fresh_copy = PAXdesign_Cybercrime_AI_Workflow::new_case_opened_copy(
    array(
        'reference_id' => 'CCS-20260815-NEWCASE01',
        'step' => 1,
        'step_label' => 'الهوية',
        'missing_labels' => array('الاسم القانوني الكامل'),
        'missing' => array('full_name'),
        'review' => array(),
    ),
    array('fresh_start' => true),
    'ar'
);
cx_assert_true(strpos($fresh_copy, 'CCS-20260815-NEWCASE01') !== false, 'A new-case reply must use the new CCS reference');
cx_assert_true(strpos($fresh_copy, 'CCS-20260815-18FF0B59') === false, 'A new-case reply must never reuse the previous CCS reference');
cx_assert_true(strpos($fresh_copy, 'بلاغ جديد') !== false || strpos($fresh_copy, 'تم فتح') !== false, 'Arabic new-case copy must say a new report was opened');
cx_assert_true(strpos($fresh_copy, 'ما زلنا على نفس') === false, 'A new-case reply must not say this is still the previous case');
$follow_copy = PAXdesign_Cybercrime_AI_Workflow::assistant_copy(
    array(
        'reference_id' => 'CCS-20260815-NEWCASE01',
        'step' => 1,
        'step_label' => 'الهوية',
        'missing_labels' => array('البلد'),
        'missing' => array('country'),
        'review' => array(),
    ),
    array('fresh_start' => false),
    'ar'
);
cx_assert_true(strpos($follow_copy, 'تم فتح بلاغ جديد') === false, 'A follow-up must not replay the new-case opening line');
cx_assert_true(strpos($follow_copy, 'ما زلنا على نفس') !== false, 'A follow-up must continue the same CCS case');
cx_assert_true(strpos($follow_copy, 'CCS-20260815-NEWCASE01') !== false, 'A follow-up must keep the new CCS reference');
cx_assert_true(strpos($ccs_page, 'data-step="1"') !== false && strpos($ccs_page, 'data-step="4"') !== false, 'The existing 4-step page must remain the source of truth');
cx_assert_true(strpos(file_get_contents($ccs_root . '/includes/class-paxdesign-cybercrime-ai-operations.php'), '$is_draft && class_exists(\'PAXdesign_Cybercrime_AI_Workflow\')') !== false, 'Draft CCS turns must run the website workflow before document checks');

$empty_flow = $empty_state;
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($empty_flow, 'Help me submit a report');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($en_flow) === 1, 'Help submitting a report must stay on Identity of the same empty draft');
cx_assert_true(($en_flow['full_name'] ?? '') === '', 'Help submitting a report must not be stored as a legal name');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'Jane Doe');
cx_assert_true(($en_flow['full_name'] ?? '') === 'Jane Doe', 'A short English name answer must fill Identity');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'jane@example.com');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, '+43 6601234567');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'Austria');
cx_assert_true(($en_flow['country_code'] ?? '') === 'AT', 'English country answer must store AT');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, '', array(array('field' => 'identity_document', 'name' => 'passport.jpg')));
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'yes');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($en_flow) === 2, 'Complete Identity must move the same case to Incident');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'phishing');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, '2026-08-12');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'Instagram');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'Someone sent a fake Instagram login page and I entered my password.');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($en_flow) === 3, 'Complete Incident must move the same case to Evidence');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'here is a screenshot', array(array('field' => 'evidence_screenshots', 'name' => 'shot.png')));
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($en_flow) === 4, 'Complete Evidence must move the same case to Review');
$en_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($en_flow, 'submit report');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::can_submit($en_flow) === true, 'English Review submit must complete the same CCS case');
$en_post = PAXdesign_Cybercrime_AI_Workflow::build_submit_post($en_flow, 'en');
cx_assert_true(($en_post['category'] ?? '') === 'phishing_fraud', 'English submit must use the website phishing category');
cx_assert_true(($en_post['country'] ?? '') === 'AT', 'English submit must send ISO country AT');

$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($empty_state, 'أريد تقديم بلاغ');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'محمد علي');
cx_assert_true(($ar_flow['full_name'] ?? '') === 'محمد علي', 'A short Arabic name answer must fill Identity');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'ali@example.com');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, '+201001234567');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'مصر');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, '', array(array('field' => 'identity_document', 'name' => 'id.pdf')));
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'نعم');
cx_assert_true(($ar_flow['country_code'] ?? '') === 'EG', 'Arabic country answer must store EG');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($ar_flow) === 2, 'Arabic Identity must advance the same case to Incident');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'تصيد');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, '2026-08-12');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'انستغرام');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'وصلني رابط تسجيل دخول مزيف وأدخلت كلمة المرور الخاصة بي.');
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'دليل', array(array('field' => 'evidence_other', 'name' => 'chat.txt')));
$ar_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($ar_flow, 'أرسل البلاغ');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::can_submit($ar_flow) === true, 'Arabic Review submit must complete the same CCS case');
cx_assert_true((PAXdesign_Cybercrime_AI_Workflow::build_submit_post($ar_flow, 'ar')['category'] ?? '') === 'phishing_fraud', 'Arabic submit must use the website phishing category');

$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($empty_state, 'Ich möchte einen Bericht einreichen');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'Anna Schmidt');
cx_assert_true(($de_flow['full_name'] ?? '') === 'Anna Schmidt', 'A short German name answer must fill Identity');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'anna@example.com');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, '+49 1701234567');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'Deutschland');
$agypten = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('Ägypten');
cx_assert_true(($agypten['country_code'] ?? '') === 'EG', 'German Ägypten must map to EG');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, '', array(array('field' => 'identity_document', 'name' => 'ausweis.pdf')));
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'ja');
cx_assert_true(($de_flow['country_code'] ?? '') === 'DE', 'German country answer must store DE');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::current_step($de_flow) === 2, 'German Identity must advance the same case to Incident');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'Kontoübernahme');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, '2026-08-12');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'GitHub');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'Mein GitHub-Konto wurde übernommen und ich komme nicht mehr hinein.');
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'Beweis', array(array('field' => 'evidence_screenshots', 'name' => 'screen.png')));
$de_flow = PAXdesign_Cybercrime_AI_Workflow::advance_state($de_flow, 'Bericht absenden');
cx_assert_true(PAXdesign_Cybercrime_AI_Workflow::can_submit($de_flow) === true, 'German Review submit must complete the same CCS case');
cx_assert_true((PAXdesign_Cybercrime_AI_Workflow::build_submit_post($de_flow, 'de')['category'] ?? '') === 'account_takeover', 'German submit must use the website account takeover category');

echo "OK: customer platform static verification passed (" . count($files) . " modules)\n";
