<?php
/**
 * Device Risk persistence. Tables when available, transients otherwise.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Fraud_Store {

    const SCHEMA_OPTION = 'pax_fraud_schema';
    const SCHEMA_VERSION = 1;
    const DEVICE_TTL     = 2592000; // 30 days
    const EVENT_TTL      = 604800;  // 7 days
    const CHALLENGE_TTL  = 600;     // 10 minutes
    const CLEAR_TTL      = 43200;   // 12 hours

    public static function maybe_install() {
        if ((int) get_option(self::SCHEMA_OPTION, 0) >= self::SCHEMA_VERSION) {
            return;
        }
        self::install_tables();
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, true);
    }

    public static function devices_table() {
        global $wpdb;
        return $wpdb->prefix . 'pax_fraud_devices';
    }

    public static function events_table() {
        global $wpdb;
        return $wpdb->prefix . 'pax_fraud_events';
    }

    public static function challenges_table() {
        global $wpdb;
        return $wpdb->prefix . 'pax_fraud_challenges';
    }

    public static function install_tables() {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        $charset = $wpdb->get_charset_collate();
        $devices = self::devices_table();
        $events  = self::events_table();
        $challenges = self::challenges_table();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$devices} (
            device_id varchar(36) NOT NULL,
            fingerprint_hash char(64) NOT NULL DEFAULT '',
            first_ip varchar(45) NOT NULL DEFAULT '',
            last_ip varchar(45) NOT NULL DEFAULT '',
            user_ids text NULL,
            signals_json mediumtext NULL,
            first_seen datetime NOT NULL,
            last_seen datetime NOT NULL,
            PRIMARY KEY  (device_id),
            KEY fingerprint_hash (fingerprint_hash),
            KEY last_ip (last_ip)
        ) {$charset};\n");

        dbDelta("CREATE TABLE {$events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created datetime NOT NULL,
            event_type varchar(40) NOT NULL DEFAULT '',
            device_id varchar(36) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            ip varchar(45) NOT NULL DEFAULT '',
            score smallint(5) unsigned NOT NULL DEFAULT 0,
            meta_json text NULL,
            PRIMARY KEY  (id),
            KEY device_id (device_id),
            KEY ip_created (ip, created)
        ) {$charset};\n");

        dbDelta("CREATE TABLE {$challenges} (
            token char(64) NOT NULL,
            device_id varchar(36) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            email varchar(190) NOT NULL DEFAULT '',
            code_hash char(64) NOT NULL DEFAULT '',
            expires int(10) unsigned NOT NULL DEFAULT 0,
            verified tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (token),
            KEY device_id (device_id)
        ) {$charset};\n");
    }

    /**
     * @param array<string, mixed> $signals
     */
    public static function upsert_device($device_id, $fingerprint_hash, $ip, array $signals) {
        $device_id = self::sanitize_device_id($device_id);
        if ($device_id === '') {
            return;
        }
        $fingerprint_hash = preg_replace('/[^a-f0-9]/', '', strtolower((string) $fingerprint_hash));
        $ip = sanitize_text_field((string) $ip);
        $now = current_time('mysql');

        global $wpdb;
        if ($wpdb && self::tables_ready()) {
            $existing = $wpdb->get_var($wpdb->prepare(
                'SELECT device_id FROM ' . self::devices_table() . ' WHERE device_id = %s',
                $device_id
            ));
            $payload = array(
                'fingerprint_hash' => $fingerprint_hash,
                'last_ip'          => $ip,
                'signals_json'     => wp_json_encode(self::trim_signals($signals)),
                'last_seen'        => $now,
            );
            if ($existing) {
                $wpdb->update(self::devices_table(), $payload, array('device_id' => $device_id));
            } else {
                $payload['device_id'] = $device_id;
                $payload['first_ip'] = $ip;
                $payload['user_ids'] = '[]';
                $payload['first_seen'] = $now;
                $wpdb->insert(self::devices_table(), $payload);
            }
            return;
        }

        set_transient('pax_fg_dev_' . $device_id, array(
            'fingerprint_hash' => $fingerprint_hash,
            'ip'               => $ip,
            'signals'          => self::trim_signals($signals),
            'user_ids'         => self::device_user_ids($device_id),
            'seen'             => time(),
        ), self::DEVICE_TTL);
    }

    public static function bind_user($device_id, $user_id) {
        $device_id = self::sanitize_device_id($device_id);
        $user_id   = (int) $user_id;
        if ($device_id === '' || $user_id <= 0) {
            return;
        }
        $ids = self::device_user_ids($device_id);
        if (!in_array($user_id, $ids, true)) {
            $ids[] = $user_id;
            $ids = array_slice($ids, -20);
        }
        self::write_user_ids($device_id, $ids);

        $hash = self::fingerprint_for_device($device_id);
        if ($hash !== '') {
            $map = get_transient('pax_fg_fp_' . $hash);
            if (!is_array($map)) {
                $map = array();
            }
            $map[] = $user_id;
            $map = array_values(array_unique(array_map('intval', $map)));
            set_transient('pax_fg_fp_' . $hash, $map, self::DEVICE_TTL);
        }

        $ip = self::client_ip();
        if ($ip !== '') {
            $ip_map = get_transient('pax_fg_ip_users_' . md5($ip));
            if (!is_array($ip_map)) {
                $ip_map = array();
            }
            $ip_map[] = $user_id;
            $ip_map = array_values(array_unique(array_map('intval', $ip_map)));
            set_transient('pax_fg_ip_users_' . md5($ip), $ip_map, DAY_IN_SECONDS);
        }
    }

    public static function device_user_ids($device_id) {
        $device_id = self::sanitize_device_id($device_id);
        if ($device_id === '') {
            return array();
        }
        global $wpdb;
        if ($wpdb && self::tables_ready()) {
            $raw = $wpdb->get_var($wpdb->prepare(
                'SELECT user_ids FROM ' . self::devices_table() . ' WHERE device_id = %s',
                $device_id
            ));
            $ids = json_decode((string) $raw, true);
            return is_array($ids) ? array_map('intval', $ids) : array();
        }
        $row = get_transient('pax_fg_dev_' . $device_id);
        if (is_array($row) && isset($row['user_ids']) && is_array($row['user_ids'])) {
            return array_map('intval', $row['user_ids']);
        }
        return array();
    }

    public static function fingerprint_account_count($fingerprint_hash) {
        $fingerprint_hash = preg_replace('/[^a-f0-9]/', '', strtolower((string) $fingerprint_hash));
        if ($fingerprint_hash === '') {
            return 0;
        }
        $map = get_transient('pax_fg_fp_' . $fingerprint_hash);
        if (is_array($map)) {
            return count(array_unique(array_map('intval', $map)));
        }
        global $wpdb;
        if ($wpdb && self::tables_ready()) {
            $raws = $wpdb->get_col($wpdb->prepare(
                'SELECT user_ids FROM ' . self::devices_table() . ' WHERE fingerprint_hash = %s LIMIT 20',
                $fingerprint_hash
            ));
            $ids = array();
            foreach ((array) $raws as $raw) {
                $parsed = json_decode((string) $raw, true);
                if (is_array($parsed)) {
                    $ids = array_merge($ids, array_map('intval', $parsed));
                }
            }
            return count(array_unique($ids));
        }
        return 0;
    }

    public static function ip_account_count($ip) {
        $ip = sanitize_text_field((string) $ip);
        if ($ip === '') {
            return 0;
        }
        $map = get_transient('pax_fg_ip_users_' . md5($ip));
        return is_array($map) ? count(array_unique(array_map('intval', $map))) : 0;
    }

    public static function is_known_device($device_id, $user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        return in_array($user_id, self::device_user_ids($device_id), true);
    }

    public static function record_event($event_type, $device_id, $user_id, $ip, $score, array $meta = array()) {
        $event_type = sanitize_key((string) $event_type);
        $device_id  = self::sanitize_device_id($device_id);
        $user_id    = (int) $user_id;
        $ip         = sanitize_text_field((string) $ip);
        $score      = (int) $score;

        global $wpdb;
        if ($wpdb && self::tables_ready()) {
            $wpdb->insert(self::events_table(), array(
                'created'    => current_time('mysql'),
                'event_type' => $event_type,
                'device_id'  => $device_id,
                'user_id'    => $user_id,
                'ip'         => $ip,
                'score'      => $score,
                'meta_json'  => wp_json_encode($meta),
            ));
            return;
        }

        $key = 'pax_fg_ev_' . md5($device_id . '|' . $ip);
        $events = get_transient($key);
        if (!is_array($events)) {
            $events = array();
        }
        $events[] = array(
            't' => time(),
            'e' => $event_type,
            's' => $score,
        );
        $events = array_slice($events, -40);
        set_transient($key, $events, self::EVENT_TTL);
    }

    public static function bump_velocity($bucket, $id) {
        $id = sanitize_text_field((string) $id);
        if ($id === '') {
            return 0;
        }
        $key = 'pax_fg_vel_' . $bucket . '_' . md5($id);
        $count = (int) get_transient($key);
        $count++;
        set_transient($key, $count, MINUTE_IN_SECONDS);
        return $count;
    }

    public static function failed_login_count($email_or_ip) {
        $key = 'pax_fg_fail_' . md5(strtolower((string) $email_or_ip));
        return (int) get_transient($key);
    }

    public static function record_failed_login($email, $ip) {
        foreach (array($email, $ip) as $id) {
            if ($id === '') {
                continue;
            }
            $key = 'pax_fg_fail_' . md5(strtolower((string) $id));
            set_transient($key, (int) get_transient($key) + 1, HOUR_IN_SECONDS);
        }
    }

    /**
     * @return array{token:string,code:string}|null
     */
    public static function create_challenge($device_id, $email, $user_id = 0) {
        $device_id = self::sanitize_device_id($device_id);
        $email     = sanitize_email((string) $email);
        if ($email === '' || !is_email($email)) {
            return null;
        }
        $token = hash('sha256', wp_generate_uuid4() . '|' . microtime(true) . '|' . wp_salt('auth'));
        $code  = sprintf('%06d', random_int(0, 999999));
        $hash  = hash('sha256', $code . wp_salt('nonce'));
        $expires = time() + self::CHALLENGE_TTL;

        $otp_key = 'pax_fg_otp_' . md5(strtolower($email));
        $otp_count = (int) get_transient($otp_key);
        if ($otp_count >= 5) {
            return null;
        }
        set_transient($otp_key, $otp_count + 1, 15 * MINUTE_IN_SECONDS);

        $row = array(
            'token'     => $token,
            'device_id' => $device_id,
            'user_id'   => (int) $user_id,
            'email'     => $email,
            'code_hash' => $hash,
            'expires'   => $expires,
            'verified'  => 0,
        );

        global $wpdb;
        if ($wpdb && self::tables_ready()) {
            $wpdb->replace(self::challenges_table(), $row);
        }
        set_transient('pax_fg_ch_' . $token, $row, self::CHALLENGE_TTL);

        return array('token' => $token, 'code' => $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function verify_challenge($token, $code) {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) $token));
        $code  = preg_replace('/\D/', '', (string) $code);
        if (strlen($token) !== 64 || strlen($code) !== 6) {
            return null;
        }

        $row = get_transient('pax_fg_ch_' . $token);
        if (!is_array($row)) {
            global $wpdb;
            if ($wpdb && self::tables_ready()) {
                $row = $wpdb->get_row($wpdb->prepare(
                    'SELECT * FROM ' . self::challenges_table() . ' WHERE token = %s',
                    $token
                ), ARRAY_A);
            }
        }
        if (!is_array($row) || (int) ($row['verified'] ?? 0) === 1) {
            return null;
        }
        if ((int) ($row['expires'] ?? 0) < time()) {
            return null;
        }
        $expected = (string) ($row['code_hash'] ?? '');
        $given    = hash('sha256', $code . wp_salt('nonce'));
        if ($expected === '' || !hash_equals($expected, $given)) {
            return null;
        }

        $row['verified'] = 1;
        set_transient('pax_fg_ch_' . $token, $row, self::CHALLENGE_TTL);
        self::mark_cleared((string) ($row['device_id'] ?? ''), (int) ($row['user_id'] ?? 0), (string) ($row['email'] ?? ''));

        global $wpdb;
        if ($wpdb && self::tables_ready()) {
            $wpdb->update(self::challenges_table(), array('verified' => 1), array('token' => $token));
        }

        return $row;
    }

    public static function challenge_is_open($token) {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) $token));
        if (strlen($token) !== 64) {
            return false;
        }
        $row = get_transient('pax_fg_ch_' . $token);
        return is_array($row) && (int) ($row['verified'] ?? 0) === 1 && (int) ($row['expires'] ?? 0) >= time();
    }

    public static function mark_cleared($device_id, $user_id, $email = '') {
        $device_id = self::sanitize_device_id($device_id);
        $keys = array();
        if ($device_id !== '') {
            $keys[] = 'pax_fg_ok_' . $device_id . '_' . (int) $user_id;
            if ($email !== '') {
                $keys[] = 'pax_fg_ok_' . $device_id . '_em_' . md5(strtolower($email));
            }
        }
        foreach ($keys as $key) {
            set_transient($key, 1, self::CLEAR_TTL);
        }
    }

    public static function is_cleared($device_id, $user_id = 0, $email = '') {
        $device_id = self::sanitize_device_id($device_id);
        if ($device_id === '') {
            return false;
        }
        if ((int) get_transient('pax_fg_ok_' . $device_id . '_' . (int) $user_id)) {
            return true;
        }
        if ($email !== '' && (int) get_transient('pax_fg_ok_' . $device_id . '_em_' . md5(strtolower($email)))) {
            return true;
        }
        return false;
    }

    public static function fingerprint_for_device($device_id) {
        $device_id = self::sanitize_device_id($device_id);
        if ($device_id === '') {
            return '';
        }
        $row = get_transient('pax_fg_dev_' . $device_id);
        if (is_array($row) && !empty($row['fingerprint_hash'])) {
            return (string) $row['fingerprint_hash'];
        }
        global $wpdb;
        if ($wpdb && self::tables_ready()) {
            return (string) $wpdb->get_var($wpdb->prepare(
                'SELECT fingerprint_hash FROM ' . self::devices_table() . ' WHERE device_id = %s',
                $device_id
            ));
        }
        return '';
    }

    public static function cached_score($device_id) {
        $device_id = self::sanitize_device_id($device_id);
        if ($device_id === '') {
            return null;
        }
        $cached = get_transient('pax_fg_score_' . $device_id);
        return is_array($cached) ? $cached : null;
    }

    public static function cache_score($device_id, array $result) {
        $device_id = self::sanitize_device_id($device_id);
        if ($device_id === '') {
            return;
        }
        set_transient('pax_fg_score_' . $device_id, $result, 5 * MINUTE_IN_SECONDS);
    }

    public static function client_ip() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '';
    }

    public static function sanitize_device_id($device_id) {
        $device_id = strtolower(trim((string) $device_id));
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $device_id)) {
            return '';
        }
        return $device_id;
    }

    /**
     * @param array<string, mixed> $signals
     * @return array<string, mixed>
     */
    public static function trim_signals(array $signals) {
        $allowed = array(
            'webdriver', 'ua', 'platform', 'languages', 'timezone', 'timezone_offset',
            'screen_w', 'screen_h', 'avail_w', 'avail_h', 'color_depth', 'pixel_ratio',
            'hardware_concurrency', 'device_memory', 'max_touch_points', 'canvas',
            'webgl_vendor', 'webgl_renderer', 'plugins', 'cookie_enabled', 'dnt',
            'vendor', 'collected_ms', 'has_storage', 'touch',
        );
        $out = array();
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $signals)) {
                continue;
            }
            $value = $signals[$key];
            if (is_bool($value) || is_int($value) || is_float($value)) {
                $out[$key] = $value;
            } elseif (is_array($value)) {
                $out[$key] = array_slice(array_map('strval', $value), 0, 8);
            } else {
                $out[$key] = substr(sanitize_text_field((string) $value), 0, 240);
            }
        }
        return $out;
    }

    private static function write_user_ids($device_id, array $ids) {
        global $wpdb;
        if ($wpdb && self::tables_ready()) {
            $wpdb->update(
                self::devices_table(),
                array('user_ids' => wp_json_encode(array_values($ids))),
                array('device_id' => $device_id)
            );
        }
        $row = get_transient('pax_fg_dev_' . $device_id);
        if (!is_array($row)) {
            $row = array();
        }
        $row['user_ids'] = array_values($ids);
        set_transient('pax_fg_dev_' . $device_id, $row, self::DEVICE_TTL);
    }

    private static function tables_ready() {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = (int) get_option(self::SCHEMA_OPTION, 0) >= self::SCHEMA_VERSION;
        return $ready;
    }
}
