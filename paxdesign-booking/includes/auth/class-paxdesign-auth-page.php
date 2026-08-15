<?php
/**
 * Dedicated customer authentication page (/account).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Auth_Page {

    const OPTION_PAGE_ID = 'paxdesign_auth_page_id';
    const PAGE_SLUG      = 'account';
    const SHORTCODE      = 'paxdesign_account';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_shortcode'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_page_assets'), 25);
        add_action('wp_head', array(__CLASS__, 'render_isolated_shell_critical_css'), 999);
    }

    public static function register_shortcode() {
        add_shortcode(self::SHORTCODE, array(__CLASS__, 'render_shortcode'));
    }

    /**
     * @return string
     */
    public static function page_url($path = '') {
        $page_id = (int) get_option(self::OPTION_PAGE_ID, 0);
        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            if (is_string($permalink) && $permalink !== '') {
                return $path !== '' ? trailingslashit($permalink) . ltrim($path, '/') : $permalink;
            }
        }
        return add_query_arg('pdx_account', '1', home_url('/'));
    }

    /**
     * Create or repair the authentication page.
     *
     * @return int Page ID or 0 on failure.
     */
    public static function ensure_page() {
        $page_id = (int) get_option(self::OPTION_PAGE_ID, 0);
        if ($page_id > 0) {
            $post = get_post($page_id);
            if ($post instanceof WP_Post && $post->post_status !== 'trash') {
                if ($post->post_name !== self::PAGE_SLUG) {
                    wp_update_post(array(
                        'ID'        => $page_id,
                        'post_name' => self::PAGE_SLUG,
                    ));
                }
                return $page_id;
            }
        }

        $existing = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        if ($existing instanceof WP_Post) {
            update_option(self::OPTION_PAGE_ID, (int) $existing->ID, false);
            if (strpos((string) $existing->post_content, '[' . self::SHORTCODE) === false) {
                wp_update_post(array(
                    'ID'           => (int) $existing->ID,
                    'post_content' => self::default_content(),
                ));
            }
            return (int) $existing->ID;
        }

        $page_id = wp_insert_post(array(
            'post_title'     => __('Account', 'paxdesign-booking'),
            'post_name'      => self::PAGE_SLUG,
            'post_content'   => self::default_content(),
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => self::author_id(),
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ), true);

        if (is_wp_error($page_id) || !$page_id) {
            return 0;
        }

        update_option(self::OPTION_PAGE_ID, (int) $page_id, false);
        flush_rewrite_rules(false);
        return (int) $page_id;
    }

    /**
     * @return string
     */
    private static function default_content() {
        return '[' . self::SHORTCODE . ']';
    }

    /**
     * @return int
     */
    private static function author_id() {
        $admins = get_users(array(
            'role'    => 'administrator',
            'number'  => 1,
            'orderby' => 'ID',
            'order'   => 'ASC',
            'fields'  => array('ID'),
        ));
        if (!empty($admins[0]->ID)) {
            return (int) $admins[0]->ID;
        }
        return 1;
    }

    /**
     * @return bool
     */
    public static function is_auth_page() {
        if (!is_singular('page')) {
            return false;
        }
        $page_id = (int) get_option(self::OPTION_PAGE_ID, 0);
        return $page_id > 0 && (int) get_queried_object_id() === $page_id;
    }

    /**
     * @param array<int, string> $classes
     * @return array<int, string>
     */
    public static function body_class($classes) {
        if (self::is_auth_page()) {
            $classes[] = 'pdx-auth-page-body';
            $classes[] = 'pdx-auth-isolated';
            if (is_user_logged_in()) {
                $classes[] = 'pdx-account-dashboard-body';
            } else {
                $classes[] = 'pdx-auth-guest-body';
            }
        }
        return $classes;
    }

    /**
     * Prevent theme chrome flash before auth CSS/JS load.
     */
    public static function render_isolated_shell_critical_css() {
        if (!self::is_auth_page()) {
            return;
        }
        echo '<style id="pdx-auth-isolated-critical">html.pdx-auth-isolated,body.pdx-auth-isolated{height:100svh;overflow:hidden;background:#fff!important}body.pdx-auth-isolated>*:not(#pdx-auth-isolated-shell):not(#wpadminbar){display:none!important}#pdx-auth-isolated-shell{position:fixed;inset:0;z-index:2147483000;background:#fff;display:flex;overflow:hidden}body.pdx-auth-guest-body #pdx-auth-isolated-shell{align-items:center;justify-content:center}body.pdx-account-dashboard-body #pdx-auth-isolated-shell{flex-direction:column;align-items:stretch;justify-content:flex-start;padding:0;background:#f5f5f7}body.pdx-account-dashboard-body #pdx-account-header{display:flex!important;visibility:visible!important;pointer-events:auto!important}body.pdx-account-dashboard-body #pdx-account-main{display:block!important;visibility:visible!important;flex:1 1 auto;min-height:0}body.pdx-auth-guest-body #pdx-auth-page{min-height:0;height:auto;max-height:100svh;overflow:auto}#paxdesign-booking-root,#pdx-auth-bar,[class*="cookie" i],[id*="cookie" i]{display:none!important}</style>';
    }

    public static function enqueue_page_assets() {
        if (!self::is_auth_page()) {
            return;
        }

        $base = 'assets/customer-auth/';
        $url  = PAXDESIGN_BOOKING_PLUGIN_URL . $base;
        $ver  = PAXDESIGN_BOOKING_VERSION;

        $auth_page_css = PAXDESIGN_BOOKING_PLUGIN_DIR . $base . 'css/pdx-auth-page.css';
        if (is_readable($auth_page_css)) {
            $ver .= '.' . filemtime($auth_page_css);
        }
        wp_enqueue_style(
            'pax-auth-page',
            $url . 'css/pdx-auth-page.css',
            array('pax-auth-ui'),
            $ver
        );

        $account_app_css = PAXDESIGN_BOOKING_PLUGIN_DIR . $base . 'css/pdx-account-app.css';
        if (is_readable($account_app_css)) {
            wp_enqueue_style(
                'pax-account-app',
                $url . 'css/pdx-account-app.css',
                array('pax-auth-page'),
                PAXDESIGN_BOOKING_VERSION . '.' . filemtime($account_app_css)
            );
        }

        $portal_apple_css = PAXDESIGN_BOOKING_PLUGIN_DIR . $base . 'css/pdx-portal-apple.css';
        if (is_readable($portal_apple_css)) {
            wp_enqueue_style(
                'pax-portal-apple',
                $url . 'css/pdx-portal-apple.css',
                array('pax-account-app'),
                PAXDESIGN_BOOKING_VERSION . '.' . filemtime($portal_apple_css)
            );
        }
    }

    /**
     * @return string
     */
    public static function brand_logo_url() {
        $override = trim((string) get_option('paxdesign_booking_logo_url', ''));
        if ($override !== '') {
            return esc_url($override);
        }

        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $image = wp_get_attachment_image_url($custom_logo_id, 'medium');
            if (is_string($image) && $image !== '') {
                return esc_url($image);
            }
        }

        return '';
    }

    /**
     * @return string
     */
    public static function render_shortcode() {
        ob_start();
        ?>
        <div id="pdx-auth-page" class="pdx-auth-page" data-pdx-auth-page="1">
            <div id="pdx-auth-page-guest" class="pdx-auth-page-guest">
                <div class="pdx-auth-page-shell pdx-auth-page-shell--compact">
                    <h1 class="pdx-auth-page-title" id="pdx-auth-page-title"><?php echo esc_html__('Sign In', 'paxdesign-booking'); ?></h1>
                    <div class="pdx-auth-page-segment" role="tablist" aria-label="<?php echo esc_attr__('Authentication', 'paxdesign-booking'); ?>">
                        <button type="button" class="pdx-auth-page-segment-btn is-active" data-auth-view="login" role="tab" aria-selected="true"><?php echo esc_html__('Sign In', 'paxdesign-booking'); ?></button>
                        <button type="button" class="pdx-auth-page-segment-btn" data-auth-view="register" role="tab" aria-selected="false"><?php echo esc_html__('Create Account', 'paxdesign-booking'); ?></button>
                    </div>
                    <div id="pdx-auth-page-form" class="pdx-auth-page-form-wrap" role="tabpanel"></div>
                </div>
            </div>
            <div id="pdx-account-app" class="pdx-account-app" hidden>
                <aside class="pdx-account-sidebar" id="pdx-account-sidebar" aria-label="<?php echo esc_attr__('Account navigation', 'paxdesign-booking'); ?>"></aside>
                <div role="main" class="pdx-account-main" id="pdx-account-main" tabindex="-1"></div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
