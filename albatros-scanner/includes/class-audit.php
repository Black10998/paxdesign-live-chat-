<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Audit {
    public static function table() {
        return Alb_Install::table('audit_logs');
    }

    public static function record($args) {
        global $wpdb;
        $user = wp_get_current_user();
        $row = array(
            'actor_id' => $user && $user->exists() ? (int) $user->ID : 0,
            'actor_name' => $user && $user->exists() ? $user->display_name : 'system',
            'action' => sanitize_key($args['action'] ?? 'change'),
            'entity_type' => sanitize_key($args['entity_type'] ?? ''),
            'entity_id' => isset($args['entity_id']) ? (int) $args['entity_id'] : 0,
            'scanner_id' => isset($args['scanner_id']) ? (int) $args['scanner_id'] : null,
            'driver_id' => isset($args['driver_id']) ? (int) $args['driver_id'] : null,
            'field_name' => sanitize_text_field($args['field'] ?? ''),
            'old_value' => isset($args['old']) ? (string) $args['old'] : '',
            'new_value' => isset($args['new']) ? (string) $args['new'] : '',
            'ip_address' => self::ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : '',
            'created_at' => Alb_Settings::now_mysql(),
        );
        $wpdb->insert(self::table(), $row, array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'));
        return (int) $wpdb->insert_id;
    }

    public static function query($args) {
        global $wpdb;
        $table = self::table();
        $where = array('1=1');
        $params = array();
        if (!empty($args['q'])) {
            $like = '%' . $wpdb->esc_like($args['q']) . '%';
            $where[] = '(actor_name LIKE %s OR action LIKE %s OR field_name LIKE %s OR old_value LIKE %s OR new_value LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($args['scanner_id'])) {
            $where[] = 'scanner_id = %d';
            $params[] = (int) $args['scanner_id'];
        }
        if (!empty($args['driver_id'])) {
            $where[] = 'driver_id = %d';
            $params[] = (int) $args['driver_id'];
        }
        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = max(10, min(200, (int) ($args['per_page'] ?? Alb_Settings::get()['items_per_page'])));
        $offset = ($page - 1) * $per_page;
        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array(
            'items' => array_map(array(__CLASS__, 'present'), $rows ?: array()),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
        );
    }

    public static function present($row) {
        return array(
            'id' => (int) $row['id'],
            'actor_id' => (int) $row['actor_id'],
            'actor_name' => $row['actor_name'],
            'action' => $row['action'],
            'entity_type' => $row['entity_type'],
            'entity_id' => (int) $row['entity_id'],
            'scanner_id' => $row['scanner_id'] ? (int) $row['scanner_id'] : null,
            'driver_id' => $row['driver_id'] ? (int) $row['driver_id'] : null,
            'field' => $row['field_name'],
            'old_value' => $row['old_value'],
            'new_value' => $row['new_value'],
            'created_at' => $row['created_at'],
            'created_at_display' => Alb_Settings::format_datetime($row['created_at']),
        );
    }

    public static function purge_expired() {
        $days = (int) Alb_Settings::get()['audit_retention_days'];
        if ($days <= 0) {
            return 0;
        }
        global $wpdb;
        return (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE created_at < %s',
            gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS))
        ));
    }

    private static function ip() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($parts[0]));
        }
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    }
}
