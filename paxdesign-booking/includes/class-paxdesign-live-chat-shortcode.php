<?php
/**
 * Front-end shortcode: [paxdesign_live_chat]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Live_Chat_Shortcode {

    const SHORTCODE = 'paxdesign_live_chat';

    /** @var bool */
    private static $fullscreen_ready = false;

    public static function init() {
        add_shortcode(self::SHORTCODE, array(__CLASS__, 'render'));
        add_action('wp', array(__CLASS__, 'maybe_setup_fullscreen'));
    }

    public static function user_can_access() {
        return current_user_can('manage_options');
    }

    /**
     * Detect shortcode on current singular page (classic editor + Elementor).
     */
    private static function current_page_has_shortcode() {
        if (!is_singular()) {
            return false;
        }

        global $post;
        if (!$post instanceof WP_Post) {
            return false;
        }

        if (has_shortcode($post->post_content, self::SHORTCODE)) {
            return true;
        }

        $elementor = get_post_meta($post->ID, '_elementor_data', true);
        if (is_string($elementor) && strpos($elementor, self::SHORTCODE) !== false) {
            return true;
        }

        return false;
    }

    private static function should_use_fullscreen() {
        return self::user_can_access() && self::current_page_has_shortcode();
    }

    public static function login_screen_html() {
        $login_url = wp_login_url(get_permalink());
        ob_start();
        ?>
        <div class="pax-live-console pax-live-console--gate" id="paxLiveChatDashboard" data-context="shortcode">
          <div class="pax-live-console__gate">
            <div class="pax-live-console__gate-icon" aria-hidden="true">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <h1 class="pax-live-console__gate-title">Live Chat Admin</h1>
            <p class="pax-live-console__gate-text">Bitte melden Sie sich als Administrator an, um den Live Chat zu öffnen.</p>
            <a class="pax-live-console__gate-btn" href="<?php echo esc_url($login_url); ?>">Anmelden</a>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function maybe_setup_fullscreen() {
        if (!self::current_page_has_shortcode()) {
            return;
        }

        self::$fullscreen_ready = true;

        add_filter('body_class', array(__CLASS__, 'filter_body_class'));
        add_filter('language_attributes', array(__CLASS__, 'filter_html_class'));
        add_filter('show_admin_bar', '__return_false');
        add_action('wp_head', array(__CLASS__, 'print_viewport_meta'), 0);
        add_action('wp_head', array(__CLASS__, 'print_critical_css'), 1);
        add_action('wp_head', array('PAXdesign_Live_Chat_PWA', 'print_head_tags'), 2);

        if (self::user_can_access()) {
            add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'), 20);
        } else {
            add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_shell_assets'), 20);
        }
    }

    public static function enqueue_shell_assets() {
        wp_enqueue_style(
            'paxdesign-live-chat-shortcode',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/css/live-chat-shortcode.css',
            array(),
            PAXDESIGN_BOOKING_VERSION
        );
        wp_enqueue_style(
            'paxdesign-live-chat-app',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/css/live-chat-app.css',
            array('paxdesign-live-chat-shortcode'),
            PAXDESIGN_BOOKING_VERSION
        );
        wp_enqueue_script(
            'paxdesign-live-chat-shortcode-fs',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/live-chat-shortcode-fullscreen.js',
            array(),
            PAXDESIGN_BOOKING_VERSION,
            true
        );
    }

    public static function print_viewport_meta() {
        if (!self::$fullscreen_ready) {
            return;
        }
        echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover" />' . "\n";
    }

    /**
     * @param array<int, string> $classes
     * @return array<int, string>
     */
    public static function filter_body_class($classes) {
        $classes[] = 'pax-live-shortcode-fullscreen';
        return $classes;
    }

    public static function filter_html_class($output) {
        if (strpos($output, 'pax-live-shortcode-fullscreen-root') !== false) {
            return $output;
        }
        if (preg_match('/class="([^"]*)"/', $output)) {
            return preg_replace(
                '/class="([^"]*)"/',
                'class="$1 pax-live-shortcode-fullscreen-root"',
                $output,
                1
            );
        }
        return $output . ' class="pax-live-shortcode-fullscreen-root"';
    }

    public static function print_critical_css() {
        if (!self::$fullscreen_ready) {
            return;
        }
        echo '<style id="pax-live-fs-critical">'
            . 'html.pax-live-shortcode-fullscreen-root,html.pax-live-shortcode-fullscreen-root body{'
            . 'overflow:hidden!important;margin:0!important;padding:0!important;'
            . 'height:100%!important;width:100%!important;background:#0f1117!important;'
            . '-webkit-text-size-adjust:100%;'
            . '}'
            . 'html.pax-live-shortcode-fullscreen-root #wpadminbar,'
            . 'html.pax-live-shortcode-fullscreen-root .pax-live-fs-hidden{'
            . 'display:none!important;visibility:hidden!important;'
            . '}'
            . '</style>';
    }

    public static function enqueue_assets() {
        self::enqueue_shell_assets();
        PAXdesign_Live_Chat_PWA::enqueue_assets();

        wp_enqueue_script(
            'paxdesign-chat-live-admin',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/chat-live-admin.js',
            array('jquery'),
            PAXDESIGN_BOOKING_VERSION,
            true
        );

        wp_localize_script('paxdesign-chat-live-admin', 'paxdesignAdmin', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('paxdesign_admin_nonce'),
            'liveAgent'    => PAXdesign_Chat_Live::get_agent_public_config(),
            'adminUrl'     => PAXdesign_Live_Chat_PWA::get_admin_panel_url(),
            'quickReplies' => PAXdesign_Chat_Live::get_admin_quick_replies(),
            'tourCompleted' => (bool) get_user_meta(get_current_user_id(), 'pax_live_dashboard_tour_completed', true),
        ));
    }

    /**
     * @param array<string, string>|string $atts
     */
    public static function render($atts = array()) {
        if (!self::user_can_access()) {
            return self::login_screen_html();
        }

        if (!self::$fullscreen_ready) {
            self::enqueue_assets();
        }

        $agent_name   = PAXdesign_Chat_Live::get_agent_display_name();
        $agent_avatar = PAXdesign_Chat_Live::get_agent_avatar_url();
        $agent_role   = PAXdesign_Chat_Live::get_agent_role();
        $agent_tagline = PAXdesign_Chat_Live::get_agent_tagline();
        $agent_bio    = PAXdesign_Chat_Live::get_agent_bio();

        ob_start();
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/live-chat-shortcode.php';
        return ob_get_clean();
    }
}
