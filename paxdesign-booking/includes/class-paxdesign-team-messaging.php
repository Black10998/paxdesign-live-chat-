<?php
/**
 * Internal team direct messaging for PAXdesign Live Chat mobile app.
 *
 * Stores admin-to-staff conversations separately from customer chat sessions
 * so the existing live-chat pipeline remains untouched.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Team_Messaging {

    const OPTION_KEY = 'paxdesign_team_conversations';
    const HANDLER    = 'team_dm';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_PENDING  = 'pending';
    const STATUS_DECLINED = 'declined';
    const STATUS_LOCKED   = 'locked';
    const TYPING_TTL      = 5;
    /** Outbound team messages per staff user per minute (abuse guard only). */
    const SEND_RATE_LIMIT_WINDOW = 60;
    const SEND_RATE_LIMIT_MAX    = 120;

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function all_conversations() {
        $stored = get_option(self::OPTION_KEY, array());
        return is_array($stored) ? $stored : array();
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    private static function save_conversations($data) {
        update_option(self::OPTION_KEY, $data, false);
    }

    /**
     * Serialize writes to option-backed team conversations to avoid lost updates.
     *
     * @param string   $scope
     * @param callable $callback
     * @return mixed
     */
    private static function with_write_lock($scope, $callback) {
        // All conversations share one wp_options row, therefore every mutation
        // must use the same lock. Per-conversation locks still lose updates
        // when two conversations rewrite the shared option concurrently.
        $lock_name = 'pax_team_msg_global';
        $acquired = PAXdesign_DB::acquire_named_lock($lock_name, 5);
        if ($acquired !== 1) {
            return new WP_Error(
                'pax_team_lock_timeout',
                'Team conversation is busy. Please retry.',
                array('status' => 503)
            );
        }

        try {
            return $callback();
        } finally {
            PAXdesign_DB::release_named_lock($lock_name);
        }
    }

    /**
     * @param int $user_id
     * @return bool
     */
    private static function check_send_rate_limit($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return true;
        }
        $transient_key = 'pax_team_send_rl_' . $user_id;
        $count         = (int) get_transient($transient_key);
        if ($count >= self::SEND_RATE_LIMIT_MAX) {
            return false;
        }
        set_transient($transient_key, $count + 1, self::SEND_RATE_LIMIT_WINDOW);
        return true;
    }

    /**
     * @param int $current_user_id
     * @return WP_Error|null
     */
    private static function send_rate_limit_error($current_user_id) {
        if (self::check_send_rate_limit($current_user_id)) {
            return null;
        }
        return new WP_Error(
            'rate_limit',
            __('Too many messages. Please wait a moment and try again.', 'paxdesign-booking'),
            array('status' => 429, 'retry_after' => self::SEND_RATE_LIMIT_WINDOW)
        );
    }

    /**
     * @param int $user_a
     * @param int $user_b
     * @return string
     */
    public static function conversation_id($user_a, $user_b) {
        $a = absint($user_a);
        $b = absint($user_b);
        if ($a > $b) {
            $tmp = $a;
            $a   = $b;
            $b   = $tmp;
        }
        return 'team_' . $a . '_' . $b;
    }

    /**
     * @param int $current_user_id
     * @param int $other_user_id
     * @return array<string, mixed>
     */
    public static function open_conversation($current_user_id, $other_user_id, $request_note = '') {
        $current_user_id = absint($current_user_id);
        $other_user_id   = absint($other_user_id);
        $request_note    = trim(wp_strip_all_tags((string) $request_note));

        if ($current_user_id <= 0 || $other_user_id <= 0 || $current_user_id === $other_user_id) {
            return array('error' => 'invalid_participants');
        }

        if (self::is_user_blocked($current_user_id, $other_user_id)) {
            return array('error' => 'user_blocked');
        }

        $other = get_user_by('id', $other_user_id);
        if (!$other) {
            return array('error' => 'user_not_found');
        }

        $conv_id = self::conversation_id($current_user_id, $other_user_id);
        $needs_approval = PAXdesign_Live_Chat_Permissions::requires_team_conversation_approval(
            $current_user_id,
            $other_user_id
        );

        $result = self::with_write_lock('open:' . $conv_id, function () use (
            $conv_id,
            $current_user_id,
            $other_user_id,
            $needs_approval,
            $request_note
        ) {
            $all = self::all_conversations();

            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                $status = $needs_approval ? self::STATUS_PENDING : self::STATUS_ACCEPTED;
                $all[$conv_id] = array(
                    'participants'   => array($current_user_id, $other_user_id),
                    'messages'       => array(),
                    'read_seq'       => array(),
                    'hidden_for'     => array(),
                    'pinned_for'     => array(),
                    'muted_for'      => array(),
                    'request_status' => $status,
                    'requested_by'   => $needs_approval ? $current_user_id : 0,
                    'requested_at'   => $needs_approval ? gmdate('Y-m-d H:i:s') : '',
                    'responded_at'   => '',
                    'responded_by'   => 0,
                    'request_note'   => $needs_approval ? $request_note : '',
                    'assigned_to'    => 0,
                    'typing'         => array(),
                    'seq'            => 0,
                    'updated_at'     => gmdate('Y-m-d H:i:s'),
                );
                self::save_conversations($all);
                if ($needs_approval) {
                    self::emit_request_event($conv_id, $all[$conv_id], $current_user_id, $other_user_id);
                }
            } else {
                $conv = $all[$conv_id];
                if (!isset($conv['hidden_for']) || !is_array($conv['hidden_for'])) {
                    $conv['hidden_for'] = array();
                }
                $status = isset($conv['request_status']) ? (string) $conv['request_status'] : self::STATUS_ACCEPTED;
                if ($status === self::STATUS_DECLINED || $status === self::STATUS_LOCKED) {
                    return array('error' => 'conversation_declined');
                }
                $conv['hidden_for'] = array_values(array_diff(
                    array_map('absint', $conv['hidden_for']),
                    array($current_user_id)
                ));
                $all[$conv_id] = $conv;
                self::save_conversations($all);
            }

            return array(
                'conversation_id' => $conv_id,
                'session'         => self::format_session_row($conv_id, $all[$conv_id], $current_user_id),
            );
        });

        if (is_wp_error($result)) {
            return array('error' => $result->get_error_code());
        }

        return $result;
    }

    /**
     * @param int $current_user_id
     * @return array{sessions: array<int, array<string, mixed>>, live_count: int}
     */
    public static function list_sessions_for_user($current_user_id, $include_threads = false) {
        $current_user_id = absint($current_user_id);
        $all             = self::all_conversations();
        $sessions        = array();

        foreach ($all as $conv_id => $conv) {
            if (!is_array($conv)) {
                continue;
            }
            $participants = isset($conv['participants']) && is_array($conv['participants'])
                ? array_map('absint', $conv['participants'])
                : array();
            if (!in_array($current_user_id, $participants, true)) {
                continue;
            }
            if (self::is_hidden_for_user($conv, $current_user_id)) {
                continue;
            }
            $sessions[] = self::format_session_row($conv_id, $conv, $current_user_id);
        }

        usort($sessions, function ($a, $b) {
            $pa = !empty($a['is_pinned']) ? 1 : 0;
            $pb = !empty($b['is_pinned']) ? 1 : 0;
            if ($pa !== $pb) {
                return $pb - $pa;
            }
            $ra = isset($a['other_role_rank']) ? (int) $a['other_role_rank'] : 99;
            $rb = isset($b['other_role_rank']) ? (int) $b['other_role_rank'] : 99;
            if ($ra !== $rb) {
                return $ra - $rb;
            }
            return strcmp((string) $b['updated_at'], (string) $a['updated_at']);
        });

        $sessions = self::dedupe_sessions_by_other_user($sessions);

        $threads = array();
        if ($include_threads) {
            foreach ($sessions as $session) {
                $conv_id = isset($session['session_id']) ? (string) $session['session_id'] : '';
                if ($conv_id === '') {
                    continue;
                }
                $thread = self::poll_conversation($conv_id, $current_user_id, 0, true);
                if (!is_wp_error($thread)) {
                    $threads[$conv_id] = $thread;
                }
            }
        }

        return array(
            'sessions'   => $sessions,
            'live_count' => 0,
            'threads'    => $threads,
        );
    }

    /**
     * @param int $user_id
     * @return int
     */
    public static function count_unread_messages_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $total = 0;
        $all   = self::all_conversations();
        foreach ($all as $conv_id => $conv) {
            if (!is_array($conv)) {
                continue;
            }
            $participants = isset($conv['participants']) && is_array($conv['participants'])
                ? array_map('absint', $conv['participants'])
                : array();
            if (!in_array($user_id, $participants, true)) {
                continue;
            }
            if (self::is_hidden_for_user($conv, $user_id)) {
                continue;
            }
            $read_seq = self::read_seq_for_user($conv, $user_id);
            if (class_exists('PAXdesign_Message_Store')) {
                $total += PAXdesign_Message_Store::count_incoming_messages_since($conv_id, $read_seq, 'team');
            }
        }

        return max(0, $total);
    }

    /**
     * @param string $conv_id
     * @param int    $current_user_id
     * @param int    $since
     * @param bool   $full
     * @return array<string, mixed>|WP_Error
     */
    public static function poll_conversation($conv_id, $current_user_id, $since = 0, $full = false) {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        $all             = self::all_conversations();

        if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
            return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
        }

        $conv = $all[$conv_id];
        if (!self::user_in_conversation($conv, $current_user_id)) {
            return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
        }

        $legacy_messages = isset($conv['messages']) && is_array($conv['messages']) ? $conv['messages'] : array();
        if (class_exists('PAXdesign_Message_Store')) {
            PAXdesign_Message_Store::migrate_legacy($conv_id, $legacy_messages, 'team');
            $messages = PAXdesign_Message_Store::all_messages($conv_id, 'team');
            $seq = PAXdesign_Message_Store::latest_seq($conv_id, 'team');
        } else {
            $messages = $legacy_messages;
            $seq = isset($conv['seq']) ? absint($conv['seq']) : 0;
        }
        $since    = absint($since);

        if ($full || $since <= 0) {
            $out_messages = $messages;
        } else {
            $out_messages = array_values(array_filter($messages, function ($msg) use ($since) {
                return isset($msg['id']) && absint($msg['id']) > $since;
            }));
            if (empty($out_messages) && $since > 0 && $seq > $since) {
                $out_messages = array_values(array_filter($messages, function ($msg) use ($since) {
                    return isset($msg['id']) && absint($msg['id']) >= $since;
                }));
            }
        }

        $other         = self::other_participant($conv, $current_user_id);
        $self_identity = PAXdesign_Chat_Live::resolve_employee_identity($current_user_id);
        $other_identity = $other ? PAXdesign_Chat_Live::resolve_employee_identity((int) $other->ID) : null;
        $read_seq      = self::read_seq_for_user($conv, $current_user_id);
        $other_read    = $other ? self::read_seq_for_user($conv, (int) $other->ID) : 0;
        $request_meta  = self::request_meta($conv, $current_user_id);
        $other_typing  = $other ? self::is_user_typing($conv, (int) $other->ID) : false;
        $other_presence = $other
            ? PAXdesign_Live_Chat_Permissions::get_team_presence((int) $other->ID)
            : array('status' => 'offline', 'last_seen' => 0);

        return array(
            'session_id'       => $conv_id,
            'handler'          => self::HANDLER,
            'handler_label'    => 'Team',
            'customer_name'    => $other_identity ? $other_identity['name'] : ($other ? $other->display_name : 'Team'),
            'admin_name'       => $self_identity ? $self_identity['name'] : wp_get_current_user()->display_name,
            'other_user_id'    => $other ? (int) $other->ID : 0,
            'assigned_agent'   => $other_identity,
            'detected_service' => 'Team-Nachricht',
            'updated_at'       => PAXdesign_API_Time::format(isset($conv['updated_at']) ? (string) $conv['updated_at'] : '', true),
            'session_rating'   => 0,
            'seq'              => $seq,
            'message_count'    => count($messages),
            'last_read_seq'    => $read_seq,
            'other_read_seq'   => $other_read,
            'messages'         => array_map(array(__CLASS__, 'format_message'), $out_messages),
            'user_typing'      => $other_typing,
            'request_status'   => $request_meta['request_status'],
            'request_status_label' => $request_meta['request_status_label'],
            'can_send'         => $request_meta['can_send'],
            'can_respond'      => $request_meta['can_respond'],
            'requested_by'     => $request_meta['requested_by'],
            'is_pinned'        => $request_meta['is_pinned'],
            'is_muted'         => $request_meta['is_muted'],
            'assigned_to'      => $request_meta['assigned_to'],
            'other_role_rank'  => $other ? PAXdesign_Live_Chat_Permissions::team_role_rank((int) $other->ID) : 99,
            'other_role_label' => $other
                ? PAXdesign_Live_Chat_Permissions::team_role_label_for_user((int) $other->ID)
                : '',
            'other_presence'   => $other_presence['status'],
            'other_last_seen'  => $other_presence['last_seen'],
        );
    }

    /**
     * @param string $conv_id
     * @param int    $current_user_id
     * @param string $content
     * @return array<string, mixed>|WP_Error
     */
    public static function send_message($conv_id, $current_user_id, $content, $client_msg_id = '') {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        $content         = trim(wp_strip_all_tags((string) $content));

        if ($content === '') {
            return new WP_Error('pax_team_empty', 'Message cannot be empty', array('status' => 400));
        }

        $limited = self::send_rate_limit_error($current_user_id);
        if ($limited instanceof WP_Error) {
            return $limited;
        }

        return self::with_write_lock('send:' . $conv_id, function () use ($conv_id, $current_user_id, $content, $client_msg_id) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }

            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }
            if (!self::conversation_writable($conv, $current_user_id)) {
                return new WP_Error(
                    'pax_team_locked',
                    'Conversation is awaiting approval or has been declined.',
                    array('status' => 403)
                );
            }

            if (!isset($conv['messages']) || !is_array($conv['messages'])) {
                $conv['messages'] = array();
            }
            if (!isset($conv['read_seq']) || !is_array($conv['read_seq'])) {
                $conv['read_seq'] = array();
            }

            $identity = PAXdesign_Chat_Live::resolve_employee_identity($current_user_id);
            $stored = PAXdesign_Message_Store::append(
                $conv_id,
                'admin',
                $content,
                array(
                    'client_msg_id' => $client_msg_id,
                    'sender_id'     => $current_user_id,
                    'sender_name'   => $identity ? $identity['name'] : wp_get_current_user()->display_name,
                    'sender_avatar' => $identity ? $identity['avatar'] : '',
                    'sender_role'   => $identity ? $identity['role'] : '',
                    'participants'  => isset($conv['participants']) ? $conv['participants'] : array(),
                    'legacy_messages' => $conv['messages'],
                    'lock_already_held' => true,
                ),
                'team'
            );
            if (is_wp_error($stored)) {
                return $stored;
            }
            $seq = isset($stored['id']) ? absint($stored['id']) : 0;
            $msg_id = $seq;

            $already_projected = false;
            foreach ($conv['messages'] as $projected) {
                if (isset($projected['id']) && absint($projected['id']) === $msg_id) {
                    $already_projected = true;
                    break;
                }
            }
            if (!$already_projected) {
                $conv['messages'][] = $stored;
            }
            $conv['seq']        = $seq;
            $conv['updated_at'] = gmdate('Y-m-d H:i:s');
            $conv['read_seq'][(string) $current_user_id] = $seq;

            $all[$conv_id] = $conv;
            self::save_conversations($all);

            $formatted = self::format_message($stored);

            $recipient = self::other_participant($conv, $current_user_id);
            if (empty($stored['_deduplicated']) && $recipient && class_exists('PAXdesign_APNS')) {
                $sender_name = $identity ? $identity['name'] : wp_get_current_user()->display_name;
                PAXdesign_APNS::notify_team_message(
                    (int) $recipient->ID,
                    $sender_name,
                    $content,
                    $conv_id
                );
            }

            return array(
                'ok'      => true,
                'message' => $formatted,
                'seq'     => $seq,
            );
        });
    }

    /**
     * Broadcast a text message to every enabled team contact (server-side fan-out).
     *
     * @param int    $current_user_id
     * @param string $content
     * @param string $client_msg_id
     * @return array<string, mixed>|WP_Error
     */
    public static function broadcast_message($current_user_id, $content, $client_msg_id = '') {
        $current_user_id = absint($current_user_id);
        $content         = trim(wp_strip_all_tags((string) $content));

        if ($content === '') {
            return new WP_Error('pax_team_empty', 'Message cannot be empty', array('status' => 400));
        }

        if (!class_exists('PAXdesign_Live_Chat_Permissions')) {
            return new WP_Error('pax_team_unavailable', 'Team permissions unavailable', array('status' => 500));
        }

        $contacts   = PAXdesign_Live_Chat_Permissions::list_team_contacts_for_api();
        $sent       = 0;
        $skipped    = array();
        $client_base = $client_msg_id !== '' ? sanitize_text_field((string) $client_msg_id) : wp_generate_uuid4();

        foreach ($contacts as $member) {
            if (!is_array($member)) {
                continue;
            }
            $uid = isset($member['user_id']) ? absint($member['user_id']) : 0;
            if ($uid <= 0 || $uid === $current_user_id) {
                continue;
            }

            $open = self::open_conversation($current_user_id, $uid);
            if (!is_array($open) || isset($open['error'])) {
                $skipped[] = $uid;
                continue;
            }

            $conv_id = isset($open['conversation_id']) ? sanitize_text_field((string) $open['conversation_id']) : '';
            if ($conv_id === '') {
                $skipped[] = $uid;
                continue;
            }

            $per_client = $client_base . ':' . $uid;
            $result     = self::send_message($conv_id, $current_user_id, $content, $per_client);
            if (is_wp_error($result)) {
                $skipped[] = $uid;
                continue;
            }
            $sent++;
        }

        return array(
            'ok'      => true,
            'sent'    => $sent,
            'skipped' => $skipped,
        );
    }

    /**
     * @param string               $conv_id
     * @param int                  $current_user_id
     * @param array<string, mixed> $file
     * @param string               $caption
     * @param string               $client_msg_id
     * @return array<string, mixed>|WP_Error
     */
    public static function send_image($conv_id, $current_user_id, $file, $caption = '', $client_msg_id = '') {
        $upload = self::handle_media_upload($file, 'image');
        if (is_wp_error($upload)) {
            return $upload;
        }

        $caption = trim(wp_strip_all_tags((string) $caption));
        return self::append_attachment_message(
            $conv_id,
            $current_user_id,
            $caption,
            array(
                'image_url'       => $upload['url'],
                'attachment_type' => 'image',
            ),
            $client_msg_id,
            $upload['preview']
        );
    }

    /**
     * @param string               $conv_id
     * @param int                  $current_user_id
     * @param array<string, mixed> $file
     * @param float                $duration
     * @param string               $client_msg_id
     * @return array<string, mixed>|WP_Error
     */
    public static function send_audio($conv_id, $current_user_id, $file, $duration = 0, $client_msg_id = '', $waveform = array()) {
        $upload = self::handle_media_upload($file, 'audio');
        if (is_wp_error($upload)) {
            return $upload;
        }

        $waveform = self::sanitize_waveform($waveform);

        return self::append_attachment_message(
            $conv_id,
            $current_user_id,
            '',
            array(
                'audio_url'       => $upload['url'],
                'audio_duration'  => max(0, (float) $duration),
                'audio_waveform'  => $waveform,
                'attachment_type' => 'voice',
            ),
            $client_msg_id,
            'Voice message'
        );
    }

    /**
     * @param string $conv_id
     * @param int    $current_user_id
     * @param float  $lat
     * @param float  $lng
     * @param string $label
     * @param string $client_msg_id
     * @return array<string, mixed>|WP_Error
     */
    public static function send_location($conv_id, $current_user_id, $lat, $lng, $label = '', $client_msg_id = '') {
        $lat   = (float) $lat;
        $lng   = (float) $lng;
        $label = trim(wp_strip_all_tags((string) $label));

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return new WP_Error('pax_team_invalid_location', 'Invalid coordinates.', array('status' => 400));
        }

        $content = $label !== '' ? $label : sprintf('%.5f, %.5f', $lat, $lng);
        return self::append_attachment_message(
            $conv_id,
            $current_user_id,
            $content,
            array(
                'location_lat'    => $lat,
                'location_lng'    => $lng,
                'location_label'  => $label,
                'attachment_type' => 'location',
            ),
            $client_msg_id,
            'Location'
        );
    }

    /**
     * @param string               $conv_id
     * @param int                  $current_user_id
     * @param array<string, mixed> $file
     * @param string               $caption
     * @param string               $client_msg_id
     * @return array<string, mixed>|WP_Error
     */
    public static function send_file($conv_id, $current_user_id, $file, $caption = '', $client_msg_id = '') {
        $upload = self::handle_media_upload($file, 'file');
        if (is_wp_error($upload)) {
            return $upload;
        }

        $caption = trim(wp_strip_all_tags((string) $caption));
        $attachment_type = !empty($upload['attachment_type']) ? (string) $upload['attachment_type'] : 'file';
        $meta = array(
            'file_url'        => $upload['url'],
            'file_name'       => $upload['name'],
            'file_mime'       => $upload['mime'],
            'attachment_type' => $attachment_type,
        );
        if (!empty($upload['image_url'])) {
            $meta['image_url'] = $upload['image_url'];
        }
        if (!empty($upload['audio_url'])) {
            $meta['audio_url'] = $upload['audio_url'];
            $meta['attachment_type'] = 'voice';
        }

        return self::append_attachment_message(
            $conv_id,
            $current_user_id,
            $caption,
            $meta,
            $client_msg_id,
            $upload['preview']
        );
    }

    /**
     * @param string               $conv_id
     * @param int                  $current_user_id
     * @param string               $content
     * @param array<string, mixed> $attachment_meta
     * @param string               $client_msg_id
     * @param string               $push_preview
     * @return array<string, mixed>|WP_Error
     */
    private static function append_attachment_message(
        $conv_id,
        $current_user_id,
        $content,
        array $attachment_meta,
        $client_msg_id,
        $push_preview
    ) {
        return self::with_write_lock('attach:' . $conv_id, function () use (
            $conv_id,
            $current_user_id,
            $content,
            $attachment_meta,
            $client_msg_id,
            $push_preview
        ) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }

            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }
            if (!self::conversation_writable($conv, $current_user_id)) {
                return new WP_Error(
                    'pax_team_locked',
                    'Conversation is awaiting approval or has been declined.',
                    array('status' => 403)
                );
            }

            if (!isset($conv['messages']) || !is_array($conv['messages'])) {
                $conv['messages'] = array();
            }
            if (!isset($conv['read_seq']) || !is_array($conv['read_seq'])) {
                $conv['read_seq'] = array();
            }

            $identity = PAXdesign_Chat_Live::resolve_employee_identity($current_user_id);
            $extra = array_merge($attachment_meta, array(
                'client_msg_id'     => $client_msg_id,
                'sender_id'         => $current_user_id,
                'sender_name'       => $identity ? $identity['name'] : wp_get_current_user()->display_name,
                'sender_avatar'     => $identity ? $identity['avatar'] : '',
                'sender_role'       => $identity ? $identity['role'] : '',
                'participants'      => isset($conv['participants']) ? $conv['participants'] : array(),
                'legacy_messages'   => $conv['messages'],
                'lock_already_held' => true,
            ));

            $stored = PAXdesign_Message_Store::append(
                $conv_id,
                'admin',
                $content,
                $extra,
                'team'
            );
            if (is_wp_error($stored)) {
                return $stored;
            }

            $seq    = isset($stored['id']) ? absint($stored['id']) : 0;
            $msg_id = $seq;

            $already_projected = false;
            foreach ($conv['messages'] as $projected) {
                if (isset($projected['id']) && absint($projected['id']) === $msg_id) {
                    $already_projected = true;
                    break;
                }
            }
            if (!$already_projected) {
                $conv['messages'][] = $stored;
            }
            $conv['seq']        = $seq;
            $conv['updated_at'] = gmdate('Y-m-d H:i:s');
            $conv['read_seq'][(string) $current_user_id] = $seq;

            $all[$conv_id] = $conv;
            self::save_conversations($all);

            $formatted = self::format_message($stored);

            $recipient = self::other_participant($conv, $current_user_id);
            if (empty($stored['_deduplicated']) && $recipient && class_exists('PAXdesign_APNS')) {
                $sender_name = $identity ? $identity['name'] : wp_get_current_user()->display_name;
                PAXdesign_APNS::notify_team_message(
                    (int) $recipient->ID,
                    $sender_name,
                    $push_preview,
                    $conv_id
                );
            }

            return array(
                'ok'      => true,
                'message' => $formatted,
                'seq'     => $seq,
            );
        });
    }

    /**
     * @param array<string, mixed> $file
     * @param string               $kind
     * @return array<string, string>|WP_Error
     */
    private static function handle_media_upload($file, $kind) {
        if (empty($file) || !empty($file['error'])) {
            return new WP_Error('upload_failed', 'Upload failed.', array('status' => 400));
        }

        if ($kind === 'audio') {
            return self::store_audio_upload($file);
        }

        if ($kind === 'image') {
            $allowed = array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'webp'         => 'image/webp',
                'gif'          => 'image/gif',
                'heic'         => 'image/heic',
                'heif'         => 'image/heif',
                'tif|tiff'     => 'image/tiff',
            );
            $max_size = 24 * 1024 * 1024;
            $name  = isset($file['name']) ? (string) $file['name'] : 'image.jpg';
            $check = wp_check_filetype($name, $allowed);
            if (empty($check['type']) || !in_array($check['type'], array_values($allowed), true)) {
                return new WP_Error('invalid_type', 'Unsupported file type.', array('status' => 400));
            }
            if (!empty($file['size']) && (int) $file['size'] > $max_size) {
                return new WP_Error('too_large', 'File is too large.', array('status' => 400));
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allowed));
            if (!empty($upload['error'])) {
                return new WP_Error('upload_failed', $upload['error'], array('status' => 500));
            }

            $url = (string) $upload['url'];
            if (!empty($upload['file'])) {
                $url = self::optimize_team_image($upload['file'], $url);
            }

            return array(
                'url'     => $url,
                'preview' => 'Photo',
            );
        }

        if ($kind === 'file') {
            return self::store_generic_file_upload($file);
        }

        return new WP_Error('invalid_type', 'Unsupported file type.', array('status' => 400));
    }

    /**
     * Store design/work files for team chat (PDF, vectors, archives, office docs, etc.).
     *
     * @param array<string, mixed> $file
     * @return array<string, string>|WP_Error
     */
    private static function store_generic_file_upload($file) {
        $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            return new WP_Error('upload_failed', 'Invalid file upload.', array('status' => 400));
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0) {
            return new WP_Error('upload_failed', 'Empty file.', array('status' => 400));
        }
        if ($size > 64 * 1024 * 1024) {
            return new WP_Error('too_large', 'File is too large.', array('status' => 400));
        }

        $original_name = isset($file['name']) ? sanitize_file_name((string) $file['name']) : 'attachment.bin';
        if ($original_name === '') {
            $original_name = 'attachment.bin';
        }

        $allowed = array(
            'pdf'                          => 'application/pdf',
            'svg'                          => 'image/svg+xml',
            'ai'                           => 'application/postscript',
            'eps'                          => 'application/postscript',
            'dxf'                          => 'application/dxf',
            'lbrn2'                        => 'application/octet-stream',
            'zip'                          => 'application/zip',
            'rar'                          => 'application/vnd.rar',
            'json'                         => 'application/json',
            'xml'                          => 'application/xml',
            'csv'                          => 'text/csv',
            'txt'                          => 'text/plain',
            'psd'                          => 'image/vnd.adobe.photoshop',
            'doc'                          => 'application/msword',
            'docx'                         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'                          => 'application/vnd.ms-excel',
            'xlsx'                         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'                          => 'application/vnd.ms-powerpoint',
            'pptx'                         => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mov'                          => 'video/quicktime',
            'mp4'                          => 'video/mp4',
            'm4v'                          => 'video/x-m4v',
            'vcf'                          => 'text/vcard',
            'jpg|jpeg|jpe'                 => 'image/jpeg',
            'png'                          => 'image/png',
            'webp'                         => 'image/webp',
            'gif'                          => 'image/gif',
            'heic'                         => 'image/heic',
            'heif'                         => 'image/heif',
            'tif|tiff'                     => 'image/tiff',
        );

        $check = wp_check_filetype($original_name, $allowed);
        if (empty($check['type'])) {
            return new WP_Error('invalid_type', 'Unsupported file type.', array('status' => 400));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload(
            $file,
            array(
                'test_form' => false,
                'mimes'     => $allowed,
            )
        );
        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], array('status' => 500));
        }

        $mime = (string) $check['type'];
        $url  = (string) $upload['url'];
        $preview = $original_name;
        $attachment_type = 'file';
        $result = array(
            'url'             => $url,
            'name'            => $original_name,
            'mime'            => $mime,
            'preview'         => $preview,
            'attachment_type' => $attachment_type,
        );

        if (strpos($mime, 'image/') === 0) {
            $result['image_url'] = $url;
            $result['attachment_type'] = 'image';
            $result['preview'] = 'Photo';
        } elseif (strpos($mime, 'video/') === 0) {
            $result['attachment_type'] = 'video';
            $result['preview'] = 'Video';
        }

        return $result;
    }

    /**
     * Store AAC/M4A voice uploads without wp_handle_upload MIME probing.
     * iOS recordings are valid MP4 containers but often fail WordPress upload tests.
     *
     * @param array<string, mixed> $file
     * @return array<string, string>|WP_Error
     */
    private static function store_audio_upload($file) {
        $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            return new WP_Error('upload_failed', 'Invalid audio upload.', array('status' => 400));
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0) {
            return new WP_Error('upload_failed', 'Empty audio file.', array('status' => 400));
        }
        if ($size > 5 * 1024 * 1024) {
            return new WP_Error('too_large', 'File is too large.', array('status' => 400));
        }

        $contents = file_get_contents($tmp_name);
        if ($contents === false || $contents === '') {
            return new WP_Error('upload_failed', 'Could not read uploaded audio.', array('status' => 400));
        }

        if (strlen($contents) < 12) {
            return new WP_Error('invalid_type', 'Unsupported audio format.', array('status' => 400));
        }

        $signature = substr($contents, 4, 4);
        if ($signature !== 'ftyp' && $signature !== 'M4A ' && $signature !== 'mp42') {
            return new WP_Error('invalid_type', 'Unsupported audio format.', array('status' => 400));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $filename = 'pax-voice-' . gmdate('Ymd-His') . '-' . wp_generate_password(8, false, false) . '.m4a';
        $upload   = wp_upload_bits($filename, null, $contents);
        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], array('status' => 500));
        }
        if (empty($upload['url'])) {
            return new WP_Error('upload_failed', 'Audio upload did not return a URL.', array('status' => 500));
        }

        return array(
            'url'     => (string) $upload['url'],
            'preview' => 'Voice message',
        );
    }

    /**
     * @param string $file_path
     * @param string $url
     * @return string
     */
    private static function optimize_team_image($file_path, $url) {
        if (!function_exists('wp_get_image_editor')) {
            return $url;
        }
        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return $url;
        }
        $size = $editor->get_size();
        if (!empty($size['width']) && (int) $size['width'] > 2048) {
            $editor->resize(2048, null, false);
            $saved = $editor->save($file_path);
            if (!is_wp_error($saved) && !empty($saved['path'])) {
                $uploads = wp_upload_dir();
                if (!empty($uploads['basedir']) && !empty($uploads['baseurl'])) {
                    $relative = ltrim(str_replace($uploads['basedir'], '', $saved['path']), '/');
                    return trailingslashit($uploads['baseurl']) . $relative;
                }
            }
        }
        return $url;
    }

    /**
     * @param string $conv_id
     * @param int    $current_user_id
     * @param int    $seq
     * @return array<string, mixed>|WP_Error
     */
    public static function mark_read($conv_id, $current_user_id, $seq) {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        $seq             = absint($seq);

        return self::with_write_lock('read:' . $conv_id, function () use ($conv_id, $current_user_id, $seq) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }

            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }

            if (!isset($conv['read_seq']) || !is_array($conv['read_seq'])) {
                $conv['read_seq'] = array();
            }

            $key = (string) $current_user_id;
            $current = isset($conv['read_seq'][$key]) ? absint($conv['read_seq'][$key]) : 0;
            if ($seq > $current) {
                $conv['read_seq'][$key] = $seq;
                $all[$conv_id] = $conv;
                self::save_conversations($all);
                if (class_exists('PAXdesign_Chat_Event_Bus')) {
                    PAXdesign_Chat_Event_Bus::emit_team($conv_id, 'read', array(
                        'seq'          => $seq,
                        'user_id'      => $current_user_id,
                        'participants' => isset($conv['participants']) ? $conv['participants'] : array(),
                    ));
                }
            }

            return array(
                'ok'            => true,
                'last_read_seq' => isset($conv['read_seq'][$key]) ? absint($conv['read_seq'][$key]) : 0,
                'seq'           => isset($conv['seq']) ? absint($conv['seq']) : 0,
            );
        });
    }

    /**
     * @param array<string, mixed> $conv
     * @param int                  $current_user_id
     * @return int
     */
    private static function read_seq_for_user($conv, $current_user_id) {
        if (!isset($conv['read_seq']) || !is_array($conv['read_seq'])) {
            return 0;
        }
        $key = (string) absint($current_user_id);
        return isset($conv['read_seq'][$key]) ? absint($conv['read_seq'][$key]) : 0;
    }

    /**
     * @param array<string, mixed> $conv
     * @param int                  $current_user_id
     * @return bool
     */
    private static function user_in_conversation($conv, $current_user_id) {
        $participants = isset($conv['participants']) && is_array($conv['participants'])
            ? array_map('absint', $conv['participants'])
            : array();
        return in_array(absint($current_user_id), $participants, true);
    }

    /**
     * @param array<string, mixed> $conv
     * @param int                  $current_user_id
     * @return WP_User|null
     */
    private static function other_participant($conv, $current_user_id) {
        $participants = isset($conv['participants']) && is_array($conv['participants'])
            ? array_map('absint', $conv['participants'])
            : array();
        foreach ($participants as $uid) {
            if ($uid !== absint($current_user_id)) {
                return get_user_by('id', $uid) ?: null;
            }
        }
        return null;
    }

    /**
     * @param string               $conv_id
     * @param array<string, mixed> $conv
     * @param int                  $current_user_id
     * @return array<string, mixed>
     */
    private static function format_session_row($conv_id, $conv, $current_user_id) {
        $other    = self::other_participant($conv, $current_user_id);
        $seq      = 0;
        $count    = 0;
        $last     = null;

        if (class_exists('PAXdesign_Message_Store')) {
            $legacy_messages = isset($conv['messages']) && is_array($conv['messages']) ? $conv['messages'] : array();
            PAXdesign_Message_Store::migrate_legacy($conv_id, $legacy_messages, 'team');
            $messages = PAXdesign_Message_Store::all_messages($conv_id, 'team');
            $seq      = PAXdesign_Message_Store::latest_seq($conv_id, 'team');
            $count    = count($messages);
            $last     = !empty($messages) ? end($messages) : null;
        } else {
            $messages = isset($conv['messages']) && is_array($conv['messages']) ? $conv['messages'] : array();
            $count    = count($messages);
            $seq      = isset($conv['seq']) ? absint($conv['seq']) : 0;
            $last     = !empty($messages) ? end($messages) : null;
        }

        $self_identity  = PAXdesign_Chat_Live::resolve_employee_identity($current_user_id);
        $other_identity = $other ? PAXdesign_Chat_Live::resolve_employee_identity((int) $other->ID) : null;

        $last_preview = '';
        $last_role    = 'admin';
        if (is_array($last)) {
            if (!empty($last['image_url'])) {
                $last_preview = 'Photo';
            } elseif (!empty($last['audio_url'])) {
                $last_preview = 'Voice message';
            } elseif (isset($last['location_lat'], $last['location_lng'])) {
                $last_preview = 'Location';
            } else {
                $last_preview = isset($last['content']) ? (string) $last['content'] : '';
            }
            if (isset($last['sender_id']) && absint($last['sender_id']) !== absint($current_user_id)) {
                $last_role = 'user';
            }
        }

        $read_seq = self::read_seq_for_user($conv, $current_user_id);
        $request_meta = self::request_meta($conv, $current_user_id);
        $other_presence = $other
            ? PAXdesign_Live_Chat_Permissions::get_team_presence((int) $other->ID)
            : array('status' => 'offline', 'last_seen' => 0);

        return array(
            'id'               => 0,
            'session_id'       => $conv_id,
            'handler'          => self::HANDLER,
            'handler_label'    => 'Team',
            'admin_name'       => $self_identity ? $self_identity['name'] : wp_get_current_user()->display_name,
            'customer_name'    => $other_identity ? $other_identity['name'] : ($other ? $other->display_name : 'Team'),
            'other_user_id'    => $other ? (int) $other->ID : 0,
            'assigned_agent'   => $other_identity,
            'session_rating'   => 0,
            'detected_service' => 'Team-Nachricht',
            'updated_at'       => PAXdesign_API_Time::format(isset($conv['updated_at']) ? (string) $conv['updated_at'] : '', true),
            'message_count'    => $count,
            'seq'              => $seq,
            'last_read_seq'    => $read_seq,
            'last_preview'     => $last_preview,
            'last_role'        => $last_role,
            'request_status'   => $request_meta['request_status'],
            'request_status_label' => $request_meta['request_status_label'],
            'can_send'         => $request_meta['can_send'],
            'can_respond'      => $request_meta['can_respond'],
            'requested_by'     => $request_meta['requested_by'],
            'is_pinned'        => $request_meta['is_pinned'],
            'is_muted'         => $request_meta['is_muted'],
            'assigned_to'      => $request_meta['assigned_to'],
            'other_role_rank'  => $other ? PAXdesign_Live_Chat_Permissions::team_role_rank((int) $other->ID) : 99,
            'other_role_label' => $other
                ? PAXdesign_Live_Chat_Permissions::team_role_label_for_user((int) $other->ID)
                : '',
            'other_presence'   => $other_presence['status'],
            'other_last_seen'  => $other_presence['last_seen'],
        );
    }

    /**
     * @param array<string, mixed> $msg
     * @return array<string, mixed>
     */
    public static function format_message($msg) {
        $sender_id = isset($msg['sender_id']) ? absint($msg['sender_id']) : 0;
        $identity  = $sender_id > 0 ? PAXdesign_Chat_Live::resolve_employee_identity($sender_id) : null;
        $is_self   = $sender_id === get_current_user_id();

        return array(
            'id'              => isset($msg['id']) ? absint($msg['id']) : 0,
            'client_msg_id'   => isset($msg['client_msg_id']) ? sanitize_text_field($msg['client_msg_id']) : '',
            'role'            => $is_self ? 'admin' : 'user',
            'content'         => isset($msg['content']) ? (string) $msg['content'] : '',
            'ts'              => isset($msg['ts']) ? absint($msg['ts']) : time(),
            'image_url'       => !empty($msg['image_url']) ? esc_url_raw((string) $msg['image_url']) : '',
            'audio_url'       => !empty($msg['audio_url']) ? esc_url_raw((string) $msg['audio_url']) : '',
            'audio_duration'  => isset($msg['audio_duration']) ? (float) $msg['audio_duration'] : 0,
            'audio_waveform'  => self::normalize_waveform(isset($msg['audio_waveform']) ? $msg['audio_waveform'] : null),
            'attachment_type' => !empty($msg['attachment_type']) ? sanitize_key((string) $msg['attachment_type']) : '',
            'location_lat'    => isset($msg['location_lat']) ? (float) $msg['location_lat'] : null,
            'location_lng'    => isset($msg['location_lng']) ? (float) $msg['location_lng'] : null,
            'location_label'  => !empty($msg['location_label']) ? sanitize_text_field((string) $msg['location_label']) : '',
            'file_url'        => !empty($msg['file_url']) ? esc_url_raw((string) $msg['file_url']) : '',
            'file_name'       => !empty($msg['file_name']) ? sanitize_file_name((string) $msg['file_name']) : '',
            'file_mime'       => !empty($msg['file_mime']) ? sanitize_mime_type((string) $msg['file_mime']) : '',
            'reply_to'        => null,
            'reaction'        => null,
            'sender_id'       => $sender_id,
            'sender_name'     => $identity ? $identity['name'] : '',
            'sender_avatar'   => $identity ? $identity['avatar'] : '',
            'sender_role'     => $identity ? $identity['role'] : '',
            'sender'          => $identity ? $identity['name'] : '',
        );
    }

    /**
     * @param array<string, mixed> $conv
     * @param int                  $user_id
     */
    private static function is_hidden_for_user($conv, $user_id) {
        if (!isset($conv['hidden_for']) || !is_array($conv['hidden_for'])) {
            return false;
        }
        return in_array(absint($user_id), array_map('absint', $conv['hidden_for']), true);
    }

    /**
     * Hide conversation for the current user only (other participant keeps it).
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function hide_conversation($conv_id, $current_user_id) {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        return self::with_write_lock('hide:' . $conv_id, function () use ($conv_id, $current_user_id) {
            $all = self::all_conversations();

            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }

            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }

            if (!isset($conv['hidden_for']) || !is_array($conv['hidden_for'])) {
                $conv['hidden_for'] = array();
            }
            if (!in_array($current_user_id, array_map('absint', $conv['hidden_for']), true)) {
                $conv['hidden_for'][] = $current_user_id;
            }

            $participants = isset($conv['participants']) ? array_map('absint', $conv['participants']) : array();
            $all_hidden   = true;
            foreach ($participants as $uid) {
                if (!in_array($uid, array_map('absint', $conv['hidden_for']), true)) {
                    $all_hidden = false;
                    break;
                }
            }
            if ($all_hidden) {
                unset($all[$conv_id]);
            } else {
                $all[$conv_id] = $conv;
            }
            self::save_conversations($all);

            if ($all_hidden && class_exists('PAXdesign_Message_Store')) {
                PAXdesign_Message_Store::delete_session($conv_id);
            }
            if (class_exists('PAXdesign_Chat_Event_Bus')) {
                PAXdesign_Chat_Event_Bus::emit_team($conv_id, 'conversation_deleted', array(
                    'mode'         => $all_hidden ? 'purged' : 'hidden',
                    'user_id'      => $current_user_id,
                    'participants' => $participants,
                ));
            }

            return array(
                'ok'      => true,
                'mode'    => $all_hidden ? 'purged' : 'hidden',
                'message' => $all_hidden
                    ? 'Conversation permanently deleted for all participants.'
                    : 'Conversation removed from your Team list. The other participant can still see it.',
            );
        });
    }

    /**
     * Force-delete conversation for all participants (managers only).
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function purge_conversation($conv_id, $current_user_id) {
        if (!PAXdesign_Live_Chat_Permissions::can($current_user_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)
            && !PAXdesign_Live_Chat_Permissions::is_super_admin($current_user_id)) {
            return new WP_Error('pax_team_forbidden', 'Insufficient permissions', array('status' => 403));
        }

        $conv_id = sanitize_text_field($conv_id);
        return self::with_write_lock('purge:' . $conv_id, function () use ($conv_id, $current_user_id) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }

            $participants = isset($all[$conv_id]['participants']) ? array_map('absint', $all[$conv_id]['participants']) : array();
            unset($all[$conv_id]);
            self::save_conversations($all);

            if (class_exists('PAXdesign_Message_Store')) {
                PAXdesign_Message_Store::delete_session($conv_id);
            }
            if (class_exists('PAXdesign_Chat_Event_Bus')) {
                PAXdesign_Chat_Event_Bus::emit_team($conv_id, 'conversation_deleted', array(
                    'mode'         => 'purged',
                    'user_id'      => absint($current_user_id),
                    'participants' => $participants,
                ));
            }

            return array(
                'ok'      => true,
                'mode'    => 'purged',
                'message' => 'Conversation permanently deleted for all participants.',
            );
        });
    }

    /**
     * @return array{sessions: array<int, array<string, mixed>>}
     */
    public static function list_pending_requests_for_user($current_user_id) {
        $current_user_id = absint($current_user_id);
        $all             = self::all_conversations();
        $sessions        = array();

        foreach ($all as $conv_id => $conv) {
            if (!is_array($conv)) {
                continue;
            }
            $status = isset($conv['request_status']) ? (string) $conv['request_status'] : self::STATUS_ACCEPTED;
            if ($status !== self::STATUS_PENDING) {
                continue;
            }
            if (!self::user_in_conversation($conv, $current_user_id)) {
                continue;
            }
            if (self::is_hidden_for_user($conv, $current_user_id)) {
                continue;
            }
            $meta = self::request_meta($conv, $current_user_id);
            if (empty($meta['can_respond'])) {
                continue;
            }
            $sessions[] = self::format_session_row($conv_id, $conv, $current_user_id);
        }

        usort($sessions, function ($a, $b) {
            return strcmp((string) $b['updated_at'], (string) $a['updated_at']);
        });

        return array('sessions' => $sessions);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function respond_to_request($conv_id, $current_user_id, $accept) {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        $accept          = (bool) $accept;

        return self::with_write_lock('respond:' . $conv_id, function () use ($conv_id, $current_user_id, $accept) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }

            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }

            $meta = self::request_meta($conv, $current_user_id);
            if (empty($meta['can_respond'])) {
                return new WP_Error('pax_team_forbidden', 'Cannot respond to this request', array('status' => 403));
            }

            $status = isset($conv['request_status']) ? (string) $conv['request_status'] : self::STATUS_ACCEPTED;
            if ($status !== self::STATUS_PENDING) {
                return new WP_Error('pax_team_invalid', 'Request is no longer pending', array('status' => 400));
            }

            $conv['request_status'] = $accept ? self::STATUS_ACCEPTED : self::STATUS_DECLINED;
            $conv['responded_at']   = gmdate('Y-m-d H:i:s');
            $conv['responded_by']   = $current_user_id;
            $conv['updated_at']     = gmdate('Y-m-d H:i:s');
            $all[$conv_id]          = $conv;
            self::save_conversations($all);

            $requester_id = isset($conv['requested_by']) ? absint($conv['requested_by']) : 0;
            if ($requester_id > 0 && class_exists('PAXdesign_APNS')) {
                $responder = PAXdesign_Chat_Live::resolve_employee_identity($current_user_id);
                $name      = $responder ? $responder['name'] : wp_get_current_user()->display_name;
                if ($accept) {
                    PAXdesign_APNS::notify_team_request_response(
                        $requester_id,
                        $name,
                        'accepted',
                        $conv_id
                    );
                } else {
                    PAXdesign_APNS::notify_team_request_response(
                        $requester_id,
                        $name,
                        'declined',
                        $conv_id
                    );
                }
            }

            if (class_exists('PAXdesign_Chat_Event_Bus')) {
                PAXdesign_Chat_Event_Bus::emit_team($conv_id, 'request_update', array(
                    'request_status' => $conv['request_status'],
                    'responded_by'   => $current_user_id,
                    'participants'   => isset($conv['participants']) ? $conv['participants'] : array(),
                    'session_id'     => $conv_id,
                ));
            }

            return array(
                'ok'             => true,
                'request_status' => $conv['request_status'],
                'session'        => self::format_session_row($conv_id, $conv, $current_user_id),
            );
        });
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function set_typing($conv_id, $current_user_id, $typing) {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        $typing          = (bool) $typing;

        return self::with_write_lock('typing:' . $conv_id, function () use ($conv_id, $current_user_id, $typing) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }

            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }

            if (!isset($conv['typing']) || !is_array($conv['typing'])) {
                $conv['typing'] = array();
            }
            if ($typing) {
                $conv['typing'][(string) $current_user_id] = time();
            } else {
                unset($conv['typing'][(string) $current_user_id]);
            }
            $all[$conv_id] = $conv;
            self::save_conversations($all);

            if ($typing && class_exists('PAXdesign_Chat_Event_Bus')) {
                PAXdesign_Chat_Event_Bus::emit_team($conv_id, 'typing', array(
                    'user_id'      => $current_user_id,
                    'typing'       => true,
                    'participants' => isset($conv['participants']) ? $conv['participants'] : array(),
                    'session_id'   => $conv_id,
                ));
            }

            return array('ok' => true);
        });
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function pin_conversation($conv_id, $current_user_id, $pinned) {
        return self::set_user_flag($conv_id, $current_user_id, 'pinned_for', (bool) $pinned);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function mute_conversation($conv_id, $current_user_id, $muted) {
        return self::set_user_flag($conv_id, $current_user_id, 'muted_for', (bool) $muted, true);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function assign_conversation($conv_id, $current_user_id, $assignee_id) {
        $conv_id      = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        $assignee_id  = absint($assignee_id);

        if (!PAXdesign_Live_Chat_Permissions::is_super_admin($current_user_id)
            && !PAXdesign_Live_Chat_Permissions::can($current_user_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)) {
            return new WP_Error('pax_team_forbidden', 'Insufficient permissions', array('status' => 403));
        }

        return self::with_write_lock('assign:' . $conv_id, function () use ($conv_id, $current_user_id, $assignee_id) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }
            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }
            $conv['assigned_to'] = $assignee_id;
            $conv['updated_at']  = gmdate('Y-m-d H:i:s');
            $all[$conv_id]       = $conv;
            self::save_conversations($all);

            if (class_exists('PAXdesign_Chat_Event_Bus')) {
                PAXdesign_Chat_Event_Bus::emit_team($conv_id, 'session_update', array(
                    'assigned_to'  => $assignee_id,
                    'participants' => isset($conv['participants']) ? $conv['participants'] : array(),
                    'session_id'   => $conv_id,
                ));
            }

            return array(
                'ok'          => true,
                'assigned_to' => $assignee_id,
                'session'     => self::format_session_row($conv_id, $conv, $current_user_id),
            );
        });
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public static function block_user($current_user_id, $blocked_user_id) {
        $current_user_id = absint($current_user_id);
        $blocked_user_id = absint($blocked_user_id);
        if ($current_user_id <= 0 || $blocked_user_id <= 0 || $current_user_id === $blocked_user_id) {
            return new WP_Error('pax_invalid', 'Invalid users', array('status' => 400));
        }
        if (!PAXdesign_Live_Chat_Permissions::is_super_admin($current_user_id)
            && !PAXdesign_Live_Chat_Permissions::can($current_user_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)) {
            return new WP_Error('pax_team_forbidden', 'Insufficient permissions', array('status' => 403));
        }

        $key = 'pax_team_blocked_' . $current_user_id;
        $blocked = get_option($key, array());
        if (!is_array($blocked)) {
            $blocked = array();
        }
        if (!in_array($blocked_user_id, array_map('absint', $blocked), true)) {
            $blocked[] = $blocked_user_id;
        }
        update_option($key, array_values(array_unique(array_map('absint', $blocked))), false);

        return array('ok' => true, 'blocked_user_id' => $blocked_user_id);
    }

    /**
     * @param int $viewer_id
     * @param int $other_id
     */
    public static function is_user_blocked($viewer_id, $other_id) {
        $viewer_id = absint($viewer_id);
        $other_id  = absint($other_id);
        $blocked   = get_option('pax_team_blocked_' . $other_id, array());
        if (!is_array($blocked)) {
            return false;
        }
        return in_array($viewer_id, array_map('absint', $blocked), true);
    }

    /**
     * @param string $conv_id
     * @param int    $user_id
     */
    public static function assert_participant($conv_id, $user_id) {
        $all = self::all_conversations();
        if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
            return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
        }
        if (!self::user_in_conversation($all[$conv_id], $user_id)) {
            return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
        }
        return true;
    }

    /**
     * @param array<string, mixed> $conv
     * @param int                  $current_user_id
     */
    private static function conversation_writable($conv, $current_user_id) {
        $status = isset($conv['request_status']) ? (string) $conv['request_status'] : self::STATUS_ACCEPTED;
        if ($status === self::STATUS_ACCEPTED) {
            return true;
        }
        if ($status === self::STATUS_DECLINED || $status === self::STATUS_LOCKED) {
            return false;
        }
        return false;
    }

    /**
     * @param array<string, mixed> $conv
     * @param int                  $current_user_id
     * @return array<string, mixed>
     */
    private static function request_meta($conv, $current_user_id) {
        $status = isset($conv['request_status']) ? (string) $conv['request_status'] : self::STATUS_ACCEPTED;
        $requested_by = isset($conv['requested_by']) ? absint($conv['requested_by']) : 0;
        $current_user_id = absint($current_user_id);

        $can_respond = false;
        if ($status === self::STATUS_PENDING && $requested_by !== $current_user_id) {
            $requester_rank = PAXdesign_Live_Chat_Permissions::team_role_rank($requested_by);
            $viewer_rank    = PAXdesign_Live_Chat_Permissions::team_role_rank($current_user_id);
            $can_respond    = $viewer_rank < $requester_rank;
        }

        $label = 'Accepted';
        if ($status === self::STATUS_PENDING) {
            $label = $requested_by === $current_user_id ? 'Waiting for approval' : 'Request pending';
        } elseif ($status === self::STATUS_DECLINED) {
            $label = 'Declined';
        } elseif ($status === self::STATUS_LOCKED) {
            $label = 'Locked';
        }

        $pinned_for = isset($conv['pinned_for']) && is_array($conv['pinned_for']) ? $conv['pinned_for'] : array();
        $muted_for  = isset($conv['muted_for']) && is_array($conv['muted_for']) ? array_map('absint', $conv['muted_for']) : array();

        return array(
            'request_status'       => $status,
            'request_status_label' => $label,
            'can_send'             => self::conversation_writable($conv, $current_user_id),
            'can_respond'          => $can_respond,
            'requested_by'         => $requested_by,
            'is_pinned'            => !empty($pinned_for[(string) $current_user_id]),
            'is_muted'             => in_array($current_user_id, $muted_for, true),
            'assigned_to'          => isset($conv['assigned_to']) ? absint($conv['assigned_to']) : 0,
        );
    }

    /**
     * @param array<string, mixed> $conv
     * @param int                  $user_id
     */
    private static function is_user_typing($conv, $user_id) {
        if (!isset($conv['typing']) || !is_array($conv['typing'])) {
            return false;
        }
        $key = (string) absint($user_id);
        if (!isset($conv['typing'][$key])) {
            return false;
        }
        return (time() - absint($conv['typing'][$key])) <= self::TYPING_TTL;
    }

    /**
     * @param int    $requester_id
     * @param int    $target_id
     */
    private static function emit_request_event($conv_id, $conv, $requester_id, $target_id) {
        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::emit_team($conv_id, 'conversation_request', array(
                'request_status' => self::STATUS_PENDING,
                'requested_by'   => $requester_id,
                'participants'   => isset($conv['participants']) ? $conv['participants'] : array(),
                'session_id'     => $conv_id,
                'request_note'   => isset($conv['request_note']) ? (string) $conv['request_note'] : '',
            ));
        }
        if (class_exists('PAXdesign_APNS')) {
            $requester = PAXdesign_Chat_Live::resolve_employee_identity($requester_id);
            $name      = $requester ? $requester['name'] : '';
            PAXdesign_APNS::notify_team_request(
                $target_id,
                $name,
                isset($conv['request_note']) ? (string) $conv['request_note'] : '',
                $conv_id
            );
        }
    }

    /**
     * @param string $conv_id
     * @param int    $current_user_id
     * @param string $field
     * @param bool   $value
     * @param bool   $list_field
     * @return array<string, mixed>|WP_Error
     */
    private static function set_user_flag($conv_id, $current_user_id, $field, $value, $list_field = false) {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);

        return self::with_write_lock($field . ':' . $conv_id, function () use ($conv_id, $current_user_id, $field, $value, $list_field) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }
            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }

            if ($list_field) {
                if (!isset($conv[$field]) || !is_array($conv[$field])) {
                    $conv[$field] = array();
                }
                $ids = array_map('absint', $conv[$field]);
                if ($value) {
                    if (!in_array($current_user_id, $ids, true)) {
                        $ids[] = $current_user_id;
                    }
                } else {
                    $ids = array_values(array_diff($ids, array($current_user_id)));
                }
                $conv[$field] = $ids;
            } else {
                if (!isset($conv[$field]) || !is_array($conv[$field])) {
                    $conv[$field] = array();
                }
                if ($value) {
                    $conv[$field][(string) $current_user_id] = true;
                } else {
                    unset($conv[$field][(string) $current_user_id]);
                }
            }

            $conv['updated_at'] = gmdate('Y-m-d H:i:s');
            $all[$conv_id]      = $conv;
            self::save_conversations($all);

            return array(
                'ok'      => true,
                'session' => self::format_session_row($conv_id, $conv, $current_user_id),
            );
        });
    }

    /**
     * One-time reconciliation after upgrades: canonicalize conversation ids,
     * merge duplicate participant pairs, and purge orphan records.
     */
    public static function maybe_reconcile_store() {
        $flag = 'paxdesign_team_store_reconciled_' . PAXDESIGN_BOOKING_VERSION;
        if (get_option($flag)) {
            return;
        }
        self::reconcile_team_store();
        update_option($flag, gmdate('c'), false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function reconcile_team_store() {
        $result = self::with_write_lock('reconcile', function () {
            $all     = self::all_conversations();
            $merged  = array();
            $orphans = array();

            foreach ($all as $conv_id => $conv) {
                if (!is_array($conv)) {
                    $orphans[] = $conv_id;
                    continue;
                }

                $participants = isset($conv['participants']) && is_array($conv['participants'])
                    ? array_values(array_unique(array_filter(array_map('absint', $conv['participants']))))
                    : array();
                sort($participants);

                if (count($participants) < 2 || $participants[0] === $participants[1]) {
                    $orphans[] = $conv_id;
                    continue;
                }

                $canonical_id = self::conversation_id($participants[0], $participants[1]);
                if (!isset($merged[$canonical_id])) {
                    $merged[$canonical_id] = self::blank_conversation($participants);
                }

                self::merge_conversation_record(
                    $merged[$canonical_id],
                    $conv,
                    $conv_id,
                    $canonical_id
                );

                if ($conv_id !== $canonical_id) {
                    $orphans[] = $conv_id;
                }
            }

            foreach ($orphans as $orphan_id) {
                if (isset($merged[$orphan_id])) {
                    continue;
                }
                if (class_exists('PAXdesign_Message_Store')) {
                    PAXdesign_Message_Store::delete_session($orphan_id);
                }
            }

            self::save_conversations($merged);

            return array(
                'ok'               => true,
                'conversation_count' => count($merged),
                'purged_orphans'   => count($orphans),
            );
        });

        if (is_wp_error($result)) {
            return array('ok' => false, 'error' => $result->get_error_code());
        }

        return $result;
    }

    /**
     * Permanently delete a single team message (Executive Administrator only).
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function delete_message($conv_id, $current_user_id, $message_id) {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        $message_id      = absint($message_id);

        if (!PAXdesign_Live_Chat_Permissions::is_super_admin($current_user_id)) {
            return new WP_Error('pax_team_forbidden', 'Executive Administrator permission required.', array('status' => 403));
        }
        if ($message_id <= 0) {
            return new WP_Error('pax_team_invalid', 'Invalid message.', array('status' => 400));
        }

        return self::with_write_lock('delete_msg:' . $conv_id, function () use ($conv_id, $current_user_id, $message_id) {
            $all = self::all_conversations();
            if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
                return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
            }

            $conv = $all[$conv_id];
            if (!self::user_in_conversation($conv, $current_user_id)) {
                return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
            }

            if (!class_exists('PAXdesign_Message_Store')) {
                return new WP_Error('pax_team_unavailable', 'Message store unavailable.', array('status' => 500));
            }

            $deleted = PAXdesign_Message_Store::delete_message(
                $conv_id,
                $message_id,
                $current_user_id,
                'team',
                ''
            );
            if (is_wp_error($deleted)) {
                return $deleted;
            }

            if (isset($conv['messages']) && is_array($conv['messages'])) {
                $conv['messages'] = array_values(array_filter($conv['messages'], function ($row) use ($message_id) {
                    return !(is_array($row) && isset($row['id']) && absint($row['id']) === $message_id);
                }));
            }
            $latest = PAXdesign_Message_Store::latest_seq($conv_id, 'team');
            $conv['seq'] = $latest;
            $conv['updated_at'] = gmdate('Y-m-d H:i:s');
            $all[$conv_id] = $conv;
            self::save_conversations($all);

            $participants = isset($conv['participants']) ? array_map('absint', $conv['participants']) : array();
            if (class_exists('PAXdesign_Chat_Event_Bus')) {
                PAXdesign_Chat_Event_Bus::emit_team($conv_id, 'message_deleted', array(
                    'message_id'   => $message_id,
                    'deleted_by'   => $current_user_id,
                    'participants' => $participants,
                    'permanent'    => true,
                ));
            }

            return array(
                'ok'         => true,
                'message_id' => $message_id,
            );
        });
    }

    /**
     * @param array<int, array<string, mixed>> $sessions
     * @return array<int, array<string, mixed>>
     */
    private static function dedupe_sessions_by_other_user($sessions) {
        $best = array();
        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }
            $other_id = isset($session['other_user_id']) ? absint($session['other_user_id']) : 0;
            if ($other_id <= 0) {
                $best[] = $session;
                continue;
            }
            if (!isset($best[$other_id])) {
                $best[$other_id] = $session;
                continue;
            }
            $existing = $best[$other_id];
            $existing_ts = isset($existing['updated_at']) ? (string) $existing['updated_at'] : '';
            $incoming_ts = isset($session['updated_at']) ? (string) $session['updated_at'] : '';
            if (strcmp($incoming_ts, $existing_ts) > 0) {
                $best[$other_id] = $session;
            }
        }

        $out = array();
        foreach ($best as $key => $session) {
            if (is_int($key)) {
                $out[] = $session;
            } else {
                $out[] = $session;
            }
        }

        usort($out, function ($a, $b) {
            $pa = !empty($a['is_pinned']) ? 1 : 0;
            $pb = !empty($b['is_pinned']) ? 1 : 0;
            if ($pa !== $pb) {
                return $pb - $pa;
            }
            $ra = isset($a['other_role_rank']) ? (int) $a['other_role_rank'] : 99;
            $rb = isset($b['other_role_rank']) ? (int) $b['other_role_rank'] : 99;
            if ($ra !== $rb) {
                return $ra - $rb;
            }
            return strcmp((string) $b['updated_at'], (string) $a['updated_at']);
        });

        return array_values($out);
    }

    /**
     * @param array<int, int> $participants
     * @return array<string, mixed>
     */
    private static function blank_conversation($participants) {
        return array(
            'participants'   => array_values($participants),
            'messages'       => array(),
            'read_seq'       => array(),
            'hidden_for'     => array(),
            'pinned_for'     => array(),
            'muted_for'      => array(),
            'request_status' => self::STATUS_ACCEPTED,
            'requested_by'   => 0,
            'requested_at'   => '',
            'responded_at'   => '',
            'responded_by'   => 0,
            'request_note'   => '',
            'assigned_to'    => 0,
            'typing'         => array(),
            'seq'            => 0,
            'updated_at'     => gmdate('Y-m-d H:i:s'),
        );
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $source
     * @param string               $source_id
     * @param string               $canonical_id
     */
    private static function merge_conversation_record(&$target, $source, $source_id, $canonical_id) {
        $target['participants'] = isset($source['participants']) && is_array($source['participants'])
            ? array_values(array_unique(array_map('absint', $source['participants'])))
            : $target['participants'];
        sort($target['participants']);

        foreach (array('read_seq', 'hidden_for', 'pinned_for', 'muted_for') as $list_key) {
            if (!isset($target[$list_key]) || !is_array($target[$list_key])) {
                $target[$list_key] = array();
            }
            if (isset($source[$list_key]) && is_array($source[$list_key])) {
                if ($list_key === 'read_seq') {
                    foreach ($source[$list_key] as $uid => $seq) {
                        $uid_key = (string) absint($uid);
                        $target[$list_key][$uid_key] = max(
                            isset($target[$list_key][$uid_key]) ? absint($target[$list_key][$uid_key]) : 0,
                            absint($seq)
                        );
                    }
                } else {
                    $merged = array_merge(
                        array_map('absint', $target[$list_key]),
                        array_map('absint', $source[$list_key])
                    );
                    $target[$list_key] = array_values(array_unique($merged));
                }
            }
        }

        $source_updated = isset($source['updated_at']) ? (string) $source['updated_at'] : '';
        $target_updated = isset($target['updated_at']) ? (string) $target['updated_at'] : '';
        if ($source_updated !== '' && strcmp($source_updated, $target_updated) > 0) {
            $target['updated_at'] = $source_updated;
        }

        $source_seq = isset($source['seq']) ? absint($source['seq']) : 0;
        $target['seq'] = max(isset($target['seq']) ? absint($target['seq']) : 0, $source_seq);

        if (!empty($source['messages']) && is_array($source['messages']) && class_exists('PAXdesign_Message_Store')) {
            PAXdesign_Message_Store::migrate_legacy($canonical_id, $source['messages'], 'team');
        }
        if ($source_id !== $canonical_id && class_exists('PAXdesign_Message_Store')) {
            PAXdesign_Message_Store::reassign_session($source_id, $canonical_id, 'team');
        }

        $target['messages'] = array();
    }

    /**
     * @param mixed $raw
     * @return array<int, float>
     */
    private static function sanitize_waveform($raw) {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        foreach ($raw as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $out[] = round(max(0.05, min(1, (float) $value)), 3);
            if (count($out) >= 96) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<int, float>
     */
    private static function normalize_waveform($raw) {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : array();
        }
        return self::sanitize_waveform($raw);
    }
}
