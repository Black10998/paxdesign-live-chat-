<?php
/**
 * Links authenticated WordPress users to durable chat sessions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Chat_Bridge {

    /** Seconds without customer activity before live-request queue is released. */
    const CUSTOMER_PRESENCE_TIMEOUT_SECONDS = 180;

    public static function init() {
        // Sessions are allocated lazily on first chat message — never on login or page load.
    }

    public static function sessions_table() {
        return PAXdesign_Customer_DB::table('chat_sessions');
    }

    public static function claims_table() {
        return PAXdesign_Customer_DB::table('guest_claims');
    }

    /**
     * Resolve the durable session id for a customer without creating a chat log row.
     */
    public static function lookup_primary_session_id($user_id) {
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
            return self::sanitize_session_id((string) $row->session_id);
        }
        return self::resolve_or_create_primary_session($user_id);
    }

    /**
     * Backward-compatible alias — lookup only, no chat log materialization.
     */
    public static function primary_session_id($user_id) {
        return self::lookup_primary_session_id($user_id);
    }

    /**
     * Create the chat log row on first write (message, attachment, or live handoff).
     */
    public static function materialize_session($session_id, $user_id = 0) {
        $session_id = self::sanitize_session_id($session_id);
        $user_id = absint($user_id);
        if ($session_id === '') {
            return '';
        }
        PAXdesign_Chat_Live::get_instance()->ensure_session($session_id, array(), $user_id);
        if ($user_id > 0) {
            self::sync_customer_profile($session_id, $user_id);
        }
        return self::ensure_persistent_session_open($session_id, $user_id);
    }

    /**
     * Bind authenticated customer identity to a chat log row (name + wp_user_id).
     */
    public static function sync_customer_profile($session_id, $user_id) {
        global $wpdb;
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        if ($user_id <= 0 || $session_id === '') {
            return;
        }
        $identity = PAXdesign_Chat_Live::resolve_customer_identity($user_id, '');
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'wp_user_id'    => $user_id,
                'customer_name' => sanitize_text_field($identity['name']),
                'updated_at'    => current_time('mysql'),
            ),
            array('session_id' => $session_id),
            array('%d', '%s', '%s'),
            array('%s')
        );
    }

    /**
     * Keep authenticated customer conversations open — never expose a closed state.
     */
    public static function ensure_persistent_session_open($session_id, $user_id = 0) {
        $live = PAXdesign_Chat_Live::get_instance();
        $session_id = self::sanitize_session_id($session_id);
        if ($session_id === '') {
            return '';
        }
        if ($user_id <= 0) {
            global $wpdb;
            $logs = PAXdesign_Chat_Log::table_name();
            $user_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT wp_user_id FROM $logs WHERE session_id = %s LIMIT 1",
                $session_id
            ));
        }
        if ($user_id <= 0) {
            return $session_id;
        }
        if ($live->get_handler($session_id) === PAXdesign_Chat_Live::HANDLER_CLOSED) {
            return self::reopen_closed_session($session_id);
        }
        return $session_id;
    }

    /**
     * Hide lifecycle noise from persistent customer transcripts.
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    public static function filter_customer_lifecycle_messages($messages) {
        if (!is_array($messages)) {
            return array();
        }
        return array_values(array_filter($messages, function ($msg) {
            if (!is_array($msg) || ($msg['role'] ?? '') !== 'system') {
                return true;
            }
            $content = strtolower((string) ($msg['content'] ?? ''));
            $client_id = (string) ($msg['client_msg_id'] ?? '');
            if ($client_id === 'sys:session_started') {
                return false;
            }
            $blocked = array(
                'closed', 'geschlossen', 'beendet', 'ended', 'conversation ended',
                'session closed', 'neues gespräch', 'new chat', 'new conversation',
                'start a new', 'inactivity', 'inaktivität', 'مغلق', 'انتهت',
                'chat-session gestartet', 'session started', 'started session',
            );
            foreach ($blocked as $needle) {
                if (strpos($content, $needle) !== false) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Re-open a closed conversation without creating a new session id.
     */
    public static function reopen_closed_session($session_id) {
        global $wpdb;
        $live = PAXdesign_Chat_Live::get_instance();
        $session_id = self::sanitize_session_id($session_id);
        if ($session_id === '') {
            return '';
        }
        if ($live->get_handler($session_id) !== PAXdesign_Chat_Live::HANDLER_CLOSED) {
            return $session_id;
        }
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'    => PAXdesign_Chat_Live::HANDLER_AI,
                'updated_at' => current_time('mysql'),
            ),
            array('session_id' => $session_id),
            array('%s', '%s'),
            array('%s')
        );
        $wp_user_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM " . PAXdesign_Chat_Log::table_name() . " WHERE session_id = %s LIMIT 1",
            $session_id
        ));
        self::materialize_session($session_id, $wp_user_id);
        return $session_id;
    }

    /**
     * Re-open the same conversation by default; rotate only when explicitly requested.
     */
    public static function renew_closed_session($user_id, $closed_session_id, $force_new = false) {
        $closed_session_id = self::sanitize_session_id($closed_session_id);
        if ($force_new) {
            return self::rotate_primary_session($user_id, $closed_session_id);
        }
        if ($closed_session_id !== '') {
            return self::reopen_closed_session($closed_session_id);
        }
        return self::primary_session_id($user_id);
    }

    /**
     * Replace a closed primary session with a fresh open conversation.
     */
    private static function rotate_primary_session($user_id, $closed_session_id) {
        global $wpdb;
        $user_id = absint($user_id);
        $closed_session_id = self::sanitize_session_id($closed_session_id);
        if ($user_id <= 0) {
            return self::create_primary_session($user_id);
        }
        $table = self::sessions_table();
        if ($closed_session_id !== '') {
            $wpdb->update(
                $table,
                array('is_primary' => 0),
                array('user_id' => $user_id, 'session_id' => $closed_session_id),
                array('%d'),
                array('%d', '%s')
            );
        }
        return self::create_primary_session($user_id);
    }

    public static function create_primary_session($user_id) {
        $user_id = absint($user_id);
        $session_id = self::generate_session_id($user_id);
        self::link_session($user_id, $session_id, 'primary', '', true);
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
        return new WP_Error(
            'guest_claim_removed',
            __('Anonymous sessions are no longer supported. Please sign in to continue chatting.', 'paxdesign-booking'),
            array('status' => 410)
        );
    }

    /**
     * When a guest conversation has more history than the empty primary, promote it.
     */
    private static function promote_richer_session_to_primary($user_id, $candidate_session_id) {
        global $wpdb;
        $user_id = absint($user_id);
        $candidate_session_id = self::sanitize_session_id($candidate_session_id);
        if ($user_id <= 0 || $candidate_session_id === '') {
            return;
        }

        $sessions = self::sessions_table();
        $logs = PAXdesign_Chat_Log::table_name();
        $primary_row = $wpdb->get_row($wpdb->prepare(
            "SELECT session_id FROM $sessions WHERE user_id = %d AND is_primary = 1 ORDER BY linked_at DESC LIMIT 1",
            $user_id
        ));
        $primary_id = $primary_row ? self::sanitize_session_id((string) $primary_row->session_id) : '';
        if ($primary_id === $candidate_session_id) {
            return;
        }

        $candidate_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT message_count FROM $logs WHERE session_id = %s LIMIT 1",
            $candidate_session_id
        ));
        $primary_count = $primary_id !== ''
            ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT message_count FROM $logs WHERE session_id = %s LIMIT 1",
                $primary_id
            ))
            : 0;

        if ($candidate_count <= $primary_count) {
            return;
        }

        if ($primary_id !== '') {
            $wpdb->update(
                $sessions,
                array('is_primary' => 0),
                array('user_id' => $user_id, 'session_id' => $primary_id),
                array('%d'),
                array('%d', '%s')
            );
        }
        $wpdb->update(
            $sessions,
            array('is_primary' => 1),
            array('user_id' => $user_id, 'session_id' => $candidate_session_id),
            array('%d'),
            array('%d', '%s')
        );
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

    /**
     * Rewrite website AJAX session IDs to the logged-in customer's primary conversation.
     */
    public static function resolve_ajax_session($user_id, $session_id) {
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        if ($user_id <= 0) {
            return $session_id;
        }

        if ($session_id !== '' && class_exists('PAXdesign_Chat_Live')) {
            $embedded = PAXdesign_Chat_Live::user_id_from_session_id($session_id);
            if ($embedded > 0 && $embedded !== $user_id) {
                $session_id = '';
            }
        }

        $primary = self::primary_session_id($user_id);
        if ($primary === '') {
            return $session_id;
        }

        if ($session_id === '' || $session_id !== $primary) {
            $session_id = $primary;
        }

        self::sync_chat_log_user($session_id, $user_id);
        return self::ensure_persistent_session_open($session_id, $user_id);
    }

    /**
     * Record customer activity and expire abandoned live-request queue slots.
     */
    public static function touch_customer_presence($session_id, $user_id = 0) {
        global $wpdb;
        $session_id = self::sanitize_session_id($session_id);
        if ($session_id === '') {
            return $session_id;
        }
        $table = PAXdesign_Chat_Log::table_name();
        $wpdb->update(
            $table,
            array('customer_last_seen_at' => current_time('mysql')),
            array('session_id' => $session_id),
            array('%s'),
            array('%s')
        );
        return self::expire_inactive_session_if_needed($session_id, $user_id);
    }

    /**
     * Mark the customer as disconnected (page closed / app backgrounded).
     */
    public static function mark_customer_disconnected($session_id, $user_id = 0) {
        $session_id = self::sanitize_session_id($session_id);
        if ($session_id === '') {
            return '';
        }
        if ($user_id > 0 && !self::user_owns_session($user_id, $session_id)) {
            return '';
        }
        return self::expire_inactive_session_if_needed($session_id, $user_id, true);
    }

    /**
     * Release stale live-request handlers after customer inactivity.
     *
     * @param bool $force When true, treat as immediately disconnected.
     */
    public static function expire_inactive_session_if_needed($session_id, $user_id = 0, $force = false) {
        global $wpdb;
        $session_id = self::sanitize_session_id($session_id);
        if ($session_id === '') {
            return '';
        }
        if ($user_id > 0 && !self::user_owns_session($user_id, $session_id)) {
            return '';
        }

        $table = PAXdesign_Chat_Log::table_name();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT handler, customer_last_seen_at, wp_user_id FROM $table WHERE session_id = %s LIMIT 1",
            $session_id
        ));
        if (!$row) {
            return $session_id;
        }

        $handler = isset($row->handler) ? (string) $row->handler : PAXdesign_Chat_Live::HANDLER_AI;
        if ($handler !== PAXdesign_Chat_Live::HANDLER_LIVE) {
            return $session_id;
        }

        $last_seen = isset($row->customer_last_seen_at) ? (string) $row->customer_last_seen_at : '';
        $elapsed = $force ? self::CUSTOMER_PRESENCE_TIMEOUT_SECONDS + 1 : 0;
        if (!$force && $last_seen !== '') {
            $ts = strtotime($last_seen);
            if ($ts) {
                $elapsed = time() - $ts;
            }
        }
        if ($elapsed < self::CUSTOMER_PRESENCE_TIMEOUT_SECONDS) {
            return $session_id;
        }

        $live = PAXdesign_Chat_Live::get_instance();
        $live->release_abandoned_live_request($session_id);
        return $session_id;
    }

    /**
     * Verify logged-in customer matches session owner (defense in depth).
     *
     * @return true|WP_Error
     */
    public static function assert_session_owner($user_id, $session_id) {
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        if ($user_id <= 0 || $session_id === '') {
            return new WP_Error('forbidden', __('Invalid session.', 'paxdesign-booking'), array('status' => 403));
        }
        if (!self::user_owns_session($user_id, $session_id)) {
            return new WP_Error('forbidden', __('You do not have access to this conversation.', 'paxdesign-booking'), array('status' => 403));
        }
        if (class_exists('PAXdesign_Chat_Live')) {
            $embedded = PAXdesign_Chat_Live::user_id_from_session_id($session_id);
            if ($embedded > 0 && $embedded !== $user_id) {
                return new WP_Error('forbidden', __('Session belongs to another account.', 'paxdesign-booking'), array('status' => 403));
            }
        }
        return true;
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
        if ($user_id <= 0) {
            return array();
        }
        $table = PAXdesign_Chat_Log::table_name();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT session_id, customer_name, updated_at, handler, message_count, last_preview, session_rating
             FROM $table WHERE wp_user_id = %d ORDER BY updated_at DESC LIMIT 50",
            $user_id
        ), ARRAY_A);
        $items = array();
        foreach ($rows ?: array() as $row) {
            $session_id = sanitize_text_field((string) ($row['session_id'] ?? ''));
            if ($session_id === '') {
                continue;
            }
            $items[] = array(
                'session_id'     => $session_id,
                'last_preview'   => sanitize_text_field((string) ($row['last_preview'] ?? '')),
                'handler'        => sanitize_key((string) ($row['handler'] ?? 'ai')),
                'message_count'  => (int) ($row['message_count'] ?? 0),
                'updated_at'     => sanitize_text_field((string) ($row['updated_at'] ?? '')),
            );
        }
        return $items;
    }

    private static function sanitize_session_id($session_id) {
        return PAXdesign_Chat_Live::get_instance()->sanitize_session_id($session_id);
    }

    private static function hash_device_token($token) {
        return hash_hmac('sha256', $token, wp_salt('auth'));
    }

    private static function resolve_or_create_primary_session($user_id) {
        $existing = self::resolve_existing_session_from_logs($user_id);
        if ($existing !== '') {
            self::link_session($user_id, $existing, 'history', '', true);
            return $existing;
        }
        return self::create_primary_session($user_id);
    }

    /**
     * Empty poll payload for sessions that exist in chat_sessions but have no chat log yet.
     *
     * @return array<string, mixed>
     */
    public static function empty_poll_payload($session_id) {
        return array(
            'session_id'         => self::sanitize_session_id($session_id),
            'handler'            => PAXdesign_Chat_Live::HANDLER_AI,
            'handler_label'      => PAXdesign_Chat_Live::handler_label(PAXdesign_Chat_Live::HANDLER_AI, ''),
            'admin_user_id'      => 0,
            'admin_name'         => '',
            'assigned_agent'     => null,
            'customer_name'      => '',
            'session_rating'     => 0,
            'detected_service'   => '',
            'updated_at'         => '',
            'seq'                => 0,
            'message_count'      => 0,
            'last_read_seq'      => 0,
            'admin_read_seq'     => 0,
            'other_read_seq'     => 0,
            'unread_staff_count' => 0,
            'messages'           => array(),
            'admin_typing'       => false,
            'assistant_typing'   => false,
            'user_typing'        => false,
            'reactions'          => array(),
            'customer_language'  => '',
            'auth_user_id'       => 0,
            'wp_user_id'         => 0,
        );
    }

    private static function resolve_existing_session_from_logs($user_id) {
        global $wpdb;
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return '';
        }
        $table = PAXdesign_Chat_Log::table_name();
        $session_id = $wpdb->get_var($wpdb->prepare(
            "SELECT session_id FROM $table WHERE wp_user_id = %d ORDER BY updated_at DESC LIMIT 1",
            $user_id
        ));
        return $session_id ? self::sanitize_session_id((string) $session_id) : '';
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
        $renewed = false;
        if ($handler === PAXdesign_Chat_Live::HANDLER_CLOSED) {
            $session_id = self::reopen_closed_session($session_id);
            self::materialize_session($session_id, $user_id);
            $handler = $live->get_handler($session_id);
            $renewed = true;
        } elseif (!$live->get_session_row($session_id)) {
            $session_id = self::materialize_session($session_id, $user_id);
            $handler = $live->get_handler($session_id);
        }

        if (!$live->is_human_queue($session_id)) {
            $result = PAXdesign_Chat::get_instance()->complete_authenticated_customer_chat(
                $session_id,
                $content,
                !empty($extra['client_msg_id']) ? (string) $extra['client_msg_id'] : '',
                !empty($extra['assistant_client_msg_id']) ? (string) $extra['assistant_client_msg_id'] : ''
            );
            if (is_wp_error($result)) {
                return $result;
            }
            if ($renewed && is_array($result)) {
                $result['session_id'] = $session_id;
                $result['renewed'] = true;
            }
            return $result;
        }

        self::materialize_session($session_id, $user_id);

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

        $live->clear_typing_indicator($session_id, 'user');

        if (empty($entry['_deduplicated']) && $live->is_human_queue($session_id) && class_exists('PAXdesign_Live_Chat_PWA')) {
            PAXdesign_Live_Chat_PWA::notify_new_customer_message($session_id, $content);
        }

        $payload = array(
            'message'    => $live->format_sse_message_payload($entry, 0),
            'handler'    => $handler,
            'session_id' => $session_id,
        );
        if ($renewed) {
            $payload['renewed'] = true;
        }
        return $payload;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function send_user_attachment($user_id, $session_id, $kind, $file, $caption = '', $extra = array()) {
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        if ($user_id <= 0 || $session_id === '' || !self::user_owns_session($user_id, $session_id)) {
            return new WP_Error('forbidden', __('You do not have access to this conversation.', 'paxdesign-booking'), array('status' => 403));
        }

        $live = PAXdesign_Chat_Live::get_instance();
        $handler = $live->get_handler($session_id);
        $renewed = false;
        if ($handler === PAXdesign_Chat_Live::HANDLER_CLOSED) {
            $session_id = self::reopen_closed_session($session_id);
            self::materialize_session($session_id, $user_id);
            $handler = $live->get_handler($session_id);
            $renewed = true;
        } elseif (!$live->get_session_row($session_id)) {
            self::materialize_session($session_id, $user_id);
            $handler = $live->get_handler($session_id);
        }
        if (!$live->is_human_queue($session_id)) {
            $ccs_session = class_exists('PAXdesign_Cybercrime_AI_Case')
                && PAXdesign_Cybercrime_AI_Case::is_ccs_session($session_id);
            if (!$ccs_session) {
                return new WP_Error('use_ai_stream', __('Attachments are available during human support.', 'paxdesign-booking'), array('status' => 409));
            }
        }

        $message_extra = array();
        $upload = null;
        if (!empty($extra['client_msg_id'])) {
            $message_extra['client_msg_id'] = sanitize_text_field($extra['client_msg_id']);
        }

        if ($kind === 'location') {
            $lat = isset($extra['lat']) ? (float) $extra['lat'] : 0.0;
            $lng = isset($extra['lng']) ? (float) $extra['lng'] : 0.0;
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return new WP_Error('invalid_location', __('Invalid coordinates.', 'paxdesign-booking'), array('status' => 400));
            }
            $label = sanitize_text_field($extra['label'] ?? '');
            $message_extra['location_lat'] = $lat;
            $message_extra['location_lng'] = $lng;
            $message_extra['location_label'] = $label;
            $message_extra['attachment_type'] = 'location';
            $caption = $label !== '' ? $label : __('Shared location', 'paxdesign-booking');
        } else {
            $upload = PAXdesign_Customer_Media::handle_upload($file, $kind === 'voice' ? 'voice' : ($kind === 'image' ? 'image' : 'file'));
            if (is_wp_error($upload)) {
                return $upload;
            }
            if ($kind === 'image') {
                $message_extra['image_url'] = $upload['url'];
                $message_extra['attachment_type'] = 'image';
            } elseif ($kind === 'voice') {
                $message_extra['audio_url'] = $upload['url'];
                $message_extra['attachment_type'] = 'voice';
                if (!empty($extra['duration'])) {
                    $message_extra['audio_duration'] = max(0, (float) $extra['duration']);
                }
            } else {
                $message_extra['file_url'] = $upload['url'];
                $message_extra['file_name'] = $upload['name'];
                $message_extra['file_mime'] = $upload['mime'];
                $message_extra['attachment_type'] = 'file';
            }
        }

        self::materialize_session($session_id, $user_id);
        $caption = sanitize_textarea_field($caption);
        $entry = $live->append_message($session_id, 'user', $caption, $message_extra);
        if (is_wp_error($entry)) {
            return $entry;
        }
        if (!$entry) {
            return new WP_Error('send_failed', __('Could not send attachment.', 'paxdesign-booking'), array('status' => 500));
        }
        if (empty($entry['_deduplicated']) && class_exists('PAXdesign_Live_Chat_PWA')) {
            PAXdesign_Live_Chat_PWA::notify_new_customer_message($session_id, $caption !== '' ? $caption : '[' . $kind . ']');
        }
        if (!empty($upload) && is_array($upload) && class_exists('PAXdesign_Cybercrime_AI_Case') && PAXdesign_Cybercrime_AI_Case::is_ccs_session($session_id)) {
            PAXdesign_Cybercrime_AI_Case::attach_chat_upload($user_id, $session_id, $upload, $kind, $caption);
        }
        $payload = array(
            'message'    => $entry,
            'handler'    => $handler,
            'session_id' => $session_id,
        );
        if ($renewed) {
            $payload['renewed'] = true;
        }
        return $payload;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function set_typing($user_id, $session_id, $stop = false) {
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        if ($user_id <= 0 || $session_id === '' || !self::user_owns_session($user_id, $session_id)) {
            return new WP_Error('forbidden', __('Invalid session.', 'paxdesign-booking'), array('status' => 403));
        }
        $live = PAXdesign_Chat_Live::get_instance();
        if (!$live->is_human_queue($session_id)) {
            return array('ok' => false);
        }
        return $live->rest_customer_typing($session_id, (bool) $stop);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function close_session($user_id, $session_id) {
        $user_id = absint($user_id);
        $session_id = self::sanitize_session_id($session_id);
        if ($user_id <= 0 || $session_id === '' || !self::user_owns_session($user_id, $session_id)) {
            return new WP_Error('forbidden', __('Invalid session.', 'paxdesign-booking'), array('status' => 403));
        }
        $live = PAXdesign_Chat_Live::get_instance();
        $result = $live->customer_release_to_ai($session_id, $user_id);
        if (is_wp_error($result)) {
            return $result;
        }
        return array_merge($result, array('ok' => true));
    }
}
