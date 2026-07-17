<?php
/**
 * Curated portfolio showcase — native structured payload mirroring the website.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Portfolio_Showcase {

    /** @var array<string, mixed>|null */
    private static $data = null;

    /**
     * @return array<string, mixed>
     */
    private static function raw_data() {
        if (self::$data !== null) {
            return self::$data;
        }
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/portfolio-showcase-data.json';
        if (!is_readable($path)) {
            self::$data = array();
            return self::$data;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        self::$data = is_array($decoded) ? $decoded : array();
        return self::$data;
    }

    public static function normalize_language($lang) {
        $lang = sanitize_key((string) $lang);
        if ($lang === '') {
            $lang = 'de';
        }
        return in_array($lang, array('de', 'en', 'ar'), true) ? $lang : 'de';
    }

    /**
     * @param string $lang
     * @return array<string, mixed>
     */
    public static function payload($lang = 'de') {
        $lang = self::normalize_language($lang);
        $data = self::raw_data();
        $header = isset($data['header'][$lang]) ? $data['header'][$lang] : (isset($data['header']['de']) ? $data['header']['de'] : array());
        $cta = isset($data['cta'][$lang]) ? $data['cta'][$lang] : (isset($data['cta']['de']) ? $data['cta']['de'] : array());
        $items = array();
        foreach (isset($data['items']) && is_array($data['items']) ? $data['items'] : array() as $row) {
            $formatted = self::format_item($row, $lang, false);
            if ($formatted !== null) {
                $items[] = $formatted;
            }
        }
        $categories = self::categories_from_items($items);
        return array(
            'lang'       => $lang,
            'dir'        => ($lang === 'ar') ? 'rtl' : 'ltr',
            'header'     => $header,
            'cta'        => self::format_cta($cta),
            'categories' => $categories,
            'items'      => $items,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_items($limit = 100, $category = '', $lang = 'de') {
        $payload = self::payload($lang);
        $items = isset($payload['items']) ? $payload['items'] : array();
        if ($category !== '') {
            $needle = sanitize_title($category);
            $items = array_values(array_filter($items, function ($item) use ($needle) {
                $slugs = isset($item['category_slugs']) && is_array($item['category_slugs']) ? $item['category_slugs'] : array();
                return in_array($needle, $slugs, true);
            }));
        }
        $limit = max(1, min(200, (int) $limit));
        return array_slice($items, 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_item($slug, $lang = 'de') {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }
        $data = self::raw_data();
        foreach (isset($data['items']) && is_array($data['items']) ? $data['items'] : array() as $row) {
            if (sanitize_title((string) ($row['slug'] ?? '')) === $slug) {
                return self::format_item($row, self::normalize_language($lang), true);
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function format_item(array $row, $lang, $full = false) {
        $slug = sanitize_title((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            return null;
        }
        $title = self::localized($row['title'] ?? '', $lang);
        $description = self::localized($row['description'] ?? '', $lang);
        $category = self::localized($row['category'] ?? '', $lang);
        $category_slug = sanitize_title($category);
        $stats = array();
        foreach (isset($row['stats']) && is_array($row['stats']) ? $row['stats'] : array() as $stat) {
            if (!is_array($stat)) {
                continue;
            }
            $value = sanitize_text_field((string) ($stat['value'] ?? ''));
            $label = self::localized($stat['label'] ?? '', $lang);
            if ($value === '' || $label === '') {
                continue;
            }
            $stats[] = array('value' => $value, 'label' => $label);
        }

        $item = array(
            'slug'            => $slug,
            'title'           => $title,
            'excerpt'         => wp_trim_words($description, 28, '…'),
            'image_url'       => esc_url_raw((string) ($row['image_url'] ?? '')),
            'client'          => sanitize_text_field((string) ($row['client'] ?? '')),
            'project_url'     => '',
            'published_at'    => '',
            'category_names'  => $category !== '' ? array($category) : array(),
            'category_slugs'  => $category_slug !== '' ? array($category_slug) : array(),
            'stats'           => $stats,
        );

        if ($full) {
            $item['structured'] = array(
                'hero' => array(
                    'headline' => $title,
                    'subtitle' => $description,
                ),
                'stats'    => $stats,
                'metadata' => self::metadata_for_item($row, $lang, $category),
                'sections' => array(
                    array(
                        'title' => __('Overview', 'paxdesign-booking'),
                        'body'  => $description,
                    ),
                ),
                'services' => array(),
                'tags'     => $category !== '' ? array($category) : array(),
                'gallery'  => self::gallery_for_item($row),
                'cta'      => null,
                'summary'  => $description,
                'website_url' => home_url('/portfolio/' . $slug . '/'),
                'published_label' => '',
            );
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, array<string, string>>
     */
    private static function metadata_for_item(array $row, $lang, $category) {
        $meta = array();
        $client = sanitize_text_field((string) ($row['client'] ?? ''));
        if ($client !== '') {
            $meta[] = array('label' => __('Client', 'paxdesign-booking'), 'value' => $client, 'link' => '');
        }
        if ($category !== '') {
            $meta[] = array('label' => __('Category', 'paxdesign-booking'), 'value' => $category, 'link' => '');
        }
        return $meta;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, array<string, string>>
     */
    private static function gallery_for_item(array $row) {
        $gallery = array();
        $image = esc_url_raw((string) ($row['image_url'] ?? ''));
        if ($image !== '') {
            $gallery[] = array('url' => $image, 'caption' => '');
        }
        return $gallery;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private static function categories_from_items(array $items) {
        $map = array();
        foreach ($items as $item) {
            $names = isset($item['category_names']) && is_array($item['category_names']) ? $item['category_names'] : array();
            $slugs = isset($item['category_slugs']) && is_array($item['category_slugs']) ? $item['category_slugs'] : array();
            foreach ($names as $i => $name) {
                $slug = isset($slugs[$i]) ? (string) $slugs[$i] : sanitize_title($name);
                if ($slug === '') {
                    continue;
                }
                if (!isset($map[$slug])) {
                    $map[$slug] = array('slug' => $slug, 'name' => $name, 'count' => 0);
                }
                $map[$slug]['count']++;
            }
        }
        return array_values($map);
    }

    /**
     * @param array<string, mixed> $cta
     * @return array<string, mixed>
     */
    private static function format_cta(array $cta) {
        $path = (string) ($cta['path'] ?? '/kontakt');
        return array(
            'tags'   => isset($cta['tags']) && is_array($cta['tags']) ? array_values($cta['tags']) : array(),
            'title'  => (string) ($cta['title'] ?? ''),
            'text'   => (string) ($cta['text'] ?? ''),
            'button' => (string) ($cta['button'] ?? ''),
            'url'    => home_url($path),
        );
    }

    /**
     * @param mixed $value
     * @param string $lang
     * @return string
     */
    private static function localized($value, $lang) {
        if (is_array($value)) {
            if (isset($value[$lang]) && (string) $value[$lang] !== '') {
                return sanitize_text_field((string) $value[$lang]);
            }
            if (isset($value['de']) && (string) $value['de'] !== '') {
                return sanitize_text_field((string) $value['de']);
            }
            return '';
        }
        return sanitize_text_field((string) $value);
    }
}
