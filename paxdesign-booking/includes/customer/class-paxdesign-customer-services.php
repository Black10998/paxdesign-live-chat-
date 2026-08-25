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
        // Seed used to run while the plugin file loaded, before customer tables
        // exist (maybe_upgrade is on plugins_loaded:5). Retry after schema is ready.
        add_action('plugins_loaded', array(__CLASS__, 'maybe_seed_catalog'), 20);
        add_action('init', array(__CLASS__, 'maybe_seed_catalog'), 20);
    }

    public static function maybe_seed_catalog() {
        if (self::active_service_count() > 0) {
            if (get_option('paxdesign_customer_services_seeded') !== '1') {
                update_option('paxdesign_customer_services_seeded', '1', false);
            }
            return;
        }
        self::seed_from_booking_catalog();
        if (self::active_service_count() > 0) {
            update_option('paxdesign_customer_services_seeded', '1', false);
        }
    }

    public static function sync_from_booking_catalog() {
        self::seed_from_booking_catalog();
        if (self::active_service_count() > 0) {
            update_option('paxdesign_customer_services_seeded', '1', false);
        }
    }

    public static function seed_from_booking_catalog() {
        global $wpdb;
        if (!class_exists('PAXdesign_Booking')) {
            return;
        }
        if (class_exists('PAXdesign_Customer_DB')) {
            PAXdesign_Customer_DB::maybe_upgrade();
        }
        if (!self::services_table_ready()) {
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
            array('slug' => 'development', 'name' => 'Entwicklung', 'description' => 'Apps, Websites und individuelle Software.'),
            array('slug' => 'design', 'name' => 'Design & Branding', 'description' => 'UI/UX und visuelle Identität.'),
            array('slug' => 'security', 'name' => 'Sicherheit', 'description' => 'Schutz- und Compliance-Leistungen.'),
            array('slug' => 'ai', 'name' => 'KI & Automatisierung', 'description' => 'Intelligente Automatisierungslösungen.'),
            array('slug' => 'operations', 'name' => 'Betrieb', 'description' => 'Hosting, Wartung und Analysen.'),
            array('slug' => 'general', 'name' => 'Leistungen', 'description' => 'Digitale Leistungen von PAXdesign.'),
        );
    }

    private static function services_table_ready() {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('services');
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function active_service_count() {
        global $wpdb;
        if (!self::services_table_ready()) {
            return 0;
        }
        $table = PAXdesign_Customer_DB::table('services');
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_active = 1");
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
        self::maybe_seed_catalog();
        global $wpdb;
        $rows = array();
        if (self::services_table_ready()) {
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
        }
        $services = array_map(array(__CLASS__, 'format_service'), $rows ? $rows : array());
        $services = self::merge_services($services, self::services_from_wordpress_pages());
        $services = self::merge_services($services, self::services_from_booking_catalog());

        if (!empty($args['category'])) {
            $category = sanitize_key($args['category']);
            $services = array_values(array_filter($services, static function ($service) use ($category) {
                return sanitize_key($service['category'] ?? '') === $category;
            }));
        }
        if (!empty($args['search'])) {
            $needle = strtolower(sanitize_text_field($args['search']));
            $services = array_values(array_filter($services, static function ($service) use ($needle) {
                $haystack = strtolower(($service['name'] ?? '') . ' ' . ($service['description'] ?? ''));
                return strpos($haystack, $needle) !== false;
            }));
        }

        return $services;
    }

    /**
     * Full catalog for owner administration, including inactive rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function list_for_admin() {
        self::maybe_seed_catalog();
        global $wpdb;
        $items = array();
        if (self::services_table_ready()) {
            $table = PAXdesign_Customer_DB::table('services');
            $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC, name ASC", ARRAY_A);
            foreach ($rows ?: array() as $row) {
                $item = self::format_service($row);
                $item['is_active'] = !empty($row['is_active']);
                $item['is_featured'] = !empty($row['is_featured']);
                $items[] = $item;
            }
        }
        if (empty($items)) {
            foreach (self::list_services(array()) as $service) {
                $service['is_active'] = true;
                $items[] = $service;
            }
        }
        return $items;
    }

    public static function get_by_slug($slug) {
        global $wpdb;
        $slug = sanitize_key($slug);
        if (self::services_table_ready()) {
            $table = PAXdesign_Customer_DB::table('services');
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE slug = %s AND is_active = 1 LIMIT 1", $slug), ARRAY_A);
            if ($row) {
                return self::format_service($row);
            }
        }
        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status === 'publish') {
            return self::format_wordpress_page_service($page);
        }
        foreach (self::services_from_booking_catalog() as $service) {
            if (sanitize_key($service['slug'] ?? '') === $slug) {
                return $service;
            }
        }
        return null;
    }

    public static function list_categories() {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('service_categories');
        $cat_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        $rows = array();
        if ($cat_exists) {
            $rows = $wpdb->get_results("SELECT slug, name, description, sort_order FROM $table ORDER BY sort_order ASC", ARRAY_A);
        }
        if (!empty($rows)) {
            return $rows;
        }
        return self::default_categories();
    }

    /**
     * @param array<int, array<string, mixed>> $primary
     * @param array<int, array<string, mixed>> $extra
     * @return array<int, array<string, mixed>>
     */
    private static function merge_services(array $primary, array $extra) {
        $by_slug = array();
        foreach ($primary as $service) {
            if (!empty($service['slug'])) {
                $by_slug[sanitize_key($service['slug'])] = $service;
            }
        }
        foreach ($extra as $service) {
            $slug = sanitize_key($service['slug'] ?? '');
            if ($slug !== '' && !isset($by_slug[$slug])) {
                $by_slug[$slug] = $service;
            }
        }
        return array_values($by_slug);
    }

    /**
     * Published WordPress service pages (Services / Leistungen sections).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function services_from_wordpress_pages() {
        $pages = array();
        foreach (array('services', 'leistungen', 'service') as $root_slug) {
            $root = get_page_by_path($root_slug, OBJECT, 'page');
            if (!$root instanceof WP_Post) {
                continue;
            }
            $children = get_pages(array(
                'post_type'   => 'page',
                'post_status' => 'publish',
                'parent'      => (int) $root->ID,
                'sort_column' => 'menu_order,post_title',
            ));
            foreach ($children as $child) {
                $pages[$child->post_name] = $child;
            }
        }
        $items = array();
        foreach ($pages as $page) {
            $items[] = self::format_wordpress_page_service($page);
        }
        return $items;
    }

    /**
     * Live booking catalog (same slugs the iOS app already requests: website, aiautomation, gdpr, …).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function services_from_booking_catalog() {
        if (!class_exists('PAXdesign_Booking')) {
            return array();
        }
        $booking = PAXdesign_Booking::get_instance();
        $catalog = $booking->get_services();
        if (!is_array($catalog) || empty($catalog)) {
            return array();
        }
        $items = array();
        $order = 0;
        foreach ($catalog as $key => $service) {
            if (!is_array($service)) {
                continue;
            }
            $order++;
            $slug = sanitize_key((string) $key);
            if ($slug === '') {
                continue;
            }
            $name = sanitize_text_field((string) ($service['name'] ?? $slug));
            $description = (string) ($service['description'] ?? '');
            $category = sanitize_key((string) ($service['category'] ?? 'general'));
            if ($category === '') {
                $category = 'general';
            }
            $items[] = array(
                'slug'        => $slug,
                'name'        => $name,
                'category'    => $category,
                'description' => $description,
                'body_html'   => wp_kses_post($description),
                'body_text'   => wp_trim_words(wp_strip_all_tags($description), 120),
                'features'    => isset($service['features']) && is_array($service['features']) ? $service['features'] : array(),
                'examples'    => array(),
                'related'     => self::related_for_key($slug),
                'media'       => array('icon' => $slug),
                'image_url'   => '',
                'icon_key'    => $slug,
                'order_url'   => self::order_url_for_service($slug, $name),
                'featured'    => !empty($service['popular']) || !empty($service['premium']),
            );
        }
        return $items;
    }

    /**
     * @param WP_Post $page
     * @return array<string, mixed>
     */
    private static function format_wordpress_page_service($page) {
        $slug = sanitize_key($page->post_name);
        $thumb = get_the_post_thumbnail_url($page, 'medium_large');
        if (!$thumb) {
            $thumb = get_the_post_thumbnail_url($page, 'large');
        }
        $item = array(
            'slug'        => $slug,
            'name'        => get_the_title($page),
            'category'    => 'general',
            'description' => '',
            'body_html'   => '',
            'body_text'   => '',
            'features'    => array(),
            'examples'    => array(),
            'related'     => array(),
            'media'       => array('icon' => $slug),
            'image_url'   => $thumb ? esc_url_raw((string) $thumb) : '',
            'icon_key'    => $slug,
            'order_url'   => self::order_url_for_service($slug, get_the_title($page)),
            'featured'    => false,
        );
        PAXdesign_Customer_Elementor::enrich_item($page, $item, true);
        if ($item['description'] === '' && !empty($item['body_text'])) {
            $item['description'] = wp_trim_words((string) $item['body_text'], 40, '…');
        }
        return $item;
    }

    private static function format_service($row) {
        $slug = isset($row['slug']) ? sanitize_key($row['slug']) : '';
        $source = !empty($row['source_key']) ? sanitize_key($row['source_key']) : $slug;
        $media = json_decode($row['media_json'] ?: '{}', true);
        if (!is_array($media)) {
            $media = array();
        }
        $image_url = '';
        if (!empty($media['image'])) {
            $image_url = esc_url_raw((string) $media['image']);
        } elseif (!empty($media['icon']) && is_string($media['icon']) && preg_match('#^https?://#i', $media['icon'])) {
            $image_url = esc_url_raw($media['icon']);
        }
        return array(
            'slug'        => $slug,
            'name'        => $row['name'],
            'category'    => $row['category_slug'],
            'description' => $row['description'],
            'body_html'   => wp_kses_post($row['description']),
            'body_text'   => wp_trim_words(wp_strip_all_tags($row['description']), 120),
            'features'    => json_decode($row['features_json'] ?: '[]', true) ?: array(),
            'examples'    => json_decode($row['examples_json'] ?: '[]', true) ?: array(),
            'related'     => json_decode($row['related_slugs_json'] ?: '[]', true) ?: array(),
            'media'       => $media,
            'image_url'   => $image_url,
            'icon_key'    => !empty($media['icon']) ? sanitize_key((string) $media['icon']) : $source,
            'order_url'   => self::order_url_for_service($source, $row['name']),
            'featured'    => !empty($row['is_featured']),
        );
    }

    public static function order_url_for_service($source_key, $service_name = '') {
        $base = trim(get_option('paxdesign_booking_contact_url', ''));
        if ($base === '') {
            $base = home_url('/kontakt/');
        }
        $args = array(
            'pax_service' => sanitize_key($source_key),
        );
        if ($service_name !== '') {
            $args['pax_service_name'] = sanitize_text_field($service_name);
        }
        return add_query_arg($args, $base);
    }
}
