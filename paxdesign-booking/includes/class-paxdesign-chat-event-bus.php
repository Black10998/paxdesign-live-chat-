<?php
/**
 * Real-time chat event bus — backs SSE streams for instant cross-client sync.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Chat_Event_Bus {

    const QUEUE_TTL    = 900;
    const MAX_EVENTS   = 500;
    const STREAM_WAIT  = 25;
    const GLOBAL_KEY   = 'pax_evt_global_seq';

    /**
     * @param string               $channel
     * @param string               $type
     * @param array<string, mixed> $payload
     * @return int Event id
     */
    public static function emit($channel, $type, $payload = array()) {
        $channel = sanitize_text_field($channel);
        $type    = sanitize_key($type);
        if ($channel === '' || $type === '') {
            return 0;
        }

        $key   = self::queue_key($channel);
        $queue = get_transient($key);
        if (!is_array($queue)) {
            $queue = array('seq' => 0, 'events' => array());
        }
        if (!isset($queue['seq'])) {
            $queue['seq'] = 0;
        }
        if (!isset($queue['events']) || !is_array($queue['events'])) {
            $queue['events'] = array();
        }

        $global_id = self::next_global_id();
        $event = array(
            'id'      => $global_id,
            'type'    => $type,
            'ts'      => time(),
            'payload' => $payload,
        );
        $queue['events'][] = $event;
        if (count($queue['events']) > self::MAX_EVENTS) {
            $queue['events'] = array_slice($queue['events'], -self::MAX_EVENTS);
        }
        set_transient($key, $queue, self::QUEUE_TTL);

        return $global_id;
    }

    /**
     * Monotonic id shared across all channels so multiplexed SSE clients
     * can track a single cursor without missing events on other channels.
     *
     * @return int
     */
    private static function next_global_id() {
        global $wpdb;

        $key = self::GLOBAL_KEY;
        $table = $wpdb->options;

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $table (option_name, option_value, autoload)
                 VALUES (%s, '0', 'no')
                 ON DUPLICATE KEY UPDATE option_name = option_name",
                $key
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table
                 SET option_value = CAST(option_value AS UNSIGNED) + 1
                 WHERE option_name = %s",
                $key
            )
        );

        $seq = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $table WHERE option_name = %s LIMIT 1",
                $key
            )
        );
        if ($seq > 0) {
            return $seq;
        }

        // Last-resort fallback.
        $seq = (int) get_option($key, 0);
        $seq++;
        update_option($key, $seq, false);
        return $seq;
    }

    /**
     * @param string $session_id
     * @param string $type
     * @param array<string, mixed> $payload
     */
    public static function emit_session($session_id, $type, $payload = array()) {
        $session_id = sanitize_text_field($session_id);
        if ($session_id === '') {
            return;
        }
        $payload['session_id'] = $session_id;
        self::emit('session:' . $session_id, $type, $payload);
        self::emit('inbox:admins', $type, $payload);
    }

    /**
     * @param string $conv_id
     * @param string $type
     * @param array<string, mixed> $payload
     */
    public static function emit_team($conv_id, $type, $payload = array()) {
        $conv_id = sanitize_text_field($conv_id);
        if ($conv_id === '') {
            return;
        }
        $payload['session_id'] = $conv_id;
        $payload['conversation_id'] = $conv_id;
        self::emit('team:' . $conv_id, $type, $payload);

        $participants = isset($payload['participants']) && is_array($payload['participants'])
            ? array_map('absint', $payload['participants'])
            : array();
        foreach ($participants as $uid) {
            if ($uid > 0) {
                self::emit('inbox:user:' . $uid, $type, $payload);
            }
        }
    }

    /**
     * @param int $user_id
     */
    public static function inbox_channel_for_user($user_id) {
        return 'inbox:user:' . absint($user_id);
    }

    /**
     * @param string $channel
     * @param int    $since
     * @return array<int, array<string, mixed>>
     */
    public static function events_since($channel, $since = 0) {
        $key   = self::queue_key($channel);
        $queue = get_transient($key);
        if (!is_array($queue) || empty($queue['events']) || !is_array($queue['events'])) {
            return array();
        }
        $since = absint($since);
        $latest = end($queue['events']);
        $latest_id = is_array($latest) && isset($latest['id']) ? (int) $latest['id'] : 0;
        // Recover automatically if the client cursor is ahead after a cache reset.
        if ($since > 0 && $latest_id > 0 && $since > $latest_id) {
            $since = 0;
        }
        $out   = array();
        foreach ($queue['events'] as $event) {
            if (!is_array($event) || !isset($event['id'])) {
                continue;
            }
            if ((int) $event['id'] > $since) {
                $out[] = $event;
            }
        }
        return $out;
    }

    /**
     * Stream SSE events until new data arrives or timeout.
     *
     * @param string $channel
     * @param int    $since
     * @param int    $timeout
     */
    public static function stream_sse($channel, $since = 0, $timeout = 0) {
        self::send_sse_headers();
        $since   = absint($since);
        $timeout = $timeout > 0 ? min(30, $timeout) : self::STREAM_WAIT;
        $deadline = microtime(true) + $timeout;
        $last_id  = $since;

        while (microtime(true) < $deadline) {
            $events = self::events_since($channel, $last_id);
            if (!empty($events)) {
                foreach ($events as $event) {
                    self::write_sse('chat', $event);
                    if (isset($event['id'])) {
                        $last_id = max($last_id, (int) $event['id']);
                    }
                }
                echo "event: ping\ndata: {\"since\":" . (int) $last_id . "}\n\n";
                self::flush_output();
                break;
            }
            echo ": keepalive " . time() . "\n\n";
            self::flush_output();
            usleep(100000);
        }
    }

    /**
     * Multiplex session + inbox channels for admin clients.
     *
     * @param int    $user_id
     * @param string $session_id
     * @param int    $since_session
     * @param int    $since_inbox
     */
    public static function stream_admin_sse($user_id, $session_id = '', $since_session = 0, $since_inbox = 0) {
        self::send_sse_headers();
        $channels = array(
            array('channel' => 'inbox:admins', 'since' => absint($since_inbox)),
            array('channel' => self::inbox_channel_for_user($user_id), 'since' => absint($since_inbox)),
        );
        if ($session_id !== '') {
            $channels[] = array('channel' => 'session:' . sanitize_text_field($session_id), 'since' => absint($since_session));
        }

        $deadline = microtime(true) + self::STREAM_WAIT;
        $last_ids = array();
        foreach ($channels as $c) {
            $last_ids[$c['channel']] = (int) $c['since'];
        }

        while (microtime(true) < $deadline) {
            $found = false;
            foreach ($channels as $c) {
                $ch = $c['channel'];
                $events = self::events_since($ch, isset($last_ids[$ch]) ? $last_ids[$ch] : 0);
                foreach ($events as $event) {
                    $found = true;
                    $event['channel'] = $ch;
                    self::write_sse('chat', $event);
                    if (isset($event['id'])) {
                        $last_ids[$ch] = max(isset($last_ids[$ch]) ? $last_ids[$ch] : 0, (int) $event['id']);
                    }
                }
            }
            if ($found) {
                echo "event: ping\ndata: " . wp_json_encode(array('since_inbox' => max($last_ids))) . "\n\n";
                self::flush_output();
                break;
            }
            echo ": keepalive " . time() . "\n\n";
            self::flush_output();
            usleep(100000);
        }
    }

    /**
     * @param string               $event_name
     * @param array<string, mixed> $data
     */
    private static function write_sse($event_name, $data) {
        echo 'event: ' . sanitize_key($event_name) . "\n";
        echo 'data: ' . wp_json_encode($data) . "\n\n";
        self::flush_output();
    }

    private static function send_sse_headers() {
        if (headers_sent()) {
            return;
        }
        status_header(200);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    private static function flush_output() {
        if (function_exists('wp_ob_end_flush_all')) {
            wp_ob_end_flush_all();
        }
        @flush();
    }

    /**
     * @param string $channel
     * @return string
     */
    private static function queue_key($channel) {
        return 'pax_evt_q_' . md5((string) $channel);
    }
}
