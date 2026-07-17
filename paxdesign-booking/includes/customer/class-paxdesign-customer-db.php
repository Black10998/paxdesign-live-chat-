<?php
/**
 * Customer platform database schema and migrations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_DB {

    const SCHEMA_VERSION = '1.0.0';
    const OPTION_VERSION = 'paxdesign_customer_db_version';

    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'maybe_upgrade'), 5);
    }

    public static function maybe_upgrade() {
        $current = get_option(self::OPTION_VERSION, '');
        if ($current === self::SCHEMA_VERSION) {
            return;
        }
        self::install();
        update_option(self::OPTION_VERSION, self::SCHEMA_VERSION, false);
    }

    public static function table($suffix) {
        global $wpdb;
        return $wpdb->prefix . 'paxdesign_customer_' . $suffix;
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE " . self::table('projects') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_ref varchar(32) NOT NULL,
            customer_user_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description longtext NULL,
            status varchar(32) NOT NULL DEFAULT 'planning',
            progress tinyint(3) unsigned NOT NULL DEFAULT 0,
            start_date date NULL,
            expected_completion date NULL,
            chat_session_id varchar(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY project_ref (project_ref),
            KEY customer_user_id (customer_user_id),
            KEY status (status),
            KEY updated_at (updated_at)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('project_assignees') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            role_label varchar(120) NOT NULL DEFAULT '',
            assigned_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY project_user (project_id, user_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('project_milestones') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description text NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            sort_order int unsigned NOT NULL DEFAULT 0,
            due_date date NULL,
            completed_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY status (status)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('project_notes') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            author_user_id bigint(20) unsigned NOT NULL,
            visibility varchar(16) NOT NULL DEFAULT 'customer',
            body longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY visibility (visibility)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('project_files') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            file_name varchar(255) NOT NULL,
            file_path varchar(512) NOT NULL,
            mime_type varchar(120) NOT NULL DEFAULT '',
            file_size bigint(20) unsigned NOT NULL DEFAULT 0,
            category varchar(64) NOT NULL DEFAULT 'general',
            visibility varchar(16) NOT NULL DEFAULT 'customer',
            uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY visibility (visibility)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('project_activity') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type varchar(64) NOT NULL,
            summary varchar(255) NOT NULL DEFAULT '',
            meta_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('orders') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_ref varchar(32) NOT NULL,
            customer_user_id bigint(20) unsigned NOT NULL,
            project_id bigint(20) unsigned NOT NULL DEFAULT 0,
            service_slug varchar(64) NOT NULL DEFAULT '',
            service_label varchar(255) NOT NULL DEFAULT '',
            status varchar(32) NOT NULL DEFAULT 'received',
            description longtext NULL,
            assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
            expected_delivery date NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY order_ref (order_ref),
            KEY customer_user_id (customer_user_id),
            KEY project_id (project_id),
            KEY status (status)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('order_notes') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            author_user_id bigint(20) unsigned NOT NULL,
            visibility varchar(16) NOT NULL DEFAULT 'customer',
            body longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('order_files') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            file_name varchar(255) NOT NULL,
            file_path varchar(512) NOT NULL,
            mime_type varchar(120) NOT NULL DEFAULT '',
            file_size bigint(20) unsigned NOT NULL DEFAULT 0,
            kind varchar(32) NOT NULL DEFAULT 'attachment',
            visibility varchar(16) NOT NULL DEFAULT 'customer',
            uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('order_activity') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type varchar(64) NOT NULL,
            summary varchar(255) NOT NULL DEFAULT '',
            meta_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('notifications') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            category varchar(32) NOT NULL DEFAULT 'general',
            title varchar(255) NOT NULL,
            body text NULL,
            entity_type varchar(32) NOT NULL DEFAULT '',
            entity_id varchar(64) NOT NULL DEFAULT '',
            deep_link varchar(255) NOT NULL DEFAULT '',
            is_read tinyint(1) NOT NULL DEFAULT 0,
            push_sent tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            read_at datetime NULL,
            PRIMARY KEY (id),
            KEY user_unread (user_id, is_read, created_at),
            KEY entity (entity_type, entity_id)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('news') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(120) NOT NULL,
            title varchar(255) NOT NULL,
            excerpt text NULL,
            body longtext NOT NULL,
            image_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(16) NOT NULL DEFAULT 'draft',
            priority varchar(16) NOT NULL DEFAULT 'normal',
            audience varchar(32) NOT NULL DEFAULT 'all_customers',
            audience_meta longtext NULL,
            push_on_publish tinyint(1) NOT NULL DEFAULT 0,
            published_at datetime NULL,
            expires_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            author_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status_published (status, published_at)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('service_categories') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(64) NOT NULL,
            name varchar(120) NOT NULL,
            description text NULL,
            sort_order int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('services') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            category_slug varchar(64) NOT NULL DEFAULT '',
            description longtext NULL,
            features_json longtext NULL,
            examples_json longtext NULL,
            related_slugs_json longtext NULL,
            media_json longtext NULL,
            is_featured tinyint(1) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int unsigned NOT NULL DEFAULT 0,
            source_key varchar(64) NOT NULL DEFAULT '',
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY category_slug (category_slug),
            KEY is_active (is_active)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('chat_sessions') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_id varchar(64) NOT NULL,
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            linked_at datetime NOT NULL,
            link_method varchar(32) NOT NULL DEFAULT 'login',
            device_token_hash varchar(64) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY user_primary (user_id, is_primary)
        ) ENGINE=InnoDB $charset;");

        dbDelta("CREATE TABLE " . self::table('guest_claims') . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_id varchar(64) NOT NULL,
            device_token_hash varchar(64) NOT NULL,
            claimed_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB $charset;");

        self::upgrade_chat_logs_column();
    }

    private static function upgrade_chat_logs_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'paxdesign_chat_logs';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return;
        }
        $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'wp_user_id'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER session_id");
            $wpdb->query("ALTER TABLE `$table` ADD KEY wp_user_id (wp_user_id)");
        }
    }

    public static function generate_ref($prefix) {
        return strtoupper($prefix) . '-' . gmdate('Ymd') . '-' . strtoupper(substr(wp_generate_password(6, false, false), 0, 6));
    }
}
