<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_I18n {
    const LOCALES = array('de', 'en', 'tr');
    const DEFAULT_LOCALE = 'de';
    const COOKIE = 'alb_locale';

    private static $catalogs = array();

    public static function supported() {
        return self::LOCALES;
    }

    public static function normalize($locale) {
        $locale = strtolower((string) $locale);
        if (strpos($locale, 'de') === 0) {
            return 'de';
        }
        if (strpos($locale, 'en') === 0) {
            return 'en';
        }
        if (strpos($locale, 'tr') === 0) {
            return 'tr';
        }
        return self::DEFAULT_LOCALE;
    }

    public static function current() {
        $user_id = get_current_user_id();
        if ($user_id) {
            $saved = get_user_meta($user_id, 'alb_locale', true);
            if ($saved) {
                return self::normalize($saved);
            }
        }
        if (!empty($_COOKIE[self::COOKIE])) {
            return self::normalize(wp_unslash($_COOKIE[self::COOKIE]));
        }
        $settings = Alb_Settings::get();
        return self::normalize($settings['default_language']);
    }

    public static function set_locale($locale, $user_id = 0) {
        $locale = self::normalize($locale);
        if ($user_id) {
            update_user_meta($user_id, 'alb_locale', $locale);
        }
        if (!headers_sent()) {
            setcookie(self::COOKIE, $locale, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), false);
        }
        $_COOKIE[self::COOKIE] = $locale;
        return $locale;
    }

    public static function catalog($locale = '') {
        $locale = $locale ? self::normalize($locale) : self::current();
        if (isset(self::$catalogs[$locale])) {
            return self::$catalogs[$locale];
        }
        $file = ALB_SCANNER_PLUGIN_DIR . 'languages/' . $locale . '.json';
        $data = array();
        if (is_readable($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        self::$catalogs[$locale] = $data;
        return $data;
    }

    public static function t($key, $replacements = array(), $locale = '') {
        $catalog = self::catalog($locale);
        $text = isset($catalog[$key]) ? (string) $catalog[$key] : $key;
        foreach ($replacements as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    public static function keys() {
        $catalog = self::catalog('de');
        return array_keys($catalog);
    }
}
