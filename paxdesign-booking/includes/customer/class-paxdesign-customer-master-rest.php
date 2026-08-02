<?php
/**
 * Master administrator REST API for in-account customer management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Master_REST {

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'), 25);
    }

    public static function register_routes() {
        $guard = array('permission_callback' => array('PAXdesign_Customer_Master_Admin', 'require_master_admin'));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/customers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_customers'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/customers/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_customer'),
                'permission_callback' => $guard['permission_callback'],
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_customer'),
                'permission_callback' => $guard['permission_callback'],
            ),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/levels', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_levels'),
            'permission_callback' => $guard['permission_callback'],
        ));
    }

    public static function list_customers(WP_REST_Request $request) {
        $search = sanitize_text_field((string) $request->get_param('search'));
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(50, max(1, (int) ($request->get_param('per_page') ?: 25)));
        $offset = ($page - 1) * $per_page;

        $args = array(
            'number'         => $per_page,
            'offset'         => $offset,
            'orderby'        => 'registered',
            'order'          => 'DESC',
            'role__not_in'   => array('administrator'),
            'search_columns' => array('user_login', 'user_email', 'display_name'),
        );
        if ($search !== '') {
            $args['search'] = '*' . esc_attr($search) . '*';
        }

        $query = new WP_User_Query($args);
        $users = $query->get_results();
        $items = array();
        foreach ($users as $user) {
            if ($user instanceof WP_User) {
                $items[] = self::customer_summary($user->ID);
            }
        }

        return rest_ensure_response(array(
            'customers' => $items,
            'total'     => (int) $query->get_total(),
            'page'      => $page,
            'per_page'  => $per_page,
        ));
    }

    public static function get_customer(WP_REST_Request $request) {
        $user_id = absint($request->get_param('id'));
        if ($user_id <= 0 || !self::is_manageable_customer($user_id)) {
            return new WP_Error('invalid_customer', __('Customer not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response(array('customer' => self::customer_detail($user_id)));
    }

    public static function update_customer(WP_REST_Request $request) {
        $user_id = absint($request->get_param('id'));
        if ($user_id <= 0 || !self::is_manageable_customer($user_id)) {
            return new WP_Error('invalid_customer', __('Customer not found.', 'paxdesign-booking'), array('status' => 404));
        }
        if (PAXdesign_Customer_Master_Admin::is_master_admin($user_id)) {
            return new WP_Error('invalid_customer', __('Cannot modify the master administrator account through customer management.', 'paxdesign-booking'), array('status' => 400));
        }

        $display_name = sanitize_text_field((string) $request->get_param('display_name'));
        if ($display_name !== '') {
            wp_update_user(array('ID' => $user_id, 'display_name' => $display_name));
        }

        $email = sanitize_email((string) $request->get_param('email'));
        if ($email !== '' && is_email($email)) {
            $existing = email_exists($email);
            if ($existing && (int) $existing !== $user_id) {
                return new WP_Error('email_exists', __('That email is already in use.', 'paxdesign-booking'), array('status' => 400));
            }
            wp_update_user(array('ID' => $user_id, 'user_email' => $email));
        }

        if ($request->has_param('account_status') && class_exists('PDX_Customers')) {
            $status = sanitize_key((string) $request->get_param('account_status'));
            if ($status === PDX_Customers::STATUS_ACTIVE) {
                PDX_Customers::activate($user_id);
            } elseif ($status === PDX_Customers::STATUS_SUSPENDED) {
                PDX_Customers::suspend($user_id);
            } elseif ($status === PDX_Customers::STATUS_PENDING) {
                PDX_Customers::set_account_status($user_id, PDX_Customers::STATUS_PENDING);
            }
        }

        if ($request->has_param('customer_level')) {
            $level = PAXdesign_Customer_Levels::sanitize_level((int) $request->get_param('customer_level'));
            if ($level > 0) {
                PAXdesign_Customer_Levels::set_level_for_user($user_id, $level);
            } else {
                PAXdesign_Customer_Levels::clear_level_for_user($user_id);
            }
        }

        if ($request->has_param('vip_avatar_id') && class_exists('PAXdesign_Customer_Avatar')) {
            $avatar_id = PAXdesign_Customer_Avatar::sanitize_preset_id((string) $request->get_param('vip_avatar_id'));
            if ($avatar_id === '') {
                // no-op
            } elseif (PAXdesign_Customer_Avatar_Vip_Presets::is_vip($avatar_id)) {
                PAXdesign_Customer_Avatar::grant_vip_avatar($user_id, $avatar_id, true);
            } elseif (PAXdesign_Customer_Avatar_Presets::exists($avatar_id)) {
                PAXdesign_Customer_Avatar::set_preset_for_user($user_id, $avatar_id);
            }
        }

        if ($request->has_param('revoke_vip_avatar_id') && class_exists('PAXdesign_Customer_Avatar')) {
            $revoke_id = PAXdesign_Customer_Avatar_Vip_Presets::sanitize_id((string) $request->get_param('revoke_vip_avatar_id'));
            if ($revoke_id !== '') {
                PAXdesign_Customer_Avatar::revoke_vip_avatar($user_id, $revoke_id);
            }
        }

        if ($request->has_param('remove_avatar_upload') && class_exists('PAXdesign_Customer_Avatar')) {
            $remove = rest_sanitize_boolean($request->get_param('remove_avatar_upload'));
            if ($remove) {
                PAXdesign_Customer_Avatar::remove_upload_for_user($user_id);
            }
        }

        if ($request->has_param('admin_notes') && class_exists('PDX_Customers')) {
            PDX_Customers::save_notes($user_id, (string) $request->get_param('admin_notes'));
        }

        return rest_ensure_response(array(
            'success'  => true,
            'customer' => self::customer_detail($user_id),
        ));
    }

    public static function list_levels() {
        return rest_ensure_response(array(
            'levels'      => PAXdesign_Customer_Levels::catalog(),
            'vip_presets' => PAXdesign_Customer_Avatar_Vip_Presets::catalog_preview(),
            'standard_presets' => class_exists('PAXdesign_Customer_Avatar_Presets')
                ? PAXdesign_Customer_Avatar_Presets::catalog()
                : array(),
        ));
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>
     */
    private static function customer_summary($user_id) {
        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            return array();
        }
        $level = PAXdesign_Customer_Levels::profile_fields($user_id);
        $avatar = class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::profile_fields($user_id) : array();
        return array_merge(
            array(
                'id'                 => $user_id,
                'display_name'       => $user->display_name,
                'email'              => $user->user_email,
                'registered'         => $user->user_registered,
                'verified'           => class_exists('PAXdesign_Auth') ? PAXdesign_Auth::is_email_verified($user_id) : false,
                'account_status'     => class_exists('PDX_Customers') ? PDX_Customers::account_status($user_id) : 'active',
                'customer_level'     => $level['customer_level'],
                'level_label'        => $level['level_label'],
                'has_customer_level' => $level['has_customer_level'],
                'avatar_preset'      => isset($avatar['avatar_preset']) ? $avatar['avatar_preset'] : '',
                'avatar_url'         => isset($avatar['avatar_url']) ? $avatar['avatar_url'] : '',
                'avatar_has_image'   => !empty($avatar['avatar_has_image']),
                'avatar_has_upload'  => !empty($avatar['avatar_has_upload']),
            )
        );
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>
     */
    private static function customer_detail($user_id) {
        $summary = self::customer_summary($user_id);
        $avatar = class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::profile_fields($user_id) : array();
        $level = PAXdesign_Customer_Levels::profile_fields($user_id);
        return array_merge($summary, $avatar, $level, array(
            'admin_notes'       => class_exists('PDX_Customers') ? (string) get_user_meta($user_id, PDX_Customers::META_ADMIN_NOTES, true) : '',
            'vip_avatar_grants' => class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::vip_grants_for_user($user_id) : array(),
            'last_login'        => class_exists('PDX_Customers') ? (string) get_user_meta($user_id, PDX_Customers::META_LAST_LOGIN, true) : '',
            'standard_presets'  => class_exists('PAXdesign_Customer_Avatar_Presets') ? PAXdesign_Customer_Avatar_Presets::catalog() : array(),
            'vip_presets'       => class_exists('PAXdesign_Customer_Avatar_Vip_Presets') ? PAXdesign_Customer_Avatar_Vip_Presets::catalog_for_user($user_id) : array(),
        ));
    }

    /**
     * @param int $user_id
     * @return bool
     */
    private static function is_manageable_customer($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }
        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            return false;
        }
        if (user_can($user, 'manage_options')) {
            return false;
        }
        if (class_exists('PDX_Customers')) {
            return PDX_Customers::is_customer($user_id);
        }
        return true;
    }
}
