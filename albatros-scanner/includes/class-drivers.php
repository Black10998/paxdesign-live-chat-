<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Drivers {
    public static function table() {
        return Alb_Install::table('drivers');
    }

    public static function create($data, $user_id) {
        list($first, $last) = self::person_names($data);
        if ($first === '') {
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
            'branch' => Alb_Branches::normalize($data['branch'] ?? ''),
            'status' => !empty($data['status']) && $data['status'] === 'inactive' ? 'inactive' : 'active',
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'user_id' => !empty($data['user_id']) ? (int) $data['user_id'] : null,
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
        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data) || array_key_exists('name', $data) || array_key_exists('full_name', $data)) {
            list($first, $last) = self::person_names($data);
            if ($first === '') {
                return new WP_Error('alb_invalid', Alb_I18n::t('driver.error.name_required'), array('status' => 400));
            }
            $data['first_name'] = $first;
            $data['last_name'] = $last;
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
        if (array_key_exists('branch', $data)) {
            $value = Alb_Branches::normalize($data['branch']);
            if ($value !== (string) ($current['branch'] ?? '')) {
                $fields['branch'] = $value;
                $changes['branch'] = array($current['branch'] ?? '', $value);
            }
        }
        if (array_key_exists('user_id', $data)) {
            $value = (int) $data['user_id'] ?: null;
            if ((int) ($current['user_id'] ?? 0) !== (int) $value) {
                $fields['user_id'] = $value;
                $changes['user_id'] = array($current['user_id'] ?? '', $value);
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
        self::write_row((int) $id, $fields);
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

    public static function digits($phone) {
        return preg_replace('/\D+/', '', (string) $phone);
    }

    public static function split_name($name) {
        $name = trim(sanitize_text_field($name));
        if ($name === '') {
            return array('', '');
        }
        $parts = preg_split('/\s+/', $name, 2);
        $first = $parts[0];
        $last = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : $parts[0];
        return array($first, $last);
    }

    public static function person_names($data) {
        $first = sanitize_text_field($data['first_name'] ?? '');
        $last = sanitize_text_field($data['last_name'] ?? '');
        if ($first === '' && $last === '') {
            $combined = trim(sanitize_text_field($data['name'] ?? $data['full_name'] ?? ''));
            if ($combined !== '') {
                return self::split_name($combined);
            }
            return array('', '');
        }
        if ($last === '') {
            return self::split_name($first);
        }
        if ($first === '') {
            return self::split_name($last);
        }
        return array($first, $last);
    }

    public static function find_by_phone($phone) {
        global $wpdb;
        $raw = sanitize_text_field($phone);
        if ($raw === '') {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE phone = %s ORDER BY id ASC LIMIT 1', $raw), ARRAY_A);
        if ($row) {
            return self::present($row);
        }
        $digits = self::digits($raw);
        if ($digits === '') {
            return null;
        }
        $rows = $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY id ASC', ARRAY_A);
        foreach ($rows ?: array() as $candidate) {
            if (self::digits($candidate['phone'] ?? '') === $digits) {
                return self::present($candidate);
            }
        }
        return null;
    }

    public static function find_by_name($name) {
        list($first, $last) = self::split_name($name);
        if ($first === '') {
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE first_name = %s AND last_name = %s ORDER BY id ASC LIMIT 1',
            $first,
            $last
        ), ARRAY_A);
        return $row ? self::present($row) : null;
    }

    public static function upsert_from_entry($data, $user_id) {
        $name = trim(sanitize_text_field($data['name'] ?? $data['full_name'] ?? ''));
        if ($name === '') {
            $name = trim(sanitize_text_field(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')));
        }
        $phone = sanitize_text_field($data['phone'] ?? '');
        $branch = Alb_Branches::normalize($data['branch'] ?? '');
        $existing = $phone !== '' ? self::find_by_phone($phone) : null;
        if (!$existing && $name !== '') {
            $existing = self::find_by_name($name);
        }
        list($first, $last) = self::split_name($name);
        if ($existing) {
            $update = array();
            if ($first !== '') {
                $update['first_name'] = $first;
                $update['last_name'] = $last;
            }
            if ($phone !== '') {
                $update['phone'] = $phone;
            }
            if (array_key_exists('branch', $data)) {
                $update['branch'] = $branch;
            }
            return $update ? self::update((int) $existing['id'], $update, $user_id) : $existing;
        }
        if ($first === '') {
            return new WP_Error('alb_invalid', Alb_I18n::t('driver.error.name_required'), array('status' => 400));
        }
        return self::create(array(
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phone,
            'branch' => $branch,
        ), $user_id);
    }

    public static function find_by_user($user_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id ASC LIMIT 1', (int) $user_id), ARRAY_A);
        return $row ? self::present($row) : null;
    }

    public static function id_for_user($user_id) {
        $found = self::find_by_user($user_id);
        return $found ? (int) $found['id'] : null;
    }

    public static function upsert_for_user($user_id) {
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return new WP_Error('alb_not_found', Alb_I18n::t('users.error.not_found'), array('status' => 404));
        }
        $name = trim($user->display_name !== '' ? $user->display_name : $user->user_login);
        $parts = preg_split('/\s+/', $name, 2);
        $first = $parts[0];
        $last = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : $parts[0];
        $phone = (string) get_user_meta($user->ID, 'alb_phone', true);
        $photo = Alb_Users::photo_path($user->ID);
        $branch = Alb_Branches::normalize(get_user_meta($user->ID, 'alb_branch', true));
        $existing = self::find_by_user($user->ID);
        $now = Alb_Settings::now_mysql();
        $fields = array(
            'first_name' => $first,
            'last_name' => $last,
            'email' => $user->user_email,
            'phone' => $phone,
            'user_id' => (int) $user->ID,
            'updated_at' => $now,
            'updated_by' => get_current_user_id(),
        );
        if ($photo !== '') {
            $fields['photo_path'] = $photo;
        }
        if ($branch !== '') {
            $fields['branch'] = $branch;
        }
        global $wpdb;
        if ($existing) {
            $wpdb->update(self::table(), $fields, array('id' => (int) $existing['id']));
            return self::get((int) $existing['id']);
        }
        $fields['status'] = 'active';
        $fields['employee_code'] = '';
        $fields['notes'] = '';
        $fields['created_at'] = $now;
        $fields['created_by'] = get_current_user_id();
        $wpdb->insert(self::table(), $fields);
        return self::get((int) $wpdb->insert_id);
    }

    public static function sync_user_profile($user_id) {
        $existing = self::find_by_user($user_id);
        if (!$existing) {
            return null;
        }
        return self::upsert_for_user($user_id);
    }

    public static function set_photo($id, $file, $actor_id) {
        $current = self::get($id);
        if (!$current) {
            return new WP_Error('alb_not_found', Alb_I18n::t('driver.error.not_found'), array('status' => 404));
        }
        $stored = Alb_Photos::store_upload($file, 'driver');
        if (is_wp_error($stored)) {
            return $stored;
        }
        $previous = (string) ($current['photo_path'] ?? '');
        global $wpdb;
        $wpdb->update(self::table(), array(
            'photo_path' => $stored,
            'updated_at' => Alb_Settings::now_mysql(),
            'updated_by' => (int) $actor_id,
        ), array('id' => (int) $id));
        if (!empty($current['user_id'])) {
            update_user_meta((int) $current['user_id'], 'alb_photo_path', $stored);
        }
        if ($previous !== '' && $previous !== $stored) {
            Alb_Photos::delete_file($previous);
        }
        Alb_Audit::record(array(
            'action' => 'driver_photo',
            'entity_type' => 'driver',
            'entity_id' => (int) $id,
            'driver_id' => (int) $id,
            'field' => 'photo',
            'new' => 'uploaded',
        ));
        return self::get($id);
    }

    public static function upsert_verified($data) {
        $phone = $data['phone'];
        $existing = self::find_by_phone($phone);
        $now = Alb_Settings::now_mysql();
        $fields = array(
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $phone,
            'photo_path' => $data['photo_path'],
            'phone_verified' => 1,
            'phone_verified_at' => $now,
            'status' => 'active',
            'updated_at' => $now,
            'updated_by' => 0,
        );
        global $wpdb;
        if ($existing) {
            $wpdb->update(self::table(), $fields, array('id' => (int) $existing['id']));
            Alb_Audit::record(array(
                'action' => 'driver_verify',
                'entity_type' => 'driver',
                'entity_id' => (int) $existing['id'],
                'driver_id' => (int) $existing['id'],
                'field' => 'phone',
                'new' => Alb_Otp::mask_phone($phone),
                'actor_id' => 0,
                'actor_name' => trim($data['first_name'] . ' ' . $data['last_name']),
            ));
            return self::get((int) $existing['id']);
        }
        $fields['email'] = '';
        $fields['employee_code'] = '';
        $fields['notes'] = '';
        $fields['created_at'] = $now;
        $fields['created_by'] = 0;
        $wpdb->insert(self::table(), $fields);
        $id = (int) $wpdb->insert_id;
        Alb_Audit::record(array(
            'action' => 'driver_create',
            'entity_type' => 'driver',
            'entity_id' => $id,
            'driver_id' => $id,
            'field' => 'phone',
            'new' => Alb_Otp::mask_phone($phone),
            'actor_id' => 0,
            'actor_name' => trim($data['first_name'] . ' ' . $data['last_name']),
        ));
        return self::get($id);
    }

    public static function handover_snapshot($handover_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT snapshot_name, snapshot_phone, snapshot_photo FROM ' . Alb_Install::table('handovers') . ' WHERE id = %d', (int) $handover_id), ARRAY_A);
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
        if (!empty($args['branch'])) {
            $where[] = 'branch = %s';
            $params[] = Alb_Branches::normalize($args['branch']);
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
        $rows = $wpdb->get_results("SELECT id, first_name, last_name, phone, branch, status FROM " . self::table() . " ORDER BY last_name ASC, first_name ASC", ARRAY_A);
        return array_map(function ($row) {
            return array(
                'id' => (int) $row['id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'phone' => $row['phone'],
                'branch' => Alb_Branches::normalize($row['branch'] ?? ''),
                'branch_label' => Alb_Branches::label($row['branch'] ?? ''),
                'status' => $row['status'],
            );
        }, $rows ?: array());
    }

    public static function present($row) {
        $photo = $row['photo_path'] ?? '';
        $user_id = !empty($row['user_id']) ? (int) $row['user_id'] : 0;
        if ($photo === '' && $user_id) {
            $photo = Alb_Users::photo_path($user_id);
        }
        $photo_url = '';
        if ($photo !== '') {
            $photo_url = Alb_Photos::admin_url('driver', (int) $row['id'], $photo);
        } elseif ($user_id && Alb_Users::photo_path($user_id) !== '') {
            $photo_url = Alb_Photos::admin_url('user', $user_id, Alb_Users::photo_path($user_id));
        }
        return array(
            'id' => (int) $row['id'],
            'user_id' => $user_id ?: null,
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'phone' => $row['phone'],
            'phone_verified' => !empty($row['phone_verified']),
            'phone_verified_at' => $row['phone_verified_at'] ?? '',
            'phone_verified_at_display' => !empty($row['phone_verified_at']) ? Alb_Settings::format_datetime($row['phone_verified_at']) : '',
            'photo_path' => $photo,
            'photo_url' => $photo_url,
            'email' => $row['email'],
            'employee_code' => $row['employee_code'],
            'branch' => Alb_Branches::normalize($row['branch'] ?? ''),
            'branch_label' => Alb_Branches::label($row['branch'] ?? ''),
            'status' => $row['status'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        );
    }

    /**
     * Persist driver columns, including real SQL NULL for optional FKs.
     */
    private static function write_row($id, $fields) {
        global $wpdb;
        $id = (int) $id;
        if ($id < 1 || !is_array($fields) || !$fields) {
            return 0;
        }
        $allowed = array(
            'user_id',
            'first_name',
            'last_name',
            'phone',
            'phone_verified',
            'phone_verified_at',
            'photo_path',
            'email',
            'employee_code',
            'branch',
            'status',
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
