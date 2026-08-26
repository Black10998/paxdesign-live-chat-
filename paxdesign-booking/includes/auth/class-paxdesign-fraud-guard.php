<?php
/**
 * Anti-fraud / anti-bot Device Risk engine.
 *
 * Collects browser/device signals (no audio), scores risk, and asks for
 * extra email verification on high-risk Login / Register / API / Chat
 * instead of blocking legitimate users.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Fraud_Guard {

    const NS = 'pdx/v1';
    const HEADER_DEVICE    = 'X-PAX-Device-Id';
    const HEADER_CHALLENGE = 'X-PAX-Challenge';

    /** @var array<int, string> */
    private static $ajax_challenge = array(
        'paxdesign_chat_live_user_send',
        'paxdesign_chat_live_user_attach',
        'paxdesign_chat_live_request',
    );

    public static function init() {
        add_action('init', array(__CLASS__, 'install'), 4);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'), 100);
        add_filter('rest_pre_dispatch', array(__CLASS__, 'rest_pre_dispatch'), 5, 3);
        add_action('wp_login', array(__CLASS__, 'on_wp_login'), 20, 2);

        foreach (self::$ajax_challenge as $action) {
            add_action('wp_ajax_' . $action, array(__CLASS__, 'gate_ajax'), 0);
            add_action('wp_ajax_nopriv_' . $action, array(__CLASS__, 'gate_ajax'), 0);
        }
    }

    public static function install() {
        if (class_exists('PAXdesign_Fraud_Store')) {
            PAXdesign_Fraud_Store::maybe_install();
        }
    }

    public static function register_routes() {
        register_rest_route(self::NS, '/auth/device-risk', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_device_risk'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(self::NS, '/auth/device-challenge', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_device_challenge'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Lightweight collector ingest. Always allows; never blocks page load.
     */
    public static function handle_device_risk(WP_REST_Request $request) {
        $limited = self::ingest_rate_limited();
        if ($limited) {
            return $limited;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        $device_id = PAXdesign_Fraud_Store::sanitize_device_id((string) ($body['device_id'] ?? self::header_device_id($request)));
        if ($device_id === '') {
            $device_id = wp_generate_uuid4();
        }
        $signals = PAXdesign_Fraud_Store::trim_signals(isset($body['signals']) && is_array($body['signals']) ? $body['signals'] : array());
        if (isset($body['collected_ms'])) {
            $signals['collected_ms'] = (int) $body['collected_ms'];
        }
        $ip     = PAXdesign_Fraud_Store::client_ip();
        $result = self::evaluate($signals, $device_id, array('source' => 'collect'));

        PAXdesign_Fraud_Store::upsert_device($device_id, $result['fingerprint_hash'], $ip, $signals);
        PAXdesign_Fraud_Store::cache_score($device_id, $result);
        PAXdesign_Fraud_Store::record_event('collect', $device_id, get_current_user_id(), $ip, (int) $result['score'], array(
            'action'  => $result['action'],
            'reasons' => $result['reasons'],
        ));

        $user_id = get_current_user_id();
        if ($user_id > 0) {
            PAXdesign_Fraud_Store::bind_user($device_id, $user_id);
        }

        return new WP_REST_Response(array(
            'success'     => true,
            'device_id'   => $device_id,
            'risk_score'  => (int) $result['score'],
            'action'      => $result['action'] === PAXdesign_Fraud_Score::ACTION_CHALLENGE ? PAXdesign_Fraud_Score::ACTION_WATCH : $result['action'],
            'reasons'     => array(),
        ), 200);
    }

    public static function handle_device_challenge(WP_REST_Request $request) {
        $token = sanitize_text_field((string) $request->get_param('token'));
        $code  = sanitize_text_field((string) $request->get_param('code'));
        $row   = PAXdesign_Fraud_Store::verify_challenge($token, $code);
        if (!$row) {
            return new WP_REST_Response(array(
                'success' => false,
                'code'    => 'invalid_code',
                'message' => __('That code is invalid or expired. Please try again.', 'paxdesign-booking'),
            ), 400);
        }
        return new WP_REST_Response(array(
            'success'         => true,
            'challenge_token' => $token,
            'message'         => __('Verification complete.', 'paxdesign-booking'),
        ), 200);
    }

    /**
     * @param mixed            $result
     * @param WP_REST_Server   $server
     * @param WP_REST_Request  $request
     * @return mixed
     */
    public static function rest_pre_dispatch($result, $server, $request) {
        if ($result !== null) {
            return $result;
        }
        if (!($request instanceof WP_REST_Request)) {
            return $result;
        }
        $route = (string) $request->get_route();
        if (strpos($route, '/pdx/v1/') !== 0) {
            return $result;
        }
        if (self::is_unprotected_route($route, $request->get_method())) {
            return $result;
        }

        $method = strtoupper((string) $request->get_method());
        $ip     = PAXdesign_Fraud_Store::client_ip();

        if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
            if (!is_user_logged_in()) {
                $vel = PAXdesign_Fraud_Store::bump_velocity('ipget', $ip);
                if ($vel >= 120) {
                    PAXdesign_Fraud_Store::record_event('scrape', self::current_device_id($request), 0, $ip, 80, array('route' => $route));
                    return new WP_Error(
                        'pax_rate_limited',
                        __('Too many requests. Please slow down.', 'paxdesign-booking'),
                        array('status' => 429)
                    );
                }
            }
            return $result;
        }

        if (self::is_auth_credential_route($route)) {
            return $result;
        }

        $eval = self::evaluate_current($request, array('source' => 'api', 'route' => $route));
        if ($eval['action'] !== PAXdesign_Fraud_Score::ACTION_CHALLENGE) {
            return $result;
        }
        if (self::request_is_cleared($request, get_current_user_id())) {
            return $result;
        }
        if (self::user_is_privileged(get_current_user_id())) {
            return $result;
        }

        $email = self::email_for_user(get_current_user_id());
        $issued = self::issue_challenge(self::current_device_id($request), $email, get_current_user_id(), (int) $eval['score'], $eval['reasons']);
        if ($issued instanceof WP_REST_Response || $issued instanceof WP_Error) {
            if ($issued instanceof WP_REST_Response) {
                return new WP_Error(
                    'pax_challenge_required',
                    (string) $issued->get_data()['message'],
                    array_merge(array('status' => 428), (array) $issued->get_data())
                );
            }
            return $issued;
        }
        return $result;
    }

    /**
     * Login / register / mobile-login extra verification (not a hard deny).
     *
     * @return WP_REST_Response|null
     */
    public static function gate_auth($action, $email, $password = '') {
        $email  = sanitize_email((string) $email);
        $action = sanitize_key((string) $action);

        if ($email === '' || !is_email($email)) {
            return null;
        }

        if (self::email_is_privileged($email)) {
            return null;
        }

        $device_id = self::current_device_id(null);
        $eval      = self::evaluate_current(null, array(
            'source' => $action,
            'email'  => $email,
        ));

        if ($eval['action'] !== PAXdesign_Fraud_Score::ACTION_CHALLENGE) {
            if ($eval['action'] === PAXdesign_Fraud_Score::ACTION_WATCH) {
                PAXdesign_Fraud_Store::record_event('watch_' . $action, $device_id, 0, PAXdesign_Fraud_Store::client_ip(), (int) $eval['score'], array(
                    'reasons' => $eval['reasons'],
                ));
            }
            return null;
        }

        if (self::request_is_cleared(null, 0, $email)) {
            return null;
        }

        if ($action === 'login' || $action === 'mobile_login') {
            if (!self::credentials_look_valid($email, $password)) {
                PAXdesign_Fraud_Store::record_failed_login($email, PAXdesign_Fraud_Store::client_ip());
                return null;
            }
        }

        $user = function_exists('get_user_by') ? get_user_by('email', $email) : null;
        $user_id = ($user && isset($user->ID)) ? (int) $user->ID : 0;
        return self::issue_challenge($device_id, $email, $user_id, (int) $eval['score'], $eval['reasons']);
    }

    public static function gate_ajax() {
        if (self::user_is_privileged(get_current_user_id())) {
            return;
        }
        $eval = self::evaluate_current(null, array('source' => 'chat_send'));
        if ($eval['action'] !== PAXdesign_Fraud_Score::ACTION_CHALLENGE) {
            return;
        }
        if (self::request_is_cleared(null, get_current_user_id())) {
            return;
        }
        $email = self::email_for_user(get_current_user_id());
        $issued = self::issue_challenge(self::current_device_id(null), $email, get_current_user_id(), (int) $eval['score'], $eval['reasons']);
        $data = ($issued instanceof WP_REST_Response) ? $issued->get_data() : array(
            'code'    => 'pax_challenge_required',
            'message' => __('Please confirm you are human.', 'paxdesign-booking'),
        );
        wp_send_json_error($data, 428);
    }

    /**
     * @param WP_User|null $user
     */
    public static function on_wp_login($user_login, $user = null) {
        $user_id = ($user instanceof WP_User) ? (int) $user->ID : get_current_user_id();
        $device_id = self::current_device_id(null);
        if ($user_id > 0 && $device_id !== '') {
            PAXdesign_Fraud_Store::bind_user($device_id, $user_id);
        }
    }

    /**
     * @param array<string, mixed> $signals
     * @param array<string, mixed> $extra
     * @return array{score:int,action:string,reasons:array<int,string>,fingerprint_hash:string}
     */
    public static function evaluate(array $signals, $device_id, array $extra = array()) {
        $device_id = PAXdesign_Fraud_Store::sanitize_device_id((string) $device_id);
        $ip        = PAXdesign_Fraud_Store::client_ip();
        $user_id   = get_current_user_id();
        $ua        = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if (empty($signals['ua'])) {
            $signals['ua'] = $ua;
        }

        $hash = PAXdesign_Fraud_Score::fingerprint_hash($signals);
        if ($device_id !== '' && self::signals_are_empty($signals)) {
            $stored = PAXdesign_Fraud_Store::fingerprint_for_device($device_id);
            if ($stored !== '') {
                $hash = $stored;
            }
        }

        $context = array(
            'owner'                 => self::user_is_privileged($user_id) || self::email_is_privileged((string) ($extra['email'] ?? '')),
            'known_device'          => $user_id > 0 && PAXdesign_Fraud_Store::is_known_device($device_id, $user_id),
            'missing_device'        => $device_id === '',
            'missing_signals'       => empty($signals) || (count($signals) <= 1 && isset($signals['ua'])),
            'fingerprint_accounts'  => PAXdesign_Fraud_Store::fingerprint_account_count($hash),
            'ip_accounts'           => PAXdesign_Fraud_Store::ip_account_count($ip),
            'ip_velocity'           => (int) ($extra['ip_velocity'] ?? 0),
            'failed_logins'         => max(
                PAXdesign_Fraud_Store::failed_login_count($ip),
                PAXdesign_Fraud_Store::failed_login_count((string) ($extra['email'] ?? ''))
            ),
            'scrape_pattern'        => !empty($extra['scrape_pattern']),
            'ua'                    => $ua,
            'webdriver'             => !empty($signals['webdriver']),
            'collected_ms'          => (int) ($signals['collected_ms'] ?? 0),
        );

        $result = PAXdesign_Fraud_Score::evaluate($signals, $context);
        if ($hash !== '') {
            $result['fingerprint_hash'] = $hash;
        }
        return $result;
    }

    /**
     * @param WP_REST_Request|null $request
     * @param array<string, mixed> $extra
     * @return array{score:int,action:string,reasons:array<int,string>,fingerprint_hash:string}
     */
    public static function evaluate_current($request, array $extra = array()) {
        $device_id = self::current_device_id($request);
        $cached    = $device_id !== '' ? PAXdesign_Fraud_Store::cached_score($device_id) : null;

        $skip_velocity = !empty($extra['source']) && $extra['source'] === 'chat_observe';
        $ip = PAXdesign_Fraud_Store::client_ip();
        $vel = 0;
        if (!$skip_velocity) {
            $vel = PAXdesign_Fraud_Store::bump_velocity('ip', $ip);
            if ($device_id !== '') {
                PAXdesign_Fraud_Store::bump_velocity('dev', $device_id);
            }
        }
        $extra['ip_velocity'] = $vel;

        if (is_array($cached) && empty($extra['force'])) {
            if ($vel >= 60) {
                $cached['score'] = min(100, (int) $cached['score'] + 12);
                if ((int) $cached['score'] >= PAXdesign_Fraud_Score::THRESHOLD_CHALLENGE) {
                    $cached['action'] = PAXdesign_Fraud_Score::ACTION_CHALLENGE;
                }
                $cached['reasons'][] = 'elevated_request_rate';
            }
            return $cached;
        }

        $signals = array();
        if ($device_id !== '') {
            $row = get_transient('pax_fg_dev_' . $device_id);
            if (is_array($row) && isset($row['signals']) && is_array($row['signals'])) {
                $signals = $row['signals'];
            }
        }
        return self::evaluate($signals, $device_id, $extra);
    }

    /**
     * @return WP_REST_Response
     */
    public static function issue_challenge($device_id, $email, $user_id, $score, array $reasons = array()) {
        $email = sanitize_email((string) $email);
        if ($email === '' || !is_email($email)) {
            return new WP_REST_Response(array(
                'success' => false,
                'code'    => 'pax_challenge_required',
                'error'   => 'pax_challenge_required',
                'message' => __('Please confirm you are human, then try again.', 'paxdesign-booking'),
                'methods' => array('retry'),
                'risk_score' => (int) $score,
                'action'  => 'challenge',
            ), 428);
        }

        $issued = PAXdesign_Fraud_Store::create_challenge($device_id, $email, (int) $user_id);
        if (!$issued) {
            return new WP_REST_Response(array(
                'success'     => false,
                'code'        => 'pax_challenge_required',
                'error'       => 'pax_challenge_required',
                'message'     => __('Please wait a few minutes before requesting another verification code.', 'paxdesign-booking'),
                'risk_score'  => (int) $score,
                'action'      => 'challenge',
            ), 428);
        }

        self::send_challenge_email($email, $issued['code']);
        PAXdesign_Fraud_Store::record_event('challenge', $device_id, (int) $user_id, PAXdesign_Fraud_Store::client_ip(), (int) $score, array(
            'reasons' => $reasons,
            'email'   => self::mask_email($email),
        ));

        return new WP_REST_Response(array(
            'success'         => false,
            'code'            => 'pax_challenge_required',
            'error'           => 'pax_challenge_required',
            'message'         => __('Please confirm you are human. We sent a 6-digit code to your email.', 'paxdesign-booking'),
            'challenge_token' => $issued['token'],
            'methods'         => array('email_otp'),
            'risk_score'      => (int) $score,
            'action'          => 'challenge',
            'email_hint'      => self::mask_email($email),
        ), 428);
    }

    public static function observe($event, $count_velocity = true) {
        $device_id = self::current_device_id(null);
        $ip = PAXdesign_Fraud_Store::client_ip();
        if ($count_velocity) {
            PAXdesign_Fraud_Store::bump_velocity('ip', $ip);
        }
        PAXdesign_Fraud_Store::record_event($event, $device_id, get_current_user_id(), $ip, 0, array());
    }

    /**
     * @param WP_REST_Request|null $request
     */
    public static function current_device_id($request = null) {
        $candidates = array();
        if ($request instanceof WP_REST_Request) {
            $candidates[] = (string) $request->get_header('x-pax-device-id');
            $candidates[] = (string) $request->get_param('device_id');
        }
        $candidates[] = self::request_header(self::HEADER_DEVICE);
        if (!empty($_POST['device_id'])) {
            $candidates[] = sanitize_text_field(wp_unslash((string) $_POST['device_id']));
        }
        foreach ($candidates as $id) {
            $clean = PAXdesign_Fraud_Store::sanitize_device_id($id);
            if ($clean !== '') {
                return $clean;
            }
        }
        return '';
    }

    /**
     * @param WP_REST_Request|null $request
     */
    public static function current_challenge_token($request = null) {
        $candidates = array();
        if ($request instanceof WP_REST_Request) {
            $candidates[] = (string) $request->get_header('x-pax-challenge');
            $candidates[] = (string) $request->get_param('challenge_token');
        }
        $candidates[] = self::request_header(self::HEADER_CHALLENGE);
        foreach ($candidates as $token) {
            $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) $token));
            if (strlen($token) === 64) {
                return $token;
            }
        }
        return '';
    }

    /**
     * @param WP_REST_Request|null $request
     */
    public static function request_is_cleared($request, $user_id = 0, $email = '') {
        $token = self::current_challenge_token($request);
        if ($token !== '' && PAXdesign_Fraud_Store::challenge_is_open($token)) {
            return true;
        }
        return PAXdesign_Fraud_Store::is_cleared(self::current_device_id($request), (int) $user_id, $email);
    }

    public static function user_is_privileged($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        if (class_exists('PAXdesign_Auth_Native') && PAXdesign_Auth_Native::is_owner_account($user_id)) {
            return true;
        }
        if (function_exists('user_can') && user_can($user_id, 'manage_options')) {
            return true;
        }
        return false;
    }

    public static function email_is_privileged($email) {
        $email = sanitize_email((string) $email);
        if ($email === '') {
            return false;
        }
        if (class_exists('PAXdesign_Auth_Native') && method_exists('PAXdesign_Auth_Native', 'is_owner_email')) {
            return PAXdesign_Auth_Native::is_owner_email($email);
        }
        return false;
    }

    private static function credentials_look_valid($email, $password) {
        if ($password === '' || !function_exists('get_user_by') || !function_exists('wp_check_password')) {
            return false;
        }
        $user = get_user_by('email', $email);
        if (!$user || empty($user->user_pass)) {
            return false;
        }
        return wp_check_password($password, $user->user_pass, (int) $user->ID);
    }

    private static function email_for_user($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !function_exists('get_userdata')) {
            return '';
        }
        $user = get_userdata($user_id);
        return ($user && !empty($user->user_email)) ? (string) $user->user_email : '';
    }

    private static function send_challenge_email($email, $code) {
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $subject = sprintf('[%s] %s', $site, __('Security verification code', 'paxdesign-booking'));
        $body = '<p>' . esc_html__('Use this code to confirm this sign-in or request. It expires in 10 minutes.', 'paxdesign-booking') . '</p>'
            . '<p style="font-size:28px;letter-spacing:8px;font-weight:700">' . esc_html($code) . '</p>';
        wp_mail($email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));
    }

    private static function mask_email($email) {
        $email = sanitize_email((string) $email);
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '';
        }
        $name = $parts[0];
        $keep = substr($name, 0, 1);
        return $keep . '***@' . $parts[1];
    }

    private static function is_unprotected_route($route, $method) {
        $skip = array(
            '/pdx/v1/auth/device-risk',
            '/pdx/v1/auth/device-challenge',
            '/pdx/v1/auth/liveness',
            '/pdx/v1/auth/apple/start',
            '/pdx/v1/auth/apple/callback',
            '/pdx/v1/auth/apple/complete',
            '/pdx/v1/auth/github/start',
            '/pdx/v1/auth/github/callback',
        );
        foreach ($skip as $needle) {
            if ($route === $needle || strpos($route, $needle) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function is_auth_credential_route($route) {
        foreach (array('/pdx/v1/auth/login', '/pdx/v1/auth/register', '/pdx/v1/auth/mobile-login') as $needle) {
            if ($route === $needle) {
                return true;
            }
        }
        return false;
    }

    private static function signals_are_empty(array $signals) {
        foreach ($signals as $key => $value) {
            if ($key === 'ua' || $key === 'collected_ms') {
                continue;
            }
            if ($value === '' || $value === null || $value === 0 || $value === false || $value === array()) {
                continue;
            }
            return false;
        }
        return true;
    }

    private static function ingest_rate_limited() {
        $ip = PAXdesign_Fraud_Store::client_ip();
        $count = PAXdesign_Fraud_Store::bump_velocity('ingest', $ip);
        if ($count > 40) {
            return new WP_REST_Response(array(
                'success' => false,
                'code'    => 'rate_limited',
            ), 429);
        }
        return null;
    }

    private static function header_device_id($request) {
        if ($request instanceof WP_REST_Request) {
            return (string) $request->get_header('x-pax-device-id');
        }
        return self::request_header(self::HEADER_DEVICE);
    }

    private static function request_header($name) {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (empty($_SERVER[$key])) {
            return '';
        }
        return sanitize_text_field(wp_unslash($_SERVER[$key]));
    }
}
