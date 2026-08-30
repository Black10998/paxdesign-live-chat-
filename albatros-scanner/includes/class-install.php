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
    }

    private static function schema_ready() {
        global $wpdb;
        $scans = self::table('scan_events');
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $scans));
        if ($found !== $scans) {
            return false;
        }
        $column = $wpdb->get_var('SHOW COLUMNS FROM ' . self::table('scanners') . " LIKE 'deleted_at'");
        return $column === 'deleted_at';
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

        dbDelta("CREATE TABLE $scanners (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scanner_code varchar(32) NOT NULL,
            brand varchar(120) NOT NULL,
            model varchar(120) NOT NULL,
            serial_number varchar(120) NOT NULL,
            phone_number varchar(60) NOT NULL DEFAULT '',
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
            KEY deleted_at (deleted_at)
        ) $charset;");

        dbDelta("CREATE TABLE $drivers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(120) NOT NULL,
            last_name varchar(120) NOT NULL,
            phone varchar(60) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            employee_code varchar(60) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            notes text NULL,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY last_name (last_name),
            KEY employee_code (employee_code)
        ) $charset;");

        dbDelta("CREATE TABLE $handovers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scanner_id bigint(20) unsigned NOT NULL,
            driver_id bigint(20) unsigned DEFAULT NULL,
            previous_driver_id bigint(20) unsigned DEFAULT NULL,
            action varchar(20) NOT NULL DEFAULT 'assign',
            handover_at datetime NOT NULL,
            recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
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
    }
}
