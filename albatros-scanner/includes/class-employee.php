<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Employee {
    const COOKIE = 'alb_employee';

    public static function start($data) {
        $payload = array(
            'driver_id' => (int) $data['driver_id'],
            'scanner_id' => (int) $data['scanner_id'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'photo_path' => $data['photo_path'],
            'exp' => time() + DAY_IN_SECONDS,
        );
        $encoded = base64_encode(wp_json_encode($payload));
        $signed = $encoded . '.' . hash_hmac('sha256', $encoded, self::key());
        if (!headers_sent()) {
            setcookie(self::COOKIE, $signed, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        }
        $_COOKIE[self::COOKIE] = $signed;
        return $payload;
    }

    public static function current() {
        if (empty($_COOKIE[self::COOKIE])) {
            return null;
        }
        $parts = explode('.', (string) wp_unslash($_COOKIE[self::COOKIE]), 2);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], self::key()), $parts[1])) {
            return null;
        }
        $data = json_decode(base64_decode($parts[0]), true);
        if (!is_array($data) || empty($data['exp']) || (int) $data['exp'] < time()) {
            return null;
        }
        return $data;
    }

    public static function for_scanner($scanner_id) {
        $current = self::current();
        if (!$current || (int) $current['scanner_id'] !== (int) $scanner_id) {
            return null;
        }
        return $current;
    }

    public static function split_name($full_name) {
        $full_name = trim(preg_replace('/\s+/', ' ', $full_name));
        $parts = explode(' ', $full_name);
        if (count($parts) < 2) {
            return new WP_Error('alb_invalid', Alb_I18n::t('scan.error.name'), array('status' => 400));
        }
        $last = array_pop($parts);
        return array(
            'first_name' => implode(' ', $parts),
            'last_name' => $last,
            'name' => $full_name,
        );
    }

    public static function register_and_send($scanner, $full_name, $phone, $file) {
        $name = self::split_name($full_name);
        if (is_wp_error($name)) {
            return $name;
        }
        $photo = Alb_Photos::store_upload($file, 'selfie');
        if (is_wp_error($photo)) {
            return $photo;
        }
        return Alb_Otp::request((int) $scanner['id'], $name['name'], $phone, $photo);
    }

    public static function verify_and_bind($scanner, $phone, $code) {
        $row = Alb_Otp::verify((int) $scanner['id'], $phone, $code);
        if (is_wp_error($row)) {
            return $row;
        }
        $name = self::split_name($row['full_name']);
        if (is_wp_error($name)) {
            return $name;
        }
        $driver = Alb_Drivers::upsert_verified(array(
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'phone' => $row['phone'],
            'photo_path' => $row['photo_path'],
        ));
        if (is_wp_error($driver)) {
            return $driver;
        }
        return self::start(array(
            'driver_id' => $driver['id'],
            'scanner_id' => (int) $scanner['id'],
            'name' => $driver['name'],
            'phone' => $driver['phone'],
            'photo_path' => $driver['photo_path'],
        ));
    }

    public static function accept($scanner) {
        $employee = self::for_scanner($scanner['id']);
        if (!$employee) {
            return new WP_Error('alb_auth', Alb_I18n::t('handover.error.verify_first'), array('status' => 401));
        }
        if (!empty($scanner['deleted_at'])) {
            return new WP_Error('alb_deleted', Alb_I18n::t('scanner.error.deleted'), array('status' => 400));
        }
        $result = Alb_Scanners::employee_accept((int) $scanner['id'], (int) $employee['driver_id']);
        if (is_wp_error($result)) {
            return $result;
        }
        Alb_Scan::record($result, 'take_over', 'employee_accept');
        return $result;
    }

    private static function key() {
        return 'alb-emp-' . wp_salt('auth');
    }
}
