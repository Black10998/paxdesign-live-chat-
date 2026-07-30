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
            'publicModules' => PAXdesign_Auth_Native::public_modules(),
            'modules'       => array(),
            'accountPageUrl'=> esc_url(PAXdesign_Auth_Page::page_url()),
            'isAuthPage'    => PAXdesign_Auth_Page::is_auth_page(),
            'appleWebEnabled'  => class_exists('PAXdesign_Auth_Apple') && PAXdesign_Auth_Apple::is_web_configured(),
            'appleStartUrl'    => class_exists('PAXdesign_Auth_Apple') ? esc_url(PAXdesign_Auth_Apple::web_start_url()) : '',
            'appleCallbackUrl' => class_exists('PAXdesign_Auth_Apple') ? esc_url(PAXdesign_Auth_Apple::web_callback_url()) : '',
            'homeUrl'          => esc_url(home_url('/')),
        );
    }
}
