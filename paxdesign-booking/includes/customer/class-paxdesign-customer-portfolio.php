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
    public static function list_items($limit = 100, $category = '') {
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
    public static function get_item($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
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
    public static function categories() {
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
        $item = array(
            'slug'       => $post->post_name,
            'title'      => get_the_title($post),
            'excerpt'    => wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 28),
            'image_url'  => $thumb ? (string) $thumb : '',
            'client'     => (string) get_post_meta($post->ID, 'dtr_client_name', true),
            'project_url'=> (string) get_post_meta($post->ID, 'dtr_project_url', true),
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
        $body_text = wp_strip_all_tags((string) ($item['body_text'] ?? ''));
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $body_text))));
        if (empty($paragraphs) && !empty($item['excerpt'])) {
            $paragraphs = array((string) $item['excerpt']);
        }

        $highlights = array();
        $client = (string) ($item['client'] ?? '');
        if ($client !== '') {
            $highlights[] = array('label' => __('Client', 'paxdesign-booking'), 'value' => $client);
        }
        $year = (string) get_post_meta($post->ID, 'dtr_year', true);
        if ($year !== '') {
            $highlights[] = array('label' => __('Year', 'paxdesign-booking'), 'value' => $year);
        }
        $services = (string) get_post_meta($post->ID, 'dtr_services', true);
        if ($services !== '') {
            $highlights[] = array('label' => __('Services', 'paxdesign-booking'), 'value' => $services);
        }
        $project_url = (string) ($item['project_url'] ?? '');
        if ($project_url !== '') {
            $highlights[] = array(
                'label' => __('Live project', 'paxdesign-booking'),
                'value' => $project_url,
                'link'  => $project_url,
            );
        }

        return array(
            'summary'         => (string) ($item['excerpt'] ?? ''),
            'paragraphs'      => $paragraphs,
            'highlights'      => $highlights,
            'website_url'     => get_permalink($post) ? (string) get_permalink($post) : '',
            'published_label' => get_the_date('', $post),
        );
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
