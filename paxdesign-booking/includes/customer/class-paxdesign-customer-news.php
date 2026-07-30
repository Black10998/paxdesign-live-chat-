<?php
/**
 * News and announcements for customer portal.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_News {

    public static function list_for_user($user_id, $limit = 20, $lang = 'de') {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $now = current_time('mysql', true);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE status = 'published'
               AND (published_at IS NULL OR published_at <= %s)
               AND (expires_at IS NULL OR expires_at > %s)
             ORDER BY published_at DESC, id DESC
             LIMIT %d",
            $now,
            $now,
            min(50, max(1, (int) $limit))
        ), ARRAY_A);
        $items = array();
        foreach ($rows ?: array() as $row) {
            if (self::matches_audience($row, $user_id)) {
                $items[] = self::format($row, false, $lang);
            }
        }
        return $items;
    }

    public static function get_published($slug, $lang = 'de') {
        $row = self::find_row_by_slug($slug, true);
        return $row ? self::format($row, true, $lang) : null;
    }

    public static function get_published_for_user($slug, $user_id, $lang = 'de') {
        $row = self::find_row_by_slug($slug, true);
        if (!$row || !self::matches_audience($row, $user_id)) {
            return null;
        }
        return self::format($row, true, $lang);
    }

    public static function user_matches_audience($row, $user_id) {
        return self::matches_audience($row, $user_id);
    }

    /**
     * Resolve a news row from a slug, numeric id, or legacy slug variants.
     *
     * @param string $slug
     * @param bool   $published_only
     * @return array|null
     */
    public static function find_row_by_slug($slug, $published_only = false) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $raw = trim(rawurldecode((string) $slug));
        if ($raw === '') {
            return null;
        }

        $candidates = array();
        $candidates[] = sanitize_title($raw);
        $candidates[] = sanitize_key($raw);
        $candidates[] = strtolower($raw);
        if (ctype_digit($raw)) {
            $candidates[] = (string) absint($raw);
        }
        $candidates = array_values(array_unique(array_filter($candidates)));

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (ctype_digit($candidate)) {
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d" . ($published_only ? " AND status = 'published'" : '') . ' LIMIT 1',
                    absint($candidate)
                ), ARRAY_A);
                if ($row) {
                    return $row;
                }
            }
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE slug = %s" . ($published_only ? " AND status = 'published'" : '') . ' LIMIT 1',
                $candidate
            ), ARRAY_A);
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public static function get_row($news_id) {
        global $wpdb;
        $news_id = absint($news_id);
        if ($news_id <= 0) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PAXdesign_Customer_DB::table('news') . ' WHERE id = %d LIMIT 1',
            $news_id
        ), ARRAY_A) ?: null;
    }

    private static function decode_meta($row) {
        $meta = json_decode((string) ($row['audience_meta'] ?? ''), true);
        return is_array($meta) ? $meta : array();
    }

    private static function matches_audience($row, $user_id) {
        $audience = $row['audience'] ?? 'all_customers';
        if ($audience === 'all_customers') {
            return true;
        }
        if ($audience === 'employees') {
            return PAXdesign_Live_Chat_Permissions::has_live_chat_access($user_id) || user_can($user_id, 'manage_options');
        }
        if ($audience === 'administrators') {
            return user_can($user_id, 'manage_options');
        }
        if ($audience === 'specific_customers') {
            $meta = self::decode_meta($row);
            return in_array((int) $user_id, array_map('intval', $meta), true);
        }
        return true;
    }

    private static function image_url_for_row($row, array $meta) {
        if (!empty($row['image_attachment_id'])) {
            $url = wp_get_attachment_url((int) $row['image_attachment_id']);
            if ($url) {
                return $url;
            }
        }
        if (!empty($meta['featured_image_url'])) {
            return esc_url_raw((string) $meta['featured_image_url']);
        }
        return '';
    }

    private static function append_external_link_to_body($body, array $meta) {
        $url = !empty($meta['external_url']) ? esc_url_raw((string) $meta['external_url']) : '';
        if ($url === '' || stripos((string) $body, $url) !== false) {
            return (string) $body;
        }
        $label = !empty($meta['external_link_label'])
            ? sanitize_text_field((string) $meta['external_link_label'])
            : __('Learn more', 'paxdesign-booking');
        $suffix = trim($label) . ': ' . $url;
        $body = trim((string) $body);
        return $body === '' ? $suffix : $body . "\n\n" . $suffix;
    }

    private static function localized_copy($row, array $meta, $lang = 'de') {
        $lang = in_array($lang, array('de', 'en', 'ar'), true) ? $lang : 'de';
        $translations = isset($meta['translations']) && is_array($meta['translations']) ? $meta['translations'] : array();
        $localized = isset($translations[$lang]) && is_array($translations[$lang]) ? $translations[$lang] : array();

        $title = !empty($localized['title']) ? (string) $localized['title'] : (string) ($row['title'] ?? '');
        $excerpt = array_key_exists('excerpt', $localized)
            ? (string) $localized['excerpt']
            : (string) ($row['excerpt'] ?? '');
        $body = !empty($localized['body']) ? (string) $localized['body'] : (string) ($row['body'] ?? '');

        return array(
            'title'   => $title,
            'excerpt' => $excerpt,
            'body'    => $body,
        );
    }

    private static function localized_link_label(array $meta, $lang = 'de') {
        $labels = isset($meta['link_labels']) && is_array($meta['link_labels']) ? $meta['link_labels'] : array();
        if (!empty($labels[$lang])) {
            return sanitize_text_field((string) $labels[$lang]);
        }
        if (!empty($meta['external_link_label'])) {
            return sanitize_text_field((string) $meta['external_link_label']);
        }
        return __('Learn more', 'paxdesign-booking');
    }

    private static function format($row, $full = false, $lang = 'de') {
        $meta = self::decode_meta($row);
        $copy = self::localized_copy($row, $meta, $lang);
        $item = array(
            'slug'         => (string) $row['slug'],
            'title'        => $copy['title'],
            'excerpt'      => $copy['excerpt'],
            'priority'     => $row['priority'],
            'published_at' => $row['published_at'],
            'lang'         => in_array($lang, array('de', 'en', 'ar'), true) ? $lang : 'de',
        );

        $image_url = self::image_url_for_row($row, $meta);
        if ($image_url !== '') {
            $item['image_url'] = $image_url;
        }
        if (!empty($meta['external_url'])) {
            $item['external_url'] = esc_url_raw((string) $meta['external_url']);
        }
        $link_label = self::localized_link_label($meta, $lang);
        if ($link_label !== '') {
            $item['external_link_label'] = $link_label;
        }

        if ($full) {
            $body = self::append_external_link_to_body($copy['body'], array_merge($meta, array(
                'external_link_label' => $link_label,
            )));
            $item['body'] = $body;
        }

        return $item;
    }

    public static function list_admin($status = '') {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $sql = "SELECT * FROM $table";
        $params = array();
        if ($status !== '') {
            $sql .= ' WHERE status = %s';
            $params[] = sanitize_key($status);
        }
        $sql .= ' ORDER BY updated_at DESC LIMIT 100';
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return $rows ?: array();
    }

    private static function build_meta_from_data(array $data, $existing_meta = array()) {
        $meta = is_array($existing_meta) ? $existing_meta : array();
        if (array_key_exists('featured_image_url', $data)) {
            $url = esc_url_raw(trim((string) $data['featured_image_url']));
            if ($url !== '') {
                $meta['featured_image_url'] = $url;
            } else {
                unset($meta['featured_image_url']);
            }
        }
        if (array_key_exists('external_url', $data)) {
            $url = esc_url_raw(trim((string) $data['external_url']));
            if ($url !== '') {
                $meta['external_url'] = $url;
            } else {
                unset($meta['external_url']);
            }
        }
        if (array_key_exists('external_link_label', $data)) {
            $label = sanitize_text_field(trim((string) $data['external_link_label']));
            if ($label !== '') {
                $meta['external_link_label'] = $label;
            } else {
                unset($meta['external_link_label']);
            }
        }
        if (array_key_exists('audience_meta', $data) && is_array($data['audience_meta'])) {
            $meta = array_merge($meta, $data['audience_meta']);
        }
        return $meta;
    }

    private static function ensure_unique_slug($slug, $exclude_id = 0) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $base = sanitize_title($slug);
        if ($base === '') {
            $base = 'news-item';
        }
        $candidate = $base;
        $suffix = 2;
        while (true) {
            $sql = "SELECT id FROM $table WHERE slug = %s";
            $params = array($candidate);
            if ($exclude_id > 0) {
                $sql .= ' AND id != %d';
                $params[] = absint($exclude_id);
            }
            $sql .= ' LIMIT 1';
            $existing = $wpdb->get_var($wpdb->prepare($sql, $params));
            if (!$existing) {
                return $candidate;
            }
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }
    }

    public static function save($data, $actor_id, $news_id = 0) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $news_id = absint($news_id);
        $existing = $news_id > 0 ? self::get_row($news_id) : null;
        if ($news_id > 0 && !$existing) {
            return new WP_Error('not_found', __('News item not found.', 'paxdesign-booking'), array('status' => 404));
        }

        $title = sanitize_text_field($data['title'] ?? '');
        if ($title === '') {
            return new WP_Error('invalid_news', __('Title is required.', 'paxdesign-booking'), array('status' => 400));
        }

        $requested_slug = sanitize_title($data['slug'] ?? $title);
        if ($requested_slug === '') {
            $requested_slug = 'news-item';
        }
        $slug = self::ensure_unique_slug($requested_slug, $news_id);

        $existing_meta = $existing ? self::decode_meta($existing) : array();
        $meta = self::build_meta_from_data($data, $existing_meta);

        $now = current_time('mysql', true);
        $status = sanitize_key($data['status'] ?? ($existing['status'] ?? 'draft'));
        if (!in_array($status, array('draft', 'published'), true)) {
            $status = 'draft';
        }

        $row = array(
            'slug'            => $slug,
            'title'           => $title,
            'excerpt'         => sanitize_textarea_field($data['excerpt'] ?? ''),
            'body'            => wp_kses_post($data['body'] ?? ''),
            'status'          => $status,
            'priority'        => sanitize_key($data['priority'] ?? ($existing['priority'] ?? 'normal')),
            'audience'        => sanitize_key($data['audience'] ?? ($existing['audience'] ?? 'all_customers')),
            'audience_meta'   => wp_json_encode($meta),
            'push_on_publish' => !empty($data['push_on_publish']) ? 1 : 0,
            'updated_at'      => $now,
            'author_user_id'  => absint($actor_id),
        );

        if (array_key_exists('image_attachment_id', $data)) {
            $row['image_attachment_id'] = absint($data['image_attachment_id']);
        }

        if ($news_id > 0) {
            if ($status === 'published' && empty($existing['published_at'])) {
                $row['published_at'] = $now;
            }
            $wpdb->update($table, $row, array('id' => $news_id));
            return $news_id;
        }

        $row['created_at'] = $now;
        if ($status === 'published') {
            $row['published_at'] = $now;
        } elseif (!empty($data['published_at'])) {
            $row['published_at'] = gmdate('Y-m-d H:i:s', strtotime((string) $data['published_at']));
        }

        $inserted = $wpdb->insert($table, $row);
        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not save news item.', 'paxdesign-booking'), array('status' => 500));
        }
        return (int) $wpdb->insert_id;
    }

    public static function publish($news_id, $actor_id) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $news_id = absint($news_id);
        $row = self::get_row($news_id);
        if (!$row) {
            return new WP_Error('not_found', __('News item not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $now = current_time('mysql', true);
        $wpdb->update($table, array(
            'status'         => 'published',
            'published_at'   => $row['published_at'] ?: $now,
            'updated_at'     => $now,
            'author_user_id' => absint($actor_id),
        ), array('id' => $news_id));
        if (!empty($row['push_on_publish'])) {
            PAXdesign_Customer_Notifications::broadcast_news($news_id, $row['title'], $row['excerpt']);
        }
        return true;
    }

    public static function unpublish($news_id, $actor_id) {
        global $wpdb;
        $news_id = absint($news_id);
        $row = self::get_row($news_id);
        if (!$row) {
            return new WP_Error('not_found', __('News item not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $wpdb->update(
            PAXdesign_Customer_DB::table('news'),
            array(
                'status'         => 'draft',
                'updated_at'     => current_time('mysql', true),
                'author_user_id' => absint($actor_id),
            ),
            array('id' => $news_id)
        );
        return true;
    }

    public static function delete($news_id) {
        global $wpdb;
        $news_id = absint($news_id);
        $row = self::get_row($news_id);
        if (!$row) {
            return new WP_Error('not_found', __('News item not found.', 'paxdesign-booking'), array('status' => 404));
        }

        $slug = sanitize_title((string) ($row['slug'] ?? ''));
        $wpdb->delete(PAXdesign_Customer_DB::table('news'), array('id' => $news_id), array('%d'));
        if ($slug !== '') {
            PAXdesign_Customer_Notifications::delete_for_entity('news', $slug);
        }
        return true;
    }
}
