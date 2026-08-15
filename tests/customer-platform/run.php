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
cx_assert_true(strpos($plugin_bootstrap, "define('PAXDESIGN_BOOKING_VERSION', '3.175.6')") !== false, 'Plugin version must be 3.175.6');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-i18n.php') !== false, 'Plugin must load CCS localization');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-document-checks.php') !== false, 'Plugin must load document checks');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-ai-case.php') !== false, 'Plugin must load AI case sync');
cx_assert_true(strpos($plugin_bootstrap, 'class-paxdesign-cybercrime-ai-operations.php') !== false, 'Plugin must load AI operation state');
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
require_once $ccs_root . '/includes/class-paxdesign-cybercrime-ai-operations.php';
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('?') === true, 'A lone ? must be treated as a status probe on the same case');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('still checking') === true, 'A checking follow-up must stay on the running operation');
cx_assert_true(PAXdesign_Cybercrime_AI_Operations::is_status_probe('Guten Tag! Wie kann ich Ihnen helfen?') === false, 'A greeting must not be classified as a status probe');
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

echo "OK: customer platform static verification passed (" . count($files) . " modules)\n";
