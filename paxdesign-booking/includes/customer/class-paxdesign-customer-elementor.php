<?php
/**
 * Elementor → native content blocks for customer portal / iOS app.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Elementor {

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $item
     */
    public static function enrich_item($post, array &$item, $full = false) {
        if (!$full || !$post instanceof WP_Post) {
            return;
        }

        $html = self::render_html($post);
        if ($html !== '') {
            $item['body_html'] = wp_kses_post($html);
            $item['body_text'] = wp_trim_words(wp_strip_all_tags($html), 800, '…');
            $item['gallery'] = self::images_from_html($html);
        }

        $blocks = self::parse_blocks((int) $post->ID);
        if (!empty($blocks)) {
            $item['blocks'] = $blocks;
        }
    }

    /**
     * @param WP_Post $post
     * @return string
     */
    public static function render_html($post) {
        if ($post instanceof WP_Post && class_exists('\Elementor\Plugin')) {
            $plugin = \Elementor\Plugin::$instance;
            if ($plugin && isset($plugin->frontend) && method_exists($plugin->frontend, 'get_builder_content_for_display')) {
                $html = $plugin->frontend->get_builder_content_for_display($post->ID);
                if (is_string($html) && trim($html) !== '') {
                    return $html;
                }
            }
        }
        return (string) apply_filters('the_content', $post->post_content);
    }

    /**
     * @param int $post_id
     * @return array<int, array<string, mixed>>
     */
    public static function parse_blocks($post_id) {
        $raw = get_post_meta($post_id, '_elementor_data', true);
        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return array();
        }
        return self::walk_elements($data);
    }

    /**
     * @param array<int, mixed> $elements
     * @return array<int, array<string, mixed>>
     */
    private static function walk_elements(array $elements) {
        $blocks = array();
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            $widget = isset($element['widgetType']) ? (string) $element['widgetType'] : '';
            $settings = isset($element['settings']) && is_array($element['settings']) ? $element['settings'] : array();

            if ($widget !== '') {
                $block = self::widget_block($widget, $settings);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }

            if (!empty($element['elements']) && is_array($element['elements'])) {
                $blocks = array_merge($blocks, self::walk_elements($element['elements']));
            }
        }
        return $blocks;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|null
     */
    private static function widget_block($widget, array $settings) {
        switch ($widget) {
            case 'heading':
                $text = wp_strip_all_tags((string) ($settings['title'] ?? ''));
                if ($text === '') {
                    return null;
                }
                return array(
                    'type'  => 'heading',
                    'text'  => $text,
                    'level' => self::heading_level($settings),
                );

            case 'text-editor':
                $html = (string) ($settings['editor'] ?? '');
                if (trim(wp_strip_all_tags($html)) === '') {
                    return null;
                }
                return array(
                    'type' => 'text',
                    'html' => wp_kses_post($html),
                    'text' => wp_trim_words(wp_strip_all_tags($html), 200, '…'),
                );

            case 'image':
                $url = self::resolve_image_url($settings);
                if ($url === '') {
                    return null;
                }
                return array(
                    'type'    => 'image',
                    'url'     => $url,
                    'caption' => wp_strip_all_tags((string) ($settings['caption'] ?? '')),
                );

            case 'image-gallery':
            case 'gallery':
                $images = self::resolve_gallery_urls($settings);
                if (empty($images)) {
                    return null;
                }
                return array(
                    'type'   => 'gallery',
                    'images' => $images,
                );

            case 'icon-box':
                $title = wp_strip_all_tags((string) ($settings['title_text'] ?? ''));
                $text  = wp_strip_all_tags((string) ($settings['description_text'] ?? ''));
                if ($title === '' && $text === '') {
                    return null;
                }
                return array(
                    'type'  => 'feature',
                    'title' => $title,
                    'text'  => $text,
                    'icon'  => self::resolve_icon($settings),
                );

            case 'icon-list':
                $items = array();
                if (!empty($settings['icon_list']) && is_array($settings['icon_list'])) {
                    foreach ($settings['icon_list'] as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $line = wp_strip_all_tags((string) ($row['text'] ?? ''));
                        if ($line !== '') {
                            $items[] = $line;
                        }
                    }
                }
                if (empty($items)) {
                    return null;
                }
                return array(
                    'type'  => 'list',
                    'items' => $items,
                );

            case 'accordion':
            case 'toggle':
                $items = array();
                $rows = isset($settings['tabs']) && is_array($settings['tabs']) ? $settings['tabs'] : array();
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $title = wp_strip_all_tags((string) ($row['tab_title'] ?? $row['title'] ?? ''));
                    $body  = wp_trim_words(wp_strip_all_tags((string) ($row['tab_content'] ?? $row['content'] ?? '')), 120, '…');
                    if ($title !== '' || $body !== '') {
                        $items[] = array('title' => $title, 'text' => $body);
                    }
                }
                if (empty($items)) {
                    return null;
                }
                return array(
                    'type'  => 'accordion',
                    'items' => $items,
                );

            case 'video':
                $url = esc_url_raw((string) ($settings['youtube_url'] ?? $settings['vimeo_url'] ?? $settings['hosted_url'] ?? ''));
                if ($url === '') {
                    return null;
                }
                return array(
                    'type'  => 'video',
                    'url'   => $url,
                    'title' => wp_strip_all_tags((string) ($settings['title'] ?? '')),
                );

            case 'button':
                $text = wp_strip_all_tags((string) ($settings['text'] ?? ''));
                $link = self::resolve_link($settings['link'] ?? array());
                if ($text === '') {
                    return null;
                }
                return array(
                    'type'   => 'button',
                    'text'   => $text,
                    'slug'   => $link['slug'],
                    'action' => $link['action'],
                );

            default:
                return null;
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function heading_level(array $settings) {
        $size = sanitize_key((string) ($settings['header_size'] ?? 'h2'));
        if (preg_match('/h(\d)/', $size, $m)) {
            return max(1, min(6, (int) $m[1]));
        }
        return 2;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function resolve_image_url(array $settings) {
        if (!empty($settings['image']['url'])) {
            return esc_url_raw((string) $settings['image']['url']);
        }
        if (!empty($settings['image']['id'])) {
            $url = wp_get_attachment_image_url((int) $settings['image']['id'], 'large');
            return $url ? esc_url_raw($url) : '';
        }
        return '';
    }

    /**
     * @param array<string, mixed> $settings
     * @return string[]
     */
    private static function resolve_gallery_urls(array $settings) {
        $urls = array();
        $gallery = isset($settings['gallery']) && is_array($settings['gallery']) ? $settings['gallery'] : array();
        foreach ($gallery as $item) {
            if (is_array($item) && !empty($item['url'])) {
                $urls[] = esc_url_raw((string) $item['url']);
            } elseif (is_numeric($item)) {
                $url = wp_get_attachment_image_url((int) $item, 'large');
                if ($url) {
                    $urls[] = esc_url_raw($url);
                }
            }
        }
        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function resolve_icon(array $settings) {
        if (!empty($settings['selected_icon']['value'])) {
            return sanitize_text_field((string) $settings['selected_icon']['value']);
        }
        return '';
    }

    /**
     * @param array<string, mixed> $link
     * @return array{slug:string,action:string}
     */
    private static function resolve_link($link) {
        if (!is_array($link)) {
            return array('slug' => '', 'action' => 'none');
        }
        $url = esc_url_raw((string) ($link['url'] ?? ''));
        if ($url === '') {
            return array('slug' => '', 'action' => 'none');
        }
        $home = home_url('/');
        if (strpos($url, $home) === 0 || strpos($url, '/') === 0) {
            $path = wp_parse_url($url, PHP_URL_PATH);
            $slug = is_string($path) ? sanitize_title(basename(untrailingslashit($path))) : '';
            return array('slug' => $slug, 'action' => $slug !== '' ? 'page' : 'none');
        }
        return array('slug' => '', 'action' => 'external');
    }

    /**
     * @param string $html
     * @return string[]
     */
    public static function images_from_html($html) {
        $urls = array();
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $urls[] = esc_url_raw($url);
            }
        }
        return array_values(array_unique(array_filter($urls)));
    }
}
