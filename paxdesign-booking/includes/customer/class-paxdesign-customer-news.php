<?php
/**
 * News and announcements for customer portal.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_News {

    public static function list_for_user($user_id, $limit = 20) {
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
                $items[] = self::format($row);
            }
        }
        return $items;
    }

    public static function get_published($slug) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . PAXdesign_Customer_DB::table('news') . " WHERE slug = %s AND status = 'published' LIMIT 1",
            sanitize_title($slug)
        ), ARRAY_A);
        return $row ? self::format($row, true) : null;
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
            $meta = json_decode($row['audience_meta'] ?: '[]', true);
            return is_array($meta) && in_array((int) $user_id, array_map('intval', $meta), true);
        }
        return true;
    }

    private static function format($row, $full = false) {
        $item = array(
            'slug'         => $row['slug'],
            'title'        => $row['title'],
            'excerpt'      => $row['excerpt'],
            'priority'     => $row['priority'],
            'published_at' => $row['published_at'],
        );
        if ($full) {
            $item['body'] = $row['body'];
            if (!empty($row['image_attachment_id'])) {
                $item['image_url'] = wp_get_attachment_url((int) $row['image_attachment_id']);
            }
        }
        return $item;
    }

    public static function list_admin($status = '') {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $sql = "SELECT * FROM $table";
        $params = array();
        if ($status !== '') {
            $sql .= " WHERE status = %s";
            $params[] = sanitize_key($status);
        }
        $sql .= " ORDER BY updated_at DESC LIMIT 100";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return $rows ?: array();
    }

    public static function save($data, $actor_id, $news_id = 0) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $title = sanitize_text_field($data['title'] ?? '');
        if ($title === '') {
            return new WP_Error('invalid_news', __('Title is required.', 'paxdesign-booking'), array('status' => 400));
        }
        $slug = sanitize_title($data['slug'] ?? $title);
        $now = current_time('mysql', true);
        $row = array(
            'slug'                => $slug,
            'title'               => $title,
            'excerpt'             => sanitize_textarea_field($data['excerpt'] ?? ''),
            'body'                => wp_kses_post($data['body'] ?? ''),
            'status'              => sanitize_key($data['status'] ?? 'draft'),
            'priority'            => sanitize_key($data['priority'] ?? 'normal'),
            'audience'            => sanitize_key($data['audience'] ?? 'all_customers'),
            'audience_meta'       => wp_json_encode($data['audience_meta'] ?? array()),
            'push_on_publish'     => !empty($data['push_on_publish']) ? 1 : 0,
            'updated_at'          => $now,
            'author_user_id'      => absint($actor_id),
        );
        if ($news_id > 0) {
            $wpdb->update($table, $row, array('id' => absint($news_id)));
            return (int) $news_id;
        }
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    public static function publish($news_id, $actor_id) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $news_id = absint($news_id);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $news_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', __('News item not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $now = current_time('mysql', true);
        $wpdb->update($table, array(
            'status'       => 'published',
            'published_at' => $now,
            'updated_at'   => $now,
            'author_user_id' => absint($actor_id),
        ), array('id' => $news_id));
        if (!empty($row['push_on_publish'])) {
            PAXdesign_Customer_Notifications::broadcast_news($news_id, $row['title'], $row['excerpt']);
        }
        return true;
    }
}
