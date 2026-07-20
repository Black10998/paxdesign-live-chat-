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
        add_action('paxdesign_live_agent_requested_language', array(__CLASS__, 'on_live_agent_requested_language'), 20, 6);
        add_action('paxdesign_new_chat_session', array(__CLASS__, 'on_new_chat_session'), 20, 3);
        add_action('paxdesign_session_sync', array(__CLASS__, 'on_session_sync'), 20, 2);
        add_action('paxdesign_chat_live_missed', array(__CLASS__, 'on_missed_chat'), 20, 4);
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
    public static function get_live_chat_user_ids() {
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
            $ids[] = $uid;
        }
        return $ids;
    }

    /**
     * @return int
     */
    public static function count_active_devices() {
        $total = 0;
        foreach (self::get_live_chat_user_ids() as $uid) {
            foreach (self::get_user_devices((int) $uid) as $device) {
                if (empty($device['token']) || !empty($device['revoked'])) {
                    continue;
                }
                if (isset($device['approved']) && empty($device['approved'])) {
                    continue;
                }
                $total++;
            }
        }
        return $total;
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

    public static function register_device($user_id, $token, $sandbox = false, $bundle_id = '', $meta = array()) {
        $token = self::normalize_token($token);
        if ($token === '') {
            return false;
        }

        $all = self::get_user_devices($user_id);
        $device_id = isset($meta['device_id']) ? sanitize_text_field((string) $meta['device_id']) : '';
        if ($device_id !== '') {
            $all = self::purge_stale_device_tokens($all, $device_id, $token);
        }

        $existing = isset($all[$token]) && is_array($all[$token]) ? $all[$token] : array();
        $was_revoked = !empty($existing['revoked']) || (isset($existing['approved']) && empty($existing['approved']));
        $now = time();

        $record = array_merge($existing, array(
            'token'      => $token,
            'sandbox'    => (bool) $sandbox,
            'bundle_id'  => sanitize_text_field($bundle_id),
            'updated_at' => $now,
            'session_only' => false,
        ));

        if (class_exists('PAXdesign_Device_Sessions')) {
            $record = PAXdesign_Device_Sessions::merge_device_meta($record, $meta, $now);
        }

        if ($was_revoked && empty($existing['revoked_reason'])) {
            $record['approved'] = false;
            $record['revoked']  = true;
        } else {
            $record['approved'] = true;
            $record['revoked']  = false;
            unset($record['revoked_at'], $record['revoked_by'], $record['revoked_reason']);
        }
        $all[$token] = $record;
        if ($device_id !== '') {
            $session_key = 'did_' . $device_id;
            if (isset($all[$session_key])) {
                unset($all[$session_key]);
            }
        }
        if (class_exists('PAXdesign_Device_Sessions')) {
            $all = PAXdesign_Device_Sessions::enforce_single_device_login(
                $all,
                isset($record['device_id']) ? (string) $record['device_id'] : '',
                $token
            );
        }
        update_user_meta((int) $user_id, self::USER_META_KEY, $all);
        return true;
    }

    /**
     * Normalize and validate an APNs device token (hex, 32–256 chars).
     */
    public static function normalize_token($token) {
        $token = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $token));
        $length = strlen($token);
        if ($length < 32 || $length > 256) {
            return '';
        }
        return $token;
    }

    /**
     * Whether a stored device record is push-capable.
     *
     * @param array<string, mixed> $device
     */
    public static function device_is_push_enabled(array $device) {
        return !empty($device['token'])
            && empty($device['session_only'])
            && empty($device['revoked'])
            && (!isset($device['approved']) || !empty($device['approved']));
    }

    /**
     * Register or refresh a device session before an APNs token is available.
     *
     * @param array<string, mixed> $meta
     */
    public static function register_device_session($user_id, $meta = array()) {
        if (!is_array($meta)) {
            $meta = array();
        }
        $device_id = isset($meta['device_id']) ? sanitize_text_field((string) $meta['device_id']) : '';
        if ($device_id === '') {
            return;
        }

        $all = self::get_user_devices($user_id);
        $now = time();
        $session_key = 'did_' . $device_id;
        $existing = isset($all[$session_key]) && is_array($all[$session_key]) ? $all[$session_key] : array();

        foreach ($all as $key => $device) {
            if (!is_array($device) || $key === $session_key) {
                continue;
            }
            $id = isset($device['device_id']) ? (string) $device['device_id'] : '';
            if ($id === $device_id && !empty($device['token'])) {
                if (class_exists('PAXdesign_Device_Sessions')) {
                    $all[$key] = PAXdesign_Device_Sessions::merge_device_meta($device, $meta, $now);
                }
                update_user_meta((int) $user_id, self::USER_META_KEY, $all);
                return;
            }
        }

        $record = array_merge($existing, array(
            'device_id'    => $device_id,
            'session_only' => true,
            'approved'     => true,
            'revoked'      => false,
            'updated_at'   => $now,
        ));
        if (class_exists('PAXdesign_Device_Sessions')) {
            $record = PAXdesign_Device_Sessions::merge_device_meta($record, $meta, $now);
        }
        $all[$session_key] = $record;
        update_user_meta((int) $user_id, self::USER_META_KEY, $all);
    }

    /**
     * @param array<string, array<string, mixed>> $all
     * @return array<string, array<string, mixed>>
     */
    private static function purge_stale_device_tokens(array $all, $device_id, $except_token = '') {
        foreach ($all as $key => $device) {
            if (!is_array($device)) {
                continue;
            }
            if ($except_token !== '' && $key === $except_token) {
                continue;
            }
            $id = isset($device['device_id']) ? (string) $device['device_id'] : '';
            if ($id !== '' && $id === $device_id) {
                unset($all[$key]);
            }
        }
        return $all;
    }

    /**
     * @return string
     */
    private static function sound_for_type($type, $handler = '', $event = '') {
        if ($event === 'customer_waiting' || $type === 'live_request') {
            return 'pax-live-request.wav';
        }
        if ($event === 'missed_chat' || $event === 'new_lead_contact') {
            return 'pax-ai-alert.wav';
        }
        if ($type === 'ai_attention' || ($type === 'session_sync' && $handler === 'ai')) {
            return 'pax-ai-alert.wav';
        }
        if ($type === 'message' || $type === 'new_chat' || $type === 'order_update' || $type === 'project_update' || $type === 'news') {
            return 'pax-message.wav';
        }
        if ($type === 'security_alert') {
            return 'pax-ai-alert.wav';
        }
        return 'pax-message.wav';
    }

    public static function unregister_device($user_id, $token) {
        $token = preg_replace('/[^a-fA-F0-9]/', '', (string) $token);
        $all   = self::get_user_devices($user_id);
        if (isset($all[$token])) {
            unset($all[$token]);
            update_user_meta((int) $user_id, self::USER_META_KEY, $all);
        }
    }

    public static function on_live_agent_requested_language($session_id, $language, $service, $preview, $admin_url, $customer = '') {
        self::dispatch_live_agent_push($session_id, (string) $language, $service, $preview, $customer);
    }

    /**
     * @param string $language Customer language code (de|en|ar) or empty.
     */
    private static function dispatch_live_agent_push($session_id, $language, $service, $preview, $customer = '') {
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
        if ($language !== '' && class_exists('PAXdesign_Language_Routing')) {
            $body .= ' · ' . PAXdesign_Language_Routing::label($language);
        }

        $payload = array(
            'type'               => 'live_request',
            'event'              => 'customer_waiting',
            'session_id'         => (string) $session_id,
            'customer_name'      => $customer,
            'service'            => $service,
            'preview'            => $preview,
            'customer_language'  => (string) $language,
        );

        $primary_ids = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::admin_user_ids_for_language($language)
            : self::get_admin_user_ids();

        self::send_to_user_ids(
            $primary_ids,
            'Kunde wartet',
            $body,
            $payload,
            false
        );

        $all_ids = self::get_admin_user_ids();
        $secondary = array_values(array_diff($all_ids, $primary_ids));
        if (!empty($secondary)) {
            self::send_to_user_ids(
                $secondary,
                'Kunde wartet',
                $body,
                $payload,
                true
            );
        }
    }

    public static function on_new_chat_session($session_id, $service, $preview) {
        $preview = trim((string) $preview);
        if ($preview === '' || self::is_system_session_preview($preview)) {
            return;
        }
        self::send_to_admins(
            'Neuer Chat gestartet',
            ($service !== '' ? $service . ' — ' : '') . ($preview !== '' ? $preview : 'Neues Kundengespräch'),
            array(
                'type'       => 'new_chat',
                'event'      => 'new_chat_started',
                'session_id' => (string) $session_id,
                'service'    => (string) $service,
                'preview'    => (string) $preview,
            ),
            false
        );
    }

    public static function notify_new_customer_order($order_id, $order_ref, $customer_name, $service_label, $body) {
        $order_id = absint($order_id);
        if ($order_id <= 0) {
            return;
        }
        $title = sprintf(__('New request from %s', 'paxdesign-booking'), $customer_name !== '' ? $customer_name : __('Customer', 'paxdesign-booking'));
        self::send_to_admins(
            $title,
            $body !== '' ? $body : $service_label,
            array(
                'type'          => 'customer_order',
                'event'         => 'new_customer_order',
                'order_id'      => (string) $order_id,
                'order_ref'     => (string) $order_ref,
                'customer_name' => (string) $customer_name,
                'service'       => (string) $service_label,
                'preview'       => (string) $body,
            ),
            false
        );
    }

    private static function is_system_session_preview($preview) {
        $preview = trim((string) $preview);
        if ($preview === '') {
            return true;
        }
        $needles = array(
            'Chat-Session gestartet',
            'Session started',
            'Chat session started',
            'تم بدء',
        );
        foreach ($needles as $needle) {
            if (stripos($preview, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function on_session_sync($session_id, $meta) {
        if (!is_array($meta)) {
            return;
        }

        $preview = isset($meta['preview']) ? (string) $meta['preview'] : '';
        if (self::is_system_session_preview($preview)) {
            return;
        }

        $is_new = !empty($meta['is_new']);
        if ($is_new) {
            // New sessions are announced via paxdesign_new_chat_session only.
            return;
        }

        $service = isset($meta['service']) ? (string) $meta['service'] : '';
        $handler = isset($meta['handler']) ? (string) $meta['handler'] : 'ai';
        $seq     = isset($meta['seq']) ? (int) $meta['seq'] : 0;

        $body = ($service !== '' ? $service . ' — ' : '') . ($preview !== '' ? $preview : 'Chat aktualisiert');

        $last_role = isset($meta['last_role']) ? (string) $meta['last_role'] : '';
        // Customer messages in the human queue also trigger notify_new_customer_message — skip duplicate alert.
        if ($last_role === 'user' && $handler === 'admin' && $preview !== '') {
            return;
        }
        $needs_ai_attention = false;
        $type = 'session_sync';
        $event = 'assigned_chat_updated';
        if ($handler === 'live_request') {
            $type = 'live_request';
            $event = 'customer_waiting';
        }
        if (preg_match('/lead|kontakt|contact/i', $preview . ' ' . $service)) {
            $event = 'new_lead_contact';
        }
        $visible_events = array(
            'customer_waiting',
            'new_lead_contact',
            'missed_chat',
            'new_customer_message',
            'team_message',
            'team_request',
            'team_request_update',
            'link_scan_attention',
        );
        $silent = !in_array($event, $visible_events, true);
        if ($last_role === 'admin' && $event !== 'customer_waiting') {
            $silent = true;
        }

        $exclude = array();
        if ($last_role === 'admin') {
            $actor = get_current_user_id();
            if ($actor > 0) {
                $exclude[] = $actor;
            }
        }

        self::send_to_admins(
            $event === 'customer_waiting'
                ? 'Kunde wartet'
                : ($event === 'new_lead_contact' ? 'Neuer Lead/Kontakt' : 'Zugewiesener Chat aktualisiert'),
            $body,
            array(
                'type'       => $type,
                'event'      => $event,
                'session_id' => (string) $session_id,
                'service'    => $service,
                'preview'    => $preview,
                'seq'        => $seq,
                'handler'    => $handler,
            ),
            $silent,
            $exclude
        );
    }

    public static function notify_new_customer_message($session_id, $content, $exclude_user_id = 0) {
        $session_id = (string) $session_id;
        if (class_exists('PAXdesign_Chat_Live')) {
            $live = PAXdesign_Chat_Live::get_instance();
            $handler = $live->get_handler($session_id);
            if ($handler === PAXdesign_Chat_Live::HANDLER_AI) {
                return;
            }
        }
        $customer_name = self::session_customer_name($session_id);
        $title = $customer_name !== '' ? $customer_name : 'Neue Kundennachricht';
        $preview = wp_html_excerpt((string) $content, 120, '…');

        self::send_to_admins(
            $title,
            $preview,
            array(
                'type'          => 'message',
                'event'         => 'new_customer_message',
                'session_id'    => $session_id,
                'preview'       => wp_html_excerpt((string) $content, 160, '…'),
                'customer_name' => $customer_name,
            ),
            false,
            array(absint($exclude_user_id))
        );
    }

    /**
     * Alert staff when automated link scanning flags a risky URL.
     *
     * @param array<string, mixed> $message
     */
    public static function notify_link_scan_attention($session_id, $message, $status) {
        $session_id = (string) $session_id;
        $customer_name = self::session_customer_name($session_id);
        $preview = isset($message['content']) ? wp_html_excerpt((string) $message['content'], 120, '…') : '';
        $title = $status === (class_exists('PAXdesign_Link_Scan_Service') ? PAXdesign_Link_Scan_Service::STATUS_DANGEROUS : 'dangerous')
            ? 'Unsicherer Link erkannt'
            : 'Verdächtiger Link erkannt';

        self::send_to_admins(
            $title,
            ($customer_name !== '' ? $customer_name . ' — ' : '') . $preview,
            array(
                'type'          => 'ai_attention',
                'event'         => 'link_scan_attention',
                'session_id'    => $session_id,
                'preview'       => $preview,
                'customer_name' => $customer_name,
                'link_status'   => (string) $status,
            ),
            false
        );
    }

    private static function link_status_dangerous() {
        return class_exists('PAXdesign_Link_Scan_Service')
            ? PAXdesign_Link_Scan_Service::STATUS_DANGEROUS
            : 'dangerous';
    }

    private static function session_customer_name($session_id) {
        if (!class_exists('PAXdesign_Chat_Log')) {
            return '';
        }
        global $wpdb;
        PAXdesign_Chat_Log::create_table();
        $table = PAXdesign_Chat_Log::table_name();
        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_name FROM $table WHERE session_id = %s LIMIT 1",
            (string) $session_id
        ));
        return sanitize_text_field((string) $name);
    }

    /**
     * Push a team DM to a specific employee.
     */
    public static function notify_team_message($recipient_user_id, $sender_name, $content, $conv_id) {
        $recipient_user_id = absint($recipient_user_id);
        if ($recipient_user_id <= 0) {
            return;
        }

        self::send_to_user(
            $recipient_user_id,
            sanitize_text_field((string) $sender_name),
            wp_html_excerpt((string) $content, 120, '…'),
            array(
                'type'       => 'team_message',
                'event'      => 'team_message',
                'session_id' => (string) $conv_id,
                'preview'    => wp_html_excerpt((string) $content, 160, '…'),
            ),
            false
        );
    }

    /**
     * Push a pending team conversation request to the approver.
     */
    public static function notify_team_request($recipient_user_id, $requester_name, $note, $conv_id) {
        $recipient_user_id = absint($recipient_user_id);
        if ($recipient_user_id <= 0) {
            return;
        }

        $body = ($requester_name !== '' ? $requester_name . ' ' : '') . 'requested a conversation';
        if ($note !== '') {
            $body .= ': ' . wp_html_excerpt((string) $note, 80, '…');
        }

        self::send_to_user(
            $recipient_user_id,
            'Team conversation request',
            $body,
            array(
                'type'       => 'team_request',
                'event'      => 'team_request',
                'session_id' => (string) $conv_id,
                'preview'    => wp_html_excerpt((string) $note, 160, '…'),
            ),
            false
        );
    }

    /**
     * Notify requester that their conversation request was accepted or declined.
     */
    public static function notify_team_request_response($recipient_user_id, $responder_name, $status, $conv_id) {
        $recipient_user_id = absint($recipient_user_id);
        if ($recipient_user_id <= 0) {
            return;
        }

        $accepted = $status === 'accepted';
        self::send_to_user(
            $recipient_user_id,
            $accepted ? 'Conversation accepted' : 'Conversation declined',
            sanitize_text_field((string) $responder_name) . ($accepted ? ' accepted your request.' : ' declined your request.'),
            array(
                'type'       => 'team_request_update',
                'event'      => 'team_request_update',
                'session_id' => (string) $conv_id,
                'status'     => (string) $status,
            ),
            false
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function send_to_user($user_id, $title, $body, $data = array(), $silent = false) {
        if (!self::is_configured()) {
            return new WP_Error('apns_not_configured', 'APNs is not configured.');
        }

        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('apns_invalid_user', 'Invalid user id.');
        }

        $sent = 0;
        $last_error = null;
        $last_status = 0;
        $last_token_prefix = '';

        foreach (self::get_user_devices($user_id) as $device) {
            if (!empty($device['revoked'])) {
                continue;
            }
            if (isset($device['approved']) && empty($device['approved'])) {
                continue;
            }
            if (!empty($device['session_only']) || empty($device['token'])) {
                continue;
            }
            $token = isset($device['token']) ? (string) $device['token'] : '';
            $result = self::send($device, $title, $body, $data, $user_id, $silent);
            if (is_wp_error($result)) {
                $last_error = $result;
                $error_data = $result->get_error_data();
                if (is_array($error_data) && isset($error_data['status'])) {
                    $last_status = (int) $error_data['status'];
                }
                if ($result->get_error_code() === 'apns_invalid_token') {
                    self::unregister_device($user_id, $token);
                }
                continue;
            }

            $sent++;
            $last_status = 200;
            $last_token_prefix = $token !== '' ? substr($token, 0, 12) : '';
        }

        if ($sent <= 0) {
            if ($last_error instanceof WP_Error) {
                return $last_error;
            }
            return new WP_Error('apns_no_devices', 'No active device tokens registered yet.');
        }

        return array(
            'sent'             => true,
            'sent_count'       => $sent,
            'apns_http_status' => $last_status,
            'token_prefix'     => $last_token_prefix,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function resolve_badge_count($user_id, $data) {
        if ($user_id > 0 && !empty($data['notification_id']) && class_exists('PAXdesign_Customer_Notifications')) {
            return PAXdesign_Customer_Notifications::unread_count($user_id);
        }
        if ($user_id > 0) {
            return self::count_user_badge($user_id);
        }
        return self::count_pending_badge();
    }

    public static function count_user_badge($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $total = 0;

        if (class_exists('PAXdesign_Chat_Live')) {
            $live = PAXdesign_Chat_Live::get_instance();
            $list = $live->get_live_list_data();
            if (!is_wp_error($list) && !empty($list['sessions']) && is_array($list['sessions'])) {
                foreach ($list['sessions'] as $session) {
                    if (!is_array($session)) {
                        continue;
                    }
                    $sid = isset($session['session_id']) ? (string) $session['session_id'] : '';
                    if ($sid === '' || strpos($sid, 'team_') === 0) {
                        continue;
                    }
                    $handler = isset($session['handler']) ? (string) $session['handler'] : '';
                    if ($handler === 'closed') {
                        continue;
                    }
                    $last_role = isset($session['last_role']) ? (string) $session['last_role'] : '';
                    if ($last_role !== 'user') {
                        continue;
                    }
                    if (class_exists('PAXdesign_Message_Store')) {
                        $total += PAXdesign_Message_Store::count_unread_customer_messages($sid);
                    } else {
                        $total += 1;
                    }
                }
            }
        }

        if (class_exists('PAXdesign_Team_Messaging')) {
            $total += PAXdesign_Team_Messaging::count_unread_messages_for_user($user_id);
        }

        return max(0, $total);
    }

    public static function count_pending_badge() {
        if (!class_exists('PAXdesign_Chat_Log')) {
            return 0;
        }

        global $wpdb;
        PAXdesign_Chat_Log::create_table();
        $table = PAXdesign_Chat_Log::table_name();
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE handler = %s",
                'live_request'
            )
        );
        return max(0, $count);
    }

    public static function on_missed_chat($session_id, $service, $preview, $customer = '') {
        $body = ($customer !== '' ? $customer . ' — ' : '') . ($preview !== '' ? $preview : 'Live-Anfrage wurde nicht beantwortet');
        if ($service !== '') {
            $body .= ' · ' . $service;
        }
        self::send_to_admins(
            'Verpasster Chat',
            $body,
            array(
                'type'          => 'session_sync',
                'event'         => 'missed_chat',
                'session_id'    => (string) $session_id,
                'customer_name' => (string) $customer,
                'service'       => (string) $service,
                'preview'       => (string) $preview,
            ),
            false
        );
    }

    /**
     * @param int[] $user_ids
     * @param array<string, mixed> $data
     */
    public static function send_to_user_ids($user_ids, $title, $body, $data = array(), $silent = false, $exclude_user_ids = array()) {
        if (!self::is_configured()) {
            return;
        }

        $exclude = array_map('absint', (array) $exclude_user_ids);
        foreach ($user_ids as $user_id) {
            $uid = absint($user_id);
            if ($uid <= 0 || in_array($uid, $exclude, true)) {
                continue;
            }
            self::send_to_user($uid, $title, $body, $data, $silent);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param int[] $exclude_user_ids
     */
    public static function send_to_admins($title, $body, $data = array(), $silent = false, $exclude_user_ids = array()) {
        $user_ids = array();
        foreach (get_users(array('fields' => array('ID'))) as $user) {
            $uid = (int) $user->ID;
            if ($uid > 0 && PAXdesign_Live_Chat_Permissions::has_live_chat_access($uid)) {
                $user_ids[] = $uid;
            }
        }
        self::send_to_user_ids($user_ids, $title, $body, $data, $silent, $exclude_user_ids);
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
        $bundle  = $cfg['bundle_id'];
        $sandbox = !empty($device['sandbox']);
        $host    = $sandbox ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';
        $jwt     = self::make_jwt($cfg);
        if ($jwt === '') {
            return new WP_Error('apns_jwt', 'JWT generation failed.');
        }

        $type     = isset($data['type']) ? (string) $data['type'] : 'message';
        $event    = isset($data['event']) ? (string) $data['event'] : $type;
        $handler  = isset($data['handler']) ? (string) $data['handler'] : '';
        $sound    = self::sound_for_type($type, $handler, $event);
        $category = ($type === 'live_request') ? 'PAX_LIVE_REQUEST' : 'PAX_MESSAGE';
        if (!empty($data['notification_id']) || !empty($data['category'])) {
            $category = ($type === 'security_alert') ? 'PAX_CUSTOMER_ALERT' : 'PAX_CUSTOMER_MESSAGE';
        }

        $aps = array(
            'badge' => self::resolve_badge_count($user_id, $data),
        );

        if ($silent) {
            $aps['content-available'] = 1;
        } else {
            $aps['alert'] = array(
                'title' => (string) $title,
                'body'  => (string) $body,
            );
            $aps['sound'] = $sound;
            // Keep app state in sync even when iOS delivers only the visible alert.
            $aps['content-available'] = 1;
            $aps['category'] = $category;
        }

        $payload = array(
            'aps' => $aps,
            'pax' => $data,
        );

        $url = $host . '/3/device/' . $device['token'];
        $push_type = $silent ? 'background' : 'alert';
        $priority  = $silent ? '5' : '10';
        $expiration = $silent ? (time() + 300) : (time() + 3600);
        $session_id = isset($data['session_id']) ? (string) $data['session_id'] : '';
        $collapse_id = $session_id !== '' ? substr($type . '-' . $session_id, 0, 64) : '';

        $headers = array(
            'authorization'  => 'bearer ' . $jwt,
            'apns-topic'     => $bundle,
            'apns-push-type' => $push_type,
            'apns-priority'  => $priority,
            'apns-expiration'=> (string) $expiration,
            'content-type'   => 'application/json',
        );
        if ($collapse_id !== '') {
            $headers['apns-collapse-id'] = $collapse_id;
        }

        $response = self::apns_post($url, $headers, wp_json_encode($payload));
        if (is_wp_error($response)) {
            self::log_delivery($user_id, $device, $silent, $push_type, 0, $response->get_error_message());
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[PAXdesign APNs] transport error: ' . $response->get_error_message());
            }
            return $response;
        }

        $code = (int) ($response['status'] ?? 0);
        $body_resp = (string) ($response['body'] ?? '');
        if ($code === 410 || $code === 404) {
            self::log_delivery($user_id, $device, $silent, $push_type, $code, $body_resp);
            return new WP_Error('apns_invalid_token', self::format_apns_error($body_resp, 'Invalid device token.'), array('status' => $code, 'body' => $body_resp));
        }

        if ($code < 200 || $code >= 300) {
            $reason = self::format_apns_error($body_resp, '');
            $error_data = array(
                'status'           => $code,
                'body'             => $body_resp,
                'primary_env'      => $sandbox ? 'sandbox' : 'production',
                'primary_reason'   => $reason !== '' ? $reason : 'APNs HTTP ' . $code,
            );
            if ($reason === 'BadDeviceToken') {
                $alt_host = $sandbox ? 'https://api.push.apple.com' : 'https://api.sandbox.push.apple.com';
                $alt_env  = $sandbox ? 'production' : 'sandbox';
                $alt_url  = $alt_host . '/3/device/' . $device['token'];
                $alt_resp = self::apns_post($alt_url, $headers, wp_json_encode($payload));
                if (!is_wp_error($alt_resp)) {
                    $alt_code = (int) ($alt_resp['status'] ?? 0);
                    $alt_body = (string) ($alt_resp['body'] ?? '');
                    $alt_reason = self::format_apns_error($alt_body, '');
                    $error_data['alternate_env'] = $alt_env;
                    $error_data['alternate_status'] = $alt_code;
                    $error_data['alternate_body'] = $alt_body;
                    $error_data['alternate_reason'] = $alt_reason !== '' ? $alt_reason : 'APNs HTTP ' . $alt_code;
                    if ($alt_code >= 200 && $alt_code < 300) {
                        if ($user_id > 0) {
                            self::set_device_sandbox_flag($user_id, (string) $device['token'], !$sandbox);
                        }
                        self::log_delivery($user_id, $device, $silent, $push_type, $alt_code, $alt_body, $alt_env);
                        return true;
                    }
                    $body_resp = $alt_body;
                    $code      = $alt_code;
                    $reason    = $alt_reason;
                }
            }

            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[PAXdesign APNs] HTTP ' . $code . ' user=' . (int) $user_id);
            }
            self::log_delivery($user_id, $device, $silent, $push_type, $code, $body_resp, $sandbox ? 'sandbox' : 'production', $reason);
            return new WP_Error(
                'apns_failed',
                $reason !== '' ? $reason : self::format_apns_error($body_resp, 'APNs HTTP ' . $code),
                $error_data
            );
        }

        self::log_delivery($user_id, $device, $silent, $push_type, $code, $body_resp, $sandbox ? 'sandbox' : 'production');
        return true;
    }

    /**
     * Send a diagnostic alert push to the current user's active device.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function send_diagnostic_test_to_user($user_id, $device_id = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('apns_invalid_user', 'Invalid user id.');
        }

        $title = 'PAXDesign Push Diagnostic';
        $body  = 'Diagnostic alert push from WordPress at ' . gmdate('H:i:s') . ' UTC';
        $data  = array(
            'type'       => 'message',
            'event'      => 'diagnostic_test',
            'session_id' => 'diag_' . time(),
            'preview'    => 'Push diagnostic verification',
        );

        $target = null;
        foreach (self::get_user_devices($user_id) as $device) {
            if (!is_array($device) || !empty($device['revoked']) || !empty($device['session_only'])) {
                continue;
            }
            if (empty($device['token'])) {
                continue;
            }
            if (isset($device['approved']) && empty($device['approved'])) {
                continue;
            }
            if ($device_id !== '') {
                $id = isset($device['device_id']) ? (string) $device['device_id'] : '';
                if ($id !== $device_id) {
                    continue;
                }
            }
            $target = $device;
            break;
        }

        if ($target === null) {
            return new WP_Error('apns_no_devices', 'No active push-enabled device registered for this account.');
        }

        $token = (string) $target['token'];
        $result = self::send($target, $title, $body, $data, $user_id, false);
        $env = !empty($target['sandbox']) ? 'sandbox' : 'production';

        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $status = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 0;
            $apple_body = is_array($error_data) && isset($error_data['body']) ? (string) $error_data['body'] : '';
            if ($result->get_error_code() === 'apns_invalid_token') {
                self::unregister_device($user_id, $token);
            }
            return array(
                'sent'              => false,
                'push_type'         => 'alert',
                'apns_http_status'  => $status,
                'token_prefix'      => substr($token, 0, 12),
                'environment'       => $env,
                'apple_response'    => $apple_body,
                'failure_reason'    => $result->get_error_message(),
            );
        }

        return array(
            'sent'              => true,
            'push_type'         => 'alert',
            'apns_http_status'  => 200,
            'token_prefix'      => substr($token, 0, 12),
            'environment'       => $env,
            'apple_response'    => '',
            'failure_reason'    => '',
        );
    }

    /**
     * @param array<string, mixed> $device
     */
    private static function log_delivery($user_id, $device, $silent, $push_type, $status, $body, $environment = '', $reason = '') {
        $token = isset($device['token']) ? (string) $device['token'] : '';
        $entry = array(
            'at'           => time(),
            'user_id'      => (int) $user_id,
            'token_prefix' => $token !== '' ? substr($token, 0, 12) : '',
            'device_id'    => isset($device['device_id']) ? (string) $device['device_id'] : '',
            'silent'       => (bool) $silent,
            'push_type'    => (string) $push_type,
            'status'       => (int) $status,
            'environment'  => $environment !== '' ? $environment : (!empty($device['sandbox']) ? 'sandbox' : 'production'),
            'reason'       => $reason !== '' ? $reason : self::format_apns_error((string) $body, ''),
            'body'         => (string) $body,
        );

        $log = get_option('paxdesign_apns_delivery_log', array());
        if (!is_array($log)) {
            $log = array();
        }
        array_unshift($log, $entry);
        $log = array_slice($log, 0, 100);
        update_option('paxdesign_apns_delivery_log', $log, false);
    }

    /**
     * @param int $user_id
     * @param string $token
     * @param bool $sandbox
     */
    private static function set_device_sandbox_flag($user_id, $token, $sandbox) {
        $token = preg_replace('/[^a-fA-F0-9]/', '', (string) $token);
        if ($token === '') {
            return;
        }

        $all = self::get_user_devices($user_id);
        if (!isset($all[$token]) || !is_array($all[$token])) {
            return;
        }

        $all[$token]['sandbox'] = (bool) $sandbox;
        $all[$token]['updated_at'] = time();
        update_user_meta((int) $user_id, self::USER_META_KEY, $all);
    }

    /**
     * Apple Push Notification service requires HTTP/2.
     *
     * @param string $url
     * @param array<string, string> $headers
     * @param string $body
     * @return array{status:int,body:string}|WP_Error
     */
    private static function apns_post($url, $headers, $body) {
        if (!function_exists('curl_init')) {
            return new WP_Error('apns_curl_missing', 'cURL is required for Apple Push Notifications.');
        }

        if (!defined('CURL_HTTP_VERSION_2_0')) {
            return new WP_Error(
                'apns_http2_unavailable',
                'HTTP/2 cURL support is required for Apple Push Notifications on this server.'
            );
        }

        $attempt = 0;
        $max_attempts = 3;
        $last_response = null;

        while ($attempt < $max_attempts) {
            $attempt++;
            $last_response = self::apns_post_once($url, $headers, $body);
            if (is_wp_error($last_response)) {
                return $last_response;
            }
            $status = (int) ($last_response['status'] ?? 0);
            if ($status !== 429 || $attempt >= $max_attempts) {
                return $last_response;
            }
            usleep(250000 * $attempt);
        }

        return $last_response;
    }

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @param string $body
     * @return array{status:int,body:string}|WP_Error
     */
    private static function apns_post_once($url, $headers, $body) {
        if (!function_exists('curl_init')) {
            return new WP_Error('apns_curl_missing', 'cURL is required for Apple Push Notifications.');
        }

        if (!defined('CURL_HTTP_VERSION_2_0')) {
            return new WP_Error(
                'apns_http2_unavailable',
                'HTTP/2 cURL support is required for Apple Push Notifications on this server.'
            );
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return new WP_Error('apns_curl_init', 'Could not initialize cURL for APNs.');
        }

        $header_lines = array();
        foreach ($headers as $name => $value) {
            $header_lines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $header_lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
        ));

        $raw = curl_exec($ch);
        if ($raw === false) {
            $message = curl_error($ch);
            curl_close($ch);
            return new WP_Error('apns_transport', $message !== '' ? $message : 'APNs transport failed.');
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return array(
            'status' => $status,
            'body'   => substr($raw, $header_size),
        );
    }

    /**
     * @param string $body
     * @param string $fallback
     * @return string
     */
    private static function format_apns_error($body, $fallback) {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $fallback;
        }

        $reason = isset($decoded['reason']) ? trim((string) $decoded['reason']) : '';
        if ($reason === '') {
            return $fallback;
        }

        return $reason;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function send_test_to_registered_devices($title, $body, $data = array()) {
        $attempts = array();
        $sent = 0;

        foreach (get_users(array('fields' => array('ID'))) as $user) {
            $uid = (int) $user->ID;
            if ($uid <= 0 || !PAXdesign_Live_Chat_Permissions::has_live_chat_access($uid)) {
                continue;
            }

            foreach (self::get_user_devices($uid) as $device) {
                if (empty($device['token']) || !empty($device['revoked'])) {
                    continue;
                }
                if (isset($device['approved']) && empty($device['approved'])) {
                    continue;
                }

                $token = (string) $device['token'];
                $result = self::send($device, $title, $body, $data, $uid, false);
                $entry = array(
                    'user_id'      => $uid,
                    'token_prefix' => substr($token, 0, 12),
                    'sandbox'      => !empty($device['sandbox']),
                    'device_name'  => isset($device['device_name']) ? (string) $device['device_name'] : '',
                );

                if (is_wp_error($result)) {
                    $error_data = $result->get_error_data();
                    $entry['sent'] = false;
                    $entry['error'] = $result->get_error_message();
                    $entry['apns_http_status'] = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 0;
                    if ($result->get_error_code() === 'apns_invalid_token') {
                        self::unregister_device($uid, $token);
                    }
                    if (is_array($error_data) && !empty($error_data['body'])) {
                        $entry['apple_response'] = (string) $error_data['body'];
                    }
                    if (is_array($error_data)) {
                        foreach (array('primary_env', 'primary_reason', 'alternate_env', 'alternate_status', 'alternate_body', 'alternate_reason') as $field) {
                            if (isset($error_data[$field]) && $error_data[$field] !== '') {
                                $entry[$field] = $error_data[$field];
                            }
                        }
                    }
                } else {
                    $entry['sent'] = true;
                    $entry['apns_http_status'] = 200;
                    $sent++;
                }

                $attempts[] = $entry;
            }
        }

        return array(
            'sent'       => $sent > 0,
            'sent_count' => $sent,
            'attempts'   => $attempts,
        );
    }

    /**
     * @param array<string, string> $cfg
     */
    private static function make_jwt($cfg) {
        $header  = self::base64url(json_encode(array(
            'alg' => 'ES256',
            'kid' => $cfg['key_id'],
            'typ' => 'JWT',
        ), JSON_UNESCAPED_SLASHES));
        $claims  = self::base64url(json_encode(array(
            'iss' => $cfg['team_id'],
            'iat' => time(),
            'exp' => time() + 3600,
        ), JSON_UNESCAPED_SLASHES));
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
