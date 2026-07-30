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
        add_action('admin_post_paxdesign_customer_unpublish_news', array(__CLASS__, 'handle_unpublish_news'));
        add_action('admin_post_paxdesign_customer_delete_news', array(__CLASS__, 'handle_delete_news'));
        add_action('admin_post_paxdesign_customer_save_project', array(__CLASS__, 'handle_save_project'));
        add_action('admin_post_paxdesign_customer_sync_services', array(__CLASS__, 'handle_sync_services'));
        add_action('admin_post_paxdesign_customer_update_project', array(__CLASS__, 'handle_update_project'));
        add_action('admin_post_paxdesign_customer_add_milestone', array(__CLASS__, 'handle_add_milestone'));
        add_action('admin_post_paxdesign_customer_add_note', array(__CLASS__, 'handle_add_note'));
        add_action('admin_post_paxdesign_customer_assign_user', array(__CLASS__, 'handle_assign_user'));
        add_action('admin_post_paxdesign_customer_upload_file', array(__CLASS__, 'handle_upload_file'));
        add_action('admin_post_paxdesign_customer_update_order', array(__CLASS__, 'handle_update_order'));
        add_action('admin_post_paxdesign_customer_send_notification', array(__CLASS__, 'handle_send_notification'));
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
            'orders'    => __('Orders', 'paxdesign-booking'),
            'news'          => __('News', 'paxdesign-booking'),
            'services'      => __('Services', 'paxdesign-booking'),
            'notifications' => __('Notifications', 'paxdesign-booking'),
            'cybercrime'    => __('Cybercrime Reports', 'paxdesign-booking'),
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
            case 'orders':
                self::render_orders_tab();
                break;
            case 'news':
                self::render_news_tab();
                break;
            case 'services':
                self::render_services_tab();
                break;
            case 'notifications':
                self::render_notifications_tab();
                break;
            case 'cybercrime':
                self::render_cybercrime_tab();
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
        echo '<p>' . esc_html__('Manage customer-facing projects, news, and the services catalog. Authentication uses the PAXDesign booking auth module.', 'paxdesign-booking') . '</p>';
        echo '<ul style="list-style:disc;margin-left:1.5em">';
        printf('<li>%s</li>', esc_html(sprintf(__('Active projects: %d', 'paxdesign-booking'), $projects)));
        printf('<li>%s</li>', esc_html(sprintf(__('Service requests: %d', 'paxdesign-booking'), $orders)));
        printf('<li>%s</li>', esc_html(sprintf(__('Published news: %d', 'paxdesign-booking'), $news)));
        printf('<li>%s</li>', esc_html(sprintf(__('Catalog services: %d', 'paxdesign-booking'), $services)));
        echo '</ul>';
        echo '<p><code>/wp-json/pdx/v1/customer/*</code></p>';
    }

    private static function render_projects_tab() {
        $project_id = absint($_GET['project_id'] ?? 0);
        if ($project_id > 0) {
            self::render_project_detail($project_id);
            return;
        }

        global $wpdb;
        $rows = $wpdb->get_results('SELECT p.*, u.display_name AS customer_name FROM ' . PAXdesign_Customer_DB::table('projects') . ' p LEFT JOIN ' . $wpdb->users . ' u ON u.ID = p.customer_user_id ORDER BY p.updated_at DESC LIMIT 50', ARRAY_A);
        echo '<h2>' . esc_html__('Projects', 'paxdesign-booking') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Ref', 'paxdesign-booking') . '</th><th>' . esc_html__('Title', 'paxdesign-booking') . '</th><th>' . esc_html__('Customer', 'paxdesign-booking') . '</th><th>' . esc_html__('Status', 'paxdesign-booking') . '</th><th>' . esc_html__('Progress', 'paxdesign-booking') . '</th></tr></thead><tbody>';
        foreach ($rows ?: array() as $row) {
            $detail_url = admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=projects&project_id=' . (int) $row['id']);
            echo '<tr>';
            echo '<td><a href="' . esc_url($detail_url) . '">' . esc_html($row['project_ref']) . '</a></td>';
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

    private static function render_orders_tab() {
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT o.*, u.display_name AS customer_name FROM ' . PAXdesign_Customer_DB::table('orders') . ' o LEFT JOIN ' . $wpdb->users . ' u ON u.ID = o.customer_user_id ORDER BY o.updated_at DESC LIMIT 50',
            ARRAY_A
        );
        echo '<h2>' . esc_html__('Service requests', 'paxdesign-booking') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Ref', 'paxdesign-booking') . '</th><th>' . esc_html__('Customer', 'paxdesign-booking') . '</th><th>' . esc_html__('Service', 'paxdesign-booking') . '</th><th>' . esc_html__('Status', 'paxdesign-booking') . '</th><th></th></tr></thead><tbody>';
        foreach ($rows ?: array() as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['order_ref']) . '</td>';
            echo '<td>' . esc_html($row['customer_name'] ?: ('#' . $row['customer_user_id'])) . '</td>';
            echo '<td>' . esc_html($row['service_label']) . '</td>';
            echo '<td>' . esc_html($row['status']) . '</td>';
            echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:6px;align-items:center">';
            wp_nonce_field('paxdesign_customer_update_order');
            echo '<input type="hidden" name="action" value="paxdesign_customer_update_order" />';
            echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $row['id']) . '" />';
            echo '<select name="status">';
            foreach (array('received', 'reviewing', 'quoted', 'approved', 'in_progress', 'completed', 'cancelled') as $status) {
                printf('<option value="%s"%s>%s</option>', esc_attr($status), selected($row['status'], $status, false), esc_html($status));
            }
            echo '</select> ';
            submit_button(__('Update', 'paxdesign-booking'), 'secondary', 'submit', false);
            echo '</form></td>';
            echo '</tr>';
        }
        if (empty($rows)) {
            echo '<tr><td colspan="5">' . esc_html__('No service requests yet.', 'paxdesign-booking') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_news_tab() {
        $items = PAXdesign_Customer_News::list_admin();
        $edit_id = absint($_GET['edit_news'] ?? 0);
        $edit_item = $edit_id > 0 ? PAXdesign_Customer_News::get_row($edit_id) : null;
        $edit_meta = $edit_item ? json_decode((string) ($edit_item['audience_meta'] ?? ''), true) : array();
        if (!is_array($edit_meta)) {
            $edit_meta = array();
        }

        echo '<h2>' . esc_html__('News & announcements', 'paxdesign-booking') . '</h2>';
        echo '<p class="description">' . esc_html__('Published items appear in the mobile app immediately. Deleting an item removes it from the app on the next refresh.', 'paxdesign-booking') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Title', 'paxdesign-booking') . '</th><th>' . esc_html__('Slug', 'paxdesign-booking') . '</th><th>' . esc_html__('Status', 'paxdesign-booking') . '</th><th>' . esc_html__('Published', 'paxdesign-booking') . '</th><th></th></tr></thead><tbody>';
        foreach ($items as $item) {
            $meta = json_decode((string) ($item['audience_meta'] ?? ''), true);
            if (!is_array($meta)) {
                $meta = array();
            }
            echo '<tr>';
            echo '<td><strong>' . esc_html($item['title']) . '</strong>';
            if (!empty($meta['featured_image_url'])) {
                echo '<br><span class="description">' . esc_html__('Featured image set', 'paxdesign-booking') . '</span>';
            }
            echo '</td>';
            echo '<td><code>' . esc_html($item['slug']) . '</code></td>';
            echo '<td>' . esc_html($item['status']) . '</td>';
            echo '<td>' . esc_html($item['published_at'] ?: '—') . '</td>';
            echo '<td style="white-space:nowrap">';
            $edit_url = admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=news&edit_news=' . absint($item['id']));
            echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'paxdesign-booking') . '</a> ';
            if ($item['status'] !== 'published') {
                echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('paxdesign_customer_publish_news');
                echo '<input type="hidden" name="action" value="paxdesign_customer_publish_news" />';
                echo '<input type="hidden" name="news_id" value="' . esc_attr((string) $item['id']) . '" />';
                submit_button(__('Publish', 'paxdesign-booking'), 'secondary button-small', 'submit', false);
                echo '</form> ';
            } else {
                echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('paxdesign_customer_unpublish_news');
                echo '<input type="hidden" name="action" value="paxdesign_customer_unpublish_news" />';
                echo '<input type="hidden" name="news_id" value="' . esc_attr((string) $item['id']) . '" />';
                submit_button(__('Unpublish', 'paxdesign-booking'), 'secondary button-small', 'submit', false);
                echo '</form> ';
            }
            echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(' . esc_js(__('Delete this news item from the app and backend?', 'paxdesign-booking')) . ');">';
            wp_nonce_field('paxdesign_customer_delete_news');
            echo '<input type="hidden" name="action" value="paxdesign_customer_delete_news" />';
            echo '<input type="hidden" name="news_id" value="' . esc_attr((string) $item['id']) . '" />';
            submit_button(__('Delete', 'paxdesign-booking'), 'delete button-small', 'submit', false);
            echo '</form>';
            echo '</td></tr>';
        }
        if (empty($items)) {
            echo '<tr><td colspan="5">' . esc_html__('No news items yet.', 'paxdesign-booking') . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h3>' . esc_html($edit_item ? __('Edit news item', 'paxdesign-booking') : __('Create news item', 'paxdesign-booking')) . '</h3>';
        if ($edit_item) {
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=news')) . '">' . esc_html__('Create new item instead', 'paxdesign-booking') . '</a></p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('paxdesign_customer_save_news');
        echo '<input type="hidden" name="action" value="paxdesign_customer_save_news" />';
        if ($edit_item) {
            echo '<input type="hidden" name="news_id" value="' . esc_attr((string) $edit_item['id']) . '" />';
        }
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="news_title">' . esc_html__('Title', 'paxdesign-booking') . '</label></th><td><input type="text" name="title" id="news_title" class="regular-text" required value="' . esc_attr($edit_item['title'] ?? '') . '" /></td></tr>';
        echo '<tr><th><label for="news_slug">' . esc_html__('Slug', 'paxdesign-booking') . '</label></th><td><input type="text" name="slug" id="news_slug" class="regular-text" value="' . esc_attr($edit_item['slug'] ?? '') . '" placeholder="' . esc_attr__('auto-generated from title', 'paxdesign-booking') . '" /><p class="description">' . esc_html__('Used by the mobile app URL. Lowercase letters, numbers, and hyphens only.', 'paxdesign-booking') . '</p></td></tr>';
        echo '<tr><th><label for="news_excerpt">' . esc_html__('Excerpt', 'paxdesign-booking') . '</label></th><td><textarea name="excerpt" id="news_excerpt" class="large-text" rows="2">' . esc_textarea($edit_item['excerpt'] ?? '') . '</textarea></td></tr>';
        echo '<tr><th><label for="news_body">' . esc_html__('Body', 'paxdesign-booking') . '</label></th><td><textarea name="body" id="news_body" class="large-text" rows="8">' . esc_textarea($edit_item['body'] ?? '') . '</textarea></td></tr>';
        echo '<tr><th><label for="news_featured_image_url">' . esc_html__('Featured image URL', 'paxdesign-booking') . '</label></th><td><input type="url" name="featured_image_url" id="news_featured_image_url" class="large-text" value="' . esc_attr($edit_meta['featured_image_url'] ?? '') . '" placeholder="https://..." /><p class="description">' . esc_html__('Shown at the top of the news article in the app.', 'paxdesign-booking') . '</p></td></tr>';
        echo '<tr><th><label for="news_external_url">' . esc_html__('External link URL', 'paxdesign-booking') . '</label></th><td><input type="url" name="external_url" id="news_external_url" class="large-text" value="' . esc_attr($edit_meta['external_url'] ?? '') . '" placeholder="https://..." /></td></tr>';
        echo '<tr><th><label for="news_external_link_label">' . esc_html__('External link label', 'paxdesign-booking') . '</label></th><td><input type="text" name="external_link_label" id="news_external_link_label" class="regular-text" value="' . esc_attr($edit_meta['external_link_label'] ?? '') . '" placeholder="' . esc_attr__('Learn more', 'paxdesign-booking') . '" /><p class="description">' . esc_html__('Appended to the article body as plain text so existing app versions can display it.', 'paxdesign-booking') . '</p></td></tr>';
        echo '<tr><th><label for="news_priority">' . esc_html__('Priority', 'paxdesign-booking') . '</label></th><td><select name="priority" id="news_priority">';
        foreach (array('normal' => __('Normal', 'paxdesign-booking'), 'high' => __('High', 'paxdesign-booking')) as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($edit_item['priority'] ?? 'normal', $value, false),
                esc_html($label)
            );
        }
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Push on publish', 'paxdesign-booking') . '</th><td><label><input type="checkbox" name="push_on_publish" value="1"' . checked(!empty($edit_item['push_on_publish']), true, false) . ' /> ' . esc_html__('Send push notification to customers', 'paxdesign-booking') . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button($edit_item ? __('Update news item', 'paxdesign-booking') : __('Save draft', 'paxdesign-booking'));
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
        $news_id = absint($_POST['news_id'] ?? 0);
        $existing = $news_id > 0 ? PAXdesign_Customer_News::get_row($news_id) : null;
        $result = PAXdesign_Customer_News::save(array(
            'title'               => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'slug'                => sanitize_text_field(wp_unslash($_POST['slug'] ?? '')),
            'excerpt'             => sanitize_textarea_field(wp_unslash($_POST['excerpt'] ?? '')),
            'body'                => wp_kses_post(wp_unslash($_POST['body'] ?? '')),
            'featured_image_url'  => esc_url_raw(wp_unslash($_POST['featured_image_url'] ?? '')),
            'external_url'        => esc_url_raw(wp_unslash($_POST['external_url'] ?? '')),
            'external_link_label' => sanitize_text_field(wp_unslash($_POST['external_link_label'] ?? '')),
            'priority'            => sanitize_key($_POST['priority'] ?? 'normal'),
            'push_on_publish'     => !empty($_POST['push_on_publish']),
            'status'              => $existing['status'] ?? 'draft',
        ), get_current_user_id(), $news_id);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
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

    public static function handle_unpublish_news() {
        self::verify_admin('paxdesign_customer_unpublish_news');
        $news_id = absint($_POST['news_id'] ?? 0);
        $result = PAXdesign_Customer_News::unpublish($news_id, get_current_user_id());
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=news&saved=1'));
        exit;
    }

    public static function handle_delete_news() {
        self::verify_admin('paxdesign_customer_delete_news');
        $news_id = absint($_POST['news_id'] ?? 0);
        $result = PAXdesign_Customer_News::delete($news_id);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=news&deleted=1'));
        exit;
    }

    public static function handle_sync_services() {
        self::verify_admin('paxdesign_customer_sync_services');
        PAXdesign_Customer_Services::sync_from_booking_catalog();
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=services&synced=1'));
        exit;
    }

    private static function render_project_detail($project_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT p.*, u.display_name AS customer_name FROM ' . PAXdesign_Customer_DB::table('projects') . ' p LEFT JOIN ' . $wpdb->users . ' u ON u.ID = p.customer_user_id WHERE p.id = %d LIMIT 1',
            $project_id
        ), ARRAY_A);
        if (!$row) {
            echo '<p>' . esc_html__('Project not found.', 'paxdesign-booking') . '</p>';
            return;
        }

        $back_url = admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=projects');
        echo '<p><a href="' . esc_url($back_url) . '">&larr; ' . esc_html__('All projects', 'paxdesign-booking') . '</a></p>';
        echo '<h2>' . esc_html($row['title']) . ' <small>(' . esc_html($row['project_ref']) . ')</small></h2>';
        echo '<p>' . esc_html($row['description']) . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:1.5em">';
        wp_nonce_field('paxdesign_customer_update_project');
        echo '<input type="hidden" name="action" value="paxdesign_customer_update_project" />';
        echo '<input type="hidden" name="project_id" value="' . esc_attr((string) $project_id) . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="status">' . esc_html__('Status', 'paxdesign-booking') . '</label></th><td><select name="status" id="status">';
        foreach (array('planning', 'in_progress', 'review', 'completed', 'on_hold') as $status) {
            printf('<option value="%s"%s>%s</option>', esc_attr($status), selected($row['status'], $status, false), esc_html($status));
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="progress">' . esc_html__('Progress %', 'paxdesign-booking') . '</label></th><td><input type="number" min="0" max="100" name="progress" id="progress" value="' . esc_attr((string) $row['progress']) . '" /></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Update project', 'paxdesign-booking'));
        echo '</form>';

        $milestones = PAXdesign_Customer_Projects::milestones($project_id, 'admin');
        echo '<h3>' . esc_html__('Milestones', 'paxdesign-booking') . '</h3><ul>';
        foreach ($milestones ?: array() as $m) {
            printf('<li><strong>%s</strong> — %s (%s)</li>', esc_html($m['title']), esc_html($m['status']), esc_html($m['due_date'] ?: '—'));
        }
        if (empty($milestones)) {
            echo '<li>' . esc_html__('No milestones yet.', 'paxdesign-booking') . '</li>';
        }
        echo '</ul>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('paxdesign_customer_add_milestone');
        echo '<input type="hidden" name="action" value="paxdesign_customer_add_milestone" /><input type="hidden" name="project_id" value="' . esc_attr((string) $project_id) . '" />';
        echo '<p><input type="text" name="title" placeholder="' . esc_attr__('Milestone title', 'paxdesign-booking') . '" class="regular-text" required /> ';
        echo '<input type="date" name="due_date" /> ';
        submit_button(__('Add milestone', 'paxdesign-booking'), 'secondary', 'submit', false);
        echo '</p></form>';

        $notes = PAXdesign_Customer_Projects::notes($project_id, 'admin');
        echo '<h3>' . esc_html__('Notes', 'paxdesign-booking') . '</h3><ul>';
        foreach ($notes ?: array() as $n) {
            echo '<li>' . esc_html($n['body']) . '</li>';
        }
        if (empty($notes)) {
            echo '<li>' . esc_html__('No notes yet.', 'paxdesign-booking') . '</li>';
        }
        echo '</ul>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('paxdesign_customer_add_note');
        echo '<input type="hidden" name="action" value="paxdesign_customer_add_note" /><input type="hidden" name="project_id" value="' . esc_attr((string) $project_id) . '" />';
        echo '<textarea name="body" rows="3" class="large-text" placeholder="' . esc_attr__('Internal note for customer', 'paxdesign-booking') . '" required></textarea><br />';
        submit_button(__('Add note', 'paxdesign-booking'), 'secondary');
        echo '</form>';

        $assignees = PAXdesign_Customer_Projects::assignees($project_id);
        echo '<h3>' . esc_html__('Assignees', 'paxdesign-booking') . '</h3><ul>';
        foreach ($assignees ?: array() as $a) {
            echo '<li>' . esc_html($a['display_name'] ?: ('#' . $a['user_id'])) . ' — ' . esc_html($a['role_label']) . '</li>';
        }
        if (empty($assignees)) {
            echo '<li>' . esc_html__('No assignees yet.', 'paxdesign-booking') . '</li>';
        }
        echo '</ul>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('paxdesign_customer_assign_user');
        echo '<input type="hidden" name="action" value="paxdesign_customer_assign_user" /><input type="hidden" name="project_id" value="' . esc_attr((string) $project_id) . '" />';
        echo '<p><input type="number" name="user_id" placeholder="' . esc_attr__('Staff user ID', 'paxdesign-booking') . '" class="small-text" required /> ';
        echo '<input type="text" name="role_label" placeholder="' . esc_attr__('Role label', 'paxdesign-booking') . '" class="regular-text" value="Project lead" /> ';
        submit_button(__('Assign staff', 'paxdesign-booking'), 'secondary', 'submit', false);
        echo '</p></form>';

        $files = PAXdesign_Customer_Projects::files($project_id, 'admin');
        echo '<h3>' . esc_html__('Files', 'paxdesign-booking') . '</h3><ul>';
        foreach ($files ?: array() as $f) {
            echo '<li>' . esc_html($f['file_name']) . ' <em>(' . esc_html($f['visibility']) . ')</em></li>';
        }
        if (empty($files)) {
            echo '<li>' . esc_html__('No files yet.', 'paxdesign-booking') . '</li>';
        }
        echo '</ul>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field('paxdesign_customer_upload_file');
        echo '<input type="hidden" name="action" value="paxdesign_customer_upload_file" /><input type="hidden" name="project_id" value="' . esc_attr((string) $project_id) . '" />';
        echo '<input type="file" name="file" required /> ';
        echo '<label><input type="checkbox" name="customer_visible" value="1" checked /> ' . esc_html__('Visible to customer', 'paxdesign-booking') . '</label> ';
        submit_button(__('Upload file', 'paxdesign-booking'), 'secondary', 'submit', false);
        echo '</form>';
    }

    private static function render_notifications_tab() {
        echo '<h2>' . esc_html__('Customer notifications', 'paxdesign-booking') . '</h2>';
        echo '<p>' . esc_html__('Send a notification to a specific customer. Push delivery requires a registered device token.', 'paxdesign-booking') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('paxdesign_customer_send_notification');
        echo '<input type="hidden" name="action" value="paxdesign_customer_send_notification" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="notify_user_id">' . esc_html__('Customer user ID', 'paxdesign-booking') . '</label></th><td><input type="number" name="user_id" id="notify_user_id" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="notify_title">' . esc_html__('Title', 'paxdesign-booking') . '</label></th><td><input type="text" name="title" id="notify_title" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="notify_body">' . esc_html__('Message', 'paxdesign-booking') . '</label></th><td><textarea name="body" id="notify_body" class="large-text" rows="4" required></textarea></td></tr>';
        echo '<tr><th><label for="notify_category">' . esc_html__('Category', 'paxdesign-booking') . '</label></th><td><select name="category" id="notify_category"><option value="general">general</option><option value="project">project</option><option value="order">order</option><option value="news">news</option></select></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Send notification', 'paxdesign-booking'));
        echo '</form>';
    }

    public static function handle_update_project() {
        self::verify_admin('paxdesign_customer_update_project');
        $project_id = absint($_POST['project_id'] ?? 0);
        $result = PAXdesign_Customer_Projects::update($project_id, array(
            'status'   => sanitize_key($_POST['status'] ?? ''),
            'progress' => absint($_POST['progress'] ?? 0),
        ), get_current_user_id());
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=projects&project_id=' . $project_id . '&saved=1'));
        exit;
    }

    public static function handle_add_milestone() {
        self::verify_admin('paxdesign_customer_add_milestone');
        $project_id = absint($_POST['project_id'] ?? 0);
        PAXdesign_Customer_Projects::add_milestone($project_id, array(
            'title'    => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
            'status'   => 'pending',
        ), get_current_user_id());
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=projects&project_id=' . $project_id . '&saved=1'));
        exit;
    }

    public static function handle_add_note() {
        self::verify_admin('paxdesign_customer_add_note');
        $project_id = absint($_POST['project_id'] ?? 0);
        PAXdesign_Customer_Projects::add_note($project_id, array(
            'body'       => sanitize_textarea_field(wp_unslash($_POST['body'] ?? '')),
            'visibility' => 'customer',
        ), get_current_user_id());
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=projects&project_id=' . $project_id . '&saved=1'));
        exit;
    }

    public static function handle_assign_user() {
        self::verify_admin('paxdesign_customer_assign_user');
        $project_id = absint($_POST['project_id'] ?? 0);
        PAXdesign_Customer_Projects::assign_user($project_id, array(
            'user_id'    => absint($_POST['user_id'] ?? 0),
            'role_label' => sanitize_text_field(wp_unslash($_POST['role_label'] ?? 'Staff')),
        ), get_current_user_id());
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=projects&project_id=' . $project_id . '&saved=1'));
        exit;
    }

    public static function handle_upload_file() {
        self::verify_admin('paxdesign_customer_upload_file');
        $project_id = absint($_POST['project_id'] ?? 0);
        if (empty($_FILES['file'])) {
            wp_die(esc_html__('No file uploaded.', 'paxdesign-booking'));
        }
        $result = PAXdesign_Customer_Projects::add_file($project_id, $_FILES['file'], array(
            'visibility' => !empty($_POST['customer_visible']) ? 'customer' : 'internal',
        ), get_current_user_id());
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=projects&project_id=' . $project_id . '&saved=1'));
        exit;
    }

    public static function handle_update_order() {
        self::verify_admin('paxdesign_customer_update_order');
        $order_id = absint($_POST['order_id'] ?? 0);
        PAXdesign_Customer_Orders::staff_update($order_id, array(
            'status' => sanitize_key($_POST['status'] ?? ''),
        ), get_current_user_id());
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=orders&saved=1'));
        exit;
    }

    public static function handle_send_notification() {
        self::verify_admin('paxdesign_customer_send_notification');
        PAXdesign_Customer_Notifications::notify_user(
            absint($_POST['user_id'] ?? 0),
            sanitize_key($_POST['category'] ?? 'general'),
            sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            sanitize_textarea_field(wp_unslash($_POST['body'] ?? ''))
        );
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=notifications&saved=1'));
        exit;
    }

    private static function render_cybercrime_tab() {
        if (!class_exists('PAXdesign_Cybercrime_Tickets')) {
            echo '<p>' . esc_html__('Cybercrime ticket module is not available.', 'paxdesign-booking') . '</p>';
            return;
        }

        $reference = sanitize_text_field($_GET['reference'] ?? '');
        if ($reference !== '') {
            self::render_cybercrime_detail($reference);
            return;
        }

        $reports = PAXdesign_Cybercrime_Tickets::list_reports_for_admin(50);
        echo '<h2>' . esc_html__('Cybercrime Support reports', 'paxdesign-booking') . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Reference', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Reporter', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Category', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Status', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Updated', 'paxdesign-booking') . '</th>';
        echo '</tr></thead><tbody>';
        if (empty($reports)) {
            echo '<tr><td colspan="5">' . esc_html__('No reports yet.', 'paxdesign-booking') . '</td></tr>';
        }
        foreach ($reports as $report) {
            $url = admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=cybercrime&reference=' . rawurlencode((string) $report['reference_id']));
            echo '<tr>';
            echo '<td><a href="' . esc_url($url) . '"><code>' . esc_html((string) $report['reference_id']) . '</code></a></td>';
            echo '<td>' . esc_html((string) ($report['reporter_name'] ?? '')) . '<br><small>' . esc_html((string) ($report['reporter_email'] ?? '')) . '</small></td>';
            echo '<td>' . esc_html((string) ($report['category_label'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($report['status_label'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($report['updated_at'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_cybercrime_detail($reference) {
        $row = PAXdesign_Cybercrime_Tickets::get_report_row($reference);
        if (!$row) {
            echo '<p>' . esc_html__('Report not found.', 'paxdesign-booking') . '</p>';
            return;
        }

        $report = PAXdesign_Cybercrime_Tickets::format_report_row($row, true, 'admin');
        $statuses = array('submitted', 'in_review', 'needs_info', 'waiting_for_customer', 'customer_replied', 'waiting_for_staff', 'resolved', 'closed');
        $back_url = admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=cybercrime');

        echo '<p><a href="' . esc_url($back_url) . '">&larr; ' . esc_html__('All reports', 'paxdesign-booking') . '</a></p>';
        echo '<h2><code>' . esc_html((string) $report['reference_id']) . '</code></h2>';
        echo '<p><strong>' . esc_html((string) $report['reporter_name']) . '</strong> &lt;' . esc_html((string) $report['reporter_email']) . '&gt;<br>';
        echo esc_html((string) $report['category_label']) . ' · ' . esc_html((string) $report['status_label']) . '<br>';
        echo esc_html__('Submitted', 'paxdesign-booking') . ': ' . esc_html((string) $report['created_at']) . '<br>';
        echo esc_html__('Updated', 'paxdesign-booking') . ': ' . esc_html((string) $report['updated_at']) . '</p>';

        if (!empty($report['description'])) {
            echo '<h3>' . esc_html__('Summary', 'paxdesign-booking') . '</h3>';
            echo '<p style="max-width:720px">' . nl2br(esc_html((string) $report['description'])) . '</p>';
        }

        if (!empty($report['attachments']) && is_array($report['attachments'])) {
            echo '<h3>' . esc_html__('Attachments', 'paxdesign-booking') . '</h3><ul>';
            foreach ($report['attachments'] as $file) {
                if (!is_array($file)) {
                    continue;
                }
                $name = (string) ($file['name'] ?? 'file');
                $url = (string) ($file['url'] ?? '');
                if ($url !== '') {
                    echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($name) . '</a></li>';
                } else {
                    echo '<li>' . esc_html($name) . '</li>';
                }
            }
            echo '</ul>';
        }

        echo '<h3>' . esc_html__('Timeline', 'paxdesign-booking') . '</h3>';
        echo '<div style="max-width:720px">';
        foreach (($report['timeline'] ?? array()) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            echo '<div style="border-left:3px solid #ccc;padding:8px 12px;margin:0 0 12px 12px">';
            echo '<strong>' . esc_html((string) ($entry['author_type'] ?? '')) . '</strong> · ';
            echo esc_html((string) ($entry['channel'] ?? '')) . ' · ';
            echo '<small>' . esc_html((string) ($entry['created_at'] ?? '')) . '</small>';
            echo '<div>' . nl2br(esc_html((string) ($entry['body'] ?? ''))) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<h3>' . esc_html__('Update status', 'paxdesign-booking') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:520px">';
        wp_nonce_field('paxdesign_cybercrime_update_status');
        echo '<input type="hidden" name="action" value="paxdesign_cybercrime_update_status">';
        echo '<input type="hidden" name="reference_id" value="' . esc_attr((string) $report['reference_id']) . '">';
        echo '<p><label>' . esc_html__('Status', 'paxdesign-booking') . '<br>';
        echo '<select name="status">';
        foreach ($statuses as $status) {
            echo '<option value="' . esc_attr($status) . '"' . selected($report['status'], $status, false) . '>' . esc_html(PAXdesign_Cybercrime_Tickets::status_label($status)) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>' . esc_html__('Customer note (optional)', 'paxdesign-booking') . '<br>';
        echo '<textarea name="summary" rows="3" class="large-text"></textarea></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save status', 'paxdesign-booking') . '</button></p>';
        echo '</form>';

        echo '<h3>' . esc_html__('Staff reply', 'paxdesign-booking') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:520px">';
        wp_nonce_field('paxdesign_cybercrime_staff_reply');
        echo '<input type="hidden" name="action" value="paxdesign_cybercrime_staff_reply">';
        echo '<input type="hidden" name="reference_id" value="' . esc_attr((string) $report['reference_id']) . '">';
        echo '<p><label>' . esc_html__('Message to customer', 'paxdesign-booking') . '<br>';
        echo '<textarea name="message" rows="5" class="large-text" required></textarea></label></p>';
        echo '<p><label>' . esc_html__('Status after reply', 'paxdesign-booking') . '<br>';
        echo '<select name="status">';
        echo '<option value="waiting_for_customer">' . esc_html__('Waiting for Customer', 'paxdesign-booking') . '</option>';
        echo '<option value="in_review">' . esc_html__('In Progress', 'paxdesign-booking') . '</option>';
        echo '<option value="resolved">' . esc_html__('Resolved', 'paxdesign-booking') . '</option>';
        echo '</select></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Send reply', 'paxdesign-booking') . '</button></p>';
        echo '</form>';
    }

    private static function verify_admin($action) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'paxdesign-booking'));
        }
        check_admin_referer($action);
    }
}
