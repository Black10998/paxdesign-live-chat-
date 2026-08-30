<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Settings {
    const OPTION = 'alb_scanner_settings';

    public static function logo_url() {
        return ALB_SCANNER_PLUGIN_URL . ALB_SCANNER_LOGO_FILE;
    }

    public static function official_url() {
        return ALB_SCANNER_OFFICIAL_URL;
    }

    public static function defaults() {
        return array(
            'company_name' => 'Albatros Scannerverwaltung',
            'owner_name' => 'Ahmad Al Khalaf',
            'default_language' => 'de',
            'timezone' => 'Europe/Vienna',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'items_per_page' => 25,
            'min_password_length' => 10,
            'remember_days' => 14,
            'audit_retention_days' => 0,
            'twilio_sid' => '',
            'twilio_token' => '',
            'twilio_from' => '',
        );
    }

    public static function get() {
        $stored = get_option(self::OPTION, array());
        if (!is_array($stored)) {
            $stored = array();
        }
        return array_merge(self::defaults(), $stored);
    }

    public static function update($input) {
        $current = self::get();
        $next = $current;
        if (isset($input['company_name'])) {
            $next['company_name'] = sanitize_text_field($input['company_name']);
        }
        if (isset($input['owner_name'])) {
            $next['owner_name'] = sanitize_text_field($input['owner_name']);
        }
        if (isset($input['default_language'])) {
            $next['default_language'] = Alb_I18n::normalize($input['default_language']);
        }
        if (isset($input['timezone'])) {
            $tz = sanitize_text_field($input['timezone']);
            $next['timezone'] = in_array($tz, timezone_identifiers_list(), true) ? $tz : $current['timezone'];
        }
        if (isset($input['date_format'])) {
            $next['date_format'] = self::safe_format($input['date_format'], $current['date_format']);
        }
        if (isset($input['time_format'])) {
            $next['time_format'] = self::safe_format($input['time_format'], $current['time_format']);
        }
        if (isset($input['items_per_page'])) {
            $next['items_per_page'] = max(10, min(200, (int) $input['items_per_page']));
        }
        if (isset($input['min_password_length'])) {
            $next['min_password_length'] = max(8, min(64, (int) $input['min_password_length']));
        }
        if (isset($input['remember_days'])) {
            $next['remember_days'] = max(1, min(90, (int) $input['remember_days']));
        }
        if (isset($input['audit_retention_days'])) {
            $next['audit_retention_days'] = max(0, min(3650, (int) $input['audit_retention_days']));
        }
        if (isset($input['twilio_sid'])) {
            $next['twilio_sid'] = sanitize_text_field($input['twilio_sid']);
        }
        if (isset($input['twilio_from'])) {
            $next['twilio_from'] = sanitize_text_field($input['twilio_from']);
        }
        if (array_key_exists('twilio_token', $input)) {
            $token = trim((string) $input['twilio_token']);
            if ($token !== '' && $token !== '********') {
                $next['twilio_token'] = sanitize_text_field($token);
            }
        }
        update_option(self::OPTION, $next, false);
        return $next;
    }

    public static function timezone() {
        $settings = self::get();
        try {
            return new DateTimeZone($settings['timezone']);
        } catch (Exception $e) {
            return new DateTimeZone('Europe/Vienna');
        }
    }

    public static function format_datetime($mysql_datetime) {
        if (!$mysql_datetime) {
            return '';
        }
        $settings = self::get();
        $dt = date_create($mysql_datetime, new DateTimeZone('UTC'));
        if (!$dt) {
            $dt = date_create($mysql_datetime);
        }
        if (!$dt) {
            return (string) $mysql_datetime;
        }
        $dt->setTimezone(self::timezone());
        return $dt->format($settings['date_format'] . ' ' . $settings['time_format']);
    }

    public static function format_date($value) {
        if (!$value) {
            return '';
        }
        $settings = self::get();
        $dt = date_create($value);
        if (!$dt) {
            return (string) $value;
        }
        return $dt->format($settings['date_format']);
    }

    public static function now_mysql() {
        return gmdate('Y-m-d H:i:s');
    }

    public static function sms_ready() {
        $settings = self::get();
        return $settings['twilio_sid'] !== '' && $settings['twilio_token'] !== '' && $settings['twilio_from'] !== '';
    }

    public static function public_settings() {
        $settings = self::get();
        $settings['twilio_token'] = $settings['twilio_token'] !== '' ? '********' : '';
        $settings['sms_ready'] = self::sms_ready();
        return $settings;
    }

    private static function safe_format($value, $fallback) {
        $value = sanitize_text_field($value);
        return preg_match('/^[dDjlmnYyHIS:\.\-\/ ]+$/', $value) ? $value : $fallback;
    }
}
