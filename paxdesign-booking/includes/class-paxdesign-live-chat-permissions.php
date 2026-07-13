<?php
/**
 * Role-based permissions for Live Chat (WordPress admin + iOS app).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Live_Chat_Permissions {

    const SUPER_ADMIN_EMAIL = 'sarah.gta1995@gmail.com';
    const OPTION_STAFF       = 'paxdesign_live_chat_staff';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_admin_menu'), 20);
        add_action('admin_post_paxdesign_live_chat_save_staff', array(__CLASS__, 'handle_save_staff'));
        add_action('admin_post_paxdesign_live_chat_remove_staff', array(__CLASS__, 'handle_remove_staff'));
    }

    public static function register_admin_menu() {
        $can_manage = self::can(get_current_user_id(), self::PERM_MANAGE_USERS)
            || self::can(get_current_user_id(), self::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!$can_manage && !self::is_super_admin()) {
            return;
        }
        add_submenu_page(
            'paxdesign-booking',
            __('Live Chat Team', 'paxdesign-booking'),
            __('Live Chat Team', 'paxdesign-booking'),
            'read',
            'paxdesign-live-chat-team',
            array(__CLASS__, 'render_admin_page')
        );
    }

    public static function render_admin_page() {
        $can_manage = self::can(get_current_user_id(), self::PERM_MANAGE_USERS)
            || self::can(get_current_user_id(), self::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!$can_manage && !self::is_super_admin()) {
            wp_die(esc_html__('Keine Berechtigung.', 'paxdesign-booking'));
        }
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/live-chat-permissions-page.php';
    }

    public static function handle_save_staff() {
        $can_manage = self::can(get_current_user_id(), self::PERM_MANAGE_USERS)
            || self::can(get_current_user_id(), self::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!current_user_can('read') || (!$can_manage && !self::is_super_admin())) {
            wp_die(esc_html__('Keine Berechtigung.', 'paxdesign-booking'));
        }
        check_admin_referer('paxdesign_live_chat_staff');
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $email   = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        if ($user_id <= 0 && $email !== '') {
            $found = get_user_by('email', $email);
            if ($found) {
                $user_id = (int) $found->ID;
            }
        }
        $perms = array();
        foreach (array_keys(self::permission_labels()) as $key) {
            $perms[$key] = !empty($_POST['perm_' . $key]);
        }
        $result = self::save_staff_record($user_id, array(
            'enabled'     => !empty($_POST['enabled']),
            'permissions' => $perms,
        ));
        $redirect = admin_url('admin.php?page=paxdesign-live-chat-team');
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('error', rawurlencode($result->get_error_message()), $redirect));
            exit;
        }
        wp_safe_redirect(add_query_arg('updated', '1', $redirect));
        exit;
    }

    public static function handle_remove_staff() {
        $can_manage = self::can(get_current_user_id(), self::PERM_MANAGE_USERS)
            || self::can(get_current_user_id(), self::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!current_user_can('read') || (!$can_manage && !self::is_super_admin())) {
            wp_die(esc_html__('Keine Berechtigung.', 'paxdesign-booking'));
        }
        check_admin_referer('paxdesign_live_chat_remove_staff');
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        self::remove_staff($user_id);
        wp_safe_redirect(admin_url('admin.php?page=paxdesign-live-chat-team&removed=1'));
        exit;
    }

    const PERM_VIEW_CHATS       = 'view_chats';
    const PERM_REPLY_CHATS      = 'reply_chats';
    const PERM_USE_AI           = 'use_ai';
    const PERM_SEND_IMAGES      = 'send_images';
    const PERM_MANAGE_SETTINGS  = 'manage_settings';
    const PERM_VIEW_RATINGS     = 'view_ratings';
    const PERM_MANAGE_USERS     = 'manage_users';
    const PERM_ACCESS_SECURITY  = 'access_security';
    const PERM_MANAGE_TEAM_PERMISSIONS = 'manage_team_permissions';
    const PERM_MANAGE_CUSTOMER_PROFILES = 'manage_customer_profiles';
    const PERM_ASSIGN_TEAM_TASKS = 'assign_team_tasks';
    const PERM_CUSTOMIZE_HUB_PROFILE = 'customize_hub_profile';

    /**
     * @return array<string, string>
     */
    public static function permission_labels() {
        return array(
            self::PERM_VIEW_CHATS      => __('Chats ansehen', 'paxdesign-booking'),
            self::PERM_REPLY_CHATS     => __('Antworten & Chat führen', 'paxdesign-booking'),
            self::PERM_USE_AI          => __('KI-Assistent nutzen', 'paxdesign-booking'),
            self::PERM_SEND_IMAGES     => __('Bilder senden', 'paxdesign-booking'),
            self::PERM_MANAGE_SETTINGS => __('Einstellungen verwalten', 'paxdesign-booking'),
            self::PERM_VIEW_RATINGS    => __('Bewertungen & Feedback', 'paxdesign-booking'),
            self::PERM_MANAGE_USERS    => __('Team & Berechtigungen', 'paxdesign-booking'),
            self::PERM_ACCESS_SECURITY => __('Sicherheit & Konto', 'paxdesign-booking'),
            self::PERM_MANAGE_TEAM_PERMISSIONS => __('Team-Berechtigungen verwalten', 'paxdesign-booking'),
            self::PERM_MANAGE_CUSTOMER_PROFILES => __('Kundenprofile verwalten', 'paxdesign-booking'),
            self::PERM_ASSIGN_TEAM_TASKS => __('Team-Aufgaben erstellen/zuweisen', 'paxdesign-booking'),
            self::PERM_CUSTOMIZE_HUB_PROFILE => __('Hub-Profilname anpassen', 'paxdesign-booking'),
        );
    }

    /**
     * @return array<string, bool>
     */
    public static function all_permissions_true() {
        $out = array();
        foreach (array_keys(self::permission_labels()) as $key) {
            $out[$key] = true;
        }
        return $out;
    }

    /**
     * @param WP_User|int|null $user
     */
    public static function is_super_admin($user = null) {
        $user = self::resolve_user($user);
        if (!$user) {
            return false;
        }
        return strtolower(trim((string) $user->user_email)) === strtolower(self::SUPER_ADMIN_EMAIL);
    }

    /**
     * @param WP_User|int|null $user
     * @return array<string, bool>
     */
    public static function get_effective_permissions($user = null) {
        $user = self::resolve_user($user);
        if (!$user) {
            return array();
        }

        if (self::is_super_admin($user) || user_can($user, 'manage_options')) {
            return self::all_permissions_true();
        }

        $staff = self::get_staff_record((int) $user->ID);
        if (!$staff || empty($staff['enabled'])) {
            return array();
        }

        $perms = isset($staff['permissions']) && is_array($staff['permissions'])
            ? $staff['permissions']
            : array();

        $out = array();
        foreach (array_keys(self::permission_labels()) as $key) {
            $out[$key] = !empty($perms[$key]);
        }

        // Backward-compatible defaults: existing managers keep elevated controls.
        if (!empty($out[self::PERM_MANAGE_USERS])) {
            $out[self::PERM_MANAGE_TEAM_PERMISSIONS] = true;
            $out[self::PERM_MANAGE_CUSTOMER_PROFILES] = true;
            $out[self::PERM_ASSIGN_TEAM_TASKS] = true;
            $out[self::PERM_CUSTOMIZE_HUB_PROFILE] = true;
        }

        if (!empty($out[self::PERM_MANAGE_SETTINGS])) {
            $out[self::PERM_CUSTOMIZE_HUB_PROFILE] = true;
        }

        return $out;
    }

    /**
     * @param WP_User|int|null $user
     */
    public static function has_live_chat_access($user = null) {
        $user = self::resolve_user($user);
        if (!$user) {
            return false;
        }
        if (self::is_super_admin($user) || user_can($user, 'manage_options')) {
            return true;
        }
        $staff = self::get_staff_record((int) $user->ID);
        if (!$staff || empty($staff['enabled'])) {
            return false;
        }
        $perms = self::get_effective_permissions($user);
        return !empty($perms[self::PERM_VIEW_CHATS]);
    }

    /**
     * @param WP_User|int|null $user
     * @param string           $permission
     */
    public static function can($user, $permission) {
        $perms = self::get_effective_permissions($user);
        return !empty($perms[$permission]);
    }

    /**
     * REST/mobile access gate.
     *
     * @return true|WP_Error
     */
    public static function authorize_api_access() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('Use your WordPress username (or account email) and a valid Application Password via HTTP Basic Auth.', 'paxdesign-booking'),
                array('status' => 401)
            );
        }

        if (!self::has_live_chat_access()) {
            return new WP_Error(
                'rest_forbidden',
                __('Your account does not have Live Chat access. Contact the main administrator.', 'paxdesign-booking'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * @param string $permission
     * @return true|WP_Error
     */
    public static function require_permission($permission) {
        $access = self::authorize_api_access();
        if (is_wp_error($access)) {
            return $access;
        }
        if (!self::can(get_current_user_id(), $permission)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission for this action.', 'paxdesign-booking'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get_all_staff() {
        $stored = get_option(self::OPTION_STAFF, array());
        return is_array($stored) ? $stored : array();
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>|null
     */
    public static function get_staff_record($user_id) {
        $all = self::get_all_staff();
        $key = (string) (int) $user_id;
        return isset($all[$key]) && is_array($all[$key]) ? $all[$key] : null;
    }

    /**
     * @param int                  $user_id
     * @param array<string, mixed> $data
     */
    public static function save_staff_record($user_id, array $data) {
        $can_manage = self::can(get_current_user_id(), self::PERM_MANAGE_USERS)
            || self::can(get_current_user_id(), self::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!$can_manage && !self::is_super_admin()) {
            return new WP_Error('forbidden', __('Keine Berechtigung.', 'paxdesign-booking'), array('status' => 403));
        }

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return new WP_Error('invalid_user', __('Ungültiger Benutzer.', 'paxdesign-booking'), array('status' => 400));
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('Benutzer nicht gefunden.', 'paxdesign-booking'), array('status' => 404));
        }

        if (self::is_super_admin($user)) {
            return new WP_Error('protected_user', __('Hauptadministrator kann nicht geändert werden.', 'paxdesign-booking'), array('status' => 400));
        }

        $labels = array_keys(self::permission_labels());
        $perms  = array();
        if (!empty($data['permissions']) && is_array($data['permissions'])) {
            foreach ($labels as $key) {
                $perms[$key] = !empty($data['permissions'][$key]);
            }
        }

        $all = self::get_all_staff();
        $all[(string) $user_id] = array(
            'enabled'     => !empty($data['enabled']),
            'permissions' => $perms,
            'team_role'   => !empty($data['team_role']) ? sanitize_key((string) $data['team_role']) : '',
            'updated_at'  => current_time('mysql'),
            'updated_by'  => get_current_user_id(),
        );
        update_option(self::OPTION_STAFF, $all, false);

        return true;
    }

    /**
     * @param int $user_id
     */
    public static function remove_staff($user_id) {
        $can_manage = self::can(get_current_user_id(), self::PERM_MANAGE_USERS)
            || self::can(get_current_user_id(), self::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!$can_manage && !self::is_super_admin()) {
            return new WP_Error('forbidden', __('Keine Berechtigung.', 'paxdesign-booking'), array('status' => 403));
        }
        $user_id = (int) $user_id;
        $user    = get_user_by('id', $user_id);
        if ($user && self::is_super_admin($user)) {
            return new WP_Error('protected_user', __('Hauptadministrator kann nicht entfernt werden.', 'paxdesign-booking'), array('status' => 400));
        }
        $all = self::get_all_staff();
        unset($all[(string) $user_id]);
        update_option(self::OPTION_STAFF, $all, false);
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_staff_for_api() {
        $out  = array();
        $all  = self::get_all_staff();
        foreach ($all as $user_id => $record) {
            $uid  = (int) $user_id;
            $user = get_user_by('id', $uid);
            if (!$user) {
                continue;
            }
            $avatar_meta = trim((string) get_user_meta($uid, 'pax_live_avatar_url', true));
            $avatar_url  = $avatar_meta !== '' ? esc_url_raw($avatar_meta) : get_avatar_url($uid, array('size' => 256));
            $identity    = PAXdesign_Chat_Live::resolve_employee_identity($uid);
            $out[] = array(
                'user_id'     => $uid,
                'name'        => $identity ? $identity['name'] : $user->display_name,
                'email'       => $user->user_email,
                'username'    => $user->user_login,
                'avatar_url'  => $avatar_url,
                'profile_title' => (string) get_user_meta($uid, 'pax_live_profile_title', true),
                'profile_phone' => (string) get_user_meta($uid, 'pax_live_profile_phone', true),
                'profile_notes' => (string) get_user_meta($uid, 'pax_live_profile_notes', true),
                'onboarding_completed' => (bool) get_user_meta($uid, 'pax_live_onboarding_completed', true),
                'enabled'     => !empty($record['enabled']),
                'team_role'   => !empty($record['team_role']) ? (string) $record['team_role'] : '',
                'permissions' => isset($record['permissions']) ? $record['permissions'] : array(),
            );
        }
        return $out;
    }

    /**
     * Human-readable role label for Team contact picker.
     *
     * @param WP_User|int|null $user
     */
    /**
     * Internal team hierarchy rank (lower = higher authority).
     * 1 Executive Director, 2 Admin, 3 Senior staff, 4 Staff, 5 Other.
     *
     * @param WP_User|int|null $user
     */
    public static function team_role_rank($user = null) {
        $user = self::resolve_user($user);
        if (!$user) {
            return 5;
        }
        $uid = (int) $user->ID;
        if (self::is_super_admin($uid)) {
            return 1;
        }
        $record = self::get_staff_record($uid);
        if (is_array($record) && !empty($record['team_role']) && class_exists('PAXdesign_Team_Registry')) {
            return PAXdesign_Team_Registry::rank_for_role((string) $record['team_role']);
        }
        if (user_can($uid, 'manage_options')) {
            return 2;
        }
        $perms = self::get_effective_permissions($uid);
        if (!empty($perms[self::PERM_MANAGE_USERS])) {
            return 3;
        }
        if (self::has_live_chat_access($uid)) {
            return 4;
        }
        return 5;
    }

    /**
     * @param int $rank
     */
    public static function team_role_label_for_rank($rank) {
        switch ((int) $rank) {
            case 1:
                return 'Executive Director';
            case 2:
                return 'Administrator';
            case 3:
                return 'Senior Staff';
            case 4:
                return 'Staff Member';
            default:
                return 'Team Member';
        }
    }

    public static function team_role_label_for_user($user = null) {
        return self::team_role_label_for_rank(self::team_role_rank($user));
    }

    /**
     * Normalize stored profile titles — maps legacy feminine German executive titles
     * to the canonical English label consumed by iOS localization.
     *
     * @param string $title
     * @param int    $user_id
     */
    public static function normalize_profile_title($title, $user_id = 0) {
        $title = trim((string) $title);
        $lower = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);

        $is_feminine_ed = ($lower === 'geschäftsführerin' || $lower === 'geschaeftsfuehrerin');
        $is_executive   = $user_id > 0 && self::is_super_admin($user_id);

        if ($is_executive && ($title === '' || $is_feminine_ed)) {
            return 'Executive Director';
        }
        if ($is_feminine_ed || $lower === 'geschäftsführer' || $lower === 'geschaeftsfuehrer') {
            return 'Executive Director';
        }
        return $title;
    }

    /**
     * @return array<string, bool>
     */
    public static function get_team_messaging_settings() {
        $stored = get_option('paxdesign_team_messaging_settings', array());
        if (!is_array($stored)) {
            $stored = array();
        }
        return array(
            'require_ed_approval'      => array_key_exists('require_ed_approval', $stored)
                ? !empty($stored['require_ed_approval'])
                : true,
            'require_admin_approval'   => array_key_exists('require_admin_approval', $stored)
                ? !empty($stored['require_admin_approval'])
                : true,
            'require_manager_approval' => array_key_exists('require_manager_approval', $stored)
                ? !empty($stored['require_manager_approval'])
                : false,
        );
    }

    /**
     * Whether a new conversation between two users requires approval before messaging.
     *
     * @param int $requester_id
     * @param int $target_id
     */
    public static function requires_team_conversation_approval($requester_id, $target_id) {
        $requester_id = absint($requester_id);
        $target_id    = absint($target_id);
        if ($requester_id <= 0 || $target_id <= 0 || $requester_id === $target_id) {
            return false;
        }

        $requester_rank = self::team_role_rank($requester_id);
        $target_rank    = self::team_role_rank($target_id);

        // Higher authority can always open without approval.
        if ($requester_rank < $target_rank) {
            return false;
        }

        $settings = self::get_team_messaging_settings();

        if ($target_rank === 1 && $requester_rank > 1) {
            // Permanent rule: everyone except the Executive Director must request access.
            return true;
        }
        if ($target_rank === 2 && $requester_rank >= 4) {
            return !empty($settings['require_admin_approval']);
        }
        if ($target_rank === 3 && $requester_rank >= 4) {
            return !empty($settings['require_manager_approval']);
        }

        return false;
    }

    /**
     * @param int $user_id
     */
    public static function touch_team_presence($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }
        set_transient(
            'pax_team_presence_' . $user_id,
            array(
                'online'    => true,
                'last_seen' => time(),
            ),
            120
        );
    }

    /**
     * @param int $user_id
     * @return array{status: string, last_seen: int}
     */
    public static function get_team_presence($user_id) {
        $user_id = absint($user_id);
        $data    = get_transient('pax_team_presence_' . $user_id);
        $last    = is_array($data) && isset($data['last_seen']) ? absint($data['last_seen']) : 0;
        $online  = is_array($data)
            && !empty($data['online'])
            && $last > 0
            && (time() - $last) < 90;
        return array(
            'status'    => $online ? 'online' : 'offline',
            'last_seen' => $last,
        );
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private static function enrich_team_contact($member) {
        $uid = isset($member['user_id']) ? (int) $member['user_id'] : 0;
        $viewer_id = get_current_user_id();
        $member['role_label'] = self::team_role_label_for_user($uid);
        $member['is_executive'] = self::is_super_admin($uid);
        $member['is_administrator'] = !empty($member['is_administrator'])
            || user_can($uid, 'manage_options')
            || self::is_super_admin($uid);
        $member['role_rank'] = self::team_role_rank($uid);
        if (!empty($member['team_role'])) {
            $member['team_role'] = sanitize_key((string) $member['team_role']);
        } elseif (class_exists('PAXdesign_Team_Registry')) {
            $member['team_role'] = PAXdesign_Team_Registry::get_assigned_role($uid);
        }
        $member['requires_ed_request'] = !empty($member['is_executive']) && !self::is_super_admin($viewer_id);
        $member['requires_contact_request'] = self::requires_team_conversation_approval($viewer_id, $uid);
        $member['can_message_ed_directly'] = self::is_super_admin($viewer_id);
        if ($member['profile_title'] === '' && $member['is_executive']) {
            $member['profile_title'] = 'Executive Director';
        }
        $member['profile_title'] = self::normalize_profile_title(
            (string) $member['profile_title'],
            $uid
        );
        $presence = self::get_team_presence($uid);
        $member['presence_status'] = $presence['status'];
        $member['last_seen']       = $presence['last_seen'];
        return $member;
    }

    /**
     * Team compose picker: enabled staff plus administrators with live chat access.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function list_team_contacts_for_api() {
        $out  = array();
        $seen = array();

        foreach (self::list_staff_for_api() as $member) {
            if (empty($member['enabled'])) {
                continue;
            }
            $uid = (int) $member['user_id'];
            if (self::is_super_admin($uid)) {
                continue;
            }
            $seen[$uid] = true;
            $out[] = self::enrich_team_contact($member);
        }

        $admin_users = get_users(array(
            'role__in' => array('administrator'),
            'fields'   => array('ID', 'display_name', 'user_email', 'user_login'),
        ));

        foreach ($admin_users as $user) {
            $uid = (int) $user->ID;
            if (isset($seen[$uid]) || self::is_super_admin($uid)) {
                continue;
            }
            if (!self::has_live_chat_access($uid)) {
                continue;
            }
            $avatar_meta = trim((string) get_user_meta($uid, 'pax_live_avatar_url', true));
            $avatar_url  = $avatar_meta !== '' ? esc_url_raw($avatar_meta) : get_avatar_url($uid, array('size' => 256));
            $identity    = PAXdesign_Chat_Live::resolve_employee_identity($uid);
            $out[] = self::enrich_team_contact(array(
                'user_id'     => $uid,
                'name'        => $identity ? $identity['name'] : $user->display_name,
                'email'       => $user->user_email,
                'username'    => $user->user_login,
                'avatar_url'  => $avatar_url,
                'profile_title' => (string) get_user_meta($uid, 'pax_live_profile_title', true),
                'profile_phone' => (string) get_user_meta($uid, 'pax_live_profile_phone', true),
                'profile_notes' => (string) get_user_meta($uid, 'pax_live_profile_notes', true),
                'onboarding_completed' => (bool) get_user_meta($uid, 'pax_live_onboarding_completed', true),
                'enabled'     => true,
                'permissions' => self::get_effective_permissions($uid),
                'is_administrator' => true,
            ));
            $seen[$uid] = true;
        }

        $executive = get_user_by('email', self::SUPER_ADMIN_EMAIL);
        if ($executive instanceof WP_User) {
            $euid = (int) $executive->ID;
            $out = array_values(array_filter($out, function ($item) use ($euid) {
                return (int) (isset($item['user_id']) ? $item['user_id'] : 0) !== $euid;
            }));
            $avatar_meta = trim((string) get_user_meta($euid, 'pax_live_avatar_url', true));
            $avatar_url  = $avatar_meta !== '' ? esc_url_raw($avatar_meta) : get_avatar_url($euid, array('size' => 256));
            $identity    = PAXdesign_Chat_Live::resolve_employee_identity($euid);
            array_unshift($out, self::enrich_team_contact(array(
                'user_id'     => $euid,
                'name'        => $identity ? $identity['name'] : $executive->display_name,
                'email'       => $executive->user_email,
                'username'    => $executive->user_login,
                'avatar_url'  => $avatar_url,
                'profile_title' => (string) get_user_meta($euid, 'pax_live_profile_title', true),
                'profile_phone' => (string) get_user_meta($euid, 'pax_live_profile_phone', true),
                'profile_notes' => (string) get_user_meta($euid, 'pax_live_profile_notes', true),
                'onboarding_completed' => (bool) get_user_meta($euid, 'pax_live_onboarding_completed', true),
                'enabled'     => true,
                'permissions' => self::get_effective_permissions($euid),
                'is_administrator' => true,
            )));
        }

        usort($out, function ($a, $b) {
            $rank = function ($item) {
                if (!empty($item['is_executive'])) {
                    return 0;
                }
                if (!empty($item['is_administrator'])) {
                    return 1;
                }
                if (!empty($item['permissions'][self::PERM_MANAGE_USERS])) {
                    return 2;
                }
                return 3;
            };
            $ra = $rank($a);
            $rb = $rank($b);
            if ($ra !== $rb) {
                return $ra - $rb;
            }
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $out;
    }

    /**
     * @param WP_User|int|null $user
     * @return WP_User|null
     */
    private static function resolve_user($user) {
        if ($user instanceof WP_User) {
            return $user;
        }
        if (is_numeric($user)) {
            $u = get_user_by('id', (int) $user);
            return $u instanceof WP_User ? $u : null;
        }
        if (is_user_logged_in()) {
            return wp_get_current_user();
        }
        return null;
    }
}
