<?php
/**
 * Rich services catalog (pricing page parity) for native customer apps.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Services_Catalog {

    /** @var array<string, mixed>|null */
    private static $data = null;

    /**
     * @return array<string, mixed>
     */
    private static function raw_data() {
        if (self::$data === null) {
            $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/services-catalog-data.php';
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
     * Map catalog card id to booking/order slug.
     */
    public static function order_slug_for_card($card_id) {
        $card_id = sanitize_key((string) $card_id);
        $data = self::raw_data();
        $aliases = isset($data['slug_aliases']) && is_array($data['slug_aliases']) ? $data['slug_aliases'] : array();
        if (isset($aliases[$card_id])) {
            return sanitize_key((string) $aliases[$card_id]);
        }
        return $card_id;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload($lang = 'de') {
        $lang = self::normalize_language($lang);
        $data = self::raw_data();
        $i18n = isset($data['i18n'][$lang]) && is_array($data['i18n'][$lang]) ? $data['i18n'][$lang] : array();
        $order = isset($data['order']) && is_array($data['order']) ? $data['order'] : array();
        $card_meta = isset($data['card_meta']) && is_array($data['card_meta']) ? $data['card_meta'] : array();
        $cards_src = isset($data['cards']) && is_array($data['cards']) ? $data['cards'] : array();
        $security_break_after = isset($data['security_break_after']) ? sanitize_key((string) $data['security_break_after']) : 'gdpr';

        $cards = array();
        foreach ($order as $card_id) {
            $card_id = sanitize_key((string) $card_id);
            if ($card_id === '') {
                continue;
            }
            $localized = isset($cards_src[$card_id][$lang]) && is_array($cards_src[$card_id][$lang])
                ? $cards_src[$card_id][$lang]
                : (isset($cards_src[$card_id]['de']) && is_array($cards_src[$card_id]['de']) ? $cards_src[$card_id]['de'] : null);
            if (!$localized) {
                continue;
            }
            $meta = isset($card_meta[$card_id]) && is_array($card_meta[$card_id]) ? $card_meta[$card_id] : array();
            $cards[] = array(
                'id'           => $card_id,
                'order_slug'   => self::order_slug_for_card($card_id),
                'title'        => (string) ($localized['title'] ?? ''),
                'description'  => (string) ($localized['desc'] ?? ''),
                'features'     => isset($localized['features']) && is_array($localized['features']) ? array_values($localized['features']) : array(),
                'details'      => self::format_details(isset($localized['details']) ? $localized['details'] : array()),
                'badge'        => isset($meta['badge']) ? sanitize_key((string) $meta['badge']) : '',
                'highlighted'  => !empty($meta['highlighted']),
                'is_new'       => !empty($meta['badge']) && sanitize_key((string) $meta['badge']) === 'new',
            );
        }

        $badges = isset($i18n['badges']) && is_array($i18n['badges']) ? $i18n['badges'] : array();
        $process = isset($i18n['process']) && is_array($i18n['process']) ? $i18n['process'] : array();

        return array(
            'lang'                   => $lang,
            'dir'                    => isset($i18n['dir']) ? (string) $i18n['dir'] : ($lang === 'ar' ? 'rtl' : 'ltr'),
            'title'                  => (string) ($i18n['title'] ?? 'PAXdesign Services'),
            'subtitle'               => (string) ($i18n['subtitle'] ?? ''),
            'statement'              => (string) ($i18n['statement'] ?? ''),
            'book_label'             => (string) ($i18n['book'] ?? 'Book appointment'),
            'more_label'             => (string) ($i18n['more'] ?? 'Learn more'),
            'less_label'             => (string) ($i18n['less'] ?? 'Show less'),
            'badges'                 => array(
                'popular' => (string) ($badges['popular'] ?? 'Popular'),
                'premium' => (string) ($badges['premium'] ?? 'Premium'),
                'new'     => (string) ($badges['new'] ?? 'New'),
            ),
            'process_title'          => (string) ($i18n['processTitle'] ?? ''),
            'process_steps'          => array_map(static function ($step) {
                return array(
                    'title' => (string) ($step['title'] ?? ''),
                    'text'  => (string) ($step['text'] ?? ''),
                );
            }, $process),
            'security_section'       => array(
                'after_card_id' => $security_break_after,
                'title'         => (string) ($i18n['securityCategoryTitle'] ?? ''),
                'subtitle'      => (string) ($i18n['securityCategorySubtitle'] ?? ''),
            ),
            'cards'                  => $cards,
            'supported_languages'    => self::supported_languages(),
        );
    }

    /**
     * @param mixed $details
     * @return array<int, array<string, mixed>>
     */
    private static function format_details($details) {
        if (!is_array($details)) {
            return array();
        }
        $out = array();
        foreach ($details as $block) {
            if (!is_array($block)) {
                continue;
            }
            $heading = (string) ($block['h'] ?? '');
            if ($heading === '') {
                continue;
            }
            if (!empty($block['p'])) {
                $out[] = array(
                    'heading' => $heading,
                    'paragraph' => (string) $block['p'],
                );
            } elseif (!empty($block['items']) && is_array($block['items'])) {
                $out[] = array(
                    'heading' => $heading,
                    'items' => array_values(array_map('strval', $block['items'])),
                );
            }
        }
        return $out;
    }
}
