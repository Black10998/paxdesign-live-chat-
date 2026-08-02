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
    const META_VIP_GRANTS    = 'pax_customer_vip_avatar_grants';
    const IMAGE_SIZE         = 'pax_customer_avatar';
    const DISPLAY_PX         = 128;
    const MAX_SOURCE_PX      = 512;
    const MAX_BYTES          = 5242880;
    const JPEG_QUALITY       = 82;

    public static function init() {
        add_action('after_setup_theme', array(__CLASS__, 'register_image_size'), 20);
        add_action('user_register', array(__CLASS__, 'on_user_register'), 20, 1);
        add_action('pdx_user_logged_in', array(__CLASS__, 'on_user_logged_in'), 10, 1);
    }

    /**
     * @param int $user_id
     */
    public static function on_user_register($user_id) {
        self::ensure_preset_assigned($user_id);
    }

    /**
     * @param int $user_id
     */
    public static function on_user_logged_in($user_id) {
        self::ensure_preset_assigned($user_id);
    }

    /**
     * Persist a random PAXDesign avatar preset when the account has none saved yet.
     *
     * @param int $user_id
     * @return string Assigned or existing preset id.
     */
    public static function ensure_preset_assigned($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return PAXdesign_Customer_Avatar_Presets::PRESET_NONE;
        }
        $saved = get_user_meta($user_id, self::META_PRESET_ID, true);
        if (is_string($saved) && self::saved_preset_is_valid($user_id, $saved)) {
            return $saved;
        }
        $preset_id = PAXdesign_Customer_Avatar_Presets::random_id();
        update_user_meta($user_id, self::META_PRESET_ID, $preset_id);
        return $preset_id;
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
        if ($user_id <= 0) {
            return PAXdesign_Customer_Avatar_Presets::auto_id_for_user(0);
        }
        $saved = get_user_meta($user_id, self::META_PRESET_ID, true);
        if (is_string($saved) && self::saved_preset_is_valid($user_id, $saved)) {
            return $saved;
        }
        return self::ensure_preset_assigned($user_id);
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function uses_none_preset($user_id) {
        $preset_id = get_user_meta(absint($user_id), self::META_PRESET_ID, true);
        return is_string($preset_id) && PAXdesign_Customer_Avatar_Presets::is_none($preset_id);
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
        $preset_id = self::preset_id_for_user($user_id);
        $url = self::url_for_preset_id($preset_id);
        return $url !== '' ? $url : self::default_avatar_url();
    }

    /**
     * @param string $preset_id
     * @return string
     */
    public static function url_for_preset_id($preset_id) {
        if (PAXdesign_Customer_Avatar_Vip_Presets::is_vip($preset_id)) {
            return PAXdesign_Customer_Avatar_Vip_Presets::url_for_id($preset_id);
        }
        return PAXdesign_Customer_Avatar_Presets::url_for_id($preset_id);
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>
     */
    public static function profile_fields($user_id) {
        $user_id = absint($user_id);
        if ($user_id > 0) {
            self::ensure_preset_assigned($user_id);
        }
        $preset_id = self::preset_id_for_user($user_id);
        return array_merge(array(
            'avatar_url'          => PAXdesign_Customer_Avatar_Presets::normalize_asset_url(self::url_for_user($user_id)),
            'avatar_fallback_url' => PAXdesign_Customer_Avatar_Presets::normalize_asset_url(self::fallback_url_for_user($user_id)),
            'avatar_preset'       => $preset_id,
            'avatar_has_upload'   => self::has_upload($user_id),
            'avatar_has_image'    => self::has_visible_avatar($user_id),
            'vip_avatar_grants'   => self::vip_grants_for_user($user_id),
        ), class_exists('PAXdesign_Customer_Levels') ? PAXdesign_Customer_Levels::profile_fields($user_id) : array());
    }

    /**
     * @param int $user_id
     * @param string $preset_id
     * @return true|WP_Error
     */
    public static function set_preset_for_user($user_id, $preset_id) {
        $user_id = absint($user_id);
        $preset_id = self::sanitize_preset_id($preset_id);
        if ($preset_id === '') {
            return new WP_Error('invalid_preset', __('Please choose a valid PAXDesign avatar.', 'paxdesign-booking'), array('status' => 400));
        }
        if (PAXdesign_Customer_Avatar_Vip_Presets::is_vip($preset_id)) {
            if (!self::has_vip_grant($user_id, $preset_id)) {
                return new WP_Error('vip_locked', __('This exclusive avatar is not available on your account.', 'paxdesign-booking'), array('status' => 403));
            }
        } elseif (!PAXdesign_Customer_Avatar_Presets::exists($preset_id)) {
            return new WP_Error('invalid_preset', __('Please choose a valid PAXDesign avatar.', 'paxdesign-booking'), array('status' => 400));
        }
        update_user_meta($user_id, self::META_PRESET_ID, $preset_id);
        return true;
    }

    /**
     * @param int $user_id
     * @return array<int, string>
     */
    public static function vip_grants_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return array();
        }
        $raw = get_user_meta($user_id, self::META_VIP_GRANTS, true);
        if (!is_array($raw)) {
            return array();
        }
        $grants = array();
        foreach ($raw as $preset_id) {
            $preset_id = PAXdesign_Customer_Avatar_Vip_Presets::sanitize_id((string) $preset_id);
            if ($preset_id !== '' && !in_array($preset_id, $grants, true)) {
                $grants[] = $preset_id;
            }
        }
        sort($grants);
        return $grants;
    }

    /**
     * @param int $user_id
     * @param string $preset_id
     * @return bool
     */
    public static function has_vip_grant($user_id, $preset_id) {
        $preset_id = PAXdesign_Customer_Avatar_Vip_Presets::sanitize_id($preset_id);
        if ($preset_id === '') {
            return false;
        }
        return in_array($preset_id, self::vip_grants_for_user($user_id), true);
    }

    /**
     * @param int $user_id
     * @param string $preset_id
     * @param bool $set_active
     * @return bool
     */
    public static function grant_vip_avatar($user_id, $preset_id, $set_active = true) {
        $user_id = absint($user_id);
        $preset_id = PAXdesign_Customer_Avatar_Vip_Presets::sanitize_id($preset_id);
        if ($user_id <= 0 || $preset_id === '') {
            return false;
        }
        $grants = self::vip_grants_for_user($user_id);
        if (!in_array($preset_id, $grants, true)) {
            $grants[] = $preset_id;
            update_user_meta($user_id, self::META_VIP_GRANTS, $grants);
        }
        if ($set_active) {
            update_user_meta($user_id, self::META_PRESET_ID, $preset_id);
        }
        return true;
    }

    /**
     * @param int $user_id
     * @param string $preset_id
     * @return bool
     */
    public static function revoke_vip_avatar($user_id, $preset_id) {
        $user_id = absint($user_id);
        $preset_id = PAXdesign_Customer_Avatar_Vip_Presets::sanitize_id($preset_id);
        if ($user_id <= 0 || $preset_id === '') {
            return false;
        }
        $grants = array_values(array_filter(
            self::vip_grants_for_user($user_id),
            static function ($id) use ($preset_id) {
                return $id !== $preset_id;
            }
        ));
        update_user_meta($user_id, self::META_VIP_GRANTS, $grants);
        $active = get_user_meta($user_id, self::META_PRESET_ID, true);
        if (is_string($active) && $active === $preset_id) {
            update_user_meta($user_id, self::META_PRESET_ID, PAXdesign_Customer_Avatar_Presets::random_id());
        }
        return true;
    }

    /**
     * @param string $preset_id
     * @return string
     */
    public static function sanitize_preset_id($preset_id) {
        $preset_id = sanitize_key((string) $preset_id);
        if (PAXdesign_Customer_Avatar_Presets::is_none($preset_id)) {
            return PAXdesign_Customer_Avatar_Presets::PRESET_NONE;
        }
        if (PAXdesign_Customer_Avatar_Vip_Presets::is_vip($preset_id)) {
            return PAXdesign_Customer_Avatar_Vip_Presets::sanitize_id($preset_id);
        }
        return PAXdesign_Customer_Avatar_Presets::sanitize_id($preset_id);
    }

    /**
     * @param int $user_id
     * @param string $preset_id
     * @return bool
     */
    private static function saved_preset_is_valid($user_id, $preset_id) {
        $preset_id = (string) $preset_id;
        if (PAXdesign_Customer_Avatar_Presets::exists($preset_id)) {
            return true;
        }
        if (PAXdesign_Customer_Avatar_Vip_Presets::exists($preset_id)) {
            return self::has_vip_grant($user_id, $preset_id);
        }
        return false;
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
