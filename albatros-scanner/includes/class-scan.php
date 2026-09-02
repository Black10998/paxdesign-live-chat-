<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Scan {
    const COOKIE = 'alb_guest_name';
    const ACTIONS = array(
        'opened',
        'take_over',
        'return',
        'mark_lost',
        'mark_defective',
        'mark_returned',
        'status',
        'deactivate',
        'restore',
    );

    public static function table() {
        return Alb_Install::table('scan_events');
    }

    public static function identity() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return array(
                'kind' => 'user',
                'actor_id' => (int) $user->ID,
                'actor_name' => $user->display_name,
                'identified' => true,
            );
        }
        $name = self::guest_name();
        if ($name !== '') {
            return array(
                'kind' => 'guest',
                'actor_id' => 0,
                'actor_name' => $name,
                'identified' => true,
            );
        }
        return array(
            'kind' => 'guest',
            'actor_id' => 0,
            'actor_name' => '',
            'identified' => false,
        );
    }

    public static function guest_name() {
        if (empty($_COOKIE[self::COOKIE])) {
            return '';
        }
        $name = self::normalize_name(wp_unslash($_COOKIE[self::COOKIE]));
        return is_wp_error($name) ? '' : $name;
    }

    public static function set_guest_name($name) {
        $name = self::normalize_name($name);
        if (is_wp_error($name)) {
            return $name;
        }
        if (!headers_sent()) {
            setcookie(self::COOKIE, $name, time() + (30 * DAY_IN_SECONDS), COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        }
        $_COOKIE[self::COOKIE] = $name;
        return $name;
    }

    public static function normalize_name($name) {
        $name = trim(preg_replace('/\s+/', ' ', sanitize_text_field($name)));
        if (strlen($name) < 3 || strlen($name) > 80) {
            return new WP_Error('alb_invalid', Alb_I18n::t('scan.error.name'), array('status' => 400));
        }
        if (!preg_match('/[\p{L}]/u', $name)) {
            return new WP_Error('alb_invalid', Alb_I18n::t('scan.error.name'), array('status' => 400));
        }
        return $name;
    }

    public static function record($scanner, $action, $notes = '') {
        $identity = self::identity();
        if (empty($identity['identified'])) {
            return new WP_Error('alb_invalid', Alb_I18n::t('scan.error.name'), array('status' => 400));
        }
        $action = sanitize_key($action);
        if (!in_array($action, self::ACTIONS, true)) {
            $action = 'opened';
        }
        global $wpdb;
        $now = Alb_Settings::now_mysql();
        $wpdb->insert(self::table(), array(
            'scanner_id' => (int) $scanner['id'],
            'serial_number' => self::display_serial($scanner['serial_number'] ?? ''),
            'scanner_code' => $scanner['scanner_code'] ?? '',
            'actor_id' => (int) $identity['actor_id'],
            'actor_name' => $identity['actor_name'],
            'actor_kind' => $identity['kind'],
            'action' => $action,
            'notes' => sanitize_textarea_field($notes),
            'ip_address' => self::ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : '',
            'created_at' => $now,
        ));
        Alb_Audit::record(array(
            'action' => 'scanner_scan',
            'entity_type' => 'scanner',
            'entity_id' => (int) $scanner['id'],
            'scanner_id' => (int) $scanner['id'],
            'driver_id' => $scanner['current_driver_id'] ?? null,
            'field' => 'scan',
            'old' => '',
            'new' => $action,
            'actor_id' => (int) $identity['actor_id'],
            'actor_name' => $identity['actor_name'],
        ));
        return (int) $wpdb->insert_id;
    }

    public static function maybe_record_open($scanner) {
        $identity = self::identity();
        if (empty($identity['identified']) || empty($scanner['id'])) {
            return 0;
        }
        global $wpdb;
        $recent = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE scanner_id = %d AND actor_name = %s AND action = %s AND created_at > %s ORDER BY id DESC LIMIT 1',
            (int) $scanner['id'],
            $identity['actor_name'],
            'opened',
            gmdate('Y-m-d H:i:s', time() - 600)
        ));
        if ($recent) {
            return (int) $recent;
        }
        return self::record($scanner, 'opened');
    }

    public static function history($scanner_id, $limit = 40) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE scanner_id = %d ORDER BY id DESC LIMIT %d',
            (int) $scanner_id,
            (int) $limit
        ), ARRAY_A);
        return array_map(array(__CLASS__, 'present'), $rows ?: array());
    }

    public static function present($row) {
        return array(
            'type' => 'scan',
            'id' => (int) $row['id'],
            'action' => $row['action'],
            'actor_id' => (int) $row['actor_id'],
            'actor_name' => $row['actor_name'],
            'actor_kind' => $row['actor_kind'],
            'serial_number' => $row['serial_number'],
            'at' => $row['created_at'],
            'at_display' => Alb_Settings::format_datetime($row['created_at']),
            'notes' => $row['notes'],
        );
    }

    public static function display_serial($serial) {
        return (string) preg_replace('/#DEL\d+$/', '', (string) $serial);
    }

    private static function ip() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($parts[0]));
        }
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    }
}
