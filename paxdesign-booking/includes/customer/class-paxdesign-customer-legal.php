<?php
/**
 * Structured legal page payloads for native customer apps.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Legal {

    /** @var array<string, mixed>|null */
    private static $data = null;

    /**
     * @return array<string, mixed>
     */
    private static function raw_data() {
        if (self::$data === null) {
            $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/legal-data.php';
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
    public static function supported_slugs() {
        return array('datenschutz', 'agb', 'impressum', 'service-dokumentation', 'ueber-uns');
    }

    public static function normalize_language($lang) {
        $lang = sanitize_key((string) $lang);
        if ($lang === '') {
            $lang = 'de';
        }
        return in_array($lang, array('de', 'en', 'ar'), true) ? $lang : 'de';
    }

    /**
     * @param string $slug
     * @param string $lang
     * @return array<string, mixed>|null
     */
    public static function payload($slug, $lang = 'de') {
        $slug = sanitize_key($slug);
        if (!in_array($slug, self::supported_slugs(), true)) {
            return null;
        }
        $lang = self::normalize_language($lang);
        $data = self::raw_data();
        $pages = isset($data['pages']) && is_array($data['pages']) ? $data['pages'] : array();
        if (!isset($pages[$slug]) || !is_array($pages[$slug])) {
            return null;
        }
        $page = $pages[$slug];
        $localized = isset($page[$lang]) && is_array($page[$lang])
            ? $page[$lang]
            : (isset($page['de']) && is_array($page['de']) ? $page['de'] : array());
        if (empty($localized)) {
            return null;
        }
        $path = (string) ($localized['website_path'] ?? ('/' . $slug . '/'));
        return array(
            'slug'         => $slug,
            'lang'         => $lang,
            'dir'          => ($lang === 'ar') ? 'rtl' : 'ltr',
            'title'        => (string) ($localized['title'] ?? ''),
            'subtitle'     => (string) ($localized['subtitle'] ?? ''),
            'sections'     => isset($localized['sections']) && is_array($localized['sections'])
                ? array_values($localized['sections'])
                : array(),
            'website_url'  => home_url($path),
            'cta'          => (string) ($localized['cta'] ?? ''),
        );
    }
}
