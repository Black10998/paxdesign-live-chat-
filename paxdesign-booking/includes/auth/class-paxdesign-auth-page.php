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
            'post_title'   => __('Account', 'paxdesign-booking'),
            'post_name'    => self::PAGE_SLUG,
            'post_content' => self::default_content(),
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => self::author_id(),
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ), true);

        if (is_wp_error($page_id) || !$page_id) {
            return 0;
        }

        update_option(self::OPTION_PAGE_ID, (int) $page_id, false);
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
        }
        return $classes;
    }

    public static function enqueue_page_assets() {
        if (!self::is_auth_page()) {
            return;
        }

        $base = 'assets/customer-auth/';
        $url  = PAXDESIGN_BOOKING_PLUGIN_URL . $base;
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . $base . 'css/pdx-auth-page.css';
        $ver  = PAXDESIGN_BOOKING_VERSION;
        if (is_readable($path)) {
            $ver .= '.' . filemtime($path);
        }

        wp_enqueue_style(
            'pax-auth-page',
            $url . 'css/pdx-auth-page.css',
            array('pax-auth-ui'),
            $ver
        );
    }

    /**
     * @return string
     */
    public static function render_shortcode() {
        ob_start();
        ?>
        <div id="pdx-auth-page" class="pdx-auth-page" data-pdx-auth-page="1">
            <div class="pdx-auth-page-backdrop" aria-hidden="true"></div>
            <div class="pdx-auth-page-shell">
                <header class="pdx-auth-page-header">
                    <div class="pdx-auth-page-mark" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" fill="currentColor"/>
                        </svg>
                    </div>
                    <h1 class="pdx-auth-page-title"><?php echo esc_html__('Account', 'paxdesign-booking'); ?></h1>
                    <p class="pdx-auth-page-subtitle"><?php echo esc_html__('Sign in or create your PAXDesign account.', 'paxdesign-booking'); ?></p>
                </header>
                <div id="pdx-auth-page-guest" class="pdx-auth-page-panel">
                    <div class="pdx-auth-page-segment" role="tablist" aria-label="<?php echo esc_attr__('Authentication', 'paxdesign-booking'); ?>">
                        <button type="button" class="pdx-auth-page-segment-btn is-active" data-auth-view="login" role="tab" aria-selected="true"><?php echo esc_html__('Sign In', 'paxdesign-booking'); ?></button>
                        <button type="button" class="pdx-auth-page-segment-btn" data-auth-view="register" role="tab" aria-selected="false"><?php echo esc_html__('Create Account', 'paxdesign-booking'); ?></button>
                    </div>
                    <div id="pdx-auth-page-form" class="pdx-auth-page-form-wrap" role="tabpanel"></div>
                </div>
                <div id="pdx-auth-page-signed-in" class="pdx-auth-page-panel" hidden>
                    <div class="pdx-auth-page-signed-head"></div>
                    <div class="pdx-auth-page-signed-actions">
                        <button type="button" class="pdx-btn-pearl pdx-auth-page-portal-btn">
                            <span class="pdx-btn-pearl__wrap"><span><?php echo esc_html__('Open Customer Portal', 'paxdesign-booking'); ?></span></span>
                        </button>
                        <button type="button" class="pdx-cx-btn pdx-cx-btn--ghost pdx-auth-page-profile-btn"><?php echo esc_html__('My Profile', 'paxdesign-booking'); ?></button>
                        <button type="button" class="pdx-cx-btn pdx-cx-btn--ghost pdx-auth-page-logout-btn"><?php echo esc_html__('Sign Out', 'paxdesign-booking'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
