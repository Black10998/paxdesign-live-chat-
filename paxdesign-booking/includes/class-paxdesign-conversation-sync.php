<?php
/**
 * Unified conversation synchronization — single source of truth for poll payloads.
 *
 * Website widget (AJAX), customer REST (iOS app), and staff surfaces all consume
 * the same normalized sync envelope built from PAXdesign_Chat_Live::get_poll_data().
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Conversation_Sync {

    /**
     * Safe incremental cursor: msg_seq > since is exclusive, so a client that
     * advances `since` to the latest seq before merging locally would miss that
     * message. Rewind by one for incremental polls; clients dedupe by seq/id.
     */
    public static function incremental_since($since) {
        $since = max(0, (int) $since);
        if ($since <= 0) {
            return 0;
        }
        return max(0, $since - 1);
    }

    /**
     * Build the canonical sync payload used by AJAX poll and REST /messages.
     *
     * @param string $session_id
     * @param int    $since       Client-reported highest merged seq.
     * @param bool   $full        Full / paginated history request.
     * @param string $mark_read   '' | 'user' | 'admin'
     * @param int    $history_limit
     * @param int    $before
     * @return array<string,mixed>|WP_Error
     */
    public static function poll($session_id, $since = 0, $full = false, $mark_read = '', $history_limit = 0, $before = 0) {
        $since = max(0, (int) $since);
        $full = (bool) $full;
        $history_limit = max(0, (int) $history_limit);
        $before = max(0, (int) $before);

        $live = PAXdesign_Chat_Live::get_instance();
        $effective_since = $since;
        if (!$full && $before <= 0 && $since > 0) {
            $effective_since = self::incremental_since($since);
        }

        $data = $live->get_poll_data($session_id, $effective_since, $full, $mark_read, $history_limit, $before);
        if (is_wp_error($data)) {
            return $data;
        }

        return self::envelope($data, $since, $effective_since, $full);
    }

    /**
     * Attach sync metadata so every client can merge monotonically.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function envelope($data, $client_since = 0, $effective_since = 0, $full = false) {
        $client_since = max(0, (int) $client_since);
        $effective_since = max(0, (int) $effective_since);
        $server_seq = isset($data['seq']) ? (int) $data['seq'] : 0;
        $message_count = isset($data['message_count']) ? (int) $data['message_count'] : 0;
        $incoming = isset($data['messages']) && is_array($data['messages']) ? $data['messages'] : array();

        $max_incoming = 0;
        foreach ($incoming as $msg) {
            if (isset($msg['id']) && (int) $msg['id'] > $max_incoming) {
                $max_incoming = (int) $msg['id'];
            }
        }

        $resync_required = false;
        if (!$full && $client_since > 0 && empty($incoming) && $server_seq > 0 && $message_count > 0) {
            $resync_required = ($client_since >= $server_seq && $message_count >= $client_since);
        }

        $data['sync'] = array(
            'version'           => 1,
            'client_since'      => $client_since,
            'effective_since'   => $effective_since,
            'server_seq'        => $server_seq,
            'message_count'     => $message_count,
            'incoming_max_seq'  => $max_incoming,
            'resync_required'   => $resync_required,
            'cursor_after_apply'=> $server_seq,
            'generated_at'      => gmdate('c'),
        );

        return $data;
    }

    /**
     * Derive a stable client_msg_id when legacy clients omit one (iOS standalone).
     */
    public static function legacy_client_msg_id($user_id, $session_id, $content) {
        $user_id = absint($user_id);
        $session_id = sanitize_text_field((string) $session_id);
        $content = sanitize_textarea_field((string) $content);
        $bucket = gmdate('YmdHi');
        $hash = substr(hash('sha256', $user_id . '|' . $session_id . '|' . $content . '|' . $bucket), 0, 32);
        return 'legacy:' . $hash;
    }

    /**
     * REST / AJAX no-store headers for sync endpoints.
     */
    public static function send_nostore_headers() {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
