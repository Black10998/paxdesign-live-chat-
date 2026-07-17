<?php
/**
 * Customer chat media upload validation and handling.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Media {

    const MAX_IMAGE_BYTES = 8388608;
    const MAX_AUDIO_BYTES = 10485760;
    const MAX_FILE_BYTES  = 15728640;

    /**
     * @param array<string, mixed> $file
     * @return array<string, string>|WP_Error
     */
    public static function handle_upload($file, $kind) {
        if (empty($file) || !empty($file['error'])) {
            return new WP_Error('upload_failed', __('Upload failed.', 'paxdesign-booking'), array('status' => 400));
        }

        $kind = sanitize_key($kind);
        if ($kind === 'voice') {
            return self::handle_voice_upload($file);
        }

        $limits = array(
            'image' => array(
                'mimes' => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                    'gif'          => 'image/gif',
                ),
                'max' => self::MAX_IMAGE_BYTES,
            ),
            'file' => array(
                'mimes' => array(
                    'pdf'  => 'application/pdf',
                    'doc'  => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'txt'  => 'text/plain',
                    'zip'  => 'application/zip',
                ),
                'max' => self::MAX_FILE_BYTES,
            ),
        );

        if (!isset($limits[$kind])) {
            return new WP_Error('invalid_kind', __('Unsupported attachment type.', 'paxdesign-booking'), array('status' => 400));
        }

        $name = isset($file['name']) ? (string) $file['name'] : 'upload.bin';
        $check = wp_check_filetype($name, $limits[$kind]['mimes']);
        if (empty($check['type']) || !in_array($check['type'], array_values($limits[$kind]['mimes']), true)) {
            return new WP_Error('invalid_type', __('File type is not allowed.', 'paxdesign-booking'), array('status' => 400));
        }
        if (!empty($file['size']) && (int) $file['size'] > $limits[$kind]['max']) {
            return new WP_Error('too_large', __('File is too large.', 'paxdesign-booking'), array('status' => 400));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $limits[$kind]['mimes']));
        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], array('status' => 500));
        }

        $result = array(
            'url'  => esc_url_raw($upload['url']),
            'file' => (string) $upload['file'],
            'name' => sanitize_file_name($name),
            'mime' => $check['type'],
        );

        if ($kind === 'image' && class_exists('PAXdesign_Chat_Live')) {
            $live = PAXdesign_Chat_Live::get_instance();
            if (method_exists($live, 'optimize_chat_image_public')) {
                $result['url'] = $live->optimize_chat_image_public($upload['file'], $upload['url']);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, string>|WP_Error
     */
    private static function handle_voice_upload($file) {
        if (!empty($file['size']) && (int) $file['size'] > self::MAX_AUDIO_BYTES) {
            return new WP_Error('too_large', __('Voice message is too large.', 'paxdesign-booking'), array('status' => 400));
        }

        $name = isset($file['name']) ? strtolower((string) $file['name']) : 'voice.m4a';
        $allowed = array('m4a', 'aac', 'mp4', 'caf', 'wav', 'mp3');
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (!in_array($ext, $allowed, true)) {
            return new WP_Error('invalid_type', __('Voice format is not allowed.', 'paxdesign-booking'), array('status' => 400));
        }

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return new WP_Error('upload_failed', $upload_dir['error'], array('status' => 500));
        }

        $subdir = trailingslashit($upload_dir['basedir']) . 'pax-customer-voice/' . gmdate('Y/m');
        if (!wp_mkdir_p($subdir)) {
            return new WP_Error('upload_failed', __('Could not create upload directory.', 'paxdesign-booking'), array('status' => 500));
        }

        $filename = 'voice-' . gmdate('Ymd-His') . '-' . wp_generate_password(8, false, false) . '.m4a';
        $dest = $subdir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return new WP_Error('upload_failed', __('Could not save voice message.', 'paxdesign-booking'), array('status' => 500));
        }

        $url = trailingslashit($upload_dir['baseurl']) . 'pax-customer-voice/' . gmdate('Y/m') . '/' . $filename;

        return array(
            'url'  => esc_url_raw($url),
            'file' => $dest,
            'name' => $filename,
            'mime' => 'audio/mp4',
        );
    }
}
