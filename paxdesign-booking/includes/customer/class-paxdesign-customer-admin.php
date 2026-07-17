<?php
/**
 * WordPress admin UI for customer portal content (projects, orders, news, services).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Admin {

    const PARENT_SLUG = 'paxdesign-booking';
    const MENU_SLUG   = 'paxdesign-customer-portal';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 20);
        add_action('admin_post_paxdesign_customer_save_news', array(__CLASS__, 'handle_save_news'));
        add_action('admin_post_paxdesign_customer_publish_news', array(__CLASS__, 'handle_publish_news'));
        add_action('admin_post_paxdesign_customer_save_project', array(__CLASS__, 'handle_save_project'));
        add_action('admin_post_paxdesign_customer_sync_services', array(__CLASS__, 'handle_sync_services'));
    }

    public static function register_menu() {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Customer Portal', 'paxdesign-booking'),
            __('Customer Portal', 'paxdesign-booking'),
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'render_page')
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'paxdesign-booking'));
        }

        $tab = sanitize_key($_GET['tab'] ?? 'overview');
        echo '<div class="wrap"><h1>' . esc_html__('Customer Portal', 'paxdesign-booking') . '</h1>';
        echo '<nav class="nav-tab-wrapper">';
        foreach (array(
            'overview'  => __('Overview', 'paxdesign-booking'),
            'projects'  => __('Projects', 'paxdesign-booking'),
            'news'      => __('News', 'paxdesign-booking'),
            'services'  => __('Services', 'paxdesign-booking'),
        ) as $slug => $label) {
            $class = $tab === $slug ? ' nav-tab nav-tab-active' : ' nav-tab';
            echo '<a class="' . esc_attr(trim($class)) . '" href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=' . $slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved.', 'paxdesign-booking') . '</p></div>';
        }
        if (isset($_GET['synced'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Services catalog synced.', 'paxdesign-booking') . '</p></div>';
        }

        switch ($tab) {
            case 'projects':
                self::render_projects_tab();
                break;
            case 'news':
                self::render_news_tab();
                break;
            case 'services':
                self::render_services_tab();
                break;
            default:
                self::render_overview_tab();
        }

        echo '</div>';
    }

    private static function render_overview_tab() {
        global $wpdb;
        $projects = (int) $wpdb->get_var('SELECT COUNT(1) FROM ' . PAXdesign_Customer_DB::table('projects'));
        $orders = (int) $wpdb->get_var('SELECT COUNT(1) FROM ' . PAXdesign_Customer_DB::table('orders'));
        $news = (int) $wpdb->get_var("SELECT COUNT(1) FROM " . PAXdesign_Customer_DB::table('news') . " WHERE status = 'published'");
        $services = (int) $wpdb->get_var('SELECT COUNT(1) FROM ' . PAXdesign_Customer_DB::table('services') . ' WHERE is_active = 1');
        echo '<p>' . esc_html__('Manage customer-facing projects, news, and the services catalog. Authentication uses paxdesign-toolbar PDX_Auth.', 'paxdesign-booking') . '</p>';
        echo '<ul style="list-style:disc;margin-left:1.5em">';
        printf('<li>%s</li>', esc_html(sprintf(__('Active projects: %d', 'paxdesign-booking'), $projects)));
        printf('<li>%s</li>', esc_html(sprintf(__('Service requests: %d', 'paxdesign-booking'), $orders)));
        printf('<li>%s</li>', esc_html(sprintf(__('Published news: %d', 'paxdesign-booking'), $news)));
        printf('<li>%s</li>', esc_html(sprintf(__('Catalog services: %d', 'paxdesign-booking'), $services)));
        echo '</ul>';
        echo '<p><code>/wp-json/pdx/v1/customer/*</code></p>';
    }

    private static function render_projects_tab() {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT p.*, u.display_name AS customer_name FROM ' . PAXdesign_Customer_DB::table('projects') . ' p LEFT JOIN ' . $wpdb->users . ' u ON u.ID = p.customer_user_id ORDER BY p.updated_at DESC LIMIT 50', ARRAY_A);
        echo '<h2>' . esc_html__('Projects', 'paxdesign-booking') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Ref', 'paxdesign-booking') . '</th><th>' . esc_html__('Title', 'paxdesign-booking') . '</th><th>' . esc_html__('Customer', 'paxdesign-booking') . '</th><th>' . esc_html__('Status', 'paxdesign-booking') . '</th><th>' . esc_html__('Progress', 'paxdesign-booking') . '</th></tr></thead><tbody>';
        foreach ($rows ?: array() as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['project_ref']) . '</td>';
            echo '<td>' . esc_html($row['title']) . '</td>';
            echo '<td>' . esc_html($row['customer_name'] ?: ('#' . $row['customer_user_id'])) . '</td>';
            echo '<td>' . esc_html($row['status']) . '</td>';
            echo '<td>' . esc_html((string) $row['progress']) . '%</td>';
            echo '</tr>';
        }
        if (empty($rows)) {
            echo '<tr><td colspan="5">' . esc_html__('No projects yet.', 'paxdesign-booking') . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h3>' . esc_html__('Create project', 'paxdesign-booking') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('paxdesign_customer_save_project');
        echo '<input type="hidden" name="action" value="paxdesign_customer_save_project" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="customer_user_id">' . esc_html__('Customer user ID', 'paxdesign-booking') . '</label></th><td><input type="number" name="customer_user_id" id="customer_user_id" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="title">' . esc_html__('Title', 'paxdesign-booking') . '</label></th><td><input type="text" name="title" id="title" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="description">' . esc_html__('Description', 'paxdesign-booking') . '</label></th><td><textarea name="description" id="description" class="large-text" rows="4"></textarea></td></tr>';
        echo '<tr><th><label for="status">' . esc_html__('Status', 'paxdesign-booking') . '</label></th><td><select name="status" id="status"><option value="planning">planning</option><option value="in_progress">in_progress</option><option value="review">review</option><option value="completed">completed</option></select></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Create project', 'paxdesign-booking'));
        echo '</form>';
    }

    private static function render_news_tab() {
        $items = PAXdesign_Customer_News::list_admin();
        echo '<h2>' . esc_html__('News & announcements', 'paxdesign-booking') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Title', 'paxdesign-booking') . '</th><th>' . esc_html__('Status', 'paxdesign-booking') . '</th><th>' . esc_html__('Published', 'paxdesign-booking') . '</th><th></th></tr></thead><tbody>';
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item['title']) . '</td>';
            echo '<td>' . esc_html($item['status']) . '</td>';
            echo '<td>' . esc_html($item['published_at'] ?: '—') . '</td>';
            echo '<td>';
            if ($item['status'] !== 'published') {
                echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('paxdesign_customer_publish_news');
                echo '<input type="hidden" name="action" value="paxdesign_customer_publish_news" />';
                echo '<input type="hidden" name="news_id" value="' . esc_attr((string) $item['id']) . '" />';
                submit_button(__('Publish', 'paxdesign-booking'), 'secondary', 'submit', false);
                echo '</form>';
            }
            echo '</td></tr>';
        }
        if (empty($items)) {
            echo '<tr><td colspan="4">' . esc_html__('No news items yet.', 'paxdesign-booking') . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h3>' . esc_html__('Create news item', 'paxdesign-booking') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('paxdesign_customer_save_news');
        echo '<input type="hidden" name="action" value="paxdesign_customer_save_news" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="news_title">' . esc_html__('Title', 'paxdesign-booking') . '</label></th><td><input type="text" name="title" id="news_title" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="news_excerpt">' . esc_html__('Excerpt', 'paxdesign-booking') . '</label></th><td><textarea name="excerpt" id="news_excerpt" class="large-text" rows="2"></textarea></td></tr>';
        echo '<tr><th><label for="news_body">' . esc_html__('Body', 'paxdesign-booking') . '</label></th><td><textarea name="body" id="news_body" class="large-text" rows="8"></textarea></td></tr>';
        echo '<tr><th>' . esc_html__('Push on publish', 'paxdesign-booking') . '</th><td><label><input type="checkbox" name="push_on_publish" value="1" /> ' . esc_html__('Send push notification to customers', 'paxdesign-booking') . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Save draft', 'paxdesign-booking'));
        echo '</form>';
    }

    private static function render_services_tab() {
        $services = PAXdesign_Customer_Services::list_services(array());
        echo '<h2>' . esc_html__('Services catalog', 'paxdesign-booking') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:1em">';
        wp_nonce_field('paxdesign_customer_sync_services');
        echo '<input type="hidden" name="action" value="paxdesign_customer_sync_services" />';
        submit_button(__('Sync from booking catalog', 'paxdesign-booking'), 'secondary');
        echo '</form>';
        echo '<p>' . esc_html(sprintf(__('%d active services in customer catalog.', 'paxdesign-booking'), count($services))) . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Name', 'paxdesign-booking') . '</th><th>' . esc_html__('Category', 'paxdesign-booking') . '</th><th>' . esc_html__('Slug', 'paxdesign-booking') . '</th></tr></thead><tbody>';
        foreach (array_slice($services, 0, 30) as $service) {
            echo '<tr><td>' . esc_html($service['name']) . '</td><td>' . esc_html($service['category']) . '</td><td><code>' . esc_html($service['slug']) . '</code></td></tr>';
        }
        if (empty($services)) {
            echo '<tr><td colspan="3">' . esc_html__('No services synced yet.', 'paxdesign-booking') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function handle_save_project() {
        self::verify_admin('paxdesign_customer_save_project');
        $result = PAXdesign_Customer_Projects::create(array(
            'customer_user_id' => absint($_POST['customer_user_id'] ?? 0),
            'title'            => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'description'      => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
            'status'           => sanitize_key($_POST['status'] ?? 'planning'),
        ), get_current_user_id());
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=projects&saved=1'));
        exit;
    }

    public static function handle_save_news() {
        self::verify_admin('paxdesign_customer_save_news');
        PAXdesign_Customer_News::save(array(
            'title'           => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'excerpt'         => sanitize_textarea_field(wp_unslash($_POST['excerpt'] ?? '')),
            'body'            => wp_kses_post(wp_unslash($_POST['body'] ?? '')),
            'push_on_publish' => !empty($_POST['push_on_publish']),
            'status'          => 'draft',
        ), get_current_user_id());
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=news&saved=1'));
        exit;
    }

    public static function handle_publish_news() {
        self::verify_admin('paxdesign_customer_publish_news');
        $news_id = absint($_POST['news_id'] ?? 0);
        $result = PAXdesign_Customer_News::publish($news_id, get_current_user_id());
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=news&saved=1'));
        exit;
    }

    public static function handle_sync_services() {
        self::verify_admin('paxdesign_customer_sync_services');
        PAXdesign_Customer_Services::sync_from_booking_catalog();
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=services&synced=1'));
        exit;
    }

    private static function verify_admin($action) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'paxdesign-booking'));
        }
        check_admin_referer($action);
    }
}
