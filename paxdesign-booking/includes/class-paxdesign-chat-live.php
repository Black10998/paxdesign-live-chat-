<?php
/**
 * Live admin takeover for KI chat sessions (REST polling).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Chat_Live {

    const ACTIVE_MINUTES = 45;
    const POLL_ROLES     = array('user', 'assistant', 'admin', 'system');
    const HANDLER_AI     = 'ai';
    const HANDLER_LIVE   = 'live_request';
    const HANDLER_ADMIN  = 'admin';
    const HANDLER_CLOSED = 'closed';

    const DEFAULT_AGENT_NAME   = 'Ahmad Alkhalaf';
    const DEFAULT_AGENT_AVATAR   = 'https://paxdesign.at/wp-content/uploads/2026/06/unnamed.jpg';
    const DEFAULT_AGENT_ROLE     = 'Development Manager';
    const DEFAULT_AGENT_TAGLINE  = 'Owner & Founder · PAXdesign';
    const DEFAULT_AGENT_BIO      = 'I am the owner and founder of PAXdesign. As Development Manager I personally support you with web design, AI chatbots, booking systems, and digital solutions.';
    const ALLOWED_REACTIONS    = array('like', 'dislike');

    private static $instance = null;

    public static function get_agent_display_name() {
        $name = trim((string) get_option('paxdesign_live_chat_agent_name', self::DEFAULT_AGENT_NAME));
        return $name !== '' ? $name : self::DEFAULT_AGENT_NAME;
    }

    public static function get_agent_avatar_url() {
        $url = trim((string) get_option('paxdesign_live_chat_agent_avatar', self::DEFAULT_AGENT_AVATAR));
        return $url !== '' ? esc_url_raw($url) : self::DEFAULT_AGENT_AVATAR;
    }

    public static function get_agent_role() {
        $role = trim((string) get_option('paxdesign_live_chat_agent_role', self::DEFAULT_AGENT_ROLE));
        return $role !== '' ? $role : self::DEFAULT_AGENT_ROLE;
    }

    public static function get_agent_tagline() {
        $tagline = trim((string) get_option('paxdesign_live_chat_agent_tagline', self::DEFAULT_AGENT_TAGLINE));
        return $tagline !== '' ? $tagline : self::DEFAULT_AGENT_TAGLINE;
    }

    public static function get_agent_bio() {
        $bio = trim((string) get_option('paxdesign_live_chat_agent_bio', self::DEFAULT_AGENT_BIO));
        return $bio !== '' ? $bio : self::DEFAULT_AGENT_BIO;
    }

    public static function get_agent_public_config() {
        return array(
            'name'    => self::get_agent_display_name(),
            'avatar'  => self::get_agent_avatar_url(),
            'role'    => self::get_agent_role(),
            'tagline' => self::get_agent_tagline(),
            'bio'     => self::get_agent_bio(),
        );
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_paxdesign_chat_poll', array($this, 'handle_poll'));
        add_action('wp_ajax_nopriv_paxdesign_chat_poll', array($this, 'handle_poll'));
        add_action('wp_ajax_paxdesign_chat_live_user_send', array($this, 'handle_user_send'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_user_send', array($this, 'handle_user_send'));
        add_action('wp_ajax_paxdesign_chat_live_request', array($this, 'handle_live_request'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_request', array($this, 'handle_live_request'));
        add_action('wp_ajax_paxdesign_chat_live_list', array($this, 'handle_live_list'));
        add_action('wp_ajax_paxdesign_chat_live_session', array($this, 'handle_live_session'));
        add_action('wp_ajax_paxdesign_chat_live_takeover', array($this, 'handle_takeover'));
        add_action('wp_ajax_paxdesign_chat_live_release', array($this, 'handle_release'));
        add_action('wp_ajax_paxdesign_chat_live_close', array($this, 'handle_close'));
        add_action('wp_ajax_paxdesign_chat_live_reopen', array($this, 'handle_reopen'));
        add_action('wp_ajax_paxdesign_chat_live_archive', array($this, 'handle_archive'));
        add_action('wp_ajax_paxdesign_chat_live_delete', array($this, 'handle_delete'));
        add_action('wp_ajax_paxdesign_chat_live_admin_send', array($this, 'handle_admin_send'));
        add_action('wp_ajax_paxdesign_chat_live_admin_image', array($this, 'handle_admin_image'));
        add_action('wp_ajax_paxdesign_chat_live_admin_typing', array($this, 'handle_admin_typing'));
        add_action('wp_ajax_paxdesign_chat_live_user_typing', array($this, 'handle_user_typing'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_user_typing', array($this, 'handle_user_typing'));
        add_action('wp_ajax_paxdesign_chat_live_reaction', array($this, 'handle_message_reaction'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_reaction', array($this, 'handle_message_reaction'));
        add_action('wp_ajax_paxdesign_chat_live_customer_close', array($this, 'handle_customer_close'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_customer_close', array($this, 'handle_customer_close'));
        add_action('wp_ajax_paxdesign_chat_live_rating', array($this, 'handle_session_rating'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_rating', array($this, 'handle_session_rating'));
        add_action('wp_ajax_paxdesign_chat_live_admin_suggestions', array($this, 'handle_admin_suggestions'));
        add_action('wp_ajax_paxdesign_chat_customer_history_list', array($this, 'handle_customer_history_list'));
        add_action('wp_ajax_nopriv_paxdesign_chat_customer_history_list', array($this, 'handle_customer_history_list'));
        add_action('wp_ajax_paxdesign_chat_customer_history_session', array($this, 'handle_customer_history_session'));
        add_action('wp_ajax_nopriv_paxdesign_chat_customer_history_session', array($this, 'handle_customer_history_session'));
    }

    /** Emails that must always receive Live-Agent alerts. */
    const REQUIRED_NOTIFY_EMAILS = array(
        'info@paxdesign.at',
        'al.kahalaf.ahmad@gmail.com',
    );

    /**
     * Ready-made admin quick replies (DE + AR).
     *
     * @return array<int, array{label: string, text: string, lang: string}>
     */
    public static function get_admin_quick_replies() {
        return array(
            array('label' => 'DE · Hallo', 'text' => 'Hallo, wie kann ich Ihnen helfen?', 'lang' => 'de'),
            array('label' => 'DE · Moment', 'text' => 'Einen Moment bitte, ich prüfe das für Sie.', 'lang' => 'de'),
            array('label' => 'DE · Details', 'text' => 'Können Sie mir bitte mehr Details schicken?', 'lang' => 'de'),
            array('label' => 'AR · مرحباً', 'text' => 'مرحباً، كيف يمكنني مساعدتك؟', 'lang' => 'ar'),
            array('label' => 'AR · لحظة', 'text' => 'لحظة من فضلك، سأتحقق من ذلك لك.', 'lang' => 'ar'),
            array('label' => 'AR · تفاصيل', 'text' => 'هل يمكنك إرسال المزيد من التفاصيل؟', 'lang' => 'ar'),
        );
    }

    /**
     * @param object|null $row
     */
    private function session_customer_label($row) {
        if (!$row) {
            return 'Kunde';
        }
        $name = isset($row->customer_name) ? trim((string) $row->customer_name) : '';
        return $name !== '' ? $name : 'Kunde';
    }

    /**
     * @param object $row
     * @return array<string, mixed>
     */
    private function session_public_meta($row) {
        return array(
            'customer_name'  => isset($row->customer_name) ? (string) $row->customer_name : '',
            'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
        );
    }

    private function typing_transient_key($session_id, $who) {
        return 'pax_live_type_' . md5($session_id . '_' . $who);
    }

    private function mark_typing($session_id, $who) {
        set_transient($this->typing_transient_key($session_id, $who), 1, 2);
    }

    private function is_typing($session_id, $who) {
        return (bool) get_transient($this->typing_transient_key($session_id, $who));
    }

    private function clear_typing($session_id, $who) {
        delete_transient($this->typing_transient_key($session_id, $who));
    }

    public static function upgrade_schema() {
        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();

        $columns = array(
            array('handler', "varchar(20) NOT NULL DEFAULT 'ai'", 'message_count'),
            array('admin_user_id', 'bigint(20) unsigned NOT NULL DEFAULT 0', 'handler'),
            array('admin_name', "varchar(120) NOT NULL DEFAULT ''", 'admin_user_id'),
            array('message_seq', 'int(10) unsigned NOT NULL DEFAULT 0', 'admin_name'),
            array('customer_name', "varchar(120) NOT NULL DEFAULT ''", 'message_seq'),
            array('session_rating', 'tinyint(3) unsigned NOT NULL DEFAULT 0', 'customer_name'),
            array('device_token_hash', "varchar(64) NOT NULL DEFAULT ''", 'session_rating'),
            array('last_preview', "varchar(160) NOT NULL DEFAULT ''", 'device_token_hash'),
        );

        foreach ($columns as $col) {
            paxdesign_booking_add_column_if_missing($table, $col[0], $col[1], $col[2]);
        }
    }

    /**
     * @param string $token Raw device token from the customer browser.
     */
    public function sanitize_device_token($token) {
        $token = sanitize_text_field($token);
        if ($token === '' || !preg_match('/^paxdev_[a-z0-9]{24,128}$/i', $token)) {
            return '';
        }
        return $token;
    }

    /**
     * @param string $token
     */
    private function hash_device_token($token) {
        return hash_hmac('sha256', $token, wp_salt('auth'));
    }

    /**
     * Read device token from the current AJAX request.
     */
    private function device_token_from_request() {
        return $this->sanitize_device_token(
            isset($_POST['device_token']) ? wp_unslash($_POST['device_token']) : ''
        );
    }

    /**
     * Bind a session to the caller's device token (first writer wins).
     *
     * @param string $session_id
     * @param string $device_token
     */
    public function bind_session_to_device($session_id, $device_token) {
        if ($session_id === '' || $device_token === '') {
            return false;
        }

        self::upgrade_schema();

        $hash = $this->hash_device_token($device_token);
        $row  = $this->get_session_row($session_id);
        if (!$row) {
            return false;
        }

        $existing = isset($row->device_token_hash) ? (string) $row->device_token_hash : '';
        if ($existing !== '') {
            return hash_equals($existing, $hash);
        }

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array('device_token_hash' => $hash),
            array('id' => (int) $row->id),
            array('%s'),
            array('%d')
        );

        return true;
    }

    /**
     * Bind session from POST device_token when present.
     *
     * @param string $session_id
     */
    public function bind_device_from_request($session_id) {
        $token = $this->device_token_from_request();
        if ($token === '') {
            return false;
        }
        return $this->bind_session_to_device($session_id, $token);
    }

    /**
     * Ensure the session belongs to the supplied device token.
     *
     * @param string $session_id
     * @param string $device_token
     */
    public function verify_session_ownership($session_id, $device_token) {
        if ($session_id === '' || $device_token === '') {
            return false;
        }

        self::upgrade_schema();

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return false;
        }

        $existing = isset($row->device_token_hash) ? (string) $row->device_token_hash : '';
        if ($existing === '') {
            return $this->bind_session_to_device($session_id, $device_token);
        }

        return hash_equals($existing, $this->hash_device_token($device_token));
    }

    /**
     * @param string $messages_json
     */
    private function history_preview_from_messages($messages_json) {
        $messages = $this->sort_messages($this->decode_messages($messages_json));
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            if (!is_array($msg)) {
                continue;
            }
            $role = isset($msg['role']) ? $msg['role'] : '';
            if (!in_array($role, array('user', 'assistant', 'admin'), true)) {
                continue;
            }
            $content = isset($msg['content']) ? trim((string) $msg['content']) : '';
            if ($content === '' && !empty($msg['image_url'])) {
                return __('Bild', 'paxdesign-booking');
            }
            if ($content !== '') {
                if (mb_strlen($content) > 120) {
                    return mb_substr($content, 0, 120) . '…';
                }
                return $content;
            }
        }
        return '';
    }

    /**
     * @param string $session_id
     */
    private function persist_session_last_preview($session_id) {
        $row = $this->get_session_row($session_id);
        if (!$row) {
            return '';
        }

        $preview = $this->history_preview_from_messages($row->messages);
        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array('last_preview' => $preview),
            array('id' => (int) $row->id),
            array('%s'),
            array('%d')
        );

        return $preview;
    }

    private function verify_chat_nonce() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce && wp_verify_nonce($nonce, 'paxdesign_chat_nonce')) {
            return true;
        }
        if ($nonce && wp_verify_nonce($nonce, 'paxdesign_admin_nonce') && current_user_can('manage_options')) {
            return true;
        }
        return false;
    }

    private function verify_admin_nonce() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'), 403);
        }
    }

    private function sanitize_session_id($session_id) {
        $session_id = sanitize_text_field($session_id);
        if ($session_id === '' || !preg_match('/^pax_[a-z0-9_]+$/i', $session_id)) {
            return '';
        }
        return $session_id;
    }

    public function get_session_row($session_id) {
        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE session_id = %s LIMIT 1", $session_id)
        );
    }

    public function get_handler($session_id) {
        $row = $this->get_session_row($session_id);
        if (!$row || empty($row->handler)) {
            return self::HANDLER_AI;
        }
        return sanitize_text_field($row->handler);
    }

    public function is_admin_handler($session_id) {
        return $this->get_handler($session_id) === self::HANDLER_ADMIN;
    }

    public function is_ai_blocked($session_id) {
        return in_array($this->get_handler($session_id), array(
            self::HANDLER_ADMIN,
            self::HANDLER_LIVE,
            self::HANDLER_CLOSED,
        ), true);
    }

    public function is_human_queue($session_id) {
        return in_array($this->get_handler($session_id), array(
            self::HANDLER_ADMIN,
            self::HANDLER_LIVE,
        ), true);
    }

    public static function handler_label($handler) {
        switch ($handler) {
            case self::HANDLER_LIVE:
                return 'Live-Anfrage';
            case self::HANDLER_ADMIN:
                return self::get_agent_display_name();
            case self::HANDLER_CLOSED:
                return 'Geschlossen';
            default:
                return 'KI aktiv';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function decode_messages($json) {
        $messages = json_decode($json, true);
        return is_array($messages) ? $messages : array();
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function encode_messages($messages) {
        $messages = $this->sort_messages($messages);
        $json = wp_json_encode(array_values($messages));
        return $json !== false ? $json : '[]';
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function sort_messages($messages) {
        usort($messages, function ($a, $b) {
            $aid = isset($a['id']) ? (int) $a['id'] : 0;
            $bid = isset($b['id']) ? (int) $b['id'] : 0;
            if ($aid === $bid) {
                return 0;
            }
            return $aid - $bid;
        });
        return array_values($messages);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function next_message_id($messages) {
        $max = 0;
        foreach ($messages as $msg) {
            if (isset($msg['id']) && (int) $msg['id'] > $max) {
                $max = (int) $msg['id'];
            }
        }
        return $max + 1;
    }

    private function set_handler($session_id, $handler, $extra = array()) {
        global $wpdb;
        $row = $this->get_session_row($session_id);
        if (!$row) {
            return false;
        }

        $data = array_merge(array(
            'handler'    => sanitize_text_field($handler),
            'updated_at' => current_time('mysql'),
        ), $extra);

        return $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            $data,
            array('id' => (int) $row->id)
        ) !== false;
    }

    public function ensure_session($session_id, $messages = array()) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return null;
        }

        $row = $this->get_session_row($session_id);
        if ($row) {
            return $row;
        }

        PAXdesign_Chat_Log::create_table();
        self::upgrade_schema();

        if (!is_array($messages)) {
            $messages = array();
        }

        if (empty($messages)) {
            $messages = array(
                array(
                    'id'      => 1,
                    'role'    => 'system',
                    'content' => 'Chat-Session gestartet.',
                    'ts'      => time(),
                ),
            );
        }

        PAXdesign_Chat_Log::get_instance()->save_session(array(
            'session_id'           => $session_id,
            'messages'             => $messages,
            'detected_service'     => '',
            'booking_triggered'    => false,
            'consultation_started' => true,
        ));

        return $this->get_session_row($session_id);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function persist_client_messages($session_id, $messages) {
        if (empty($messages)) {
            return;
        }

        PAXdesign_Chat_Log::get_instance()->save_session(array(
            'session_id'           => $session_id,
            'messages'             => $messages,
            'detected_service'     => '',
            'consultation_started' => true,
        ));
    }

    private function active_since_sql() {
        return wp_date(
            'Y-m-d H:i:s',
            strtotime('-' . self::ACTIVE_MINUTES . ' minutes', current_time('timestamp'))
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function append_message($session_id, $role, $content, $extra = array()) {
        global $wpdb;

        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '' || !in_array($role, self::POLL_ROLES, true)) {
            return null;
        }

        $content = sanitize_textarea_field($content);
        $has_image = !empty($extra['image_url']);
        if ($content === '' && !$has_image) {
            return null;
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            $row = $this->ensure_session($session_id);
        }
        if (!$row) {
            return null;
        }

        $messages = $this->decode_messages($row->messages);
        $id       = $this->next_message_id($messages);
        $entry    = array(
            'id'      => $id,
            'role'    => $role,
            'content' => $content,
            'ts'      => time(),
        );
        if (!empty($extra['reply_to'])) {
            $reply_to = (int) $extra['reply_to'];
            if ($reply_to > 0) {
                $entry['reply_to'] = $reply_to;
            }
        }
        if ($has_image) {
            $entry['image_url']        = esc_url_raw($extra['image_url']);
            $entry['attachment_type']  = 'image';
        }
        $messages[] = $entry;
        $messages   = $this->sort_messages($messages);

        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'messages'      => $this->encode_messages($messages),
                'message_seq'   => $id,
                'message_count' => count($messages),
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%d', '%s'),
            array('%d')
        );

        $handler = isset($row->handler) ? (string) $row->handler : self::HANDLER_AI;
        $preview = $content !== '' ? wp_html_excerpt($content, 120, '…') : '';
        do_action('paxdesign_session_sync', $session_id, array(
            'is_new'    => false,
            'seq'       => $id,
            'preview'   => $preview,
            'last_role' => $role,
            'handler'   => $handler,
            'service'   => isset($row->detected_service) ? (string) $row->detected_service : '',
        ));

        return $entry;
    }

    public function handle_poll() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Invalid session'), 400);
        }

        $device_token = $this->device_token_from_request();
        if ($device_token !== '') {
            $this->bind_session_to_device($session_id, $device_token);
        }

        $since = isset($_POST['since']) ? (int) $_POST['since'] : 0;
        $full  = isset($_POST['full']) && wp_unslash($_POST['full']) === '1';
        $data  = $this->get_poll_data($session_id, $since, $full);
        if (is_wp_error($data)) {
            $status = 400;
            $error_data = $data->get_error_data();
            if (is_array($error_data) && !empty($error_data['status'])) {
                $status = (int) $error_data['status'];
            }
            wp_send_json_error(array('message' => $data->get_error_message()), $status);
        }

        wp_send_json_success($data);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, string>
     */
    private function extract_message_reactions($messages) {
        $reactions = array();
        foreach ($messages as $msg) {
            if (!is_array($msg) || empty($msg['id']) || empty($msg['reaction'])) {
                continue;
            }
            $key = (int) $msg['id'];
            $reaction = sanitize_text_field($msg['reaction']);
            if (in_array($reaction, self::ALLOWED_REACTIONS, true)) {
                $reactions[$key] = $reaction;
            }
        }
        return $reactions;
    }

    public function handle_message_reaction() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        $reaction   = isset($_POST['reaction']) ? sanitize_text_field(wp_unslash($_POST['reaction'])) : '';

        if ($session_id === '' || $message_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid payload'), 400);
        }

        if ($reaction !== '' && !in_array($reaction, self::ALLOWED_REACTIONS, true)) {
            wp_send_json_error(array('message' => 'Invalid reaction'), 400);
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            wp_send_json_error(array('message' => 'Session not found'), 404);
        }

        $messages = $this->sort_messages($this->decode_messages($row->messages));
        $found    = false;
        $target_role = '';

        foreach ($messages as $idx => $msg) {
            if (!is_array($msg) || (int) ($msg['id'] ?? 0) !== $message_id) {
                continue;
            }
            $target_role = isset($msg['role']) ? $msg['role'] : '';
            if ($target_role !== 'admin') {
                wp_send_json_error(array('message' => 'Reactions only on staff messages'), 409);
            }
            if ($reaction === '') {
                unset($messages[$idx]['reaction']);
            } else {
                $messages[$idx]['reaction'] = $reaction;
            }
            $found = true;
            break;
        }

        if (!$found) {
            wp_send_json_error(array('message' => 'Message not found'), 404);
        }

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'messages'   => $this->encode_messages($messages),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'message_id' => $message_id,
            'reaction'   => $reaction,
        ));
    }

    public function handle_admin_typing() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '' || !$this->is_admin_handler($session_id)) {
            wp_send_json_success(array('ok' => false));
        }

        $stop = isset($_POST['stop']) && (string) wp_unslash($_POST['stop']) === '1';
        if ($stop) {
            $this->clear_typing($session_id, 'admin');
        } else {
            $this->mark_typing($session_id, 'admin');
        }
        wp_send_json_success(array('ok' => true));
    }

    public function handle_user_typing() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '' || !$this->is_human_queue($session_id)) {
            wp_send_json_success(array('ok' => false));
        }

        $stop = isset($_POST['stop']) && (string) wp_unslash($_POST['stop']) === '1';
        if ($stop) {
            $this->clear_typing($session_id, 'user');
        } else {
            $this->mark_typing($session_id, 'user');
        }
        wp_send_json_success(array('ok' => true));
    }

    public function handle_live_request() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        self::upgrade_schema();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Invalid session'), 400);
        }

        $messages_raw = isset($_POST['messages']) ? wp_unslash($_POST['messages']) : '';
        $messages     = json_decode($messages_raw, true);
        if (is_array($messages) && !empty($messages)) {
            $this->persist_client_messages($session_id, $messages);
        }

        $row = $this->ensure_session($session_id, is_array($messages) ? $messages : array());
        if (!$row) {
            wp_send_json_error(array('message' => 'Session konnte nicht erstellt werden.'), 500);
        }

        $handler = $this->get_handler($session_id);
        if ($handler === self::HANDLER_ADMIN) {
            wp_send_json_error(array('message' => 'Admin ist bereits aktiv.'), 409);
        }
        if ($handler === self::HANDLER_CLOSED) {
            wp_send_json_error(array('message' => 'Chat geschlossen.'), 409);
        }
        if ($handler === self::HANDLER_LIVE) {
            wp_send_json_success(array(
                'handler'  => self::HANDLER_LIVE,
                'messages' => array(),
            ));
        }

        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $customer_name = isset($_POST['customer_name'])
            ? sanitize_text_field(wp_unslash($_POST['customer_name']))
            : '';
        $customer_name = trim(preg_replace('/\s+/', ' ', $customer_name));
        if (strlen($customer_name) > 120) {
            $customer_name = substr($customer_name, 0, 120);
        }
        if (strlen($customer_name) < 2) {
            wp_send_json_error(array('message' => 'Bitte geben Sie Ihren Namen ein (mindestens 2 Zeichen).'), 400);
        }

        global $wpdb;
        $update = array(
            'handler'       => self::HANDLER_LIVE,
            'updated_at'    => current_time('mysql'),
            'customer_name' => $customer_name,
        );
        if ($topic !== '') {
            $update['detected_service'] = $topic;
        }

        $updated = $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            $update,
            array('id' => (int) $row->id)
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Status konnte nicht gespeichert werden.'), 500);
        }

        $thanks = $this->append_message(
            $session_id,
            'assistant',
            'Danke. Ich leite Sie jetzt an einen PAXDesign-Mitarbeiter weiter.'
        );
        $notice = $this->append_message(
            $session_id,
            'system',
            'Ein PAXDesign-Mitarbeiter wurde informiert. Bitte bleiben Sie kurz im Chat.'
        );

        $row = $this->get_session_row($session_id);
        $this->notify_live_agent_request($session_id, $topic, $row);

        wp_send_json_success(array(
            'handler'  => self::HANDLER_LIVE,
            'messages' => array_values(array_filter(array($thanks, $notice))),
        ));
    }

    public function handle_user_send() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $content = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if ($session_id === '' || $content === '') {
            wp_send_json_error(array('message' => 'Invalid payload'), 400);
        }

        $handler = $this->get_handler($session_id);
        if ($handler === self::HANDLER_CLOSED) {
            wp_send_json_error(array('message' => 'Chat geschlossen.'), 409);
        }
        if (!$this->is_human_queue($session_id)) {
            wp_send_json_error(array('message' => 'Not in human mode'), 409);
        }

        $this->ensure_session($session_id);

        $reply_to = isset($_POST['reply_to']) ? (int) $_POST['reply_to'] : 0;
        $extra    = $reply_to > 0 ? array('reply_to' => $reply_to) : array();
        $entry    = $this->append_message($session_id, 'user', $content, $extra);
        if (!$entry) {
            wp_send_json_error(array('message' => 'Could not save'), 500);
        }

        $this->clear_typing($session_id, 'user');

        if (class_exists('PAXdesign_Live_Chat_PWA')) {
            PAXdesign_Live_Chat_PWA::notify_new_customer_message($session_id, $content);
        }

        wp_send_json_success(array('message' => $entry));
    }

    public function handle_live_list() {
        $this->verify_admin_nonce();

        $data = $this->get_live_list_data();
        if (is_wp_error($data)) {
            $status = 500;
            $error_data = $data->get_error_data();
            if (is_array($error_data) && !empty($error_data['status'])) {
                $status = (int) $error_data['status'];
            }
            wp_send_json_error(array('message' => $data->get_error_message()), $status);
        }

        wp_send_json_success($data);
    }

    public function handle_live_session() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $data = $this->get_session_detail_data($session_id);
        if (is_wp_error($data)) {
            $status = 404;
            $error_data = $data->get_error_data();
            if (is_array($error_data) && !empty($error_data['status'])) {
                $status = (int) $error_data['status'];
            }
            wp_send_json_error(array('message' => $data->get_error_message()), $status);
        }

        wp_send_json_success($data);
    }

    public function handle_customer_history_list() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        self::upgrade_schema();

        $device_token = $this->device_token_from_request();
        if ($device_token === '') {
            wp_send_json_error(array('message' => 'Invalid device token'), 400);
        }

        $hash  = $this->hash_device_token($device_token);
        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT session_id, customer_name, updated_at, started_at, message_count, session_rating, last_preview
                 FROM $table
                 WHERE device_token_hash = %s AND handler = %s AND message_count > 0
                 ORDER BY updated_at DESC
                 LIMIT 50",
                $hash,
                self::HANDLER_CLOSED
            )
        );

        $sessions = array();
        foreach ((array) $rows as $row) {
            $preview = isset($row->last_preview) ? trim((string) $row->last_preview) : '';
            if ($preview === '' && (int) $row->message_count > 0) {
                $preview = sprintf(
                    /* translators: %d: message count */
                    __('%d Nachrichten', 'paxdesign-booking'),
                    (int) $row->message_count
                );
            }
            $sessions[] = array(
                'session_id'     => $row->session_id,
                'customer_name'  => isset($row->customer_name) ? (string) $row->customer_name : '',
                'updated_at'     => $row->updated_at,
                'started_at'     => $row->started_at,
                'message_count'  => (int) $row->message_count,
                'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
                'preview'        => $preview,
            );
        }

        wp_send_json_success(array('sessions' => $sessions));
    }

    public function handle_customer_history_session() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        self::upgrade_schema();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $device_token = $this->device_token_from_request();

        if ($session_id === '' || $device_token === '') {
            wp_send_json_error(array('message' => 'Invalid payload'), 400);
        }

        if (!$this->verify_session_ownership($session_id, $device_token)) {
            wp_send_json_error(array('message' => 'Access denied'), 403);
        }

        $row = $this->get_session_row($session_id);
        if (!$row || $this->get_handler($session_id) !== self::HANDLER_CLOSED) {
            wp_send_json_error(array('message' => 'Not found'), 404);
        }

        wp_send_json_success(array(
            'session_id'     => $row->session_id,
            'customer_name'  => isset($row->customer_name) ? (string) $row->customer_name : '',
            'updated_at'     => $row->updated_at,
            'started_at'     => $row->started_at,
            'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
            'messages'       => $this->sort_messages($this->decode_messages($row->messages)),
        ));
    }

    public function handle_customer_close() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        self::upgrade_schema();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Invalid session'), 400);
        }

        $device_token = $this->device_token_from_request();
        if ($device_token !== '' && !$this->verify_session_ownership($session_id, $device_token)) {
            wp_send_json_error(array('message' => 'Access denied'), 403);
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            wp_send_json_error(array('message' => 'Session not found'), 404);
        }

        if ($device_token !== '') {
            $this->bind_session_to_device($session_id, $device_token);
        }

        if ($this->get_handler($session_id) === self::HANDLER_CLOSED) {
            wp_send_json_success(array(
                'handler'         => self::HANDLER_CLOSED,
                'session_rating'  => isset($row->session_rating) ? (int) $row->session_rating : 0,
                'messages'        => array(),
            ));
        }

        $existing_messages = $this->sort_messages($this->decode_messages($row->messages));
        foreach (array_reverse($existing_messages) as $msg) {
            if (!is_array($msg) || ($msg['role'] ?? '') !== 'system') {
                continue;
            }
            if (($msg['content'] ?? '') === 'Der Kunde hat das Gespräch beendet.') {
                global $wpdb;
                $wpdb->update(
                    PAXdesign_Chat_Log::table_name(),
                    array(
                        'handler'       => self::HANDLER_CLOSED,
                        'admin_user_id' => 0,
                        'admin_name'    => '',
                        'updated_at'    => current_time('mysql'),
                    ),
                    array('id' => (int) $row->id),
                    array('%s', '%d', '%s', '%s'),
                    array('%d')
                );
                wp_send_json_success(array(
                    'handler'        => self::HANDLER_CLOSED,
                    'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
                    'message'        => $msg,
                ));
            }
            break;
        }

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'       => self::HANDLER_CLOSED,
                'admin_user_id' => 0,
                'admin_name'    => '',
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        $entry = $this->append_message(
            $session_id,
            'system',
            'Der Kunde hat das Gespräch beendet.'
        );

        $this->persist_session_last_preview($session_id);

        wp_send_json_success(array(
            'handler'        => self::HANDLER_CLOSED,
            'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
            'message'        => $entry,
        ));
    }

    public function handle_session_rating() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        self::upgrade_schema();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $feedback = isset($_POST['feedback']) ? sanitize_text_field(wp_unslash($_POST['feedback'])) : '';
        $rating   = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;

        if ($feedback === 'like') {
            $rating = 5;
        } elseif ($feedback === 'dislike') {
            $rating = 1;
        }

        if ($session_id === '' || ($rating !== 1 && $rating !== 5)) {
            wp_send_json_error(array('message' => 'Invalid payload'), 400);
        }

        $device_token = $this->device_token_from_request();
        if ($device_token !== '' && !$this->verify_session_ownership($session_id, $device_token)) {
            wp_send_json_error(array('message' => 'Access denied'), 403);
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            wp_send_json_error(array('message' => 'Session not found'), 404);
        }

        if (!empty($row->session_rating)) {
            wp_send_json_error(array('message' => 'Already rated'), 409);
        }

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'session_rating' => $rating,
                'updated_at'     => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%d', '%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'rating'   => $rating,
            'feedback' => $rating === 5 ? 'like' : 'dislike',
        ));
    }

    public function handle_takeover() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            wp_send_json_error(array('message' => 'Session nicht gefunden.'), 404);
        }

        if ($this->get_handler($session_id) === self::HANDLER_CLOSED) {
            wp_send_json_error(array('message' => 'Chat ist geschlossen.'), 409);
        }

        $user       = wp_get_current_user();
        $admin_name = self::get_agent_display_name();

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'       => self::HANDLER_ADMIN,
                'admin_user_id' => (int) $user->ID,
                'admin_name'    => sanitize_text_field($admin_name),
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        $notice = sprintf('%s ist dem Chat beigetreten.', $admin_name);
        $entry  = $this->append_message($session_id, 'system', $notice);

        wp_send_json_success(array(
            'handler'    => self::HANDLER_ADMIN,
            'admin_name' => $admin_name,
            'message'    => $entry,
        ));
    }

    public function handle_release() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            wp_send_json_error(array('message' => 'Session nicht gefunden.'), 404);
        }

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'       => self::HANDLER_AI,
                'admin_user_id' => 0,
                'admin_name'    => '',
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        $entry = $this->append_message(
            $session_id,
            'system',
            'Der KI-Assistent übernimmt den Chat wieder.'
        );

        wp_send_json_success(array(
            'handler' => self::HANDLER_AI,
            'message' => $entry,
        ));
    }

    public function handle_close() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            wp_send_json_error(array('message' => 'Session nicht gefunden.'), 404);
        }

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'       => self::HANDLER_CLOSED,
                'admin_user_id' => 0,
                'admin_name'    => '',
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        $entry = $this->append_message(
            $session_id,
            'system',
            'Dieser Chat wurde geschlossen. Sie können jederzeit ein neues Gespräch starten.'
        );

        $this->persist_session_last_preview($session_id);

        wp_send_json_success(array(
            'handler' => self::HANDLER_CLOSED,
            'message' => $entry,
        ));
    }

    public function handle_reopen() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $result = $this->admin_reopen($session_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), (int) $result->get_error_data()['status']);
        }

        wp_send_json_success($result);
    }

    public function handle_archive() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $result = $this->admin_archive_session($session_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), (int) $result->get_error_data()['status']);
        }

        wp_send_json_success($result);
    }

    public function handle_delete() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $result = $this->admin_delete_session($session_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), (int) $result->get_error_data()['status']);
        }

        wp_send_json_success($result);
    }

    public function handle_admin_send() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $content = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if ($session_id === '' || $content === '') {
            wp_send_json_error(array('message' => 'Ungültige Nachricht.'), 400);
        }

        if (!$this->is_admin_handler($session_id)) {
            wp_send_json_error(array('message' => 'Chat nicht übernommen.'), 409);
        }

        $reply_to = isset($_POST['reply_to']) ? (int) $_POST['reply_to'] : 0;
        $extra    = $reply_to > 0 ? array('reply_to' => $reply_to) : array();
        $entry    = $this->append_message($session_id, 'admin', $content, $extra);
        if (!$entry) {
            wp_send_json_error(array('message' => 'Speichern fehlgeschlagen.'), 500);
        }

        $this->clear_typing($session_id, 'admin');

        wp_send_json_success(array('message' => $entry));
    }

    /**
     * AI-assisted reply suggestions for admin (never sent to customer automatically).
     *
     * @return array{suggestions: array<int, string>, message_id: int}|WP_Error
     */
    public function admin_get_suggestions($session_id, $message_id) {
        $session_id = $this->sanitize_session_id($session_id);
        $message_id = (int) $message_id;

        if ($session_id === '' || $message_id <= 0) {
            return new WP_Error('invalid_request', 'Ungültige Anfrage.', array('status' => 400));
        }

        if (!$this->is_admin_handler($session_id)) {
            return new WP_Error('not_admin', 'Chat nicht übernommen.', array('status' => 409));
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        $messages = $this->sort_messages($this->decode_messages($row->messages));
        $target   = null;
        foreach ($messages as $msg) {
            if (is_array($msg) && isset($msg['id']) && (int) $msg['id'] === $message_id) {
                $target = $msg;
                break;
            }
        }

        if (!$target || ($target['role'] ?? '') !== 'user') {
            return new WP_Error('message_not_found', 'Kundennachricht nicht gefunden.', array('status' => 404));
        }

        $chat = PAXdesign_Chat::get_instance();
        $result = $chat->generate_admin_reply_suggestions($messages, $target, array(
            'service'       => isset($row->detected_service) ? (string) $row->detected_service : '',
            'customer_name' => isset($row->customer_name) ? (string) $row->customer_name : '',
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'suggestions' => $result,
            'message_id'  => $message_id,
        );
    }

    /**
     * AI-assisted reply suggestions for admin (never sent to customer automatically).
     */
    public function handle_admin_suggestions() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;

        $result = $this->admin_get_suggestions($session_id, $message_id);
        if (is_wp_error($result)) {
            $status = 500;
            $data   = $result->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                $status = (int) $data['status'];
            }
            wp_send_json_error(array('message' => $result->get_error_message()), $status);
        }

        wp_send_json_success($result);
    }

    public function handle_admin_image() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );

        if ($session_id === '' || empty($_FILES['image'])) {
            wp_send_json_error(array('message' => 'Kein Bild übermittelt.'), 400);
        }

        $caption  = isset($_POST['caption']) ? sanitize_textarea_field(wp_unslash($_POST['caption'])) : '';
        $reply_to = isset($_POST['reply_to']) ? (int) $_POST['reply_to'] : 0;
        $result   = $this->admin_send_image($session_id, $_FILES['image'], $caption, $reply_to);

        if (is_wp_error($result)) {
            $status = 500;
            $data   = $result->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                $status = (int) $data['status'];
            }
            wp_send_json_error(array('message' => $result->get_error_message()), $status);
        }

        wp_send_json_success($result);
    }

    /**
     * Admin image upload (shared by browser AJAX + mobile REST).
     *
     * @param array<string, mixed> $file $_FILES-style upload array
     * @return array{message: array<string, mixed>}|WP_Error
     */
    public function admin_send_image($session_id, array $file, $caption = '', $reply_to = 0) {
        $session_id = $this->sanitize_session_id($session_id);

        if ($session_id === '' || empty($file)) {
            return new WP_Error('invalid_payload', 'Kein Bild übermittelt.', array('status' => 400));
        }

        if (!$this->is_admin_handler($session_id)) {
            return new WP_Error('not_admin', 'Chat nicht übernommen.', array('status' => 409));
        }

        if (!empty($file['error'])) {
            return new WP_Error('upload_failed', 'Upload fehlgeschlagen.', array('status' => 400));
        }

        $allowed = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'gif'          => 'image/gif',
        );

        $name = isset($file['name']) ? (string) $file['name'] : 'image.jpg';
        $check = wp_check_filetype($name, $allowed);
        if (empty($check['type']) || !in_array($check['type'], array_values($allowed), true)) {
            return new WP_Error('invalid_type', 'Nur JPG, PNG, WebP oder GIF erlaubt.', array('status' => 400));
        }

        if (!empty($file['size']) && (int) $file['size'] > 8 * 1024 * 1024) {
            return new WP_Error('too_large', 'Bild ist zu groß (max. 8 MB).', array('status' => 400));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allowed));
        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], array('status' => 500));
        }

        $image_url = $this->optimize_chat_image($upload['file'], $upload['url']);
        $caption   = sanitize_textarea_field((string) $caption);
        $reply_to  = (int) $reply_to;
        $extra     = array('image_url' => $image_url);
        if ($reply_to > 0) {
            $extra['reply_to'] = $reply_to;
        }

        $entry = $this->append_message($session_id, 'admin', $caption, $extra);
        if (!$entry) {
            return new WP_Error('save_failed', 'Speichern fehlgeschlagen.', array('status' => 500));
        }

        $this->clear_typing($session_id, 'admin');

        return array('message' => $entry);
    }

    /**
     * Resize uploaded chat images so they stay lightweight in the thread.
     */
    private function optimize_chat_image($file_path, $url) {
        if (!function_exists('wp_get_image_editor')) {
            return $url;
        }

        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return $url;
        }

        $size = $editor->get_size();
        if (!empty($size['width']) && (int) $size['width'] > 960) {
            $editor->resize(960, null, false);
        }

        $editor->set_quality(82);
        $saved = $editor->save($file_path);
        if (is_wp_error($saved)) {
            return $url;
        }

        if (!empty($saved['path']) && !empty($saved['url'])) {
            return $saved['url'];
        }

        return $url;
    }

    const DEFAULT_NOTIFY_EMAIL         = 'info@paxdesign.at';
    const DEFAULT_NOTIFY_EMAIL_SECOND  = 'al.kahalaf.ahmad@gmail.com';
    const DEFAULT_WHATSAPP_PHONE       = '4368120543638';
    const DEFAULT_WHATSAPP_CALLMEBOT_KEY = '3515631';

    /**
     * Email + WhatsApp alerts when a customer requests a live agent.
     */
    private function notify_live_agent_request($session_id, $topic, $row) {
        $messages = $this->sort_messages($this->decode_messages($row->messages));
        $preview  = '';
        if (!empty($messages)) {
            $last = end($messages);
            if (is_array($last) && !empty($last['content'])) {
                $preview = wp_html_excerpt($last['content'], 160, '…');
            }
        }

        $service = $topic !== '' ? $topic : (isset($row->detected_service) ? $row->detected_service : '');
        $customer = $this->session_customer_label($row);
        $admin_url = add_query_arg('session', rawurlencode($session_id), $this->get_admin_panel_url());

        $this->send_live_agent_email($session_id, $service, $preview, $admin_url, $customer);
        $this->send_live_agent_whatsapp($session_id, $service, $preview, $admin_url, $customer);

        do_action('paxdesign_live_agent_requested', $session_id, $service, $preview, $admin_url, $customer);
    }

    private function get_admin_panel_url() {
        if (class_exists('PAXdesign_Live_Chat_PWA')) {
            return PAXdesign_Live_Chat_PWA::get_admin_panel_url();
        }
        return 'https://paxdesign.at/live-chat-admin/';
    }

    private function get_live_notify_emails() {
        $raw = trim((string) get_option('paxdesign_live_notify_emails', ''));
        $emails = array();

        if ($raw !== '') {
            foreach (preg_split('/[\s,;]+/', $raw) as $part) {
                $email = sanitize_email($part);
                if ($email !== '') {
                    $emails[] = $email;
                }
            }
        }

        if (empty($emails)) {
            $legacy = sanitize_email(get_option('paxdesign_live_notify_email', ''));
            if ($legacy !== '') {
                $emails[] = $legacy;
            }
        }

        foreach (array(self::DEFAULT_NOTIFY_EMAIL, self::DEFAULT_NOTIFY_EMAIL_SECOND) as $default) {
            if (!in_array($default, $emails, true)) {
                $emails[] = $default;
            }
        }

        foreach (self::REQUIRED_NOTIFY_EMAILS as $required) {
            if (!in_array($required, $emails, true)) {
                $emails[] = $required;
            }
        }

        return array_values(array_unique($emails));
    }

    private function get_live_whatsapp_callmebot_key() {
        $key = trim((string) get_option('paxdesign_live_whatsapp_callmebot_key', ''));
        if ($key === '' && defined('PAXDESIGN_WHATSAPP_CALLMEBOT_KEY') && PAXDESIGN_WHATSAPP_CALLMEBOT_KEY) {
            $key = trim((string) PAXDESIGN_WHATSAPP_CALLMEBOT_KEY);
        }
        if ($key === '') {
            $key = self::DEFAULT_WHATSAPP_CALLMEBOT_KEY;
        }
        return $key;
    }

    private function get_live_whatsapp_phone() {
        $phone = preg_replace('/\D+/', '', (string) get_option('paxdesign_live_whatsapp_phone', self::DEFAULT_WHATSAPP_PHONE));
        return $phone !== '' ? $phone : self::DEFAULT_WHATSAPP_PHONE;
    }

    private function send_live_agent_email($session_id, $service, $preview, $admin_url, $customer_name = 'Kunde') {
        $recipients = $this->get_live_notify_emails();
        if (empty($recipients)) {
            return;
        }

        $subject = '🚨 Live-Agent-Anfrage — PAXdesign Chat';
        $time    = wp_date('d.m.Y H:i');
        $customer_name = $customer_name !== '' ? $customer_name : 'Kunde';

        $plain = "LIVE-AGENT-ANFRAGE\n\n"
            . "Zeit:     {$time}\n"
            . "Kunde:    {$customer_name}\n"
            . "Session:  {$session_id}\n"
            . "Thema:    " . ($service !== '' ? $service : '—') . "\n\n"
            . "Letzte Nachricht:\n" . ($preview !== '' ? $preview : '—') . "\n\n"
            . "Admin-Panel:\n{$admin_url}\n";

        $html = '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:0 auto;padding:24px;">'
            . '<div style="background:#dc2626;color:#fff;padding:16px 20px;border-radius:10px 10px 0 0;">'
            . '<h1 style="margin:0;font-size:18px;">🚨 Live-Agent-Anfrage</h1>'
            . '<p style="margin:6px 0 0;opacity:0.9;font-size:14px;">Ein Kunde wartet auf persönliche Unterstützung</p>'
            . '</div>'
            . '<div style="background:#fff;border:1px solid #e5e7eb;border-top:0;padding:20px;border-radius:0 0 10px 10px;">'
            . '<p style="margin:0 0 12px;"><strong>Zeit:</strong> ' . esc_html($time) . '</p>'
            . '<p style="margin:0 0 12px;"><strong>Kunde:</strong> ' . esc_html($customer_name) . '</p>'
            . '<p style="margin:0 0 12px;"><strong>Session:</strong> ' . esc_html($session_id) . '</p>'
            . '<p style="margin:0 0 12px;"><strong>Thema:</strong> ' . esc_html($service !== '' ? $service : '—') . '</p>'
            . '<p style="margin:0 0 16px;padding:12px;background:#fef3c7;border-radius:8px;border-left:4px solid #f59e0b;">'
            . esc_html($preview !== '' ? $preview : 'Keine Vorschau verfügbar.')
            . '</p>'
            . '<a href="' . esc_url($admin_url) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:600;">Zum Live-Panel →</a>'
            . '</div></div>';

        $from = get_option('paxdesign_booking_notification_email', self::DEFAULT_NOTIFY_EMAIL);
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: PAXdesign Live Chat <' . sanitize_email($from) . '>',
        );

        foreach ($recipients as $to) {
            wp_mail($to, $subject, $html, $headers);
        }
    }

    /**
     * WhatsApp via CallMeBot (https://www.callmebot.com/blog/free-api-whatsapp-messages/).
     */
    private function send_live_agent_whatsapp($session_id, $service, $preview, $admin_url, $customer_name = 'Kunde') {
        $api_key = $this->get_live_whatsapp_callmebot_key();
        if ($api_key === '') {
            return;
        }

        $phone = $this->get_live_whatsapp_phone();
        $customer_name = $customer_name !== '' ? $customer_name : 'Kunde';
        $text  = '🚨 *Live-Agent-Anfrage* — PAXdesign' . "\n\n"
            . 'Kunde: ' . $customer_name . "\n"
            . 'Thema: ' . ($service !== '' ? $service : '—') . "\n"
            . 'Session: ' . $session_id . "\n\n"
            . ($preview !== '' ? $preview . "\n\n" : '')
            . 'Panel: ' . $admin_url;

        $url = add_query_arg(
            array(
                'phone'   => $phone,
                'text'    => $text,
                'apikey'  => $api_key,
            ),
            'https://api.callmebot.com/whatsapp.php'
        );

        wp_remote_get($url, array(
            'timeout'   => 8,
            'blocking'  => true,
            'sslverify' => true,
        ));
    }

    /**
     * REST/mobile admin authorization (Application Password or WP session).
     *
     * @param bool $detailed_errors Return WP_Error messages for mobile clients.
     * @return true|WP_Error
     */
    public static function rest_admin_authorized($detailed_errors = false) {
        $result = PAXdesign_Live_Chat_Permissions::authorize_api_access();
        if ($result === true) {
            return true;
        }
        if (!$detailed_errors) {
            return false;
        }
        return $result;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_live_list_data() {
        PAXdesign_Chat_Log::create_table();
        self::upgrade_schema();

        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();
        $since = $this->active_since_sql();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                 WHERE handler IN ('live_request', 'admin', 'closed')
                 OR (COALESCE(handler, 'ai') = %s AND updated_at >= %s)
                 ORDER BY
                   CASE COALESCE(handler, 'ai')
                     WHEN 'live_request' THEN 0
                     WHEN 'admin' THEN 1
                     WHEN 'closed' THEN 3
                     ELSE 2
                   END,
                   updated_at DESC
                 LIMIT 100",
                self::HANDLER_AI,
                $since
            )
        );

        if ($rows === null && $wpdb->last_error) {
            return new WP_Error('db_error', 'Datenbankfehler: ' . $wpdb->last_error, array('status' => 500));
        }

        $sessions   = array();
        $live_count = 0;
        foreach ((array) $rows as $row) {
            $item = $this->format_live_list_session($row);
            if ($item['handler'] === self::HANDLER_LIVE) {
                $live_count++;
            }
            $sessions[] = $item;
        }

        return array(
            'sessions'    => $sessions,
            'live_count'  => $live_count,
            'server_time' => current_time('mysql'),
        );
    }

    /**
     * @param object $row
     * @return array<string, mixed>
     */
    private function format_live_list_session($row) {
        $messages = $this->sort_messages($this->decode_messages($row->messages));
        $last     = !empty($messages) ? end($messages) : null;
        $preview  = is_array($last) && !empty($last['content'])
            ? wp_html_excerpt($last['content'], 100, '…')
            : '';
        $handler  = isset($row->handler) ? (string) $row->handler : self::HANDLER_AI;

        return array(
            'id'               => isset($row->id) ? (int) $row->id : 0,
            'session_id'       => isset($row->session_id) ? (string) $row->session_id : '',
            'handler'          => $handler,
            'handler_label'    => self::handler_label($handler),
            'admin_name'       => isset($row->admin_name) ? (string) $row->admin_name : '',
            'customer_name'    => isset($row->customer_name) ? (string) $row->customer_name : '',
            'session_rating'   => isset($row->session_rating) ? (int) $row->session_rating : 0,
            'detected_service' => isset($row->detected_service) ? (string) $row->detected_service : '',
            'updated_at'       => isset($row->updated_at) ? (string) $row->updated_at : '',
            'message_count'    => isset($row->message_count) ? (int) $row->message_count : 0,
            'seq'              => isset($row->message_seq) ? (int) $row->message_seq : 0,
            'last_preview'     => $preview,
            'last_role'        => is_array($last) && !empty($last['role']) ? (string) $last['role'] : '',
        );
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function format_messages_for_api($messages) {
        $out = array();
        foreach ($this->sort_messages($messages) as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $entry = array(
                'id'      => isset($msg['id']) ? (int) $msg['id'] : 0,
                'role'    => isset($msg['role']) ? sanitize_text_field($msg['role']) : 'assistant',
                'content' => isset($msg['content']) ? (string) $msg['content'] : '',
            );
            if (isset($msg['ts'])) {
                $entry['ts'] = (int) $msg['ts'];
            }
            if (!empty($msg['image_url'])) {
                $entry['image_url'] = esc_url_raw($msg['image_url']);
            }
            if (!empty($msg['reply_to'])) {
                $entry['reply_to'] = (int) $msg['reply_to'];
            }
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_poll_payload() {
        return array(
            'handler'          => self::HANDLER_AI,
            'handler_label'    => self::handler_label(self::HANDLER_AI),
            'admin_name'       => '',
            'customer_name'    => '',
            'session_rating'   => 0,
            'detected_service' => '',
            'updated_at'       => '',
            'seq'              => 0,
            'messages'         => array(),
            'admin_typing'     => false,
            'user_typing'      => false,
            'reactions'        => array(),
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_session_detail_data($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Ungültige Session.', array('status' => 400));
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        $handler = isset($row->handler) ? (string) $row->handler : self::HANDLER_AI;

        return array(
            'session_id'       => isset($row->session_id) ? (string) $row->session_id : '',
            'handler'          => $handler,
            'handler_label'    => self::handler_label($handler),
            'admin_name'       => isset($row->admin_name) ? (string) $row->admin_name : '',
            'customer_name'    => isset($row->customer_name) ? (string) $row->customer_name : '',
            'session_rating'   => isset($row->session_rating) ? (int) $row->session_rating : 0,
            'detected_service' => isset($row->detected_service) ? (string) $row->detected_service : '',
            'updated_at'       => isset($row->updated_at) ? (string) $row->updated_at : '',
            'seq'              => isset($row->message_seq) ? (int) $row->message_seq : 0,
            'messages'         => $this->format_messages_for_api($this->decode_messages($row->messages)),
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_poll_data($session_id, $since = 0, $full = false) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Invalid session', array('status' => 400));
        }

        $since = (int) $since;
        $row   = $this->get_session_row($session_id);

        if (!$row) {
            return $this->empty_poll_payload();
        }

        $messages = $this->decode_messages($row->messages);
        $all      = $this->format_messages_for_api($messages);
        $new      = $full ? $all : array();
        if (!$full) {
            foreach ($all as $msg) {
                $mid = isset($msg['id']) ? (int) $msg['id'] : 0;
                if ($mid > $since) {
                    $new[] = $msg;
                }
            }
        }

        $handler = isset($row->handler) ? (string) $row->handler : self::HANDLER_AI;

        return array(
            'handler'          => $handler,
            'handler_label'    => self::handler_label($handler),
            'admin_name'       => isset($row->admin_name) ? (string) $row->admin_name : '',
            'customer_name'    => isset($row->customer_name) ? (string) $row->customer_name : '',
            'session_rating'   => isset($row->session_rating) ? (int) $row->session_rating : 0,
            'detected_service' => isset($row->detected_service) ? (string) $row->detected_service : '',
            'updated_at'       => isset($row->updated_at) ? (string) $row->updated_at : '',
            'seq'              => isset($row->message_seq) ? (int) $row->message_seq : 0,
            'messages'         => $new,
            'admin_typing'     => $this->is_typing($session_id, 'admin'),
            'user_typing'      => $this->is_typing($session_id, 'user'),
            'reactions'        => $this->extract_message_reactions($messages),
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function admin_takeover($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Ungültige Session.', array('status' => 400));
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        if ($this->get_handler($session_id) === self::HANDLER_CLOSED) {
            return new WP_Error('closed', 'Chat ist geschlossen.', array('status' => 409));
        }

        $user       = wp_get_current_user();
        $admin_name = self::get_agent_display_name();

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'       => self::HANDLER_ADMIN,
                'admin_user_id' => (int) $user->ID,
                'admin_name'    => sanitize_text_field($admin_name),
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        $notice = sprintf('%s ist dem Chat beigetreten.', $admin_name);
        $entry  = $this->append_message($session_id, 'system', $notice);

        return array(
            'handler'    => self::HANDLER_ADMIN,
            'admin_name' => $admin_name,
            'message'    => $entry,
        );
    }

    /**
     * Decline a live agent request by closing without joining.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function admin_decline_live_request($session_id) {
        $handler = $this->get_handler($session_id);
        if ($handler !== self::HANDLER_LIVE) {
            return new WP_Error('invalid_state', 'Keine offene Live-Anfrage.', array('status' => 409));
        }
        return $this->admin_close($session_id);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function admin_close($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Ungültige Session.', array('status' => 400));
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        $handler = isset($row->handler) ? $row->handler : self::HANDLER_AI;
        if ($handler === self::HANDLER_CLOSED) {
            return array(
                'handler' => self::HANDLER_CLOSED,
                'message' => null,
            );
        }

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'       => self::HANDLER_CLOSED,
                'admin_user_id' => 0,
                'admin_name'    => '',
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        $entry = $this->append_message(
            $session_id,
            'system',
            'Dieser Chat wurde geschlossen. Sie können jederzeit ein neues Gespräch starten.'
        );

        $this->persist_session_last_preview($session_id);

        return array(
            'handler' => self::HANDLER_CLOSED,
            'message' => $entry,
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function admin_release($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Ungültige Session.', array('status' => 400));
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'       => self::HANDLER_AI,
                'admin_user_id' => 0,
                'admin_name'    => '',
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        $entry = $this->append_message(
            $session_id,
            'system',
            'Der KI-Assistent übernimmt den Chat wieder.'
        );

        return array(
            'handler' => self::HANDLER_AI,
            'message' => $entry,
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function admin_reopen($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Ungültige Session.', array('status' => 400));
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        if ($this->get_handler($session_id) !== self::HANDLER_CLOSED) {
            return new WP_Error('invalid_state', 'Chat ist nicht geschlossen.', array('status' => 409));
        }

        $user       = wp_get_current_user();
        $admin_name = self::get_agent_display_name();

        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array(
                'handler'       => self::HANDLER_ADMIN,
                'admin_user_id' => (int) $user->ID,
                'admin_name'    => sanitize_text_field($admin_name),
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => (int) $row->id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        $entry = $this->append_message(
            $session_id,
            'system',
            'Der Chat wurde wieder geöffnet. ' . $admin_name . ' ist wieder für Sie da.'
        );

        return array(
            'handler'    => self::HANDLER_ADMIN,
            'admin_name' => $admin_name,
            'message'    => $entry,
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function admin_send_message($session_id, $content, $reply_to = 0) {
        $session_id = $this->sanitize_session_id($session_id);
        $content    = sanitize_textarea_field((string) $content);

        if ($session_id === '' || $content === '') {
            return new WP_Error('invalid_payload', 'Ungültige Nachricht.', array('status' => 400));
        }

        if (!$this->is_admin_handler($session_id)) {
            return new WP_Error('not_admin', 'Chat nicht übernommen.', array('status' => 409));
        }

        $reply_to = (int) $reply_to;
        $extra    = $reply_to > 0 ? array('reply_to' => $reply_to) : array();
        $entry    = $this->append_message($session_id, 'admin', $content, $extra);
        if (!$entry) {
            return new WP_Error('save_failed', 'Speichern fehlgeschlagen.', array('status' => 500));
        }

        $this->clear_typing($session_id, 'admin');

        return array('message' => $entry);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function admin_set_typing($session_id, $stop = false) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '' || !$this->is_admin_handler($session_id)) {
            return new WP_Error('invalid_state', 'Chat nicht übernommen.', array('status' => 409));
        }

        if ($stop) {
            $this->clear_typing($session_id, 'admin');
        } else {
            $this->mark_typing($session_id, 'admin');
        }

        return array('ok' => true);
    }

    /**
     * Archive (close) a session — same outcome as admin_close for list sync.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function admin_archive_session($session_id) {
        return $this->admin_close($session_id);
    }

    /**
     * Permanently delete a session from the log.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function admin_delete_session($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Ungültige Session.', array('status' => 400));
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        $this->clear_typing($session_id, 'admin');
        $this->clear_typing($session_id, 'user');

        $deleted = PAXdesign_Chat_Log::get_instance()->delete_logs_by_ids(array((int) $row->id));
        if ($deleted < 1) {
            return new WP_Error('delete_failed', 'Löschen fehlgeschlagen.', array('status' => 500));
        }

        return array(
            'ok'      => true,
            'deleted' => $deleted,
        );
    }
}
