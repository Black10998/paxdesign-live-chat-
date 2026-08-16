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

    /**
     * Actual admin page hook suffix returned by add_submenu_page(). WordPress
     * derives this from the parent menu's sanitized title (e.g. "Booking System"
     * => "booking-system_page_..."), which is not the same as the parent slug, so
     * it must be captured at registration time rather than hardcoded.
     *
     * @var string
     */
    private static $page_hook = '';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 20);
        add_action('admin_menu', array(__CLASS__, 'add_menu_unread_badges'), 999);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_notification_assets'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_cybercrime_admin_assets'));
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
        self::$page_hook = (string) add_submenu_page(
            self::PARENT_SLUG,
            __('Customer Portal', 'paxdesign-booking'),
            __('Customer Portal', 'paxdesign-booking'),
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'render_page')
        );
    }

    /**
     * Append unread Cybercrime counts to Booking System + Customer Portal admin menu labels.
     */
    public static function add_menu_unread_badges() {
        if (!current_user_can('manage_options') || !class_exists('PAXdesign_Cybercrime_Tickets')) {
            return;
        }

        $summary = PAXdesign_Cybercrime_Tickets::staff_unread_summary(50);
        $total = (int) ($summary['total'] ?? 0);
        if ($total <= 0) {
            return;
        }

        $label = $total > 99 ? '99+' : (string) $total;
        $badge = ' <span class="awaiting-mod pax-cc-menu-unread-badge"><span class="pax-cc-menu-unread-count">' . esc_html($label) . '</span></span>';

        global $submenu;
        if (isset($submenu[self::PARENT_SLUG]) && is_array($submenu[self::PARENT_SLUG])) {
            foreach ($submenu[self::PARENT_SLUG] as $index => $item) {
                if (!is_array($item) || ($item[2] ?? '') !== self::MENU_SLUG) {
                    continue;
                }
                $submenu[self::PARENT_SLUG][$index][0] .= $badge;
                break;
            }
        }

        global $menu;
        if (is_array($menu)) {
            foreach ($menu as $index => $item) {
                if (!is_array($item) || ($item[2] ?? '') !== self::PARENT_SLUG) {
                    continue;
                }
                $menu[$index][0] .= $badge;
                break;
            }
        }
    }

    /**
     * Poll unread counts and keep admin menu badges in sync on every wp-admin screen.
     */
    public static function enqueue_admin_notification_assets($hook) {
        unset($hook);
        if (!current_user_can('manage_options') || !class_exists('PAXdesign_Cybercrime_Tickets') || !defined('PAXDESIGN_BOOKING_PLUGIN_URL')) {
            return;
        }

        wp_enqueue_script(
            'paxdesign-cybercrime-admin-notifications',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/cybercrime-admin-notifications.js',
            array(),
            defined('PAXDESIGN_BOOKING_VERSION') ? PAXDESIGN_BOOKING_VERSION : '1.0',
            true
        );

        wp_localize_script(
            'paxdesign-cybercrime-admin-notifications',
            'paxCybercrimeAdminNotify',
            array(
                'ajaxUrl'          => admin_url('admin-ajax.php'),
                'nonce'            => wp_create_nonce(PAXdesign_Cybercrime_Tickets::ADMIN_NONCE_ACTION),
                'pollIntervalMs'   => 30000,
                'initialSummary'   => PAXdesign_Cybercrime_Tickets::staff_unread_summary(50),
                'defaultPortalUrl' => admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=cybercrime'),
                'parentMenuSlug'   => self::PARENT_SLUG,
                'portalMenuSlug'   => self::MENU_SLUG,
            )
        );

        wp_register_style('paxdesign-cybercrime-admin-notify', false, array(), defined('PAXDESIGN_BOOKING_VERSION') ? PAXDESIGN_BOOKING_VERSION : '1.0');
        wp_enqueue_style('paxdesign-cybercrime-admin-notify');
        wp_add_inline_style(
            'paxdesign-cybercrime-admin-notify',
            '.pax-cc-menu-unread-badge .pax-cc-menu-unread-count{display:inline-block;min-width:18px;height:18px;padding:0 6px;border-radius:10px;background:#d63638;color:#fff;font-size:11px;font-weight:700;line-height:18px;text-align:center}'
            . '.pax-cc-unread-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 6px;border-radius:999px;background:#d63638;color:#fff;font-size:11px;font-weight:700;line-height:1;box-shadow:0 0 0 2px #fff}'
            . '.pax-cc-unread-badge--row{min-width:22px;height:22px;font-size:12px}'
            . '#pax-cc-tab-unread-badge{margin-left:6px;vertical-align:middle}'
        );
    }

    public static function enqueue_cybercrime_admin_assets($hook) {
        unset($hook);
        // Match by menu slug — reliable even when the parent menu title changes the hook suffix.
        if (sanitize_key(wp_unslash($_GET['page'] ?? '')) !== self::MENU_SLUG) {
            return;
        }
        if (sanitize_key(wp_unslash($_GET['tab'] ?? '')) !== 'cybercrime') {
            return;
        }
        if (!class_exists('PAXdesign_Cybercrime_Tickets') || !defined('PAXDESIGN_BOOKING_PLUGIN_URL')) {
            return;
        }

        $reference = sanitize_text_field(wp_unslash($_GET['reference'] ?? ''));
        $view = $reference !== '' ? 'detail' : 'list';
        $initial_sync = null;
        if ($reference !== '' && class_exists('PAXdesign_Cybercrime_Tickets')) {
            $initial_sync = PAXdesign_Cybercrime_Tickets::report_sync_snapshot($reference);
        }

        wp_enqueue_script(
            'paxdesign-cybercrime-admin',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/cybercrime-admin.js',
            array(),
            defined('PAXDESIGN_BOOKING_VERSION') ? PAXDESIGN_BOOKING_VERSION : '1.0',
            true
        );
        wp_localize_script('paxdesign-cybercrime-admin', 'paxCybercrimeAdmin', array(
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce(PAXdesign_Cybercrime_Tickets::ADMIN_NONCE_ACTION),
            'view'      => $view,
            'reference' => $reference,
            'initialSync' => $initial_sync,
            'statusClasses' => array(
                'submitted'             => 'pax-cc-status--submitted',
                'in_review'             => 'pax-cc-status--in_review',
                'waiting_for_customer'  => 'pax-cc-status--waiting_for_customer',
                'resolved'              => 'pax-cc-status--resolved',
                'closed'                => 'pax-cc-status--closed',
                'rejected'              => 'pax-cc-status--rejected',
            ),
            'i18n' => array(
                'statusSaved' => __('Status saved.', 'paxdesign-booking'),
                'replySent'   => __('Reply sent to customer.', 'paxdesign-booking'),
                'requestEvidence' => __('Request customer to upload evidence', 'paxdesign-booking'),
                'requestEvidenceSent' => __('Evidence request sent to customer.', 'paxdesign-booking'),
                'requestEvidenceHint' => __('The customer will see Upload Evidence / رفع الأدلة with your message.', 'paxdesign-booking'),
                'requestEvidenceTag' => __('Evidence requested', 'paxdesign-booking'),
                'deleteMessage' => __('Delete message', 'paxdesign-booking'),
                'deleteConfirm' => __('Permanently delete this message? This cannot be undone.', 'paxdesign-booking'),
                'deleteSuccess' => __('Message deleted.', 'paxdesign-booking'),
                'deleting' => __('Deleting…', 'paxdesign-booking'),
                'labelCustomer' => __('Customer', 'paxdesign-booking'),
                'labelStaff' => __('You / Staff', 'paxdesign-booking'),
                'labelInternal' => __('Internal Note', 'paxdesign-booking'),
                'labelSystem' => __('System', 'paxdesign-booking'),
                'noConversation' => __('No messages yet.', 'paxdesign-booking'),
                'noteAdded'   => __('Internal note added.', 'paxdesign-booking'),
                'saving'      => __('Saving…', 'paxdesign-booking'),
                'sending'     => __('Sending…', 'paxdesign-booking'),
                'addingNote'  => __('Adding note…', 'paxdesign-booking'),
                'error'       => __('Something went wrong. Please try again.', 'paxdesign-booking'),
                'internal'    => __('internal', 'paxdesign-booking'),
                'noTimeline'  => __('No timeline entries yet.', 'paxdesign-booking'),
                'closeTicket' => __('Close ticket', 'paxdesign-booking'),
                'closeConfirm'=> __('Close this ticket? The customer can start a new report.', 'paxdesign-booking'),
                'rejectTicket'=> 'مرفوض',
                'rejectConfirm'=> class_exists('PAXdesign_Cybercrime_I18n')
                    ? PAXdesign_Cybercrime_I18n::t('action.reject_confirm', 'de')
                    : 'Mark this case as مرفوض (Rejected)? The customer will be emailed.',
            ),
        ));
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
            $badge = $slug === 'cybercrime' ? ' <span class="pax-cc-unread-badge" id="pax-cc-tab-unread-badge" hidden aria-label="' . esc_attr__('Unread reports', 'paxdesign-booking') . '"></span>' : '';
            echo '<a class="' . esc_attr(trim($class)) . '" href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=' . $slug)) . '">' . esc_html($label) . $badge . '</a>';
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

        self::render_cybercrime_admin_styles();

        $reference = sanitize_text_field($_GET['reference'] ?? '');
        if ($reference !== '') {
            self::render_cybercrime_detail($reference);
            return;
        }

        $reports = PAXdesign_Cybercrime_Tickets::list_reports_for_admin(50);
        echo '<div class="pax-cc-admin">';
        echo '<div class="pax-cc-admin__header">';
        echo '<h2 class="pax-cc-admin__title">' . esc_html__('Cybercrime Support', 'paxdesign-booking') . '</h2>';
        echo '<p class="pax-cc-admin__subtitle">' . esc_html__('Manage customer reports through a clear workflow: New → In Review → Waiting for Customer → Resolved → Closed / مرفوض.', 'paxdesign-booking') . '</p>';
        echo '</div>';

        echo '<table class="widefat striped pax-cc-admin__table"><thead><tr>';
        echo '<th>' . esc_html__('Reference', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Reporter', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Category', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Status', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Updated', 'paxdesign-booking') . '</th>';
        echo '<th>' . esc_html__('Alerts', 'paxdesign-booking') . '</th>';
        echo '</tr></thead><tbody id="pax-cc-admin-list-body">';
        if (empty($reports)) {
            echo '<tr><td colspan="6">' . esc_html__('No reports yet.', 'paxdesign-booking') . '</td></tr>';
        }
        foreach ($reports as $report) {
            $url = admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=cybercrime&reference=' . rawurlencode((string) $report['reference_id']));
            $status = (string) ($report['status'] ?? '');
            $ref = (string) ($report['reference_id'] ?? '');
            $unread = (int) ($report['unread_count'] ?? 0);
            echo '<tr data-reference="' . esc_attr($ref) . '">';
            echo '<td><a href="' . esc_url($url) . '"><code>' . esc_html($ref) . '</code></a></td>';
            echo '<td>' . esc_html((string) ($report['reporter_name'] ?? '')) . '<br><small>' . esc_html((string) ($report['reporter_email'] ?? '')) . '</small></td>';
            echo '<td>' . esc_html((string) ($report['category_label'] ?? '')) . '</td>';
            $lang = (string) ($report['locale'] ?? 'de');
            $dir = $lang === 'ar' ? 'rtl' : 'ltr';
            echo '<td><span class="' . esc_attr(PAXdesign_Cybercrime_Tickets::admin_status_badge_class($status)) . '" lang="' . esc_attr($lang) . '" dir="' . esc_attr($dir) . '">' . esc_html((string) ($report['status_label'] ?? '')) . '</span></td>';
            echo '<td>' . esc_html((string) ($report['updated_at'] ?? '')) . '</td>';
            echo '<td><span class="pax-cc-unread-badge pax-cc-unread-badge--row" data-unread-for="' . esc_attr($ref) . '"' . ($unread > 0 ? '' : ' hidden') . '>' . esc_html((string) $unread) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    private static function render_cybercrime_admin_styles() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        echo '<style>
        .pax-cc-admin{max-width:1100px;margin-top:12px}
        .pax-cc-admin__header{margin:0 0 20px}
        .pax-cc-admin__title{margin:0 0 6px;font-size:22px;font-weight:600}
        .pax-cc-admin__subtitle{margin:0;color:#646970;font-size:14px;line-height:1.5}
        .pax-cc-admin__back{display:inline-block;margin:0 0 16px;text-decoration:none;font-size:13px}
        .pax-cc-admin__card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;margin:0 0 16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
        .pax-cc-admin__card-title{margin:0 0 12px;font-size:15px;font-weight:600}
        .pax-cc-admin__meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:0 0 4px}
        .pax-cc-admin__meta dt{margin:0 0 2px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#646970}
        .pax-cc-admin__meta dd{margin:0;font-size:14px;font-weight:500}
        .pax-cc-workflow{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0;list-style:none}
        .pax-cc-workflow__step{flex:1 1 140px;min-width:120px;border:1px solid #dcdcde;border-radius:10px;padding:12px;background:#f6f7f7;text-align:center}
        .pax-cc-workflow__step.is-current{border-color:#2271b1;background:#f0f6fc;box-shadow:inset 0 0 0 1px #2271b1}
        .pax-cc-workflow__step.is-done{border-color:#00a32a;background:#edfaef}
        .pax-cc-workflow__num{display:block;font-size:11px;font-weight:700;color:#646970;margin:0 0 4px}
        .pax-cc-workflow__label{display:block;font-size:13px;font-weight:600;color:#1d2327;margin:0 0 2px}
        .pax-cc-workflow__desc{display:block;font-size:11px;color:#646970;line-height:1.35}
        .pax-cc-activity{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 4px}
        .pax-cc-activity__chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:500;background:#fcf0f1;color:#8a2424;border:1px solid #f1aeb5}
        .pax-cc-activity__chip--info{background:#f0f6fc;color:#135e96;border-color:#c5d9ed}
        .pax-cc-admin__grid{display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start}
        @media(max-width:960px){.pax-cc-admin__grid{grid-template-columns:1fr}}
        .pax-cc-attachments{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px}
        .pax-cc-attachment{display:flex;flex-direction:column;align-items:stretch;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;background:#f6f7f7;text-decoration:none;color:inherit;min-height:120px}
        .pax-cc-attachment:hover{border-color:#2271b1;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        .pax-cc-attachment__thumb{display:block;width:100%;aspect-ratio:1;object-fit:cover;background:#fff}
        .pax-cc-attachment__file{display:flex;flex:1;flex-direction:column;align-items:center;justify-content:center;padding:16px 8px;text-align:center;gap:6px}
        .pax-cc-attachment__icon{font-size:28px;line-height:1;opacity:.75}
        .pax-cc-attachment__name{display:block;padding:8px;font-size:11px;line-height:1.35;word-break:break-word;border-top:1px solid #dcdcde;background:#fff}
        .pax-cc-timeline__attachments{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
        .pax-cc-timeline__attachment{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7;font-size:12px;text-decoration:none;color:inherit}
        .pax-cc-timeline__attachment img{width:40px;height:40px;object-fit:cover;border-radius:4px}
        .pax-cc-convo{display:flex;flex-direction:column;gap:14px;margin:0;padding:0;list-style:none}
        .pax-cc-convo__item{display:flex;width:100%;margin:0;padding:0}
        .pax-cc-convo__item--customer{justify-content:flex-start}
        .pax-cc-convo__item--staff{justify-content:flex-end}
        .pax-cc-convo__item--internal,.pax-cc-convo__item--system{justify-content:center}
        .pax-cc-convo__bubble{max-width:min(100%,560px);border-radius:16px;padding:12px 14px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
        .pax-cc-convo__item--customer .pax-cc-convo__bubble{background:#f2f2f7;border:1px solid #e5e5ea;color:#1d1d1f}
        .pax-cc-convo__item--staff .pax-cc-convo__bubble{background:#007aff;border:1px solid #007aff;color:#fff}
        .pax-cc-convo__item--staff .pax-cc-convo__time{color:rgba(255,255,255,.78)}
        .pax-cc-convo__item--staff .pax-cc-convo__body{color:#fff}
        .pax-cc-convo__item--internal .pax-cc-convo__bubble{max-width:min(100%,640px);background:#fafafa;border:1px dashed #c7c7cc;color:#636366}
        .pax-cc-convo__item--system .pax-cc-convo__bubble{max-width:min(100%,640px);background:#f5f5f7;border:1px solid #e5e5ea;color:#636366;padding:10px 14px}
        .pax-cc-convo__head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 8px;flex-wrap:wrap}
        .pax-cc-convo__badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.02em;line-height:1.3}
        .pax-cc-convo__badge--customer{background:#e8e8ed;color:#1d1d1f}
        .pax-cc-convo__badge--staff{background:rgba(255,255,255,.18);color:#fff}
        .pax-cc-convo__badge--internal{background:#ececf1;color:#636366}
        .pax-cc-convo__badge--system{background:#ececf1;color:#636366}
        .pax-cc-convo__time{font-size:11px;color:#86868b;white-space:nowrap}
        .pax-cc-convo__body{font-size:14px;line-height:1.55;word-break:break-word}
        .pax-cc-convo__foot{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid rgba(0,0,0,.08)}
        .pax-cc-convo__item--staff .pax-cc-convo__foot{border-top-color:rgba(255,255,255,.22)}
        .pax-cc-convo__tag{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;line-height:1.3}
        .pax-cc-convo__tag--evidence{background:#fff3cd;color:#856404;border:1px solid #ffeeba}
        .pax-cc-convo__item--staff .pax-cc-convo__tag--evidence{background:rgba(255,255,255,.95);color:#135e96;border-color:transparent}
        .pax-cc-convo__delete{border:0;background:transparent;color:#ff3b30;font-size:12px;font-weight:600;cursor:pointer;padding:0;text-decoration:underline;margin-left:auto}
        .pax-cc-convo__item--staff .pax-cc-convo__delete{color:#ffd6d3}
        .pax-cc-convo__delete:hover{opacity:.85}
        .pax-cc-convo__delete:disabled{opacity:.55;cursor:not-allowed}
        .pax-cc-convo__item--customer .pax-cc-timeline__attachment{background:#fff;border-color:#e5e5ea}
        .pax-cc-convo__item--staff .pax-cc-timeline__attachment{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.24);color:#fff}
        .pax-cc-convo__item--internal .pax-cc-timeline__attachment,.pax-cc-convo__item--system .pax-cc-timeline__attachment{background:#fff}
        .pax-cc-lightbox{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.82);padding:24px}
        .pax-cc-lightbox.is-open{display:flex}
        .pax-cc-lightbox__img{max-width:min(96vw,1200px);max-height:90vh;object-fit:contain;border-radius:6px;box-shadow:0 8px 32px rgba(0,0,0,.35)}
        .pax-cc-lightbox__close{position:absolute;top:16px;right:16px;width:40px;height:40px;border:none;border-radius:999px;background:rgba(255,255,255,.15);color:#fff;font-size:24px;line-height:1;cursor:pointer}
        .pax-cc-lightbox__close:hover{background:rgba(255,255,255,.28)}
        .pax-cc-status{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.3}
        .pax-cc-status--submitted{background:#f0f0f1;color:#50575e}
        .pax-cc-status--in_review{background:#f0f6fc;color:#135e96}
        .pax-cc-status--waiting_for_customer{background:#fcf9e8;color:#9d6e00}
        .pax-cc-status--resolved{background:#edfaef;color:#007017}
        .pax-cc-status--closed{background:#f6f7f7;color:#646970}
        .pax-cc-status--rejected{background:#fcf0f1;color:#8a2424}
        .pax-cc-reject-btn{background:#8a2424 !important;border-color:#8a2424 !important;color:#fff !important}
        .pax-cc-form .large-text{width:100%}
        .pax-cc-form p{margin:0 0 12px}
        .pax-cc-form label{font-weight:500;font-size:13px}
        .pax-cc-form select,.pax-cc-form textarea{width:100%;max-width:100%}
        .pax-cc-form__hint{margin:4px 0 0;font-size:12px;color:#646970}
        .pax-cc-actions{display:flex;flex-direction:column;gap:16px}
        .pax-cc-actions__section{padding-top:16px;border-top:1px solid #e0e0e0}
        .pax-cc-actions__section:first-child{padding-top:0;border-top:0}
        .pax-cc-actions__section-title{margin:0 0 10px;font-size:13px;font-weight:600;color:#1d2327}
        .pax-cc-actions__section--internal .pax-cc-actions__section-title{color:#646970}
        .pax-cc-actions__status-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .pax-cc-actions__status-row select{flex:1 1 180px;max-width:100%}
        .pax-cc-actions__feedback{font-size:12px;color:#646970;min-height:18px}
        .pax-cc-actions__feedback.is-success{color:#007017}
        .pax-cc-actions__feedback.is-error{color:#b32d2e}
        .pax-cc-actions__feedback.is-saving{color:#135e96}
        .pax-cc-actions__reply-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0}
        .pax-cc-evidence-toggle{margin:0 0 12px;padding:12px 14px;border:1px solid #c3c4c7;border-radius:6px;background:#f6f7f7}
        .pax-cc-evidence-toggle__label{display:flex;align-items:flex-start;gap:10px;margin:0;font-weight:600;font-size:14px;line-height:1.45;color:#1d2327;cursor:pointer}
        .pax-cc-evidence-toggle__label input[type=checkbox]{margin:3px 0 0;flex:0 0 auto;width:18px;height:18px;cursor:pointer}
        .pax-cc-evidence-toggle__hint{display:block;margin-top:4px;font-weight:400;font-size:12px;color:#646970}
        .pax-cc-unread-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 6px;border-radius:999px;background:#d63638;color:#fff;font-size:11px;font-weight:700;line-height:1;box-shadow:0 0 0 2px #fff}
        .pax-cc-unread-badge--row{min-width:22px;height:22px;font-size:12px}
        #pax-cc-tab-unread-badge{margin-left:6px;vertical-align:middle}
        </style>';
    }

    private static function render_cybercrime_workflow($current_status) {
        echo '<ol class="pax-cc-workflow">';
        self::render_cybercrime_workflow_steps($current_status);
        echo '</ol>';
    }

    private static function render_cybercrime_workflow_steps($current_status, $lang = '') {
        $steps = PAXdesign_Cybercrime_Tickets::workflow_steps($lang);
        $order = PAXdesign_Cybercrime_Tickets::workflow_statuses();
        $current = PAXdesign_Cybercrime_Tickets::normalize_workflow_status($current_status);
        $current_index = array_search($current, $order, true);
        if ($current_index === false) {
            $current_index = 0;
        }
        $dir = $lang === 'ar' ? 'rtl' : 'ltr';

        foreach ($order as $index => $slug) {
            if (!isset($steps[$slug])) {
                continue;
            }
            $classes = array('pax-cc-workflow__step');
            if ($slug === $current) {
                $classes[] = 'is-current';
            } elseif ($index < $current_index) {
                $classes[] = 'is-done';
            }
            echo '<li class="' . esc_attr(implode(' ', $classes)) . '" dir="' . esc_attr($dir) . '">';
            echo '<span class="pax-cc-workflow__num">' . esc_html(sprintf(__('Step %d', 'paxdesign-booking'), $index + 1)) . '</span>';
            echo '<span class="pax-cc-workflow__label">' . esc_html($steps[$slug]['label']) . '</span>';
            echo '<span class="pax-cc-workflow__desc">' . esc_html($steps[$slug]['description']) . '</span>';
            echo '</li>';
        }
    }

    private static function render_cybercrime_detail($reference) {
        $row = PAXdesign_Cybercrime_Tickets::get_report_row($reference);
        if (!$row) {
            echo '<p>' . esc_html__('Report not found.', 'paxdesign-booking') . '</p>';
            return;
        }

        PAXdesign_Cybercrime_Tickets::mark_read_for_audience($reference, 'staff', get_current_user_id());
        PAXdesign_Cybercrime_Tickets::sync_report_attachments_column($reference);

        $row = PAXdesign_Cybercrime_Tickets::get_report_row($reference);
        if (!$row) {
            echo '<p>' . esc_html__('Report not found.', 'paxdesign-booking') . '</p>';
            return;
        }

        $report = PAXdesign_Cybercrime_Tickets::format_report_row($row, true, 'admin', 'staff');
        $status = (string) ($report['status'] ?? '');
        $lang = (string) ($report['locale'] ?? 'de');
        $dir = $lang === 'ar' ? 'rtl' : 'ltr';
        $back_url = admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=cybercrime');

        echo '<div class="pax-cc-admin">';
        echo '<a class="pax-cc-admin__back" href="' . esc_url($back_url) . '">&larr; ' . esc_html__('All reports', 'paxdesign-booking') . '</a>';

        echo '<div class="pax-cc-admin__card">';
        echo '<h2 class="pax-cc-admin__title"><code>' . esc_html((string) $report['reference_id']) . '</code></h2>';
        echo '<dl class="pax-cc-admin__meta">';
        echo '<div><dt>' . esc_html__('Reporter', 'paxdesign-booking') . '</dt><dd>' . esc_html((string) $report['reporter_name']) . '<br><small>' . esc_html((string) $report['reporter_email']) . '</small></dd></div>';
        echo '<div><dt>' . esc_html__('Category', 'paxdesign-booking') . '</dt><dd>' . esc_html((string) $report['category_label']) . '</dd></div>';
        echo '<div><dt>' . esc_html__('Status', 'paxdesign-booking') . '</dt><dd><span id="pax-cc-admin-status-badge" class="' . esc_attr(PAXdesign_Cybercrime_Tickets::admin_status_badge_class($status)) . '" lang="' . esc_attr($lang) . '" dir="' . esc_attr($dir) . '">' . esc_html((string) $report['status_label']) . '</span></dd></div>';
        echo '<div><dt>' . esc_html__('Submitted', 'paxdesign-booking') . '</dt><dd>' . esc_html((string) $report['created_at']) . '</dd></div>';
        echo '<div><dt>' . esc_html__('Updated', 'paxdesign-booking') . '</dt><dd>' . esc_html((string) $report['updated_at']) . '</dd></div>';
        echo '</dl>';
        echo '</div>';

        echo '<div class="pax-cc-admin__card" id="pax-cc-admin-workflow-card">';
        echo '<h3 class="pax-cc-admin__card-title">' . esc_html__('Workflow', 'paxdesign-booking') . '</h3>';
        echo '<ol class="pax-cc-workflow" id="pax-cc-admin-workflow">';
        self::render_cybercrime_workflow_steps($status, $lang);
        echo '</ol></div>';

        $indicators = (array) ($report['activity_indicators'] ?? array());
        if (!empty($indicators)) {
            echo '<div class="pax-cc-admin__card">';
            echo '<h3 class="pax-cc-admin__card-title">' . esc_html__('Internal activity', 'paxdesign-booking') . '</h3>';
            echo '<div class="pax-cc-activity">';
            foreach ($indicators as $indicator) {
                if (!is_array($indicator)) {
                    continue;
                }
                $class = 'pax-cc-activity__chip';
                if (($indicator['key'] ?? '') === 'latest_customer_reply') {
                    $class .= ' pax-cc-activity__chip--info';
                }
                echo '<span class="' . esc_attr($class) . '">' . esc_html((string) ($indicator['label'] ?? ''));
                if (!empty($indicator['at'])) {
                    echo ' · ' . esc_html((string) $indicator['at']);
                }
                echo '</span>';
            }
            echo '</div>';
            echo '<p class="pax-cc-form__hint">' . esc_html__('These indicators are for staff only — customers see simplified status badges.', 'paxdesign-booking') . '</p>';
            echo '</div>';
        }

        if (!empty($report['description'])) {
            echo '<div class="pax-cc-admin__card">';
            echo '<h3 class="pax-cc-admin__card-title">' . esc_html__('Report summary', 'paxdesign-booking') . '</h3>';
            echo '<p style="margin:0;max-width:720px;line-height:1.55">' . nl2br(esc_html((string) $report['description'])) . '</p>';
            echo '</div>';
        }

        $attachment_items = (!empty($report['attachments']) && is_array($report['attachments']))
            ? $report['attachments']
            : array();
        echo '<div class="pax-cc-admin__card" id="pax-cc-admin-attachments-card"' . (empty($attachment_items) ? ' hidden' : '') . '>';
        echo '<h3 class="pax-cc-admin__card-title">' . esc_html__('Attachments', 'paxdesign-booking') . '</h3>';
        echo '<div class="pax-cc-attachments" id="pax-cc-admin-attachments">';
        if (!empty($attachment_items)) {
            self::render_cybercrime_attachment_gallery($attachment_items);
        }
        echo '</div></div>';

        echo '<div class="pax-cc-admin__grid">';

        echo '<div class="pax-cc-admin__card">';
        echo '<h3 class="pax-cc-admin__card-title">' . esc_html__('Conversation', 'paxdesign-booking') . '</h3>';
        echo '<ul class="pax-cc-convo" id="pax-cc-admin-timeline">';
        $timeline = (array) ($report['timeline'] ?? array());
        if (empty($timeline)) {
            echo '<li class="pax-cc-convo__item pax-cc-convo__item--system"><div class="pax-cc-convo__bubble"><div class="pax-cc-convo__body">' . esc_html__('No messages yet.', 'paxdesign-booking') . '</div></div></li>';
        }
        foreach ($timeline as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            self::render_cybercrime_timeline_item($entry);
        }
        echo '</ul></div>';

        echo '<div class="pax-cc-admin__card pax-cc-actions" id="pax-cc-ticket-actions" data-reference="' . esc_attr((string) $report['reference_id']) . '" data-lang="' . esc_attr($lang) . '">';
        echo '<h3 class="pax-cc-admin__card-title">' . esc_html__('Ticket actions', 'paxdesign-booking') . '</h3>';

        echo '<div class="pax-cc-actions__section">';
        echo '<p class="pax-cc-actions__section-title">' . esc_html__('Workflow status', 'paxdesign-booking') . '</p>';
        echo '<div class="pax-cc-actions__status-row">';
        echo '<select id="pax-cc-status" aria-label="' . esc_attr__('Workflow status', 'paxdesign-booking') . '">';
        foreach (PAXdesign_Cybercrime_Tickets::workflow_statuses() as $workflow_status) {
            echo '<option value="' . esc_attr($workflow_status) . '"' . selected($status, $workflow_status, false) . '>' . esc_html(PAXdesign_Cybercrime_Tickets::status_label($workflow_status, $lang)) . '</option>';
        }
        echo '</select>';
        echo '<button type="button" class="button" id="pax-cc-close-ticket"' . (PAXdesign_Cybercrime_Tickets::is_active_status($status) ? '' : ' hidden') . '>' . esc_html__('Close ticket', 'paxdesign-booking') . '</button>';
        echo '<button type="button" class="button pax-cc-reject-btn" id="pax-cc-reject-ticket" lang="ar" dir="rtl"' . (PAXdesign_Cybercrime_Tickets::is_active_status($status) ? '' : ' hidden') . '>مرفوض</button>';
        echo '</div>';
        echo '<p class="pax-cc-actions__feedback" id="pax-cc-status-feedback" aria-live="polite"></p>';
        echo '<p class="pax-cc-form__hint">' . esc_html__('Changes save automatically. Closing a ticket lets the customer start a new report.', 'paxdesign-booking') . '</p>';
        echo '</div>';

        $reply_section_hidden = !PAXdesign_Cybercrime_Tickets::is_active_status($status);
        echo '<div class="pax-cc-actions__section"' . ($reply_section_hidden ? ' hidden' : '') . ' id="pax-cc-reply-section">';
        echo '<p class="pax-cc-actions__section-title">' . esc_html__('Reply to customer', 'paxdesign-booking') . '</p>';
        echo '<form id="pax-cc-reply-form" class="pax-cc-form" onsubmit="return false;">';
        echo '<p><label for="pax-cc-staff-message">' . esc_html__('Message', 'paxdesign-booking') . '</label><br>';
        echo '<textarea id="pax-cc-staff-message" rows="5" class="large-text" required></textarea></p>';
        echo '<p><label for="pax-cc-reply-status">' . esc_html__('After reply, set status to', 'paxdesign-booking') . '</label><br>';
        echo '<select id="pax-cc-reply-status">';
        echo '<option value="waiting_for_customer"' . selected($status, 'waiting_for_customer', false) . '>' . esc_html(PAXdesign_Cybercrime_Tickets::status_label('waiting_for_customer', $lang)) . '</option>';
        echo '<option value="in_review"' . selected($status, 'in_review', false) . '>' . esc_html(PAXdesign_Cybercrime_Tickets::status_label('in_review', $lang)) . '</option>';
        echo '<option value="resolved">' . esc_html(PAXdesign_Cybercrime_Tickets::status_label('resolved', $lang)) . '</option>';
        echo '<option value="closed">' . esc_html(PAXdesign_Cybercrime_Tickets::status_label('closed', $lang)) . '</option>';
        echo '<option value="rejected">' . esc_html(PAXdesign_Cybercrime_Tickets::status_label('rejected', $lang)) . '</option>';
        echo '</select>';
        echo '<span class="pax-cc-form__hint">' . esc_html__('The reply appears on the customer timeline immediately.', 'paxdesign-booking') . '</span></p>';
        echo '<p class="pax-cc-evidence-toggle">';
        echo '<label class="pax-cc-evidence-toggle__label" for="pax-cc-request-evidence">';
        echo '<input type="checkbox" id="pax-cc-request-evidence" name="request_evidence" value="1">';
        echo '<span>' . esc_html__('Request customer to upload evidence', 'paxdesign-booking');
        echo '<span class="pax-cc-evidence-toggle__hint">' . esc_html__('Shows Upload Evidence / رفع الأدلة with your message and sets status to Waiting for Customer.', 'paxdesign-booking') . '</span>';
        echo '</span></label></p>';
        echo '<p class="pax-cc-actions__reply-row">';
        echo '<button type="submit" class="button button-primary" id="pax-cc-reply-submit">' . esc_html__('Send reply', 'paxdesign-booking') . '</button>';
        echo '</p>';
        echo '<p class="pax-cc-actions__feedback" id="pax-cc-reply-feedback" aria-live="polite"></p>';
        echo '</form></div>';

        echo '<div class="pax-cc-actions__section pax-cc-actions__section--internal">';
        echo '<p class="pax-cc-actions__section-title">' . esc_html__('Internal note (staff only)', 'paxdesign-booking') . '</p>';
        echo '<form id="pax-cc-internal-note-form" class="pax-cc-form" onsubmit="return false;">';
        echo '<p><label for="pax-cc-internal-note">' . esc_html__('Note', 'paxdesign-booking') . '</label><br>';
        echo '<textarea id="pax-cc-internal-note" rows="3" class="large-text" required></textarea>';
        echo '<span class="pax-cc-form__hint">' . esc_html__('Not visible to the customer. Use for staff coordination only.', 'paxdesign-booking') . '</span></p>';
        echo '<p><button type="submit" class="button" id="pax-cc-internal-note-submit">' . esc_html__('Add internal note', 'paxdesign-booking') . '</button></p>';
        echo '<p class="pax-cc-actions__feedback" id="pax-cc-internal-note-feedback" aria-live="polite"></p>';
        echo '</form></div>';

        echo '</div></div></div>';
        echo '<div class="pax-cc-lightbox" id="pax-cc-lightbox" hidden aria-hidden="true"><button type="button" class="pax-cc-lightbox__close" id="pax-cc-lightbox-close" aria-label="' . esc_attr__('Close', 'paxdesign-booking') . '">&times;</button><img class="pax-cc-lightbox__img" id="pax-cc-lightbox-img" alt=""></div>';
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     */
    private static function render_cybercrime_attachment_gallery($attachments) {
        foreach ($attachments as $file) {
            if (!is_array($file)) {
                continue;
            }
            $name = sanitize_file_name((string) ($file['name'] ?? 'file'));
            $url = esc_url((string) ($file['url'] ?? ''));
            $is_image = !empty($file['is_image']) || PAXdesign_Cybercrime_Intake::is_image_mime((string) ($file['type'] ?? ''));
            if ($url === '') {
                echo '<div class="pax-cc-attachment"><span class="pax-cc-attachment__name">' . esc_html($name) . '</span></div>';
                continue;
            }
            if ($is_image) {
                echo '<a class="pax-cc-attachment pax-cc-attachment--image" href="' . $url . '" data-pax-cc-lightbox target="_blank" rel="noopener">';
                echo '<img class="pax-cc-attachment__thumb" src="' . $url . '" alt="' . esc_attr($name) . '" loading="lazy">';
                echo '<span class="pax-cc-attachment__name">' . esc_html($name) . '</span></a>';
            } else {
                echo '<a class="pax-cc-attachment pax-cc-attachment--file" href="' . $url . '" target="_blank" rel="noopener">';
                echo '<span class="pax-cc-attachment__file"><span class="pax-cc-attachment__icon" aria-hidden="true">📄</span></span>';
                echo '<span class="pax-cc-attachment__name">' . esc_html($name) . '</span></a>';
            }
        }
    }

    private static function render_cybercrime_timeline_item($entry) {
        $kind = (string) ($entry['timeline_kind'] ?? PAXdesign_Cybercrime_Tickets::admin_timeline_kind($entry));
        $label = (string) ($entry['timeline_label'] ?? PAXdesign_Cybercrime_Tickets::admin_timeline_label($kind));
        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : array();
        $message_id = (int) ($entry['id'] ?? 0);
        $allow_delete = !empty($entry['allow_delete']) || !empty($entry['can_delete']);
        if (!$allow_delete) {
            $allow_delete = PAXdesign_Cybercrime_Tickets::is_deletable_staff_message($entry);
        }
        $has_evidence = !empty($meta['request_evidence']) || !empty($entry['request_evidence']);

        echo '<li class="pax-cc-convo__item pax-cc-convo__item--' . esc_attr($kind) . '" data-message-id="' . esc_attr((string) $message_id) . '">';
        echo '<div class="pax-cc-convo__bubble">';
        echo '<div class="pax-cc-convo__head">';
        echo '<span class="pax-cc-convo__badge pax-cc-convo__badge--' . esc_attr($kind) . '">' . esc_html($label) . '</span>';
        echo '<time class="pax-cc-convo__time">' . esc_html((string) ($entry['created_at'] ?? '')) . '</time>';
        echo '</div>';
        echo '<div class="pax-cc-convo__body">' . nl2br(esc_html((string) ($entry['body'] ?? ''))) . '</div>';
        if (!empty($entry['attachments']) && is_array($entry['attachments'])) {
            echo '<div class="pax-cc-timeline__attachments">';
            foreach ($entry['attachments'] as $file) {
                if (!is_array($file)) {
                    continue;
                }
                $name = sanitize_file_name((string) ($file['name'] ?? 'file'));
                $url = esc_url((string) ($file['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $is_image = !empty($file['is_image']);
                echo '<a class="pax-cc-timeline__attachment" href="' . $url . '"' . ($is_image ? ' data-pax-cc-lightbox' : '') . ' target="_blank" rel="noopener">';
                if ($is_image) {
                    echo '<img src="' . $url . '" alt="' . esc_attr($name) . '" loading="lazy">';
                }
                echo '<span>' . esc_html($name) . '</span></a>';
            }
            echo '</div>';
        }
        if ($has_evidence || ($allow_delete && $message_id > 0)) {
            echo '<div class="pax-cc-convo__foot">';
            if ($has_evidence) {
                echo '<span class="pax-cc-convo__tag pax-cc-convo__tag--evidence">' . esc_html__('Evidence Requested', 'paxdesign-booking') . '</span>';
            }
            if ($allow_delete && $message_id > 0) {
                echo '<button type="button" class="pax-cc-convo__delete" data-message-id="' . esc_attr((string) $message_id) . '">' . esc_html__('Delete message', 'paxdesign-booking') . '</button>';
            }
            echo '</div>';
        }
        echo '</div></li>';
    }

    private static function verify_admin($action) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'paxdesign-booking'));
        }
        check_admin_referer($action);
    }
}
