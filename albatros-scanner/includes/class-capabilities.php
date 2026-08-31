<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Capabilities {
    const ROLE_META = 'alb_role';
    const PERMS_OPTION = 'alb_role_permissions';
    const USER_PERMS = 'alb_permissions';
    const PRIMARY_EMAIL = 'sarah.gta1995@gmail.com';
    const SCHEMA_OPTION = 'alb_role_schema';
    const SCHEMA_VERSION = 4;

    const SUPER_ADMIN = 'super_admin';
    const ADMINISTRATOR = 'administrator';
    const SCANNER_ADMIN = 'scanner_admin';
    const STAFF = 'staff';

    public static function roles() {
        return array(self::SUPER_ADMIN, self::ADMINISTRATOR, self::SCANNER_ADMIN, self::STAFF);
    }

    public static function privileged_keys() {
        return array(
            'scanners.identity',
            'users.manage',
            'roles.manage',
            'settings.manage',
        );
    }

    public static function extra_permission_keys() {
        return array(
            'scanners.identity',
            'users.manage',
            'roles.manage',
            'settings.manage',
            'audit.view',
        );
    }

    public static function permission_keys() {
        return array(
            'dashboard.view',
            'scanners.view',
            'scanners.create',
            'scanners.edit',
            'scanners.identity',
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
        $none = array_fill_keys(self::permission_keys(), false);
        $all = array_fill_keys(self::permission_keys(), true);
        $employee = $none;
        $employee['dashboard.view'] = true;
        $employee['scanners.view'] = true;
        $employee['scanners.assign'] = true;
        $employee['scanners.status'] = true;
        $employee['qr.view'] = true;
        $scanner = $employee;
        $scanner['scanners.create'] = true;
        $scanner['scanners.edit'] = true;
        $scanner['drivers.view'] = true;
        $scanner['drivers.create'] = true;
        $scanner['drivers.edit'] = true;
        $scanner['drivers.deactivate'] = true;
        $scanner['history.view'] = true;
        $admin = $scanner;
        $admin['reports.export'] = true;
        $admin['users.view'] = true;
        $admin['audit.view'] = true;
        $admin['settings.view'] = true;
        return array(
            self::SUPER_ADMIN => $all,
            self::ADMINISTRATOR => $admin,
            self::SCANNER_ADMIN => $scanner,
            self::STAFF => $employee,
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
        foreach (self::privileged_keys() as $key) {
            $stored[self::ADMINISTRATOR][$key] = false;
            $stored[self::SCANNER_ADMIN][$key] = false;
            $stored[self::STAFF][$key] = false;
        }
        $stored[self::STAFF]['scanners.edit'] = false;
        return $stored;
    }

    public static function save_map($map) {
        if (!self::is_primary()) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.primary_protected'), array('status' => 403));
        }
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
            foreach (self::privileged_keys() as $key) {
                $clean[$role][$key] = false;
            }
            if ($role === self::STAFF) {
                $clean[$role]['scanners.edit'] = false;
            }
        }
        $clean[self::SUPER_ADMIN] = array_fill_keys(self::permission_keys(), true);
        update_option(self::PERMS_OPTION, $clean, false);
        return $clean;
    }

    public static function is_primary($user = null) {
        $user = self::resolve_user($user === null ? wp_get_current_user() : $user);
        if (!$user) {
            return false;
        }
        return strtolower($user->user_email) === self::PRIMARY_EMAIL;
    }

    public static function primary_user() {
        return get_user_by('email', self::PRIMARY_EMAIL);
    }

    public static function ensure_primary() {
        $primary = self::primary_user();
        if ($primary) {
            self::set_role($primary->ID, self::SUPER_ADMIN);
            delete_user_meta($primary->ID, self::USER_PERMS);
            update_user_meta($primary->ID, 'alb_status', 'active');
        }
        $reset = (int) get_option(self::SCHEMA_OPTION, 0) < self::SCHEMA_VERSION;
        foreach (get_users(array('fields' => 'all')) as $user) {
            if (self::is_primary($user)) {
                continue;
            }
            $role = get_user_meta($user->ID, self::ROLE_META, true);
            if ($role === self::SUPER_ADMIN || ($role === '' && user_can($user, 'manage_options'))) {
                self::set_role($user->ID, self::ADMINISTRATOR);
                delete_user_meta($user->ID, self::USER_PERMS);
                continue;
            }
            if (!$reset) {
                continue;
            }
            $overrides = self::user_permissions($user);
            if (!$overrides) {
                continue;
            }
            $changed = false;
            foreach (self::privileged_keys() as $key) {
                if (!empty($overrides[$key])) {
                    unset($overrides[$key]);
                    $changed = true;
                }
            }
            if ($changed) {
                if ($overrides) {
                    update_user_meta($user->ID, self::USER_PERMS, $overrides);
                } else {
                    delete_user_meta($user->ID, self::USER_PERMS);
                }
            }
        }
        if ($reset) {
            update_option(self::PERMS_OPTION, self::defaults(), false);
            update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        }
    }

    public static function role_of($user) {
        $user = self::resolve_user($user);
        if (!$user) {
            return '';
        }
        if (self::is_primary($user)) {
            return self::SUPER_ADMIN;
        }
        $role = get_user_meta($user->ID, self::ROLE_META, true);
        if (in_array($role, self::roles(), true)) {
            return $role;
        }
        return self::STAFF;
    }

    public static function set_role($user_id, $role) {
        $role = in_array($role, self::roles(), true) ? $role : self::STAFF;
        update_user_meta((int) $user_id, self::ROLE_META, $role);
        return $role;
    }

    public static function user_permissions($user) {
        $user = self::resolve_user($user);
        if (!$user) {
            return array();
        }
        $stored = get_user_meta($user->ID, self::USER_PERMS, true);
        return is_array($stored) ? $stored : array();
    }

    public static function set_user_permissions($user_id, $permissions) {
        if (!self::is_primary()) {
            return array();
        }
        $clean = array();
        if (is_array($permissions)) {
            foreach (self::permission_keys() as $key) {
                if (array_key_exists($key, $permissions) && !empty($permissions[$key])) {
                    $clean[$key] = true;
                }
            }
        }
        if ($clean) {
            update_user_meta((int) $user_id, self::USER_PERMS, $clean);
        } else {
            delete_user_meta((int) $user_id, self::USER_PERMS);
        }
        return $clean;
    }

    public static function user_can($user, $permission) {
        $user = self::resolve_user($user);
        if (!$user) {
            return false;
        }
        if (self::is_primary($user)) {
            return true;
        }
        if (!self::is_active($user)) {
            return false;
        }
        $overrides = self::user_permissions($user);
        if (in_array($permission, self::privileged_keys(), true)) {
            return !empty($overrides[$permission]);
        }
        if (array_key_exists($permission, $overrides)) {
            return !empty($overrides[$permission]);
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

    public static function has_any_permission($user = null) {
        $user = self::resolve_user($user === null ? wp_get_current_user() : $user);
        if (!$user) {
            return false;
        }
        foreach (self::permission_keys() as $key) {
            if (self::user_can($user, $key)) {
                return true;
            }
        }
        return false;
    }

    public static function can_use_admin_app($user = null) {
        $user = $user === null ? wp_get_current_user() : $user;
        if (self::is_primary($user)) {
            return true;
        }
        if (!self::is_active($user)) {
            return false;
        }
        $role = self::role_of($user);
        if (in_array($role, array(self::SUPER_ADMIN, self::ADMINISTRATOR, self::SCANNER_ADMIN), true)) {
            return true;
        }
        return self::has_any_permission($user);
    }

    public static function assignable_roles($actor = null) {
        $actor = self::resolve_user($actor === null ? wp_get_current_user() : $actor);
        if (self::is_primary($actor)) {
            return self::roles();
        }
        if (self::user_can($actor, 'users.manage')) {
            return array(self::SCANNER_ADMIN, self::STAFF);
        }
        return array();
    }

    public static function can_assign_user_permissions($actor = null) {
        return self::is_primary($actor === null ? wp_get_current_user() : $actor);
    }

    public static function status_of($user) {
        $user = self::resolve_user($user);
        if (!$user) {
            return 'inactive';
        }
        if (self::is_primary($user)) {
            return 'active';
        }
        $status = sanitize_key((string) get_user_meta($user->ID, 'alb_status', true));
        return $status === 'inactive' ? 'inactive' : 'active';
    }

    public static function is_active($user) {
        return self::status_of($user) === 'active';
    }

    public static function set_status($user_id, $status) {
        $user = get_userdata((int) $user_id);
        if ($user && self::is_primary($user)) {
            update_user_meta((int) $user_id, 'alb_status', 'active');
            return 'active';
        }
        $status = $status === 'inactive' ? 'inactive' : 'active';
        update_user_meta((int) $user_id, 'alb_status', $status);
        return $status;
    }

    public static function sync_stored_map() {
        self::ensure_primary();
        update_option(self::PERMS_OPTION, self::map(), false);
        return self::map();
    }

    public static function lock_staff() {
        return self::sync_stored_map();
    }

    public static function require_login() {
        if (!is_user_logged_in()) {
            return new WP_Error('alb_auth', Alb_I18n::t('error.auth_required'), array('status' => 401));
        }
        if (!self::is_active(wp_get_current_user())) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('users.error.inactive'), array('status' => 403));
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
        if (self::is_primary($user)) {
            self::set_role($user_id, self::SUPER_ADMIN);
            return;
        }
        $existing = get_user_meta($user_id, self::ROLE_META, true);
        if ($existing) {
            return;
        }
        self::set_role($user_id, self::STAFF);
    }

    public static function resolve_user($user) {
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
