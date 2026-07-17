<?php
/**
 * WordPress site content for native app (menus, pages, marketing structure).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Content {

    /**
     * Primary site sections mapped to WordPress nav menus.
     *
     * @var array<string, array<string, mixed>>
     */
    private static $sections = array(
        'services' => array(
            'title'      => 'Services',
            'menu_slugs' => array('services', 'service', 'leistungen-services', 'main-services'),
        ),
        'referenzen' => array(
            'title'      => 'Referenzen',
            'menu_slugs' => array('referenzen', 'portfolio', 'references'),
        ),
        'leistungen' => array(
            'title'      => 'Leistungen',
            'menu_slugs' => array('leistungen', 'capabilities', 'what-we-do'),
        ),
        'kontakt' => array(
            'title'      => 'Kontakt',
            'menu_slugs' => array('kontakt', 'contact', 'about', 'footer'),
        ),
    );

    /**
     * @return string
     */
    public static function resolve_lang(WP_REST_Request $request = null) {
        if ($request instanceof WP_REST_Request) {
            $lang = sanitize_key((string) $request->get_param('lang'));
            if ($lang !== '') {
                return $lang;
            }
            $header = (string) $request->get_header('accept-language');
            if ($header !== '') {
                $parts = explode(',', $header);
                $primary = trim(explode(';', $parts[0])[0]);
                if (preg_match('/^[a-z]{2}/i', $primary, $m)) {
                    return strtolower($m[0]);
                }
            }
        }
        $locale = determine_locale();
        return substr(sanitize_key($locale), 0, 2) ?: 'de';
    }

    /**
     * @return array<string, mixed>
     */
    public static function navigation(WP_REST_Request $request = null) {
        $lang = self::resolve_lang($request);
        $sections = array();
        foreach (self::$sections as $key => $config) {
            $items = self::menu_items_for_slugs((array) $config['menu_slugs']);
            if (empty($items)) {
                $items = self::fallback_section_items($key);
            }
            $tree = self::build_menu_tree($items);
            $tree = self::enrich_section_items($key, $tree);
            $sections[] = array(
                'key'   => $key,
                'title' => self::localized_section_title($key, (string) $config['title']),
                'items' => $tree,
            );
        }
        return array(
            'locale'   => $lang,
            'sections' => $sections,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_page($slug, WP_REST_Request $request = null) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }
        $post = self::find_post_by_slug($slug);
        if (!$post instanceof WP_Post) {
            return null;
        }
        return self::format_page($post, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_pages($parent_slug = '', $limit = 50) {
        $limit = max(1, min(100, (int) $limit));
        $parent_id = 0;
        if ($parent_slug !== '') {
            $parent = self::find_post_by_slug($parent_slug);
            if ($parent instanceof WP_Post) {
                $parent_id = (int) $parent->ID;
            }
        }
        $posts = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'post_parent'    => $parent_id,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ));
        $items = array();
        foreach ($posts as $post) {
            $items[] = self::format_page($post, false);
        }
        return $items;
    }

    /**
     * @param string[] $slugs
     * @return array<int, object>
     */
    private static function menu_items_for_slugs(array $slugs) {
        foreach ($slugs as $slug) {
            $menu = wp_get_nav_menu_object($slug);
            if ($menu && !is_wp_error($menu)) {
                $items = wp_get_nav_menu_items((int) $menu->term_id);
                if (!empty($items)) {
                    return $items;
                }
            }
        }
        $locations = get_nav_menu_locations();
        if (is_array($locations)) {
            foreach ($slugs as $slug) {
                foreach ($locations as $location => $menu_id) {
                    if (!$menu_id) {
                        continue;
                    }
                    $location_key = sanitize_key($location);
                    $slug_key = sanitize_key($slug);
                    if ($location_key === $slug_key || strpos($location_key, $slug_key) !== false || strpos($slug_key, $location_key) !== false) {
                        $items = wp_get_nav_menu_items((int) $menu_id);
                        if (!empty($items)) {
                            return $items;
                        }
                    }
                }
            }
        }
        return array();
    }

    /**
     * Merge dynamic catalog data so native app always reflects WordPress content.
     *
     * @param string $section_key
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private static function enrich_section_items($section_key, array $items) {
        $existing = self::collect_menu_slugs($items);
        if ($section_key === 'services' && class_exists('PAXdesign_Customer_Services')) {
            foreach (PAXdesign_Customer_Services::list_services() as $service) {
                $slug = sanitize_key((string) ($service['slug'] ?? ''));
                if ($slug === '' || in_array($slug, $existing, true)) {
                    continue;
                }
                $items[] = array(
                    'id'        => 0,
                    'parent_id' => 0,
                    'title'     => (string) ($service['name'] ?? $slug),
                    'slug'      => $slug,
                    'type'      => 'service',
                    'url'       => '',
                    'children'  => array(),
                );
                $existing[] = $slug;
            }
        }
        if ($section_key === 'referenzen') {
            foreach (PAXdesign_Customer_Portfolio::list_items(100) as $portfolio) {
                $slug = sanitize_title((string) ($portfolio['slug'] ?? ''));
                if ($slug === '' || in_array($slug, $existing, true)) {
                    continue;
                }
                $items[] = array(
                    'id'        => 0,
                    'parent_id' => 0,
                    'title'     => (string) ($portfolio['title'] ?? $slug),
                    'slug'      => $slug,
                    'type'      => 'portfolio',
                    'url'       => '',
                    'children'  => array(),
                );
                $existing[] = $slug;
            }
        }
        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return string[]
     */
    private static function collect_menu_slugs(array $items) {
        $slugs = array();
        foreach ($items as $item) {
            if (!empty($item['slug'])) {
                $slugs[] = sanitize_title((string) $item['slug']);
            }
            if (!empty($item['children']) && is_array($item['children'])) {
                $slugs = array_merge($slugs, self::collect_menu_slugs($item['children']));
            }
        }
        return array_values(array_unique(array_filter($slugs)));
    }

    /**
     * @param array<int, object> $items
     * @return array<int, array<string, mixed>>
     */
    private static function build_menu_tree(array $items) {
        if (empty($items)) {
            return array();
        }
        $by_id = array();
        foreach ($items as $item) {
            if (empty($item->ID)) {
                continue;
            }
            $node = self::format_menu_item($item);
            if ($node === null) {
                continue;
            }
            $by_id[(int) $item->ID] = $node;
        }
        $tree = array();
        foreach ($by_id as $id => $node) {
            $parent = (int) $node['parent_id'];
            if ($parent > 0 && isset($by_id[$parent])) {
                if (!isset($by_id[$parent]['children'])) {
                    $by_id[$parent]['children'] = array();
                }
                $by_id[$parent]['children'][] = &$by_id[$id];
            } else {
                $tree[] = &$by_id[$id];
            }
        }
        return array_values($tree);
    }

    /**
     * @param object $item
     * @return array<string, mixed>|null
     */
    private static function format_menu_item($item) {
        $slug = '';
        $type = 'link';
        $object_id = isset($item->object_id) ? (int) $item->object_id : 0;
        if (!empty($item->object) && $item->object === 'page' && $object_id > 0) {
            $post = get_post($object_id);
            if ($post instanceof WP_Post) {
                $slug = $post->post_name;
                $type = 'page';
            }
        } elseif (!empty($item->url)) {
            $path = wp_parse_url($item->url, PHP_URL_PATH);
            if (is_string($path)) {
                $slug = sanitize_title(basename(untrailingslashit($path)));
            }
            if (self::is_internal_url((string) $item->url)) {
                $type = 'page';
            } else {
                $type = 'external';
            }
        }
        if ($type === 'page' && self::is_portfolio_slug($slug)) {
            $type = 'portfolio';
        }
        if ($type === 'page' && self::is_service_slug($slug)) {
            $type = 'service';
        }
        if ($type === 'external') {
            return null;
        }
        return array(
            'id'        => (int) $item->ID,
            'parent_id' => isset($item->menu_item_parent) ? (int) $item->menu_item_parent : 0,
            'title'     => wp_strip_all_tags((string) $item->title),
            'slug'      => $slug,
            'type'      => $type,
            'url'       => esc_url_raw((string) $item->url),
            'children'  => array(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fallback_section_items($section_key) {
        $map = array(
            'services'   => array('slug' => 'services', 'parent' => 0),
            'referenzen' => array('slug' => 'referenzen', 'parent' => 0),
            'leistungen' => array('slug' => 'leistungen', 'parent' => 0),
            'kontakt'    => array('slug' => 'kontakt', 'parent' => 0),
        );
        if (!isset($map[$section_key])) {
            return array();
        }
        $cfg = $map[$section_key];
        $parent = get_page_by_path($cfg['slug'], OBJECT, 'page');
        if (!$parent instanceof WP_Post) {
            return array();
        }
        $children = get_pages(array(
            'parent'      => (int) $parent->ID,
            'post_status' => 'publish',
            'sort_column' => 'menu_order,post_title',
        ));
        $items = array();
        foreach ($children as $child) {
            $items[] = array(
                'id'        => (int) $child->ID,
                'parent_id' => (int) $parent->ID,
                'title'     => get_the_title($child),
                'slug'      => $child->post_name,
                'type'      => self::guess_content_type($child),
                'url'       => get_permalink($child),
                'children'  => array(),
            );
        }
        if (empty($items) && $section_key === 'referenzen') {
            foreach (PAXdesign_Customer_Portfolio::list_items(100) as $portfolio) {
                $items[] = array(
                    'id'        => 0,
                    'parent_id' => 0,
                    'title'     => (string) $portfolio['title'],
                    'slug'      => (string) $portfolio['slug'],
                    'type'      => 'portfolio',
                    'url'       => '',
                    'children'  => array(),
                );
            }
        }
        return $items;
    }

    /**
     * @param WP_Post $post
     * @return string
     */
    private static function guess_content_type($post) {
        if ($post->post_type === 'dtr_portfolio') {
            return 'portfolio';
        }
        $slug = $post->post_name;
        if (self::is_service_slug($slug)) {
            return 'service';
        }
        if (self::is_portfolio_slug($slug)) {
            return 'portfolio';
        }
        return 'page';
    }

    private static function is_service_slug($slug) {
        return in_array(sanitize_key($slug), array(
            'website', 'webapp', 'ios', 'android', 'security', 'ai', 'maintenance', 'branding', 'uiux',
        ), true) || (bool) PAXdesign_Customer_Services::get_by_slug(sanitize_key($slug));
    }

    private static function is_portfolio_slug($slug) {
        if (post_type_exists('dtr_portfolio')) {
            $found = get_posts(array(
                'post_type'      => 'dtr_portfolio',
                'name'           => sanitize_title($slug),
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ));
            if (!empty($found)) {
                return true;
            }
        }
        return false;
    }

    private static function is_internal_url($url) {
        $home = home_url('/');
        return strpos($url, $home) === 0 || strpos($url, '/') === 0;
    }

    /**
     * @return WP_Post|null
     */
    private static function find_post_by_slug($slug) {
        if (post_type_exists('dtr_portfolio')) {
            $posts = get_posts(array(
                'post_type'      => 'dtr_portfolio',
                'name'           => $slug,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
            ));
            if (!empty($posts[0])) {
                return $posts[0];
            }
        }
        $page = get_page_by_path($slug, OBJECT, array('page', 'post'));
        return ($page instanceof WP_Post && $page->post_status === 'publish') ? $page : null;
    }

    /**
     * @param WP_Post $post
     * @return array<string, mixed>
     */
    private static function format_page($post, $full = false) {
        $thumb = get_the_post_thumbnail_url($post, 'large');
        if (!$thumb) {
            $thumb = get_the_post_thumbnail_url($post, 'medium_large');
        }
        $item = array(
            'slug'       => $post->post_name,
            'title'      => get_the_title($post),
            'excerpt'    => wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 40),
            'image_url'  => $thumb ? (string) $thumb : '',
            'type'       => self::guess_content_type($post),
            'updated_at' => get_the_modified_date('c', $post),
        );
        if ($full) {
            $content = apply_filters('the_content', $post->post_content);
            $item['body_html'] = wp_kses_post($content);
            $item['body_text'] = wp_trim_words(wp_strip_all_tags($content), 800);
            $item['gallery'] = self::inline_image_urls($content);
        }
        return $item;
    }

    /**
     * @param string $html
     * @return string[]
     */
    private static function inline_image_urls($html) {
        $urls = array();
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $urls[] = esc_url_raw($url);
            }
        }
        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @return string
     */
    private static function localized_section_title($key, $fallback) {
        $map = array(
            'services'   => array('de' => 'Services', 'en' => 'Services', 'ar' => 'الخدمات'),
            'referenzen' => array('de' => 'Referenzen', 'en' => 'Portfolio', 'ar' => 'الأعمال'),
            'leistungen' => array('de' => 'Leistungen', 'en' => 'Capabilities', 'ar' => 'القدرات'),
            'kontakt'    => array('de' => 'Kontakt', 'en' => 'Contact', 'ar' => 'تواصل'),
        );
        $lang = substr(determine_locale(), 0, 2);
        if (isset($map[$key][$lang])) {
            return $map[$key][$lang];
        }
        return $fallback;
    }
}
