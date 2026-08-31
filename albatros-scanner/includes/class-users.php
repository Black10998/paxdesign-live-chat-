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
        $branch = Alb_Branches::normalize($args['branch'] ?? '');
        if ($branch !== '') {
            $query['meta_key'] = 'alb_branch';
            $query['meta_value'] = $branch;
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
        Alb_Capabilities::set_status($user_id, 'active');
        if (isset($data['phone'])) {
            update_user_meta($user_id, 'alb_phone', sanitize_text_field($data['phone']));
        }
        if (isset($data['branch'])) {
            update_user_meta($user_id, 'alb_branch', Alb_Branches::normalize($data['branch']));
        }
        if (Alb_Capabilities::can_assign_user_permissions($actor) && isset($data['permissions'])) {
            Alb_Capabilities::set_user_permissions($user_id, $data['permissions']);
        }
        if (!empty($data['create_as_employee'])) {
            Alb_Drivers::upsert_for_user((int) $user_id);
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
        if (Alb_Capabilities::is_primary($user) && !Alb_Capabilities::is_primary($actor)) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.primary_protected'), array('status' => 403));
        }
        $target_role = Alb_Capabilities::role_of($user);
        $next_role = null;
        if (isset($data['role'])) {
            $requested_role = sanitize_key((string) $data['role']);
            if ($requested_role !== '' && $requested_role !== $target_role) {
                $next_role = self::normalize_assignable_role($data['role'], $actor);
                if (is_wp_error($next_role)) {
                    return $next_role;
                }
                if (Alb_Capabilities::is_primary($user) && $next_role !== Alb_Capabilities::SUPER_ADMIN) {
                    return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.primary_role'), array('status' => 403));
                }
                if ($target_role === Alb_Capabilities::SUPER_ADMIN && $next_role !== Alb_Capabilities::SUPER_ADMIN && self::super_admin_count() <= 1) {
                    return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.last_super'), array('status' => 403));
                }
            }
        }
        if (array_key_exists('permissions', $data) && !Alb_Capabilities::can_assign_user_permissions($actor)) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.primary_protected'), array('status' => 403));
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
            if (Alb_Capabilities::is_primary($user) && strtolower($email) !== Alb_Capabilities::PRIMARY_EMAIL) {
                return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.primary_email'), array('status' => 403));
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
        if ($next_role) {
            Alb_Capabilities::set_role($id, $next_role);
            Alb_Audit::record(array(
                'action' => 'user_role',
                'entity_type' => 'user',
                'entity_id' => (int) $id,
                'field' => 'role',
                'old' => $target_role,
                'new' => $next_role,
            ));
        }
        if (array_key_exists('phone', $data)) {
            update_user_meta((int) $id, 'alb_phone', sanitize_text_field($data['phone']));
        }
        if (array_key_exists('branch', $data)) {
            update_user_meta((int) $id, 'alb_branch', Alb_Branches::normalize($data['branch']));
        }
        if (array_key_exists('permissions', $data)) {
            Alb_Capabilities::set_user_permissions($id, $data['permissions']);
        }
        if (isset($data['status'])) {
            $status = sanitize_key((string) $data['status']) === 'inactive' ? 'inactive' : 'active';
            if ($status === 'inactive' && Alb_Capabilities::is_primary($user)) {
                return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.cannot_deactivate_primary'), array('status' => 403));
            }
            Alb_Capabilities::set_status($id, $status);
        }
        if (!empty($data['create_as_employee'])) {
            Alb_Drivers::upsert_for_user((int) $id);
        } else {
            Alb_Drivers::sync_user_profile((int) $id);
        }
        clean_user_cache((int) $id);
        $fresh = get_userdata((int) $id);
        return $fresh ? self::present($fresh) : new WP_Error('alb_not_found', Alb_I18n::t('users.error.not_found'), array('status' => 404));
    }

    public static function set_photo($id, $file) {
        $actor = wp_get_current_user();
        if (!Alb_Capabilities::user_can($actor, 'users.manage')) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
        }
        $user = get_userdata((int) $id);
        if (!$user) {
            return new WP_Error('alb_not_found', Alb_I18n::t('users.error.not_found'), array('status' => 404));
        }
        if (Alb_Capabilities::is_primary($user) && !Alb_Capabilities::is_primary($actor)) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.primary_protected'), array('status' => 403));
        }
        $stored = Alb_Photos::store_upload($file, 'user');
        if (is_wp_error($stored)) {
            return $stored;
        }
        $previous = self::photo_path($id);
        update_user_meta((int) $id, 'alb_photo_path', $stored);
        $driver_id = Alb_Drivers::id_for_user((int) $id);
        if ($driver_id) {
            global $wpdb;
            $wpdb->update(Alb_Drivers::table(), array(
                'photo_path' => $stored,
                'updated_at' => Alb_Settings::now_mysql(),
                'updated_by' => (int) $actor->ID,
            ), array('id' => (int) $driver_id));
        }
        if ($previous !== '' && $previous !== $stored) {
            Alb_Photos::delete_file($previous);
        }
        Alb_Audit::record(array(
            'action' => 'user_photo',
            'entity_type' => 'user',
            'entity_id' => (int) $id,
            'field' => 'photo',
            'new' => 'uploaded',
        ));
        return self::present($user);
    }

    public static function photo_path($user_id) {
        return (string) get_user_meta((int) $user_id, 'alb_photo_path', true);
    }

    public static function present(WP_User $user) {
        $photo = self::photo_path($user->ID);
        return array(
            'id' => (int) $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'phone' => (string) get_user_meta($user->ID, 'alb_phone', true),
            'branch' => Alb_Branches::normalize(get_user_meta($user->ID, 'alb_branch', true)),
            'branch_label' => Alb_Branches::label(get_user_meta($user->ID, 'alb_branch', true)),
            'role' => Alb_Capabilities::role_of($user),
            'status' => Alb_Capabilities::status_of($user),
            'is_primary' => Alb_Capabilities::is_primary($user),
            'photo_path' => $photo,
            'photo_url' => $photo !== '' ? Alb_Photos::admin_url('user', (int) $user->ID, $photo) : '',
            'last_login' => Alb_Auth::last_login($user->ID),
            'last_login_display' => Alb_Auth::last_login_display($user->ID),
            'permissions' => Alb_Capabilities::user_permissions($user),
            'driver_id' => Alb_Drivers::id_for_user((int) $user->ID),
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
        if ($role === Alb_Capabilities::SUPER_ADMIN && !Alb_Capabilities::is_primary($actor)) {
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
