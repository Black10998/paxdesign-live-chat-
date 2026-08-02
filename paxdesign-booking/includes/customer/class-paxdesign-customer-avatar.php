<?php
/**
 * Customer profile avatar upload and retrieval.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Avatar {

    const META_ATTACHMENT_ID = 'pax_customer_avatar_id';
    const IMAGE_SIZE         = 'pax_customer_avatar';
    const DISPLAY_PX         = 128;
    const MAX_SOURCE_PX      = 512;
    const MAX_BYTES          = 5242880;
    const JPEG_QUALITY       = 82;

    public static function init() {
        add_action('after_setup_theme', array(__CLASS__, 'register_image_size'), 20);
    }

    public static function register_image_size() {
        add_image_size(self::IMAGE_SIZE, self::DISPLAY_PX, self::DISPLAY_PX, true);
    }

    /**
     * Default avatar when no upload or Gravatar is available.
     *
     * @return string
     */
    public static function default_avatar_url() {
        $url = PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/customer-auth/images/default-avatar.svg';
        return esc_url_raw($url);
    }

    /**
     * Resolve avatar for a customer: manual upload > Gravatar/email provider > default.
     * Always returns an optimized small image URL for UI display.
     *
     * @param int $user_id
     * @return string
     */
    public static function url_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::default_avatar_url();
        }
        $attachment_id = absint(get_user_meta($user_id, self::META_ATTACHMENT_ID, true));
        if ($attachment_id > 0) {
            $url = self::optimized_attachment_url($attachment_id);
            if ($url !== '') {
                return $url;
            }
        }
        $user = get_user_by('id', $user_id);
        if ($user instanceof WP_User && $user->user_email !== '') {
            return (string) get_avatar_url($user_id, array(
                'size'    => self::DISPLAY_PX,
                'default' => self::default_avatar_url(),
            ));
        }
        return self::default_avatar_url();
    }

    /**
     * @param int $attachment_id
     * @return string
     */
    private static function optimized_attachment_url($attachment_id) {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return '';
        }
        self::ensure_optimized_attachment($attachment_id);
        $url = wp_get_attachment_image_url($attachment_id, self::IMAGE_SIZE);
        if ($url) {
            return (string) $url;
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

        self::optimize_attachment((int) $attachment_id);
        self::replace_avatar($user_id, (int) $attachment_id);

        return array(
            'avatar_url'    => self::url_for_user($user_id),
            'attachment_id' => (int) $attachment_id,
        );
    }

    /**
     * Resize, compress, and generate the dedicated avatar derivative.
     *
     * @param int $attachment_id
     */
    private static function optimize_attachment($attachment_id) {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return;
        }
        $file = get_attached_file($attachment_id);
        if (!$file || !is_string($file) || !file_exists($file)) {
            return;
        }

        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            return;
        }

        if (method_exists($editor, 'set_quality')) {
            $editor->set_quality(self::JPEG_QUALITY);
        }

        $size = $editor->get_size();
        if (is_array($size)) {
            $width = isset($size['width']) ? (int) $size['width'] : 0;
            $height = isset($size['height']) ? (int) $size['height'] : 0;
            if ($width > self::MAX_SOURCE_PX || $height > self::MAX_SOURCE_PX) {
                $editor->resize(self::MAX_SOURCE_PX, self::MAX_SOURCE_PX, false);
                $saved = $editor->save($file);
                if (!is_wp_error($saved) && !empty($saved['path'])) {
                    $file = $saved['path'];
                    update_attached_file($attachment_id, $file);
                    $editor = wp_get_image_editor($file);
                    if (is_wp_error($editor)) {
                        return;
                    }
                    if (method_exists($editor, 'set_quality')) {
                        $editor->set_quality(self::JPEG_QUALITY);
                    }
                }
            }
        }

        $meta = wp_generate_attachment_metadata($attachment_id, $file);
        if (is_array($meta)) {
            wp_update_attachment_metadata($attachment_id, $meta);
        }
    }

    /**
     * Ensure legacy/full-size uploads have a compact avatar derivative.
     *
     * @param int $attachment_id
     */
    private static function ensure_optimized_attachment($attachment_id) {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return;
        }
        $meta = wp_get_attachment_metadata($attachment_id);
        if (is_array($meta) && !empty($meta['sizes'][self::IMAGE_SIZE]['file'])) {
            return;
        }
        self::optimize_attachment($attachment_id);
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
