<?php
/**
 * Employee device session registry for PAXDesign Live Chat mobile app.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Device_Sessions {
    const ONLINE_WINDOW_SECONDS = 180;

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function merge_device_meta($record, $meta, $now = 0) {
        if (!is_array($meta)) {
            $meta = array();
        }
        $now = $now > 0 ? $now : time();

        $device_id = isset($meta['device_id']) ? sanitize_text_field($meta['device_id']) : '';
        if ($device_id === '' && !empty($record['device_id'])) {
            $device_id = (string) $record['device_id'];
        }

        $merged = $record;
        if ($device_id !== '') {
            $merged['device_id'] = $device_id;
        }
        if (!empty($meta['device_name'])) {
            $merged['device_name'] = sanitize_text_field($meta['device_name']);
        }
        if (!empty($meta['device_model'])) {
            $merged['device_model'] = sanitize_text_field($meta['device_model']);
        }
        if (!empty($meta['os_version'])) {
            $merged['os_version'] = sanitize_text_field($meta['os_version']);
        }
        if (!empty($meta['app_version'])) {
            $merged['app_version'] = sanitize_text_field($meta['app_version']);
        }
        if (empty($merged['first_login_at'])) {
            $merged['first_login_at'] = $now;
        }
        $merged['last_active_at'] = $now;

        $ip = self::client_ip();
        if ($ip !== '') {
            $merged['ip_address'] = $ip;
            $merged['location'] = self::lookup_location($ip);
        }

        if (!empty($meta['force_logout'])) {
            $merged['revoked'] = true;
            $merged['approved'] = false;
            $merged['revoked_at'] = $now;
        }

        if (!isset($merged['approved'])) {
            $merged['approved'] = empty($merged['revoked']);
        }
        if (!empty($merged['revoked'])) {
            $merged['approved'] = false;
        }

        return $merged;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get_user_devices($user_id) {
        if (class_exists('PAXdesign_APNS')) {
            return PAXdesign_APNS::get_user_devices($user_id);
        }
        $all = get_user_meta((int) $user_id, PAXdesign_APNS::USER_META_KEY, true);
        return is_array($all) ? $all : array();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_employee_devices($filter_user_id = 0, $current_device_id = '') {
        $users = get_users(array('fields' => array('ID', 'display_name', 'user_email')));
        $rows  = array();

        foreach ($users as $user) {
            $uid = (int) $user->ID;
            if ($uid <= 0) {
                continue;
            }
            if ($filter_user_id > 0 && $uid !== $filter_user_id) {
                continue;
            }
            if (!PAXdesign_Live_Chat_Permissions::has_live_chat_access($uid)) {
                continue;
            }

            $devices = self::get_user_devices($uid);
            foreach ($devices as $token => $device) {
                if (!is_array($device)) {
                    continue;
                }
                $rows[] = self::format_device_row($uid, $user, $token, $device, $current_device_id);
            }
        }

        usort($rows, function ($a, $b) {
            return (int) ($b['last_active_at'] ?? 0) <=> (int) ($a['last_active_at'] ?? 0);
        });

        return $rows;
    }

    /**
     * @param object|WP_User $user
     * @param array<string, mixed> $device
     * @return array<string, mixed>
     */
    private static function format_device_row($user_id, $user, $token, $device, $current_device_id = '') {
        $display_name = '';
        $email        = '';
        if (is_object($user)) {
            $display_name = isset($user->display_name) ? (string) $user->display_name : '';
            $email        = isset($user->user_email) ? (string) $user->user_email : '';
        }

        $device_id   = !empty($device['device_id']) ? (string) $device['device_id'] : substr($token, 0, 12);
        $revoked     = !empty($device['revoked']);
        $approved    = isset($device['approved']) ? !empty($device['approved']) : !$revoked;
        $last_active = (int) ($device['last_active_at'] ?? $device['updated_at'] ?? 0);
        $is_online   = !$revoked && $approved && $last_active > 0 && (time() - $last_active) <= self::ONLINE_WINDOW_SECONDS;
        $is_current  = $current_device_id !== '' && $current_device_id === $device_id;
        $has_token   = !empty($device['token']) && empty($device['session_only']);

        return array(
            'user_id'        => (int) $user_id,
            'employee_name'  => $display_name,
            'employee_email' => $email,
            'device_id'      => $device_id,
            'device_token'   => $has_token ? substr((string) $device['token'], 0, 8) . '…' : '',
            'device_name'    => (string) ($device['device_name'] ?? 'Unbekanntes Gerät'),
            'device_model'   => (string) ($device['device_model'] ?? ''),
            'os_version'     => (string) ($device['os_version'] ?? ''),
            'app_version'    => (string) ($device['app_version'] ?? ''),
            'first_login_at' => (int) ($device['first_login_at'] ?? 0),
            'last_active_at' => $last_active,
            'ip_address'     => (string) ($device['ip_address'] ?? ''),
            'location'       => (string) ($device['location'] ?? ''),
            'revoked'        => $revoked,
            'approved'       => $approved,
            'online'         => $is_online,
            'is_current'     => $is_current,
            'sandbox'        => !empty($device['sandbox']),
            'push_registered'=> $has_token,
            'push_environment' => !empty($device['sandbox']) ? 'sandbox' : 'production',
        );
    }

    public static function heartbeat($user_id, $device_id, $meta = array()) {
        $devices = self::get_user_devices($user_id);
        foreach ($devices as $token => $device) {
            if (!is_array($device)) {
                continue;
            }
            $id = !empty($device['device_id']) ? (string) $device['device_id'] : '';
            if ($id !== $device_id) {
                continue;
            }
            if (!empty($device['revoked'])) {
                return new WP_Error('device_revoked', 'This device session has been revoked.', array('status' => 403));
            }
            if (isset($device['approved']) && empty($device['approved'])) {
                return new WP_Error('device_not_approved', 'This device is awaiting administrator approval.', array('status' => 403));
            }
            $devices[$token] = self::merge_device_meta($device, $meta);
            update_user_meta((int) $user_id, PAXdesign_APNS::USER_META_KEY, $devices);
            return rest_ensure_response(array(
                'ok' => true,
                'revoked' => false,
                'push_registered' => !empty($devices[$token]['token']) && empty($devices[$token]['session_only']),
            ));
        }
        return new WP_Error('device_not_found', 'Device not registered.', array('status' => 404));
    }

    public static function revoke_device($admin_id, $target_user_id, $device_id, $force_logout = true) {
        $can_manage = PAXdesign_Live_Chat_Permissions::can($admin_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)
            || PAXdesign_Live_Chat_Permissions::can($admin_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!$can_manage) {
            return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
        }

        $devices = self::get_user_devices($target_user_id);
        $found   = false;

        foreach ($devices as $token => $device) {
            if (!is_array($device)) {
                continue;
            }
            $id = !empty($device['device_id']) ? (string) $device['device_id'] : '';
            if ($id !== $device_id) {
                continue;
            }
            $found = true;
            $devices[$token]['revoked'] = true;
            $devices[$token]['approved'] = false;
            $devices[$token]['revoked_at'] = time();
            $devices[$token]['revoked_by'] = (int) $admin_id;
            if (!$force_logout) {
                unset($devices[$token]);
            }
            break;
        }

        if (!$found) {
            return new WP_Error('device_not_found', 'Device not found.', array('status' => 404));
        }

        update_user_meta((int) $target_user_id, PAXdesign_APNS::USER_META_KEY, $devices);
        return rest_ensure_response(array('ok' => true));
    }

    public static function approve_device($admin_id, $target_user_id, $device_id) {
        $can_manage = PAXdesign_Live_Chat_Permissions::can($admin_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)
            || PAXdesign_Live_Chat_Permissions::can($admin_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!$can_manage) {
            return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
        }

        $devices = self::get_user_devices($target_user_id);
        $found   = false;
        foreach ($devices as $token => $device) {
            if (!is_array($device)) {
                continue;
            }
            $id = !empty($device['device_id']) ? (string) $device['device_id'] : '';
            if ($id !== $device_id) {
                continue;
            }
            $found = true;
            $devices[$token]['revoked'] = false;
            $devices[$token]['approved'] = true;
            $devices[$token]['approved_at'] = time();
            $devices[$token]['approved_by'] = (int) $admin_id;
            unset($devices[$token]['revoked_at'], $devices[$token]['revoked_by']);
            break;
        }

        if (!$found) {
            return new WP_Error('device_not_found', 'Device not found.', array('status' => 404));
        }

        update_user_meta((int) $target_user_id, PAXdesign_APNS::USER_META_KEY, $devices);
        return rest_ensure_response(array('ok' => true));
    }

    public static function force_logout_user($admin_id, $target_user_id) {
        $can_manage = PAXdesign_Live_Chat_Permissions::can($admin_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)
            || PAXdesign_Live_Chat_Permissions::can($admin_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!$can_manage) {
            return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
        }

        $devices = self::get_user_devices($target_user_id);
        $now = time();
        foreach ($devices as $token => $device) {
            if (!is_array($device)) {
                continue;
            }
            $devices[$token]['revoked'] = true;
            $devices[$token]['approved'] = false;
            $devices[$token]['revoked_at'] = $now;
            $devices[$token]['revoked_by'] = (int) $admin_id;
        }
        update_user_meta((int) $target_user_id, PAXdesign_APNS::USER_META_KEY, $devices);
        return rest_ensure_response(array('ok' => true));
    }

    /**
     * Revoke all older devices once a new device signs in.
     *
     * @param array<string, array<string, mixed>> $devices
     * @return array<string, array<string, mixed>>
     */
    public static function enforce_single_device_login(array $devices, $current_device_id, $except_token = '') {
        $current_device_id = sanitize_text_field((string) $current_device_id);
        if ($current_device_id === '') {
            return $devices;
        }
        $now = time();
        foreach ($devices as $token => $device) {
            if (!is_array($device)) {
                continue;
            }
            if ($except_token !== '' && $token === $except_token) {
                continue;
            }
            $id = !empty($device['device_id']) ? (string) $device['device_id'] : '';
            if ($id === '' || $id === $current_device_id) {
                continue;
            }
            $devices[$token]['revoked'] = true;
            $devices[$token]['approved'] = false;
            $devices[$token]['revoked_at'] = $now;
            $devices[$token]['revoked_reason'] = 'new_login';
        }
        return $devices;
    }

    public static function reset_onboarding($admin_id, $target_user_id) {
        $can_manage = PAXdesign_Live_Chat_Permissions::can($admin_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)
            || PAXdesign_Live_Chat_Permissions::can($admin_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        if (!$can_manage) {
            return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
        }
        delete_user_meta((int) $target_user_id, 'pax_live_onboarding_completed');
        delete_user_meta((int) $target_user_id, 'pax_live_terms_accepted_at');
        delete_user_meta((int) $target_user_id, 'pax_live_permission_notifications');
        delete_user_meta((int) $target_user_id, 'pax_live_permission_location');
        delete_user_meta((int) $target_user_id, 'pax_live_security_device_type');
        delete_user_meta((int) $target_user_id, 'pax_live_security_biometric_available');
        delete_user_meta((int) $target_user_id, 'pax_live_security_biometric_enabled');
        delete_user_meta((int) $target_user_id, 'pax_live_security_pin_enabled');
        delete_user_meta((int) $target_user_id, 'pax_live_security_password_confirmed');
        return rest_ensure_response(array('ok' => true));
    }

    public static function client_ip() {
        $candidates = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($candidates as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }
            $raw = (string) $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $raw);
                $raw   = trim($parts[0]);
            }
            if (filter_var($raw, FILTER_VALIDATE_IP)) {
                return $raw;
            }
        }
        return '';
    }

    public static function lookup_location($ip) {
        $ip = (string) $ip;
        if ($ip === '' || $ip === '127.0.0.1' || strpos($ip, '10.') === 0 || strpos($ip, '192.168.') === 0) {
            return '';
        }

        $cache_key = 'pax_geo_' . md5($ip);
        $cached = get_transient($cache_key);
        if (is_string($cached)) {
            return $cached;
        }

        $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,city,regionName';
        $response = wp_remote_get($url, array('timeout' => 4));
        if (is_wp_error($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || ($body['status'] ?? '') !== 'success') {
            return '';
        }

        $parts = array_filter(array(
            isset($body['city']) ? (string) $body['city'] : '',
            isset($body['regionName']) ? (string) $body['regionName'] : '',
            isset($body['country']) ? (string) $body['country'] : '',
        ));
        $label = implode(', ', $parts);
        if ($label !== '') {
            set_transient($cache_key, $label, DAY_IN_SECONDS);
        }
        return $label;
    }
}
