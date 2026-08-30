<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Otp {
    const TTL = 600;
    const MAX_ATTEMPTS = 5;

    public static function table() {
        return Alb_Install::table('otp_challenges');
    }

    public static function normalize_phone($phone) {
        $phone = trim((string) $phone);
        $phone = preg_replace('/[\s().-]/', '', $phone);
        if (strpos($phone, '00') === 0) {
            $phone = '+' . substr($phone, 2);
        }
        if (preg_match('/^0[1-9]/', $phone)) {
            $phone = '+43' . substr($phone, 1);
        }
        if (preg_match('/^[1-9][0-9]{7,14}$/', $phone)) {
            $phone = '+' . $phone;
        }
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
            return new WP_Error('alb_phone', Alb_I18n::t('handover.error.phone'), array('status' => 400));
        }
        return $phone;
    }

    public static function request($scanner_id, $full_name, $phone, $photo_path) {
        $phone = self::normalize_phone($phone);
        if (is_wp_error($phone)) {
            return $phone;
        }
        if (!Alb_Settings::sms_ready()) {
            return new WP_Error('alb_sms', Alb_I18n::t('otp.error.not_configured'), array('status' => 503));
        }
        if (self::too_many($phone)) {
            return new WP_Error('alb_limited', Alb_I18n::t('otp.error.limited'), array('status' => 429));
        }
        $code = (string) random_int(100000, 999999);
        $now = Alb_Settings::now_mysql();
        global $wpdb;
        $wpdb->insert(self::table(), array(
            'scanner_id' => (int) $scanner_id,
            'full_name' => $full_name,
            'phone' => $phone,
            'photo_path' => $photo_path,
            'code_hash' => wp_hash_password($code),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::TTL),
            'attempts' => 0,
            'consumed_at' => null,
            'ip_address' => self::ip(),
            'created_at' => $now,
        ));
        $sent = self::send_sms($phone, $code);
        if (is_wp_error($sent)) {
            return $sent;
        }
        Alb_Audit::record(array(
            'action' => 'otp_sent',
            'entity_type' => 'scanner',
            'entity_id' => (int) $scanner_id,
            'scanner_id' => (int) $scanner_id,
            'field' => 'phone',
            'new' => self::mask_phone($phone),
            'actor_id' => 0,
            'actor_name' => $full_name,
        ));
        return array(
            'ok' => true,
            'phone' => $phone,
            'phone_masked' => self::mask_phone($phone),
            'expires_in' => self::TTL,
        );
    }

    public static function verify($scanner_id, $phone, $code) {
        $phone = self::normalize_phone($phone);
        if (is_wp_error($phone)) {
            return $phone;
        }
        $code = preg_replace('/\D/', '', (string) $code);
        if (strlen($code) !== 6) {
            return new WP_Error('alb_otp', Alb_I18n::t('otp.error.invalid'), array('status' => 400));
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE scanner_id = %d AND phone = %s AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            (int) $scanner_id,
            $phone
        ), ARRAY_A);
        if (!$row) {
            return new WP_Error('alb_otp', Alb_I18n::t('otp.error.invalid'), array('status' => 400));
        }
        if (strtotime($row['expires_at'] . ' UTC') < time()) {
            return new WP_Error('alb_otp', Alb_I18n::t('otp.error.expired'), array('status' => 400));
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return new WP_Error('alb_limited', Alb_I18n::t('otp.error.limited'), array('status' => 429));
        }
        $wpdb->update(self::table(), array('attempts' => (int) $row['attempts'] + 1), array('id' => (int) $row['id']));
        if (!wp_check_password($code, $row['code_hash'])) {
            return new WP_Error('alb_otp', Alb_I18n::t('otp.error.invalid'), array('status' => 400));
        }
        $wpdb->update(self::table(), array('consumed_at' => Alb_Settings::now_mysql()), array('id' => (int) $row['id']));
        return $row;
    }

    public static function mask_phone($phone) {
        $phone = (string) $phone;
        if (strlen($phone) < 6) {
            return $phone;
        }
        return substr($phone, 0, 4) . str_repeat('•', max(0, strlen($phone) - 7)) . substr($phone, -3);
    }

    public static function send_sms($phone, $code) {
        $settings = Alb_Settings::get();
        $sid = $settings['twilio_sid'];
        $token = $settings['twilio_token'];
        $from = $settings['twilio_from'];
        if ($sid === '' || $token === '' || $from === '') {
            return new WP_Error('alb_sms', Alb_I18n::t('otp.error.not_configured'), array('status' => 503));
        }
        $body = Alb_I18n::t('otp.sms_body', array('code' => $code));
        $response = wp_remote_post('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json', array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token),
            ),
            'body' => array(
                'To' => $phone,
                'From' => $from,
                'Body' => $body,
            ),
        ));
        if (is_wp_error($response)) {
            return new WP_Error('alb_sms', Alb_I18n::t('otp.error.send_failed'), array('status' => 502));
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new WP_Error('alb_sms', Alb_I18n::t('otp.error.send_failed'), array('status' => 502));
        }
        return true;
    }

    private static function too_many($phone) {
        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE phone = %s AND created_at > %s',
            $phone,
            gmdate('Y-m-d H:i:s', time() - 3600)
        ));
        return $count >= 5;
    }

    private static function ip() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($parts[0]));
        }
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    }
}
