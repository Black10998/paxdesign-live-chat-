<?php
/**
 * One-off and recurring customer news announcements (upsert + media upload).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_News_Announcements {

    const OPTION_KEY         = 'paxdesign_news_platform_update_2026_v2';
    const ATTACHMENT_OPTION  = 'paxdesign_news_platform_update_2026_attachment';
    const DATA_FILE          = 'news-platform-update-2026.php';

    public static function init() {
        add_action('paxdesign_customer_platform_ready', array(__CLASS__, 'maybe_publish_platform_update'), 25);
    }

    public static function maybe_publish_platform_update() {
        if (get_option(self::OPTION_KEY) === '1') {
            return;
        }
        $result = self::publish_platform_update_2026();
        if (!is_wp_error($result)) {
            update_option(self::OPTION_KEY, '1', false);
        }
    }

    /**
     * @return int|WP_Error News post ID.
     */
    public static function publish_platform_update_2026($force = false) {
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/' . self::DATA_FILE;
        if (!is_readable($path)) {
            return new WP_Error('missing_data', 'News data file not found.', array('status' => 500));
        }
        $data = include $path;
        if (!is_array($data)) {
            return new WP_Error('invalid_data', 'News data file is invalid.', array('status' => 500));
        }

        $media = self::ensure_featured_image($data);
        if (is_wp_error($media)) {
            return $media;
        }

        $translations = isset($data['translations']) && is_array($data['translations']) ? $data['translations'] : array();
        $primary = isset($translations['de']) ? $translations['de'] : reset($translations);
        if (!is_array($primary)) {
            return new WP_Error('invalid_data', 'News translations missing.', array('status' => 500));
        }

        $slug = sanitize_title($data['slug'] ?? 'plattform-update-2026');
        $existing = PAXdesign_Customer_News::find_row_by_slug($slug, false);
        $news_id = $existing ? (int) $existing['id'] : 0;

        if (!$force && $news_id > 0 && get_option(self::OPTION_KEY) === '1') {
            return $news_id;
        }

        $link_labels = isset($data['external_link_label']) && is_array($data['external_link_label'])
            ? $data['external_link_label']
            : array();

        $payload = array(
            'slug'                => $slug,
            'title'               => (string) ($primary['title'] ?? ''),
            'excerpt'             => (string) ($primary['excerpt'] ?? ''),
            'body'                => (string) ($primary['body'] ?? ''),
            'status'              => 'published',
            'priority'            => sanitize_key($data['priority'] ?? 'high'),
            'audience'            => 'all_customers',
            'featured_image_url'  => (string) $media['url'],
            'image_attachment_id' => (int) $media['id'],
            'external_url'        => (string) ($data['external_url'] ?? ''),
            'external_link_label' => (string) ($link_labels['de'] ?? __('Learn more', 'paxdesign-booking')),
            'audience_meta'       => array(
                'featured_image_url'  => (string) $media['url'],
                'external_url'        => (string) ($data['external_url'] ?? ''),
                'external_link_label' => (string) ($link_labels['de'] ?? ''),
                'translations'        => $translations,
                'link_labels'         => $link_labels,
            ),
            'published_at'        => gmdate('Y-m-d H:i:s'),
        );

        $actor_id = 1;
        $admins = get_users(array(
            'role'    => 'administrator',
            'number'  => 1,
            'orderby' => 'ID',
            'order'   => 'ASC',
            'fields'  => array('ID'),
        ));
        if (!empty($admins[0]->ID)) {
            $actor_id = (int) $admins[0]->ID;
        }

        $saved = PAXdesign_Customer_News::save($payload, $actor_id, $news_id);
        if (is_wp_error($saved)) {
            return $saved;
        }

        if (!$existing || ($existing['status'] ?? '') !== 'published') {
            PAXdesign_Customer_News::publish((int) $saved, $actor_id);
        }

        update_option(self::ATTACHMENT_OPTION, (int) $media['id'], false);
        update_option(self::OPTION_KEY, '1', false);

        return (int) $saved;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id:int,url:string}|WP_Error
     */
    private static function ensure_featured_image(array $data) {
        $stored_id = (int) get_option(self::ATTACHMENT_OPTION, 0);
        if ($stored_id > 0) {
            $url = wp_get_attachment_url($stored_id);
            if ($url) {
                return array('id' => $stored_id, 'url' => (string) $url);
            }
        }

        $image = isset($data['image']) && is_array($data['image']) ? $data['image'] : array();
        $filename = sanitize_file_name((string) ($image['filename'] ?? 'platform-update-2026-hero.png'));
        $source = PAXDESIGN_BOOKING_PLUGIN_DIR . 'assets/customer-news/' . $filename;
        if (!is_readable($source)) {
            return new WP_Error('missing_image', 'News hero image missing in plugin assets.', array('status' => 500));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return new WP_Error('upload_dir', $upload_dir['error'], array('status' => 500));
        }

        $dest_name = wp_unique_filename($upload_dir['path'], $filename);
        $dest_path = trailingslashit($upload_dir['path']) . $dest_name;
        if (!copy($source, $dest_path)) {
            return new WP_Error('copy_failed', 'Could not copy news hero image into uploads.', array('status' => 500));
        }

        $filetype = wp_check_filetype($dest_name, null);
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $filetype['type'] ?: 'image/png',
            'post_title'     => sanitize_text_field((string) ($image['title'] ?? 'PAXdesign News')),
            'post_content'   => '',
            'post_excerpt'   => sanitize_text_field((string) ($image['alt'] ?? '')),
            'post_status'    => 'inherit',
        ), $dest_path);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            @unlink($dest_path);
            return new WP_Error('attachment_failed', 'Could not create news image attachment.', array('status' => 500));
        }

        $meta = wp_generate_attachment_metadata((int) $attachment_id, $dest_path);
        if (is_array($meta)) {
            wp_update_attachment_metadata((int) $attachment_id, $meta);
        }

        $url = wp_get_attachment_url((int) $attachment_id);
        if (!$url) {
            return new WP_Error('attachment_url', 'News image attachment URL missing.', array('status' => 500));
        }

        update_option(self::ATTACHMENT_OPTION, (int) $attachment_id, false);

        return array(
            'id'  => (int) $attachment_id,
            'url' => (string) $url,
        );
    }
}
