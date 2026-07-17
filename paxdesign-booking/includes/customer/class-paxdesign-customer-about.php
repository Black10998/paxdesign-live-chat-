<?php
/**
 * Structured about page payload for native customer apps.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_About {

    /** @var array<string, mixed>|null */
    private static $data = null;

    /**
     * @return array<string, mixed>
     */
    private static function raw_data() {
        if (self::$data === null) {
            $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/about-data.php';
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
        $intro = self::localized_block($data, 'intro', $lang);
        $values = self::localized_block($data, 'values', $lang);
        $stats = isset($data['stats'][$lang]) ? $data['stats'][$lang] : (isset($data['stats']['de']) ? $data['stats']['de'] : array());
        $awards = self::localized_block($data, 'awards', $lang);
        $gallery = isset($data['gallery']) && is_array($data['gallery']) ? array_values($data['gallery']) : array();

        return array(
            'lang'                => $lang,
            'dir'                 => $dir,
            'hero'                => $hero,
            'intro'               => $intro,
            'values'              => $values,
            'stats'               => is_array($stats) ? array_values($stats) : array(),
            'awards'              => $awards,
            'gallery'             => $gallery,
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
