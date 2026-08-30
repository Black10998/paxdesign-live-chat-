<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Scanners {
    const STATUSES = array('active', 'lost', 'defective', 'returned', 'repair', 'inactive');
    const IMMUTABLE = array('brand', 'model', 'serial_number');

    public static function table() {
        return Alb_Install::table('scanners');
    }

    public static function statuses() {
        return self::STATUSES;
    }

    public static function create($data, $user_id) {
        global $wpdb;
        $brand = sanitize_text_field($data['brand'] ?? '');
        $model = sanitize_text_field($data['model'] ?? '');
        $serial = sanitize_text_field($data['serial_number'] ?? '');
        if ($brand === '' || $model === '' || $serial === '') {
            return new WP_Error('alb_invalid', Alb_I18n::t('scanner.error.identity_required'), array('status' => 400));
        }
        if (self::find_by_serial($serial)) {
            return new WP_Error('alb_conflict', Alb_I18n::t('scanner.error.serial_exists'), array('status' => 409));
        }
        $now = Alb_Settings::now_mysql();
        $status = self::normalize_status($data['status'] ?? 'active');
        $inserted = $wpdb->insert(self::table(), array(
            'scanner_code' => 'TMP-' . wp_generate_password(12, false, false),
            'brand' => $brand,
            'model' => $model,
            'serial_number' => $serial,
            'phone_number' => sanitize_text_field($data['phone_number'] ?? ''),
            'branch' => Alb_Branches::normalize($data['branch'] ?? ''),
            'status' => $status,
            'current_driver_id' => null,
            'current_handover_id' => null,
            'handover_date' => null,
            'qr_token' => bin2hex(random_bytes(16)),
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
        $code = sprintf('SCN-%06d', $id);
        $wpdb->update(self::table(), array('scanner_code' => $code), array('id' => $id), array('%s'), array('%d'));
        Alb_Audit::record(array(
            'action' => 'scanner_create',
            'entity_type' => 'scanner',
            'entity_id' => $id,
            'scanner_id' => $id,
            'field' => 'serial_number',
            'new' => $serial,
        ));
        $driver_id = self::person_id_from_request($data, $user_id);
        if (is_wp_error($driver_id)) {
            return $driver_id;
        }
        $handover_date = sanitize_text_field($data['handover_date'] ?? '');
        if ($driver_id > 0) {
            $assigned = self::assign($id, $driver_id, $handover_date, '', $user_id);
            if (is_wp_error($assigned)) {
                return $assigned;
            }
        }
        return self::get($id);
    }

    public static function person_id_from_request($data, $user_id) {
        if (!is_array($data)) {
            $data = array();
        }
        $name = trim(sanitize_text_field($data['employee_name'] ?? ''));
        $phone = sanitize_text_field($data['employee_phone'] ?? '');
        $branch = $data['employee_branch'] ?? '';
        if ($name !== '' || $phone !== '') {
            $person = Alb_Drivers::upsert_from_entry(array(
                'name' => $name,
                'phone' => $phone,
                'branch' => $branch,
            ), $user_id);
            if (is_wp_error($person)) {
                return $person;
            }
            return (int) $person['id'];
        }
        return (int) ($data['driver_id'] ?? 0);
    }

    public static function update($id, $data, $user_id) {
        $current = self::get($id);
        if (!$current) {
            return new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404));
        }
        if (!empty($current['deleted_at'])) {
            return new WP_Error('alb_deleted', Alb_I18n::t('scanner.error.deleted'), array('status' => 400));
        }
        $can_identity = Alb_Capabilities::user_can((int) $user_id, 'scanners.identity');
        foreach (self::IMMUTABLE as $field) {
            if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $current[$field] && !$can_identity) {
                return new WP_Error('alb_immutable', Alb_I18n::t('scanner.error.immutable'), array('status' => 403));
            }
        }
        $fields = array();
        $changes = array();
        if ($can_identity) {
            foreach (self::IMMUTABLE as $field) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }
                $value = sanitize_text_field($data[$field]);
                if ($value === '') {
                    return new WP_Error('alb_invalid', Alb_I18n::t('scanner.error.identity_required'), array('status' => 400));
                }
                if ($field === 'serial_number' && $value !== (string) $current['serial_number'] && self::find_by_serial($value)) {
                    return new WP_Error('alb_conflict', Alb_I18n::t('scanner.error.serial_exists'), array('status' => 409));
                }
                if ($value !== (string) $current[$field]) {
                    $fields[$field] = $value;
                    $changes[$field] = array($current[$field], $value);
                }
            }
        }
        if (array_key_exists('phone_number', $data)) {
            $value = sanitize_text_field($data['phone_number']);
            if ($value !== (string) $current['phone_number']) {
                if (!$can_identity) {
                    return new WP_Error('alb_immutable', Alb_I18n::t('scanner.error.phone_protected'), array('status' => 403));
                }
                $fields['phone_number'] = $value;
                $changes['phone_number'] = array($current['phone_number'], $value);
            }
        }
        if (array_key_exists('branch', $data)) {
            $value = Alb_Branches::normalize($data['branch']);
            if ($value !== (string) ($current['branch'] ?? '')) {
                $fields['branch'] = $value;
                $changes['branch'] = array($current['branch'] ?? '', $value);
            }
        }
        if (array_key_exists('notes', $data) && Alb_Capabilities::user_can((int) $user_id, 'scanners.edit')) {
            $value = sanitize_textarea_field($data['notes']);
            if ($value !== (string) $current['notes']) {
                $fields['notes'] = $value;
                $changes['notes'] = array($current['notes'], $value);
            }
        }
        if (array_key_exists('handover_date', $data) && empty($data['driver_id'])) {
            $value = self::normalize_date($data['handover_date']);
            if ($value !== (string) $current['handover_date']) {
                $fields['handover_date'] = $value ?: null;
                $changes['handover_date'] = array($current['handover_date'], $value);
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
                'action' => 'scanner_update',
                'entity_type' => 'scanner',
                'entity_id' => (int) $id,
                'scanner_id' => (int) $id,
                'driver_id' => $current['current_driver_id'],
                'field' => $field,
                'old' => $pair[0],
                'new' => $pair[1],
            ));
        }
        return self::get($id);
    }

    public static function assign($scanner_id, $driver_id, $handover_at, $notes, $user_id) {
        $scanner = self::get($scanner_id);
        if (!$scanner) {
            return new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404));
        }
        if (!empty($scanner['deleted_at'])) {
            return new WP_Error('alb_deleted', Alb_I18n::t('scanner.error.deleted'), array('status' => 400));
        }
        $driver_id = (int) $driver_id;
        $driver = $driver_id ? Alb_Drivers::get($driver_id) : null;
        if ($driver_id && !$driver) {
            return new WP_Error('alb_not_found', Alb_I18n::t('driver.error.not_found'), array('status' => 404));
        }
        if ($driver && $driver['status'] !== 'active') {
            return new WP_Error('alb_invalid', Alb_I18n::t('driver.error.inactive'), array('status' => 400));
        }
        $when = self::normalize_datetime($handover_at);
        $previous = $scanner['current_driver_id'] ? (int) $scanner['current_driver_id'] : null;
        $action = $driver_id ? ($previous ? 'reassign' : 'assign') : 'return';
        global $wpdb;
        $wpdb->insert(Alb_Install::table('handovers'), array(
            'scanner_id' => (int) $scanner_id,
            'driver_id' => $driver_id ?: null,
            'previous_driver_id' => $previous,
            'action' => $action,
            'handover_at' => $when,
            'recorded_by' => (int) $user_id,
            'snapshot_name' => $driver ? $driver['name'] : '',
            'snapshot_phone' => $driver ? $driver['phone'] : '',
            'snapshot_photo' => $driver ? ($driver['photo_path'] ?? '') : '',
            'notes' => sanitize_textarea_field($notes),
        ));
        $handover_id = (int) $wpdb->insert_id;
        // Device SIM (phone_number) stays on the scanner row. Assignment only stores the employee's personal number in the handover snapshot.
        $wpdb->update(self::table(), array(
            'current_driver_id' => $driver_id ?: null,
            'current_handover_id' => $handover_id,
            'handover_date' => substr($when, 0, 10),
            'updated_at' => Alb_Settings::now_mysql(),
            'updated_by' => (int) $user_id,
        ), array('id' => (int) $scanner_id));
        Alb_Audit::record(array(
            'action' => 'scanner_assign',
            'entity_type' => 'scanner',
            'entity_id' => (int) $scanner_id,
            'scanner_id' => (int) $scanner_id,
            'driver_id' => $driver_id ?: $previous,
            'field' => 'driver',
            'old' => $scanner['driver_name'],
            'new' => $driver ? $driver['name'] : '',
        ));
        return self::get($scanner_id);
    }

    public static function change_status($scanner_id, $status, $notes, $user_id) {
        $scanner = self::get($scanner_id);
        if (!$scanner) {
            return new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404));
        }
        if (!empty($scanner['deleted_at'])) {
            return new WP_Error('alb_deleted', Alb_I18n::t('scanner.error.deleted'), array('status' => 400));
        }
        $status = self::normalize_status($status);
        if ($status === $scanner['status']) {
            return $scanner;
        }
        global $wpdb;
        $now = Alb_Settings::now_mysql();
        $fields = array(
            'status' => $status,
            'updated_at' => $now,
            'updated_by' => (int) $user_id,
        );
        if ($status === 'returned') {
            $fields['current_driver_id'] = null;
        }
        $wpdb->update(self::table(), $fields, array('id' => (int) $scanner_id));
        if ($status === 'returned' && $scanner['current_driver_id']) {
            $wpdb->insert(Alb_Install::table('handovers'), array(
                'scanner_id' => (int) $scanner_id,
                'driver_id' => null,
                'previous_driver_id' => (int) $scanner['current_driver_id'],
                'action' => 'return',
                'handover_at' => $now,
                'recorded_by' => (int) $user_id,
                'notes' => sanitize_textarea_field($notes),
            ));
        }
        $wpdb->insert(Alb_Install::table('status_events'), array(
            'scanner_id' => (int) $scanner_id,
            'old_status' => $scanner['status'],
            'new_status' => $status,
            'changed_at' => $now,
            'changed_by' => (int) $user_id,
            'notes' => sanitize_textarea_field($notes),
        ));
        Alb_Audit::record(array(
            'action' => 'scanner_status',
            'entity_type' => 'scanner',
            'entity_id' => (int) $scanner_id,
            'scanner_id' => (int) $scanner_id,
            'driver_id' => $scanner['current_driver_id'],
            'field' => 'status',
            'old' => $scanner['status'],
            'new' => $status,
        ));
        return self::get($scanner_id);
    }

    public static function take_over($scanner_id, $driver_id, $notes, $user_id) {
        $scanner = self::get($scanner_id);
        if (!$scanner) {
            return new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404));
        }
        if (!empty($scanner['deleted_at'])) {
            return new WP_Error('alb_deleted', Alb_I18n::t('scanner.error.deleted'), array('status' => 400));
        }
        $driver_id = (int) $driver_id;
        if ($driver_id <= 0) {
            return new WP_Error('alb_invalid', Alb_I18n::t('driver.error.not_found'), array('status' => 400));
        }
        if ($scanner['status'] !== 'active') {
            $activated = self::change_status($scanner_id, 'active', $notes, $user_id);
            if (is_wp_error($activated)) {
                return $activated;
            }
        }
        $assigned = self::assign($scanner_id, $driver_id, '', $notes, $user_id);
        if (is_wp_error($assigned)) {
            return $assigned;
        }
        Alb_Scan::record($assigned, 'take_over', $notes);
        return $assigned;
    }

    public static function employee_accept($scanner_id, $driver_id) {
        $scanner = self::get($scanner_id);
        if (!$scanner) {
            return new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404));
        }
        if (!empty($scanner['deleted_at'])) {
            return new WP_Error('alb_deleted', Alb_I18n::t('scanner.error.deleted'), array('status' => 400));
        }
        if ($scanner['status'] !== 'active') {
            $activated = self::change_status($scanner_id, 'active', 'employee_accept', 0);
            if (is_wp_error($activated)) {
                return $activated;
            }
        }
        return self::assign($scanner_id, (int) $driver_id, '', 'employee_accept', 0);
    }

    public static function return_device($scanner_id, $notes, $user_id) {
        $result = self::change_status($scanner_id, 'returned', $notes, $user_id);
        if (!is_wp_error($result)) {
            Alb_Scan::record($result, 'mark_returned', $notes);
        }
        return $result;
    }

    public static function soft_delete($scanner_id, $notes, $user_id) {
        $scanner = self::get($scanner_id);
        if (!$scanner) {
            return new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404));
        }
        if (!empty($scanner['deleted_at'])) {
            return $scanner;
        }
        $now = Alb_Settings::now_mysql();
        $stored_serial = $scanner['serial_number'];
        if (strpos($stored_serial, '#DEL') === false) {
            $stored_serial .= '#DEL' . (int) $scanner_id;
        }
        global $wpdb;
        $wpdb->update(self::table(), array(
            'serial_number' => $stored_serial,
            'deleted_at' => $now,
            'deleted_by' => (int) $user_id,
            'updated_at' => $now,
            'updated_by' => (int) $user_id,
        ), array('id' => (int) $scanner_id));
        Alb_Audit::record(array(
            'action' => 'scanner_delete',
            'entity_type' => 'scanner',
            'entity_id' => (int) $scanner_id,
            'scanner_id' => (int) $scanner_id,
            'driver_id' => $scanner['current_driver_id'],
            'field' => 'deleted_at',
            'old' => '',
            'new' => $now,
        ));
        if ($notes !== '') {
            Alb_Audit::record(array(
                'action' => 'scanner_delete',
                'entity_type' => 'scanner',
                'entity_id' => (int) $scanner_id,
                'scanner_id' => (int) $scanner_id,
                'field' => 'notes',
                'new' => sanitize_textarea_field($notes),
            ));
        }
        return self::get($scanner_id);
    }

    public static function restore($scanner_id, $notes, $user_id) {
        $scanner = self::get($scanner_id);
        if (!$scanner) {
            return new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404));
        }
        $now = Alb_Settings::now_mysql();
        $old_status = $scanner['status'];
        global $wpdb;
        if (!empty($scanner['deleted_at'])) {
            $row = self::raw($scanner_id);
            $serial = Alb_Scan::display_serial($row ? $row['serial_number'] : $scanner['serial_number']);
            $existing = self::find_by_serial($serial);
            if ($existing && (int) $existing['id'] !== (int) $scanner_id) {
                return new WP_Error('alb_conflict', Alb_I18n::t('scanner.error.serial_exists'), array('status' => 409));
            }
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . self::table() . ' SET serial_number = %s, deleted_at = NULL, deleted_by = 0, status = %s, updated_at = %s, updated_by = %d WHERE id = %d',
                $serial,
                'active',
                $now,
                (int) $user_id,
                (int) $scanner_id
            ));
        } else {
            $wpdb->update(self::table(), array(
                'status' => 'active',
                'updated_at' => $now,
                'updated_by' => (int) $user_id,
            ), array('id' => (int) $scanner_id));
        }
        if ($old_status !== 'active') {
            $wpdb->insert(Alb_Install::table('status_events'), array(
                'scanner_id' => (int) $scanner_id,
                'old_status' => $scanner['status'],
                'new_status' => 'active',
                'changed_at' => $now,
                'changed_by' => (int) $user_id,
                'notes' => sanitize_textarea_field($notes),
            ));
        }
        Alb_Audit::record(array(
            'action' => 'scanner_restore',
            'entity_type' => 'scanner',
            'entity_id' => (int) $scanner_id,
            'scanner_id' => (int) $scanner_id,
            'driver_id' => $scanner['current_driver_id'],
            'field' => 'status',
            'old' => $scanner['status'],
            'new' => 'active',
        ));
        $restored = self::get($scanner_id);
        Alb_Scan::record($restored, 'restore', $notes);
        return $restored;
    }

    public static function raw($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id), ARRAY_A);
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id), ARRAY_A);
        return $row ? self::present($row, true) : null;
    }

    public static function get_by_qr($token) {
        global $wpdb;
        $token = sanitize_text_field($token);
        if ($token === '') {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE qr_token = %s', $token), ARRAY_A);
        return $row ? self::present($row, true) : null;
    }

    public static function public_view($token) {
        $scanner = self::get_by_qr($token);
        if (!$scanner || !empty($scanner['deleted_at'])) {
            return null;
        }
        return $scanner;
    }

    public static function public_photo_url($token, $version = '') {
        $token = preg_replace('/[^A-Za-z0-9]/', '', (string) $token);
        $url = $token !== '' ? home_url('/s/' . $token . '/photo') : '';
        $version = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $version);
        return ($url !== '' && $version !== '') ? add_query_arg('v', $version, $url) : $url;
    }

    public static function find_by_serial($serial) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE serial_number = %s', sanitize_text_field($serial)), ARRAY_A);
    }

    public static function query($args) {
        global $wpdb;
        $scanners = self::table();
        $drivers = Alb_Install::table('drivers');
        $where = array('1=1');
        $params = array();
        $removed = !empty($args['removed']) && $args['removed'] !== '0' && $args['removed'] !== 'false';
        if ($removed) {
            $where[] = 's.deleted_at IS NOT NULL';
        } else {
            $where[] = 's.deleted_at IS NULL';
        }
        if (!empty($args['q'])) {
            $q = '%' . $wpdb->esc_like($args['q']) . '%';
            $where[] = "(s.scanner_code LIKE %s OR s.serial_number LIKE %s OR s.phone_number LIKE %s OR s.brand LIKE %s OR s.model LIKE %s OR CAST(s.id AS CHAR) LIKE %s OR CONCAT(d.first_name, ' ', d.last_name) LIKE %s)";
            array_push($params, $q, $q, $q, $q, $q, $q, $q);
        }
        if (!empty($args['status'])) {
            $where[] = 's.status = %s';
            $params[] = self::normalize_status($args['status']);
        }
        if (!empty($args['assigned']) && $args['assigned'] !== '0' && $args['assigned'] !== 'false') {
            $where[] = "s.current_driver_id IS NOT NULL AND s.status != 'returned'";
        }
        if (!empty($args['unassigned']) && $args['unassigned'] !== '0' && $args['unassigned'] !== 'false') {
            $where[] = 's.current_driver_id IS NULL';
        }
        if (!empty($args['driver_id'])) {
            $where[] = 's.current_driver_id = %d';
            $params[] = (int) $args['driver_id'];
        }
        if (!empty($args['brand'])) {
            $where[] = 's.brand LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['brand']) . '%';
        }
        if (!empty($args['model'])) {
            $where[] = 's.model LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['model']) . '%';
        }
        if (!empty($args['branch'])) {
            $where[] = 's.branch = %s';
            $params[] = Alb_Branches::normalize($args['branch']);
        }
        $sortable = array(
            'id' => 's.id',
            'scanner_code' => 's.scanner_code',
            'brand' => 's.brand',
            'model' => 's.model',
            'serial_number' => 's.serial_number',
            'phone_number' => 's.phone_number',
            'branch' => 's.branch',
            'status' => 's.status',
            'handover_date' => 's.handover_date',
            'driver' => 'd.last_name',
        );
        $sort = $sortable[$args['sort'] ?? 'id'] ?? 's.id';
        $dir = strtolower($args['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = max(10, min(200, (int) ($args['per_page'] ?? Alb_Settings::get()['items_per_page'])));
        $offset = ($page - 1) * $per_page;
        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM $scanners s LEFT JOIN $drivers d ON d.id = s.current_driver_id WHERE $where_sql";
        $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));
        $sql = "SELECT s.*, CONCAT(d.first_name, ' ', d.last_name) AS _driver_name, d.phone AS _driver_phone, d.photo_path AS _driver_photo, d.phone_verified AS _driver_phone_verified, d.branch AS _driver_branch, d.user_id AS _driver_user_id FROM $scanners s LEFT JOIN $drivers d ON d.id = s.current_driver_id WHERE $where_sql ORDER BY $sort $dir LIMIT %d OFFSET %d";
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
        );
    }

    public static function counts() {
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM $table WHERE deleted_at IS NULL GROUP BY status", ARRAY_A);
        $counts = array_fill_keys(self::STATUSES, 0);
        $counts['total'] = 0;
        $counts['assigned'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted_at IS NULL AND current_driver_id IS NOT NULL AND status != 'returned'");
        foreach ($rows ?: array() as $row) {
            $counts[$row['status']] = (int) $row['total'];
            $counts['total'] += (int) $row['total'];
        }
        return $counts;
    }

    public static function history($scanner_id) {
        global $wpdb;
        $handovers = Alb_Install::table('handovers');
        $status_events = Alb_Install::table('status_events');
        $drivers = Alb_Install::table('drivers');
        $assign = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, CONCAT(d.first_name, ' ', d.last_name) AS driver_name, CONCAT(p.first_name, ' ', p.last_name) AS previous_driver_name
             FROM $handovers h
             LEFT JOIN $drivers d ON d.id = h.driver_id
             LEFT JOIN $drivers p ON p.id = h.previous_driver_id
             WHERE h.scanner_id = %d
             ORDER BY h.handover_at DESC, h.id DESC",
            (int) $scanner_id
        ), ARRAY_A);
        $status = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $status_events WHERE scanner_id = %d ORDER BY changed_at DESC, id DESC",
            (int) $scanner_id
        ), ARRAY_A);
        $items = array();
        foreach ($assign ?: array() as $row) {
            $items[] = array(
                'type' => 'handover',
                'id' => (int) $row['id'],
                'action' => $row['action'],
                'driver_id' => $row['driver_id'] ? (int) $row['driver_id'] : null,
                'driver_name' => $row['snapshot_name'] !== '' ? $row['snapshot_name'] : trim((string) $row['driver_name']),
                'driver_phone' => $row['snapshot_phone'] ?? '',
                'driver_photo_url' => !empty($row['snapshot_photo']) ? Alb_Photos::admin_url('handover', (int) $row['id']) : '',
                'previous_driver_id' => $row['previous_driver_id'] ? (int) $row['previous_driver_id'] : null,
                'previous_driver_name' => trim((string) $row['previous_driver_name']),
                'at' => $row['handover_at'],
                'at_display' => Alb_Settings::format_datetime($row['handover_at']),
                'notes' => $row['notes'],
            );
        }
        foreach ($status ?: array() as $row) {
            $items[] = array(
                'type' => 'status',
                'id' => (int) $row['id'],
                'action' => 'status',
                'old_status' => $row['old_status'],
                'new_status' => $row['new_status'],
                'at' => $row['changed_at'],
                'at_display' => Alb_Settings::format_datetime($row['changed_at']),
                'notes' => $row['notes'],
            );
        }
        foreach (Alb_Scan::history($scanner_id, 80) as $row) {
            $items[] = $row;
        }
        usort($items, function ($a, $b) {
            return strcmp($b['at'], $a['at']);
        });
        return $items;
    }

    public static function recent_handovers($limit = 8) {
        global $wpdb;
        $handovers = Alb_Install::table('handovers');
        $scanners = self::table();
        $drivers = Alb_Install::table('drivers');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, s.serial_number, s.scanner_code, CONCAT(d.first_name, ' ', d.last_name) AS driver_name
             FROM $handovers h
             INNER JOIN $scanners s ON s.id = h.scanner_id
             LEFT JOIN $drivers d ON d.id = h.driver_id
             ORDER BY h.handover_at DESC, h.id DESC
             LIMIT %d",
            (int) $limit
        ), ARRAY_A);
        return array_map(function ($row) {
            return array(
                'id' => (int) $row['id'],
                'scanner_id' => (int) $row['scanner_id'],
                'scanner_code' => $row['scanner_code'],
                'serial_number' => $row['serial_number'],
                'driver_name' => trim((string) $row['driver_name']),
                'action' => $row['action'],
                'at' => $row['handover_at'],
                'at_display' => Alb_Settings::format_datetime($row['handover_at']),
            );
        }, $rows ?: array());
    }

    public static function last_assigned_driver($scanner_id) {
        global $wpdb;
        $handovers = Alb_Install::table('handovers');
        $drivers = Alb_Install::table('drivers');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT h.handover_at, h.driver_id, CONCAT(d.first_name, ' ', d.last_name) AS driver_name
             FROM $handovers h
             LEFT JOIN $drivers d ON d.id = h.driver_id
             WHERE h.scanner_id = %d AND h.driver_id IS NOT NULL
             ORDER BY h.handover_at DESC, h.id DESC
             LIMIT 1",
            (int) $scanner_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        return array(
            'driver_id' => (int) $row['driver_id'],
            'driver_name' => trim((string) $row['driver_name']),
            'at' => $row['handover_at'],
            'at_display' => Alb_Settings::format_datetime($row['handover_at']),
        );
    }

    public static function present($row, $detail = true) {
        $driver = null;
        $driver_name = '';
        $driver_phone = '';
        $driver_photo_url = '';
        $driver_photo_path = '';
        $driver_verified = false;
        $driver_branch = '';
        $driver_branch_label = Alb_Branches::label('');
        if ($detail && !empty($row['current_driver_id'])) {
            $driver = Alb_Drivers::get((int) $row['current_driver_id']);
        }
        if ($driver) {
            $driver_name = $driver['name'];
            $driver_phone = $driver['phone'];
            $driver_photo_url = $driver['photo_url'];
            $driver_photo_path = (string) ($driver['photo_path'] ?? '');
            $driver_verified = !empty($driver['phone_verified']);
            $driver_branch = $driver['branch'];
            $driver_branch_label = $driver['branch_label'];
        } elseif (isset($row['_driver_name'])) {
            $driver_name = trim((string) $row['_driver_name']);
            $driver_phone = (string) ($row['_driver_phone'] ?? '');
            $driver_verified = !empty($row['_driver_phone_verified']);
            $driver_branch = Alb_Branches::normalize($row['_driver_branch'] ?? '');
            $driver_branch_label = Alb_Branches::label($row['_driver_branch'] ?? '');
            if (!empty($row['_driver_photo']) && !empty($row['current_driver_id'])) {
                $driver_photo_path = (string) $row['_driver_photo'];
                $driver_photo_url = Alb_Photos::admin_url('driver', (int) $row['current_driver_id'], $driver_photo_path);
            } elseif (!empty($row['_driver_user_id']) && Alb_Users::photo_path((int) $row['_driver_user_id']) !== '') {
                $driver_photo_path = Alb_Users::photo_path((int) $row['_driver_user_id']);
                $driver_photo_url = Alb_Photos::admin_url('user', (int) $row['_driver_user_id'], $driver_photo_path);
            }
        }
        $handover_at = '';
        if ($detail && !empty($row['current_handover_id'])) {
            global $wpdb;
            $handover_at = (string) $wpdb->get_var($wpdb->prepare(
                'SELECT handover_at FROM ' . Alb_Install::table('handovers') . ' WHERE id = %d',
                (int) $row['current_handover_id']
            ));
        }
        $last = null;
        if ($detail && $row['status'] === 'lost') {
            $last = self::last_assigned_driver((int) $row['id']);
        }
        $serial = Alb_Scan::display_serial($row['serial_number']);
        return array(
            'id' => (int) $row['id'],
            'scanner_code' => $row['scanner_code'],
            'brand' => $row['brand'],
            'model' => $row['model'],
            'serial_number' => $serial,
            'phone_number' => $row['phone_number'],
            'branch' => Alb_Branches::normalize($row['branch'] ?? ''),
            'branch_label' => Alb_Branches::label($row['branch'] ?? ''),
            'status' => $row['status'],
            'current_driver_id' => $row['current_driver_id'] ? (int) $row['current_driver_id'] : null,
            'driver_name' => $driver_name,
            'driver_phone' => $driver_phone,
            'driver_phone_verified' => $driver_verified,
            'driver_photo_url' => $driver_photo_url,
            'driver_photo_path' => $driver_photo_path,
            'driver_branch' => $driver_branch,
            'driver_branch_label' => $driver_branch_label,
            'handover_date' => $row['handover_date'],
            'handover_date_display' => Alb_Settings::format_date($row['handover_date']),
            'handover_at' => $handover_at,
            'handover_at_display' => $handover_at !== '' ? Alb_Settings::format_datetime($handover_at) : Alb_Settings::format_date($row['handover_date']),
            'qr_token' => $row['qr_token'],
            'qr_url' => home_url('/s/' . $row['qr_token']),
            'notes' => $row['notes'],
            'last_assigned' => $last,
            'deleted_at' => !empty($row['deleted_at']) ? $row['deleted_at'] : null,
            'deleted_at_display' => !empty($row['deleted_at']) ? Alb_Settings::format_datetime($row['deleted_at']) : '',
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        );
    }

    public static function normalize_status($status) {
        $status = sanitize_key($status);
        return in_array($status, self::STATUSES, true) ? $status : 'active';
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
}
