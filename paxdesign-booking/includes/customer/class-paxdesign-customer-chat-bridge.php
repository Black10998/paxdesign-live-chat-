<?php
/**
 * Links authenticated WordPress users to durable chat sessions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Chat_Bridge {

    public static function init() {
        add_action('pdx_user_logged_in', array(__CLASS__, 'on_user_login'), 10, 1);
        add_action('wp_login', array(__CLASS__, 'on_user_login'), 10, 2);
    }

    public static function sessions_table() {
        return PAXdesign_Customer_DB::table('chat_sessions');
    }

    public static function claims_table() {
        return PAXdesign_Customer_DB::table('guest_claims');
    }

    /**
     * @param int $user_id
     */
    public static function on_user_login($user_id, $user = null) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }
        self::ensure_primary_session($user_id);
    }

    public static function primary_session_id($user_id) {
        global $wpdb;
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return '';
        }
        $table = self::sessions_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT session_id FROM $table WHERE user_id = %d AND is_primary = 1 ORDER BY linked_at DESC LIMIT 1",
            $user_id
        ));
        if ($row && !empty($row->session_id)) {
            return (string) $row->session_id;
        }
        return self::create_primary_session($user_id);
    }

    public static function create_primary_session($user_id) {
        $user_id = absint($user_id);
        $session_id = self::generate_session_id($user_id);
        self::link_session($user_id, $session_id, 'primary', '', true);
        self::sync_chat_log_user($session_id, $user_id);
        return $session_id;
    }

    private static function generate_session_id($user_id) {
        return 'pax_u' . $user_id . '_' . strtolower(wp_generate_password(10, false, false));
    }

    /**
     * Secure guest session claim using device token hash match.
     *
     * @return true|WP_Error
     */
    public static function claim_guest_session($user_id, $session_id, $device_token) {
        global $wpdb;
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        if ($user_id <= 0 || $session_id === '') {
            return new WP_Error('invalid_claim', __('Invalid claim request.', 'paxdesign-booking'), array('status' => 400));
        }

        $live = PAXdesign_Chat_Live::get_instance();
        $token = $live->sanitize_device_token($device_token);
        if ($token === '') {
            return new WP_Error('missing_device_token', __('Device verification required to claim this conversation.', 'paxdesign-booking'), array('status' => 403));
        }

        if (!$live->verify_session_ownership($session_id, $token)) {
            return new WP_Error('claim_denied', __('This conversation could not be verified for your account.', 'paxdesign-booking'), array('status' => 403));
        }

        $logs = PAXdesign_Chat_Log::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT session_id, wp_user_id FROM $logs WHERE session_id = %s LIMIT 1", $session_id));
        if (!$row) {
            return new WP_Error('not_found', __('Conversation not found.', 'paxdesign-booking'), array('status' => 404));
        }
        if (!empty($row->wp_user_id) && (int) $row->wp_user_id !== $user_id) {
            return new WP_Error('already_claimed', __('This conversation belongs to another account.', 'paxdesign-booking'), array('status' => 409));
        }

        $hash = self::hash_device_token($token);
        $claims = self::claims_table();
        $wpdb->replace($claims, array(
            'user_id'           => $user_id,
            'session_id'        => $session_id,
            'device_token_hash' => $hash,
            'claimed_at'        => current_time('mysql', true),
        ), array('%d', '%s', '%s', '%s'));

        self::link_session($user_id, $session_id, 'guest_claim', $hash, false);
        self::sync_chat_log_user($session_id, $user_id);
        return true;
    }

    public static function link_session($user_id, $session_id, $method = 'login', $device_hash = '', $is_primary = false) {
        global $wpdb;
        $table = self::sessions_table();
        if ($is_primary) {
            $wpdb->update($table, array('is_primary' => 0), array('user_id' => $user_id), array('%d'), array('%d'));
        }
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE session_id = %s LIMIT 1", $session_id));
        $data = array(
            'user_id'           => $user_id,
            'session_id'        => $session_id,
            'is_primary'        => $is_primary ? 1 : 0,
            'linked_at'         => current_time('mysql', true),
            'link_method'       => sanitize_key($method),
            'device_token_hash' => sanitize_text_field($device_hash),
        );
        if ($existing) {
            $wpdb->update($table, $data, array('id' => (int) $existing));
        } else {
            $wpdb->insert($table, $data);
        }
    }

    public static function sync_chat_log_user($session_id, $user_id) {
        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();
        $wpdb->update(
            $table,
            array('wp_user_id' => absint($user_id)),
            array('session_id' => $session_id),
            array('%d'),
            array('%s')
        );
    }

    public static function user_owns_session($user_id, $session_id) {
        global $wpdb;
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        if ($user_id <= 0 || $session_id === '') {
            return false;
        }
        if (user_can($user_id, 'manage_options') || PAXdesign_Live_Chat_Permissions::has_live_chat_access($user_id)) {
            return true;
        }
        $linked = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM " . self::sessions_table() . " WHERE user_id = %d AND session_id = %s",
            $user_id,
            $session_id
        ));
        if ($linked > 0) {
            return true;
        }
        $logs = PAXdesign_Chat_Log::table_name();
        $owner = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM $logs WHERE session_id = %s LIMIT 1",
            $session_id
        ));
        return $owner === $user_id;
    }

    public static function list_user_sessions($user_id) {
        global $wpdb;
        $user_id = absint($user_id);
        $table = PAXdesign_Chat_Log::table_name();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT session_id, customer_name, updated_at, handler, message_count, last_preview, session_rating
             FROM $table WHERE wp_user_id = %d ORDER BY updated_at DESC LIMIT 50",
            $user_id
        ), ARRAY_A);
    }

    private static function sanitize_session_id($session_id) {
        $session_id = sanitize_text_field($session_id);
        if ($session_id === '' || !preg_match('/^pax_[a-z0-9_]+$/i', $session_id)) {
            return '';
        }
        return $session_id;
    }

    private static function hash_device_token($token) {
        return hash_hmac('sha256', $token, wp_salt('auth'));
    }

    private static function ensure_primary_session($user_id) {
        if (self::primary_session_id($user_id) !== '') {
            return;
        }
        self::create_primary_session($user_id);
    }

    /**
     * Send a customer message on an owned session (human queue or AI transcript).
     *
     * @return array|WP_Error
     */
    public static function send_user_message($user_id, $session_id, $content, $extra = array()) {
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        $content = sanitize_textarea_field($content);

        if ($user_id <= 0 || $session_id === '' || $content === '') {
            return new WP_Error('invalid_payload', __('Message and session are required.', 'paxdesign-booking'), array('status' => 400));
        }
        if (!self::user_owns_session($user_id, $session_id)) {
            return new WP_Error('forbidden', __('You do not have access to this conversation.', 'paxdesign-booking'), array('status' => 403));
        }

        $live = PAXdesign_Chat_Live::get_instance();
        $handler = $live->get_handler($session_id);
        if ($handler === PAXdesign_Chat_Live::HANDLER_CLOSED) {
            return new WP_Error('chat_closed', __('This conversation is closed.', 'paxdesign-booking'), array('status' => 409));
        }

        $live->ensure_session($session_id);
        self::sync_chat_log_user($session_id, $user_id);

        $message_extra = array();
        if (!empty($extra['reply_to'])) {
            $message_extra['reply_to'] = absint($extra['reply_to']);
        }
        if (!empty($extra['client_msg_id'])) {
            $message_extra['client_msg_id'] = sanitize_text_field($extra['client_msg_id']);
        }
        if (class_exists('PAXdesign_Link_Scanner')) {
            $message_extra = PAXdesign_Link_Scanner::attach_scan_meta($content, 'user', $message_extra);
        }

        $entry = $live->append_message($session_id, 'user', $content, $message_extra);
        if (is_wp_error($entry)) {
            return $entry;
        }
        if (!$entry) {
            return new WP_Error('send_failed', __('Could not send message.', 'paxdesign-booking'), array('status' => 500));
        }

        $live->clear_typing($session_id, 'user');

        if (empty($entry['_deduplicated']) && $live->is_human_queue($session_id) && class_exists('PAXdesign_Live_Chat_PWA')) {
            PAXdesign_Live_Chat_PWA::notify_new_customer_message($session_id, $content);
        }

        return array(
            'message' => $entry,
            'handler' => $handler,
            'session_id' => $session_id,
        );
    }
}
