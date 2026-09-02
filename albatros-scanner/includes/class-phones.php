<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handy-Box smartphone inventory.
 *
 * Phones are a separate device category from handheld scanners. Only
 * "available" phones live in the Handy-Box; assigning a phone links it to an
 * employee (driver) and removes it from the box, while returning it puts it
 * back. Every assignment/return is recorded so history is never overwritten.
 */
class Alb_Phones {
    const STATUSES = array('available', 'assigned', 'damaged', 'lost', 'retired');

    public static function table() {
        return Alb_Install::table('phones');
    }

    public static function assignments_table() {
        return Alb_Install::table('phone_assignments');
    }

    public static function statuses() {
        return self::STATUSES;
    }

    public static function normalize_status($status) {
        $status = sanitize_key($status);
        return in_array($status, self::STATUSES, true) ? $status : 'available';
    }

    public static function create($data, $user_id) {
        global $wpdb;
        $model = sanitize_text_field($data['model'] ?? '');
        if ($model === '') {
            return new WP_Error('alb_invalid', Alb_I18n::t('phone.error.model_required'), array('status' => 400));
        }
        $serial = sanitize_text_field($data['serial_number'] ?? '');
        $imei = self::normalize_imei($data['imei'] ?? '');
        if ($serial !== '' && self::find_by('serial_number', $serial)) {
            return new WP_Error('alb_conflict', Alb_I18n::t('phone.error.serial_exists'), array('status' => 409));
        }
        if ($imei !== '' && self::find_by('imei', $imei)) {
            return new WP_Error('alb_conflict', Alb_I18n::t('phone.error.imei_exists'), array('status' => 409));
        }
        $now = Alb_Settings::now_mysql();
        $status = self::normalize_status($data['status'] ?? 'available');
        if ($status === 'assigned') {
            // Assignment happens through assign() so the driver link and history stay consistent.
            $status = 'available';
        }
        $date_added = self::normalize_date($data['date_added'] ?? '');
        $inserted = $wpdb->insert(self::table(), array(
            'model' => $model,
            'serial_number' => $serial,
            'imei' => $imei,
            'branch' => Alb_Branches::normalize($data['branch'] ?? ''),
            'status' => $status,
            'current_driver_id' => null,
            'current_assignment_id' => null,
            'assigned_date' => null,
            'date_added' => $date_added !== '' ? $date_added : substr($now, 0, 10),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_at' => $now,
            'created_by' => (int) $user_id,
            'updated_at' => $now,
            'updated_by' => (int) $user_id,
        ));
        if (!$inserted) {
            return new WP_Error('alb_db', Alb_I18n::t('error.save_failed'), array('status' => 500));
        }
        $id = (int) $wpdb->insert_id;
        Alb_Audit::record(array(
            'action' => 'phone_create',
            'entity_type' => 'phone',
            'entity_id' => $id,
            'field' => 'model',
            'new' => $model . ($serial !== '' ? ' / ' . $serial : ''),
        ));
        $driver_id = Alb_Scanners::person_id_from_request($data, $user_id);
        if (is_wp_error($driver_id)) {
            return $driver_id;
        }
        if ((int) $driver_id > 0) {
            $assigned = self::assign($id, (int) $driver_id, $data['assigned_date'] ?? '', $data['assign_notes'] ?? '', $user_id);
            if (is_wp_error($assigned)) {
                return $assigned;
            }
        }
        return self::get($id);
    }

    public static function update($id, $data, $user_id) {
        $current = self::get($id);
        if (!$current) {
            return new WP_Error('alb_not_found', Alb_I18n::t('phone.error.not_found'), array('status' => 404));
        }
        $fields = array();
        $changes = array();
        $map = array(
            'model' => 'sanitize_text_field',
            'serial_number' => 'sanitize_text_field',
            'notes' => 'sanitize_textarea_field',
        );
        foreach ($map as $key => $cb) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $cb($data[$key]);
            if ($key === 'model' && $value === '') {
                return new WP_Error('alb_invalid', Alb_I18n::t('phone.error.model_required'), array('status' => 400));
            }
            if ($key === 'serial_number' && $value !== '' && $value !== (string) $current['serial_number']) {
                $existing = self::find_by('serial_number', $value);
                if ($existing && (int) $existing['id'] !== (int) $id) {
                    return new WP_Error('alb_conflict', Alb_I18n::t('phone.error.serial_exists'), array('status' => 409));
                }
            }
            if ((string) $value !== (string) $current[$key]) {
                $fields[$key] = $value;
                $changes[$key] = array($current[$key], $value);
            }
        }
        if (array_key_exists('imei', $data)) {
            $value = self::normalize_imei($data['imei']);
            if ($value !== (string) $current['imei']) {
                if ($value !== '') {
                    $existing = self::find_by('imei', $value);
                    if ($existing && (int) $existing['id'] !== (int) $id) {
                        return new WP_Error('alb_conflict', Alb_I18n::t('phone.error.imei_exists'), array('status' => 409));
                    }
                }
                $fields['imei'] = $value;
                $changes['imei'] = array($current['imei'], $value);
            }
        }
        if (array_key_exists('branch', $data)) {
            $value = Alb_Branches::normalize($data['branch']);
            if ($value !== (string) ($current['branch'] ?? '')) {
                $fields['branch'] = $value;
                $changes['branch'] = array($current['branch'] ?? '', $value);
            }
        }
        if (array_key_exists('date_added', $data)) {
            $value = self::normalize_date($data['date_added']);
            if ($value !== (string) ($current['date_added'] ?? '')) {
                $fields['date_added'] = $value ?: null;
                $changes['date_added'] = array($current['date_added'] ?? '', $value);
            }
        }
        $wanted_status = null;
        if (array_key_exists('status', $data)) {
            $wanted_status = self::normalize_status($data['status']);
        }
        if ($fields) {
            $fields['updated_at'] = Alb_Settings::now_mysql();
            $fields['updated_by'] = (int) $user_id;
            self::write_row((int) $id, $fields);
            foreach ($changes as $field => $pair) {
                Alb_Audit::record(array(
                    'action' => 'phone_update',
                    'entity_type' => 'phone',
                    'entity_id' => (int) $id,
                    'field' => $field,
                    'old' => $pair[0],
                    'new' => $pair[1],
                ));
            }
        }
        if ($wanted_status !== null && $wanted_status !== $current['status'] && $wanted_status !== 'assigned') {
            $changed = self::change_status($id, $wanted_status, $data['status_notes'] ?? '', $user_id);
            if (is_wp_error($changed)) {
                return $changed;
            }
        }
        return self::get($id);
    }

    public static function assign($phone_id, $driver_id, $assigned_at, $notes, $user_id) {
        $phone = self::get($phone_id);
        if (!$phone) {
            return new WP_Error('alb_not_found', Alb_I18n::t('phone.error.not_found'), array('status' => 404));
        }
        $driver_id = (int) $driver_id;
        $driver = $driver_id ? Alb_Drivers::get($driver_id) : null;
        if ($driver_id && !$driver) {
            return new WP_Error('alb_not_found', Alb_I18n::t('driver.error.not_found'), array('status' => 404));
        }
        if ($driver && $driver['status'] !== 'active') {
            return new WP_Error('alb_invalid', Alb_I18n::t('driver.error.inactive'), array('status' => 400));
        }
        if (!$driver_id) {
            return self::return_phone($phone_id, $notes, $user_id);
        }
        $when = self::normalize_datetime($assigned_at);
        $previous = $phone['current_driver_id'] ? (int) $phone['current_driver_id'] : null;
        $action = $previous ? ($previous === $driver_id ? 'reassign' : 'reassign') : 'assign';
        global $wpdb;
        $wpdb->insert(self::assignments_table(), array(
            'phone_id' => (int) $phone_id,
            'driver_id' => $driver_id,
            'previous_driver_id' => $previous,
            'action' => $action,
            'assigned_at' => $when,
            'recorded_by' => (int) $user_id,
            'snapshot_name' => $driver ? $driver['name'] : '',
            'snapshot_phone' => $driver ? $driver['phone'] : '',
            'notes' => sanitize_textarea_field($notes),
        ));
        $assignment_id = (int) $wpdb->insert_id;
        self::write_row((int) $phone_id, array(
            'status' => 'assigned',
            'current_driver_id' => $driver_id,
            'current_assignment_id' => $assignment_id,
            'assigned_date' => substr($when, 0, 10),
            'updated_at' => Alb_Settings::now_mysql(),
            'updated_by' => (int) $user_id,
        ));
        Alb_Audit::record(array(
            'action' => 'phone_assign',
            'entity_type' => 'phone',
            'entity_id' => (int) $phone_id,
            'driver_id' => $driver_id,
            'field' => 'driver',
            'old' => $phone['driver_name'],
            'new' => $driver ? $driver['name'] : '',
        ));
        return self::get($phone_id);
    }

    public static function return_phone($phone_id, $notes, $user_id) {
        $phone = self::get($phone_id);
        if (!$phone) {
            return new WP_Error('alb_not_found', Alb_I18n::t('phone.error.not_found'), array('status' => 404));
        }
        $previous = $phone['current_driver_id'] ? (int) $phone['current_driver_id'] : null;
        $now = Alb_Settings::now_mysql();
        if ($previous) {
            global $wpdb;
            $wpdb->insert(self::assignments_table(), array(
                'phone_id' => (int) $phone_id,
                'driver_id' => null,
                'previous_driver_id' => $previous,
                'action' => 'return',
                'assigned_at' => $now,
                'recorded_by' => (int) $user_id,
                'snapshot_name' => $phone['driver_name'],
                'notes' => sanitize_textarea_field($notes),
            ));
        }
        self::write_row((int) $phone_id, array(
            'status' => 'available',
            'current_driver_id' => null,
            'current_assignment_id' => null,
            'assigned_date' => null,
            'updated_at' => $now,
            'updated_by' => (int) $user_id,
        ));
        Alb_Audit::record(array(
            'action' => 'phone_return',
            'entity_type' => 'phone',
            'entity_id' => (int) $phone_id,
            'driver_id' => $previous,
            'field' => 'status',
            'old' => $phone['status'],
            'new' => 'available',
        ));
        return self::get($phone_id);
    }

    public static function change_status($phone_id, $status, $notes, $user_id) {
        $phone = self::get($phone_id);
        if (!$phone) {
            return new WP_Error('alb_not_found', Alb_I18n::t('phone.error.not_found'), array('status' => 404));
        }
        $status = self::normalize_status($status);
        if ($status === 'assigned') {
            return new WP_Error('alb_invalid', Alb_I18n::t('phone.error.assign_required'), array('status' => 400));
        }
        if ($status === $phone['status']) {
            return $phone;
        }
        if ($status === 'available') {
            return self::return_phone($phone_id, $notes, $user_id);
        }
        // damaged / lost / retired: drop the active assignment but keep the history row.
        $now = Alb_Settings::now_mysql();
        $previous = $phone['current_driver_id'] ? (int) $phone['current_driver_id'] : null;
        if ($previous) {
            global $wpdb;
            $wpdb->insert(self::assignments_table(), array(
                'phone_id' => (int) $phone_id,
                'driver_id' => null,
                'previous_driver_id' => $previous,
                'action' => 'return',
                'assigned_at' => $now,
                'recorded_by' => (int) $user_id,
                'snapshot_name' => $phone['driver_name'],
                'notes' => sanitize_textarea_field($notes),
            ));
        }
        self::write_row((int) $phone_id, array(
            'status' => $status,
            'current_driver_id' => null,
            'current_assignment_id' => null,
            'assigned_date' => null,
            'updated_at' => $now,
            'updated_by' => (int) $user_id,
        ));
        Alb_Audit::record(array(
            'action' => 'phone_status',
            'entity_type' => 'phone',
            'entity_id' => (int) $phone_id,
            'driver_id' => $previous,
            'field' => 'status',
            'old' => $phone['status'],
            'new' => $status,
        ));
        return self::get($phone_id);
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id), ARRAY_A);
        return $row ? self::present($row, true) : null;
    }

    public static function find_by($column, $value) {
        global $wpdb;
        $column = in_array($column, array('serial_number', 'imei'), true) ? $column : 'serial_number';
        return $wpdb->get_row($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE ' . $column . ' = %s', sanitize_text_field($value)), ARRAY_A);
    }

    public static function query($args) {
        global $wpdb;
        $phones = self::table();
        $drivers = Alb_Install::table('drivers');
        $where = array('1=1');
        $params = array();
        if (!empty($args['q'])) {
            $q = '%' . $wpdb->esc_like($args['q']) . '%';
            $where[] = "(p.model LIKE %s OR p.serial_number LIKE %s OR p.imei LIKE %s OR CAST(p.id AS CHAR) LIKE %s OR CONCAT(d.first_name, ' ', d.last_name) LIKE %s)";
            array_push($params, $q, $q, $q, $q, $q);
        }
        if (!empty($args['status'])) {
            $where[] = 'p.status = %s';
            $params[] = self::normalize_status($args['status']);
        }
        if (!empty($args['branch'])) {
            $where[] = 'p.branch = %s';
            $params[] = Alb_Branches::normalize($args['branch']);
        }
        if (!empty($args['driver_id'])) {
            $where[] = 'p.current_driver_id = %d';
            $params[] = (int) $args['driver_id'];
        }
        $sortable = array(
            'id' => 'p.id',
            'model' => 'p.model',
            'serial_number' => 'p.serial_number',
            'status' => 'p.status',
            'date_added' => 'p.date_added',
        );
        $sort = $sortable[$args['sort'] ?? 'id'] ?? 'p.id';
        $dir = strtolower($args['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = max(10, min(200, (int) ($args['per_page'] ?? Alb_Settings::get()['items_per_page'])));
        $offset = ($page - 1) * $per_page;
        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM $phones p LEFT JOIN $drivers d ON d.id = p.current_driver_id WHERE $where_sql";
        $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));
        $sql = "SELECT p.*, CONCAT(d.first_name, ' ', d.last_name) AS _driver_name, d.phone AS _driver_phone, d.photo_path AS _driver_photo, d.user_id AS _driver_user_id FROM $phones p LEFT JOIN $drivers d ON d.id = p.current_driver_id WHERE $where_sql ORDER BY $sort $dir LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array(
            'items' => array_map(function ($row) {
                return self::present($row, false);
            }, $rows ?: array()),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'counts' => self::counts(),
        );
    }

    public static function box_items($branch = '') {
        $args = array('status' => 'available', 'per_page' => 200, 'sort' => 'model', 'dir' => 'asc');
        if ($branch !== '') {
            $args['branch'] = $branch;
        }
        $result = self::query($args);
        return $result['items'];
    }

    public static function assigned_to_driver($driver_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE current_driver_id = %d ORDER BY model ASC',
            (int) $driver_id
        ), ARRAY_A);
        return array_map(function ($row) {
            return self::present($row, false);
        }, $rows ?: array());
    }

    public static function counts() {
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM $table GROUP BY status", ARRAY_A);
        $counts = array_fill_keys(self::STATUSES, 0);
        $counts['total'] = 0;
        foreach ($rows ?: array() as $row) {
            $counts[$row['status']] = (int) $row['total'];
            $counts['total'] += (int) $row['total'];
        }
        return $counts;
    }

    public static function history($phone_id) {
        global $wpdb;
        $assignments = self::assignments_table();
        $drivers = Alb_Install::table('drivers');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, CONCAT(d.first_name, ' ', d.last_name) AS driver_name, CONCAT(p.first_name, ' ', p.last_name) AS previous_driver_name
             FROM $assignments a
             LEFT JOIN $drivers d ON d.id = a.driver_id
             LEFT JOIN $drivers p ON p.id = a.previous_driver_id
             WHERE a.phone_id = %d
             ORDER BY a.assigned_at DESC, a.id DESC",
            (int) $phone_id
        ), ARRAY_A);
        return array_map(function ($row) {
            return array(
                'id' => (int) $row['id'],
                'action' => $row['action'],
                'driver_id' => $row['driver_id'] ? (int) $row['driver_id'] : null,
                'driver_name' => $row['snapshot_name'] !== '' ? $row['snapshot_name'] : trim((string) $row['driver_name']),
                'previous_driver_id' => $row['previous_driver_id'] ? (int) $row['previous_driver_id'] : null,
                'previous_driver_name' => trim((string) $row['previous_driver_name']),
                'at' => $row['assigned_at'],
                'at_display' => Alb_Settings::format_datetime($row['assigned_at']),
                'notes' => $row['notes'],
            );
        }, $rows ?: array());
    }

    public static function present($row, $detail = true) {
        $driver_name = '';
        $driver_phone = '';
        $driver_photo_url = '';
        if ($detail && !empty($row['current_driver_id'])) {
            $driver = Alb_Drivers::get((int) $row['current_driver_id']);
            if ($driver) {
                $driver_name = $driver['name'];
                $driver_phone = $driver['phone'];
                $driver_photo_url = $driver['photo_url'];
            }
        } elseif (isset($row['_driver_name'])) {
            $driver_name = trim((string) $row['_driver_name']);
            $driver_phone = (string) ($row['_driver_phone'] ?? '');
            if (!empty($row['_driver_photo']) && !empty($row['current_driver_id'])) {
                $driver_photo_url = Alb_Photos::admin_url('driver', (int) $row['current_driver_id'], (string) $row['_driver_photo']);
            } elseif (!empty($row['_driver_user_id']) && Alb_Users::photo_path((int) $row['_driver_user_id']) !== '') {
                $driver_photo_url = Alb_Photos::admin_url('user', (int) $row['_driver_user_id'], Alb_Users::photo_path((int) $row['_driver_user_id']));
            }
        }
        return array(
            'id' => (int) $row['id'],
            'model' => $row['model'],
            'serial_number' => $row['serial_number'],
            'imei' => $row['imei'],
            'branch' => Alb_Branches::normalize($row['branch'] ?? ''),
            'branch_label' => Alb_Branches::label($row['branch'] ?? ''),
            'status' => $row['status'],
            'status_label' => Alb_I18n::t('phone.status.' . $row['status']),
            'current_driver_id' => $row['current_driver_id'] ? (int) $row['current_driver_id'] : null,
            'driver_name' => $driver_name,
            'driver_phone' => $driver_phone,
            'driver_photo_url' => $driver_photo_url,
            'assigned_date' => $row['assigned_date'],
            'assigned_date_display' => Alb_Settings::format_date($row['assigned_date']),
            'date_added' => $row['date_added'],
            'date_added_display' => Alb_Settings::format_date($row['date_added']),
            'notes' => $row['notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        );
    }

    public static function normalize_imei($value) {
        $value = preg_replace('/[^0-9]/', '', (string) $value);
        return substr((string) $value, 0, 20);
    }

    private static function normalize_date($value) {
        $value = sanitize_text_field($value);
        if ($value === '') {
            return '';
        }
        $dt = date_create($value);
        return $dt ? $dt->format('Y-m-d') : '';
    }

    private static function normalize_datetime($value) {
        $value = sanitize_text_field($value);
        if ($value === '') {
            return Alb_Settings::now_mysql();
        }
        try {
            $dt = date_create($value, Alb_Settings::timezone());
        } catch (Exception $e) {
            $dt = false;
        }
        if (!$dt) {
            return Alb_Settings::now_mysql();
        }
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }

    private static function write_row($id, $fields) {
        global $wpdb;
        $id = (int) $id;
        if ($id < 1 || !is_array($fields) || !$fields) {
            return 0;
        }
        $allowed = array(
            'model',
            'serial_number',
            'imei',
            'branch',
            'status',
            'current_driver_id',
            'current_assignment_id',
            'assigned_date',
            'date_added',
            'notes',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
        );
        $sets = array();
        $values = array();
        foreach ($fields as $column => $value) {
            if (!in_array($column, $allowed, true)) {
                continue;
            }
            if ($value === null) {
                $sets[] = '`' . $column . '` = NULL';
                continue;
            }
            $sets[] = '`' . $column . '` = %s';
            $values[] = $value;
        }
        if (!$sets) {
            return 0;
        }
        $values[] = $id;
        $sql = 'UPDATE ' . self::table() . ' SET ' . implode(', ', $sets) . ' WHERE id = %d';
        return (int) $wpdb->query($wpdb->prepare($sql, $values));
    }
}
