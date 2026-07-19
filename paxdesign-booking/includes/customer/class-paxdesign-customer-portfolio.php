<?php
/**
 * Portfolio catalog for customer portal (WordPress CPT + fallbacks).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Portfolio {

    const CPT = 'dtr_portfolio';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_items($limit = 100, $category = '', $lang = 'de') {
        if (class_exists('PAXdesign_Customer_Portfolio_Showcase')) {
            $showcase = PAXdesign_Customer_Portfolio_Showcase::list_items($limit, $category, $lang);
            if (!empty($showcase)) {
                return $showcase;
            }
        }

        $limit = max(1, min(200, (int) $limit));
        $items = array();

        if (post_type_exists(self::CPT)) {
            $args = array(
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'orderby'        => 'date',
                'order'          => 'DESC',
            );
            if ($category !== '') {
                $args['tax_query'] = array(array(
                    'taxonomy' => 'portfolio_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_title($category),
                ));
            }
            $query = new WP_Query($args);
            foreach ($query->posts as $post) {
                $items[] = self::format_post($post, false);
            }
            wp_reset_postdata();
        }

        if (empty($items)) {
            $items = self::fallback_from_pages($limit);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_item($slug, $lang = 'de') {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }

        if (class_exists('PAXdesign_Customer_Portfolio_Showcase')) {
            $showcase = PAXdesign_Customer_Portfolio_Showcase::get_item($slug, $lang);
            if ($showcase !== null) {
                return $showcase;
            }
        }

        if (post_type_exists(self::CPT)) {
            $posts = get_posts(array(
                'post_type'      => self::CPT,
                'name'           => $slug,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
            ));
            if (!empty($posts[0])) {
                return self::format_post($posts[0], true);
            }
        }

        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status === 'publish') {
            return self::format_post($page, true);
        }

        return null;
    }

    /**
     * @return string[]
     */
    public static function categories($lang = 'de') {
        if (class_exists('PAXdesign_Customer_Portfolio_Showcase')) {
            $payload = PAXdesign_Customer_Portfolio_Showcase::payload($lang);
            if (!empty($payload['categories'])) {
                return $payload['categories'];
            }
        }
        if (!taxonomy_exists('portfolio_category')) {
            return array();
        }
        $terms = get_terms(array(
            'taxonomy'   => 'portfolio_category',
            'hide_empty' => true,
        ));
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }
        $out = array();
        foreach ($terms as $term) {
            $out[] = array(
                'slug'  => $term->slug,
                'name'  => $term->name,
                'count' => (int) $term->count,
            );
        }
        return $out;
    }

    /**
     * @param WP_Post $post
     * @return array<string, mixed>
     */
    private static function format_post($post, $full = false) {
        $thumb = get_the_post_thumbnail_url($post, 'large');
        if (!$thumb) {
            $thumb = get_the_post_thumbnail_url($post, 'medium_large');
        }
        $raw_title = get_the_title($post);
        $item = array(
            'slug'       => $post->post_name,
            'title'      => self::clean_title($raw_title, $post),
            'excerpt'    => self::clean_excerpt($post),
            'image_url'  => $thumb ? (string) $thumb : '',
            'client'     => self::clean_text((string) get_post_meta($post->ID, 'dtr_client_name', true)),
            'project_url'=> esc_url_raw((string) get_post_meta($post->ID, 'dtr_project_url', true)),
            'published_at' => get_the_date('c', $post),
        );
        $term_slugs = wp_get_post_terms($post->ID, 'portfolio_category', array('fields' => 'slugs'));
        $term_names = wp_get_post_terms($post->ID, 'portfolio_category', array('fields' => 'names'));
        $item['category_slugs'] = is_array($term_slugs) ? array_values($term_slugs) : array();
        $item['category_names'] = is_array($term_names) ? array_values($term_names) : array();
        if ($full) {
            $item['body'] = PAXdesign_Customer_Elementor::render_html($post);
            $item['gallery'] = PAXdesign_Customer_Elementor::images_from_html($item['body']);
            $gallery_meta = self::gallery_urls($post->ID);
            if (!empty($gallery_meta)) {
                $item['gallery'] = array_values(array_unique(array_merge($gallery_meta, $item['gallery'] ?? array())));
            }
            $blocks = PAXdesign_Customer_Elementor::parse_blocks((int) $post->ID);
            if (!empty($blocks)) {
                $item['blocks'] = $blocks;
            }
            $item['body_text'] = wp_trim_words(wp_strip_all_tags($item['body']), 800, '…');
            $item['categories'] = wp_get_post_terms($post->ID, 'portfolio_category', array('fields' => 'names'));
            if (!is_array($item['categories'])) {
                $item['categories'] = array();
            }
            $item['structured'] = self::structured_detail($post, $item);
        }
        return $item;
    }

    /**
     * Native-friendly structured detail payload (no HTML layout).
     *
     * @param WP_Post $post
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function structured_detail($post, array $item) {
        $blocks = isset($item['blocks']) && is_array($item['blocks']) ? $item['blocks'] : array();
        $parsed = self::structured_from_blocks($blocks, $item, $post);

        $highlights = array();
        $client = (string) ($item['client'] ?? '');
        if ($client !== '') {
            $highlights[] = array('label' => __('Client', 'paxdesign-booking'), 'value' => $client);
        }
        $year = self::clean_text((string) get_post_meta($post->ID, 'dtr_year', true));
        if ($year !== '') {
            $highlights[] = array('label' => __('Year', 'paxdesign-booking'), 'value' => $year);
        }
        $services_meta = self::clean_text((string) get_post_meta($post->ID, 'dtr_services', true));
        if ($services_meta !== '') {
            $highlights[] = array('label' => __('Services', 'paxdesign-booking'), 'value' => $services_meta);
        }
        $project_url = (string) ($item['project_url'] ?? '');
        if ($project_url !== '') {
            $highlights[] = array(
                'label' => __('Live project', 'paxdesign-booking'),
                'value' => $project_url,
                'link'  => $project_url,
            );
        }

        if (empty($parsed['metadata']) && !empty($highlights)) {
            foreach ($highlights as $row) {
                $parsed['metadata'][] = array(
                    'label' => $row['label'],
                    'value' => $row['value'],
                    'link'  => isset($row['link']) ? $row['link'] : '',
                );
            }
        }

        $summary = (string) ($parsed['hero']['subtitle'] ?? '');
        if ($summary === '') {
            $summary = self::clean_text((string) ($item['excerpt'] ?? ''));
        }
        if (self::is_placeholder_text($summary)) {
            $summary = '';
        }
        if ($summary === '' && !empty($parsed['sections'][0]['body'])) {
            $summary = wp_trim_words($parsed['sections'][0]['body'], 32, '…');
        }

        return array(
            'hero'            => $parsed['hero'],
            'stats'           => $parsed['stats'],
            'metadata'        => $parsed['metadata'],
            'sections'        => $parsed['sections'],
            'services'        => $parsed['services'],
            'tags'            => $parsed['tags'],
            'gallery'         => $parsed['gallery'],
            'cta'             => $parsed['cta'],
            'summary'         => $summary,
            'paragraphs'      => array_values(array_filter(array_map(array(__CLASS__, 'clean_text'), $parsed['paragraphs']))),
            'highlights'      => $highlights,
            'website_url'     => 'https://paxdesign.at/projekte-referenzen/',
            'published_label' => get_the_date('', $post),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, mixed> $item
     * @param WP_Post $post
     * @return array<string, mixed>
     */
    private static function structured_from_blocks(array $blocks, array $item, $post) {
        $hero = array(
            'headline' => self::clean_title((string) ($item['title'] ?? ''), $post),
            'subtitle' => '',
        );
        $stats = array();
        $metadata = array();
        $sections = array();
        $services = array();
        $tags = array();
        $paragraphs = array();
        $gallery = array();
        $cta = null;

        if (!empty($item['gallery']) && is_array($item['gallery'])) {
            foreach ($item['gallery'] as $url) {
                $clean_url = esc_url_raw((string) $url);
                if ($clean_url !== '' && !self::is_external_demo_image($clean_url)) {
                    $gallery[] = array('url' => $clean_url, 'caption' => '');
                }
            }
        }

        $metadata_labels = array(
            'industry', 'scope', 'duration', 'stage', 'client', 'year', 'services',
            'branche', 'umfang', 'dauer', 'phase', 'kunde', 'jahr', 'leistungen',
        );
        $pending_label = '';
        $pending_section = '';
        $seen_metadata_labels = array();

        foreach ($blocks as $block) {
            if (!is_array($block) || empty($block['type'])) {
                continue;
            }
            $type = (string) $block['type'];

            if ($type === 'feature') {
                $title = self::clean_text((string) ($block['title'] ?? ''));
                $text = self::clean_text((string) ($block['text'] ?? ''));
                if ($title === '') {
                    continue;
                }
                if (self::looks_like_stat($title)) {
                    $stat = self::parse_stat($title);
                    if ($stat !== null) {
                        $stats[] = $stat;
                        continue;
                    }
                }
                if ($hero['subtitle'] === '' && !self::is_placeholder_text($title)) {
                    if ($text !== '') {
                        $hero['subtitle'] = $text;
                    } elseif (strlen($title) > 40) {
                        $hero['subtitle'] = $title;
                    }
                }
                continue;
            }

            if ($type === 'text') {
                $text = self::clean_text((string) ($block['text'] ?? ''));
                if ($text === '' || self::is_placeholder_text($text)) {
                    continue;
                }
                $label_key = strtolower(trim($text, " :"));
                if ($pending_label === '' && in_array($label_key, $metadata_labels, true)) {
                    $pending_label = self::normalize_metadata_label($text);
                    continue;
                }
                if ($pending_label !== '') {
                    if (!isset($seen_metadata_labels[$pending_label])) {
                        $metadata[] = array(
                            'label' => $pending_label,
                            'value' => $text,
                            'link'  => '',
                        );
                        $seen_metadata_labels[$pending_label] = true;
                    }
                    $pending_label = '';
                    continue;
                }
                if ($pending_section !== '') {
                    $sections[] = array(
                        'title' => $pending_section,
                        'body'  => $text,
                    );
                    $paragraphs[] = $text;
                    $pending_section = '';
                    continue;
                }
                $bullets = self::extract_bullet_items((string) ($block['html'] ?? ''), $text);
                if (!empty($bullets)) {
                    $services = array_merge($services, $bullets);
                    continue;
                }
                if (strlen($text) > 40) {
                    $paragraphs[] = $text;
                }
                continue;
            }

            if ($type === 'heading') {
                $heading = self::clean_text((string) ($block['text'] ?? ''));
                if ($heading === '' || self::is_placeholder_text($heading)) {
                    continue;
                }
                if ($hero['subtitle'] === '' && empty($sections) && empty($stats)) {
                    $hero['subtitle'] = $heading;
                } else {
                    $pending_section = $heading;
                }
                continue;
            }

            if ($type === 'list') {
                $items = isset($block['items']) && is_array($block['items']) ? $block['items'] : array();
                $clean_items = array();
                foreach ($items as $line) {
                    $line = self::clean_text((string) $line);
                    if ($line !== '' && !self::is_placeholder_text($line)) {
                        $clean_items[] = $line;
                    }
                }
                if (empty($clean_items)) {
                    continue;
                }
                if ($pending_section !== '' && stripos($pending_section, 'leist') !== false) {
                    $services = array_merge($services, $clean_items);
                    $pending_section = '';
                } elseif (count($clean_items) <= 6 && strlen($clean_items[0]) <= 24) {
                    $tags = array_merge($tags, $clean_items);
                } else {
                    $services = array_merge($services, $clean_items);
                }
                continue;
            }

            if ($type === 'image') {
                $url = esc_url_raw((string) ($block['url'] ?? ''));
                if ($url !== '' && !self::is_external_demo_image($url)) {
                    $gallery[] = array(
                        'url'     => $url,
                        'caption' => self::clean_text((string) ($block['caption'] ?? '')),
                    );
                }
                continue;
            }

            if ($type === 'gallery' && !empty($block['images']) && is_array($block['images'])) {
                foreach ($block['images'] as $url) {
                    $url = esc_url_raw((string) $url);
                    if ($url !== '' && !self::is_external_demo_image($url)) {
                        $gallery[] = array('url' => $url, 'caption' => '');
                    }
                }
                continue;
            }

            if ($type === 'button') {
                $label = self::clean_text((string) ($block['text'] ?? ''));
                $url = esc_url_raw((string) ($block['url'] ?? ''));
                if ($label !== '' && !self::is_noise_button_label($label)) {
                    $cta = array(
                        'label' => $label,
                        'url'   => $url !== '' ? $url : (get_permalink($post) ? (string) get_permalink($post) : ''),
                    );
                }
            }
        }

        if ($hero['subtitle'] === '' && !empty($paragraphs[0])) {
            $hero['subtitle'] = wp_trim_words($paragraphs[0], 24, '…');
        }

        $gallery = self::unique_gallery($gallery);
        $services = array_values(array_unique(array_filter($services)));
        $tags = array_values(array_unique(array_filter($tags)));

        return array(
            'hero'       => $hero,
            'stats'      => $stats,
            'metadata'   => $metadata,
            'sections'   => $sections,
            'services'   => $services,
            'tags'       => $tags,
            'gallery'    => $gallery,
            'cta'        => $cta,
            'paragraphs' => $paragraphs,
        );
    }

    private static function clean_title($title, $post = null) {
        $title = self::clean_text($title);
        if ($title === '' || self::is_placeholder_text($title)) {
            if ($post instanceof WP_Post) {
                $title = self::clean_text(str_replace(array('-', '_'), ' ', $post->post_name));
                $title = ucwords($title);
            }
        }
        return $title;
    }

    private static function clean_excerpt($post) {
        $raw = $post->post_excerpt ?: $post->post_content;
        $text = self::clean_text(wp_trim_words(wp_strip_all_tags($raw), 28, '…'));
        if (self::is_placeholder_text($text)) {
            return '';
        }
        if (preg_match('/\b0\s*%|\bIndustry\b|\bScope\b|\bStage\b/ui', $text)) {
            return '';
        }
        return $text;
    }

    private static function clean_text($text) {
        if ($text === null) {
            $text = '';
        }
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = wp_strip_all_tags($text);
        $text = str_replace(array('&hellip;', '…'), '…', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }

    private static function is_placeholder_text($text) {
        $text = strtolower(self::clean_text($text));
        if ($text === '') {
            return true;
        }
        $needles = array(
            'single line or paragraph description',
            'product or service',
            'lorem ipsum',
            'your text here',
            'sample text',
            'placeholder',
        );
        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function looks_like_stat($text) {
        return (bool) preg_match('/(\+?\d[\d,\.]*\s*%|\+\d+)/u', $text);
    }

    /**
     * @return array{value:string,label:string}|null
     */
    private static function parse_stat($text) {
        if (preg_match('/^(\+?\d[\d,\.]*\s*%)\s*(.+)$/u', $text, $matches)) {
            return array(
                'value' => trim($matches[1]),
                'label' => trim($matches[2]),
            );
        }
        if (preg_match('/^(.+?)\s*(\+?\d[\d,\.]*\s*%)$/u', $text, $matches)) {
            return array(
                'value' => trim($matches[2]),
                'label' => trim($matches[1]),
            );
        }
        if (preg_match('/(\+?\d[\d,\.]*\s*%)/u', $text, $matches)) {
            $value = trim($matches[1]);
            $label = trim(str_replace($value, '', $text));
            if ($label !== '') {
                return array('value' => $value, 'label' => $label);
            }
        }
        return null;
    }

    private static function normalize_metadata_label($label) {
        $label = self::clean_text($label);
        $map = array(
            'branche' => __('Industry', 'paxdesign-booking'),
            'industry' => __('Industry', 'paxdesign-booking'),
            'scope' => __('Scope', 'paxdesign-booking'),
            'umfang' => __('Scope', 'paxdesign-booking'),
            'duration' => __('Duration', 'paxdesign-booking'),
            'dauer' => __('Duration', 'paxdesign-booking'),
            'stage' => __('Stage', 'paxdesign-booking'),
            'phase' => __('Stage', 'paxdesign-booking'),
        );
        $key = strtolower($label);
        return isset($map[$key]) ? $map[$key] : ucfirst($label);
    }

    /**
     * @return string[]
     */
    private static function extract_bullet_items($html, $text) {
        $items = array();
        if ($html !== '' && preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches)) {
            foreach ($matches[1] as $line) {
                $line = self::clean_text($line);
                if ($line !== '') {
                    $items[] = $line;
                }
            }
        }
        if (empty($items) && (strpos($text, '•') !== false || strpos($text, '·') !== false)) {
            foreach (preg_split('/\s*[•·]\s*/u', $text) as $line) {
                $line = self::clean_text($line);
                if ($line !== '') {
                    $items[] = $line;
                }
            }
        }
        return $items;
    }

    private static function is_external_demo_image($url) {
        return (bool) preg_match('#https?://(navein\.tanshcreative\.com|placehold|dummyimage)#i', (string) $url);
    }

    private static function is_noise_button_label($label) {
        $label = strtolower($label);
        return in_array($label, array('mehr details', 'more details', 'read more', 'learn more'), true);
    }

    /**
     * @param array<int, array<string, string>> $gallery
     * @return array<int, array<string, string>>
     */
    private static function unique_gallery(array $gallery) {
        $seen = array();
        $out = array();
        foreach ($gallery as $row) {
            $url = isset($row['url']) ? (string) $row['url'] : '';
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $out[] = array(
                'url'     => $url,
                'caption' => isset($row['caption']) ? (string) $row['caption'] : '',
            );
        }
        return $out;
    }

    /**
     * @return string[]
     */
    private static function gallery_urls($post_id) {
        $urls = array();
        $gallery = get_post_meta($post_id, 'dtr_gallery', true);
        if (is_array($gallery)) {
            foreach ($gallery as $id) {
                $url = wp_get_attachment_image_url((int) $id, 'large');
                if ($url) {
                    $urls[] = $url;
                }
            }
        }
        return $urls;
    }

    /**
     * Fallback when CPT is unavailable — pages tagged or titled as portfolio work.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function fallback_from_pages($limit) {
        $pages = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_wp_page_template',
                    'value'   => 'portfolio',
                    'compare' => 'LIKE',
                ),
            ),
        ));
        $items = array();
        foreach ($pages as $post) {
            $items[] = self::format_post($post, false);
        }
        return $items;
    }
}
