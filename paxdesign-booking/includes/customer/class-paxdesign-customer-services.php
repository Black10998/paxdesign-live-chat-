<?php
/**
 * Structured service catalog for customer portal (sourced from projektpreise / booking catalog).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Services {

    public static function init() {
        add_action('paxdesign_customer_platform_ready', array(__CLASS__, 'maybe_seed_catalog'));
    }

    public static function maybe_seed_catalog() {
        if (get_option('paxdesign_customer_services_seeded') === '1') {
            return;
        }
        self::seed_from_booking_catalog();
        update_option('paxdesign_customer_services_seeded', '1', false);
    }

    public static function seed_from_booking_catalog() {
        global $wpdb;
        if (!class_exists('PAXdesign_Booking')) {
            return;
        }
        $booking = PAXdesign_Booking::get_instance();
        $services = $booking->get_services();
        $categories = self::default_categories();
        $cat_table = PAXdesign_Customer_DB::table('service_categories');
        foreach ($categories as $i => $cat) {
            $wpdb->replace($cat_table, array(
                'slug'        => $cat['slug'],
                'name'        => $cat['name'],
                'description' => $cat['description'],
                'sort_order'  => $i,
            ));
        }
        $svc_table = PAXdesign_Customer_DB::table('services');
        $order = 0;
        foreach ($services as $key => $service) {
            $order++;
            $related = self::related_for_key($key);
            $wpdb->replace($svc_table, array(
                'slug'               => sanitize_key($key),
                'name'               => sanitize_text_field($service['name']),
                'category_slug'      => sanitize_key($service['category'] ?? 'general'),
                'description'        => wp_kses_post($service['description'] ?? ''),
                'features_json'      => wp_json_encode(isset($service['features']) ? $service['features'] : array()),
                'examples_json'      => wp_json_encode(array()),
                'related_slugs_json' => wp_json_encode($related),
                'media_json'         => wp_json_encode(array('icon' => $key)),
                'is_featured'        => !empty($service['popular']) || !empty($service['premium']) ? 1 : 0,
                'is_active'          => 1,
                'sort_order'         => $order,
                'source_key'         => sanitize_key($key),
                'updated_at'         => current_time('mysql', true),
            ));
        }
    }

    private static function default_categories() {
        return array(
            array('slug' => 'development', 'name' => 'Development', 'description' => 'Apps, websites, and custom software.'),
            array('slug' => 'design', 'name' => 'Design & Branding', 'description' => 'UI/UX and visual identity.'),
            array('slug' => 'security', 'name' => 'Security', 'description' => 'Protection and compliance services.'),
            array('slug' => 'ai', 'name' => 'AI & Automation', 'description' => 'Intelligent automation solutions.'),
            array('slug' => 'operations', 'name' => 'Operations', 'description' => 'Hosting, maintenance, and analytics.'),
            array('slug' => 'general', 'name' => 'Services', 'description' => 'Professional digital services from PAXDesign.'),
        );
    }

    private static function related_for_key($key) {
        $map = array(
            'website' => array('webapp', 'pagespeed', 'maintenance'),
            'ios'     => array('crossplatform', 'android', 'uiux'),
            'android' => array('crossplatform', 'ios', 'uiux'),
            'security'=> array('gdpr', 'secintegrity', 'backend'),
        );
        return isset($map[$key]) ? $map[$key] : array();
    }

    public static function list_services($args = array()) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('services');
        $where = array('is_active = 1');
        $params = array();
        if (!empty($args['category'])) {
            $where[] = 'category_slug = %s';
            $params[] = sanitize_key($args['category']);
        }
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY sort_order ASC, name ASC";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return array_map(array(__CLASS__, 'format_service'), $rows ? $rows : array());
    }

    public static function get_by_slug($slug) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('services');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE slug = %s AND is_active = 1 LIMIT 1", sanitize_key($slug)), ARRAY_A);
        return $row ? self::format_service($row) : null;
    }

    public static function list_categories() {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('service_categories');
        return $wpdb->get_results("SELECT slug, name, description, sort_order FROM $table ORDER BY sort_order ASC", ARRAY_A);
    }

    private static function format_service($row) {
        return array(
            'slug'        => $row['slug'],
            'name'        => $row['name'],
            'category'    => $row['category_slug'],
            'description' => $row['description'],
            'features'    => json_decode($row['features_json'] ?: '[]', true) ?: array(),
            'examples'    => json_decode($row['examples_json'] ?: '[]', true) ?: array(),
            'related'     => json_decode($row['related_slugs_json'] ?: '[]', true) ?: array(),
            'media'       => json_decode($row['media_json'] ?: '{}', true) ?: array(),
            'featured'    => !empty($row['is_featured']),
        );
    }
}
