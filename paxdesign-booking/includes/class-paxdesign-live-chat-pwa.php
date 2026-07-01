<?php
/**
 * PWA + Web Push for Live Chat Admin front-end page.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Live_Chat_PWA {

    const DEFAULT_ADMIN_URL = 'https://paxdesign.at/live-chat-admin/';
    const REWRITE_VERSION   = '1';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_rewrites'));
        add_filter('query_vars', array(__CLASS__, 'register_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'serve_virtual_assets'));

        add_action('wp_ajax_paxdesign_live_push_subscribe', array(__CLASS__, 'handle_subscribe'));
        add_action('wp_ajax_paxdesign_live_push_unsubscribe', array(__CLASS__, 'handle_unsubscribe'));
        add_action('wp_ajax_paxdesign_live_push_vapid', array(__CLASS__, 'handle_vapid_public'));

        add_action('paxdesign_live_agent_requested', array(__CLASS__, 'on_live_agent_requested'), 10, 4);
    }

    public static function register_rewrites() {
        add_rewrite_rule('^pax-live-sw\.js$', 'index.php?pax_live_sw=1', 'top');
        add_rewrite_rule('^pax-live-manifest\.webmanifest$', 'index.php?pax_live_manifest=1', 'top');
        add_rewrite_rule('^pax-live-icon-([0-9]+)\.png$', 'index.php?pax_live_icon=$matches[1]', 'top');

        if (get_option('paxdesign_live_pwa_rewrite_version') !== self::REWRITE_VERSION) {
            flush_rewrite_rules(false);
            update_option('paxdesign_live_pwa_rewrite_version', self::REWRITE_VERSION, false);
        }
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public static function register_query_vars($vars) {
        $vars[] = 'pax_live_sw';
        $vars[] = 'pax_live_manifest';
        $vars[] = 'pax_live_icon';
        return $vars;
    }

    public static function serve_virtual_assets() {
        if (get_query_var('pax_live_sw')) {
            self::output_service_worker();
        }
        if (get_query_var('pax_live_manifest')) {
            self::output_manifest();
        }
        $icon_size = (int) get_query_var('pax_live_icon');
        if ($icon_size > 0) {
            self::output_icon($icon_size);
        }
    }

    public static function get_admin_panel_url() {
        $custom = trim((string) get_option('paxdesign_live_admin_url', ''));
        if ($custom !== '') {
            return trailingslashit(esc_url_raw($custom));
        }

        $page_id = (int) get_option('paxdesign_live_chat_page_id', 0);
        if ($page_id > 0) {
            $url = get_permalink($page_id);
            if ($url) {
                return trailingslashit($url);
            }
        }

        $page = get_page_by_path('live-chat-admin');
        if ($page instanceof WP_Post) {
            update_option('paxdesign_live_chat_page_id', (int) $page->ID, false);
            return trailingslashit(get_permalink($page));
        }

        return trailingslashit(self::DEFAULT_ADMIN_URL);
    }

    public static function get_sw_url() {
        return home_url('/pax-live-sw.js');
    }

    public static function get_manifest_url() {
        return home_url('/pax-live-manifest.webmanifest');
    }

    public static function get_icon_url($size) {
        return home_url('/pax-live-icon-' . (int) $size . '.png');
    }

    public static function print_head_tags() {
        $admin_url = self::get_admin_panel_url();
        $manifest  = esc_url(self::get_manifest_url());
        $icon192   = esc_url(self::get_icon_url(192));
        $theme     = '#0f1117';

        echo '<link rel="manifest" href="' . $manifest . '" />' . "\n";
        echo '<meta name="theme-color" content="' . esc_attr($theme) . '" />' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes" />' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="PAX Live Chat" />' . "\n";
        echo '<link rel="apple-touch-icon" href="' . $icon192 . '" />' . "\n";
        echo '<link rel="icon" type="image/png" sizes="192x192" href="' . $icon192 . '" />' . "\n";
        echo '<meta name="application-name" content="PAX Live Chat" />' . "\n";
        echo '<link rel="canonical" href="' . esc_url($admin_url) . '" />' . "\n";
    }

    public static function enqueue_assets() {
        wp_enqueue_script(
            'paxdesign-live-chat-pwa',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/live-chat-pwa.js',
            array(),
            PAXDESIGN_BOOKING_VERSION,
            true
        );

        wp_localize_script('paxdesign-live-chat-pwa', 'paxLivePwa', array(
            'swUrl'       => self::get_sw_url(),
            'manifestUrl' => self::get_manifest_url(),
            'adminUrl'    => self::get_admin_panel_url(),
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('paxdesign_admin_nonce'),
            'vapidUrl'    => admin_url('admin-ajax.php?action=paxdesign_live_push_vapid'),
            'agentName'   => PAXdesign_Chat_Live::get_agent_display_name(),
        ));
    }

    private static function output_manifest() {
        $admin_url = self::get_admin_panel_url();
        $icon192   = self::get_icon_url(192);
        $icon512   = self::get_icon_url(512);

        $manifest = array(
            'name'             => 'PAX Live Chat',
            'short_name'       => 'Live Chat',
            'description'      => 'PAXdesign Live Chat Admin — Kundensupport',
            'start_url'        => $admin_url,
            'scope'            => $admin_url,
            'display'          => 'standalone',
            'display_override' => array('standalone', 'fullscreen'),
            'orientation'      => 'portrait-primary',
            'background_color' => '#0f1117',
            'theme_color'      => '#0f1117',
            'categories'       => array('business', 'productivity'),
            'lang'             => 'de-AT',
            'icons'            => array(
                array('src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'),
                array('src' => $icon512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'),
            ),
        );

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function output_service_worker() {
        $admin_url = self::get_admin_panel_url();
        $sw_path   = PAXDESIGN_BOOKING_PLUGIN_DIR . 'assets/pwa/live-chat-sw.js';
        if (!is_readable($sw_path)) {
            status_header(404);
            exit;
        }

        $js = file_get_contents($sw_path);
        $js = str_replace('__PAX_ADMIN_URL__', esc_js($admin_url), $js);

        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache');
        echo $js;
        exit;
    }

    private static function output_icon($size) {
        $size = max(64, min(512, (int) $size));
        if (!function_exists('imagecreatetruecolor')) {
            status_header(404);
            exit;
        }

        $img = imagecreatetruecolor($size, $size);
        if (!$img) {
            status_header(500);
            exit;
        }

        $bg    = imagecolorallocate($img, 15, 17, 23);
        $accent = imagecolorallocate($img, 37, 99, 235);
        $white  = imagecolorallocate($img, 243, 244, 246);
        imagefilledrectangle($img, 0, 0, $size, $size, $bg);
        imagefilledellipse($img, (int) ($size / 2), (int) ($size / 2), (int) ($size * 0.72), (int) ($size * 0.72), $accent);

        $font = 5;
        $text = 'PAX';
        $tw   = imagefontwidth($font) * strlen($text);
        $th   = imagefontheight($font);
        imagestring($img, $font, (int) (($size - $tw) / 2), (int) (($size - $th) / 2), $text, $white);

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800');
        imagepng($img);
        imagedestroy($img);
        exit;
    }

    public static function ensure_vapid_keys() {
        $public  = get_option('paxdesign_live_vapid_public', '');
        $private = get_option('paxdesign_live_vapid_private', '');
        if ($public !== '' && $private !== '') {
            return true;
        }

        if (!function_exists('openssl_pkey_new')) {
            return false;
        }

        $keys = PAXdesign_Web_Push::generate_vapid_keys();
        if (!$keys) {
            return false;
        }

        update_option('paxdesign_live_vapid_public', $keys['public_key'], false);
        update_option('paxdesign_live_vapid_private', $keys['private_pem'], false);
        return true;
    }

    /**
     * @return array<string, string>
     */
    private static function get_vapid_config() {
        self::ensure_vapid_keys();
        return array(
            'public_key'  => (string) get_option('paxdesign_live_vapid_public', ''),
            'private_pem' => (string) get_option('paxdesign_live_vapid_private', ''),
            'subject'     => 'mailto:' . PAXdesign_Chat_Live::DEFAULT_NOTIFY_EMAIL,
        );
    }

    public static function handle_vapid_public() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
        }
        self::ensure_vapid_keys();
        wp_send_json_success(array(
            'publicKey' => get_option('paxdesign_live_vapid_public', ''),
        ));
    }

    public static function handle_subscribe() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
        }

        $raw = isset($_POST['subscription']) ? wp_unslash($_POST['subscription']) : '';
        $sub = json_decode($raw, true);
        if (!is_array($sub) || empty($sub['endpoint'])) {
            wp_send_json_error(array('message' => 'Invalid subscription'), 400);
        }

        $user_id = get_current_user_id();
        $all     = self::get_user_subscriptions($user_id);
        $all[$sub['endpoint']] = $sub;
        update_user_meta($user_id, 'pax_live_push_subscriptions', $all);

        wp_send_json_success(array('ok' => true));
    }

    public static function handle_unsubscribe() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
        }

        $endpoint = isset($_POST['endpoint']) ? esc_url_raw(wp_unslash($_POST['endpoint'])) : '';
        if ($endpoint === '') {
            wp_send_json_error(array('message' => 'Missing endpoint'), 400);
        }

        $user_id = get_current_user_id();
        $all     = self::get_user_subscriptions($user_id);
        unset($all[$endpoint]);
        update_user_meta($user_id, 'pax_live_push_subscriptions', $all);

        wp_send_json_success(array('ok' => true));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function get_user_subscriptions($user_id) {
        $all = get_user_meta($user_id, 'pax_live_push_subscriptions', true);
        return is_array($all) ? $all : array();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function get_all_admin_subscriptions() {
        $users = get_users(array(
            'role__in' => array('administrator'),
            'fields'   => array('ID'),
        ));

        $subs = array();
        foreach ($users as $user) {
            foreach (self::get_user_subscriptions((int) $user->ID) as $endpoint => $sub) {
                $subs[$endpoint] = $sub;
            }
        }
        return array_values($subs);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function send_push_to_admins($title, $body, $options = array()) {
        if (!self::ensure_vapid_keys()) {
            return;
        }

        $vapid = self::get_vapid_config();
        if ($vapid['public_key'] === '' || $vapid['private_pem'] === '') {
            return;
        }

        $admin_url = self::get_admin_panel_url();
        $session   = isset($options['session_id']) ? (string) $options['session_id'] : '';
        $url       = $session !== '' ? add_query_arg('session', rawurlencode($session), $admin_url) : $admin_url;
        $badge     = isset($options['badge']) ? (int) $options['badge'] : 1;
        $tag       = isset($options['tag']) ? (string) $options['tag'] : 'pax-live-chat';

        $payload = array(
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'badge' => $badge,
            'tag'   => $tag,
            'icon'  => self::get_icon_url(192),
        );

        foreach (self::get_all_admin_subscriptions() as $sub) {
            $result = PAXdesign_Web_Push::send($sub, $payload, $vapid);
            if (is_wp_error($result) && in_array($result->get_error_code(), array('gone', 'invalid_subscription'), true)) {
                self::remove_subscription_by_endpoint(isset($sub['endpoint']) ? $sub['endpoint'] : '');
            }
        }
    }

    private static function remove_subscription_by_endpoint($endpoint) {
        if ($endpoint === '') {
            return;
        }
        $users = get_users(array('role__in' => array('administrator'), 'fields' => array('ID')));
        foreach ($users as $user) {
            $all = self::get_user_subscriptions((int) $user->ID);
            if (isset($all[$endpoint])) {
                unset($all[$endpoint]);
                update_user_meta((int) $user->ID, 'pax_live_push_subscriptions', $all);
            }
        }
    }

    public static function on_live_agent_requested($session_id, $service, $preview, $admin_url) {
        self::send_push_to_admins(
            '🚨 Live-Agent-Anfrage',
            ($service !== '' ? $service . ' — ' : '') . ($preview !== '' ? $preview : 'Kunde wartet auf Support'),
            array(
                'session_id' => $session_id,
                'tag'        => 'live-request-' . $session_id,
                'badge'      => 1,
            )
        );
    }

    public static function notify_new_customer_message($session_id, $content) {
        self::send_push_to_admins(
            'Neue Kundennachricht',
            wp_html_excerpt($content, 120, '…'),
            array(
                'session_id' => $session_id,
                'tag'        => 'msg-' . $session_id,
                'badge'      => 1,
            )
        );
        if (class_exists('PAXdesign_APNS')) {
            PAXdesign_APNS::notify_new_customer_message($session_id, $content);
        }
    }

    public static function count_pending_items() {
        if (!class_exists('PAXdesign_Chat_Live')) {
            return 0;
        }

        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();
        $since = gmdate('Y-m-d H:i:s', time() - (45 * MINUTE_IN_SECONDS));

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table
                 WHERE handler = %s
                 OR (COALESCE(handler, 'ai') = %s AND updated_at >= %s)",
                PAXdesign_Chat_Live::HANDLER_LIVE,
                PAXdesign_Chat_Live::HANDLER_AI,
                $since
            )
        );

        return max(0, $count);
    }
}
