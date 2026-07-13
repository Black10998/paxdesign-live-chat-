<?php
/**
 * GDPR-conscious KI chat session logging for WordPress admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Chat_Log {

    const TABLE_SUFFIX = 'paxdesign_chat_logs';
    const RETENTION_DAYS = 90;
    const ALLOWED_ROLES = array('user', 'assistant', 'admin', 'system');

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_paxdesign_chat_log', array($this, 'handle_sync'));
        add_action('wp_ajax_nopriv_paxdesign_chat_log', array($this, 'handle_sync'));
        add_action('admin_post_paxdesign_chat_export', array($this, 'handle_export'));
        add_action('wp_ajax_paxdesign_chat_delete_logs', array($this, 'handle_delete'));
        add_action('wp_ajax_paxdesign_chat_log_detail', array($this, 'handle_log_detail'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function create_table() {
        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            started_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            messages longtext NOT NULL,
            detected_service varchar(120) DEFAULT '',
            booking_triggered tinyint(1) NOT NULL DEFAULT 0,
            consultation_started tinyint(1) NOT NULL DEFAULT 0,
            message_count int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY updated_at (updated_at),
            KEY detected_service (detected_service)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function sanitize_messages($messages) {
        $sanitized = array();
        foreach ($messages as $msg) {
            if (!is_array($msg) || empty($msg['role']) || !isset($msg['content'])) {
                continue;
            }
            $role = sanitize_text_field($msg['role']);
            if (!in_array($role, self::ALLOWED_ROLES, true)) {
                continue;
            }
            $content = sanitize_textarea_field($msg['content']);
            $has_image = !empty($msg['image_url']);
            if ($content === '' && !$has_image) {
                continue;
            }
            if (mb_strlen($content) > 4000) {
                $content = mb_substr($content, 0, 4000);
            }
            $entry = array(
                'role'    => $role,
                'content' => $content,
            );
            if (isset($msg['id'])) {
                $entry['id'] = (int) $msg['id'];
            }
            if (!empty($msg['client_msg_id'])) {
                $entry['client_msg_id'] = PAXdesign_Message_Store::normalize_client_id($msg['client_msg_id']);
            }
            if (isset($msg['ts'])) {
                $entry['ts'] = (int) $msg['ts'];
            }
            if (!empty($msg['reaction']) && in_array($msg['reaction'], array('like', 'dislike', 'pax-top', 'pax-thanks', 'pax-clear', 'pax-love'), true)) {
                $entry['reaction'] = sanitize_text_field($msg['reaction']);
            }
            if (!empty($msg['reply_to'])) {
                $entry['reply_to'] = (int) $msg['reply_to'];
            }
            if ($has_image) {
                $entry['image_url']       = esc_url_raw($msg['image_url']);
                $entry['attachment_type'] = 'image';
            }
            $sanitized[] = $entry;
        }
        return $sanitized;
    }

    /**
     * @param array<int, array<string, mixed>> $existing
     * @param array<int, array<string, mixed>> $incoming
     * @return array<int, array<string, mixed>>
     */
    private function merge_messages($existing, $incoming) {
        $by_id = array();

        foreach ($existing as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $id = isset($msg['id']) ? (int) $msg['id'] : 0;
            if ($id > 0) {
                $by_id[$id] = $msg;
            }
        }

        $next_id = empty($by_id) ? 1 : (max(array_keys($by_id)) + 1);

        foreach ($incoming as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $id = isset($msg['id']) ? (int) $msg['id'] : 0;
            if ($id > 0) {
                if (isset($by_id[$id]) && !empty($by_id[$id]['reaction']) && empty($msg['reaction'])) {
                    $msg['reaction'] = $by_id[$id]['reaction'];
                }
                if (isset($by_id[$id]) && !empty($by_id[$id]['reply_to']) && empty($msg['reply_to'])) {
                    $msg['reply_to'] = $by_id[$id]['reply_to'];
                }
                if (isset($by_id[$id]) && !empty($by_id[$id]['image_url']) && empty($msg['image_url'])) {
                    $msg['image_url'] = $by_id[$id]['image_url'];
                    if (!empty($by_id[$id]['attachment_type'])) {
                        $msg['attachment_type'] = $by_id[$id]['attachment_type'];
                    }
                }
                $by_id[$id] = $msg;
                continue;
            }
            $by_id[$next_id] = $msg;
            $msg['id'] = $next_id;
            $by_id[$next_id]['id'] = $next_id;
            $next_id++;
        }

        ksort($by_id, SORT_NUMERIC);
        $merged = array_values($by_id);

        foreach ($merged as $idx => $msg) {
            if (!isset($merged[$idx]['ts'])) {
                $merged[$idx]['ts'] = time();
            }
        }

        return $merged;
    }

    /**
     * Persist any client-identified messages to the durable store immediately.
     * Legacy JSON projection may lag; SSE and mobile polls read the store first.
     *
     * @param string $session_id
     * @param array<int, array<string, mixed>> $messages
     * @return int Number of newly committed durable rows
     */
    private function persist_durable_messages($session_id, $messages) {
        if (!class_exists('PAXdesign_Message_Store')) {
            return 0;
        }
        $written = 0;
        foreach ((array) $messages as $message) {
            if (!is_array($message) || empty($message['client_msg_id'])) {
                continue;
            }
            $extra = $message;
            $extra['client_msg_id'] = $message['client_msg_id'];
            $stored = PAXdesign_Message_Store::append(
                $session_id,
                $message['role'],
                $message['content'],
                $extra,
                'customer'
            );
            if (is_wp_error($stored)) {
                continue;
            }
            if (empty($stored['_deduplicated'])) {
                $written++;
            }
        }
        return $written;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function max_message_id($messages) {
        $max = 0;
        foreach ($messages as $msg) {
            if (isset($msg['id']) && (int) $msg['id'] > $max) {
                $max = (int) $msg['id'];
            }
        }
        return $max;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save_session($payload) {
        global $wpdb;

        $session_id = isset($payload['session_id']) ? sanitize_text_field($payload['session_id']) : '';
        if ($session_id === '' || !preg_match('/^pax_[a-z0-9_]+$/i', $session_id)) {
            return false;
        }

        $messages = isset($payload['messages']) ? $payload['messages'] : array();
        if (!is_array($messages)) {
            return false;
        }

        $sanitized = $this->sanitize_messages($messages);
        $consult   = !empty($payload['consultation_started']) ? 1 : 0;
        if (empty($sanitized) && $consult) {
            $table = self::table_name();
            $existing_row = $wpdb->get_row(
                $wpdb->prepare("SELECT consultation_started FROM $table WHERE session_id = %s LIMIT 1", $session_id)
            );
            $already_started = $existing_row && !empty($existing_row->consultation_started);
            if (!$already_started && class_exists('PAXdesign_Message_Store')) {
                $already_started = (bool) PAXdesign_Message_Store::get_by_client_id($session_id, 'sys:session_started');
            }
            if (!$already_started) {
                $sanitized = array(
                    array(
                        'role'          => 'system',
                        'content'       => 'Chat-Session gestartet.',
                        'client_msg_id' => 'sys:session_started',
                        'id'            => 1,
                        'ts'            => time(),
                    ),
                );
            }
        }
        if (empty($sanitized)) {
            if ($consult) {
                $table = self::table_name();
                $existing = $wpdb->get_row(
                    $wpdb->prepare("SELECT id, consultation_started FROM $table WHERE session_id = %s LIMIT 1", $session_id)
                );
                if ($existing) {
                    if (empty($existing->consultation_started)) {
                        $wpdb->update(
                            $table,
                            array(
                                'consultation_started' => 1,
                                'updated_at'           => current_time('mysql'),
                            ),
                            array('id' => (int) $existing->id),
                            array('%d', '%s'),
                            array('%d')
                        );
                    }
                    return (int) $existing->id;
                }
            }
            return false;
        }

        $table = self::table_name();
        if (class_exists('PAXdesign_Chat_Live')) {
            PAXdesign_Chat_Live::upgrade_schema();
        }
        $now   = current_time('mysql');
        $service = isset($payload['detected_service']) ? sanitize_text_field($payload['detected_service']) : '';
        $booking = !empty($payload['booking_triggered']) ? 1 : 0;

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE session_id = %s LIMIT 1", $session_id)
        );

        $all_idempotent = !empty($sanitized);
        foreach ($sanitized as $message) {
            if (empty($message['client_msg_id'])) {
                $all_idempotent = false;
                break;
            }
        }

        if (!$existing && $all_idempotent && class_exists('PAXdesign_Message_Store')) {
            $wpdb->insert(
                $table,
                array(
                    'session_id'            => $session_id,
                    'started_at'            => $now,
                    'updated_at'            => $now,
                    'messages'              => '[]',
                    'detected_service'      => $service,
                    'booking_triggered'     => $booking,
                    'consultation_started'  => $consult,
                    'message_count'         => 0,
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
            );
            $insert_id = (int) $wpdb->insert_id;
            if ($insert_id <= 0) {
                return false;
            }
            $preview = '';
            foreach ($sanitized as $message) {
                $stored = PAXdesign_Message_Store::append(
                    $session_id,
                    $message['role'],
                    $message['content'],
                    $message,
                    'customer'
                );
                if (is_wp_error($stored)) {
                    return false;
                }
                if ($preview === '' && $message['role'] === 'user') {
                    $preview = wp_html_excerpt($message['content'], 120, '…');
                }
            }
            do_action('paxdesign_new_chat_session', $session_id, $service, $preview);
            return $insert_id;
        }

        if ($existing) {
            $existing_messages = json_decode($existing->messages, true);
            if (!is_array($existing_messages)) {
                $existing_messages = array();
            }

            if ($all_idempotent && class_exists('PAXdesign_Message_Store')) {
                foreach ($sanitized as $message) {
                    $extra = $message;
                    $extra['client_msg_id'] = $message['client_msg_id'];
                    $stored = PAXdesign_Message_Store::append(
                        $session_id,
                        $message['role'],
                        $message['content'],
                        $extra,
                        'customer'
                    );
                    if (is_wp_error($stored)) {
                        return false;
                    }
                }
                $wpdb->update(
                    $table,
                    array(
                        'detected_service'     => $service !== '' ? $service : $existing->detected_service,
                        'booking_triggered'    => $booking ? 1 : (int) $existing->booking_triggered,
                        'consultation_started' => $consult ? 1 : (int) $existing->consultation_started,
                        'updated_at'           => $now,
                    ),
                    array('id' => (int) $existing->id),
                    array('%s', '%d', '%d', '%s'),
                    array('%d')
                );
                return (int) $existing->id;
            }

            $this->persist_durable_messages($session_id, $sanitized);

            $sanitized = $this->merge_messages($existing_messages, $sanitized);

            $json = wp_json_encode($sanitized);
            if ($json === false) {
                return false;
            }

            $wpdb->update(
                $table,
                array(
                    'updated_at'            => $now,
                    'messages'              => $json,
                    'detected_service'      => $service !== '' ? $service : $existing->detected_service,
                    'booking_triggered'     => $booking ? 1 : (int) $existing->booking_triggered,
                    'consultation_started'  => $consult ? 1 : (int) $existing->consultation_started,
                    'message_count'         => count($sanitized),
                    'message_seq'           => $this->max_message_id($sanitized),
                ),
                array('id' => (int) $existing->id),
                array('%s', '%s', '%s', '%d', '%d', '%d', '%d'),
                array('%d')
            );
            self::broadcast_session_sync($session_id, $sanitized, $existing, false);
            if (class_exists('PAXdesign_Message_Store')) {
                PAXdesign_Message_Store::migrate_legacy($session_id, $sanitized, 'customer');
            }
            return (int) $existing->id;
        }

        $this->persist_durable_messages($session_id, $sanitized);

        $sanitized = $this->merge_messages(array(), $sanitized);
        $json = wp_json_encode($sanitized);
        if ($json === false) {
            return false;
        }

        $wpdb->insert(
            $table,
            array(
                'session_id'            => $session_id,
                'started_at'            => $now,
                'updated_at'            => $now,
                'messages'              => $json,
                'detected_service'      => $service,
                'booking_triggered'     => $booking,
                'consultation_started'  => $consult,
                'message_count'         => count($sanitized),
                'message_seq'           => $this->max_message_id($sanitized),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
        );

        $insert_id = (int) $wpdb->insert_id;
        if ($insert_id > 0) {
            $preview = '';
            foreach ($sanitized as $msg) {
                if (is_array($msg) && isset($msg['role']) && $msg['role'] === 'user' && !empty($msg['content'])) {
                    $preview = wp_html_excerpt((string) $msg['content'], 120, '…');
                    break;
                }
            }
            if ($preview === '') {
                foreach ($sanitized as $msg) {
                    if (is_array($msg) && !empty($msg['content'])) {
                        $preview = wp_html_excerpt((string) $msg['content'], 120, '…');
                        break;
                    }
                }
            }
            do_action('paxdesign_new_chat_session', $session_id, $service, $preview);
            self::broadcast_session_sync($session_id, $sanitized, null, true);
            if (class_exists('PAXdesign_Message_Store')) {
                PAXdesign_Message_Store::migrate_legacy($session_id, $sanitized, 'customer');
            }
        }

        return $insert_id;
    }

    /**
     * Notify admin clients (web + iOS) that a session changed.
     *
     * @param object|null $previous
     * @param array<int, array<string, mixed>> $messages
     */
    private static function broadcast_session_sync($session_id, $messages, $previous, $is_new) {
        $seq = 0;
        $preview = '';
        $last_role = '';
        $last = null;

        if (!empty($messages)) {
            $last = end($messages);
            if (is_array($last)) {
                $seq = isset($last['id']) ? (int) $last['id'] : 0;
                $preview = !empty($last['content']) ? wp_html_excerpt((string) $last['content'], 120, '…') : '';
                $last_role = isset($last['role']) ? (string) $last['role'] : '';
            }
        }

        $prev_seq = 0;
        $prev_count = 0;
        if ($previous) {
            $prev_count = isset($previous->message_count) ? (int) $previous->message_count : 0;
            $prev_seq = isset($previous->message_seq) ? (int) $previous->message_seq : 0;
        }
        if (class_exists('PAXdesign_Message_Store')) {
            $store_seq = PAXdesign_Message_Store::latest_seq($session_id, 'customer');
            if ($store_seq > 0) {
                $seq = max($seq, $store_seq);
            }
        }

        if (!$is_new && $seq <= $prev_seq && count($messages) <= $prev_count) {
            return;
        }

        $handler = ($previous && isset($previous->handler)) ? (string) $previous->handler : 'ai';

        do_action('paxdesign_session_sync', $session_id, array(
            'is_new'    => (bool) $is_new,
            'seq'       => $seq,
            'preview'   => $preview,
            'last_role' => $last_role,
            'handler'   => $handler,
            'service'   => ($previous && isset($previous->detected_service)) ? (string) $previous->detected_service : '',
        ));

        if ($seq > $prev_seq && class_exists('PAXdesign_Chat_Event_Bus')) {
            $fallback = 0;
            if ($previous && isset($previous->admin_user_id)) {
                $fallback = (int) $previous->admin_user_id;
            }
            $live = class_exists('PAXdesign_Chat_Live') ? PAXdesign_Chat_Live::get_instance() : null;

            $new_messages = array();
            foreach ($messages as $msg) {
                if (!is_array($msg)) {
                    continue;
                }
                if (!empty($msg['client_msg_id'])) {
                    // Durable append already emitted inbox + session events.
                    continue;
                }
                $mid = isset($msg['id']) ? (int) $msg['id'] : 0;
                if ($mid > $prev_seq) {
                    $new_messages[] = $msg;
                }
            }
            usort($new_messages, function ($a, $b) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            });

            foreach ($new_messages as $msg) {
                $mid = isset($msg['id']) ? (int) $msg['id'] : 0;
                if ($mid <= 0) {
                    continue;
                }
                $role = isset($msg['role']) ? (string) $msg['role'] : '';
                $preview = !empty($msg['content']) ? wp_html_excerpt((string) $msg['content'], 120, '…') : '';
                $sse_message = $msg;
                if ($live) {
                    $sse_message = $live->format_sse_message_payload($msg, $fallback);
                }
                PAXdesign_Chat_Event_Bus::emit_session($session_id, 'message', array(
                    'seq'     => $mid,
                    'role'    => $role,
                    'handler' => $handler,
                    'preview' => $preview,
                    'message' => $sse_message,
                ));
            }
        }
    }

    public function handle_sync() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'paxdesign_chat_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        if (!PAXdesign_Chat::get_instance()->is_enabled()) {
            wp_send_json_error(array('message' => 'Chat disabled'), 503);
        }

        $messages_raw = isset($_POST['messages']) ? wp_unslash($_POST['messages']) : '';
        $messages     = json_decode($messages_raw, true);
        if (!is_array($messages)) {
            wp_send_json_error(array('message' => 'Invalid payload'), 400);
        }

        $id = $this->save_session(array(
            'session_id'           => isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : '',
            'messages'             => $messages,
            'detected_service'     => isset($_POST['detected_service']) ? wp_unslash($_POST['detected_service']) : '',
            'booking_triggered'    => !empty($_POST['booking_triggered']),
            'consultation_started' => !empty($_POST['consultation_started']),
        ));

        if (!$id) {
            wp_send_json_error(array('message' => 'Could not save'), 400);
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        if ($session_id !== '') {
            PAXdesign_Chat_Live::get_instance()->bind_device_from_request($session_id);
        }

        wp_send_json_success(array('id' => $id));
    }

    public function handle_log_detail() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'), 403);
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(array('message' => 'Ungültige ID.'), 400);
        }

        global $wpdb;
        $table = self::table_name();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT messages FROM $table WHERE id = %d LIMIT 1", $id));
        if (!$row) {
            wp_send_json_error(array('message' => 'Nicht gefunden.'), 404);
        }

        $messages = json_decode($row->messages, true);
        if (!is_array($messages)) {
            $messages = array();
        }

        $role_labels = array(
            'user'      => 'Kunde',
            'assistant' => 'KI',
            'admin'     => 'Support',
            'system'    => 'System',
        );

        $items = array();
        foreach ($messages as $msg) {
            if (!is_array($msg) || empty($msg['content'])) {
                continue;
            }
            $role = isset($msg['role']) ? sanitize_text_field($msg['role']) : 'assistant';
            $items[] = array(
                'role'  => $role,
                'label' => isset($role_labels[$role]) ? $role_labels[$role] : 'KI',
                'content' => sanitize_textarea_field($msg['content']),
            );
        }

        wp_send_json_success(array('messages' => $items));
    }

    public function handle_delete() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'), 403);
        }

        $delete_all = !empty($_POST['delete_all']);
        if ($delete_all) {
            $confirm = isset($_POST['confirm']) ? sanitize_text_field(wp_unslash($_POST['confirm'])) : '';
            if ($confirm !== 'ALLE LOESCHEN') {
                wp_send_json_error(array('message' => 'Bestätigung fehlgeschlagen. Bitte „ALLE LOESCHEN“ eingeben.'), 400);
            }
            $count = $this->delete_all_logs();
            wp_send_json_success(array(
                'message' => sprintf('%d Konversation(en) gelöscht.', $count),
                'deleted' => $count,
            ));
        }

        $ids = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : array();
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        $ids = array_filter(array_map('absint', $ids));
        if (empty($ids)) {
            wp_send_json_error(array('message' => 'Keine Einträge ausgewählt.'), 400);
        }

        $count = $this->delete_logs_by_ids($ids);
        wp_send_json_success(array(
            'message' => sprintf('%d Konversation(en) gelöscht.', $count),
            'deleted' => $count,
        ));
    }

    /**
     * @param array<int> $ids
     */
    public function delete_logs_by_ids($ids) {
        global $wpdb;
        $table = self::table_name();
        $ids   = array_filter(array_map('absint', $ids));
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE id IN ($placeholders)",
            $ids
        ));
    }

    public function delete_all_logs() {
        global $wpdb;
        $table = self::table_name();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $wpdb->query("TRUNCATE TABLE $table");
        return $count;
    }

    /**
     * @return array<int, object>
     */
    public function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'search'   => '',
            'service'  => '',
            'date_from'=> '',
            'date_to'  => '',
            'limit'    => 100,
            'offset'   => 0,
        );
        $args  = wp_parse_args($args, $defaults);
        $table = self::table_name();
        $where = array('1=1');
        $params = array();

        if ($args['service'] !== '') {
            $where[]  = 'detected_service = %s';
            $params[] = sanitize_text_field($args['service']);
        }

        if ($args['date_from'] !== '') {
            $where[]  = 'updated_at >= %s';
            $params[] = sanitize_text_field($args['date_from']) . ' 00:00:00';
        }

        if ($args['date_to'] !== '') {
            $where[]  = 'updated_at <= %s';
            $params[] = sanitize_text_field($args['date_to']) . ' 23:59:59';
        }

        if ($args['search'] !== '') {
            $like     = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[]  = '(session_id LIKE %s OR messages LIKE %s OR detected_service LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where)
            . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d';
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        }

        return $wpdb->get_results($sql);
    }

    public function count_logs($args = array()) {
        global $wpdb;

        $table = self::table_name();
        $where = array('1=1');
        $params = array();

        if (!empty($args['service'])) {
            $where[]  = 'detected_service = %s';
            $params[] = sanitize_text_field($args['service']);
        }
        if (!empty($args['date_from'])) {
            $where[]  = 'updated_at >= %s';
            $params[] = sanitize_text_field($args['date_from']) . ' 00:00:00';
        }
        if (!empty($args['date_to'])) {
            $where[]  = 'updated_at <= %s';
            $params[] = sanitize_text_field($args['date_to']) . ' 23:59:59';
        }
        if (!empty($args['search'])) {
            $like     = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[]  = '(session_id LIKE %s OR messages LIKE %s OR detected_service LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE " . implode(' AND ', $where);
        if (!empty($params)) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }
        return (int) $wpdb->get_var($sql);
    }

    public function get_distinct_services() {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_col("SELECT DISTINCT detected_service FROM $table WHERE detected_service != '' ORDER BY detected_service ASC");
    }

    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        check_admin_referer('paxdesign_chat_export');

        $format = isset($_GET['format']) ? sanitize_key($_GET['format']) : 'csv';
        $logs   = $this->get_logs(array(
            'search'    => isset($_GET['s']) ? wp_unslash($_GET['s']) : '',
            'service'   => isset($_GET['service']) ? wp_unslash($_GET['service']) : '',
            'date_from' => isset($_GET['date_from']) ? wp_unslash($_GET['date_from']) : '',
            'date_to'   => isset($_GET['date_to']) ? wp_unslash($_GET['date_to']) : '',
            'limit'     => 5000,
            'offset'    => 0,
        ));

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="paxdesign-chat-logs.json"');
            $out = array();
            foreach ($logs as $log) {
                $out[] = $this->format_log_row($log);
            }
            echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="paxdesign-chat-logs.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('ID', 'Session', 'Started', 'Updated', 'Service', 'Booking', 'Consultation', 'Messages'));
        foreach ($logs as $log) {
            $row = $this->format_log_row($log);
            fputcsv($out, array(
                $row['id'],
                $row['session_id'],
                $row['started_at'],
                $row['updated_at'],
                $row['detected_service'],
                $row['booking_triggered'] ? 'yes' : 'no',
                $row['consultation_started'] ? 'yes' : 'no',
                $row['transcript'],
            ));
        }
        fclose($out);
        exit;
    }

    private function role_label($role) {
        switch ($role) {
            case 'user':
                return 'Kunde';
            case 'admin':
                return 'Support';
            case 'system':
                return 'System';
            default:
                return 'KI';
        }
    }

    private function format_log_row($log) {
        $messages = json_decode($log->messages, true);
        $lines    = array();
        if (is_array($messages)) {
            foreach ($messages as $msg) {
                $role = isset($msg['role']) ? $msg['role'] : 'assistant';
                $lines[] = $this->role_label($role) . ': ' . $msg['content'];
            }
        }
        return array(
            'id'                   => (int) $log->id,
            'session_id'           => $log->session_id,
            'started_at'           => $log->started_at,
            'updated_at'           => $log->updated_at,
            'detected_service'     => $log->detected_service,
            'booking_triggered'    => (bool) $log->booking_triggered,
            'consultation_started' => (bool) $log->consultation_started,
            'message_count'        => (int) $log->message_count,
            'messages'             => is_array($messages) ? $messages : array(),
            'transcript'           => implode(' | ', $lines),
        );
    }

    public static function maybe_purge_old_logs() {
        global $wpdb;
        $table = self::table_name();
        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE updated_at < %s", $cutoff));
    }
}
