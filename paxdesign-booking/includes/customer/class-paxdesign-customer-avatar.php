<?php
/**
 * Customer profile avatar upload and retrieval.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Avatar {

    const META_ATTACHMENT_ID = 'pax_customer_avatar_id';
    const MAX_BYTES = 5242880;

    /**
     * @param int $user_id
     * @return string
     */
    public static function url_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return '';
        }
        $attachment_id = absint(get_user_meta($user_id, self::META_ATTACHMENT_ID, true));
        if ($attachment_id <= 0) {
            return '';
        }
        $url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        return $url ? (string) $url : '';
    }

    /**
     * @param int $user_id
     * @param array<string, mixed> $file
     * @return array<string, mixed>|WP_Error
     */
    public static function upload_for_user($user_id, $file) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('invalid_user', __('Invalid user.', 'paxdesign-booking'), array('status' => 400));
        }
        if (empty($file) || !empty($file['error'])) {
            return new WP_Error('upload_failed', __('Upload failed.', 'paxdesign-booking'), array('status' => 400));
        }
        if (!empty($file['size']) && (int) $file['size'] > self::MAX_BYTES) {
            return new WP_Error('too_large', __('Image is too large (max 5 MB).', 'paxdesign-booking'), array('status' => 400));
        }

        $mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        );
        $name = isset($file['name']) ? (string) $file['name'] : 'avatar.jpg';
        $check = wp_check_filetype($name, $mimes);
        if (empty($check['type']) || !in_array($check['type'], array_values($mimes), true)) {
            return new WP_Error('invalid_type', __('Only JPEG, PNG, and WebP images are allowed.', 'paxdesign-booking'), array('status' => 400));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $mimes));
        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], array('status' => 500));
        }

        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $check['type'],
            'post_title'     => sanitize_file_name(pathinfo($name, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => $user_id,
        ), $upload['file']);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            @unlink($upload['file']);
            return new WP_Error('upload_failed', __('Could not save avatar.', 'paxdesign-booking'), array('status' => 500));
        }

        $meta = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        if (is_array($meta)) {
            wp_update_attachment_metadata($attachment_id, $meta);
        }

        self::replace_avatar($user_id, (int) $attachment_id);

        return array(
            'avatar_url' => self::url_for_user($user_id),
            'attachment_id' => (int) $attachment_id,
        );
    }

    /**
     * @param int $user_id
     * @param int $attachment_id
     */
    private static function replace_avatar($user_id, $attachment_id) {
        $previous = absint(get_user_meta($user_id, self::META_ATTACHMENT_ID, true));
        update_user_meta($user_id, self::META_ATTACHMENT_ID, $attachment_id);
        if ($previous > 0 && $previous !== $attachment_id) {
            $prev_author = (int) get_post_field('post_author', $previous);
            if ($prev_author === $user_id) {
                wp_delete_attachment($previous, true);
            }
        }
    }
}
