<?php
/**
 * Durable, ordered and idempotent message persistence.
 *
 * MySQL is the source of truth. JSON session blobs remain a compatibility
 * projection while older installations and admin screens are migrated.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Message_Store {

    const SCHEMA_VERSION = '2.1';

    /** @var array<string, string> */
    private static $deferred_projection_sessions = array();

    /** @var array<int, array<string, mixed>> */
    private static $deferred_asset_purges = array();

    private static $shutdown_deferred_registered = false;

    public static function init() {
        add_action('paxdesign_message_store_prune', array(__CLASS__, 'prune'));
        if (!wp_next_scheduled('paxdesign_message_store_prune')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'paxdesign_message_store_prune');
        }
    }

    public static function messages_table() {
        global $wpdb;
        return $wpdb->prefix . 'paxdesign_chat_messages';
    }

    public static function outbox_table() {
        global $wpdb;
        return $wpdb->prefix . 'paxdesign_chat_outbox';
    }

    public static function cursors_table() {
        global $wpdb;
        return $wpdb->prefix . 'paxdesign_chat_cursors';
    }

    public static function create_tables() {
        global $wpdb;

        $charset  = $wpdb->get_charset_collate();
        $messages = self::messages_table();
        $outbox   = self::outbox_table();
        $cursors  = self::cursors_table();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE $messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            channel varchar(16) NOT NULL DEFAULT 'customer',
            msg_seq bigint(20) unsigned NOT NULL,
            client_msg_id varchar(64) NOT NULL,
            role varchar(16) NOT NULL,
            content longtext NOT NULL,
            meta_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_seq (session_id, msg_seq),
            UNIQUE KEY session_client (session_id, client_msg_id),
            KEY session_since (session_id, msg_seq),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE $outbox (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            channel_key varchar(128) NOT NULL,
            event_type varchar(32) NOT NULL,
            payload_json longtext NOT NULL,
            message_seq bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY channel_event (channel_key, id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE $cursors (
            consumer_key varchar(80) NOT NULL,
            channel_key varchar(80) NOT NULL,
            last_event_id bigint(20) unsigned NOT NULL DEFAULT 0,
            last_msg_seq bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY (consumer_key, channel_key),
            KEY updated_at (updated_at)
        ) ENGINE=InnoDB $charset;");

        $created = true;
        foreach (array($messages, $outbox, $cursors) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                $created = false;
                break;
            }
        }
        if ($created) {
            update_option('paxdesign_message_store_schema', self::SCHEMA_VERSION, false);
        }
    }

    public static function maybe_upgrade() {
        if ((string) get_option('paxdesign_message_store_schema', '') !== self::SCHEMA_VERSION) {
            self::create_tables();
        }
    }

    public static function uuid() {
        return function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : sprintf(
                '%08x-%04x-%04x-%04x-%012x',
                mt_rand(),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand()
            );
    }

    public static function normalize_client_id($value) {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return self::uuid();
        }
        $value = preg_replace('/[^a-z0-9._:-]/', '', $value);
        $value = substr((string) $value, 0, 64);
        return $value !== '' ? $value : self::uuid();
    }

    /**
     * Stable client_msg_id for system notices so retries/reconnects never duplicate rows.
     */
    public static function system_notice_client_id($content) {
        $content = sanitize_textarea_field((string) $content);
        static $exact = null;
        if ($exact === null) {
            $exact = array(
                'Chat-Session gestartet.' => 'sys:session_started',
                'Dieser Chat wurde geschlossen. Sie können jederzeit ein neues Gespräch starten.' => 'sys:chat_closed',
                'Der Kunde hat das Gespräch beendet.' => 'sys:customer_closed',
                'Der KI-Assistent übernimmt den Chat wieder.' => 'sys:ai_reclaimed',
                'Ein PAXDesign-Mitarbeiter wurde informiert. Bitte bleiben Sie kurz im Chat.' => 'sys:live_agent_notified',
                'Danke. Ich leite Sie jetzt an einen PAXDesign-Mitarbeiter weiter.' => 'sys:live_transfer_thanks',
            );
        }
        if (isset($exact[$content])) {
            return $exact[$content];
        }
        if (preg_match('/^(.+) ist dem Chat beigetreten\.$/u', $content)) {
            return 'sys:admin_joined:' . substr(md5($content), 0, 12);
        }
        if (strpos($content, 'Der Chat wurde wieder geöffnet.') === 0) {
            return 'sys:chat_reopened:' . substr(md5($content), 0, 12);
        }
        return 'sys:content:' . substr(md5($content), 0, 24);
    }

    /**
     * Import legacy JSON exactly once. Existing message IDs become msg_seq.
     */
    public static function migrate_legacy($session_id, $messages, $channel = 'customer') {
        global $wpdb;

        $session_id = sanitize_text_field($session_id);
        if ($session_id === '' || !is_array($messages)) {
            return;
        }
        self::maybe_upgrade();
        $lock_name = 'pax_msg_' . md5($session_id);
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name));
        if ($locked !== 1) {
            return;
        }
        try {
            self::import_legacy_rows($session_id, $messages, $channel);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    private static function import_legacy_rows($session_id, $messages, $channel = 'customer') {
        global $wpdb;

        $table = self::messages_table();
        foreach ($messages as $index => $message) {
            if (!is_array($message)) {
                continue;
            }
            $seq = isset($message['id']) ? absint($message['id']) : ($index + 1);
            if ($seq <= 0) {
                continue;
            }
            $role = isset($message['role']) ? sanitize_key($message['role']) : 'system';
            if (!in_array($role, array('user', 'assistant', 'admin', 'system'), true)) {
                continue;
            }
            $content = isset($message['content']) ? (string) $message['content'] : '';
            $meta = $message;
            unset($meta['id'], $meta['role'], $meta['content']);
            $created = !empty($message['ts'])
                ? gmdate('Y-m-d H:i:s', absint($message['ts']))
                : current_time('mysql', true);
            if (!empty($message['client_msg_id'])) {
                $legacy_id = self::normalize_client_id($message['client_msg_id']);
            } elseif ($role === 'system') {
                $legacy_id = self::system_notice_client_id($content);
            } else {
                $legacy_id = 'legacy:' . $seq;
            }

            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table
                    (session_id, channel, msg_seq, client_msg_id, role, content, meta_json, created_at)
                 VALUES (%s, %s, %d, %s, %s, %s, %s, %s)",
                $session_id,
                sanitize_key($channel),
                $seq,
                $legacy_id,
                $role,
                $content,
                wp_json_encode($meta),
                $created
            ));
        }
    }

    /**
     * Append atomically. Repeating client_msg_id returns the original message.
     */
    public static function append($session_id, $role, $content, $extra = array(), $channel = 'customer') {
        global $wpdb;

        self::maybe_upgrade();
        $session_id = sanitize_text_field($session_id);
        $role       = sanitize_key($role);
        $channel    = sanitize_key($channel);
        $content    = sanitize_textarea_field($content);
        $has_image    = !empty($extra['image_url']);
        $has_audio    = !empty($extra['audio_url']);
        $has_location = isset($extra['location_lat'], $extra['location_lng'])
            && $extra['location_lat'] !== ''
            && $extra['location_lng'] !== '';
        if ($session_id === '' || !in_array($role, array('user', 'assistant', 'admin', 'system'), true)) {
            return new WP_Error('pax_message_invalid', 'Invalid message.', array('status' => 400));
        }
        if ($content === '' && !$has_image && !$has_audio && !$has_location) {
            return new WP_Error('pax_message_empty', 'Message cannot be empty.', array('status' => 400));
        }

        if ($role === 'system' && empty($extra['client_msg_id'])) {
            $extra['client_msg_id'] = self::system_notice_client_id($content);
        }
        $client_id = self::normalize_client_id(isset($extra['client_msg_id']) ? $extra['client_msg_id'] : '');
        $lock_name = 'pax_msg_' . md5($session_id);
        $owns_lock = empty($extra['lock_already_held']);
        $locked = $owns_lock
            ? (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name))
            : 1;
        if ($locked !== 1) {
            return new WP_Error('pax_message_busy', 'Conversation is busy. Retry with the same client_msg_id.', array('status' => 503));
        }

        try {
            self::migrate_customer_session_if_needed($session_id, $channel, true);
            if ($channel === 'team' && !empty($extra['legacy_messages']) && is_array($extra['legacy_messages'])) {
                self::import_legacy_rows($session_id, $extra['legacy_messages'], 'team');
            }
            $existing = self::get_by_client_id($session_id, $client_id);
            if ($existing) {
                $existing['_deduplicated'] = true;
                return $existing;
            }

            $table = self::messages_table();
            $wpdb->query('START TRANSACTION');
            $seq = 1 + (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(msg_seq), 0) FROM $table WHERE session_id = %s",
                $session_id
            ));

            $meta = self::sanitize_meta($extra);
            $created = current_time('mysql', true);
            $inserted = $wpdb->insert(
                $table,
                array(
                    'session_id'    => $session_id,
                    'channel'       => $channel,
                    'msg_seq'       => $seq,
                    'client_msg_id' => $client_id,
                    'role'          => $role,
                    'content'       => $content,
                    'meta_json'     => wp_json_encode($meta),
                    'created_at'    => $created,
                ),
                array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
            if (!$inserted) {
                $duplicate = self::get_by_client_id($session_id, $client_id);
                if ($duplicate) {
                    $wpdb->query('ROLLBACK');
                    return $duplicate;
                }
                $wpdb->query('ROLLBACK');
                return new WP_Error('pax_message_write_failed', 'Message could not be persisted.', array('status' => 500));
            }

            $message = self::format_row((object) array(
                'msg_seq'       => $seq,
                'client_msg_id' => $client_id,
                'role'          => $role,
                'content'       => $content,
                'meta_json'     => wp_json_encode($meta),
                'created_at'    => $created,
            ));

            self::update_customer_projection($session_id, $message, $channel);
            $payload = array(
                'session_id' => $session_id,
                'seq'        => $seq,
                'role'       => $role,
                'message'    => $message,
            );
            $outbox_ok = true;
            if ($channel === 'customer') {
                $outbox_ok = self::emit('session:' . $session_id, 'message', $payload, $seq) > 0
                    && self::emit('inbox:admins', 'message', $payload, $seq) > 0;
            } elseif (!empty($extra['participants']) && is_array($extra['participants'])) {
                $outbox_ok = self::emit('team:' . $session_id, 'message', $payload, $seq) > 0;
                foreach (array_map('absint', $extra['participants']) as $participant) {
                    if ($participant > 0) {
                        $outbox_ok = self::emit('inbox:user:' . $participant, 'message', $payload, $seq) > 0
                            && $outbox_ok;
                    }
                }
            }
            if (!$outbox_ok) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('pax_outbox_write_failed', 'Message delivery event could not be persisted.', array('status' => 500));
            }
            $wpdb->query('COMMIT');
            return $message;
        } finally {
            if ($owns_lock) {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            }
        }
    }

    public static function messages_since($session_id, $since = 0, $limit = 500, $channel = 'customer') {
        global $wpdb;
        self::maybe_upgrade();
        self::migrate_customer_session_if_needed($session_id, $channel);
        $table = self::messages_table();
        $limit = max(1, min(2000, absint($limit)));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT msg_seq, client_msg_id, role, content, meta_json, created_at
             FROM $table
             WHERE session_id = %s AND msg_seq > %d
             ORDER BY msg_seq ASC
             LIMIT %d",
            sanitize_text_field($session_id),
            absint($since),
            $limit
        ));
        return array_map(array(__CLASS__, 'format_row'), is_array($rows) ? $rows : array());
    }

    public static function all_messages($session_id, $channel = 'customer') {
        self::maybe_upgrade();
        self::migrate_customer_session_if_needed($session_id, $channel);
        return self::fetch_all_rows($session_id);
    }

    private static function fetch_all_rows($session_id) {
        global $wpdb;
        $table = self::messages_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT msg_seq, client_msg_id, role, content, meta_json, created_at
             FROM $table WHERE session_id = %s ORDER BY msg_seq ASC",
            sanitize_text_field($session_id)
        ));
        return array_map(array(__CLASS__, 'format_row'), is_array($rows) ? $rows : array());
    }

    public static function latest_seq($session_id, $channel = 'customer') {
        global $wpdb;
        self::migrate_customer_session_if_needed($session_id, $channel);
        $table = self::messages_table();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(msg_seq), 0) FROM $table WHERE session_id = %s",
            sanitize_text_field($session_id)
        ));
    }

    public static function count($session_id, $channel = 'customer') {
        global $wpdb;
        self::migrate_customer_session_if_needed($session_id, $channel);
        $table = self::messages_table();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE session_id = %s",
            sanitize_text_field($session_id)
        ));
    }

    public static function count_unread_customer_messages($session_id) {
        global $wpdb;
        self::maybe_upgrade();
        self::migrate_customer_session_if_needed($session_id, 'customer');
        $table = self::messages_table();
        $session_id = sanitize_text_field($session_id);
        $last_staff = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(msg_seq), 0) FROM $table WHERE session_id = %s AND channel = 'customer' AND role IN ('admin', 'assistant')",
            $session_id
        ));
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE session_id = %s AND channel = 'customer' AND role = 'user' AND msg_seq > %d",
            $session_id,
            $last_staff
        ));
    }

    public static function count_incoming_messages_since($session_id, $since_seq, $channel = 'team') {
        global $wpdb;
        self::maybe_upgrade();
        self::migrate_customer_session_if_needed($session_id, $channel);
        $table = self::messages_table();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE session_id = %s AND channel = %s AND role = 'user' AND msg_seq > %d",
            sanitize_text_field($session_id),
            sanitize_text_field($channel),
            absint($since_seq)
        ));
    }

    /**
     * Count messages created on a specific UTC day (YYYY-MM-DD).
     *
     * @param string $day
     * @param string $channel customer|team|*
     * @return int
     */
    public static function count_messages_on_day($day, $channel = 'customer') {
        global $wpdb;
        self::maybe_upgrade();
        $table = self::messages_table();
        $day   = sanitize_text_field($day);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return 0;
        }
        $start = $day . ' 00:00:00';
        $end   = $day . ' 23:59:59';
        if ($channel === '*') {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE created_at BETWEEN %s AND %s",
                $start,
                $end
            ));
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE channel = %s AND created_at BETWEEN %s AND %s",
            sanitize_text_field($channel),
            $start,
            $end
        ));
    }

    public static function emit($channel_key, $event_type, $payload, $message_seq = 0) {
        global $wpdb;
        self::maybe_upgrade();
        $inserted = $wpdb->insert(
            self::outbox_table(),
            array(
                'channel_key' => sanitize_text_field($channel_key),
                'event_type'  => sanitize_key($event_type),
                'payload_json'=> wp_json_encode(is_array($payload) ? $payload : array()),
                'message_seq' => absint($message_seq),
                'created_at'  => current_time('mysql', true),
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public static function events_since($channel_key, $since = 0, $limit = 500) {
        global $wpdb;
        self::maybe_upgrade();
        $table = self::outbox_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_type, payload_json, message_seq, created_at
             FROM $table
             WHERE channel_key = %s AND id > %d
             ORDER BY id ASC
             LIMIT %d",
            sanitize_text_field($channel_key),
            absint($since),
            max(1, min(1000, absint($limit)))
        ));
        $events = array();
        foreach (is_array($rows) ? $rows : array() as $row) {
            $payload = json_decode($row->payload_json, true);
            $events[] = array(
                'id'      => (int) $row->id,
                'type'    => (string) $row->event_type,
                'ts'      => strtotime($row->created_at . ' UTC'),
                'payload' => is_array($payload) ? $payload : array(),
            );
        }
        return $events;
    }

    public static function acknowledge($consumer_key, $channel_key, $event_id = 0, $msg_seq = 0) {
        global $wpdb;
        self::maybe_upgrade();
        $table = self::cursors_table();
        $consumer_key = substr(sanitize_text_field($consumer_key), 0, 80);
        $channel_key  = substr(sanitize_text_field($channel_key), 0, 80);
        if ($consumer_key === '' || $channel_key === '') {
            return new WP_Error('pax_ack_invalid', 'Invalid acknowledgement.', array('status' => 400));
        }
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table
                (consumer_key, channel_key, last_event_id, last_msg_seq, updated_at)
             VALUES (%s, %s, %d, %d, %s)
             ON DUPLICATE KEY UPDATE
                last_event_id = GREATEST(last_event_id, VALUES(last_event_id)),
                last_msg_seq = GREATEST(last_msg_seq, VALUES(last_msg_seq)),
                updated_at = VALUES(updated_at)",
            $consumer_key,
            $channel_key,
            absint($event_id),
            absint($msg_seq),
            current_time('mysql', true)
        ));
        if ($result === false) {
            return new WP_Error('pax_ack_failed', 'Acknowledgement could not be persisted.', array('status' => 500));
        }
        return array('ok' => true, 'event_id' => absint($event_id), 'seq' => absint($msg_seq));
    }

    public static function prune() {
        global $wpdb;
        $table = self::outbox_table();
        $cursors = self::cursors_table();
        $wpdb->query(
            "DELETE o FROM $table o
             INNER JOIN (
                SELECT channel_key, MIN(last_event_id) acknowledged
                FROM $cursors GROUP BY channel_key
             ) c ON c.channel_key = o.channel_key
             WHERE o.id <= c.acknowledged
               AND o.created_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)"
        );
        $wpdb->query("DELETE FROM $table WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)");
    }

    public static function delete_session($session_id) {
        global $wpdb;
        $session_id = sanitize_text_field($session_id);
        if ($session_id === '') {
            return false;
        }
        $results = array(
            $wpdb->delete(self::messages_table(), array('session_id' => $session_id), array('%s')),
            $wpdb->delete(self::outbox_table(), array('channel_key' => 'session:' . $session_id), array('%s')),
            $wpdb->delete(self::outbox_table(), array('channel_key' => 'team:' . $session_id), array('%s')),
            $wpdb->delete(self::cursors_table(), array('channel_key' => 'session:' . $session_id), array('%s')),
            $wpdb->delete(self::cursors_table(), array('channel_key' => 'team:' . $session_id), array('%s')),
        );
        $payload_pattern = '%"session_id":"' . str_replace(array('%', '_'), array('\\%', '\\_'), $session_id) . '"%';
        $results[] = $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::outbox_table() . ' WHERE payload_json LIKE %s',
            $payload_pattern
        ));
        foreach ($results as $result) {
            if ($result === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Move all messages from one session id to another (team deduplication).
     */
    public static function reassign_session($from_id, $to_id, $channel = 'team') {
        global $wpdb;
        self::maybe_upgrade();
        $from_id = sanitize_text_field((string) $from_id);
        $to_id   = sanitize_text_field((string) $to_id);
        $channel = sanitize_key((string) $channel);
        if ($from_id === '' || $to_id === '' || $from_id === $to_id) {
            return false;
        }

        $table = self::messages_table();
        $updated = $wpdb->update(
            $table,
            array('session_id' => $to_id),
            array(
                'session_id' => $from_id,
                'channel'    => $channel,
            ),
            array('%s'),
            array('%s', '%s')
        );
        if ($updated === false) {
            return false;
        }
        self::delete_session($from_id);
        return true;
    }

    private static function get_by_client_id($session_id, $client_id) {
        global $wpdb;
        $table = self::messages_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT msg_seq, client_msg_id, role, content, meta_json, created_at
             FROM $table WHERE session_id = %s AND client_msg_id = %s LIMIT 1",
            $session_id,
            $client_id
        ));
        return $row ? self::format_row($row) : null;
    }

    public static function find_by_client_id($session_id, $client_id) {
        self::maybe_upgrade();
        $client_id = self::normalize_client_id($client_id);
        return self::get_by_client_id(sanitize_text_field($session_id), $client_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_message($session_id, $msg_seq) {
        global $wpdb;
        self::maybe_upgrade();
        $session_id = sanitize_text_field((string) $session_id);
        $msg_seq      = absint($msg_seq);
        if ($session_id === '' || $msg_seq <= 0) {
            return null;
        }
        $table = self::messages_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT msg_seq, client_msg_id, role, content, meta_json, created_at
             FROM $table WHERE session_id = %s AND msg_seq = %d LIMIT 1",
            $session_id,
            $msg_seq
        ));
        return $row ? self::format_row($row) : null;
    }

    /**
     * @param array<string, mixed> $meta_updates
     * @return array<string, mixed>|WP_Error
     */
    public static function update_message_meta($session_id, $msg_seq, array $meta_updates, $channel = 'customer') {
        global $wpdb;
        self::maybe_upgrade();
        $session_id = sanitize_text_field((string) $session_id);
        $msg_seq      = absint($msg_seq);
        if ($session_id === '' || $msg_seq <= 0) {
            return new WP_Error('pax_message_invalid', 'Invalid message.', array('status' => 400));
        }

        $table = self::messages_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT msg_seq, client_msg_id, role, content, meta_json, created_at
             FROM $table WHERE session_id = %s AND msg_seq = %d LIMIT 1",
            $session_id,
            $msg_seq
        ));
        if (!$row) {
            return new WP_Error('pax_message_not_found', 'Message not found.', array('status' => 404));
        }

        $meta = json_decode((string) $row->meta_json, true);
        if (!is_array($meta)) {
            $meta = array();
        }
        $clean = self::sanitize_meta($meta_updates);
        foreach ($clean as $key => $value) {
            $meta[$key] = $value;
        }

        $updated = $wpdb->update(
            $table,
            array('meta_json' => wp_json_encode($meta)),
            array(
                'session_id' => $session_id,
                'msg_seq'    => $msg_seq,
            ),
            array('%s'),
            array('%s', '%d')
        );
        if ($updated === false) {
            return new WP_Error('pax_message_update_failed', 'Message could not be updated.', array('status' => 500));
        }

        $message = self::format_row((object) array(
            'msg_seq'       => $row->msg_seq,
            'client_msg_id' => $row->client_msg_id,
            'role'          => $row->role,
            'content'       => $row->content,
            'meta_json'     => wp_json_encode($meta),
            'created_at'    => $row->created_at,
        ));
        self::defer_customer_projection_rebuild($session_id, $channel);
        return $message;
    }

    private static function register_shutdown_deferred() {
        if (self::$shutdown_deferred_registered) {
            return;
        }
        self::$shutdown_deferred_registered = true;
        add_action('shutdown', array(__CLASS__, 'flush_deferred_work'), 9998);
    }

    public static function flush_deferred_work() {
        foreach (self::$deferred_asset_purges as $message) {
            self::purge_message_assets($message);
        }
        self::$deferred_asset_purges = array();

        foreach (self::$deferred_projection_sessions as $session_id => $channel) {
            self::rebuild_customer_projection($session_id, $channel);
        }
        self::$deferred_projection_sessions = array();
    }

    private static function defer_customer_projection_rebuild($session_id, $channel = 'customer') {
        if ($channel !== 'customer') {
            return;
        }
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return;
        }
        self::$deferred_projection_sessions[$session_id] = $channel;
        self::register_shutdown_deferred();
    }

    private static function defer_purge_message_assets($message) {
        if (!is_array($message)) {
            return;
        }
        self::$deferred_asset_purges[] = $message;
        self::register_shutdown_deferred();
    }

    /**
     * Permanently delete a single customer-chat message and purge related data.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function is_link_review_pending($message) {
        if (!is_array($message)) {
            return false;
        }
        $pending = $message['link_scan_review_pending'] ?? '';
        return $pending === '1' || $pending === 1 || $pending === true;
    }

    /**
     * Hide internal scan results from customer clients until an employee decides.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    public static function mask_message_for_customer($message) {
        if (!is_array($message)) {
            return $message;
        }
        if (self::is_link_review_pending($message)) {
            $message['link_scan_status'] = 'checking';
        }
        unset($message['link_scan_system_status'], $message['link_scan_review_pending']);
        return $message;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    public static function mask_messages_for_customer($messages) {
        if (!is_array($messages)) {
            return array();
        }
        $out = array();
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $out[] = self::mask_message_for_customer($message);
        }
        return $out;
    }

    public static function delete_message($session_id, $msg_seq, $deleted_by = 0, $channel = 'customer', $tombstone = '') {
        global $wpdb;
        self::maybe_upgrade();
        $session_id  = sanitize_text_field((string) $session_id);
        $msg_seq     = absint($msg_seq);
        $deleted_by  = absint($deleted_by);
        if ($session_id === '' || $msg_seq <= 0) {
            return new WP_Error('pax_message_invalid', 'Invalid message.', array('status' => 400));
        }

        $message = self::get_message($session_id, $msg_seq);
        if (!$message) {
            return new WP_Error('pax_message_not_found', 'Message not found.', array('status' => 404));
        }

        $table = self::messages_table();
        $deleted = $wpdb->delete(
            $table,
            array(
                'session_id' => $session_id,
                'msg_seq'    => $msg_seq,
            ),
            array('%s', '%d')
        );
        if ($deleted === false) {
            return new WP_Error('pax_message_delete_failed', 'Message could not be deleted.', array('status' => 500));
        }

        if ($tombstone === '') {
            $tombstone = __('This message was deleted by an employee.', 'paxdesign-booking');
        } else {
            $tombstone = sanitize_text_field((string) $tombstone);
        }
        $payload = array(
            'session_id'  => $session_id,
            'message_id'  => $msg_seq,
            'deleted_by'  => $deleted_by,
            'tombstone'   => $tombstone,
            'in_place'    => true,
            'warn'        => strpos($tombstone, 'unsafe link') !== false
                || strpos($tombstone, 'unsicheren Link') !== false
                || strpos($tombstone, 'رابط غير آمن') !== false,
        );
        self::emit('session:' . $session_id, 'message_deleted', $payload, $msg_seq);
        self::emit('inbox:admins', 'message_deleted', $payload, $msg_seq);

        if (class_exists('PAXdesign_Link_Scan_Service')) {
            PAXdesign_Link_Scan_Service::cancel_message_scans($session_id, $msg_seq);
        }

        self::defer_purge_message_assets($message);
        self::defer_customer_projection_rebuild($session_id, $channel);

        return array(
            'ok'          => true,
            'message_id'  => $msg_seq,
            'tombstone'   => $tombstone,
            'deleted_by'  => $deleted_by,
            'in_place'    => true,
            'warn'        => strpos($tombstone, 'unsafe link') !== false
                || strpos($tombstone, 'unsicheren Link') !== false
                || strpos($tombstone, 'رابط غير آمن') !== false,
        );
    }

    /**
     * @param array<string, mixed> $message
     */
    private static function purge_message_assets($message) {
        $image_url = !empty($message['image_url']) ? (string) $message['image_url'] : '';
        if ($image_url !== '') {
            self::delete_uploaded_file($image_url);
        }
        $audio_url = !empty($message['audio_url']) ? (string) $message['audio_url'] : '';
        if ($audio_url !== '') {
            self::delete_uploaded_file($audio_url);
        }
        if (!empty($message['link_url'])) {
            // Link card metadata only — no remote fetch cache retained.
        }
    }

    private static function delete_uploaded_file($url) {
        $uploads = wp_upload_dir();
        if (empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return;
        }
        if (strpos($url, $uploads['baseurl']) !== 0) {
            return;
        }
        $relative = ltrim(substr($url, strlen($uploads['baseurl'])), '/');
        $path = trailingslashit($uploads['basedir']) . $relative;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function rebuild_customer_projection($session_id, $channel = 'customer') {
        if ($channel !== 'customer' || !class_exists('PAXdesign_Chat_Log')) {
            return;
        }
        global $wpdb;
        $messages = self::fetch_all_rows($session_id);
        $last = !empty($messages) ? end($messages) : null;
        $preview = ($last && !empty($last['content'])) ? wp_html_excerpt($last['content'], 120, '…') : '';
        $seq = $last && isset($last['id']) ? (int) $last['id'] : 0;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'messages'      => wp_json_encode($messages),
                'message_seq'   => $seq,
                'message_count' => count($messages),
                'last_preview'  => $preview,
                'updated_at'    => current_time('mysql'),
            ),
            array('session_id' => $session_id),
            array('%s', '%d', '%d', '%s', '%s'),
            array('%s')
        );
    }

    private static function migrate_customer_session_if_needed($session_id, $channel, $already_locked = false) {
        if ($channel !== 'customer' || !class_exists('PAXdesign_Chat_Live') || !class_exists('PAXdesign_Chat_Log')) {
            return;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT messages FROM ' . PAXdesign_Chat_Log::table_name() . ' WHERE session_id = %s LIMIT 1',
            $session_id
        ));
        if ($row && isset($row->messages)) {
            $messages = json_decode($row->messages, true);
            if ($already_locked) {
                self::import_legacy_rows($session_id, is_array($messages) ? $messages : array(), 'customer');
            } else {
                self::migrate_legacy($session_id, $messages, 'customer');
            }
        }
    }

    private static function update_customer_projection($session_id, $message, $channel) {
        if ($channel !== 'customer' || !class_exists('PAXdesign_Chat_Log')) {
            return;
        }
        global $wpdb;
        $messages = self::fetch_all_rows($session_id);
        $preview = !empty($message['content']) ? wp_html_excerpt($message['content'], 120, '…') : '';
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'messages'      => wp_json_encode($messages),
                'message_seq'   => (int) $message['id'],
                'message_count' => count($messages),
                'last_preview'  => $preview,
                'updated_at'    => current_time('mysql'),
            ),
            array('session_id' => $session_id),
            array('%s', '%d', '%d', '%s', '%s'),
            array('%s')
        );
    }

    private static function sanitize_meta($extra) {
        $allowed = array(
            'image_url', 'audio_url', 'audio_duration', 'attachment_type', 'reply_to', 'reaction',
            'location_lat', 'location_lng', 'location_label',
            'sender_id', 'sender_name', 'sender_avatar', 'sender_role', 'sender_email',
            'link_url', 'link_label', 'link_icon',
            'link_scan_status', 'link_scan_system_status', 'link_scan_review_pending',
            'link_scan_urls', 'link_scan_started_at',
            'link_scan_completed_at', 'link_scan_provider',
        );
        $meta = array();
        foreach ($allowed as $key) {
            if (isset($extra[$key]) && $extra[$key] !== '') {
                $meta[$key] = $extra[$key];
            }
        }
        return $meta;
    }

    public static function format_row($row) {
        $meta = isset($row->meta_json) ? json_decode($row->meta_json, true) : array();
        if (!is_array($meta)) {
            $meta = array();
        }
        $entry = array_merge(array(
            'id'            => (int) $row->msg_seq,
            'client_msg_id' => (string) $row->client_msg_id,
            'role'          => (string) $row->role,
            'content'       => (string) $row->content,
            'ts'            => strtotime($row->created_at . ' UTC'),
        ), $meta);
        return $entry;
    }
}
