<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Drivers {
    public static function table() {
        return Alb_Install::table('drivers');
    }

    public static function create($data, $user_id) {
        $first = sanitize_text_field($data['first_name'] ?? '');
        $last = sanitize_text_field($data['last_name'] ?? '');
        if ($first === '' || $last === '') {
            return new WP_Error('alb_invalid', Alb_I18n::t('driver.error.name_required'), array('status' => 400));
        }
        $now = Alb_Settings::now_mysql();
        global $wpdb;
        $ok = $wpdb->insert(self::table(), array(
            'first_name' => $first,
            'last_name' => $last,
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'employee_code' => sanitize_text_field($data['employee_code'] ?? ''),
            'status' => !empty($data['status']) && $data['status'] === 'inactive' ? 'inactive' : 'active',
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_at' => $now,
            'created_by' => (int) $user_id,
            'updated_at' => $now,
            'updated_by' => (int) $user_id,
        ));
        if (!$ok) {
            return new WP_Error('alb_db', Alb_I18n::t('error.save_failed'), array('status' => 500));
        }
        $id = (int) $wpdb->insert_id;
        Alb_Audit::record(array(
            'action' => 'driver_create',
            'entity_type' => 'driver',
            'entity_id' => $id,
            'driver_id' => $id,
            'field' => 'name',
            'new' => trim($first . ' ' . $last),
        ));
        return self::get($id);
    }

    public static function update($id, $data, $user_id) {
        $current = self::get($id);
        if (!$current) {
            return new WP_Error('alb_not_found', Alb_I18n::t('driver.error.not_found'), array('status' => 404));
        }
        $map = array(
            'first_name' => 'sanitize_text_field',
            'last_name' => 'sanitize_text_field',
            'phone' => 'sanitize_text_field',
            'employee_code' => 'sanitize_text_field',
            'notes' => 'sanitize_textarea_field',
        );
        $fields = array();
        $changes = array();
        foreach ($map as $key => $cb) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $cb($data[$key]);
            if ((string) $value !== (string) $current[$key]) {
                $fields[$key] = $value;
                $changes[$key] = array($current[$key], $value);
            }
        }
        if (array_key_exists('email', $data)) {
            $value = sanitize_email($data['email']);
            if ($value !== (string) $current['email']) {
                $fields['email'] = $value;
                $changes['email'] = array($current['email'], $value);
            }
        }
        if (array_key_exists('status', $data)) {
            $value = $data['status'] === 'inactive' ? 'inactive' : 'active';
            if ($value !== $current['status']) {
                $fields['status'] = $value;
                $changes['status'] = array($current['status'], $value);
            }
        }
        if (!$fields) {
            return $current;
        }
        $fields['updated_at'] = Alb_Settings::now_mysql();
        $fields['updated_by'] = (int) $user_id;
        global $wpdb;
        $wpdb->update(self::table(), $fields, array('id' => (int) $id));
        foreach ($changes as $field => $pair) {
            Alb_Audit::record(array(
                'action' => 'driver_update',
                'entity_type' => 'driver',
                'entity_id' => (int) $id,
                'driver_id' => (int) $id,
                'field' => $field,
                'old' => $pair[0],
                'new' => $pair[1],
            ));
        }
        return self::get($id);
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id), ARRAY_A);
        return $row ? self::present($row) : null;
    }

    public static function query($args) {
        global $wpdb;
        $table = self::table();
        $where = array('1=1');
        $params = array();
        if (!empty($args['q'])) {
            $q = '%' . $wpdb->esc_like($args['q']) . '%';
            $where[] = "(first_name LIKE %s OR last_name LIKE %s OR phone LIKE %s OR email LIKE %s OR employee_code LIKE %s OR CONCAT(first_name, ' ', last_name) LIKE %s)";
            array_push($params, $q, $q, $q, $q, $q, $q);
        }
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'] === 'inactive' ? 'inactive' : 'active';
        }
        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = max(10, min(200, (int) ($args['per_page'] ?? Alb_Settings::get()['items_per_page'])));
        $offset = ($page - 1) * $per_page;
        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY last_name ASC, first_name ASC LIMIT %d OFFSET %d";
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

    public static function assigned_scanners($driver_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Alb_Scanners::table() . ' WHERE current_driver_id = %d ORDER BY scanner_code ASC',
            (int) $driver_id
        ), ARRAY_A);
        return array_map(function ($row) {
            return Alb_Scanners::present($row, false);
        }, $rows ?: array());
    }

    public static function history($driver_id) {
        global $wpdb;
        $handovers = Alb_Install::table('handovers');
        $scanners = Alb_Scanners::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, s.serial_number, s.scanner_code, s.brand, s.model
             FROM $handovers h
             INNER JOIN $scanners s ON s.id = h.scanner_id
             WHERE h.driver_id = %d OR h.previous_driver_id = %d
             ORDER BY h.handover_at DESC, h.id DESC",
            (int) $driver_id,
            (int) $driver_id
        ), ARRAY_A);
        return array_map(function ($row) {
            return array(
                'id' => (int) $row['id'],
                'scanner_id' => (int) $row['scanner_id'],
                'scanner_code' => $row['scanner_code'],
                'serial_number' => $row['serial_number'],
                'brand' => $row['brand'],
                'model' => $row['model'],
                'action' => $row['action'],
                'at' => $row['handover_at'],
                'at_display' => Alb_Settings::format_datetime($row['handover_at']),
                'notes' => $row['notes'],
            );
        }, $rows ?: array());
    }

    public static function options() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT id, first_name, last_name, status FROM " . self::table() . " ORDER BY last_name ASC, first_name ASC", ARRAY_A);
        return array_map(function ($row) {
            return array(
                'id' => (int) $row['id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'status' => $row['status'],
            );
        }, $rows ?: array());
    }

    public static function present($row) {
        return array(
            'id' => (int) $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'phone' => $row['phone'],
            'email' => $row['email'],
            'employee_code' => $row['employee_code'],
            'status' => $row['status'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        );
    }
}
