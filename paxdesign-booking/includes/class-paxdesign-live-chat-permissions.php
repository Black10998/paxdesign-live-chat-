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
        if (!self::can(get_current_user_id(), self::PERM_MANAGE_USERS) && !self::is_super_admin()) {
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
        if (!self::can(get_current_user_id(), self::PERM_MANAGE_USERS) && !self::is_super_admin()) {
            wp_die(esc_html__('Keine Berechtigung.', 'paxdesign-booking'));
        }
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/live-chat-permissions-page.php';
    }

    public static function handle_save_staff() {
        if (!current_user_can('read') || (!self::can(get_current_user_id(), self::PERM_MANAGE_USERS) && !self::is_super_admin())) {
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
        if (!current_user_can('read') || (!self::can(get_current_user_id(), self::PERM_MANAGE_USERS) && !self::is_super_admin())) {
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
        if (!self::can(get_current_user_id(), self::PERM_MANAGE_USERS) && !self::is_super_admin()) {
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
        if (!self::can(get_current_user_id(), self::PERM_MANAGE_USERS) && !self::is_super_admin()) {
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
            $out[] = array(
                'user_id'     => $uid,
                'name'        => $user->display_name,
                'email'       => $user->user_email,
                'username'    => $user->user_login,
                'enabled'     => !empty($record['enabled']),
                'permissions' => isset($record['permissions']) ? $record['permissions'] : array(),
            );
        }
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
