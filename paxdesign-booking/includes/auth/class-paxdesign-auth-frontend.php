<?php
/**
 * Booking-native auth UI (header bar, login overlay, customer portal) — no toolbar dependency.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Auth_Frontend {

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 20);
        add_action('wp_footer', array(__CLASS__, 'print_mobile_header_overrides'), PHP_INT_MAX);
    }

    private static function asset_version($relative_path) {
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . ltrim($relative_path, '/');
        $mtime = is_readable($path) ? (string) filemtime($path) : '';
        return PAXDESIGN_BOOKING_VERSION . ('' !== $mtime ? '.' . $mtime : '');
    }

    public static function enqueue() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $base = 'assets/customer-auth/';
        $url  = PAXDESIGN_BOOKING_PLUGIN_URL . $base;

        wp_enqueue_style(
            'pax-auth-tokens',
            $url . 'css/pdx-tokens.css',
            array(),
            self::asset_version($base . 'css/pdx-tokens.css')
        );
        wp_enqueue_style(
            'pax-auth-unified-ui',
            $url . 'css/pdx-unified-ui.css',
            array('pax-auth-tokens'),
            self::asset_version($base . 'css/pdx-unified-ui.css')
        );
        wp_enqueue_style(
            'pax-auth-verified-badge',
            $url . 'css/pdx-verified-badge.css',
            array('pax-auth-unified-ui'),
            self::asset_version($base . 'css/pdx-verified-badge.css')
        );
        wp_enqueue_style(
            'pax-auth-customer-ui',
            $url . 'css/pdx-customer-ui.css',
            array('pax-auth-unified-ui', 'pax-auth-verified-badge'),
            self::asset_version($base . 'css/pdx-customer-ui.css')
        );
        wp_enqueue_style(
            'pax-auth-ui',
            $url . 'css/pdx-auth.css',
            array('pax-auth-customer-ui'),
            self::asset_version($base . 'css/pdx-auth.css')
        );
        wp_add_inline_style(
            'pax-auth-ui',
            'html body #pdx-auth-bar.pdx-auth-bar--header .pdx-auth-signup-btn{background:#000!important;background-color:#000!important;color:#fff!important;border:0!important;box-shadow:none!important;border-radius:980px!important;background-image:none!important}' .
            'html body #pdx-auth-bar.pdx-auth-bar--header .pdx-auth-signup-btn:hover,html body #pdx-auth-bar.pdx-auth-bar--header .pdx-auth-signup-btn:focus{background:#1d1d1f!important;color:#fff!important;border:0!important;text-decoration:none!important}'
        );

        $script_args = array('strategy' => 'defer', 'in_footer' => true);

        wp_enqueue_script(
            'pax-auth-customer-icons',
            $url . 'js/pdx-customer-icons.js',
            array(),
            self::asset_version($base . 'js/pdx-customer-icons.js'),
            $script_args
        );
        wp_enqueue_script(
            'pax-auth-verified-badge',
            $url . 'js/pdx-verified-badge.js',
            array(),
            self::asset_version($base . 'js/pdx-verified-badge.js'),
            $script_args
        );
        wp_enqueue_script(
            'pax-auth-ui',
            $url . 'js/pax-auth.js',
            array('pax-auth-verified-badge', 'pax-auth-customer-icons'),
            self::asset_version($base . 'js/pax-auth.js'),
            $script_args
        );

        wp_localize_script('pax-auth-ui', 'PAX_AUTH_CONFIG', self::js_config());
    }

    /**
     * Beat leftover Customizer / snippet CSS that still forces the old clipped
     * ".pdx-auth-trigger" login control ("Sign") onto the mobile header.
     */
    public static function print_mobile_header_overrides() {
        if (is_admin()) {
            return;
        }
        echo '<style id="pdx-auth-mobile-header-fix">'
            . 'html body .pdx-auth-trigger,html body .pdx-auth-trigger-label,'
            . 'html body #pdx-auth-bar .pdx-auth-trigger,html body #pdx-auth-bar .pdx-auth-trigger-label,'
            . 'html body header .pdx-auth-trigger,html body header button.pdx-auth-trigger,'
            . 'html body #pdx-auth-bar.pdx-cx-shell .pdx-auth-trigger,'
            . 'html body #pdx-auth-bar.pdx-auth-bar--header .pdx-auth-trigger{'
            . 'display:none!important;visibility:hidden!important;pointer-events:none!important;'
            . 'width:0!important;height:0!important;overflow:hidden!important;opacity:0!important}'
            . 'html body #pdx-auth-bar .pdx-auth-signout-btn,html body .pdx-account-header-signout{'
            . 'display:none!important}'
            . '@media (max-width:992px){'
            . 'html body #pdx-auth-bar,html body #pdx-auth-bar.pdx-auth-bar--header{'
            . 'max-width:none!important;width:auto!important;transform:none!important;overflow:visible!important}'
            . 'html body #pdx-auth-bar .pdx-auth-signup-btn:not([hidden]),'
            . 'html body #pdx-auth-bar.pdx-auth-bar--header .pdx-auth-signup-btn:not([hidden]){'
            . 'display:inline-flex!important;max-width:none!important;overflow:visible!important;'
            . 'text-overflow:clip!important;white-space:nowrap!important;font-size:13px!important}'
            . 'html body #pdx-auth-bar .pdx-auth-signup-btn::before,html body #pdx-auth-bar .pdx-auth-signup-btn::after{'
            . 'content:none!important;display:none!important}'
            . 'html body #pdx-auth-bar .pdx-is-hidden,html body #pdx-auth-bar [hidden],'
            . 'html body #pdx-auth-bar .pdx-auth-signin-btn{'
            . 'display:none!important;visibility:hidden!important}'
            . 'html body #pdx-auth-bar .pdx-auth-menu:not(.is-open),html body #pdx-auth-bar .pdx-auth-menu[hidden]{'
            . 'display:none!important;visibility:hidden!important}'
            . '}'
            . '</style>' . "\n";
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function account_ui_l10n() {
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/account-ui-l10n.php';
        if (!is_readable($path)) {
            return array();
        }
        $strings = include $path;
        return is_array($strings) ? $strings : array();
    }

    /**
     * @return array<string, mixed>
     */
    private static function js_config() {
        $user_id = get_current_user_id();
        $logged_in = is_user_logged_in();
        $avatar_url = '';
        $avatar_fallback = '';
        $avatar_has_image = false;
        if ($logged_in && class_exists('PAXdesign_Customer_Avatar')) {
            $avatar_url = PAXdesign_Customer_Avatar::url_for_user($user_id);
            $avatar_fallback = PAXdesign_Customer_Avatar::fallback_url_for_user($user_id);
            $avatar_has_image = PAXdesign_Customer_Avatar::has_visible_avatar($user_id);
            if (class_exists('PAXdesign_Customer_Avatar_Presets')) {
                $avatar_url = PAXdesign_Customer_Avatar_Presets::normalize_asset_url($avatar_url);
                $avatar_fallback = PAXdesign_Customer_Avatar_Presets::normalize_asset_url($avatar_fallback);
            }
        }
        return array(
            'version'       => PAXDESIGN_BOOKING_VERSION,
            'restUrl'       => esc_url(rest_url('pdx/v1')),
            'ajaxUrl'       => esc_url(admin_url('admin-ajax.php')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'userId'        => $user_id,
            'isLoggedIn'    => $logged_in,
            'emailVerified' => $logged_in ? PAXdesign_Auth::is_email_verified($user_id) : false,
            'userName'      => $logged_in ? wp_get_current_user()->display_name : '',
            'userEmail'     => $logged_in ? wp_get_current_user()->user_email : '',
            'avatarUrl'     => $avatar_url,
            'avatarFallbackUrl' => $avatar_fallback,
            'avatarHasImage' => $avatar_has_image,
            'avatarPresets' => class_exists('PAXdesign_Customer_Avatar_Presets')
                ? PAXdesign_Customer_Avatar_Presets::catalog()
                : array(),
            'vipAvatarPresets' => class_exists('PAXdesign_Customer_Avatar_Vip_Presets')
                ? PAXdesign_Customer_Avatar_Vip_Presets::catalog_for_user($logged_in ? $user_id : 0)
                : array(),
            'isMasterAdmin' => ($logged_in && class_exists('PAXdesign_Customer_Master_Admin'))
                ? PAXdesign_Customer_Master_Admin::is_master_admin($user_id)
                : false,
            'customerLevel' => ($logged_in && class_exists('PAXdesign_Customer_Levels'))
                ? PAXdesign_Customer_Levels::profile_fields($user_id)
                : array(),
            'defaultAvatarUrl' => class_exists('PAXdesign_Customer_Avatar')
                ? (class_exists('PAXdesign_Customer_Avatar_Presets')
                    ? PAXdesign_Customer_Avatar_Presets::normalize_asset_url(PAXdesign_Customer_Avatar::default_avatar_url())
                    : PAXdesign_Customer_Avatar::default_avatar_url())
                : '',
            'publicModules' => PAXdesign_Auth_Native::public_modules(),
            'modules'       => array(),
            'accountPageUrl'=> esc_url(PAXdesign_Auth_Page::page_url()),
            'isAuthPage'    => PAXdesign_Auth_Page::is_auth_page(),
            'appleWebEnabled'  => class_exists('PAXdesign_Auth_Apple') && PAXdesign_Auth_Apple::is_web_configured(),
            'appleStartUrl'    => class_exists('PAXdesign_Auth_Apple') ? esc_url(PAXdesign_Auth_Apple::web_start_url()) : '',
            'appleCallbackUrl' => class_exists('PAXdesign_Auth_Apple') ? esc_url(PAXdesign_Auth_Apple::web_callback_url()) : '',
            'githubWebEnabled' => class_exists('PAXdesign_Auth_GitHub') && PAXdesign_Auth_GitHub::is_configured(),
            'githubStartUrl'   => class_exists('PAXdesign_Auth_GitHub') ? esc_url(PAXdesign_Auth_GitHub::start_url()) : '',
            'githubCallbackUrl'=> class_exists('PAXdesign_Auth_GitHub') ? esc_url(PAXdesign_Auth_GitHub::callback_url()) : '',
            'homeUrl'          => esc_url(home_url('/')),
            'logoUrl'          => class_exists('PAXdesign_Auth_Page') ? PAXdesign_Auth_Page::brand_logo_url() : '',
            'siteName'         => get_bloginfo('name'),
            'accountUiL10n'    => self::account_ui_l10n(),
        );
    }
}
