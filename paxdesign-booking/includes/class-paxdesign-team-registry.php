<?php
/**
 * Centralized Team Management for the Executive Director.
 *
 * Single authority model: sarah.gta1995@gmail.com is the permanent Executive Director.
 * All roster, hierarchy, policy, and pending-request management flows through here.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Team_Registry {

    const OPTION_POLICY = 'paxdesign_team_messaging_settings';
    const ROLE_EXECUTIVE_DIRECTOR = 'executive_director';
    const ROLE_ADMINISTRATOR      = 'administrator';
    const ROLE_SENIOR_STAFF       = 'senior_staff';
    const ROLE_STAFF_MEMBER       = 'staff_member';
    const ROLE_TEAM_MEMBER        = 'team_member';

    /**
     * @return array<int, string>
     */
    public static function role_options() {
        return array(
            1 => self::ROLE_EXECUTIVE_DIRECTOR,
            2 => self::ROLE_ADMINISTRATOR,
            3 => self::ROLE_SENIOR_STAFF,
            4 => self::ROLE_STAFF_MEMBER,
            5 => self::ROLE_TEAM_MEMBER,
        );
    }

    /**
     * @param WP_User|int|null $user
     */
    public static function is_executive_director($user = null) {
        return PAXdesign_Live_Chat_Permissions::is_super_admin($user);
    }

    /**
     * Only the Executive Director may use centralized team management APIs.
     *
     * @return true|WP_Error
     */
    public static function require_executive_director() {
        $access = PAXdesign_Live_Chat_Permissions::authorize_api_access();
        if (is_wp_error($access)) {
            return $access;
        }
        if (!self::is_executive_director()) {
            return new WP_Error(
                'pax_ed_only',
                'Only the Executive Director can manage the team.',
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function get_assigned_role($user_id) {
        $user_id = absint($user_id);
        if (self::is_executive_director($user_id)) {
            return self::ROLE_EXECUTIVE_DIRECTOR;
        }
        $record = PAXdesign_Live_Chat_Permissions::get_staff_record($user_id);
        if (is_array($record) && !empty($record['team_role'])) {
            return sanitize_key((string) $record['team_role']);
        }
        $rank = PAXdesign_Live_Chat_Permissions::team_role_rank($user_id);
        $map  = self::role_options();
        return isset($map[$rank]) ? $map[$rank] : self::ROLE_TEAM_MEMBER;
    }

    /**
     * @param string $role
     * @return int
     */
    public static function rank_for_role($role) {
        $role = sanitize_key((string) $role);
        foreach (self::role_options() as $rank => $key) {
            if ($key === $role) {
                return (int) $rank;
            }
        }
        return 5;
    }

    /**
     * @return array<string, mixed>
     */
    public static function management_overview() {
        $members = self::list_managed_members();
        $pending = class_exists('PAXdesign_Team_Messaging')
            ? PAXdesign_Team_Messaging::list_pending_requests_for_user((int) get_current_user_id())
            : array('sessions' => array());

        $by_role = array();
        foreach ($members as $member) {
            $role = isset($member['team_role']) ? (string) $member['team_role'] : self::ROLE_TEAM_MEMBER;
            if (!isset($by_role[$role])) {
                $by_role[$role] = 0;
            }
            $by_role[$role]++;
        }

        return array(
            'executive_director_email' => PAXdesign_Live_Chat_Permissions::SUPER_ADMIN_EMAIL,
            'total_members'            => count($members),
            'enabled_members'          => count(array_filter($members, function ($m) {
                return !empty($m['enabled']);
            })),
            'pending_request_count'    => count(isset($pending['sessions']) ? $pending['sessions'] : array()),
            'members_by_role'          => $by_role,
            'policy'                   => self::get_contact_policy(),
            'hierarchy'                => self::build_hierarchy($members),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_managed_members() {
        $out = array();

        $executive = get_user_by('email', PAXdesign_Live_Chat_Permissions::SUPER_ADMIN_EMAIL);
        if ($executive instanceof WP_User) {
            $out[] = self::format_member_row($executive, array(
                'enabled'     => true,
                'permissions' => PAXdesign_Live_Chat_Permissions::all_permissions_true(),
                'team_role'   => self::ROLE_EXECUTIVE_DIRECTOR,
                'protected'   => true,
            ));
        }

        foreach (PAXdesign_Live_Chat_Permissions::list_staff_for_api() as $member) {
            $uid = isset($member['user_id']) ? (int) $member['user_id'] : 0;
            if ($uid <= 0 || self::is_executive_director($uid)) {
                continue;
            }
            $user = get_user_by('id', $uid);
            if (!$user) {
                continue;
            }
            $record = PAXdesign_Live_Chat_Permissions::get_staff_record($uid);
            $out[] = self::format_member_row($user, is_array($record) ? $record : array());
        }

        usort($out, function ($a, $b) {
            $ra = isset($a['role_rank']) ? (int) $a['role_rank'] : 99;
            $rb = isset($b['role_rank']) ? (int) $b['role_rank'] : 99;
            if ($ra !== $rb) {
                return $ra - $rb;
            }
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $out;
    }

    /**
     * @param WP_User              $user
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private static function format_member_row($user, $record) {
        $uid = (int) $user->ID;
        $team_role = !empty($record['team_role'])
            ? sanitize_key((string) $record['team_role'])
            : self::get_assigned_role($uid);
        $rank = self::rank_for_role($team_role);
        $perms = isset($record['permissions']) && is_array($record['permissions'])
            ? $record['permissions']
            : PAXdesign_Live_Chat_Permissions::get_effective_permissions($uid);
        $presence = PAXdesign_Live_Chat_Permissions::get_team_presence($uid);

        return array(
            'user_id'          => $uid,
            'name'             => $user->display_name,
            'email'            => $user->user_email,
            'username'         => $user->user_login,
            'enabled'          => !empty($record['enabled']) || self::is_executive_director($uid),
            'team_role'        => $team_role,
            'role_rank'        => $rank,
            'role_label'       => PAXdesign_Live_Chat_Permissions::team_role_label_for_rank($rank),
            'permissions'      => $perms,
            'profile_title'    => (string) get_user_meta($uid, 'pax_live_profile_title', true),
            'profile_phone'    => (string) get_user_meta($uid, 'pax_live_profile_phone', true),
            'profile_notes'    => (string) get_user_meta($uid, 'pax_live_profile_notes', true),
            'avatar_url'       => get_avatar_url($uid, array('size' => 256)),
            'protected'        => !empty($record['protected']) || self::is_executive_director($uid),
            'presence_status'  => $presence['status'],
            'last_seen'        => $presence['last_seen'],
            'can_contact_ed'      => self::is_executive_director($uid),
            'requires_ed_request' => !self::is_executive_director($uid),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $members
     * @return array<int, array<string, mixed>>
     */
    private static function build_hierarchy($members) {
        $levels = array(
            self::ROLE_EXECUTIVE_DIRECTOR => array(),
            self::ROLE_ADMINISTRATOR      => array(),
            self::ROLE_SENIOR_STAFF       => array(),
            self::ROLE_STAFF_MEMBER       => array(),
            self::ROLE_TEAM_MEMBER        => array(),
        );
        foreach ($members as $member) {
            $role = isset($member['team_role']) ? (string) $member['team_role'] : self::ROLE_TEAM_MEMBER;
            if (!isset($levels[$role])) {
                $levels[$role] = array();
            }
            $levels[$role][] = array(
                'user_id'    => $member['user_id'],
                'name'       => $member['name'],
                'email'      => $member['email'],
                'enabled'    => $member['enabled'],
                'role_label' => $member['role_label'],
            );
        }
        $hierarchy = array();
        foreach ($levels as $role => $items) {
            $hierarchy[] = array(
                'role'       => $role,
                'role_label' => PAXdesign_Live_Chat_Permissions::team_role_label_for_rank(self::rank_for_role($role)),
                'members'    => $items,
            );
        }
        return $hierarchy;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_contact_policy() {
        $stored = get_option(self::OPTION_POLICY, array());
        if (!is_array($stored)) {
            $stored = array();
        }
        return array(
            'ed_request_required_for_all' => true,
            'require_ed_approval'         => true,
            'require_admin_approval'      => array_key_exists('require_admin_approval', $stored)
                ? !empty($stored['require_admin_approval'])
                : true,
            'require_manager_approval'    => array_key_exists('require_manager_approval', $stored)
                ? !empty($stored['require_manager_approval'])
                : false,
            'ed_email'                    => PAXdesign_Live_Chat_Permissions::SUPER_ADMIN_EMAIL,
            'contact_matrix'              => array(
                'executive_director' => array('can_message_without_request' => true, 'receives_requests_from' => 'everyone_except_self'),
                'everyone_else'      => array('must_request_before_messaging_ed' => true),
            ),
        );
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>|WP_Error
     */
    public static function save_contact_policy($policy) {
        if (!is_array($policy)) {
            return new WP_Error('invalid_policy', 'Invalid policy payload.', array('status' => 400));
        }
        $current = get_option(self::OPTION_POLICY, array());
        if (!is_array($current)) {
            $current = array();
        }
        $current['require_ed_approval']      = true;
        $current['require_admin_approval']   = !empty($policy['require_admin_approval']);
        $current['require_manager_approval'] = !empty($policy['require_manager_approval']);
        update_option(self::OPTION_POLICY, $current, false);
        return array('ok' => true, 'policy' => self::get_contact_policy());
    }

    /**
     * @param int                  $user_id
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    public static function update_member($user_id, $data) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('invalid_user', 'Invalid user.', array('status' => 400));
        }
        if (self::is_executive_director($user_id)) {
            return new WP_Error('protected_user', 'Executive Director cannot be modified.', array('status' => 400));
        }

        $record = PAXdesign_Live_Chat_Permissions::get_staff_record($user_id);
        if (!$record) {
            return new WP_Error('not_found', 'User is not on the team roster.', array('status' => 404));
        }

        if (!empty($data['team_role'])) {
            $role = sanitize_key((string) $data['team_role']);
            if (!in_array($role, array_values(self::role_options()), true)) {
                return new WP_Error('invalid_role', 'Invalid team role.', array('status' => 400));
            }
            if ($role === self::ROLE_EXECUTIVE_DIRECTOR) {
                return new WP_Error('invalid_role', 'Executive Director role is reserved.', array('status' => 400));
            }
            $record['team_role'] = $role;
        }

        if (isset($data['enabled'])) {
            $record['enabled'] = !empty($data['enabled']);
        }
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $record['permissions'] = $data['permissions'];
        }

        $all = PAXdesign_Live_Chat_Permissions::get_all_staff();
        $all[(string) $user_id] = array_merge(
            isset($all[(string) $user_id]) ? $all[(string) $user_id] : array(),
            $record,
            array(
                'updated_at' => current_time('mysql'),
                'updated_by' => get_current_user_id(),
            )
        );
        update_option(PAXdesign_Live_Chat_Permissions::OPTION_STAFF, $all, false);

        $user = get_user_by('id', $user_id);
        return array(
            'ok'     => true,
            'member' => $user ? self::format_member_row($user, $record) : null,
        );
    }

    /**
     * Whether $requester may initiate contact with $target without an approval request.
     *
     * @param int $requester_id
     * @param int $target_id
     */
    public static function can_initiate_contact($requester_id, $target_id) {
        $requester_id = absint($requester_id);
        $target_id    = absint($target_id);
        if ($requester_id <= 0 || $target_id <= 0 || $requester_id === $target_id) {
            return false;
        }
        if (self::is_executive_director($requester_id)) {
            return true;
        }
        if (self::is_executive_director($target_id)) {
            return false;
        }
        return !PAXdesign_Live_Chat_Permissions::requires_team_conversation_approval($requester_id, $target_id);
    }

    /**
     * @param string               $email
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    public static function add_member_by_email($email, $data = array()) {
        $email = sanitize_email((string) $email);
        if ($email === '') {
            return new WP_Error('invalid_email', 'Valid email required.', array('status' => 400));
        }
        $user = get_user_by('email', $email);
        if (!$user instanceof WP_User) {
            return new WP_Error('user_not_found', 'No WordPress user found for this email.', array('status' => 404));
        }
        if (self::is_executive_director($user)) {
            return new WP_Error('protected_user', 'Executive Director is already on the roster.', array('status' => 400));
        }

        $existing = PAXdesign_Live_Chat_Permissions::get_staff_record((int) $user->ID);
        if (is_array($existing)) {
            return new WP_Error('already_member', 'User is already on the team roster.', array('status' => 400));
        }

        $role = self::ROLE_TEAM_MEMBER;
        if (!empty($data['team_role'])) {
            $role = sanitize_key((string) $data['team_role']);
            if (!in_array($role, array_values(self::role_options()), true) || $role === self::ROLE_EXECUTIVE_DIRECTOR) {
                return new WP_Error('invalid_role', 'Invalid team role.', array('status' => 400));
            }
        }

        $labels = array_keys(PAXdesign_Live_Chat_Permissions::permission_labels());
        $perms  = array();
        if (!empty($data['permissions']) && is_array($data['permissions'])) {
            foreach ($labels as $key) {
                $perms[$key] = !empty($data['permissions'][$key]);
            }
        } else {
            $perms = array(
                PAXdesign_Live_Chat_Permissions::PERM_VIEW_CHATS           => true,
                PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS          => true,
                PAXdesign_Live_Chat_Permissions::PERM_USE_AI               => true,
                PAXdesign_Live_Chat_Permissions::PERM_SEND_IMAGES          => true,
                PAXdesign_Live_Chat_Permissions::PERM_MANAGE_SETTINGS      => false,
                PAXdesign_Live_Chat_Permissions::PERM_VIEW_RATINGS         => false,
                PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS         => false,
                PAXdesign_Live_Chat_Permissions::PERM_ACCESS_SECURITY       => false,
                PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS => false,
                PAXdesign_Live_Chat_Permissions::PERM_MANAGE_CUSTOMER_PROFILES => false,
                PAXdesign_Live_Chat_Permissions::PERM_ASSIGN_TEAM_TASKS    => false,
                PAXdesign_Live_Chat_Permissions::PERM_CUSTOMIZE_HUB_PROFILE => false,
            );
        }

        $result = PAXdesign_Live_Chat_Permissions::save_staff_record((int) $user->ID, array(
            'enabled'     => !array_key_exists('enabled', $data) || !empty($data['enabled']),
            'permissions' => $perms,
            'team_role'   => $role,
        ));
        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'ok'     => true,
            'member' => self::format_member_row($user, PAXdesign_Live_Chat_Permissions::get_staff_record((int) $user->ID) ?: array()),
        );
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>|WP_Error
     */
    public static function remove_member($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('invalid_user', 'Invalid user.', array('status' => 400));
        }
        if (self::is_executive_director($user_id)) {
            return new WP_Error('protected_user', 'Executive Director cannot be removed.', array('status' => 400));
        }
        $result = PAXdesign_Live_Chat_Permissions::remove_staff($user_id);
        if (is_wp_error($result)) {
            return $result;
        }
        return array('ok' => true);
    }
}
