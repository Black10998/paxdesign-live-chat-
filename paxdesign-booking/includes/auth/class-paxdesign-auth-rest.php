<?php
/**
 * Auth REST routes — registered when paxdesign-toolbar is not providing /auth/*.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Auth_REST {

    const NS = 'pdx/v1';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'), 99);
    }

    public static function register_routes() {
        $pub = '__return_true';

        register_rest_route(self::NS, '/auth/register', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'register'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/login', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'login'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/logout', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'logout'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/me', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'me'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/forgot-password', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'forgot_password'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/reset-password', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'reset_password'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/resend-verification', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'resend_verification'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/verify', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'verify'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/mobile-login', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'mobile_login'),
            'permission_callback' => $pub,
        ));
        register_rest_route(self::NS, '/auth/mobile-logout', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'mobile_logout'),
            'permission_callback' => array(__CLASS__, 'mobile_logout_permission'),
        ));
    }

    private static function rate_limit($action) {
        $result = PAXdesign_Auth::auth_rate_limit($action);
        if (empty($result['allowed'])) {
            return new WP_REST_Response(array(
                'success'     => false,
                'error'       => 'rate_limited',
                'message'     => __('Too many attempts. Please try again later.', 'paxdesign-booking'),
                'retry_after' => (int) ($result['retry_after'] ?? 60),
            ), 429);
        }
        return null;
    }

    public static function register(WP_REST_Request $request) {
        $limited = self::rate_limit('register');
        if ($limited) {
            return $limited;
        }
        $result = PAXdesign_Auth::register(
            sanitize_email((string) $request->get_param('email')),
            (string) $request->get_param('password'),
            sanitize_text_field((string) $request->get_param('name'))
        );
        return new WP_REST_Response($result, !empty($result['success']) ? 201 : 400);
    }

    public static function login(WP_REST_Request $request) {
        $limited = self::rate_limit('login');
        if ($limited) {
            return $limited;
        }
        $result = PAXdesign_Auth::login(
            sanitize_email((string) $request->get_param('email')),
            (string) $request->get_param('password'),
            (bool) $request->get_param('remember')
        );
        return new WP_REST_Response($result, !empty($result['success']) ? 200 : 401);
    }

    public static function logout() {
        return new WP_REST_Response(PAXdesign_Auth::logout(), 200);
    }

    public static function me() {
        return new WP_REST_Response(array_merge(
            PAXdesign_Auth::user_payload(),
            array('nonce' => wp_create_nonce('wp_rest'))
        ), 200);
    }

    public static function forgot_password(WP_REST_Request $request) {
        $limited = self::rate_limit('forgot');
        if ($limited) {
            return $limited;
        }
        $result = PAXdesign_Auth::forgot_password(sanitize_email((string) $request->get_param('email')));
        return new WP_REST_Response($result, 200);
    }

    public static function reset_password(WP_REST_Request $request) {
        $result = PAXdesign_Auth::reset_password(
            sanitize_text_field((string) $request->get_param('token')),
            (int) $request->get_param('uid'),
            (string) $request->get_param('password')
        );
        return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
    }

    public static function resend_verification(WP_REST_Request $request) {
        $limited = self::rate_limit('resend');
        if ($limited) {
            return $limited;
        }
        $email = sanitize_email((string) $request->get_param('email'));
        if ($email !== '') {
            $result = PAXdesign_Auth::resend_verification_by_email($email);
            return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
        }
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Authentication required.'), 401);
        }
        $result = PAXdesign_Auth::resend_verification();
        return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
    }

    public static function verify(WP_REST_Request $request) {
        $email = sanitize_email((string) $request->get_param('email'));
        $code  = sanitize_text_field((string) $request->get_param('code'));
        if ($email !== '' && $code !== '') {
            $result = PAXdesign_Auth::verify_by_email_and_code($email, $code);
            return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
        }
        $uid   = (int) $request->get_param('uid');
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ($uid <= 0) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid verification request.'), 400);
        }
        if ($code !== '') {
            $result = PAXdesign_Auth::verify_email($uid, '', $code);
        } else {
            $result = PAXdesign_Auth::verify_email($uid, $token);
        }
        return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
    }

    public static function mobile_login(WP_REST_Request $request) {
        $limited = self::rate_limit('login');
        if ($limited) {
            return $limited;
        }
        $login = sanitize_text_field((string) $request->get_param('login'));
        if ($login === '') {
            $login = sanitize_email((string) $request->get_param('email'));
        }
        $result = PAXdesign_Auth::mobile_login(
            $login,
            (string) $request->get_param('password'),
            sanitize_text_field((string) $request->get_param('device_label'))
        );
        $status = 200;
        if (empty($result['success'])) {
            $error = (string) ($result['error'] ?? '');
            if (in_array($error, array('invalid_credentials', 'email_unverified', 'suspended'), true)) {
                $status = 401;
            } elseif ($error === 'locked') {
                $status = 429;
            } else {
                $status = 400;
            }
        }
        return new WP_REST_Response($result, $status);
    }

    public static function mobile_logout(WP_REST_Request $request) {
        $result = PAXdesign_Auth::mobile_logout(get_current_user_id(), sanitize_text_field((string) $request->get_param('app_password_uuid')));
        return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
    }

    public static function mobile_logout_permission() {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', __('Authentication required.', 'paxdesign-booking'), array('status' => 401));
        }
        return true;
    }
}
