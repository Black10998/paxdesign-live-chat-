<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Users {
    public static function list_users($args) {
        $q = sanitize_text_field($args['q'] ?? '');
        $query = array(
            'number' => max(10, min(200, (int) ($args['per_page'] ?? 50))),
            'offset' => (max(1, (int) ($args['page'] ?? 1)) - 1) * max(10, min(200, (int) ($args['per_page'] ?? 50))),
            'orderby' => 'display_name',
            'order' => 'ASC',
        );
        if ($q !== '') {
            $query['search'] = '*' . $q . '*';
            $query['search_columns'] = array('user_login', 'user_email', 'display_name');
        }
        $users = get_users($query);
        $total_q = $query;
        unset($total_q['number'], $total_q['offset']);
        $total_q['fields'] = 'ID';
        $total = count(get_users($total_q));
        return array(
            'items' => array_map(array(__CLASS__, 'present'), $users),
            'total' => $total,
            'page' => max(1, (int) ($args['page'] ?? 1)),
            'per_page' => (int) $query['number'],
        );
    }

    public static function get($id) {
        $user = get_userdata((int) $id);
        return $user ? self::present($user) : null;
    }

    public static function create($data) {
        $actor = wp_get_current_user();
        if (!Alb_Capabilities::user_can($actor, 'users.manage')) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
        }
        $username = sanitize_user($data['username'] ?? '', true);
        $email = sanitize_email($data['email'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $role = self::normalize_assignable_role($data['role'] ?? Alb_Capabilities::STAFF, $actor);
        if (is_wp_error($role)) {
            return $role;
        }
        $min = (int) Alb_Settings::get()['min_password_length'];
        if ($username === '' || $email === '' || $password === '') {
            return new WP_Error('alb_invalid', Alb_I18n::t('users.error.required'), array('status' => 400));
        }
        if (!is_email($email)) {
            return new WP_Error('alb_invalid', Alb_I18n::t('users.error.email'), array('status' => 400));
        }
        if (strlen($password) < $min) {
            return new WP_Error('alb_invalid', Alb_I18n::t('users.error.password_length', array('min' => $min)), array('status' => 400));
        }
        if (username_exists($username) || email_exists($email)) {
            return new WP_Error('alb_conflict', Alb_I18n::t('users.error.exists'), array('status' => 409));
        }
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $name !== '' ? $name : $username,
            'role' => $role === Alb_Capabilities::SUPER_ADMIN ? 'administrator' : 'subscriber',
        ));
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        Alb_Capabilities::set_role($user_id, $role);
        if (Alb_Capabilities::can_assign_user_permissions($actor) && isset($data['permissions'])) {
            Alb_Capabilities::set_user_permissions($user_id, $data['permissions']);
        }
        Alb_Audit::record(array(
            'action' => 'user_create',
            'entity_type' => 'user',
            'entity_id' => (int) $user_id,
            'field' => 'username',
            'new' => $username,
        ));
        return self::present(get_userdata($user_id));
    }

    public static function update($id, $data) {
        $actor = wp_get_current_user();
        if (!Alb_Capabilities::user_can($actor, 'users.manage')) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
        }
        $user = get_userdata((int) $id);
        if (!$user) {
            return new WP_Error('alb_not_found', Alb_I18n::t('users.error.not_found'), array('status' => 404));
        }
        $target_role = Alb_Capabilities::role_of($user);
        if ($target_role === Alb_Capabilities::SUPER_ADMIN && Alb_Capabilities::role_of($actor) !== Alb_Capabilities::SUPER_ADMIN) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.role_forbidden'), array('status' => 403));
        }
        $update = array('ID' => (int) $id);
        if (isset($data['name'])) {
            $update['display_name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['email'])) {
            $email = sanitize_email($data['email']);
            if (!is_email($email)) {
                return new WP_Error('alb_invalid', Alb_I18n::t('users.error.email'), array('status' => 400));
            }
            $update['user_email'] = $email;
        }
        if (!empty($data['password'])) {
            $min = (int) Alb_Settings::get()['min_password_length'];
            if (strlen((string) $data['password']) < $min) {
                return new WP_Error('alb_invalid', Alb_I18n::t('users.error.password_length', array('min' => $min)), array('status' => 400));
            }
            $update['user_pass'] = (string) $data['password'];
        }
        $result = wp_update_user($update);
        if (is_wp_error($result)) {
            return $result;
        }
        if (isset($data['role'])) {
            $role = self::normalize_assignable_role($data['role'], $actor);
            if (is_wp_error($role)) {
                return $role;
            }
            if ($target_role === Alb_Capabilities::SUPER_ADMIN && $role !== Alb_Capabilities::SUPER_ADMIN && self::super_admin_count() <= 1) {
                return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.last_super'), array('status' => 403));
            }
            if ($target_role !== $role) {
                Alb_Capabilities::set_role($id, $role);
                Alb_Audit::record(array(
                    'action' => 'user_role',
                    'entity_type' => 'user',
                    'entity_id' => (int) $id,
                    'field' => 'role',
                    'old' => $target_role,
                    'new' => $role,
                ));
            }
        }
        if (Alb_Capabilities::can_assign_user_permissions($actor) && array_key_exists('permissions', $data)) {
            Alb_Capabilities::set_user_permissions($id, $data['permissions']);
        }
        return self::present(get_userdata((int) $id));
    }

    public static function present(WP_User $user) {
        return array(
            'id' => (int) $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'role' => Alb_Capabilities::role_of($user),
            'last_login' => Alb_Auth::last_login($user->ID),
            'last_login_display' => Alb_Auth::last_login_display($user->ID),
            'permissions' => Alb_Capabilities::user_permissions($user),
        );
    }

    private static function normalize_assignable_role($role, $actor) {
        $role = sanitize_key($role);
        if (!in_array($role, Alb_Capabilities::roles(), true)) {
            $role = Alb_Capabilities::STAFF;
        }
        if (!in_array($role, Alb_Capabilities::assignable_roles($actor), true)) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.role_forbidden'), array('status' => 403));
        }
        return $role;
    }

    private static function super_admin_count() {
        $users = get_users(array(
            'meta_key' => Alb_Capabilities::ROLE_META,
            'meta_value' => Alb_Capabilities::SUPER_ADMIN,
            'fields' => 'ID',
        ));
        return count($users);
    }
}
