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
}
