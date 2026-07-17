<?php
/**
 * Structured homepage payload for native customer apps (WordPress front page parity).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Homepage {

    /** @var array<string, mixed>|null */
    private static $data = null;

    /**
     * @return array<string, mixed>
     */
    private static function raw_data() {
        if (self::$data === null) {
            $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/homepage-data.php';
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
        $capabilities = self::localized_block($data, 'capabilities', $lang);
        $portfolio_section = self::localized_block($data, 'portfolio_section', $lang);
        $about_teaser = self::localized_block($data, 'about_teaser', $lang);
        $stats = isset($data['stats'][$lang]) ? $data['stats'][$lang] : (isset($data['stats']['de']) ? $data['stats']['de'] : array());
        $awards = self::localized_block($data, 'awards', $lang);
        $testimonials = isset($data['testimonials'][$lang]) ? $data['testimonials'][$lang] : (isset($data['testimonials']['de']) ? $data['testimonials']['de'] : array());
        $features = isset($data['features'][$lang]) ? $data['features'][$lang] : (isset($data['features']['de']) ? $data['features']['de'] : array());
        $process = self::localized_block($data, 'process', $lang);
        $news_section = self::localized_block($data, 'news_section', $lang);

        $portfolio_items = array();
        if (class_exists('PAXdesign_Customer_Portfolio')) {
            foreach (PAXdesign_Customer_Portfolio::list_items(8) as $item) {
                $portfolio_items[] = array(
                    'slug'      => (string) ($item['slug'] ?? ''),
                    'title'     => (string) ($item['title'] ?? ''),
                    'excerpt'   => (string) ($item['excerpt'] ?? ''),
                    'image_url' => (string) ($item['image_url'] ?? ''),
                );
            }
        }

        $service_cards = self::service_carousel_cards($data, $lang);

        return array(
            'lang'               => $lang,
            'dir'                => $dir,
            'hero'               => $hero,
            'service_carousel'   => $service_cards,
            'capabilities'       => $capabilities,
            'portfolio_section'  => $portfolio_section,
            'portfolio_items'    => $portfolio_items,
            'about_teaser'       => $about_teaser,
            'stats'              => is_array($stats) ? array_values($stats) : array(),
            'awards'             => $awards,
            'testimonials'       => is_array($testimonials) ? array_values($testimonials) : array(),
            'features'           => is_array($features) ? array_values($features) : array(),
            'process'            => $process,
            'news_section'       => $news_section,
            'supported_languages'=> self::supported_languages(),
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

    /**
     * @param array<string, mixed> $data
     * @param string $lang
     * @return array<int, array<string, mixed>>
     */
    private static function service_carousel_cards(array $data, $lang) {
        $ids = isset($data['service_carousel_ids']) && is_array($data['service_carousel_ids'])
            ? $data['service_carousel_ids']
            : array();
        if (empty($ids) || !class_exists('PAXdesign_Customer_Services_Catalog')) {
            return array();
        }
        $catalog = PAXdesign_Customer_Services_Catalog::payload($lang);
        $by_id = array();
        foreach ($catalog['cards'] as $card) {
            if (!empty($card['id'])) {
                $by_id[sanitize_key((string) $card['id'])] = $card;
            }
        }
        $alias_map = array('mobile' => 'cross');
        $cards = array();
        foreach ($ids as $id) {
            $lookup = sanitize_key((string) $id);
            if (isset($alias_map[$lookup])) {
                $lookup = $alias_map[$lookup];
            }
            if (isset($by_id[$lookup])) {
                $cards[] = array(
                    'id'          => (string) $by_id[$lookup]['id'],
                    'order_slug'  => (string) $by_id[$lookup]['order_slug'],
                    'title'       => (string) $by_id[$lookup]['title'],
                    'description' => (string) $by_id[$lookup]['description'],
                    'features'    => isset($by_id[$lookup]['features']) ? $by_id[$lookup]['features'] : array(),
                    'is_new'      => !empty($by_id[$lookup]['is_new']),
                );
            }
        }
        return $cards;
    }
}
