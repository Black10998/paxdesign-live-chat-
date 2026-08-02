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
     * @return array<string, mixed>
     */
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

    private static function js_config() {
        $user_id = get_current_user_id();
        return array(
            'version'       => PAXDESIGN_BOOKING_VERSION,
            'restUrl'       => esc_url(rest_url('pdx/v1')),
            'ajaxUrl'       => esc_url(admin_url('admin-ajax.php')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'userId'        => $user_id,
            'isLoggedIn'    => is_user_logged_in(),
            'emailVerified' => is_user_logged_in() ? PAXdesign_Auth::is_email_verified($user_id) : false,
            'userName'      => is_user_logged_in() ? wp_get_current_user()->display_name : '',
            'userEmail'     => is_user_logged_in() ? wp_get_current_user()->user_email : '',
            'avatarUrl'     => (is_user_logged_in() && class_exists('PAXdesign_Customer_Avatar'))
                ? PAXdesign_Customer_Avatar_Presets::normalize_asset_url(PAXdesign_Customer_Avatar::url_for_user($user_id))
                : '',
            'avatarFallbackUrl' => (is_user_logged_in() && class_exists('PAXdesign_Customer_Avatar'))
                ? PAXdesign_Customer_Avatar_Presets::normalize_asset_url(PAXdesign_Customer_Avatar::fallback_url_for_user($user_id))
                : '',
            'avatarHasImage' => (is_user_logged_in() && class_exists('PAXdesign_Customer_Avatar'))
                ? PAXdesign_Customer_Avatar::has_visible_avatar($user_id)
                : false,
            'avatarPresets' => class_exists('PAXdesign_Customer_Avatar_Presets')
                ? PAXdesign_Customer_Avatar_Presets::catalog()
                : array(),
            'vipAvatarPresets' => (is_user_logged_in() && class_exists('PAXdesign_Customer_Avatar_Vip_Presets'))
                ? PAXdesign_Customer_Avatar_Vip_Presets::catalog_for_user($user_id)
                : (class_exists('PAXdesign_Customer_Avatar_Vip_Presets')
                    ? PAXdesign_Customer_Avatar_Vip_Presets::catalog_for_user(0)
                    : array()),
            'isMasterAdmin' => (is_user_logged_in() && class_exists('PAXdesign_Customer_Master_Admin'))
                ? PAXdesign_Customer_Master_Admin::is_master_admin($user_id)
                : false,
            'customerLevel' => (is_user_logged_in() && class_exists('PAXdesign_Customer_Levels'))
                ? PAXdesign_Customer_Levels::profile_fields($user_id)
                : array(),
            'defaultAvatarUrl' => class_exists('PAXdesign_Customer_Avatar')
                ? PAXdesign_Customer_Avatar_Presets::normalize_asset_url(PAXdesign_Customer_Avatar::default_avatar_url())
                : '',
            'publicModules' => PAXdesign_Auth_Native::public_modules(),
            'modules'       => array(),
            'accountPageUrl'=> esc_url(PAXdesign_Auth_Page::page_url()),
            'isAuthPage'    => PAXdesign_Auth_Page::is_auth_page(),
            'appleWebEnabled'  => class_exists('PAXdesign_Auth_Apple') && PAXdesign_Auth_Apple::is_web_configured(),
            'appleStartUrl'    => class_exists('PAXdesign_Auth_Apple') ? esc_url(PAXdesign_Auth_Apple::web_start_url()) : '',
            'appleCallbackUrl' => class_exists('PAXdesign_Auth_Apple') ? esc_url(PAXdesign_Auth_Apple::web_callback_url()) : '',
            'homeUrl'          => esc_url(home_url('/')),
            'logoUrl'          => class_exists('PAXdesign_Auth_Page') ? PAXdesign_Auth_Page::brand_logo_url() : '',
            'siteName'         => get_bloginfo('name'),
            'accountUiL10n'    => self::account_ui_l10n(),
        );
    }
}
