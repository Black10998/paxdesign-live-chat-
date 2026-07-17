<?php
/**
 * Customer portal REST API — pdx/v1 namespace (booking-native auth + content).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_REST {

    const NS = 'pdx/v1';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'), 20);
    }

    public static function register_routes() {
        $auth = array('permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'));

        register_rest_route(self::NS, '/customer/dashboard', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'dashboard'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/profile', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_profile'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_profile'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
        ));

        register_rest_route(self::NS, '/customer/profile/avatar', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'upload_profile_avatar'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/settings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_settings'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_settings'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
        ));

        register_rest_route(self::NS, '/customer/account/delete', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'delete_account'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/projects', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_projects'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/projects/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_project'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/projects/(?P<id>\d+)/files/(?P<file_id>\d+)/download', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'download_project_file'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/orders', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'list_orders'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'create_order_request'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
        ));

        register_rest_route(self::NS, '/customer/orders/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_order'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/orders/(?P<id>\d+)/files/(?P<file_id>\d+)/download', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'download_order_file'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/files', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_files'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/services', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_services'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/customer/services/(?P<slug>[a-z0-9\-_]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_service'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/navigation', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'content_navigation'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/services-catalog', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'services_catalog'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/portfolio-showcase', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'portfolio_showcase'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/homepage', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'homepage'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/site-menu', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'site_menu'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/about', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'about_page'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/contact', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'contact_page'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/legal/(?P<slug>[a-z0-9\-_]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'legal_page'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/pages/(?P<slug>[a-z0-9\-_\/]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'content_page'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/content/pages', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'content_pages'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/customer/portfolio', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_portfolio'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/customer/portfolio/(?P<slug>[a-z0-9\-_]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_portfolio_item'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/customer/news', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_news'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/news/(?P<slug>[a-z0-9\-_]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_news'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/notifications', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'list_notifications'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'mark_notifications_read'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
        ));

        register_rest_route(self::NS, '/customer/chat/session', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'chat_session'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'chat_renew_session'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
        ));

        register_rest_route(self::NS, '/customer/chat/conversations', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'chat_conversations'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/chat/claim', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'chat_claim'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/chat/messages', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'chat_messages'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'chat_send_message'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
        ));

        register_rest_route(self::NS, '/customer/chat/messages/image', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'chat_send_image'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/chat/messages/voice', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'chat_send_voice'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/chat/messages/file', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'chat_send_file'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/chat/messages/location', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'chat_send_location'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/chat/stream', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'chat_stream'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/chat/typing', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'chat_typing'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/chat/close', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'chat_close'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(self::NS, '/customer/push/register', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'register_push'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));
    }

    public static function dashboard(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        PAXdesign_Customer_Orders::link_bookings_for_user($uid);
        $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        $live = PAXdesign_Chat_Live::get_instance();
        $poll = $live->get_poll_data($session_id, 0, true);
        return rest_ensure_response(array(
            'user'            => PAXdesign_Customer_Auth::user_payload($uid),
            'projects_active' => array_values(array_filter(PAXdesign_Customer_Projects::list_for_user($uid), function ($p) {
                return in_array($p['status'], array('planning', 'in_progress', 'active', 'review'), true);
            })),
            'projects_recent' => array_slice(PAXdesign_Customer_Projects::list_for_user($uid), 0, 5),
            'orders_recent'   => array_slice(PAXdesign_Customer_Orders::list_for_user($uid), 0, 5),
            'notifications'   => PAXdesign_Customer_Notifications::list_for_user($uid, true, 10),
            'unread_count'    => PAXdesign_Customer_Notifications::unread_count($uid),
            'news'            => PAXdesign_Customer_News::list_for_user($uid, 5),
            'portfolio'       => array_slice(PAXdesign_Customer_Portfolio::list_items(6), 0, 6),
            'files_count'     => count(PAXdesign_Customer_Orders::library_for_user($uid, 100)),
            'services_featured' => array_values(array_filter(PAXdesign_Customer_Services::list_services(), function ($s) {
                return !empty($s['featured']);
            })),
            'chat'            => array(
                'session_id'     => $session_id,
                'last_preview'   => isset($poll['last_preview']) ? $poll['last_preview'] : '',
                'handler'        => isset($poll['handler']) ? $poll['handler'] : 'ai',
                'message_count'  => isset($poll['message_count']) ? (int) $poll['message_count'] : 0,
            ),
        ));
    }

    public static function get_profile() {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $user = get_user_by('id', $uid);
        return rest_ensure_response(array(
            'profile' => array(
                'id'           => $uid,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'verified'     => PAXdesign_Customer_Auth::is_email_verified($uid),
                'role'         => PAXdesign_Customer_Auth::resolve_portal_role($user),
                'avatar_url'   => class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::url_for_user($uid) : '',
            ),
        ));
    }

    public static function upload_profile_avatar(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $files = $request->get_file_params();
        $file = isset($files['avatar']) ? $files['avatar'] : (isset($files['file']) ? $files['file'] : null);
        if (!is_array($file)) {
            return new WP_Error('missing_file', __('Avatar image is required.', 'paxdesign-booking'), array('status' => 400));
        }
        $result = PAXdesign_Customer_Avatar::upload_for_user($uid, $file);
        if (is_wp_error($result)) {
            return $result;
        }
        $profile_response = self::get_profile();
        $profile_data = $profile_response->get_data();
        return rest_ensure_response(array(
            'success' => true,
            'profile' => isset($profile_data['profile']) ? $profile_data['profile'] : array(),
        ));
    }

    public static function update_profile(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $display = sanitize_text_field($request->get_param('display_name') ?? '');
        if ($display !== '') {
            wp_update_user(array('ID' => $uid, 'display_name' => $display));
        }
        return self::get_profile();
    }

    public static function get_settings() {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        return rest_ensure_response(array(
            'notifications' => PAXdesign_Customer_Notifications::get_prefs($uid),
        ));
    }

    public static function update_settings(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $prefs = $request->get_param('notifications');
        if (is_array($prefs)) {
            PAXdesign_Customer_Notifications::save_prefs($uid, $prefs);
        }
        return self::get_settings();
    }

    public static function delete_account(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        if (!wp_check_password((string) $request->get_param('password'), get_userdata($uid)->user_pass, $uid)) {
            return new WP_Error('invalid_password', __('Incorrect password.', 'paxdesign-booking'), array('status' => 403));
        }
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        PAXdesign_Customer_Notifications::notify_user($uid, 'security', __('Account scheduled for deletion', 'paxdesign-booking'), '', 'account', (string) $uid, '');
        $deleted = wp_delete_user($uid);
        if (!$deleted) {
            return new WP_Error('delete_failed', __('Account could not be deleted.', 'paxdesign-booking'), array('status' => 500));
        }
        wp_logout();
        return rest_ensure_response(array('success' => true));
    }

    public static function list_projects(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $status = sanitize_key($request->get_param('status') ?? '');
        return rest_ensure_response(array('projects' => PAXdesign_Customer_Projects::list_for_user($uid, $status)));
    }

    public static function get_project(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $project = PAXdesign_Customer_Projects::get_for_user($uid, (int) $request['id']);
        if (!$project) {
            return new WP_Error('not_found', __('Project not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response($project);
    }

    public static function list_orders(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        PAXdesign_Customer_Orders::link_bookings_for_user($uid);
        return rest_ensure_response(array('orders' => PAXdesign_Customer_Orders::list_for_user($uid, sanitize_key($request->get_param('status') ?? ''))));
    }

    public static function get_order(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $order = PAXdesign_Customer_Orders::get_for_user($uid, (int) $request['id']);
        if (!$order) {
            return new WP_Error('not_found', __('Order not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response($order);
    }

    public static function create_order_request(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $result = PAXdesign_Customer_Orders::create_request($uid, $request->get_json_params() ?: $request->get_params());
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function list_services(WP_REST_Request $request) {
        return rest_ensure_response(array(
            'categories' => PAXdesign_Customer_Services::list_categories(),
            'services'   => PAXdesign_Customer_Services::list_services(array(
                'category' => $request->get_param('category'),
                'search'   => $request->get_param('search'),
            )),
        ));
    }

    public static function get_service(WP_REST_Request $request) {
        $service = PAXdesign_Customer_Services::get_by_slug($request['slug']);
        if (!$service) {
            return new WP_Error('not_found', __('Service not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response($service);
    }

    public static function list_portfolio(WP_REST_Request $request) {
        $limit = absint($request->get_param('limit'));
        if ($limit <= 0) {
            $limit = 100;
        }
        $lang = PAXdesign_Customer_Portfolio_Showcase::normalize_language((string) $request->get_param('lang'));
        return rest_ensure_response(array(
            'categories' => PAXdesign_Customer_Portfolio::categories($lang),
            'items'      => PAXdesign_Customer_Portfolio::list_items($limit, (string) $request->get_param('category'), $lang),
            'lang'       => $lang,
        ));
    }

    public static function get_portfolio_item(WP_REST_Request $request) {
        $lang = PAXdesign_Customer_Portfolio_Showcase::normalize_language((string) $request->get_param('lang'));
        $item = PAXdesign_Customer_Portfolio::get_item($request['slug'], $lang);
        if (!$item) {
            return new WP_Error('not_found', __('Portfolio item not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response($item);
    }

    public static function portfolio_showcase(WP_REST_Request $request) {
        $lang = PAXdesign_Customer_Portfolio_Showcase::normalize_language((string) $request->get_param('lang'));
        return rest_ensure_response(PAXdesign_Customer_Portfolio_Showcase::payload($lang));
    }

    public static function content_navigation(WP_REST_Request $request) {
        return rest_ensure_response(PAXdesign_Customer_Content::navigation($request));
    }

    public static function services_catalog(WP_REST_Request $request) {
        $lang = PAXdesign_Customer_Services_Catalog::normalize_language((string) $request->get_param('lang'));
        return rest_ensure_response(PAXdesign_Customer_Services_Catalog::payload($lang));
    }

    public static function homepage(WP_REST_Request $request) {
        $lang = PAXdesign_Customer_Homepage::normalize_language((string) $request->get_param('lang'));
        return rest_ensure_response(PAXdesign_Customer_Homepage::payload($lang));
    }

    public static function site_menu(WP_REST_Request $request) {
        $lang = PAXdesign_Customer_Homepage::normalize_language((string) $request->get_param('lang'));
        return rest_ensure_response(PAXdesign_Customer_Site_Menu::payload($lang));
    }

    public static function about_page(WP_REST_Request $request) {
        $lang = PAXdesign_Customer_About::normalize_language((string) $request->get_param('lang'));
        return rest_ensure_response(PAXdesign_Customer_About::payload($lang));
    }

    public static function contact_page(WP_REST_Request $request) {
        $lang = PAXdesign_Customer_Contact::normalize_language((string) $request->get_param('lang'));
        return rest_ensure_response(PAXdesign_Customer_Contact::payload($lang));
    }

    public static function legal_page(WP_REST_Request $request) {
        $lang = PAXdesign_Customer_Legal::normalize_language((string) $request->get_param('lang'));
        $payload = PAXdesign_Customer_Legal::payload((string) $request['slug'], $lang);
        if (!$payload) {
            return new WP_Error('not_found', __('Legal page not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response($payload);
    }

    public static function content_page(WP_REST_Request $request) {
        $item = PAXdesign_Customer_Content::get_page($request['slug'], $request);
        if (!$item) {
            return new WP_Error('not_found', __('Page not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response($item);
    }

    public static function content_pages(WP_REST_Request $request) {
        $limit = absint($request->get_param('limit'));
        if ($limit <= 0) {
            $limit = 50;
        }
        return rest_ensure_response(array(
            'pages' => PAXdesign_Customer_Content::list_pages(
                sanitize_title((string) $request->get_param('parent')),
                $limit
            ),
        ));
    }

    public static function list_news() {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        return rest_ensure_response(array('items' => PAXdesign_Customer_News::list_for_user($uid)));
    }

    public static function get_news(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $item = PAXdesign_Customer_News::get_published_for_user($request['slug'], $uid);
        if (!$item) {
            return new WP_Error('not_found', __('News item not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response($item);
    }

    public static function list_notifications(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        return rest_ensure_response(array(
            'items'        => PAXdesign_Customer_Notifications::list_for_user($uid, !empty($request['unread']), (int) ($request['limit'] ?? 50)),
            'unread_count' => PAXdesign_Customer_Notifications::unread_count($uid),
        ));
    }

    public static function mark_notifications_read(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $ids = $request->get_param('ids');
        if (!is_array($ids)) {
            $ids = array($request->get_param('id'));
        }
        foreach ($ids as $id) {
            PAXdesign_Customer_Notifications::mark_read($uid, (int) $id);
        }
        return rest_ensure_response(array('success' => true));
    }

    public static function chat_session() {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        $handler = PAXdesign_Chat_Live::get_instance()->get_handler($session_id);
        return rest_ensure_response(array(
            'session_id' => $session_id,
            'handler'    => $handler,
        ));
    }

    public static function chat_renew_session(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $params = $request->get_json_params() ?: $request->get_params();
        $closed_session_id = sanitize_text_field($params['session_id'] ?? '');
        if ($closed_session_id === '') {
            $closed_session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        }
        if (!PAXdesign_Customer_Chat_Bridge::user_owns_session($uid, $closed_session_id)) {
            return new WP_Error('forbidden', __('You do not have access to this conversation.', 'paxdesign-booking'), array('status' => 403));
        }
        $session_id = PAXdesign_Customer_Chat_Bridge::renew_closed_session($uid, $closed_session_id);
        PAXdesign_Chat_Live::get_instance()->ensure_session($session_id);
        return rest_ensure_response(array(
            'session_id' => $session_id,
            'handler'    => PAXdesign_Chat_Live::get_instance()->get_handler($session_id),
            'renewed'    => true,
        ));
    }

    public static function chat_conversations() {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        return rest_ensure_response(array('conversations' => PAXdesign_Customer_Chat_Bridge::list_user_sessions($uid)));
    }

    public static function chat_claim(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $params = $request->get_json_params() ?: $request->get_params();
        $result = PAXdesign_Customer_Chat_Bridge::claim_guest_session(
            $uid,
            (string) ($params['session_id'] ?? ''),
            (string) ($params['device_token'] ?? '')
        );
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array('success' => true));
    }

    public static function chat_messages(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $session_id = sanitize_text_field($request->get_param('session_id') ?? '');
        if ($session_id === '') {
            $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        }
        if (!PAXdesign_Customer_Chat_Bridge::user_owns_session($uid, $session_id)) {
            return new WP_Error('forbidden', __('You do not have access to this conversation.', 'paxdesign-booking'), array('status' => 403));
        }
        $since = max(0, (int) $request->get_param('since'));
        $full = !empty($request['full']);
        $live = PAXdesign_Chat_Live::get_instance();
        $handler = $live->get_handler($session_id);
        if ($handler === PAXdesign_Chat_Live::HANDLER_CLOSED && $full) {
            $data = $live->get_poll_data($session_id, $since, $full, 'user');
            if (is_wp_error($data)) {
                return $data;
            }
            $data['handler'] = PAXdesign_Chat_Live::HANDLER_CLOSED;
            $data['notice'] = __('This conversation was closed due to inactivity. Send a new message to continue.', 'paxdesign-booking');
            return rest_ensure_response($data);
        }
        $data = $live->get_poll_data($session_id, $since, $full, 'user');
        if (is_wp_error($data)) {
            if ($data->get_error_code() === 'not_found') {
                $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
                PAXdesign_Chat_Live::get_instance()->ensure_session($session_id);
                $data = $live->get_poll_data($session_id, $since, $full, 'user');
            }
        }
        if (is_wp_error($data)) {
            return $data;
        }
        $data['session_id'] = $session_id;
        if (!isset($data['other_read_seq']) && isset($data['admin_read_seq'])) {
            $data['other_read_seq'] = (int) $data['admin_read_seq'];
        }
        return rest_ensure_response($data);
    }

    public static function chat_send_message(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $params = $request->get_json_params() ?: $request->get_params();
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        if ($session_id === '') {
            $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        }
        $result = PAXdesign_Customer_Chat_Bridge::send_user_message(
            $uid,
            $session_id,
            (string) ($params['message'] ?? ''),
            array(
                'reply_to'               => $params['reply_to'] ?? 0,
                'client_msg_id'          => $params['client_msg_id'] ?? '',
                'assistant_client_msg_id'=> $params['assistant_client_msg_id'] ?? '',
            )
        );
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function chat_stream(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $params = $request->get_json_params() ?: $request->get_params();
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        if ($session_id === '') {
            $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        }
        if (!PAXdesign_Customer_Chat_Bridge::user_owns_session($uid, $session_id)) {
            return new WP_Error('forbidden', __('You do not have access to this conversation.', 'paxdesign-booking'), array('status' => 403));
        }

        PAXdesign_Customer_Chat_Bridge::sync_chat_log_user($session_id, $uid);

        $result = PAXdesign_Chat::get_instance()->stream_authenticated_customer_chat(
            $session_id,
            (string) ($params['message'] ?? ''),
            (string) ($params['client_msg_id'] ?? ''),
            (string) ($params['assistant_client_msg_id'] ?? '')
        );
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array('success' => true));
    }

    public static function chat_typing(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $params = $request->get_json_params() ?: $request->get_params();
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        if ($session_id === '') {
            $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        }
        $stop = !empty($params['stop']);
        $result = PAXdesign_Customer_Chat_Bridge::set_typing($uid, $session_id, $stop);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function chat_close(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $params = $request->get_json_params() ?: $request->get_params();
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        if ($session_id === '') {
            $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        }
        $result = PAXdesign_Customer_Chat_Bridge::close_session($uid, $session_id);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function register_push(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $token = sanitize_text_field($request->get_param('token') ?? '');
        $device_id = sanitize_text_field($request->get_param('device_id') ?? '');
        if ($token === '' || $device_id === '') {
            return new WP_Error('invalid_device', __('Device token and device ID are required.', 'paxdesign-booking'), array('status' => 400));
        }
        $devices = get_user_meta($uid, PAXdesign_Customer_Notifications::USER_META_DEVICES, true);
        if (!is_array($devices)) {
            $devices = array();
        }
        $devices['did_' . $device_id] = array(
            'device_id'   => $device_id,
            'token'       => $token,
            'platform'    => sanitize_text_field($request->get_param('platform') ?? 'ios'),
            'sandbox'     => rest_sanitize_boolean($request->get_param('sandbox')),
            'updated_at'  => gmdate('c'),
            'revoked'     => false,
        );
        update_user_meta($uid, PAXdesign_Customer_Notifications::USER_META_DEVICES, $devices);
        return rest_ensure_response(array('success' => true));
    }

    public static function download_project_file(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $file = PAXdesign_Customer_Projects::get_file_for_user($uid, (int) $request['id'], (int) $request['file_id']);
        if (!$file || empty($file['file_path']) || !file_exists($file['file_path'])) {
            return new WP_Error('not_found', __('File not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $mime = !empty($file['mime_type']) ? $file['mime_type'] : 'application/octet-stream';
        $name = !empty($file['file_name']) ? $file['file_name'] : basename($file['file_path']);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        header('Content-Length: ' . (string) filesize($file['file_path']));
        readfile($file['file_path']);
        exit;
    }

    public static function download_order_file(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $file = PAXdesign_Customer_Orders::get_file_for_user($uid, (int) $request['id'], (int) $request['file_id']);
        if (!$file || empty($file['file_path']) || !file_exists($file['file_path'])) {
            return new WP_Error('not_found', __('File not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $mime = !empty($file['mime_type']) ? $file['mime_type'] : 'application/octet-stream';
        $name = !empty($file['file_name']) ? $file['file_name'] : basename($file['file_path']);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        header('Content-Length: ' . (string) filesize($file['file_path']));
        readfile($file['file_path']);
        exit;
    }

    public static function list_files(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $limit = absint($request->get_param('limit'));
        if ($limit <= 0) {
            $limit = 50;
        }
        return rest_ensure_response(array(
            'files' => PAXdesign_Customer_Orders::library_for_user($uid, $limit),
        ));
    }

    public static function chat_send_image(WP_REST_Request $request) {
        return self::chat_send_media($request, 'image', 'image');
    }

    public static function chat_send_voice(WP_REST_Request $request) {
        return self::chat_send_media($request, 'voice', 'audio');
    }

    public static function chat_send_file(WP_REST_Request $request) {
        return self::chat_send_media($request, 'file', 'file');
    }

    public static function chat_send_location(WP_REST_Request $request) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $params = $request->get_json_params() ?: $request->get_params();
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        if ($session_id === '') {
            $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        }
        $result = PAXdesign_Customer_Chat_Bridge::send_user_attachment(
            $uid,
            $session_id,
            'location',
            array(),
            sanitize_text_field($params['label'] ?? ''),
            array(
                'lat'           => $params['lat'] ?? 0,
                'lng'           => $params['lng'] ?? 0,
                'label'         => $params['label'] ?? '',
                'client_msg_id' => $params['client_msg_id'] ?? '',
            )
        );
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    private static function chat_send_media(WP_REST_Request $request, $kind, $field) {
        $uid = PAXdesign_Customer_Auth::current_user_id();
        $files = $request->get_file_params();
        $file = isset($files[$field]) ? $files[$field] : (isset($files['file']) ? $files['file'] : null);
        if (!$file) {
            return new WP_Error('missing_file', __('Attachment is required.', 'paxdesign-booking'), array('status' => 400));
        }
        $params = $request->get_params();
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        if ($session_id === '') {
            $session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
        }
        $result = PAXdesign_Customer_Chat_Bridge::send_user_attachment(
            $uid,
            $session_id,
            $kind,
            $file,
            sanitize_textarea_field($params['caption'] ?? $params['message'] ?? ''),
            array(
                'client_msg_id' => $params['client_msg_id'] ?? '',
                'duration'      => $params['duration'] ?? 0,
            )
        );
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }
}
