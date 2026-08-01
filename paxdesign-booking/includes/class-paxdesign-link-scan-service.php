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
    const TIMEOUT_SECONDS   = 35;
    const MIN_SCAN_SECONDS  = 3;
    const TARGET_SCAN_SECONDS = 4;
    const PER_PROVIDER_SECONDS = 4;
    const CANCELLED_OPTION  = 'paxdesign_cancelled_link_scans';

    /** @var array<int, array{0: string, 1: int}> */
    private static $dispatch_queue = array();

    /** @var array<string, true> */
    private static $cancelled_scans = array();

    /** @var array<string, true> */
    private static $active_scans = array();

    public static function init() {
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_message_scan'), 10, 2);
        add_action('shutdown', array(__CLASS__, 'flush_dispatch_queue'), 1);
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
        $extra['link_scan_original_content'] = (string) $content;
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
        wp_schedule_single_event(time() + 5, self::CRON_HOOK, array($session_id, $message_seq));
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

        delete_option(self::scan_lock_option($session_id, $message_seq));
        unset(self::$active_scans[self::scan_key($session_id, $message_seq)]);

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

    private static function scan_lock_option($session_id, $message_seq) {
        return 'paxdesign_scan_lock_' . md5(self::scan_key($session_id, $message_seq));
    }

    private static function unschedule_scan_cron($session_id, $message_seq) {
        while (($timestamp = wp_next_scheduled(self::CRON_HOOK, array($session_id, $message_seq))) !== false) {
            wp_unschedule_event($timestamp, self::CRON_HOOK, array($session_id, $message_seq));
        }
    }

    private static function try_begin_scan($session_id, $message_seq) {
        $key = self::scan_key($session_id, $message_seq);
        if (!empty(self::$active_scans[$key])) {
            return false;
        }
        $lock_key = self::scan_lock_option($session_id, $message_seq);
        $existing = (int) get_option($lock_key, 0);
        if ($existing > 0) {
            if (time() - $existing < self::TIMEOUT_SECONDS + 20) {
                return false;
            }
            delete_option($lock_key);
        }
        self::$active_scans[$key] = true;
        update_option($lock_key, time(), false);
        return true;
    }

    private static function end_scan($session_id, $message_seq) {
        $key = self::scan_key($session_id, $message_seq);
        unset(self::$active_scans[$key]);
        delete_option(self::scan_lock_option($session_id, $message_seq));
        self::unschedule_scan_cron($session_id, $message_seq);
    }

    public static function flush_dispatch_queue() {
        if (empty(self::$dispatch_queue)) {
            return;
        }
        if (class_exists('PAXdesign_DB')) {
            PAXdesign_DB::drain_connection();
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        $queue = self::$dispatch_queue;
        self::$dispatch_queue = array();
        foreach ($queue as $item) {
            if (self::is_scan_cancelled($item[0], $item[1])) {
                continue;
            }
            self::run_message_scan($item[0], $item[1]);
        }
        if (class_exists('PAXdesign_DB')) {
            PAXdesign_DB::drain_connection();
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

        if (!empty($message['link_scan_completed_at'])) {
            self::unschedule_scan_cron($session_id, $message_seq);
            return;
        }

        if (!self::try_begin_scan($session_id, $message_seq)) {
            return;
        }

        $urls = self::urls_from_message($message);
        if (empty($urls)) {
            self::end_scan($session_id, $message_seq);
            return;
        }

        self::delete_scan_rows($session_id, $message_seq);
        $started_at = !empty($message['link_scan_started_at'])
            ? gmdate('Y-m-d H:i:s', absint($message['link_scan_started_at']))
            : current_time('mysql', true);

        foreach ($urls as $url) {
            self::insert_scan_row($session_id, $message_seq, $url, $started_at);
        }

        $scan_started = microtime(true);
        $scan_deadline = $scan_started + (float) self::TIMEOUT_SECONDS;
        $results   = array();
        $providers = array();
        $worst     = self::STATUS_SAFE;

        foreach ($urls as $url) {
            if (self::is_scan_cancelled($session_id, $message_seq)) {
                self::delete_scan_rows($session_id, $message_seq);
                self::end_scan($session_id, $message_seq);
                return;
            }
            if (!PAXdesign_Message_Store::get_message($session_id, $message_seq)) {
                self::delete_scan_rows($session_id, $message_seq);
                self::end_scan($session_id, $message_seq);
                return;
            }
            $outcome = self::scan_url_remote($url, array(
                'deadline'      => $scan_deadline,
                'per_provider'  => (float) self::PER_PROVIDER_SECONDS,
            ));
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
        }

        $system_status = $worst;
        $customer_status = $worst;
        if (!in_array($worst, array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)) {
            $customer_status = self::STATUS_SAFE;
        }

        $elapsed = microtime(true) - $scan_started;
        if ($elapsed < (float) self::MIN_SCAN_SECONDS) {
            usleep((int) (((float) self::MIN_SCAN_SECONDS - $elapsed) * 1000000));
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
            self::end_scan($session_id, $message_seq);
            return;
        }
        if (!PAXdesign_Message_Store::get_message($session_id, $message_seq)) {
            self::delete_scan_rows($session_id, $message_seq);
            self::end_scan($session_id, $message_seq);
            return;
        }

        if (!empty(PAXdesign_Message_Store::get_message($session_id, $message_seq)['link_scan_completed_at'])) {
            self::end_scan($session_id, $message_seq);
            return;
        }

        $language = self::resolve_customer_language($session_id);
        $analysis = self::build_analysis_text($customer_status, $results, $provider_label, $language);
        $review_pending = in_array($system_status, array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true) ? '1' : '';

        $meta_updates = array(
            'link_scan_status'        => $customer_status,
            'link_scan_system_status' => $system_status,
            'link_scan_urls'          => wp_json_encode($results),
            'link_scan_completed_at'  => $completed,
            'link_scan_provider'      => $provider_label,
            'link_scan_label'         => self::status_label($customer_status, $language),
            'link_scan_analysis'      => $analysis,
            'link_scan_frame'         => 0,
        );
        if ($review_pending !== '') {
            $meta_updates['link_scan_review_pending'] = $review_pending;
        }

        $updated = PAXdesign_Message_Store::update_message_meta(
            $session_id,
            $message_seq,
            $meta_updates,
            'customer'
        );

        if (is_wp_error($updated) || !is_array($updated)) {
            self::end_scan($session_id, $message_seq);
            return;
        }

        self::end_scan($session_id, $message_seq);
        self::emit_customer_scan_event($session_id, $message_seq, 'link_scan_updated');

        $admin_message = PAXdesign_Message_Store::get_message($session_id, $message_seq);
        if ($admin_message && class_exists('PAXdesign_Chat_Live')) {
            $admin_payload = array(
                'session_id' => $session_id,
                'seq'        => $message_seq,
                'message'    => PAXdesign_Chat_Live::get_instance()->format_sse_message_payload($admin_message, 0),
            );
            PAXdesign_Message_Store::emit('inbox:admins', 'link_scan_updated', $admin_payload, $message_seq);
            if ($review_pending !== '') {
                PAXdesign_Message_Store::emit('inbox:admins', 'link_scan_review_ready', $admin_payload, $message_seq);
            }
        }
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

        $deadline = isset($options['deadline'])
            ? (float) $options['deadline']
            : microtime(true) + (float) self::TIMEOUT_SECONDS;
        $per_provider = isset($options['per_provider'])
            ? (float) $options['per_provider']
            : (float) self::PER_PROVIDER_SECONDS;

        $provider_deadline = function () use ($deadline, $per_provider) {
            return min($deadline, microtime(true) + $per_provider);
        };

        $gsb = self::scan_google_safe_browsing($url, $provider_deadline());
        if (in_array($gsb['status'], array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)) {
            return $gsb;
        }

        if (microtime(true) >= $deadline) {
            return self::best_effort_outcome($gsb);
        }

        $haus = self::scan_urlhaus($url, $provider_deadline());
        if (in_array($haus['status'], array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)) {
            return $haus;
        }

        if (microtime(true) >= $deadline) {
            return self::best_effort_outcome($haus, $gsb);
        }

        $tank = self::scan_phishtank($url, $provider_deadline());
        if (in_array($tank['status'], array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)) {
            return $tank;
        }

        if (microtime(true) >= $deadline) {
            return self::best_effort_outcome($tank, $haus, $gsb);
        }

        $probe = self::scan_server_probe($url, $provider_deadline());
        if (in_array($probe['status'], array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)) {
            return $probe;
        }

        return self::best_effort_outcome($probe, $tank, $haus, $gsb);
    }

    /**
     * @param array{status: string, provider: string, raw: mixed, error: string} ...$outcomes
     * @return array{status: string, provider: string, raw: mixed, error: string}
     */
    private static function best_effort_outcome(...$outcomes) {
        $best = array(
            'status'   => self::STATUS_SAFE,
            'provider' => 'server',
            'raw'      => null,
            'error'    => '',
        );
        $providers = array();
        foreach ($outcomes as $outcome) {
            if (!is_array($outcome)) {
                continue;
            }
            $best['status'] = self::worst_status($best['status'], (string) ($outcome['status'] ?? self::STATUS_SAFE));
            if (!empty($outcome['provider'])) {
                $providers[(string) $outcome['provider']] = true;
            }
            if (in_array($best['status'], array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)) {
                $best['provider'] = (string) $outcome['provider'];
                $best['raw'] = $outcome['raw'] ?? null;
                $best['error'] = (string) ($outcome['error'] ?? '');
                return $best;
            }
        }
        if (!empty($providers)) {
            $best['provider'] = implode('+', array_keys($providers));
        }
        if (!in_array($best['status'], array(self::STATUS_DANGEROUS, self::STATUS_SUSPICIOUS), true)) {
            $best['status'] = self::STATUS_SAFE;
        }
        return $best;
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
        if (self::is_blocked_probe_host($host)) {
            return array(
                'status'   => self::STATUS_DANGEROUS,
                'provider' => 'server_probe',
                'raw'      => array('reason' => 'blocked_host'),
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

    /**
     * @param string $session_id
     * @return string de|en|ar
     */
    public static function resolve_customer_language($session_id) {
        if ($session_id !== '' && class_exists('PAXdesign_Language_Routing')) {
            return PAXdesign_Language_Routing::resolve_session_language($session_id, '');
        }
        return 'de';
    }

    /**
     * @param string $status
     * @param string $lang
     * @return string
     */
    public static function status_label($status, $lang = 'de') {
        $lang = sanitize_key((string) $lang);
        if (!in_array($lang, array('de', 'en', 'ar'), true)) {
            $lang = 'de';
        }
        $labels = array(
            self::STATUS_CHECKING => array(
                'de' => 'Sicherheitsprüfung läuft …',
                'en' => 'Security scan in progress …',
                'ar' => 'جاري فحص الأمان …',
            ),
            self::STATUS_SAFE => array(
                'de' => 'Sicherer Link',
                'en' => 'Safe link',
                'ar' => 'رابط آمن',
            ),
            self::STATUS_SUSPICIOUS => array(
                'de' => 'Verdächtiger Link',
                'en' => 'Suspicious link',
                'ar' => 'رابط مشبوه',
            ),
            self::STATUS_DANGEROUS => array(
                'de' => 'Gefährlicher Link',
                'en' => 'Dangerous link',
                'ar' => 'رابط خطير',
            ),
            self::STATUS_FAILED => array(
                'de' => 'Scan fehlgeschlagen',
                'en' => 'Scan failed',
                'ar' => 'فشل الفحص',
            ),
            self::STATUS_TIMEOUT => array(
                'de' => 'Scan-Zeitüberschreitung',
                'en' => 'Scan timed out',
                'ar' => 'انتهت مهلة الفحص',
            ),
            self::STATUS_INCOMPLETE => array(
                'de' => 'Scan unvollständig',
                'en' => 'Scan incomplete',
                'ar' => 'الفحص غير مكتمل',
            ),
        );
        if (!isset($labels[$status])) {
            return '';
        }
        return $labels[$status][$lang] ?? $labels[$status]['de'];
    }

    /**
     * @param string               $worst
     * @param array<int, array<string, mixed>> $results
     * @param string               $provider_label
     * @param string               $lang
     * @return string
     */
    public static function build_analysis_text($worst, $results, $provider_label, $lang = 'de') {
        $lang = sanitize_key((string) $lang);
        if (!in_array($lang, array('de', 'en', 'ar'), true)) {
            $lang = 'de';
        }
        $count = count($results);
        $providers = sanitize_text_field((string) $provider_label);
        if ($providers === '') {
            $providers = 'server';
        }

        if ($worst === self::STATUS_SAFE) {
            if ($lang === 'en') {
                return sprintf('Security scan complete: %d link(s) checked via %s. No threats detected.', $count, $providers);
            }
            if ($lang === 'ar') {
                return sprintf('اكتمل فحص الأمان: تم فحص %d رابط/روابط عبر %s. لم يتم العثور على تهديدات.', $count, $providers);
            }
            return sprintf('Sicherheitsprüfung abgeschlossen: %d Link(s) über %s geprüft. Keine Bedrohungen erkannt.', $count, $providers);
        }
        if ($worst === self::STATUS_SUSPICIOUS) {
            if ($lang === 'en') {
                return sprintf('Security scan complete: suspicious signals detected (%s). Open with caution.', $providers);
            }
            if ($lang === 'ar') {
                return sprintf('اكتمل فحص الأمان: تم رصد إشارات مشبوهة (%s). افتح الرابط بحذر.', $providers);
            }
            return sprintf('Sicherheitsprüfung abgeschlossen: verdächtige Signale erkannt (%s). Link mit Vorsicht öffnen.', $providers);
        }
        if ($worst === self::STATUS_DANGEROUS) {
            if ($lang === 'en') {
                return sprintf('Security scan complete: this link was flagged as dangerous (%s). Do not open it.', $providers);
            }
            if ($lang === 'ar') {
                return sprintf('اكتمل فحص الأمان: تم تصنيف هذا الرابط على أنه خطير (%s). لا تفتحه.', $providers);
            }
            return sprintf('Sicherheitsprüfung abgeschlossen: dieser Link wurde als gefährlich eingestuft (%s). Nicht öffnen.', $providers);
        }
        if ($lang === 'en') {
            return sprintf('Security scan could not be completed for all providers (%s).', $providers);
        }
        if ($lang === 'ar') {
            return sprintf('تعذر إكمال فحص الأمان لجميع المزودين (%s).', $providers);
        }
        return sprintf('Sicherheitsprüfung konnte nicht vollständig abgeschlossen werden (%s).', $providers);
    }

    /**
     * Deterministic same-length URL scramble for live scan animation frames.
     */
    public static function scramble_url($url, $frame) {
        $url = (string) $url;
        $len = function_exists('mb_strlen') ? mb_strlen($url) : strlen($url);
        if ($len <= 0) {
            return $url;
        }
        $chars = '0123456789abcdef•·∙▪▫◦×÷+=@#$_-';
        $char_len = strlen($chars);
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $seed = crc32($url . ':' . (int) $frame . ':' . $i);
            $out .= $chars[$seed % $char_len];
        }
        return $out;
    }

    /**
     * @param string               $content
     * @param array<string, mixed> $message
     * @param int                  $frame
     * @return string
     */
    public static function apply_scrambled_urls_to_content($content, $message, $frame) {
        $urls = self::urls_from_message($message);
        if (empty($urls) && class_exists('PAXdesign_Link_Scanner')) {
            $urls = PAXdesign_Link_Scanner::extract_urls((string) $content);
        }
        foreach ($urls as $url) {
            $scrambled = self::scramble_url($url, $frame);
            $content = str_replace($url, $scrambled, $content);
        }
        return $content;
    }

    /**
     * @param array<string, mixed> $message
     * @param string               $lang
     * @return string
     */
    public static function analysis_from_message($message, $lang = 'de') {
        $results = array();
        if (!empty($message['link_scan_urls'])) {
            $decoded = json_decode((string) $message['link_scan_urls'], true);
            if (is_array($decoded)) {
                $results = $decoded;
            }
        }
        $status = sanitize_key((string) ($message['link_scan_status'] ?? ''));
        $provider = (string) ($message['link_scan_provider'] ?? 'server');
        if ($status !== '' && self::is_final_status($status)) {
            return self::build_analysis_text($status, $results, $provider, $lang);
        }
        if (!empty($message['link_scan_analysis'])) {
            return (string) $message['link_scan_analysis'];
        }
        return self::build_analysis_text(self::STATUS_INCOMPLETE, $results, $provider, $lang);
    }

    /**
     * Customer-facing message enrichment (poll, SSE, mobile).
     *
     * @param array<string, mixed> $message
     * @param string               $session_id
     * @return array<string, mixed>
     */
    public static function format_customer_message($message, $session_id = '') {
        if (!is_array($message) || empty($message['link_scan_status'])) {
            return $message;
        }

        $lang = self::resolve_customer_language($session_id);
        $status = sanitize_key((string) $message['link_scan_status']);
        unset($message['link_scan_system_status'], $message['link_scan_review_pending']);

        if ($status === self::STATUS_CHECKING) {
            $original = (string) ($message['link_scan_original_content'] ?? $message['content'] ?? '');
            $frame = absint($message['link_scan_frame'] ?? 0);
            if ($frame <= 0) {
                $frame = absint($message['link_scan_started_at'] ?? time());
            }
            $message['link_scan_original_content'] = $original;
            $message['content'] = self::apply_scrambled_urls_to_content($original, $message, $frame);
            $message['link_scan_label'] = self::status_label(self::STATUS_CHECKING, $lang);
            return $message;
        }

        if (!self::is_final_status($status)) {
            $message['link_scan_label'] = self::status_label($status, $lang);
            return $message;
        }

        $original = (string) ($message['link_scan_original_content'] ?? $message['content'] ?? '');
        if ($original !== '') {
            $message['content'] = $original;
        }

        $analysis = self::analysis_from_message($message, $lang);
        $message['link_scan_label'] = self::status_label($status, $lang);
        $message['link_scan_analysis'] = $analysis;
        return $message;
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function is_final_status($status) {
        return in_array(
            sanitize_key((string) $status),
            array(
                self::STATUS_SAFE,
                self::STATUS_SUSPICIOUS,
                self::STATUS_DANGEROUS,
                self::STATUS_FAILED,
                self::STATUS_TIMEOUT,
                self::STATUS_INCOMPLETE,
            ),
            true
        );
    }

    /**
     * @deprecated Progress frames are rendered client-side; kept for backwards compatibility.
     */
    public static function emit_scan_progress($session_id, $message_seq, $frame) {
        unset($session_id, $message_seq, $frame);
    }

    /**
     * @param string $session_id
     * @param int    $message_seq
     * @param string $event_type
     */
    public static function emit_customer_scan_event($session_id, $message_seq, $event_type = 'link_scan_updated') {
        if (!class_exists('PAXdesign_Message_Store') || !class_exists('PAXdesign_Chat_Live')) {
            return;
        }
        $message = PAXdesign_Message_Store::get_message($session_id, $message_seq);
        if (!$message) {
            return;
        }
        $customer = self::format_customer_message($message, $session_id);
        $payload_message = PAXdesign_Chat_Live::get_instance()->format_sse_message_payload($customer, 0);
        $payload = array(
            'session_id' => $session_id,
            'seq'        => $message_seq,
            'message'    => $payload_message,
        );
        PAXdesign_Message_Store::emit('session:' . $session_id, $event_type, $payload, $message_seq);
    }

    /**
     * @param string $ip
     * @return bool
     */
    private static function is_private_or_reserved_ip($ip) {
        if (!is_string($ip) || $ip === '') {
            return true;
        }
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Block SSRF targets such as localhost, cloud metadata, and private networks.
     *
     * @param string $host
     * @return bool
     */
    private static function is_blocked_probe_host($host) {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return true;
        }

        $host = trim($host, '[]');

        if (in_array($host, array('localhost', 'localhost.localdomain', 'metadata', 'metadata.google.internal'), true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::is_private_or_reserved_ip($host);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (!is_array($records) || $records === array()) {
            return false;
        }

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $ip = '';
            if (!empty($record['ip'])) {
                $ip = (string) $record['ip'];
            } elseif (!empty($record['ipv6'])) {
                $ip = (string) $record['ipv6'];
            }
            if ($ip !== '' && self::is_private_or_reserved_ip($ip)) {
                return true;
            }
        }

        return false;
    }
}
