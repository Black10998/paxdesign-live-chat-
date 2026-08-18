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
    const CHAT_SCHEMA_VERSION = '2.5';

    /** @var bool */
    private static $list_schema_ready = false;

    const DEFAULT_AGENT_NAME   = 'Ahmad Alkhalaf';
    const DEFAULT_AGENT_AVATAR   = 'https://paxdesign.at/wp-content/uploads/2026/06/unnamed.jpg';
    const DEFAULT_AI_AVATAR_URL  = 'https://paxdesign.at/wp-content/uploads/2026/06/a5af1840-117e-11ee-bada-ff65d56e3db4.gif';
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

    /**
     * Public AI assistant identity for assistant-role chat messages.
     *
     * @return array{id: int, name: string, avatar: string, role: string}
     */
    public static function get_ai_assistant_identity() {
        $name = trim((string) get_option('paxdesign_chat_ai_name', ''));
        if ($name === '') {
            $name = __('PAXDesign AI', 'paxdesign-booking');
        }
        $avatar = trim((string) get_option('paxdesign_chat_ai_avatar', ''));
        if ($avatar === '') {
            $avatar = self::DEFAULT_AI_AVATAR_URL;
        }
        return array(
            'id'     => 0,
            'name'   => sanitize_text_field($name),
            'avatar' => esc_url_raw($avatar),
            'role'   => __('AI Assistant', 'paxdesign-booking'),
        );
    }

    /**
     * Resolve a customer's public chat identity.
     *
     * @param int    $user_id
     * @param string $fallback_name
     * @return array{id: int, name: string, avatar: string, role: string}
     */
    public static function resolve_customer_identity($user_id, $fallback_name = '') {
        $user_id = absint($user_id);
        $fallback_name = trim((string) $fallback_name);
        if ($user_id <= 0) {
            $name = $fallback_name !== '' ? $fallback_name : __('Customer', 'paxdesign-booking');
            return array(
                'id'     => 0,
                'name'   => sanitize_text_field($name),
                'avatar' => '',
                'role'   => __('Customer', 'paxdesign-booking'),
            );
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            $name = $fallback_name !== '' ? $fallback_name : __('Customer', 'paxdesign-booking');
            return array(
                'id'     => $user_id,
                'name'   => sanitize_text_field($name),
                'avatar' => '',
                'role'   => __('Customer', 'paxdesign-booking'),
            );
        }

        $name = trim($user->display_name);
        if ($name === '') {
            $name = $fallback_name !== '' ? $fallback_name : $user->user_login;
        }

        return array(
            'id'     => $user_id,
            'name'   => sanitize_text_field($name),
            'avatar' => esc_url_raw(get_avatar_url($user_id, array('size' => 256))),
            'role'   => __('Customer', 'paxdesign-booking'),
        );
    }

    /**
     * Resolve the authenticated employee's public identity (hub name, avatar, role).
     *
     * @param int $user_id
     * @return array{id: int, name: string, email: string, avatar: string, role: string}|null
     */
    public static function resolve_employee_identity($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return null;
        }

        $hub_name = trim((string) get_user_meta($user_id, 'pax_live_hub_display_name', true));
        $name     = $hub_name !== '' ? $hub_name : $user->display_name;
        if ($name === '') {
            $name = $user->user_login;
        }
        $name = self::normalize_staff_display_name($user_id, $name);

        $avatar_meta = trim((string) get_user_meta($user_id, 'pax_live_avatar_url', true));
        $avatar      = $avatar_meta !== '' ? esc_url_raw($avatar_meta) : get_avatar_url($user_id, array('size' => 256));

        $title = trim((string) get_user_meta($user_id, 'pax_live_profile_title', true));
        if (class_exists('PAXdesign_Live_Chat_Permissions')) {
            $title = PAXdesign_Live_Chat_Permissions::normalize_profile_title($title, $user_id);
        }
        if ($title === '' && class_exists('PAXdesign_Live_Chat_Permissions')) {
            $title = PAXdesign_Live_Chat_Permissions::is_super_admin($user)
                ? 'Executive Director'
                : __('Mitarbeiter', 'paxdesign-booking');
        }

        return array(
            'id'     => $user_id,
            'name'   => sanitize_text_field($name),
            'email'  => sanitize_email($user->user_email),
            'avatar' => $avatar,
            'role'   => sanitize_text_field($title),
        );
    }

    /**
     * Replace generic WordPress/system placeholder names with a human identity.
     *
     * @param int    $user_id
     * @param string $candidate
     */
    public static function normalize_staff_display_name($user_id, $candidate) {
        $user_id   = absint($user_id);
        $candidate = trim((string) $candidate);
        if ($candidate === '' || $user_id <= 0) {
            return $candidate;
        }

        $user = get_user_by('id', $user_id);
        $login = ($user && is_string($user->user_login)) ? trim($user->user_login) : '';

        $is_placeholder = (bool) preg_match('/^management\s+system/i', $candidate)
            || (bool) preg_match('/^system\s+account/i', $candidate)
            || (bool) preg_match('/^(manage|system|admin)[\-_]/i', $candidate)
            || strtolower($candidate) === 'administrator'
            || strtolower($candidate) === 'admin'
            || ($login !== '' && strcasecmp($candidate, $login) === 0)
            || self::looks_like_internal_username_slug($candidate);

        if (!$is_placeholder) {
            return $candidate;
        }

        $resolved = self::resolve_human_name_from_user_meta($user_id, $user);
        if ($resolved !== '') {
            return $resolved;
        }

        return $candidate;
    }

    /**
     * Detect WordPress login slugs that should never be shown as display names.
     *
     * @param string $candidate
     * @return bool
     */
    private static function looks_like_internal_username_slug($candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return false;
        }
        if (strpos($candidate, ' ') !== false) {
            return false;
        }
        if (strpos($candidate, '@') !== false) {
            return false;
        }
        if (preg_match('/[\-_.]{1,}/', $candidate) && !preg_match('/^[A-Z][a-z]+$/', $candidate)) {
            return true;
        }
        return (bool) preg_match('/^(manage|system|admin|user)[\-_]/i', $candidate);
    }

    /**
     * @param int              $user_id
     * @param WP_User|false    $user
     * @return string
     */
    private static function resolve_human_name_from_user_meta($user_id, $user) {
        $first = trim((string) get_user_meta($user_id, 'first_name', true));
        $last  = trim((string) get_user_meta($user_id, 'last_name', true));
        $full  = trim($first . ' ' . $last);
        if ($full !== '' && !preg_match('/^management\s+system/i', $full)) {
            return $full;
        }

        if ($user && is_string($user->user_email) && strpos($user->user_email, '@') !== false) {
            $local = strstr($user->user_email, '@', true);
            if (is_string($local) && $local !== '') {
                $parts = preg_split('/[._\-+]+/', $local);
                if (is_array($parts)) {
                    $parts = array_values(array_filter(array_map(function ($part) {
                        $part = trim((string) $part);
                        return $part !== '' ? ucfirst(strtolower($part)) : '';
                    }, $parts)));
                    if (!empty($parts)) {
                        return implode(' ', $parts);
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param object|array<string, mixed>|null $row
     * @return array{id: int, name: string, email: string, avatar: string, role: string}|null
     */
    public static function assigned_agent_from_row($row) {
        if (!$row) {
            return null;
        }

        $user_id = 0;
        $name    = '';
        if (is_object($row)) {
            $user_id = isset($row->admin_user_id) ? absint($row->admin_user_id) : 0;
            $name    = isset($row->admin_name) ? trim((string) $row->admin_name) : '';
        } elseif (is_array($row)) {
            $user_id = isset($row['admin_user_id']) ? absint($row['admin_user_id']) : 0;
            $name    = isset($row['admin_name']) ? trim((string) $row['admin_name']) : '';
        }

        if ($user_id > 0) {
            $identity = self::resolve_employee_identity($user_id);
            if ($identity) {
                return $identity;
            }
        }

        if ($name !== '') {
            return array(
                'id'     => $user_id,
                'name'   => $name,
                'email'  => '',
                'avatar' => '',
                'role'   => '',
            );
        }

        return null;
    }

    /**
     * @param object|array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    public static function session_agent_payload($row) {
        $payload = array(
            'admin_user_id'  => 0,
            'admin_name'     => '',
            'assigned_agent' => null,
        );

        if (!$row) {
            return $payload;
        }

        if (is_object($row)) {
            $payload['admin_user_id'] = isset($row->admin_user_id) ? absint($row->admin_user_id) : 0;
            $payload['admin_name']    = isset($row->admin_name) ? sanitize_text_field((string) $row->admin_name) : '';
        } elseif (is_array($row)) {
            $payload['admin_user_id'] = isset($row['admin_user_id']) ? absint($row['admin_user_id']) : 0;
            $payload['admin_name']    = isset($row['admin_name']) ? sanitize_text_field((string) $row['admin_name']) : '';
        }

        $assigned = self::assigned_agent_from_row($row);
        if ($assigned) {
            $payload['assigned_agent'] = $assigned;
            if ($payload['admin_name'] === '' && !empty($assigned['name'])) {
                $payload['admin_name'] = (string) $assigned['name'];
            }
            if ($payload['admin_user_id'] <= 0 && !empty($assigned['id'])) {
                $payload['admin_user_id'] = absint($assigned['id']);
            }
        }

        return $payload;
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_paxdesign_chat_nonce', array($this, 'handle_chat_nonce'));
        add_action('wp_ajax_nopriv_paxdesign_chat_nonce', array($this, 'handle_chat_nonce'));
        add_action('wp_ajax_paxdesign_chat_poll', array($this, 'handle_poll'));
        add_action('wp_ajax_nopriv_paxdesign_chat_poll', array($this, 'handle_poll'));
        add_action('wp_ajax_paxdesign_chat_disconnect', array($this, 'handle_disconnect'));
        add_action('wp_ajax_nopriv_paxdesign_chat_disconnect', array($this, 'handle_disconnect'));
        add_action('wp_ajax_paxdesign_chat_stream', array($this, 'handle_stream'));
        add_action('wp_ajax_nopriv_paxdesign_chat_stream', array($this, 'handle_stream'));
        add_action('wp_ajax_paxdesign_chat_live_stream', array($this, 'handle_admin_stream'));
        add_action('wp_ajax_paxdesign_chat_live_user_send', array($this, 'handle_user_send'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_user_send', array($this, 'handle_user_send'));
        add_action('wp_ajax_paxdesign_chat_live_user_attach', array($this, 'handle_user_attach'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_user_attach', array($this, 'handle_user_attach'));
        add_action('wp_ajax_paxdesign_chat_live_request', array($this, 'handle_live_request'));
        add_action('wp_ajax_nopriv_paxdesign_chat_live_request', array($this, 'handle_live_request'));
        add_action('wp_ajax_paxdesign_chat_live_list', array($this, 'handle_live_list'));
        add_action('wp_ajax_paxdesign_chat_live_presence', array($this, 'handle_live_presence'));
        add_action('wp_ajax_paxdesign_chat_live_session', array($this, 'handle_live_session'));
        add_action('wp_ajax_paxdesign_chat_live_takeover', array($this, 'handle_takeover'));
        add_action('wp_ajax_paxdesign_chat_live_decline', array($this, 'handle_decline'));
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
        add_action('wp_ajax_paxdesign_chat_live_admin_delete_message', array($this, 'handle_admin_delete_message'));
        add_action('wp_ajax_paxdesign_chat_live_admin_link_review', array($this, 'handle_admin_link_review'));
        add_action('wp_ajax_paxdesign_chat_live_tour_complete', array($this, 'handle_tour_complete'));
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
        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::emit_session($session_id, 'typing', array(
                'who'    => sanitize_key($who),
                'active' => true,
            ));
        }
    }

    private function is_typing($session_id, $who) {
        return (bool) get_transient($this->typing_transient_key($session_id, $who));
    }

    private function clear_typing($session_id, $who) {
        $this->clear_typing_indicator($session_id, $who);
    }

    /**
     * Clear typing state for a participant (used by REST, bridge, and internal handlers).
     */
    public function clear_typing_indicator($session_id, $who) {
        delete_transient($this->typing_transient_key($session_id, $who));
        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::emit_session($session_id, 'typing', array(
                'who'    => sanitize_key($who),
                'active' => false,
            ));
        }
    }

    /** Signal that the AI assistant is composing a reply (visible to staff). */
    public function mark_assistant_typing($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '' || $this->get_handler($session_id) !== self::HANDLER_AI) {
            return;
        }
        $this->mark_typing($session_id, 'assistant');
    }

    /** Clear AI assistant typing indicator. */
    public function clear_assistant_typing($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return;
        }
        $this->clear_typing_indicator($session_id, 'assistant');
    }

    /**
     * REST/mobile customer typing indicator.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function rest_customer_typing($session_id, $stop = false) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '' || !$this->is_human_queue($session_id)) {
            return array('ok' => false);
        }
        if ($stop) {
            $this->clear_typing($session_id, 'user');
        } else {
            $this->mark_typing($session_id, 'user');
        }
        return array('ok' => true);
    }

    /**
     * REST/mobile customer-initiated conversation close.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function rest_customer_close($session_id) {
        self::upgrade_schema();
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', __('Invalid session.', 'paxdesign-booking'), array('status' => 400));
        }
        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', __('Session not found.', 'paxdesign-booking'), array('status' => 404));
        }
        if ($this->get_handler($session_id) === self::HANDLER_CLOSED) {
            return array(
                'handler'        => self::HANDLER_CLOSED,
                'session_id'     => $session_id,
                'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
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
        $this->append_message($session_id, 'system', __('The customer ended this conversation.', 'paxdesign-booking'));
        $this->persist_session_last_preview($session_id);
        return array(
            'handler'        => self::HANDLER_CLOSED,
            'session_id'     => $session_id,
            'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
        );
    }

    public static function upgrade_schema() {
        if ((string) get_option('paxdesign_chat_live_schema', '') === self::CHAT_SCHEMA_VERSION) {
            return;
        }

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
            array('customer_language', "varchar(8) NOT NULL DEFAULT ''", 'last_preview'),
            array('user_read_seq', 'int(10) unsigned NOT NULL DEFAULT 0', 'customer_language'),
            array('admin_read_seq', 'int(10) unsigned NOT NULL DEFAULT 0', 'user_read_seq'),
            array('wp_user_id', 'bigint(20) unsigned NOT NULL DEFAULT 0', 'admin_read_seq'),
            array('customer_last_seen_at', "datetime NULL DEFAULT NULL", 'wp_user_id'),
        );

        foreach ($columns as $col) {
            paxdesign_booking_add_column_if_missing($table, $col[0], $col[1], $col[2]);
        }

        update_option('paxdesign_chat_live_schema', self::CHAT_SCHEMA_VERSION, false);
        self::purge_legacy_anonymous_sessions();
    }

    /**
     * Permanently remove guest/anonymous chat sessions (registered users only).
     */
    public static function purge_legacy_anonymous_sessions() {
        if ((string) get_option('paxdesign_purged_anonymous_sessions', '') === '1') {
            return;
        }

        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();
        PAXdesign_Chat_Log::create_table();

        $anonymous_ids = $wpdb->get_col(
            "SELECT session_id FROM $table
             WHERE COALESCE(wp_user_id, 0) = 0
               AND session_id NOT LIKE 'pax_u%'"
        );

        if (!empty($anonymous_ids) && class_exists('PAXdesign_Message_Store')) {
            $msg_table = PAXdesign_Message_Store::table_name();
            foreach ((array) $anonymous_ids as $sid) {
                $sid = (string) $sid;
                if ($sid === '') {
                    continue;
                }
                $wpdb->delete($msg_table, array('session_id' => $sid), array('%s'));
                $wpdb->delete($table, array('session_id' => $sid), array('%s'));
            }
        }

        if (class_exists('PAXdesign_Customer_DB')) {
            $claims = PAXdesign_Customer_DB::table('guest_claims');
            $sessions_map = PAXdesign_Customer_DB::table('chat_sessions');
            $wpdb->query("DELETE FROM $claims");
            $wpdb->query(
                "DELETE FROM $sessions_map
                 WHERE session_id NOT LIKE 'pax_u%'"
            );
        }

        update_option('paxdesign_purged_anonymous_sessions', '1', false);
    }

    /**
     * @return bool
     */
    public static function is_registered_session_id($session_id) {
        $session_id = (string) $session_id;
        return $session_id !== '' && strpos($session_id, 'pax_u') === 0;
    }

    /**
     * Extract WordPress user id from durable customer session ids (pax_u{uid}_…).
     */
    public static function user_id_from_session_id($session_id) {
        $session_id = (string) $session_id;
        if ($session_id === '' || !preg_match('/^pax_u(\d+)_/i', $session_id, $matches)) {
            return 0;
        }
        return absint($matches[1]);
    }

    private static function ensure_list_schema() {
        if (self::$list_schema_ready) {
            return;
        }
        PAXdesign_Chat_Log::create_table();
        self::upgrade_schema();
        self::$list_schema_ready = true;
    }

    /**
     * @return array{user:int,admin:int}
     */
    private function get_read_seqs($row) {
        return array(
            'user'  => isset($row->user_read_seq) ? (int) $row->user_read_seq : 0,
            'admin' => isset($row->admin_read_seq) ? (int) $row->admin_read_seq : 0,
        );
    }

    /**
     * @param 'user'|'admin' $who
     */
    private function mark_read_seq($session_id, $who, $seq) {
        $session_id = $this->sanitize_session_id($session_id);
        $seq = max(0, (int) $seq);
        if ($session_id === '' || $seq <= 0) {
            return;
        }
        $row = $this->get_session_row($session_id);
        if (!$row) {
            return;
        }
        $reads = $this->get_read_seqs($row);
        $column = $who === 'admin' ? 'admin_read_seq' : 'user_read_seq';
        $current = $who === 'admin' ? $reads['admin'] : $reads['user'];
        if ($seq <= $current) {
            return;
        }
        global $wpdb;
        $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            array($column => $seq),
            array('id' => (int) $row->id),
            array('%d'),
            array('%d')
        );
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

    public function handle_chat_nonce() {
        $this->reject_guest_chat();
        wp_send_json_success(array(
            'nonce' => wp_create_nonce('paxdesign_chat_nonce'),
        ));
    }

    private function reject_guest_chat() {
        if (is_user_logged_in()) {
            return;
        }
        wp_send_json_error(array(
            'message' => __('Sign in or create an account to use Live Chat.', 'paxdesign-booking'),
            'code'    => 'login_required',
        ), 401);
    }

    private function verify_chat_nonce() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' && isset($_GET['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));
        }
        if ($nonce && wp_verify_nonce($nonce, 'paxdesign_chat_nonce')) {
            return true;
        }
        // Logged-in widget may briefly send wp_rest nonce after auth session refresh.
        if ($nonce && is_user_logged_in() && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }
        if ($nonce && wp_verify_nonce($nonce, 'paxdesign_admin_nonce') && current_user_can('manage_options')) {
            return true;
        }
        return false;
    }

    /**
     * Map website AJAX session IDs to the authenticated customer's primary conversation.
     */
    private function resolve_customer_ajax_session($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if (!class_exists('PAXdesign_Customer_Chat_Bridge')) {
            return $session_id;
        }
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return $session_id;
        }
        return PAXdesign_Customer_Chat_Bridge::resolve_ajax_session($user_id, $session_id);
    }

    private function verify_admin_stream_access() {
        $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'paxdesign_admin_nonce')) {
            status_header(403);
            exit;
        }
        if (!current_user_can('manage_options')) {
            status_header(403);
            exit;
        }
    }

    private function verify_admin_nonce() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'), 403);
        }
    }

    /**
     * Mark the current staff member as online for team presence.
     */
    private function touch_staff_presence_if_applicable() {
        $user_id = get_current_user_id();
        if ($user_id <= 0 || !class_exists('PAXdesign_Live_Chat_Permissions')) {
            return;
        }
        if (PAXdesign_Live_Chat_Permissions::has_live_chat_access($user_id)) {
            PAXdesign_Live_Chat_Permissions::touch_team_presence($user_id);
        }
    }

    /**
     * Canonical session ID validation for all chat subsystems.
     * Public so PAXdesign_Chat and customer bridge can validate before calling live APIs.
     */
    public function sanitize_session_id($session_id) {
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

    /**
     * Ensure the current agent owns the chat; optionally auto-takeover from AI/live queue.
     *
     * @return true|WP_Error
     */
    public function ensure_admin_handler_for_agent($session_id, $auto_takeover = true) {
        $handler = $this->get_handler($session_id);
        if ($handler === self::HANDLER_ADMIN) {
            return true;
        }
        if ($handler === self::HANDLER_CLOSED) {
            return new WP_Error('closed', 'Chat ist geschlossen.', array('status' => 409));
        }
        if ($handler === self::HANDLER_LIVE) {
            return new WP_Error(
                'live_request_pending',
                __('Please accept the live request before replying.', 'paxdesign-booking'),
                array('status' => 409)
            );
        }
        if (!$auto_takeover) {
            return new WP_Error('not_admin', 'Chat nicht übernommen.', array('status' => 409));
        }
        $result = $this->admin_takeover($session_id);
        return is_wp_error($result) ? $result : true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function session_messages_for_agent($session_id) {
        if (class_exists('PAXdesign_Message_Store')) {
            $messages = PAXdesign_Message_Store::all_messages($session_id, 'customer');
            if (!empty($messages)) {
                return $this->sort_messages($messages);
            }
        }
        $row = $this->get_session_row($session_id);
        if (!$row) {
            return array();
        }
        return $this->sort_messages($this->decode_messages($row->messages));
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

    public static function handler_label($handler, $session_admin_name = '') {
        switch ($handler) {
            case self::HANDLER_LIVE:
                return 'Live-Anfrage';
            case self::HANDLER_ADMIN:
                $name = trim((string) $session_admin_name);
                return $name !== '' ? $name : __('Live Agent', 'paxdesign-booking');
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

    public function ensure_session($session_id, $messages = array(), $user_id = 0) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return null;
        }

        $row = $this->get_session_row($session_id);
        if ($row) {
            if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
                PAXdesign_Customer_Chat_Bridge::sync_customer_profile($session_id, $user_id);
            }
            return $this->get_session_row($session_id);
        }

        PAXdesign_Chat_Log::create_table();
        self::upgrade_schema();

        if (!is_array($messages)) {
            $messages = array();
        }

        if (empty($messages)) {
            global $wpdb;
            PAXdesign_Chat_Log::create_table();
            self::upgrade_schema();
            $table = PAXdesign_Chat_Log::table_name();
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE session_id = %s LIMIT 1",
                $session_id
            ));
            if (!$existing) {
                $now = current_time('mysql');
                $insert = array(
                    'session_id'           => $session_id,
                    'started_at'           => $now,
                    'updated_at'           => $now,
                    'messages'             => '[]',
                    'handler'              => self::HANDLER_AI,
                    'detected_service'     => '',
                    'booking_triggered'    => 0,
                    'consultation_started' => 0,
                    'message_count'        => 0,
                );
                $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d');
                $user_id = absint($user_id);
                if ($user_id <= 0) {
                    $user_id = self::user_id_from_session_id($session_id);
                }
                if ($user_id > 0) {
                    $identity = self::resolve_customer_identity($user_id, '');
                    $insert['wp_user_id']    = $user_id;
                    $insert['customer_name'] = $identity['name'];
                    $formats[] = '%d';
                    $formats[] = '%s';
                }
                $wpdb->insert($table, $insert, $formats);
            }
            return $this->get_session_row($session_id);
        }

        PAXdesign_Chat_Log::get_instance()->save_session(array(
            'session_id'           => $session_id,
            'messages'             => $messages,
            'detected_service'     => '',
            'booking_triggered'    => false,
            'consultation_started' => false,
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
        $has_file = !empty($extra['file_url']);
        $has_link_card = !empty($extra['attachment_type']) && $extra['attachment_type'] === 'link_card';
        if ($content === '' && !$has_image && !$has_file && !$has_link_card) {
            return null;
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            $row = $this->ensure_session($session_id);
        }
        if (!$row || !class_exists('PAXdesign_Message_Store')) {
            return null;
        }

        if (!empty($extra['reply_to'])) {
            $extra['reply_to'] = absint($extra['reply_to']);
        }
        if ($has_image) {
            $extra['image_url']       = esc_url_raw($extra['image_url']);
            $extra['attachment_type'] = 'image';
        }
        if ($has_file) {
            $extra['file_url']        = esc_url_raw((string) $extra['file_url']);
            $extra['file_name']       = sanitize_file_name((string) ($extra['file_name'] ?? ''));
            $extra['file_mime']       = sanitize_text_field((string) ($extra['file_mime'] ?? ''));
            $extra['attachment_type'] = 'file';
        }
        if ($has_link_card) {
            $extra['link_url']   = esc_url_raw((string) ($extra['link_url'] ?? ''));
            $extra['link_label'] = sanitize_text_field((string) ($extra['link_label'] ?? ''));
            $extra['link_icon']  = sanitize_text_field((string) ($extra['link_icon'] ?? '🔗'));
            if ($extra['link_url'] === '' || $extra['link_label'] === '') {
                return null;
            }
        }
        if ($role === 'admin') {
            $sender_id = get_current_user_id();
            if ($sender_id > 0) {
                $identity = self::resolve_employee_identity($sender_id);
                if ($identity) {
                    $extra['sender_id']     = $identity['id'];
                    $extra['sender_name']   = $identity['name'];
                    $extra['sender_avatar'] = $identity['avatar'];
                    $extra['sender_role']   = $identity['role'];
                    $extra['sender_email']  = $identity['email'];
                }
            } elseif (!empty($extra['sender_id'])) {
                $identity = self::resolve_employee_identity((int) $extra['sender_id']);
                if ($identity) {
                    $extra['sender_id']     = $identity['id'];
                    $extra['sender_name']   = $identity['name'];
                    $extra['sender_avatar'] = $identity['avatar'];
                    $extra['sender_role']   = $identity['role'];
                    $extra['sender_email']  = $identity['email'];
                }
            }
        }

        $entry = PAXdesign_Message_Store::append($session_id, $role, $content, $extra, 'customer');
        if (is_wp_error($entry)) {
            return $entry;
        }
        $id = isset($entry['id']) ? absint($entry['id']) : 0;

        if ($id > 0 && !empty($entry['_deduplicated'])) {
            // Skip duplicate hook firing.
        } elseif ($id > 0) {
            do_action('paxdesign_chat_message_appended', $session_id, $role, $content, $id);
        }

        if ($role === 'user' && class_exists('PAXdesign_Language_Routing')) {
            $detected = PAXdesign_Language_Routing::detect_text_language($content);
            if ($detected !== '') {
                $wpdb->update(
                    PAXdesign_Chat_Log::table_name(),
                    array('customer_language' => $detected),
                    array('session_id' => $session_id),
                    array('%s'),
                    array('%s')
                );
            }
        }

        $handler = isset($row->handler) ? (string) $row->handler : self::HANDLER_AI;
        $preview = $content !== '' ? wp_html_excerpt($content, 120, '…') : '';
        if (empty($entry['_deduplicated'])) {
            do_action('paxdesign_session_sync', $session_id, array(
                'is_new'    => false,
                'seq'       => $id,
                'preview'   => $preview,
                'last_role' => $role,
                'handler'   => $handler,
                'service'   => isset($row->detected_service) ? (string) $row->detected_service : '',
            ));
        }

        if (
            $role === 'user'
            && $id > 0
            && class_exists('PAXdesign_Link_Scan_Service')
            && !empty($entry['link_scan_status'])
            && $entry['link_scan_status'] === PAXdesign_Link_Scan_Service::STATUS_CHECKING
        ) {
            PAXdesign_Link_Scan_Service::dispatch_scan($session_id, $id);
        }

        return $entry;
    }

    /**
     * Customer ends an active human chat and returns to the AI assistant.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function customer_release_to_ai($session_id, $user_id = 0) {
        self::upgrade_schema();

        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', __('Invalid session.', 'paxdesign-booking'), array('status' => 400));
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', __('Session not found.', 'paxdesign-booking'), array('status' => 404));
        }

        $user_id = absint($user_id);
        $handler = $this->get_handler($session_id);

        if ($handler === self::HANDLER_AI) {
            return array(
                'handler'    => self::HANDLER_AI,
                'session_id' => $session_id,
                'message'    => null,
            );
        }

        if ($handler === self::HANDLER_CLOSED) {
            if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
                $session_id = PAXdesign_Customer_Chat_Bridge::reopen_closed_session($session_id);
            }
            return array(
                'handler'    => self::HANDLER_AI,
                'session_id' => $session_id,
                'message'    => null,
            );
        }

        if (!in_array($handler, array(self::HANDLER_ADMIN, self::HANDLER_LIVE), true)) {
            return array(
                'handler'    => $handler,
                'session_id' => $session_id,
                'message'    => null,
            );
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

        $notice = 'Der KI-Assistent ist wieder für Sie da. Schreiben Sie jederzeit weiter.';
        $entry = $this->append_message(
            $session_id,
            'system',
            $notice,
            array('client_msg_id' => 'sys:customer_released_to_ai')
        );

        $this->clear_typing($session_id, 'user');
        $this->clear_typing($session_id, 'admin');
        $this->persist_session_last_preview($session_id);
        $this->emit_handler_event($session_id, self::HANDLER_AI);

        return array(
            'handler'    => self::HANDLER_AI,
            'session_id' => $session_id,
            'message'    => $entry,
        );
    }

    public function handle_poll() {
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $user_id = get_current_user_id();
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $session_id = $this->resolve_customer_ajax_session($session_id);
        }
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Invalid session'), 400);
        }

        if ($user_id <= 0) {
            $device_token = $this->device_token_from_request();
            if ($device_token !== '') {
                $this->bind_session_to_device($session_id, $device_token);
            }
        }

        $since = isset($_POST['since']) ? (int) $_POST['since'] : 0;
        $full  = isset($_POST['full']) && wp_unslash($_POST['full']) === '1';
        $history_limit = isset($_POST['history_limit']) ? absint($_POST['history_limit']) : 0;
        $before = isset($_POST['before']) ? absint($_POST['before']) : 0;
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            PAXdesign_Customer_Chat_Bridge::touch_customer_presence($session_id, $user_id);
        }
        $data  = $this->get_poll_data($session_id, $since, $full, 'user', $history_limit, $before);
        if (is_wp_error($data)) {
            if ($data->get_error_code() === 'not_found' && $this->can_serve_unmaterialized_poll($session_id, $user_id)) {
                $data = class_exists('PAXdesign_Customer_Chat_Bridge')
                    ? PAXdesign_Customer_Chat_Bridge::empty_poll_payload($session_id)
                    : $this->empty_poll_payload();
            } else {
                $status = 400;
                $error_data = $data->get_error_data();
                if (is_array($error_data) && !empty($error_data['status'])) {
                    $status = (int) $error_data['status'];
                }
                wp_send_json_error(array('message' => $data->get_error_message()), $status);
            }
        }

        $data['session_id'] = $session_id;
        if ($user_id > 0) {
            $data['auth_user_id'] = $user_id;
        }
        if (!headers_sent()) {
            header('Cache-Control: no-store, private, max-age=0');
            header('Pragma: no-cache');
        }
        wp_send_json_success($data);
    }

    /**
     * Allow poll/readiness for sessions registered to the client but not yet written to chat logs.
     */
    private function can_serve_unmaterialized_poll($session_id, $user_id = 0) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return false;
        }
        $user_id = absint($user_id);
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            if (PAXdesign_Customer_Chat_Bridge::user_owns_session($user_id, $session_id)) {
                return true;
            }
            return $session_id === PAXdesign_Customer_Chat_Bridge::lookup_primary_session_id($user_id);
        }
        return $user_id <= 0;
    }

    public function handle_disconnect() {
        $this->reject_guest_chat();
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $user_id = get_current_user_id();
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            if ($session_id !== '') {
                $session_id = PAXdesign_Customer_Chat_Bridge::resolve_ajax_session($user_id, $session_id);
            }
            if ($session_id !== '') {
                PAXdesign_Customer_Chat_Bridge::mark_customer_disconnected($session_id, $user_id);
            }
            wp_send_json_success(array('ok' => true));
        }

        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Invalid session'), 400);
        }
        wp_send_json_success(array('ok' => true));
    }

    public function handle_stream() {
        if (!$this->verify_chat_nonce()) {
            status_header(403);
            exit;
        }

        $session_id = $this->sanitize_session_id(
            isset($_GET['session_id']) ? wp_unslash($_GET['session_id']) : ''
        );
        if (get_current_user_id() > 0) {
            $session_id = $this->resolve_customer_ajax_session($session_id);
            if ($session_id !== '' && class_exists('PAXdesign_Customer_Chat_Bridge')) {
                PAXdesign_Customer_Chat_Bridge::touch_customer_presence($session_id, get_current_user_id());
            }
        }
        if ($session_id === '') {
            status_header(400);
            exit;
        }

        $since = isset($_GET['since']) ? absint($_GET['since']) : 0;
        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::stream_sse(
                'session:' . $session_id,
                $since,
                PAXdesign_Chat_Event_Bus::STREAM_CHAIN
            );
        }
        exit;
    }

    public function handle_admin_stream() {
        $this->verify_admin_stream_access();
        $this->touch_staff_presence_if_applicable();

        $session_id = $this->sanitize_session_id(
            isset($_GET['session_id']) ? wp_unslash($_GET['session_id']) : ''
        );
        $since_session = isset($_GET['since']) ? absint($_GET['since']) : 0;
        $since_inbox   = isset($_GET['since_inbox']) ? absint($_GET['since_inbox']) : 0;

        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::stream_admin_sse(
                (int) get_current_user_id(),
                $session_id,
                $since_session,
                $since_inbox
            );
        }
        exit;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function emit_handler_event($session_id, $handler, $extra = array()) {
        if (!class_exists('PAXdesign_Chat_Event_Bus')) {
            return;
        }
        PAXdesign_Chat_Event_Bus::emit_session($session_id, 'handler', array_merge(array(
            'handler' => (string) $handler,
        ), $extra));
    }

    /**
     * Emit handler change with the triggering system message for instant list/thread updates.
     *
     * @param array<string, mixed>|null $entry
     * @param array<string, mixed>       $extra
     */
    private function emit_handler_event_with_message($session_id, $handler, $entry, $extra = array()) {
        if (is_array($entry) && !empty($entry['id'])) {
            $extra['message'] = $this->format_sse_message_payload($entry);
            $extra['seq']     = (int) $entry['id'];
        }
        $this->emit_handler_event($session_id, $handler, $extra);
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
        $session_id = $this->resolve_customer_ajax_session($session_id);
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
        $session_id = $this->resolve_customer_ajax_session($session_id);
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
        $session_id = $this->resolve_customer_ajax_session($session_id);
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Invalid session'), 400);
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $lang = isset($_POST['language']) ? sanitize_key(wp_unslash($_POST['language'])) : 'de';
            $result = $this->escalate_authenticated_to_live($session_id, $user_id, $lang);
            if (is_wp_error($result)) {
                $status = 500;
                $error_data = $result->get_error_data();
                if (is_array($error_data) && !empty($error_data['status'])) {
                    $status = (int) $error_data['status'];
                }
                wp_send_json_error(array('message' => $result->get_error_message()), $status);
            }
            wp_send_json_success(array(
                'handler'    => self::HANDLER_LIVE,
                'session_id' => $session_id,
                'messages'   => array_values(array_filter(array(
                    !empty($result['thanks']) ? $result['thanks'] : null,
                    !empty($result['notice']) ? $result['notice'] : null,
                ))),
            ));
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

        if (class_exists('PAXdesign_Language_Routing')) {
            $messages = $this->decode_messages($row->messages);
            $detected = PAXdesign_Language_Routing::detect_from_messages($messages);
            if ($detected !== '') {
                $update['customer_language'] = $detected;
            }
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
        $session_id = $this->resolve_customer_ajax_session($session_id);

        if ($session_id === '' || $content === '') {
            wp_send_json_error(array('message' => 'Invalid payload'), 400);
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $result = PAXdesign_Customer_Chat_Bridge::send_user_message(
                $user_id,
                $session_id,
                $content,
                array(
                    'reply_to'      => isset($_POST['reply_to']) ? (int) $_POST['reply_to'] : 0,
                    'client_msg_id' => isset($_POST['client_msg_id']) ? sanitize_text_field(wp_unslash($_POST['client_msg_id'])) : '',
                )
            );
            if (is_wp_error($result)) {
                $status = 500;
                $error_data = $result->get_error_data();
                if (is_array($error_data) && !empty($error_data['status'])) {
                    $status = (int) $error_data['status'];
                }
                wp_send_json_error(array('message' => $result->get_error_message()), $status);
            }
            wp_send_json_success($result);
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
        $extra['client_msg_id'] = isset($_POST['client_msg_id'])
            ? sanitize_text_field(wp_unslash($_POST['client_msg_id']))
            : '';
        if (class_exists('PAXdesign_Link_Scanner')) {
            $extra = PAXdesign_Link_Scanner::attach_scan_meta($content, 'user', $extra);
        }
        $entry    = $this->append_message($session_id, 'user', $content, $extra);
        if (is_wp_error($entry)) {
            $data = $entry->get_error_data();
            wp_send_json_error(array('message' => $entry->get_error_message()), is_array($data) && !empty($data['status']) ? (int) $data['status'] : 500);
        }
        if (!$entry) {
            wp_send_json_error(array('message' => 'Could not save'), 500);
        }

        $this->clear_typing($session_id, 'user');

        if (empty($entry['_deduplicated']) && class_exists('PAXdesign_Live_Chat_PWA')) {
            PAXdesign_Live_Chat_PWA::notify_new_customer_message($session_id, $content);
        }

        wp_send_json_success(array('message' => $entry));
    }

    public function handle_user_attach() {
        $this->reject_guest_chat();
        if (!$this->verify_chat_nonce()) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $session_id = $this->resolve_customer_ajax_session($session_id);
        $kind       = isset($_POST['kind']) ? sanitize_key(wp_unslash($_POST['kind'])) : 'file';
        $client_id  = isset($_POST['client_msg_id']) ? sanitize_text_field(wp_unslash($_POST['client_msg_id'])) : '';

        if ($session_id === '' || empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('Please choose a file to upload.', 'paxdesign-booking')), 400);
        }

        $kind = $kind === 'image' ? 'image' : 'file';
        $file = $_FILES['file'];
        $name = isset($file['name']) ? (string) $file['name'] : '';
        $ext  = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $blocked_ext = array('php', 'phtml', 'phar', 'php5', 'php7', 'php8', 'js', 'mjs', 'html', 'htm', 'shtml', 'svg', 'exe', 'sh', 'bash', 'htaccess', 'cgi');
        if ($ext === '' || in_array($ext, $blocked_ext, true)) {
            wp_send_json_error(array('message' => __('File type is not allowed.', 'paxdesign-booking')), 400);
        }

        $max_bytes = $kind === 'image' ? 5242880 : 8388608;
        if (!empty($file['size']) && (int) $file['size'] > $max_bytes) {
            wp_send_json_error(array(
                'message' => $kind === 'image'
                    ? __('Images must be 5 MB or smaller.', 'paxdesign-booking')
                    : __('Files must be 8 MB or smaller.', 'paxdesign-booking'),
            ), 400);
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge') && method_exists('PAXdesign_Customer_Chat_Bridge', 'send_user_attachment')) {
            $result = PAXdesign_Customer_Chat_Bridge::send_user_attachment(
                $user_id,
                $session_id,
                $kind,
                $file,
                '',
                array('client_msg_id' => $client_id)
            );
            if (is_wp_error($result)) {
                $status = 500;
                $error_data = $result->get_error_data();
                if (is_array($error_data) && !empty($error_data['status'])) {
                    $status = (int) $error_data['status'];
                }
                wp_send_json_error(array('message' => $result->get_error_message()), $status);
            }
            wp_send_json_success($result);
        }

        $handler = $this->get_handler($session_id);
        if ($handler === self::HANDLER_CLOSED) {
            wp_send_json_error(array('message' => 'Chat geschlossen.'), 409);
        }
        if (!$this->is_human_queue($session_id)) {
            wp_send_json_error(array('message' => __('Attachments are available during human support.', 'paxdesign-booking')), 409);
        }

        $this->ensure_session($session_id);

        if (!class_exists('PAXdesign_Customer_Media')) {
            wp_send_json_error(array('message' => __('Uploads are unavailable.', 'paxdesign-booking')), 500);
        }

        $upload = PAXdesign_Customer_Media::handle_upload($file, $kind);
        if (is_wp_error($upload)) {
            wp_send_json_error(array('message' => $upload->get_error_message()), 400);
        }

        $caption = sanitize_file_name((string) ($upload['name'] ?? ($file['name'] ?? 'file')));
        $extra = array(
            'client_msg_id' => $client_id,
        );
        if ($kind === 'image') {
            $extra['image_url'] = $upload['url'];
            $extra['attachment_type'] = 'image';
        } else {
            $extra['file_url'] = $upload['url'];
            $extra['file_name'] = $caption;
            $extra['file_mime'] = (string) ($upload['mime'] ?? '');
            $extra['attachment_type'] = 'file';
        }

        $entry = $this->append_message($session_id, 'user', $caption, $extra);
        if (is_wp_error($entry)) {
            $data = $entry->get_error_data();
            wp_send_json_error(array('message' => $entry->get_error_message()), is_array($data) && !empty($data['status']) ? (int) $data['status'] : 500);
        }
        if (!$entry) {
            wp_send_json_error(array('message' => 'Could not save'), 500);
        }

        $this->clear_typing($session_id, 'user');

        if (empty($entry['_deduplicated']) && class_exists('PAXdesign_Live_Chat_PWA')) {
            PAXdesign_Live_Chat_PWA::notify_new_customer_message($session_id, $caption);
        }

        wp_send_json_success(array('message' => $entry));
    }

    public function handle_live_list() {
        $this->verify_admin_nonce();
        $this->touch_staff_presence_if_applicable();

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

    public function handle_live_presence() {
        $this->verify_admin_nonce();
        $this->touch_staff_presence_if_applicable();
        wp_send_json_success(array('ok' => true));
    }

    public function handle_tour_complete() {
        $this->verify_admin_nonce();
        $completed = isset($_POST['completed']) && (string) wp_unslash($_POST['completed']) === '1';
        update_user_meta(get_current_user_id(), 'pax_live_dashboard_tour_completed', $completed ? 1 : 0);
        wp_send_json_success(array('completed' => $completed));
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

        $user_id = get_current_user_id();
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $sessions = PAXdesign_Customer_Chat_Bridge::list_user_sessions($user_id);
            wp_send_json_success(array('sessions' => $sessions));
        }

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
                'updated_at'     => PAXdesign_API_Time::format(isset($row->updated_at) ? (string) $row->updated_at : '', false),
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
        $session_id = $this->resolve_customer_ajax_session($session_id);
        $user_id = get_current_user_id();

        if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            if ($session_id === '' || !PAXdesign_Customer_Chat_Bridge::user_owns_session($user_id, $session_id)) {
                wp_send_json_error(array('message' => 'Access denied'), 403);
            }
            $row = $this->get_session_row($session_id);
            if (!$row) {
                wp_send_json_error(array('message' => 'Not found'), 404);
            }
            $messages = $this->sort_messages($this->decode_messages($row->messages));
            if (class_exists('PAXdesign_Customer_Chat_Bridge')) {
                $messages = PAXdesign_Customer_Chat_Bridge::filter_customer_lifecycle_messages($messages);
            }
            wp_send_json_success(array(
                'session_id'     => $row->session_id,
                'customer_name'  => isset($row->customer_name) ? (string) $row->customer_name : '',
                'updated_at'     => PAXdesign_API_Time::format(isset($row->updated_at) ? (string) $row->updated_at : '', false),
                'started_at'     => $row->started_at,
                'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
                'messages'       => $messages,
            ));
        }

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
            'updated_at'     => PAXdesign_API_Time::format(isset($row->updated_at) ? (string) $row->updated_at : '', false),
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
        $session_id = $this->resolve_customer_ajax_session($session_id);
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Invalid session'), 400);
        }

        $device_token = $this->device_token_from_request();
        if ($device_token !== '' && !$this->verify_session_ownership($session_id, $device_token)) {
            $probe = $this->get_session_row($session_id);
            $existing_hash = ($probe && isset($probe->device_token_hash)) ? (string) $probe->device_token_hash : '';
            if ($existing_hash === '') {
                $this->bind_session_to_device($session_id, $device_token);
            }
            // Customer-initiated close: allow even when token drifted (e.g. cleared storage).
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

        $wp_user_id = isset($row->wp_user_id) ? (int) $row->wp_user_id : 0;
        if ($wp_user_id > 0) {
            $result = $this->customer_release_to_ai($session_id, $wp_user_id);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 400);
            }
            wp_send_json_success(array(
                'handler'        => $result['handler'],
                'session_rating' => isset($row->session_rating) ? (int) $row->session_rating : 0,
                'message'        => $result['message'],
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
            'Der Kunde hat das Gespräch beendet.',
            array('client_msg_id' => 'sys:customer_closed')
        );

        $this->persist_session_last_preview($session_id);
        $this->emit_handler_event($session_id, self::HANDLER_CLOSED);

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
        $session_id = $this->resolve_customer_ajax_session($session_id);
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
            $probe = $this->get_session_row($session_id);
            $existing_hash = ($probe && isset($probe->device_token_hash)) ? (string) $probe->device_token_hash : '';
            if ($existing_hash === '') {
                $this->bind_session_to_device($session_id, $device_token);
            }
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
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!class_exists('PAXdesign_Live_Chat_Permissions') || !PAXdesign_Live_Chat_Permissions::can_takeover_chats()) {
            wp_send_json_error(array('message' => __('Keine Berechtigung.', 'paxdesign-booking')), 403);
        }

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $result = $this->admin_takeover($session_id);
        if (is_wp_error($result)) {
            $status = 500;
            $error_data = $result->get_error_data();
            if (is_array($error_data) && !empty($error_data['status'])) {
                $status = (int) $error_data['status'];
            }
            wp_send_json_error(array('message' => $result->get_error_message()), $status);
        }

        wp_send_json_success($result);
    }

    public function handle_decline() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $result = $this->admin_decline_live_request($session_id);
        if (is_wp_error($result)) {
            $status = 409;
            $error_data = $result->get_error_data();
            if (is_array($error_data) && !empty($error_data['status'])) {
                $status = (int) $error_data['status'];
            }
            wp_send_json_error(array('message' => $result->get_error_message()), $status);
        }

        wp_send_json_success($result);
    }

    public function handle_release() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $result = $this->admin_release($session_id);
        if (is_wp_error($result)) {
            $status = 500;
            $error_data = $result->get_error_data();
            if (is_array($error_data) && !empty($error_data['status'])) {
                $status = (int) $error_data['status'];
            }
            wp_send_json_error(array('message' => $result->get_error_message()), $status);
        }

        wp_send_json_success($result);
    }

    public function handle_close() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        if ($session_id === '') {
            wp_send_json_error(array('message' => 'Ungültige Session.'), 400);
        }

        $result = $this->admin_close($session_id);
        if (is_wp_error($result)) {
            $status = 500;
            $error_data = $result->get_error_data();
            if (is_array($error_data) && !empty($error_data['status'])) {
                $status = (int) $error_data['status'];
            }
            wp_send_json_error(array('message' => $result->get_error_message()), $status);
        }

        wp_send_json_success($result);
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
        $extra['client_msg_id'] = isset($_POST['client_msg_id'])
            ? sanitize_text_field(wp_unslash($_POST['client_msg_id']))
            : '';
        $entry    = $this->append_message($session_id, 'admin', $content, $extra);
        if (is_wp_error($entry)) {
            $data = $entry->get_error_data();
            wp_send_json_error(array('message' => $entry->get_error_message()), is_array($data) && !empty($data['status']) ? (int) $data['status'] : 500);
        }
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

        $ensure = $this->ensure_admin_handler_for_agent($session_id);
        if (is_wp_error($ensure)) {
            return $ensure;
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        $messages = $this->session_messages_for_agent($session_id);
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
        $customer_language = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::session_language_from_row($row)
            : '';
        $result = $chat->generate_admin_reply_suggestions($messages, $target, array(
            'service'            => isset($row->detected_service) ? (string) $row->detected_service : '',
            'customer_name'      => isset($row->customer_name) ? (string) $row->customer_name : '',
            'customer_language'  => $customer_language,
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
    public function handle_admin_delete_message() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $message_id = isset($_POST['message_id']) ? absint($_POST['message_id']) : 0;

        if ($session_id === '' || $message_id <= 0) {
            wp_send_json_error(array('message' => 'Ungültige Nachricht.'), 400);
        }

        if (!$this->is_admin_handler($session_id)) {
            wp_send_json_error(array('message' => 'Chat nicht übernommen.'), 409);
        }

        $result = $this->admin_delete_message($session_id, $message_id);
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            wp_send_json_error(
                array('message' => $result->get_error_message()),
                is_array($data) && !empty($data['status']) ? (int) $data['status'] : 500
            );
        }

        wp_send_json_success($result);
    }

    public function handle_admin_link_review() {
        $this->verify_admin_nonce();

        $session_id = $this->sanitize_session_id(
            isset($_POST['session_id']) ? wp_unslash($_POST['session_id']) : ''
        );
        $message_id = isset($_POST['message_id']) ? absint($_POST['message_id']) : 0;
        $action = isset($_POST['review_action']) ? sanitize_key(wp_unslash($_POST['review_action'])) : '';

        if ($session_id === '' || $message_id <= 0 || $action === '') {
            wp_send_json_error(array('message' => 'Ungültige Anfrage.'), 400);
        }

        if (!$this->is_admin_handler($session_id)) {
            wp_send_json_error(array('message' => 'Chat nicht übernommen.'), 409);
        }

        $result = $this->admin_apply_link_review($session_id, $message_id, $action);
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            wp_send_json_error(
                array('message' => $result->get_error_message()),
                is_array($data) && !empty($data['status']) ? (int) $data['status'] : 500
            );
        }

        wp_send_json_success($result);
    }

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
        $client_id = isset($_POST['client_msg_id']) ? sanitize_text_field(wp_unslash($_POST['client_msg_id'])) : '';
        $result   = $this->admin_send_image($session_id, $_FILES['image'], $caption, $reply_to, $client_id);

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
    public function admin_send_image($session_id, array $file, $caption = '', $reply_to = 0, $client_msg_id = '') {
        $session_id = $this->sanitize_session_id($session_id);
        $client_msg_id = PAXdesign_Message_Store::normalize_client_id($client_msg_id);

        if ($session_id === '' || empty($file)) {
            return new WP_Error('invalid_payload', 'Kein Bild übermittelt.', array('status' => 400));
        }

        $ensure = $this->ensure_admin_handler_for_agent($session_id);
        if (is_wp_error($ensure)) {
            return $ensure;
        }
        global $wpdb;
        $lock_name = 'pax_msg_' . md5($session_id);
        $locked = class_exists('PAXdesign_DB')
            ? PAXdesign_DB::acquire_named_lock($lock_name, 10)
            : (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name));
        if ($locked !== 1) {
            return new WP_Error('pax_message_busy', 'Chat ist beschäftigt. Bitte erneut versuchen.', array('status' => 503));
        }
        try {
            $existing = PAXdesign_Message_Store::find_by_client_id($session_id, $client_msg_id);
            if ($existing) {
                return array('message' => $existing);
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
        $extra     = array(
            'image_url' => $image_url,
            'client_msg_id' => $client_msg_id,
            'lock_already_held' => true,
        );
        if ($reply_to > 0) {
            $extra['reply_to'] = $reply_to;
        }

        $entry = $this->append_message($session_id, 'admin', $caption, $extra);
        if (is_wp_error($entry)) {
            if (!empty($upload['file']) && file_exists($upload['file'])) {
                wp_delete_file($upload['file']);
            }
            return $entry;
        }
        if (!$entry) {
            return new WP_Error('save_failed', 'Speichern fehlgeschlagen.', array('status' => 500));
        }

        $this->clear_typing($session_id, 'admin');

        return array('message' => $entry);
        } finally {
            if (class_exists('PAXdesign_DB')) {
                PAXdesign_DB::release_named_lock($lock_name);
            } else {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            }
        }
    }

    /**
     * Resize uploaded chat images so they stay lightweight in the thread.
     */
    public function optimize_chat_image_public($file_path, $url) {
        return $this->optimize_chat_image($file_path, $url);
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
    const DEFAULT_WHATSAPP_PHONE = '4368120543638';

    /**
     * Escalate an authenticated mobile/web customer session to the live-agent queue.
     *
     * @param string $session_id
     * @param int    $user_id
     * @param string $language de|en|ar
     * @return array{thanks:array|null,notice:array|null}|WP_Error
     */
    public function escalate_authenticated_to_live($session_id, $user_id = 0, $language = 'de') {
        self::upgrade_schema();

        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', __('Invalid session.', 'paxdesign-booking'), array('status' => 400));
        }

        $row = $this->ensure_session($session_id);
        if (!$row) {
            return new WP_Error('session_failed', __('Session could not be created.', 'paxdesign-booking'), array('status' => 500));
        }

        $handler = $this->get_handler($session_id);
        if ($handler === self::HANDLER_ADMIN) {
            return new WP_Error('admin_active', __('A team member is already active.', 'paxdesign-booking'), array('status' => 409));
        }
        if ($handler === self::HANDLER_CLOSED) {
            if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
                $session_id = PAXdesign_Customer_Chat_Bridge::reopen_closed_session($session_id);
                $this->ensure_session($session_id);
                $handler = $this->get_handler($session_id);
            } else {
                return new WP_Error('chat_closed', __('This conversation is closed.', 'paxdesign-booking'), array('status' => 409));
            }
        }
        if ($handler === self::HANDLER_LIVE) {
            return array(
                'thanks'  => null,
                'notice'  => null,
                'handler' => self::HANDLER_LIVE,
            );
        }

        $user_id = absint($user_id);
        $customer_name = '';
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if ($user) {
                $customer_name = trim(preg_replace('/\s+/', ' ', (string) $user->display_name));
                if (strlen($customer_name) < 2) {
                    $customer_name = (string) $user->user_login;
                }
            }
        }
        if (strlen($customer_name) < 2) {
            $customer_name = __('Customer', 'paxdesign-booking');
        }

        $lang = sanitize_key((string) $language);
        if ($lang === '') {
            $lang = 'de';
        }

        global $wpdb;
        $update = array(
            'handler'       => self::HANDLER_LIVE,
            'admin_user_id' => 0,
            'admin_name'    => '',
            'updated_at'    => current_time('mysql'),
            'customer_name' => $customer_name,
        );
        if ($user_id > 0) {
            $update['wp_user_id'] = $user_id;
        }
        if (class_exists('PAXdesign_Language_Routing')) {
            $messages = $this->decode_messages($row->messages);
            $detected = PAXdesign_Language_Routing::detect_from_messages($messages);
            if ($detected !== '') {
                $update['customer_language'] = $detected;
                $lang = $detected;
            } else {
                $update['customer_language'] = $lang;
            }
        }

        $updated = $wpdb->update(
            PAXdesign_Chat_Log::table_name(),
            $update,
            array('id' => (int) $row->id)
        );
        if ($updated === false) {
            return new WP_Error('save_failed', __('Could not save live request.', 'paxdesign-booking'), array('status' => 500));
        }

        $thanks_text = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::live_handoff_thanks_message($lang)
            : 'Danke. Ich leite Sie jetzt an einen PAXDesign-Mitarbeiter weiter.';
        $notice_text = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::live_handoff_notice_message($lang)
            : 'Ein PAXDesign-Mitarbeiter wurde informiert. Bitte bleiben Sie kurz im Chat.';

        $thanks = $this->append_message($session_id, 'assistant', $thanks_text);
        $notice = $this->append_message($session_id, 'system', $notice_text);

        $row = $this->get_session_row($session_id);
        $topic = isset($row->detected_service) ? (string) $row->detected_service : '';
        $this->notify_live_agent_request($session_id, $topic, $row);
        $this->emit_handler_event($session_id, self::HANDLER_LIVE);

        return array(
            'thanks'  => $thanks,
            'notice'  => $notice,
            'handler' => self::HANDLER_LIVE,
        );
    }

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

        $language = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::session_language_from_row($row)
            : '';
        do_action('paxdesign_live_agent_requested_language', $session_id, $language, $service, $preview, $admin_url, $customer);
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
            return '';
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
    public function get_live_list_data($include_threads = false) {
        self::ensure_list_schema();

        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();

        $rows = $wpdb->get_results(
            "SELECT id, session_id, handler, admin_user_id, admin_name, customer_name,
                    session_rating, detected_service, updated_at, message_count, message_seq,
                    last_preview, customer_language, user_read_seq, admin_read_seq, wp_user_id
             FROM $table
             WHERE session_id LIKE 'pax_u%'
             ORDER BY
               CASE COALESCE(handler, 'ai')
                 WHEN 'live_request' THEN 0
                 WHEN 'admin' THEN 1
                 WHEN 'closed' THEN 3
                 ELSE 2
               END,
               updated_at DESC
             LIMIT 250"
        );

        if ($rows === null && $wpdb->last_error) {
            return new WP_Error('db_error', 'Datenbankfehler: ' . $wpdb->last_error, array('status' => 500));
        }

        $sessions   = array();
        $live_count = 0;
        $threads    = array();
        foreach ((array) $rows as $row) {
            $session_id = isset($row->session_id) ? (string) $row->session_id : '';
            $wp_user_id = isset($row->wp_user_id) ? (int) $row->wp_user_id : 0;
            if ($session_id !== '' && ($wp_user_id <= 0 || trim((string) ($row->customer_name ?? '')) === '')) {
                $resolved_user = self::user_id_from_session_id($session_id);
                if ($resolved_user > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
                    PAXdesign_Customer_Chat_Bridge::sync_customer_profile($session_id, $resolved_user);
                    $fresh = $this->get_session_row($session_id);
                    if ($fresh) {
                        $row = $fresh;
                    }
                }
            }
            $item = $this->format_live_list_session($row);
            if ($item['handler'] === self::HANDLER_LIVE) {
                $live_count++;
            }
            $sessions[] = $item;
            if ($include_threads) {
                $session_id = isset($row->session_id) ? (string) $row->session_id : '';
                if ($session_id !== '') {
                    $thread = $this->get_poll_data($session_id, 0, true);
                    if (!is_wp_error($thread)) {
                        $threads[$session_id] = $thread;
                    }
                }
            }
        }

        return array(
            'sessions'    => $sessions,
            'live_count'  => $live_count,
            'server_time' => PAXdesign_API_Time::format(current_time('mysql'), false),
            'threads'     => $threads,
        );
    }

    /**
     * @param object $row
     * @return array<string, mixed>
     */
    private function format_live_list_session($row) {
        $preview = isset($row->last_preview) ? trim((string) $row->last_preview) : '';
        $last_role = '';
        if (isset($row->messages) && (string) $row->messages !== '') {
            $messages = $this->sort_messages($this->decode_messages($row->messages));
            $last = !empty($messages) ? end($messages) : null;
            if ($preview === '') {
                $preview = is_array($last) && !empty($last['content'])
                    ? wp_html_excerpt($last['content'], 100, '…')
                    : '';
            }
            $last_role = is_array($last) && !empty($last['role']) ? (string) $last['role'] : '';
        }
        $session_id = isset($row->session_id) ? (string) $row->session_id : '';
        if ($last_role === '' && $session_id !== '' && class_exists('PAXdesign_Message_Store')) {
            $last_role = PAXdesign_Message_Store::last_message_role($session_id, 'customer');
        }
        $handler  = isset($row->handler) ? (string) $row->handler : self::HANDLER_AI;
        $agent    = self::session_agent_payload($row);
        $wp_user_id = isset($row->wp_user_id) ? (int) $row->wp_user_id : 0;
        if ($wp_user_id <= 0) {
            $wp_user_id = self::user_id_from_session_id($session_id);
        }
        $customer_name = isset($row->customer_name) ? trim((string) $row->customer_name) : '';
        if ($customer_name === '' && $wp_user_id > 0) {
            $customer_name = self::resolve_customer_identity($wp_user_id, '')->name;
        }

        $message_seq = isset($row->message_seq) ? (int) $row->message_seq : 0;
        $admin_read_seq = isset($row->admin_read_seq) ? (int) $row->admin_read_seq : 0;
        $needs_reply = ($handler !== self::HANDLER_CLOSED && $last_role === 'user')
            && ($handler === self::HANDLER_ADMIN || $handler === self::HANDLER_LIVE);
        $unread_count = 0;
        if ($handler !== self::HANDLER_CLOSED && $last_role === 'user' && $session_id !== ''
            && class_exists('PAXdesign_Message_Store')) {
            $unread_count = PAXdesign_Message_Store::count_incoming_messages_since(
                $session_id,
                $admin_read_seq,
                'customer'
            );
        }

        return array(
            'id'               => isset($row->id) ? (int) $row->id : 0,
            'session_id'       => $session_id,
            'handler'          => $handler,
            'handler_label'    => self::handler_label($handler, $agent['admin_name']),
            'admin_user_id'    => $agent['admin_user_id'],
            'admin_name'       => $agent['admin_name'],
            'assigned_agent'   => $agent['assigned_agent'],
            'customer_name'    => $customer_name,
            'wp_user_id'       => $wp_user_id,
            'session_rating'   => isset($row->session_rating) ? (int) $row->session_rating : 0,
            'detected_service' => isset($row->detected_service) ? (string) $row->detected_service : '',
            'updated_at'       => PAXdesign_API_Time::format(isset($row->updated_at) ? (string) $row->updated_at : '', false),
            'message_count'    => isset($row->message_count) ? (int) $row->message_count : 0,
            'seq'              => $message_seq,
            'last_preview'     => $preview,
            'last_role'        => $last_role,
            'needs_reply'      => $needs_reply,
            'unread_count'     => $unread_count,
            'admin_read_seq'   => $admin_read_seq,
            'customer_language'=> class_exists('PAXdesign_Language_Routing')
                ? PAXdesign_Language_Routing::session_language_from_row($row)
                : '',
        );
    }

    /**
     * Normalize a single message for SSE instant-render payloads (iOS/web).
     *
     * @param array<string, mixed> $msg
     * @param int                  $fallback_agent_id
     * @return array<string, mixed>
     */
    public function format_sse_message_payload($msg, $fallback_agent_id = 0) {
        if (!is_array($msg)) {
            return array();
        }
        $formatted = $this->format_messages_for_api(array($msg), absint($fallback_agent_id), array());
        return !empty($formatted) ? $formatted[0] : $msg;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed>             $session_context
     * @return array<int, array<string, mixed>>
     */
    private function format_messages_for_api($messages, $fallback_agent_id = 0, $session_context = array()) {
        $out = array();
        foreach ($this->sort_messages($messages) as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $role  = isset($msg['role']) ? sanitize_text_field($msg['role']) : 'assistant';
            $seq   = isset($msg['seq']) ? (int) $msg['seq'] : (isset($msg['id']) ? (int) $msg['id'] : 0);
            $entry = array(
                'id'      => $seq,
                'seq'     => $seq,
                'role'    => $role,
                'content' => isset($msg['content']) ? (string) $msg['content'] : '',
            );
            if (!empty($msg['client_msg_id'])) {
                $entry['client_msg_id'] = sanitize_text_field((string) $msg['client_msg_id']);
            }
            if (isset($msg['ts'])) {
                $entry['ts'] = (int) $msg['ts'];
            }
            if (!empty($msg['image_url'])) {
                $entry['image_url'] = esc_url_raw($msg['image_url']);
            }
            if (!empty($msg['reply_to'])) {
                $entry['reply_to'] = (int) $msg['reply_to'];
            }
            if (!empty($msg['reaction'])) {
                $entry['reaction'] = sanitize_text_field((string) $msg['reaction']);
            }
            if (!empty($msg['attachment_type'])) {
                $entry['attachment_type'] = sanitize_text_field((string) $msg['attachment_type']);
            }
            if (!empty($msg['audio_url'])) {
                $entry['audio_url'] = esc_url_raw((string) $msg['audio_url']);
            }
            if (isset($msg['audio_duration'])) {
                $entry['audio_duration'] = (float) $msg['audio_duration'];
            }
            if (!empty($msg['audio_waveform']) && is_array($msg['audio_waveform'])) {
                $entry['audio_waveform'] = array_map('floatval', array_values($msg['audio_waveform']));
            }
            if (!empty($msg['link_url'])) {
                $entry['link_url'] = esc_url_raw((string) $msg['link_url']);
            }
            if (!empty($msg['link_label'])) {
                $entry['link_label'] = sanitize_text_field((string) $msg['link_label']);
            }
            if (!empty($msg['link_icon'])) {
                $entry['link_icon'] = sanitize_text_field((string) $msg['link_icon']);
            }
            if ($role === 'user' && !empty($msg['link_scan_status'])) {
                $entry['link_scan_status'] = sanitize_text_field((string) $msg['link_scan_status']);
            }
            if ($role === 'user' && !empty($msg['link_scan_system_status'])) {
                $entry['link_scan_system_status'] = sanitize_text_field((string) $msg['link_scan_system_status']);
            }
            if ($role === 'user' && !empty($msg['link_scan_review_pending'])) {
                $entry['link_scan_review_pending'] = sanitize_text_field((string) $msg['link_scan_review_pending']);
            }
            if ($role === 'user' && !empty($msg['link_scan_urls'])) {
                $entry['link_scan_urls'] = (string) $msg['link_scan_urls'];
            }
            if ($role === 'user' && !empty($msg['link_scan_started_at'])) {
                $entry['link_scan_started_at'] = absint($msg['link_scan_started_at']);
            }
            if ($role === 'user' && !empty($msg['link_scan_completed_at'])) {
                $entry['link_scan_completed_at'] = absint($msg['link_scan_completed_at']);
            }
            if ($role === 'user' && !empty($msg['link_scan_provider'])) {
                $entry['link_scan_provider'] = sanitize_text_field((string) $msg['link_scan_provider']);
            }
            if ($role === 'user' && !empty($msg['link_scan_label'])) {
                $entry['link_scan_label'] = sanitize_text_field((string) $msg['link_scan_label']);
            }
            if ($role === 'user' && !empty($msg['link_scan_analysis'])) {
                $entry['link_scan_analysis'] = sanitize_textarea_field((string) $msg['link_scan_analysis']);
            }
            if ($role === 'user' && !empty($msg['link_scan_frame'])) {
                $entry['link_scan_frame'] = absint($msg['link_scan_frame']);
            }
            if ($role === 'user' && !empty($msg['link_scan_original_content'])) {
                $entry['link_scan_original_content'] = sanitize_textarea_field((string) $msg['link_scan_original_content']);
            }
            if ($role === 'admin') {
                $sender_id = !empty($msg['sender_id']) ? absint($msg['sender_id']) : 0;
                if ($sender_id <= 0 && $fallback_agent_id > 0) {
                    $sender_id = absint($fallback_agent_id);
                }
                if (!empty($msg['sender_name'])) {
                    $entry['sender_id']     = $sender_id;
                    $entry['sender_name']   = sanitize_text_field((string) $msg['sender_name']);
                    $entry['sender_avatar'] = !empty($msg['sender_avatar']) ? esc_url_raw((string) $msg['sender_avatar']) : '';
                    $entry['sender_role']   = !empty($msg['sender_role']) ? sanitize_text_field((string) $msg['sender_role']) : '';
                } elseif ($sender_id > 0) {
                    $identity = self::resolve_employee_identity($sender_id);
                    if ($identity) {
                        $entry['sender_id']     = $identity['id'];
                        $entry['sender_name']   = $identity['name'];
                        $entry['sender_avatar'] = $identity['avatar'];
                        $entry['sender_role']   = $identity['role'];
                    }
                }
            }
            if ($role === 'assistant') {
                $ai = self::get_ai_assistant_identity();
                $entry['sender_id']     = 0;
                $entry['sender_name']   = $ai['name'];
                $entry['sender_avatar'] = $ai['avatar'];
                $entry['sender_role']   = $ai['role'];
            }
            if ($role === 'user') {
                $customer_user_id = !empty($msg['sender_id']) ? absint($msg['sender_id']) : 0;
                if ($customer_user_id <= 0 && !empty($session_context['wp_user_id'])) {
                    $customer_user_id = absint($session_context['wp_user_id']);
                }
                $customer_name = !empty($session_context['customer_name'])
                    ? (string) $session_context['customer_name']
                    : '';
                $identity = self::resolve_customer_identity($customer_user_id, $customer_name);
                $entry['sender_id']     = $identity['id'];
                $entry['sender_name']   = $identity['name'];
                $entry['sender_avatar'] = $identity['avatar'];
                $entry['sender_role']   = $identity['role'];
            }
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function empty_poll_payload() {
        return array(
            'handler'          => self::HANDLER_AI,
            'handler_label'    => self::handler_label(self::HANDLER_AI),
            'admin_user_id'    => 0,
            'admin_name'       => '',
            'assigned_agent'   => null,
            'customer_name'    => '',
            'session_rating'   => 0,
            'detected_service' => '',
            'updated_at'       => '',
            'seq'              => 0,
            'message_count'    => 0,
            'messages'         => array(),
            'admin_typing'     => false,
            'assistant_typing' => false,
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
        $agent   = self::session_agent_payload($row);
        $raw_messages = class_exists('PAXdesign_Message_Store')
            ? PAXdesign_Message_Store::all_messages($session_id, 'customer')
            : $this->decode_messages($row->messages);
        $all_messages = $this->format_messages_for_api($raw_messages, $agent['admin_user_id'], array(
            'wp_user_id'    => isset($row->wp_user_id) ? (int) $row->wp_user_id : 0,
            'customer_name' => isset($row->customer_name) ? (string) $row->customer_name : '',
        ));
        $authoritative_seq = class_exists('PAXdesign_Message_Store')
            ? PAXdesign_Message_Store::latest_seq($session_id, 'customer')
            : (isset($row->message_seq) ? (int) $row->message_seq : 0);

        return array(
            'session_id'       => isset($row->session_id) ? (string) $row->session_id : '',
            'handler'          => $handler,
            'handler_label'    => self::handler_label($handler, $agent['admin_name']),
            'admin_user_id'    => $agent['admin_user_id'],
            'admin_name'       => $agent['admin_name'],
            'assigned_agent'   => $agent['assigned_agent'],
            'customer_name'    => isset($row->customer_name) ? (string) $row->customer_name : '',
            'session_rating'   => isset($row->session_rating) ? (int) $row->session_rating : 0,
            'detected_service' => isset($row->detected_service) ? (string) $row->detected_service : '',
            'updated_at'       => PAXdesign_API_Time::format(isset($row->updated_at) ? (string) $row->updated_at : '', false),
            'seq'              => $authoritative_seq,
            'message_count'    => count($all_messages),
            'last_read_seq'    => $this->get_read_seqs($row)['user'],
            'admin_read_seq'   => $this->get_read_seqs($row)['admin'],
            'other_read_seq'   => $this->get_read_seqs($row)['user'],
            'messages'         => $all_messages,
            'admin_typing'     => $this->is_typing($session_id, 'admin'),
            'assistant_typing' => $this->is_typing($session_id, 'assistant'),
            'user_typing'      => $this->is_typing($session_id, 'user'),
            'reactions'        => $this->extract_message_reactions($raw_messages),
            'customer_language'=> class_exists('PAXdesign_Language_Routing')
                ? PAXdesign_Language_Routing::session_language_from_row($row)
                : '',
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_poll_data($session_id, $since = 0, $full = false, $mark_read = '', $history_limit = 0, $before = 0) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Invalid session', array('status' => 400));
        }

        $since = (int) $since;
        $history_limit = max(0, min(100, absint($history_limit)));
        $before = absint($before);
        $history_window = $history_limit > 0;
        $row   = $this->get_session_row($session_id);

        if (!$row) {
            return new WP_Error('not_found', 'Session not found', array('status' => 404));
        }

        $wp_user_id = isset($row->wp_user_id) ? (int) $row->wp_user_id : 0;
        if ($wp_user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $session_id = PAXdesign_Customer_Chat_Bridge::ensure_persistent_session_open($session_id, $wp_user_id);
            $row = $this->get_session_row($session_id);
            if (!$row) {
                return new WP_Error('not_found', 'Session not found', array('status' => 404));
            }
        }

        $agent    = self::session_agent_payload($row);
        $session_context = array(
            'wp_user_id'    => isset($row->wp_user_id) ? (int) $row->wp_user_id : 0,
            'customer_name' => isset($row->customer_name) ? (string) $row->customer_name : '',
        );
        $has_older = false;
        $oldest_seq = 0;
        if (class_exists('PAXdesign_Message_Store')) {
            if ($before > 0 && $history_window) {
                $new = PAXdesign_Message_Store::messages_before($session_id, $before, $history_limit, 'customer');
                $all = null;
                if (!empty($new)) {
                    $oldest_seq = (int) $new[0]['id'];
                    $has_older = PAXdesign_Message_Store::has_older_than($session_id, $oldest_seq, 'customer');
                }
            } elseif ($full && $history_window) {
                $new = PAXdesign_Message_Store::latest_messages($session_id, $history_limit, 'customer');
                $all = null;
                if (!empty($new)) {
                    $oldest_seq = (int) $new[0]['id'];
                    $has_older = PAXdesign_Message_Store::has_older_than($session_id, $oldest_seq, 'customer');
                }
            } elseif ($full) {
                $all = PAXdesign_Message_Store::all_messages($session_id, 'customer');
                $new = $all;
            } else {
                $new = PAXdesign_Message_Store::messages_since($session_id, $since, 500, 'customer');
                $all = null;
            }
            $message_seq = PAXdesign_Message_Store::latest_seq($session_id, 'customer');
            $message_count = ($full && !$history_window)
                ? count($all)
                : PAXdesign_Message_Store::count($session_id, 'customer');
            $reactions_map = ($full && !$history_window)
                ? null
                : PAXdesign_Message_Store::reactions_map($session_id, 'customer');
        } else {
            $messages = $this->decode_messages($row->messages);
            $all = $this->format_messages_for_api($messages, $agent['admin_user_id'], $session_context);
            if ($before > 0 && $history_window) {
                $new = array_values(array_filter($all, function ($msg) use ($before) {
                    return isset($msg['id']) && (int) $msg['id'] < $before;
                }));
                $new = array_slice($new, -$history_limit);
                if (!empty($new)) {
                    $oldest_seq = (int) $new[0]['id'];
                    $has_older = count(array_filter($all, function ($msg) use ($oldest_seq) {
                        return isset($msg['id']) && (int) $msg['id'] < $oldest_seq;
                    })) > 0;
                }
            } elseif ($full && $history_window) {
                $new = array_slice($all, -$history_limit);
                if (!empty($new)) {
                    $oldest_seq = (int) $new[0]['id'];
                    $has_older = count($all) > count($new);
                }
            } else {
                $new = $full ? $all : array_values(array_filter($all, function ($msg) use ($since) {
                    return isset($msg['id']) && (int) $msg['id'] > $since;
                }));
            }
            $message_seq = isset($row->message_seq) ? (int) $row->message_seq : 0;
            $message_count = count($all);
            $reactions_map = null;
        }
        if ($all !== null && !$full) {
            $all = $this->format_messages_for_api($all, $agent['admin_user_id'], $session_context);
        }
        $new = $this->format_messages_for_api($new, $agent['admin_user_id'], $session_context);
        if (class_exists('PAXdesign_Message_Store')) {
            $new = PAXdesign_Message_Store::mask_messages_for_customer($new, $session_id);
            if ($all !== null && !$full) {
                $all = PAXdesign_Message_Store::mask_messages_for_customer($all, $session_id);
            }
        }
        if ($wp_user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $new = PAXdesign_Customer_Chat_Bridge::filter_customer_lifecycle_messages($new);
            if ($all !== null && !$full) {
                $all = PAXdesign_Customer_Chat_Bridge::filter_customer_lifecycle_messages($all);
            }
        }

        $handler = isset($row->handler) ? (string) $row->handler : self::HANDLER_AI;
        $reads = $this->get_read_seqs($row);
        if ($mark_read === 'user') {
            $this->mark_read_seq($session_id, 'user', $message_seq);
            $reads['user'] = max($reads['user'], $message_seq);
        } elseif ($mark_read === 'admin') {
            $this->mark_read_seq($session_id, 'admin', $message_seq);
            $reads['admin'] = max($reads['admin'], $message_seq);
        }
        $other_read = $mark_read === 'admin' ? $reads['user'] : $reads['admin'];
        $unread_staff = class_exists('PAXdesign_Message_Store')
            ? PAXdesign_Message_Store::count_unread_staff_messages($session_id, $reads['user'])
            : 0;
        $reactions = is_array($reactions_map)
            ? $reactions_map
            : $this->extract_message_reactions($full ? $new : ($all ?? $new));

        $payload = array(
            'session_id'       => $session_id,
            'handler'          => $handler,
            'handler_label'    => self::handler_label($handler, $agent['admin_name']),
            'admin_user_id'    => $agent['admin_user_id'],
            'admin_name'       => $agent['admin_name'],
            'assigned_agent'   => $agent['assigned_agent'],
            'customer_name'    => isset($row->customer_name) ? (string) $row->customer_name : '',
            'session_rating'   => isset($row->session_rating) ? (int) $row->session_rating : 0,
            'detected_service' => isset($row->detected_service) ? (string) $row->detected_service : '',
            'updated_at'       => PAXdesign_API_Time::format(isset($row->updated_at) ? (string) $row->updated_at : '', false),
            'seq'              => $message_seq,
            'message_count'    => $message_count,
            'last_read_seq'    => $reads['user'],
            'admin_read_seq'   => $reads['admin'],
            'other_read_seq'   => $other_read,
            'unread_staff_count' => $unread_staff,
            'messages'         => $new,
            'admin_typing'     => $this->is_typing($session_id, 'admin'),
            'assistant_typing' => $this->is_typing($session_id, 'assistant'),
            'user_typing'      => $this->is_typing($session_id, 'user'),
            'reactions'        => $reactions,
            'customer_language'=> class_exists('PAXdesign_Language_Routing')
                ? PAXdesign_Language_Routing::session_language_from_row($row)
                : '',
            'auth_user_id'     => $wp_user_id,
            'wp_user_id'       => $wp_user_id,
        );
        if ($history_window) {
            $payload['has_older'] = $has_older;
            $payload['oldest_seq'] = $oldest_seq;
        }
        if (!$full && $since > 0 && empty($new) && $message_count > $since) {
            $payload['sync'] = array('resync_required' => true);
        }
        return $payload;
    }

    /**
     * Release a stale live-request queue slot after customer disconnect or inactivity.
     */
    public function release_abandoned_live_request($session_id) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return false;
        }

        $row = $this->get_session_row($session_id);
        if (!$row) {
            return false;
        }

        $handler = isset($row->handler) ? (string) $row->handler : self::HANDLER_AI;
        if ($handler !== self::HANDLER_LIVE) {
            return false;
        }

        global $wpdb;
        $wp_user_id = isset($row->wp_user_id) ? (int) $row->wp_user_id : 0;
        if ($wp_user_id > 0) {
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
            $this->clear_typing($session_id, 'user');
            $this->clear_typing($session_id, 'admin');
            $this->persist_session_last_preview($session_id);
            $this->emit_handler_event($session_id, self::HANDLER_AI);
            return true;
        }

        return !is_wp_error($this->admin_decline_live_request($session_id));
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function admin_takeover($session_id) {
        if (class_exists('PAXdesign_Live_Chat_Permissions') && !PAXdesign_Live_Chat_Permissions::can_takeover_chats()) {
            return new WP_Error(
                'forbidden',
                __('You do not have permission to take over this conversation.', 'paxdesign-booking'),
                array('status' => 403)
            );
        }

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

        $user     = wp_get_current_user();
        $identity = self::resolve_employee_identity((int) $user->ID);
        $admin_name = $identity ? $identity['name'] : $user->display_name;

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

        $lang   = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::session_customer_language($row)
            : 'de';
        $notice = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::system_notice('staff_takeover', $lang)
            : 'Ein Mitarbeiter hat den Live-Chat übernommen.';
        $entry  = $this->append_message(
            $session_id,
            'system',
            $notice,
            array('client_msg_id' => 'sys:staff_takeover')
        );

        $this->clear_typing($session_id, 'user');
        $this->clear_typing($session_id, 'admin');
        $this->persist_session_last_preview($session_id);
        $this->emit_handler_event_with_message($session_id, self::HANDLER_ADMIN, $entry, array(
            'admin_name'    => $admin_name,
            'admin_user_id' => (int) $user->ID,
        ));

        return array(
            'handler'        => self::HANDLER_ADMIN,
            'admin_user_id'  => (int) $user->ID,
            'admin_name'     => $admin_name,
            'assigned_agent' => $identity,
            'message'        => $entry,
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
        $was_live_request = isset($row->handler) && (string) $row->handler === self::HANDLER_LIVE;
        $wp_user_id = isset($row->wp_user_id) ? (int) $row->wp_user_id : 0;

        $handler = isset($row->handler) ? $row->handler : self::HANDLER_AI;
        if ($handler === self::HANDLER_CLOSED) {
            return array(
                'handler' => self::HANDLER_CLOSED,
                'message' => null,
            );
        }

        global $wpdb;
        if ($wp_user_id > 0) {
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
                __('Our team will reply here when available. You can keep messaging anytime.', 'paxdesign-booking')
            );
            $this->persist_session_last_preview($session_id);
            $this->emit_handler_event($session_id, self::HANDLER_AI);
            return array(
                'handler' => self::HANDLER_AI,
                'message' => $entry,
            );
        }

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

        $lang   = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::session_customer_language($row)
            : 'de';
        $closed = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::system_notice('chat_closed', $lang)
            : 'Dieser Chat wurde geschlossen. Sie können jederzeit ein neues Gespräch starten.';
        $entry = $this->append_message(
            $session_id,
            'system',
            $closed,
            array('client_msg_id' => 'sys:chat_closed')
        );

        $this->persist_session_last_preview($session_id);
        if ($was_live_request) {
            $this->dispatch_missed_chat_event($session_id, $row, 'Live-Anfrage geschlossen');
        }
        $this->emit_handler_event_with_message($session_id, self::HANDLER_CLOSED, $entry);

        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            PAXdesign_Cybercrime_Tickets::close_report_for_chat_session($session_id, get_current_user_id());
        }

        return array(
            'handler' => self::HANDLER_CLOSED,
            'message' => $entry,
        );
    }

    /**
     * Trigger explicit missed-chat notifications when a waiting live request is closed.
     *
     * @param string $session_id
     * @param object $row
     * @param string $preview
     */
    private function dispatch_missed_chat_event($session_id, $row, $preview = '') {
        $service = isset($row->detected_service) ? (string) $row->detected_service : '';
        $customer = $this->session_customer_label($row);
        do_action(
            'paxdesign_chat_live_missed',
            (string) $session_id,
            $service,
            (string) $preview,
            $customer
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

        $lang   = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::session_customer_language($row)
            : 'de';
        $notice = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::system_notice('staff_returned_to_ai', $lang)
            : 'Das Gespräch wurde an den KI-Assistenten zurückgegeben.';
        $entry  = $this->append_message(
            $session_id,
            'system',
            $notice,
            array('client_msg_id' => 'sys:staff_returned_to_ai')
        );

        $this->clear_typing($session_id, 'user');
        $this->clear_typing($session_id, 'admin');
        $this->persist_session_last_preview($session_id);
        $this->emit_handler_event_with_message($session_id, self::HANDLER_AI, $entry);

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
        $identity   = self::resolve_employee_identity((int) $user->ID);
        $admin_name = $identity ? $identity['name'] : $user->display_name;

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

        $lang   = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::session_customer_language($row)
            : 'de';
        $reopened = class_exists('PAXdesign_Language_Routing')
            ? PAXdesign_Language_Routing::system_notice('chat_reopened', $lang, array('admin_name' => $admin_name))
            : 'Der Chat wurde wieder geöffnet. ' . $admin_name . ' ist wieder für Sie da.';
        $entry = $this->append_message(
            $session_id,
            'system',
            $reopened,
            array('client_msg_id' => 'sys:chat_reopened:' . substr(md5($admin_name), 0, 12))
        );

        $this->emit_handler_event_with_message($session_id, self::HANDLER_ADMIN, $entry, array(
            'admin_name'    => $admin_name,
            'admin_user_id' => (int) $user->ID,
        ));

        return array(
            'handler'        => self::HANDLER_ADMIN,
            'admin_user_id'  => (int) $user->ID,
            'admin_name'     => $admin_name,
            'assigned_agent' => $identity,
            'message'        => $entry,
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function admin_send_message($session_id, $content, $reply_to = 0, $client_msg_id = '') {
        $session_id = $this->sanitize_session_id($session_id);
        $content    = sanitize_textarea_field((string) $content);

        if ($session_id === '' || $content === '') {
            return new WP_Error('invalid_payload', 'Ungültige Nachricht.', array('status' => 400));
        }

        $ensure = $this->ensure_admin_handler_for_agent($session_id);
        if (is_wp_error($ensure)) {
            return $ensure;
        }

        $reply_to = (int) $reply_to;
        $extra    = $reply_to > 0 ? array('reply_to' => $reply_to) : array();
        $extra['client_msg_id'] = sanitize_text_field((string) $client_msg_id);
        $entry    = $this->append_message($session_id, 'admin', $content, $extra);
        if (is_wp_error($entry)) {
            return $entry;
        }
        if (!$entry) {
            return new WP_Error('save_failed', 'Speichern fehlgeschlagen.', array('status' => 500));
        }

        $this->clear_typing($session_id, 'admin');

        return array('message' => $entry);
    }

    /**
     * @param array<string, mixed> $link
     * @return array<string, mixed>|WP_Error
     */
    public function admin_send_link_card($session_id, array $link) {
        $session_id = $this->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', 'Ungültige Session.', array('status' => 400));
        }

        $ensure = $this->ensure_admin_handler_for_agent($session_id);
        if (is_wp_error($ensure)) {
            return $ensure;
        }

        $label = sanitize_text_field((string) ($link['label'] ?? ''));
        $url   = esc_url_raw((string) ($link['url'] ?? ''));
        $icon  = sanitize_text_field((string) ($link['icon'] ?? '🔗'));
        if ($label === '' || $url === '') {
            return new WP_Error('invalid_link', 'Ungültiger Link.', array('status' => 400));
        }

        $extra = array(
            'attachment_type' => 'link_card',
            'link_url'        => $url,
            'link_label'      => $label,
            'link_icon'       => $icon,
            'client_msg_id'   => isset($_POST['client_msg_id'])
                ? sanitize_text_field(wp_unslash($_POST['client_msg_id']))
                : '',
        );

        $entry = $this->append_message($session_id, 'admin', $label, $extra);
        if (is_wp_error($entry)) {
            return $entry;
        }
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
     * Permanently delete a single message from a customer live-chat session.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function admin_delete_message($session_id, $message_id) {
        $session_id = $this->sanitize_session_id($session_id);
        $message_id = absint($message_id);
        if ($session_id === '' || $message_id <= 0) {
            return new WP_Error('invalid_message', 'Ungültige Nachricht.', array('status' => 400));
        }

        if (!$this->get_session_row($session_id)) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        if (!class_exists('PAXdesign_Message_Store')) {
            return new WP_Error('unavailable', 'Message store unavailable.', array('status' => 500));
        }

        return PAXdesign_Message_Store::delete_message(
            $session_id,
            $message_id,
            (int) get_current_user_id(),
            'customer'
        );
    }

    /**
     * Apply employee link-scan review decision for a customer message.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function admin_apply_link_review($session_id, $message_id, $action) {
        $session_id = $this->sanitize_session_id($session_id);
        $message_id = absint($message_id);
        $action     = sanitize_key((string) $action);

        if ($session_id === '' || $message_id <= 0 || $action === '') {
            return new WP_Error('invalid_review', 'Ungültige Anfrage.', array('status' => 400));
        }

        if (!$this->get_session_row($session_id)) {
            return new WP_Error('not_found', 'Session nicht gefunden.', array('status' => 404));
        }

        if (!class_exists('PAXdesign_Link_Scan_Service')) {
            return new WP_Error('unavailable', 'Link scan service unavailable.', array('status' => 500));
        }

        return PAXdesign_Link_Scan_Service::apply_review_decision(
            $session_id,
            $message_id,
            $action,
            (int) get_current_user_id()
        );
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
        if (class_exists('PAXdesign_Message_Store')) {
            if (!PAXdesign_Message_Store::delete_session($session_id)) {
                return new WP_Error('delete_failed', 'Nachrichtendaten konnten nicht vollständig gelöscht werden.', array('status' => 500));
            }
        }
        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::emit_session($session_id, 'conversation_deleted', array(
                'mode' => 'purged',
            ));
        }

        return array(
            'ok'      => true,
            'deleted' => $deleted,
        );
    }
}
