<?php
/**
 * Production server-side URL security scanning for customer live chat.
 *
 * Scans run asynchronously after message persistence. Clients only receive
 * checking / final / failure states from the server — never client-side guesses.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Link_Scan_Service {

    const STATUS_CHECKING   = 'checking';
    const STATUS_SAFE       = 'safe';
    const STATUS_SUSPICIOUS = 'suspicious';
    const STATUS_DANGEROUS  = 'dangerous';
    const STATUS_FAILED     = 'failed';
    const STATUS_TIMEOUT    = 'timeout';
    const STATUS_INCOMPLETE = 'incomplete';

    const SCHEMA_VERSION    = '1.0';
    const OPTION_SCHEMA     = 'paxdesign_link_scan_schema';
    const CRON_HOOK         = 'paxdesign_run_link_scan';
    const TIMEOUT_SECONDS   = 15;
    const CANCELLED_OPTION  = 'paxdesign_cancelled_link_scans';

    /** @var array<int, array{0: string, 1: int}> */
    private static $dispatch_queue = array();

    /** @var array<string, true> */
    private static $cancelled_scans = array();

    public static function init() {
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_message_scan'), 10, 2);
        add_action('shutdown', array(__CLASS__, 'flush_dispatch_queue'), 9999);
    }

    public static function scans_table() {
        global $wpdb;
        return $wpdb->prefix . 'paxdesign_link_scans';
    }

    public static function maybe_upgrade() {
        if ((string) get_option(self::OPTION_SCHEMA, '') === self::SCHEMA_VERSION) {
            return;
        }
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = self::scans_table();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            message_seq bigint(20) unsigned NOT NULL,
            url text NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'checking',
            provider varchar(64) NOT NULL DEFAULT '',
            result_json longtext NULL,
            error_message text NULL,
            started_at datetime NOT NULL,
            completed_at datetime NULL,
            PRIMARY KEY (id),
            KEY session_message (session_id, message_seq),
            KEY status_started (status, started_at)
        ) ENGINE=InnoDB $charset;");
        update_option(self::OPTION_SCHEMA, self::SCHEMA_VERSION, false);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function begin_scan_meta($content, $role, $extra = array()) {
        if ($role !== 'user' || !class_exists('PAXdesign_Link_Scanner')) {
            return $extra;
        }
        $urls = PAXdesign_Link_Scanner::extract_urls((string) $content);
        if (empty($urls)) {
            return $extra;
        }
        $started = time();
        $url_rows = array();
        foreach ($urls as $url) {
            $url_rows[] = array(
                'url'    => $url,
                'status' => self::STATUS_CHECKING,
            );
        }
        $extra['link_scan_status']       = self::STATUS_CHECKING;
        $extra['link_scan_urls']         = wp_json_encode($url_rows);
        $extra['link_scan_started_at']   = $started;
        $extra['link_scan_completed_at'] = 0;
        $extra['link_scan_provider']     = '';
        return $extra;
    }

    public static function dispatch_scan($session_id, $message_seq) {
        $session_id  = sanitize_text_field((string) $session_id);
        $message_seq = absint($message_seq);
        if ($session_id === '' || $message_seq <= 0) {
            return;
        }
        if (self::is_scan_cancelled($session_id, $message_seq)) {
            return;
        }
        self::$dispatch_queue[] = array($session_id, $message_seq);
        wp_schedule_single_event(time() + 90, self::CRON_HOOK, array($session_id, $message_seq));
    }

    /**
     * Permanently cancel all pending and future scan work for a deleted message.
     */
    public static function cancel_message_scans($session_id, $message_seq) {
        $session_id  = sanitize_text_field((string) $session_id);
        $message_seq = absint($message_seq);
        if ($session_id === '' || $message_seq <= 0) {
            return;
        }

        $key = self::scan_key($session_id, $message_seq);
        self::$cancelled_scans[$key] = true;

        $persisted = get_option(self::CANCELLED_OPTION, array());
        if (!is_array($persisted)) {
            $persisted = array();
        }
        $persisted[$key] = time();
        $cutoff = time() - DAY_IN_SECONDS;
        foreach ($persisted as $persist_key => $ts) {
            if (!is_numeric($ts) || (int) $ts < $cutoff) {
                unset($persisted[$persist_key]);
            }
        }
        update_option(self::CANCELLED_OPTION, $persisted, false);

        if (!empty(self::$dispatch_queue)) {
            self::$dispatch_queue = array_values(array_filter(
                self::$dispatch_queue,
                function ($item) use ($session_id, $message_seq) {
                    return !($item[0] === $session_id && (int) $item[1] === $message_seq);
                }
            ));
        }

        while (($timestamp = wp_next_scheduled(self::CRON_HOOK, array($session_id, $message_seq))) !== false) {
            wp_unschedule_event($timestamp, self::CRON_HOOK, array($session_id, $message_seq));
        }

        self::delete_scan_rows($session_id, $message_seq);
    }

    public static function is_scan_cancelled($session_id, $message_seq) {
        $session_id  = sanitize_text_field((string) $session_id);
        $message_seq = absint($message_seq);
        if ($session_id === '' || $message_seq <= 0) {
            return true;
        }
        $key = self::scan_key($session_id, $message_seq);
        if (!empty(self::$cancelled_scans[$key])) {
            return true;
        }
        $persisted = get_option(self::CANCELLED_OPTION, array());
        return is_array($persisted) && isset($persisted[$key]);
    }

    private static function scan_key($session_id, $message_seq) {
        return sanitize_text_field((string) $session_id) . ':' . absint($message_seq);
    }

    public static function flush_dispatch_queue() {
        if (empty(self::$dispatch_queue)) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        $queue = self::$dispatch_queue;
        self::$dispatch_queue = array();
        foreach ($queue as $item) {
            if (self::is_scan_cancelled($item[0], $item[1])) {
                continue;
            }
            self::run_message_scan($item[0], $item[1]);
        }
    }

    public static function run_message_scan($session_id, $message_seq) {
        self::maybe_upgrade();
        $session_id  = sanitize_text_field((string) $session_id);
        $message_seq = absint($message_seq);
        if ($session_id === '' || $message_seq <= 0 || !class_exists('PAXdesign_Message_Store')) {
            return;
        }
        if (self::is_scan_cancelled($session_id, $message_seq)) {
            self::delete_scan_rows($session_id, $message_seq);
            return;
        }

        $message = PAXdesign_Message_Store::get_message($session_id, $message_seq);
        if (!$message || ($message['role'] ?? '') !== 'user') {
            self::delete_scan_rows($session_id, $message_seq);
            return;
        }

        $urls = self::urls_from_message($message);
        if (empty($urls)) {
            return;
        }

        self::delete_scan_rows($session_id, $message_seq);
        $started_at = !empty($message['link_scan_started_at'])
            ? gmdate('Y-m-d H:i:s', absint($message['link_scan_started_at']))
            : current_time('mysql', true);

        foreach ($urls as $url) {
            self::insert_scan_row($session_id, $message_seq, $url, $started_at);
        }

        $results   = array();
        $providers = array();
        $worst     = self::STATUS_SAFE;
        $had_error = false;

        foreach ($urls as $url) {
            if (self::is_scan_cancelled($session_id, $message_seq)) {
                self::delete_scan_rows($session_id, $message_seq);
                return;
            }
            if (!PAXdesign_Message_Store::get_message($session_id, $message_seq)) {
                self::delete_scan_rows($session_id, $message_seq);
                return;
            }
            $outcome = self::scan_url_remote($url);
            $results[] = array(
                'url'      => $url,
                'status'   => $outcome['status'],
                'provider' => $outcome['provider'],
            );
            if ($outcome['provider'] !== '') {
                $providers[$outcome['provider']] = true;
            }
            self::complete_scan_row($session_id, $message_seq, $url, $outcome);
            $worst = self::worst_status($worst, $outcome['status']);
            if (in_array($outcome['status'], array(self::STATUS_FAILED, self::STATUS_TIMEOUT, self::STATUS_INCOMPLETE), true)) {
                $had_error = true;
            }
        }

        if ($had_error && !in_array($worst, array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)) {
            $worst = self::STATUS_INCOMPLETE;
        }

        $message = PAXdesign_Message_Store::get_message($session_id, $message_seq);
        if (
            is_array($message)
            && in_array($worst, array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)
            && class_exists('PAXdesign_APNS')
        ) {
            PAXdesign_APNS::notify_link_scan_attention($session_id, $message, $worst);
        }

        $provider_label = implode('+', array_keys($providers));
        if ($provider_label === '') {
            $provider_label = 'server';
        }

        $completed = time();
        if (self::is_scan_cancelled($session_id, $message_seq)) {
            self::delete_scan_rows($session_id, $message_seq);
            return;
        }
        if (!PAXdesign_Message_Store::get_message($session_id, $message_seq)) {
            self::delete_scan_rows($session_id, $message_seq);
            return;
        }

        $updated = PAXdesign_Message_Store::update_message_meta(
            $session_id,
            $message_seq,
            array(
                'link_scan_status'         => self::STATUS_CHECKING,
                'link_scan_system_status'  => $worst,
                'link_scan_review_pending'   => '1',
                'link_scan_urls'           => wp_json_encode($results),
                'link_scan_completed_at'   => $completed,
                'link_scan_provider'       => $provider_label,
            ),
            'customer'
        );

        if (is_wp_error($updated) || !is_array($updated)) {
            return;
        }

        $payload = array(
            'session_id' => $session_id,
            'seq'        => $message_seq,
            'message'    => $updated,
        );
        PAXdesign_Message_Store::emit('inbox:admins', 'link_scan_review_ready', $payload, $message_seq);
    }

    /**
     * Apply an employee's final link-scan decision for a customer message.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function apply_review_decision($session_id, $message_seq, $action, $reviewer_id = 0) {
        $session_id  = sanitize_text_field((string) $session_id);
        $message_seq = absint($message_seq);
        $action      = sanitize_key((string) $action);
        $reviewer_id = absint($reviewer_id);

        if ($session_id === '' || $message_seq <= 0) {
            return new WP_Error('pax_link_review_invalid', 'Invalid message.', array('status' => 400));
        }

        $message = PAXdesign_Message_Store::get_message($session_id, $message_seq);
        if (!$message) {
            return new WP_Error('pax_link_review_not_found', 'Message not found.', array('status' => 404));
        }
        if (!PAXdesign_Message_Store::is_link_review_pending($message)) {
            return new WP_Error('pax_link_review_not_pending', 'No pending link review for this message.', array('status' => 409));
        }

        if ($action === 'delete_warn') {
            $tombstone = __('This message contained an unsafe link and was removed to protect you.', 'paxdesign-booking');
            $result = PAXdesign_Message_Store::delete_message(
                $session_id,
                $message_seq,
                $reviewer_id,
                'customer',
                $tombstone
            );
            if (!is_wp_error($result) && is_array($result)) {
                $result['warn'] = true;
            }
            return $result;
        }

        if ($action === 'mark_safe') {
            $customer_status = self::STATUS_SAFE;
        } elseif ($action === 'mark_unsafe') {
            $customer_status = self::STATUS_DANGEROUS;
        } else {
            return new WP_Error('pax_link_review_invalid_action', 'Invalid review action.', array('status' => 400));
        }

        $updated = PAXdesign_Message_Store::update_message_meta(
            $session_id,
            $message_seq,
            array(
                'link_scan_status'        => $customer_status,
                'link_scan_review_pending' => '',
            ),
            'customer'
        );
        if (is_wp_error($updated) || !is_array($updated)) {
            return $updated;
        }

        $payload = array(
            'session_id' => $session_id,
            'seq'        => $message_seq,
            'message'    => $updated,
            'reviewed_by'=> $reviewer_id,
            'action'     => $action,
        );
        PAXdesign_Message_Store::emit('session:' . $session_id, 'link_scan_updated', $payload, $message_seq);
        PAXdesign_Message_Store::emit('inbox:admins', 'link_scan_updated', $payload, $message_seq);

        return array(
            'ok'       => true,
            'action'   => $action,
            'message'  => $updated,
        );
    }

    /**
     * @param array<string, mixed> $message
     * @return array<int, string>
     */
    private static function urls_from_message($message) {
        if (!empty($message['link_scan_urls'])) {
            $decoded = json_decode((string) $message['link_scan_urls'], true);
            if (is_array($decoded)) {
                $urls = array();
                foreach ($decoded as $row) {
                    if (is_array($row) && !empty($row['url'])) {
                        $urls[] = (string) $row['url'];
                    }
                }
                if (!empty($urls)) {
                    return $urls;
                }
            }
        }
        return class_exists('PAXdesign_Link_Scanner')
            ? PAXdesign_Link_Scanner::extract_urls((string) ($message['content'] ?? ''))
            : array();
    }

    /**
     * @return array{status: string, provider: string, raw: mixed, error: string}
     */
    public static function scan_url_remote($url, $options = array()) {
        $url = trim((string) $url);
        if ($url === '') {
            return array(
                'status'   => self::STATUS_FAILED,
                'provider' => 'validation',
                'raw'      => null,
                'error'    => 'empty_url',
            );
        }

        $simulate = isset($options['simulate']) ? (string) $options['simulate'] : '';
        if ($simulate === 'failure') {
            return array(
                'status'   => self::STATUS_FAILED,
                'provider' => 'simulated',
                'raw'      => null,
                'error'    => 'service_unavailable',
            );
        }
        if ($simulate === 'timeout') {
            return array(
                'status'   => self::STATUS_TIMEOUT,
                'provider' => 'simulated',
                'raw'      => null,
                'error'    => 'timeout',
            );
        }

        $deadline = microtime(true) + (float) self::TIMEOUT_SECONDS;

        $gsb = self::scan_google_safe_browsing($url, $deadline);
        if ($gsb['status'] !== self::STATUS_SAFE || $gsb['provider'] !== '') {
            return $gsb;
        }

        $haus = self::scan_urlhaus($url, $deadline);
        if ($haus['status'] !== self::STATUS_SAFE) {
            return $haus;
        }

        $tank = self::scan_phishtank($url, $deadline);
        if ($tank['status'] !== self::STATUS_SAFE) {
            return $tank;
        }

        $probe = self::scan_server_probe($url, $deadline);
        return $probe;
    }

    /**
     * @return array{status: string, provider: string, raw: mixed, error: string}
     */
    private static function scan_google_safe_browsing($url, $deadline) {
        $api_key = trim((string) get_option('paxdesign_gsb_api_key', ''));
        if ($api_key === '' || microtime(true) >= $deadline) {
            return array('status' => self::STATUS_SAFE, 'provider' => '', 'raw' => null, 'error' => '');
        }

        $body = array(
            'client' => array(
                'clientId'      => 'paxdesign-booking',
                'clientVersion' => defined('PAXDESIGN_BOOKING_VERSION') ? PAXDESIGN_BOOKING_VERSION : '1.0',
            ),
            'threatInfo' => array(
                'threatTypes'      => array('MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'),
                'platformTypes'    => array('ANY_PLATFORM'),
                'threatEntryTypes' => array('URL'),
                'threatEntries'    => array(array('url' => $url)),
            ),
        );

        $response = wp_remote_post(
            'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=' . rawurlencode($api_key),
            array(
                'timeout' => max(1, (int) ceil($deadline - microtime(true))),
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode($body),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'status'   => self::STATUS_FAILED,
                'provider' => 'google_safe_browsing',
                'raw'      => $response->get_error_message(),
                'error'    => 'request_failed',
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code >= 500) {
            return array(
                'status'   => self::STATUS_FAILED,
                'provider' => 'google_safe_browsing',
                'raw'      => $raw,
                'error'    => 'upstream_error',
            );
        }
        if ($code === 408 || $code === 504) {
            return array(
                'status'   => self::STATUS_TIMEOUT,
                'provider' => 'google_safe_browsing',
                'raw'      => $raw,
                'error'    => 'timeout',
            );
        }
        if (!is_array($raw)) {
            return array(
                'status'   => self::STATUS_INCOMPLETE,
                'provider' => 'google_safe_browsing',
                'raw'      => null,
                'error'    => 'invalid_response',
            );
        }
        if (!empty($raw['matches']) && is_array($raw['matches'])) {
            return array(
                'status'   => self::STATUS_DANGEROUS,
                'provider' => 'google_safe_browsing',
                'raw'      => $raw,
                'error'    => '',
            );
        }
        return array('status' => self::STATUS_SAFE, 'provider' => 'google_safe_browsing', 'raw' => $raw, 'error' => '');
    }

    /**
     * @return array{status: string, provider: string, raw: mixed, error: string}
     */
    private static function scan_urlhaus($url, $deadline) {
        if (microtime(true) >= $deadline) {
            return array(
                'status'   => self::STATUS_TIMEOUT,
                'provider' => 'urlhaus',
                'raw'      => null,
                'error'    => 'timeout',
            );
        }

        $response = wp_remote_post(
            'https://urlhaus-api.abuse.ch/v1/url/',
            array(
                'timeout' => max(1, (int) ceil($deadline - microtime(true))),
                'body'    => array('url' => $url),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'status'   => self::STATUS_FAILED,
                'provider' => 'urlhaus',
                'raw'      => $response->get_error_message(),
                'error'    => 'request_failed',
            );
        }

        $raw = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($raw)) {
            return array(
                'status'   => self::STATUS_INCOMPLETE,
                'provider' => 'urlhaus',
                'raw'      => null,
                'error'    => 'invalid_response',
            );
        }

        $query_status = isset($raw['query_status']) ? (string) $raw['query_status'] : '';
        if ($query_status === 'ok' && !empty($raw['url_status']) && $raw['url_status'] !== 'offline') {
            $threat = strtolower((string) $raw['threat']);
            if (strpos($threat, 'malware') !== false || strpos($threat, 'ransomware') !== false) {
                return array(
                    'status'   => self::STATUS_DANGEROUS,
                    'provider' => 'urlhaus',
                    'raw'      => $raw,
                    'error'    => '',
                );
            }
            return array(
                'status'   => self::STATUS_SUSPICIOUS,
                'provider' => 'urlhaus',
                'raw'      => $raw,
                'error'    => '',
            );
        }
        if ($query_status === 'no_results') {
            return array('status' => self::STATUS_SAFE, 'provider' => 'urlhaus', 'raw' => $raw, 'error' => '');
        }
        if ($query_status === 'invalid_url') {
            return array(
                'status'   => self::STATUS_DANGEROUS,
                'provider' => 'urlhaus',
                'raw'      => $raw,
                'error'    => '',
            );
        }

        return array(
            'status'   => self::STATUS_INCOMPLETE,
            'provider' => 'urlhaus',
            'raw'      => $raw,
            'error'    => $query_status !== '' ? $query_status : 'unknown_status',
        );
    }

    /**
     * @return array{status: string, provider: string, raw: mixed, error: string}
     */
    private static function scan_phishtank($url, $deadline) {
        if (microtime(true) >= $deadline) {
            return array(
                'status'   => self::STATUS_TIMEOUT,
                'provider' => 'phishtank',
                'raw'      => null,
                'error'    => 'timeout',
            );
        }

        $response = wp_remote_post(
            'https://checkurl.phishtank.com/checkurl/',
            array(
                'timeout' => max(1, (int) ceil($deadline - microtime(true))),
                'body'    => array(
                    'url'    => $url,
                    'format' => 'json',
                ),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'status'   => self::STATUS_FAILED,
                'provider' => 'phishtank',
                'raw'      => $response->get_error_message(),
                'error'    => 'request_failed',
            );
        }

        $raw = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($raw)) {
            return array(
                'status'   => self::STATUS_INCOMPLETE,
                'provider' => 'phishtank',
                'raw'      => null,
                'error'    => 'invalid_response',
            );
        }

        if (!empty($raw['results']['in_database']) && !empty($raw['results']['valid'])) {
            return array(
                'status'   => self::STATUS_DANGEROUS,
                'provider' => 'phishtank',
                'raw'      => $raw,
                'error'    => '',
            );
        }

        return array('status' => self::STATUS_SAFE, 'provider' => 'phishtank', 'raw' => $raw, 'error' => '');
    }

    /**
     * @return array{status: string, provider: string, raw: mixed, error: string}
     */
    private static function scan_server_probe($url, $deadline) {
        if (microtime(true) >= $deadline) {
            return array(
                'status'   => self::STATUS_TIMEOUT,
                'provider' => 'server_probe',
                'raw'      => null,
                'error'    => 'timeout',
            );
        }

        $lower = strtolower($url);
        if (preg_match('~^(javascript|data|file|vbscript|blob):~i', $lower)) {
            return array(
                'status'   => self::STATUS_DANGEROUS,
                'provider' => 'server_probe',
                'raw'      => array('reason' => 'dangerous_scheme'),
                'error'    => '',
            );
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return array(
                'status'   => self::STATUS_DANGEROUS,
                'provider' => 'server_probe',
                'raw'      => array('reason' => 'invalid_host'),
                'error'    => '',
            );
        }

        $host = strtolower((string) $parts['host']);
        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
            return array(
                'status'   => self::STATUS_SUSPICIOUS,
                'provider' => 'server_probe',
                'raw'      => array('reason' => 'raw_ip'),
                'error'    => '',
            );
        }

        $response = wp_remote_head(
            $url,
            array(
                'timeout'     => max(1, (int) ceil($deadline - microtime(true))),
                'redirection' => 5,
                'headers'     => array('User-Agent' => 'PAXdesign-LinkScanner/1.0'),
            )
        );

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
                return array(
                    'status'   => self::STATUS_TIMEOUT,
                    'provider' => 'server_probe',
                    'raw'      => $message,
                    'error'    => 'timeout',
                );
            }
            return array(
                'status'   => self::STATUS_INCOMPLETE,
                'provider' => 'server_probe',
                'raw'      => $message,
                'error'    => 'request_failed',
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = array(
            'http_code'      => $code,
            'effective_url'  => $url,
            'content_type'   => (string) wp_remote_retrieve_header($response, 'content-type'),
        );

        if ($code >= 500) {
            return array(
                'status'   => self::STATUS_INCOMPLETE,
                'provider' => 'server_probe',
                'raw'      => $raw,
                'error'    => 'upstream_error',
            );
        }

        $final_url = (string) wp_remote_retrieve_header($response, 'x-final-url');
        if ($final_url !== '' && stripos($final_url, $host) === false) {
            $cross = self::scan_urlhaus($final_url, $deadline);
            if ($cross['status'] !== self::STATUS_SAFE) {
                $cross['provider'] = 'server_probe+urlhaus';
                return $cross;
            }
            return array(
                'status'   => self::STATUS_SUSPICIOUS,
                'provider' => 'server_probe',
                'raw'      => array_merge($raw, array('redirect' => $final_url)),
                'error'    => '',
            );
        }

        return array('status' => self::STATUS_SAFE, 'provider' => 'server_probe', 'raw' => $raw, 'error' => '');
    }

    public static function delete_scan_rows($session_id, $message_seq) {
        global $wpdb;
        self::maybe_upgrade();
        $wpdb->delete(
            self::scans_table(),
            array(
                'session_id'  => sanitize_text_field((string) $session_id),
                'message_seq' => absint($message_seq),
            ),
            array('%s', '%d')
        );
    }

    private static function insert_scan_row($session_id, $message_seq, $url, $started_at) {
        global $wpdb;
        $wpdb->insert(
            self::scans_table(),
            array(
                'session_id'  => $session_id,
                'message_seq' => $message_seq,
                'url'         => $url,
                'status'      => self::STATUS_CHECKING,
                'provider'    => '',
                'started_at'  => $started_at,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * @param array{status: string, provider: string, raw: mixed, error: string} $outcome
     */
    private static function complete_scan_row($session_id, $message_seq, $url, $outcome) {
        global $wpdb;
        $wpdb->update(
            self::scans_table(),
            array(
                'status'       => sanitize_key($outcome['status']),
                'provider'     => sanitize_text_field((string) $outcome['provider']),
                'result_json'  => wp_json_encode($outcome['raw']),
                'error_message'=> sanitize_text_field((string) $outcome['error']),
                'completed_at' => current_time('mysql', true),
            ),
            array(
                'session_id'  => $session_id,
                'message_seq' => $message_seq,
                'url'         => $url,
            ),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%s', '%d', '%s')
        );
    }

    public static function worst_status($current, $next) {
        $rank = array(
            self::STATUS_SAFE       => 0,
            self::STATUS_CHECKING   => 1,
            self::STATUS_INCOMPLETE => 2,
            self::STATUS_FAILED     => 3,
            self::STATUS_TIMEOUT    => 4,
            self::STATUS_SUSPICIOUS => 5,
            self::STATUS_DANGEROUS  => 6,
        );
        $a = isset($rank[$current]) ? $rank[$current] : 0;
        $b = isset($rank[$next]) ? $rank[$next] : 0;
        return $b > $a ? $next : $current;
    }
}
