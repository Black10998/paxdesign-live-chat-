<?php
/**
 * Customer profile avatar upload and retrieval.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Avatar {

    const META_ATTACHMENT_ID = 'pax_customer_avatar_id';
    const META_PRESET_ID     = 'pax_customer_avatar_preset';
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
     * @return string
     */
    public static function default_avatar_url() {
        return PAXdesign_Customer_Avatar_Presets::url_for_id('pax-01');
    }

    /**
     * Resolve avatar for a customer: manual upload > selected portrait > none.
     *
     * @param int $user_id
     * @return string
     */
    public static function url_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::default_avatar_url();
        }
        if (self::has_upload($user_id)) {
            $attachment_id = absint(get_user_meta($user_id, self::META_ATTACHMENT_ID, true));
            $url = self::optimized_attachment_url($attachment_id);
            if ($url !== '') {
                return $url;
            }
        }
        if (self::uses_none_preset($user_id)) {
            return '';
        }
        return self::preset_url_for_user($user_id);
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function fallback_url_for_user($user_id) {
        if (self::uses_none_preset($user_id)) {
            return '';
        }
        return self::preset_url_for_user($user_id);
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function has_upload($user_id) {
        return absint(get_user_meta(absint($user_id), self::META_ATTACHMENT_ID, true)) > 0;
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function preset_id_for_user($user_id) {
        $user_id = absint($user_id);
        $saved = get_user_meta($user_id, self::META_PRESET_ID, true);
        if (is_string($saved) && PAXdesign_Customer_Avatar_Presets::exists($saved)) {
            return $saved;
        }
        return PAXdesign_Customer_Avatar_Presets::auto_id_for_user($user_id);
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function uses_none_preset($user_id) {
        return PAXdesign_Customer_Avatar_Presets::is_none(self::preset_id_for_user($user_id));
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function has_visible_avatar($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return true;
        }
        if (self::has_upload($user_id)) {
            $attachment_id = absint(get_user_meta($user_id, self::META_ATTACHMENT_ID, true));
            return self::optimized_attachment_url($attachment_id) !== '';
        }
        return !self::uses_none_preset($user_id);
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function preset_url_for_user($user_id) {
        if (self::uses_none_preset($user_id)) {
            return '';
        }
        $url = PAXdesign_Customer_Avatar_Presets::url_for_id(self::preset_id_for_user($user_id));
        return $url !== '' ? $url : self::default_avatar_url();
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>
     */
    public static function profile_fields($user_id) {
        $user_id = absint($user_id);
        $preset_id = self::preset_id_for_user($user_id);
        return array(
            'avatar_url'          => self::url_for_user($user_id),
            'avatar_fallback_url' => self::fallback_url_for_user($user_id),
            'avatar_preset'       => $preset_id,
            'avatar_has_upload'   => self::has_upload($user_id),
            'avatar_has_image'    => self::has_visible_avatar($user_id),
        );
    }

    /**
     * @param int $user_id
     * @param string $preset_id
     * @return true|WP_Error
     */
    public static function set_preset_for_user($user_id, $preset_id) {
        $user_id = absint($user_id);
        $preset_id = PAXdesign_Customer_Avatar_Presets::sanitize_id($preset_id);
        if ($preset_id === '' || !PAXdesign_Customer_Avatar_Presets::exists($preset_id)) {
            return new WP_Error('invalid_preset', __('Please choose a valid PAXDesign avatar.', 'paxdesign-booking'), array('status' => 400));
        }
        update_user_meta($user_id, self::META_PRESET_ID, $preset_id);
        return true;
    }

    /**
     * @param int $user_id
     * @return true|WP_Error
     */
    public static function remove_upload_for_user($user_id) {
        $user_id = absint($user_id);
        $attachment_id = absint(get_user_meta($user_id, self::META_ATTACHMENT_ID, true));
        delete_user_meta($user_id, self::META_ATTACHMENT_ID);
        if ($attachment_id > 0) {
            $prev_author = (int) get_post_field('post_author', $attachment_id);
            if ($prev_author === $user_id) {
                wp_delete_attachment($attachment_id, true);
            }
        }
        return true;
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
