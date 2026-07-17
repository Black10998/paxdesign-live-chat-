<?php
/**
 * Structured contact page payload for native customer apps.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Contact {

    /** @var array<string, mixed>|null */
    private static $data = null;

    /**
     * @return array<string, mixed>
     */
    private static function raw_data() {
        if (self::$data === null) {
            $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/contact-data.php';
            if (!is_readable($path)) {
                self::$data = array();
                return self::$data;
            }
            $loaded = include $path;
            self::$data = is_array($loaded) ? $loaded : array();
        }
        return self::$data;
    }

    /**
     * @return string[]
     */
    public static function supported_languages() {
        return array('de', 'en', 'ar');
    }

    public static function normalize_language($lang) {
        $lang = sanitize_key((string) $lang);
        if ($lang === '') {
            $lang = 'de';
        }
        return in_array($lang, self::supported_languages(), true) ? $lang : 'de';
    }

    /**
     * @param string $lang
     * @return array<string, mixed>
     */
    public static function payload($lang = 'de') {
        $lang = self::normalize_language($lang);
        $data = self::raw_data();
        $dir = ($lang === 'ar') ? 'rtl' : 'ltr';

        $hero = self::localized_block($data, 'hero', $lang);
        $contact = isset($data['contact']) && is_array($data['contact']) ? $data['contact'] : array();
        $faq = isset($data['faq'][$lang]) ? $data['faq'][$lang] : (isset($data['faq']['de']) ? $data['faq']['de'] : array());
        $cta = self::localized_block($data, 'cta', $lang);

        return array(
            'lang'                => $lang,
            'dir'                 => $dir,
            'hero'                => $hero,
            'contact'             => array(
                'phone'   => (string) ($contact['phone'] ?? ''),
                'email'   => (string) ($contact['email'] ?? ''),
                'address' => (string) ($contact['address'] ?? ''),
            ),
            'faq'                 => is_array($faq) ? array_values($faq) : array(),
            'cta'                 => $cta,
            'supported_languages' => self::supported_languages(),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param string $key
     * @param string $lang
     * @return array<string, mixed>
     */
    private static function localized_block(array $data, $key, $lang) {
        if (isset($data[$key][$lang]) && is_array($data[$key][$lang])) {
            return $data[$key][$lang];
        }
        if (isset($data[$key]['de']) && is_array($data[$key]['de'])) {
            return $data[$key]['de'];
        }
        return array();
    }
}
