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
    public static function open_conversation($current_user_id, $other_user_id) {
        $current_user_id = absint($current_user_id);
        $other_user_id   = absint($other_user_id);

        if ($current_user_id <= 0 || $other_user_id <= 0 || $current_user_id === $other_user_id) {
            return array('error' => 'invalid_participants');
        }

        $other = get_user_by('id', $other_user_id);
        if (!$other) {
            return array('error' => 'user_not_found');
        }

        $conv_id = self::conversation_id($current_user_id, $other_user_id);
        $all     = self::all_conversations();

        if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
            $all[$conv_id] = array(
                'participants' => array($current_user_id, $other_user_id),
                'messages'     => array(),
                'read_seq'     => array(),
                'seq'          => 0,
                'updated_at'   => gmdate('Y-m-d H:i:s'),
            );
            self::save_conversations($all);
        }

        return array(
            'conversation_id' => $conv_id,
            'session'         => self::format_session_row($conv_id, $all[$conv_id], $current_user_id),
        );
    }

    /**
     * @param int $current_user_id
     * @return array{sessions: array<int, array<string, mixed>>, live_count: int}
     */
    public static function list_sessions_for_user($current_user_id) {
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
            $sessions[] = self::format_session_row($conv_id, $conv, $current_user_id);
        }

        usort($sessions, function ($a, $b) {
            return strcmp((string) $b['updated_at'], (string) $a['updated_at']);
        });

        return array(
            'sessions'   => $sessions,
            'live_count' => 0,
        );
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

        $messages = isset($conv['messages']) && is_array($conv['messages']) ? $conv['messages'] : array();
        $seq      = isset($conv['seq']) ? absint($conv['seq']) : 0;
        $since    = absint($since);

        if ($full || $since <= 0) {
            $out_messages = $messages;
        } else {
            $out_messages = array_values(array_filter($messages, function ($msg) use ($since) {
                return isset($msg['id']) && absint($msg['id']) > $since;
            }));
        }

        $other         = self::other_participant($conv, $current_user_id);
        $self_identity = PAXdesign_Chat_Live::resolve_employee_identity($current_user_id);
        $other_identity = $other ? PAXdesign_Chat_Live::resolve_employee_identity((int) $other->ID) : null;
        $read_seq      = self::read_seq_for_user($conv, $current_user_id);
        $other_read    = $other ? self::read_seq_for_user($conv, (int) $other->ID) : 0;

        return array(
            'session_id'       => $conv_id,
            'handler'          => self::HANDLER,
            'handler_label'    => 'Team',
            'customer_name'    => $other_identity ? $other_identity['name'] : ($other ? $other->display_name : 'Team'),
            'admin_name'       => $self_identity ? $self_identity['name'] : wp_get_current_user()->display_name,
            'other_user_id'    => $other ? (int) $other->ID : 0,
            'assigned_agent'   => $other_identity,
            'detected_service' => 'Team-Nachricht',
            'updated_at'       => isset($conv['updated_at']) ? (string) $conv['updated_at'] : '',
            'session_rating'   => 0,
            'seq'              => $seq,
            'last_read_seq'    => $read_seq,
            'other_read_seq'   => $other_read,
            'messages'         => array_map(array(__CLASS__, 'format_message'), $out_messages),
            'user_typing'      => false,
        );
    }

    /**
     * @param string $conv_id
     * @param int    $current_user_id
     * @param string $content
     * @return array<string, mixed>|WP_Error
     */
    public static function send_message($conv_id, $current_user_id, $content) {
        $conv_id         = sanitize_text_field($conv_id);
        $current_user_id = absint($current_user_id);
        $content         = trim(wp_strip_all_tags((string) $content));

        if ($content === '') {
            return new WP_Error('pax_team_empty', 'Message cannot be empty', array('status' => 400));
        }

        $all = self::all_conversations();
        if (!isset($all[$conv_id]) || !is_array($all[$conv_id])) {
            return new WP_Error('pax_team_not_found', 'Conversation not found', array('status' => 404));
        }

        $conv = $all[$conv_id];
        if (!self::user_in_conversation($conv, $current_user_id)) {
            return new WP_Error('pax_team_forbidden', 'Not a participant', array('status' => 403));
        }

        if (!isset($conv['messages']) || !is_array($conv['messages'])) {
            $conv['messages'] = array();
        }
        if (!isset($conv['read_seq']) || !is_array($conv['read_seq'])) {
            $conv['read_seq'] = array();
        }

        $seq = isset($conv['seq']) ? absint($conv['seq']) : 0;
        $seq++;
        $msg_id = $seq;

        $identity = PAXdesign_Chat_Live::resolve_employee_identity($current_user_id);

        $conv['messages'][] = array(
            'id'        => $msg_id,
            'sender_id' => $current_user_id,
            'content'   => $content,
            'ts'        => time(),
            'role'      => 'admin',
        );
        $conv['seq']        = $seq;
        $conv['updated_at'] = gmdate('Y-m-d H:i:s');
        $conv['read_seq'][(string) $current_user_id] = $seq;

        $all[$conv_id] = $conv;
        self::save_conversations($all);

        $formatted = self::format_message(end($conv['messages']));

        $recipient = self::other_participant($conv, $current_user_id);
        if ($recipient && class_exists('PAXdesign_APNS')) {
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
        }

        return array(
            'ok'            => true,
            'last_read_seq' => isset($conv['read_seq'][$key]) ? absint($conv['read_seq'][$key]) : 0,
            'seq'           => isset($conv['seq']) ? absint($conv['seq']) : 0,
        );
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
        $messages = isset($conv['messages']) && is_array($conv['messages']) ? $conv['messages'] : array();
        $last     = !empty($messages) ? end($messages) : null;
        $other    = self::other_participant($conv, $current_user_id);

        $self_identity  = PAXdesign_Chat_Live::resolve_employee_identity($current_user_id);
        $other_identity = $other ? PAXdesign_Chat_Live::resolve_employee_identity((int) $other->ID) : null;

        $last_preview = '';
        $last_role    = 'admin';
        if (is_array($last)) {
            $last_preview = isset($last['content']) ? (string) $last['content'] : '';
            if (isset($last['sender_id']) && absint($last['sender_id']) !== absint($current_user_id)) {
                $last_role = 'user';
            }
        }

        $seq      = isset($conv['seq']) ? absint($conv['seq']) : 0;
        $read_seq = self::read_seq_for_user($conv, $current_user_id);

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
            'updated_at'       => isset($conv['updated_at']) ? (string) $conv['updated_at'] : '',
            'message_count'    => count($messages),
            'seq'              => $seq,
            'last_read_seq'    => $read_seq,
            'last_preview'     => $last_preview,
            'last_role'        => $last_role,
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
            'id'            => isset($msg['id']) ? absint($msg['id']) : 0,
            'role'          => $is_self ? 'admin' : 'user',
            'content'       => isset($msg['content']) ? (string) $msg['content'] : '',
            'ts'            => isset($msg['ts']) ? absint($msg['ts']) : time(),
            'image_url'     => '',
            'reply_to'      => null,
            'reaction'      => null,
            'sender_id'     => $sender_id,
            'sender_name'   => $identity ? $identity['name'] : '',
            'sender_avatar' => $identity ? $identity['avatar'] : '',
            'sender_role'   => $identity ? $identity['role'] : '',
            'sender'        => $identity ? $identity['name'] : '',
        );
    }
}
