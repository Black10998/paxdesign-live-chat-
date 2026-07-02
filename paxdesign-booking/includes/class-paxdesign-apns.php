<?php
/**
 * Apple Push Notification service (APNs) for native iOS Live Chat app.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_APNS {

    const USER_META_KEY = 'pax_live_apns_devices';

    public static function init() {
        add_action('paxdesign_live_agent_requested', array(__CLASS__, 'on_live_agent_requested'), 20, 5);
        add_action('paxdesign_new_chat_session', array(__CLASS__, 'on_new_chat_session'), 20, 3);
        add_action('paxdesign_session_sync', array(__CLASS__, 'on_session_sync'), 20, 2);
    }

    /**
     * @return array<string, string>
     */
    public static function get_config() {
        return array(
            'key_id'    => trim((string) get_option('paxdesign_apns_key_id', '')),
            'team_id'   => trim((string) get_option('paxdesign_apns_team_id', '')),
            'key_p8'    => trim((string) get_option('paxdesign_apns_key_p8', '')),
            'bundle_id' => trim((string) get_option('paxdesign_apns_bundle_id', 'at.paxdesign.livechat')),
        );
    }

    public static function is_configured() {
        $cfg = self::get_config();
        return $cfg['key_id'] !== '' && $cfg['team_id'] !== '' && $cfg['key_p8'] !== '';
    }

    /**
     * @return array<int, int>
     */
    public static function get_admin_user_ids() {
        $users = get_users(array('fields' => array('ID')));
        $ids   = array();
        foreach ($users as $user) {
            $uid = (int) $user->ID;
            if ($uid <= 0) {
                continue;
            }
            if (!PAXdesign_Live_Chat_Permissions::has_live_chat_access($uid)) {
                continue;
            }
            if (empty(self::get_user_devices($uid))) {
                continue;
            }
            $ids[] = $uid;
        }
        return $ids;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get_user_devices($user_id) {
        $all = get_user_meta((int) $user_id, self::USER_META_KEY, true);
        return is_array($all) ? $all : array();
    }

    public static function register_device($user_id, $token, $sandbox = false, $bundle_id = '') {
        $token = preg_replace('/[^a-fA-F0-9]/', '', (string) $token);
        if ($token === '') {
            return;
        }

        $all = self::get_user_devices($user_id);
        $all[$token] = array(
            'token'      => $token,
            'sandbox'    => (bool) $sandbox,
            'bundle_id'  => sanitize_text_field($bundle_id),
            'updated_at' => time(),
        );
        update_user_meta((int) $user_id, self::USER_META_KEY, $all);
    }

    public static function unregister_device($user_id, $token) {
        $token = preg_replace('/[^a-fA-F0-9]/', '', (string) $token);
        $all   = self::get_user_devices($user_id);
        if (isset($all[$token])) {
            unset($all[$token]);
            update_user_meta((int) $user_id, self::USER_META_KEY, $all);
        }
    }

    public static function on_live_agent_requested($session_id, $service, $preview, $admin_url, $customer = '') {
        $customer = (string) $customer;
        $service  = (string) $service;
        $preview  = (string) $preview;
        $body     = $customer !== '' ? $customer : 'Kunde wartet auf Support';
        if ($service !== '') {
            $body .= ' · ' . $service;
        }
        if ($preview !== '') {
            $body .= ' — ' . $preview;
        }

        self::send_to_admins(
            'Live-Agent-Anfrage',
            $body,
            array(
                'type'          => 'live_request',
                'session_id'    => (string) $session_id,
                'customer_name' => $customer,
                'service'       => $service,
                'preview'       => $preview,
            ),
            false
        );
    }

    public static function on_new_chat_session($session_id, $service, $preview) {
        self::send_to_admins(
            'Neuer Live-Chat',
            ($service !== '' ? $service . ' — ' : '') . ($preview !== '' ? $preview : 'Neues Kundengespräch'),
            array(
                'type'       => 'new_chat',
                'session_id' => (string) $session_id,
                'service'    => (string) $service,
                'preview'    => (string) $preview,
            ),
            false
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function on_session_sync($session_id, $meta) {
        if (!is_array($meta)) {
            return;
        }

        $preview = isset($meta['preview']) ? (string) $meta['preview'] : '';
        $service = isset($meta['service']) ? (string) $meta['service'] : '';
        $handler = isset($meta['handler']) ? (string) $meta['handler'] : 'ai';
        $seq     = isset($meta['seq']) ? (int) $meta['seq'] : 0;
        $is_new  = !empty($meta['is_new']);

        if ($handler === 'live_request') {
            return;
        }

        $body = ($service !== '' ? $service . ' — ' : '') . ($preview !== '' ? $preview : 'Chat aktualisiert');

        self::send_to_admins(
            $is_new ? 'Neuer Live-Chat' : 'Chat aktualisiert',
            $body,
            array(
                'type'       => $is_new ? 'new_chat' : 'session_sync',
                'session_id' => (string) $session_id,
                'service'    => $service,
                'preview'    => $preview,
                'seq'        => $seq,
                'handler'    => $handler,
            ),
            !$is_new
        );
    }

    public static function notify_new_customer_message($session_id, $content) {
        self::send_to_admins(
            'Neue Kundennachricht',
            wp_html_excerpt((string) $content, 120, '…'),
            array(
                'type'       => 'message',
                'session_id' => (string) $session_id,
                'preview'    => wp_html_excerpt((string) $content, 160, '…'),
            ),
            false
        );
    }

    public static function count_pending_badge() {
        if (!class_exists('PAXdesign_Chat_Log')) {
            return 1;
        }

        global $wpdb;
        PAXdesign_Chat_Log::create_table();
        $table = PAXdesign_Chat_Log::table_name();
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE handler = 'live_request'"
        );
        return max(1, $count);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function send_to_admins($title, $body, $data = array(), $silent = false) {
        if (!self::is_configured()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PAXdesign APNs] skipped — not configured');
            }
            return;
        }

        foreach (self::get_admin_user_ids() as $user_id) {
            $devices = self::get_user_devices($user_id);
            foreach ($devices as $device) {
                $result = self::send($device, $title, $body, $data, $user_id, $silent);
                if (is_wp_error($result) && $result->get_error_code() === 'apns_invalid_token') {
                    self::unregister_device($user_id, (string) $device['token']);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $device
     * @param array<string, mixed> $data
     * @return true|WP_Error
     */
    public static function send($device, $title, $body, $data = array(), $user_id = 0, $silent = false) {
        if (!self::is_configured() || empty($device['token'])) {
            return new WP_Error('apns_not_ready', 'APNs not configured.');
        }

        $cfg     = self::get_config();
        $bundle  = !empty($device['bundle_id']) ? $device['bundle_id'] : $cfg['bundle_id'];
        $sandbox = !empty($device['sandbox']);
        $host    = $sandbox ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';
        $jwt     = self::make_jwt($cfg);
        if ($jwt === '') {
            return new WP_Error('apns_jwt', 'JWT generation failed.');
        }

        $type     = isset($data['type']) ? (string) $data['type'] : 'message';
        $sound    = ($type === 'live_request') ? 'pax-live-request.wav' : 'default';
        $category = ($type === 'live_request') ? 'PAX_LIVE_REQUEST' : 'PAX_MESSAGE';

        $aps = array(
            'badge'              => self::count_pending_badge(),
            'mutable-content'    => 1,
            'content-available'  => 1,
        );

        if (!$silent) {
            $aps['alert'] = array(
                'title' => (string) $title,
                'body'  => (string) $body,
            );
            $aps['sound'] = $sound;
            $aps['interruption-level'] = 'time-sensitive';
            $aps['category'] = $category;
        }

        $payload = array(
            'aps' => $aps,
            'pax' => $data,
        );

        $url = $host . '/3/device/' . $device['token'];
        $push_type = $silent ? 'background' : 'alert';
        $priority  = $silent ? '5' : '10';

        $response = wp_remote_post($url, array(
            'timeout' => 12,
            'headers' => array(
                'authorization'  => 'bearer ' . $jwt,
                'apns-topic'     => $bundle,
                'apns-push-type' => $push_type,
                'apns-priority'  => $priority,
                'apns-expiration'=> (string) (time() + 120),
                'content-type'   => 'application/json',
            ),
            'body'    => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PAXdesign APNs] transport error: ' . $response->get_error_message());
            }
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 410 || $code === 404) {
            return new WP_Error('apns_invalid_token', 'Invalid device token.', array('status' => $code));
        }

        if ($code < 200 || $code >= 300) {
            $body_resp = wp_remote_retrieve_body($response);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PAXdesign APNs] HTTP ' . $code . ' user=' . (int) $user_id . ' body=' . $body_resp);
            }
            return new WP_Error('apns_failed', 'APNs HTTP ' . $code, array('status' => $code));
        }

        return true;
    }

    /**
     * @param array<string, string> $cfg
     */
    private static function make_jwt($cfg) {
        $header  = self::base64url(wp_json_encode(array('alg' => 'ES256', 'kid' => $cfg['key_id'])));
        $claims  = self::base64url(wp_json_encode(array(
            'iss' => $cfg['team_id'],
            'iat' => time(),
        )));
        $input   = $header . '.' . $claims;

        $key = openssl_pkey_get_private($cfg['key_p8']);
        if (!$key) {
            return '';
        }

        $signature = '';
        if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            return '';
        }

        $sig = self::der_to_jose($signature);
        if ($sig === '') {
            return '';
        }

        return $input . '.' . self::base64url($sig);
    }

    private static function base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function der_to_jose($der) {
        $pos = 0;
        if (ord($der[$pos++]) !== 0x30) {
            return '';
        }
        self::read_length($der, $pos);
        if (ord($der[$pos++]) !== 0x02) {
            return '';
        }
        $rlen = self::read_length($der, $pos);
        $r    = substr($der, $pos, $rlen);
        $pos += $rlen;
        if (ord($der[$pos++]) !== 0x02) {
            return '';
        }
        $slen = self::read_length($der, $pos);
        $s    = substr($der, $pos, $slen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    private static function read_length($data, &$pos) {
        $len = ord($data[$pos++]);
        if ($len & 0x80) {
            $n   = $len & 0x1f;
            $len = 0;
            for ($i = 0; $i < $n; $i++) {
                $len = ($len << 8) | ord($data[$pos++]);
            }
        }
        return $len;
    }
}
