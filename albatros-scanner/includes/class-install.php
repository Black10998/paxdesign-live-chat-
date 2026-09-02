<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Install {
    public static function activate() {
        self::create_tables();
        if (!get_option(Alb_Settings::OPTION)) {
            update_option(Alb_Settings::OPTION, Alb_Settings::defaults(), false);
        }
        if (!get_option(Alb_Capabilities::PERMS_OPTION)) {
            update_option(Alb_Capabilities::PERMS_OPTION, Alb_Capabilities::defaults(), false);
        }
        foreach (get_users(array('fields' => 'ID')) as $user_id) {
            Alb_Capabilities::bootstrap_user((int) $user_id);
        }
        update_option('alb_scanner_db_version', ALB_SCANNER_DB_VERSION, false);
        if (!wp_next_scheduled('alb_scanner_daily_maintenance')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'alb_scanner_daily_maintenance');
        }
        flush_rewrite_rules();
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled('alb_scanner_daily_maintenance');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'alb_scanner_daily_maintenance');
        }
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        $installed = get_option('alb_scanner_db_version');
        if ($installed !== ALB_SCANNER_DB_VERSION || !self::schema_ready()) {
            self::create_tables();
            update_option('alb_scanner_db_version', ALB_SCANNER_DB_VERSION, false);
        }
        Alb_Capabilities::sync_stored_map();
        update_option('users_can_register', 0, false);
        Alb_Photos::dir();
    }

    private static function schema_ready() {
        global $wpdb;
        $scans = self::table('scan_events');
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $scans));
        if ($found !== $scans) {
            return false;
        }
        $otp = self::table('otp_challenges');
        $otp_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $otp));
        $photo = $wpdb->get_var('SHOW COLUMNS FROM ' . self::table('drivers') . " LIKE 'photo_path'");
        $user_id = $wpdb->get_var('SHOW COLUMNS FROM ' . self::table('drivers') . " LIKE 'user_id'");
        $deleted = $wpdb->get_var('SHOW COLUMNS FROM ' . self::table('scanners') . " LIKE 'deleted_at'");
        $sbranch = $wpdb->get_var('SHOW COLUMNS FROM ' . self::table('scanners') . " LIKE 'branch'");
        $dbranch = $wpdb->get_var('SHOW COLUMNS FROM ' . self::table('drivers') . " LIKE 'branch'");
        $refused = $wpdb->get_var('SHOW COLUMNS FROM ' . self::table('drivers') . " LIKE 'phone_data_refused'");
        $photo_refused = $wpdb->get_var('SHOW COLUMNS FROM ' . self::table('drivers') . " LIKE 'photo_refused'");
        $phones = self::table('phones');
        $phones_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $phones));
        $assignments = self::table('phone_assignments');
        $assignments_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $assignments));
        return $otp_found === $otp && $photo === 'photo_path' && $user_id === 'user_id' && $deleted === 'deleted_at'
            && $sbranch === 'branch' && $dbranch === 'branch'
            && $refused === 'phone_data_refused' && $photo_refused === 'photo_refused'
            && $phones_found === $phones && $assignments_found === $assignments;
    }

    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'alb_' . $name;
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $scanners = self::table('scanners');
        $drivers = self::table('drivers');
        $handovers = self::table('handovers');
        $status_events = self::table('status_events');
        $audit = self::table('audit_logs');
        $scans = self::table('scan_events');
        $otp = self::table('otp_challenges');
        $phones = self::table('phones');
        $phone_assignments = self::table('phone_assignments');

        dbDelta("CREATE TABLE $scanners (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scanner_code varchar(32) NOT NULL,
            brand varchar(120) NOT NULL,
            model varchar(120) NOT NULL,
            serial_number varchar(120) NOT NULL,
            phone_number varchar(60) NOT NULL DEFAULT '',
            branch varchar(20) NOT NULL DEFAULT '',
            status varchar(32) NOT NULL DEFAULT 'active',
            current_driver_id bigint(20) unsigned DEFAULT NULL,
            current_handover_id bigint(20) unsigned DEFAULT NULL,
            handover_date date DEFAULT NULL,
            qr_token varchar(64) NOT NULL,
            notes text NULL,
            deleted_at datetime DEFAULT NULL,
            deleted_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY scanner_code (scanner_code),
            UNIQUE KEY serial_number (serial_number),
            UNIQUE KEY qr_token (qr_token),
            KEY status (status),
            KEY current_driver_id (current_driver_id),
            KEY phone_number (phone_number),
            KEY branch (branch),
            KEY deleted_at (deleted_at)
        ) $charset;");

        dbDelta("CREATE TABLE $drivers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(120) NOT NULL,
            last_name varchar(120) NOT NULL,
            phone varchar(60) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            employee_code varchar(60) NOT NULL DEFAULT '',
            branch varchar(20) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            photo_path varchar(190) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned DEFAULT NULL,
            phone_verified tinyint(1) NOT NULL DEFAULT 0,
            phone_verified_at datetime DEFAULT NULL,
            phone_data_refused tinyint(1) NOT NULL DEFAULT 0,
            photo_refused tinyint(1) NOT NULL DEFAULT 0,
            notes text NULL,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY last_name (last_name),
            KEY employee_code (employee_code),
            KEY branch (branch),
            KEY phone (phone),
            KEY user_id (user_id)
        ) $charset;");

        dbDelta("CREATE TABLE $handovers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scanner_id bigint(20) unsigned NOT NULL,
            driver_id bigint(20) unsigned DEFAULT NULL,
            previous_driver_id bigint(20) unsigned DEFAULT NULL,
            action varchar(20) NOT NULL DEFAULT 'assign',
            handover_at datetime NOT NULL,
            recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
            snapshot_name varchar(190) NOT NULL DEFAULT '',
            snapshot_phone varchar(60) NOT NULL DEFAULT '',
            snapshot_photo varchar(190) NOT NULL DEFAULT '',
            notes text NULL,
            PRIMARY KEY  (id),
            KEY scanner_id (scanner_id),
            KEY driver_id (driver_id),
            KEY handover_at (handover_at)
        ) $charset;");

        dbDelta("CREATE TABLE $status_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scanner_id bigint(20) unsigned NOT NULL,
            old_status varchar(32) NOT NULL DEFAULT '',
            new_status varchar(32) NOT NULL,
            changed_at datetime NOT NULL,
            changed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            notes text NULL,
            PRIMARY KEY  (id),
            KEY scanner_id (scanner_id),
            KEY changed_at (changed_at)
        ) $charset;");

        dbDelta("CREATE TABLE $audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            actor_name varchar(190) NOT NULL DEFAULT '',
            action varchar(80) NOT NULL,
            entity_type varchar(40) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
            scanner_id bigint(20) unsigned DEFAULT NULL,
            driver_id bigint(20) unsigned DEFAULT NULL,
            field_name varchar(80) NOT NULL DEFAULT '',
            old_value longtext NULL,
            new_value longtext NULL,
            ip_address varchar(64) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY scanner_id (scanner_id),
            KEY actor_id (actor_id),
            KEY action (action)
        ) $charset;");

        dbDelta("CREATE TABLE $scans (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scanner_id bigint(20) unsigned NOT NULL,
            serial_number varchar(120) NOT NULL DEFAULT '',
            scanner_code varchar(32) NOT NULL DEFAULT '',
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            actor_name varchar(190) NOT NULL DEFAULT '',
            actor_kind varchar(20) NOT NULL DEFAULT 'guest',
            action varchar(40) NOT NULL DEFAULT 'opened',
            notes text NULL,
            ip_address varchar(64) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY scanner_id (scanner_id),
            KEY created_at (created_at),
            KEY action (action)
        ) $charset;");

        dbDelta("CREATE TABLE $otp (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scanner_id bigint(20) unsigned NOT NULL,
            full_name varchar(190) NOT NULL DEFAULT '',
            phone varchar(60) NOT NULL DEFAULT '',
            photo_path varchar(190) NOT NULL DEFAULT '',
            code_hash varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            consumed_at datetime DEFAULT NULL,
            ip_address varchar(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY scanner_phone (scanner_id, phone),
            KEY created_at (created_at)
        ) $charset;");

        dbDelta("CREATE TABLE $phones (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            model varchar(160) NOT NULL DEFAULT '',
            serial_number varchar(120) NOT NULL DEFAULT '',
            imei varchar(40) NOT NULL DEFAULT '',
            branch varchar(20) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'available',
            current_driver_id bigint(20) unsigned DEFAULT NULL,
            current_assignment_id bigint(20) unsigned DEFAULT NULL,
            assigned_date date DEFAULT NULL,
            date_added date DEFAULT NULL,
            notes text NULL,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY current_driver_id (current_driver_id),
            KEY branch (branch),
            KEY serial_number (serial_number),
            KEY imei (imei)
        ) $charset;");

        dbDelta("CREATE TABLE $phone_assignments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            phone_id bigint(20) unsigned NOT NULL,
            driver_id bigint(20) unsigned DEFAULT NULL,
            previous_driver_id bigint(20) unsigned DEFAULT NULL,
            action varchar(20) NOT NULL DEFAULT 'assign',
            assigned_at datetime NOT NULL,
            recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
            snapshot_name varchar(190) NOT NULL DEFAULT '',
            snapshot_phone varchar(60) NOT NULL DEFAULT '',
            notes text NULL,
            PRIMARY KEY  (id),
            KEY phone_id (phone_id),
            KEY driver_id (driver_id),
            KEY assigned_at (assigned_at)
        ) $charset;");
    }
}
