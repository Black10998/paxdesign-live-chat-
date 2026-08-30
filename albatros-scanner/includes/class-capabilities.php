<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Capabilities {
    const ROLE_META = 'alb_role';
    const PERMS_OPTION = 'alb_role_permissions';

    const SUPER_ADMIN = 'super_admin';
    const ADMINISTRATOR = 'administrator';
    const STAFF = 'staff';

    public static function roles() {
        return array(self::SUPER_ADMIN, self::ADMINISTRATOR, self::STAFF);
    }

    public static function permission_keys() {
        return array(
            'dashboard.view',
            'scanners.view',
            'scanners.create',
            'scanners.edit',
            'scanners.assign',
            'scanners.status',
            'scanners.delete',
            'drivers.view',
            'drivers.create',
            'drivers.edit',
            'drivers.deactivate',
            'history.view',
            'audit.view',
            'users.view',
            'users.manage',
            'roles.manage',
            'settings.view',
            'settings.manage',
            'reports.export',
            'qr.view',
        );
    }

    public static function defaults() {
        $all = array_fill_keys(self::permission_keys(), true);
        $admin = $all;
        $admin['users.view'] = false;
        $admin['users.manage'] = false;
        $admin['roles.manage'] = false;
        $admin['settings.manage'] = false;
        $admin['audit.view'] = false;
        $admin['scanners.delete'] = false;
        $staff = array_fill_keys(self::permission_keys(), false);
        return array(
            self::SUPER_ADMIN => $all,
            self::ADMINISTRATOR => $admin,
            self::STAFF => $staff,
        );
    }

    public static function map() {
        $stored = get_option(self::PERMS_OPTION, array());
        $defaults = self::defaults();
        foreach ($defaults as $role => $perms) {
            if (!isset($stored[$role]) || !is_array($stored[$role])) {
                $stored[$role] = $perms;
                continue;
            }
            $stored[$role] = array_merge($perms, array_intersect_key($stored[$role], $perms));
        }
        $stored[self::SUPER_ADMIN] = array_fill_keys(self::permission_keys(), true);
        $stored[self::STAFF] = array_fill_keys(self::permission_keys(), false);
        return $stored;
    }

    public static function save_map($map) {
        $clean = self::defaults();
        foreach (self::roles() as $role) {
            if ($role === self::SUPER_ADMIN) {
                continue;
            }
            if (!isset($map[$role]) || !is_array($map[$role])) {
                continue;
            }
            foreach (self::permission_keys() as $key) {
                $clean[$role][$key] = !empty($map[$role][$key]);
            }
        }
        $clean[self::SUPER_ADMIN] = array_fill_keys(self::permission_keys(), true);
        update_option(self::PERMS_OPTION, $clean, false);
        return $clean;
    }

    public static function role_of($user) {
        $user = self::resolve_user($user);
        if (!$user) {
            return '';
        }
        $role = get_user_meta($user->ID, self::ROLE_META, true);
        if (in_array($role, self::roles(), true)) {
            return $role;
        }
        if (user_can($user, 'manage_options')) {
            return self::SUPER_ADMIN;
        }
        return self::STAFF;
    }

    public static function set_role($user_id, $role) {
        $role = in_array($role, self::roles(), true) ? $role : self::STAFF;
        update_user_meta((int) $user_id, self::ROLE_META, $role);
        return $role;
    }

    public static function user_can($user, $permission) {
        $user = self::resolve_user($user);
        if (!$user) {
            return false;
        }
        $role = self::role_of($user);
        if ($role === self::SUPER_ADMIN) {
            return true;
        }
        $map = self::map();
        return !empty($map[$role][$permission]);
    }

    public static function current_user_can($permission) {
        return self::user_can(wp_get_current_user(), $permission);
    }

    public static function can_use_admin_app($user = null) {
        $role = self::role_of($user === null ? wp_get_current_user() : $user);
        return in_array($role, array(self::SUPER_ADMIN, self::ADMINISTRATOR), true);
    }

    public static function lock_staff() {
        $map = self::map();
        update_option(self::PERMS_OPTION, $map, false);
        return $map;
    }

    public static function require_login() {
        if (!is_user_logged_in()) {
            return new WP_Error('alb_auth', Alb_I18n::t('error.auth_required'), array('status' => 401));
        }
        return true;
    }

    public static function require_permission($permission) {
        $login = self::require_login();
        if (is_wp_error($login)) {
            return $login;
        }
        if (!self::current_user_can($permission)) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
        }
        return true;
    }

    public static function bootstrap_user($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        $existing = get_user_meta($user_id, self::ROLE_META, true);
        if ($existing) {
            return;
        }
        self::set_role($user_id, user_can($user, 'manage_options') ? self::SUPER_ADMIN : self::STAFF);
    }

    private static function resolve_user($user) {
        if ($user instanceof WP_User) {
            return $user->exists() ? $user : null;
        }
        if (is_numeric($user)) {
            $found = get_userdata((int) $user);
            return $found ?: null;
        }
        return null;
    }
}
