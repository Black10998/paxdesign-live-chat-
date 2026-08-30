<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Photos {
    const MAX_BYTES = 5242880;

    public static function dir() {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'albatros-private';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
        $index = $dir . '/index.php';
        if (!is_file($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
        return $dir;
    }

    public static function from_request($request, $key = 'photo') {
        $files = is_object($request) && method_exists($request, 'get_file_params') ? $request->get_file_params() : array();
        if (!empty($files[$key]) && is_array($files[$key])) {
            return $files[$key];
        }
        return isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : array();
    }

    public static function store_upload($file, $prefix) {
        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp === '' || !is_readable($tmp)) {
            return new WP_Error('alb_photo', Alb_I18n::t('handover.error.photo_required'), array('status' => 400));
        }
        if (!empty($file['size']) && (int) $file['size'] > self::MAX_BYTES) {
            return new WP_Error('alb_photo', Alb_I18n::t('handover.error.photo_type'), array('status' => 400));
        }
        $info = @getimagesize($tmp);
        if (!$info || empty($info['mime'])) {
            return new WP_Error('alb_photo', Alb_I18n::t('handover.error.photo_type'), array('status' => 400));
        }
        $map = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        );
        if (!isset($map[$info['mime']])) {
            return new WP_Error('alb_photo', Alb_I18n::t('handover.error.photo_type'), array('status' => 400));
        }
        $name = $prefix . '-' . wp_generate_password(16, false, false) . '.' . $map[$info['mime']];
        $dest = self::dir() . '/' . $name;
        $moved = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : (@rename($tmp, $dest) || @copy($tmp, $dest));
        if (!$moved || !is_file($dest)) {
            return new WP_Error('alb_photo', Alb_I18n::t('error.save_failed'), array('status' => 500));
        }
        @chmod($dest, 0640);
        return $name;
    }

    public static function delete_file($filename) {
        $full = self::path($filename);
        if ($full !== '') {
            @unlink($full);
        }
    }

    public static function path($filename) {
        $filename = self::safe_name($filename);
        if ($filename === '') {
            return '';
        }
        $full = self::dir() . '/' . $filename;
        return is_file($full) ? $full : '';
    }

    public static function admin_url($kind, $id, $version = '') {
        $url = home_url('/alb-photo/' . $kind . '/' . (int) $id);
        $version = self::safe_name($version);
        return $version !== '' ? add_query_arg('v', $version, $url) : $url;
    }

    public static function selfie_url($token) {
        return home_url('/s/' . $token . '/selfie');
    }

    public static function serve_request($path) {
        if (!Alb_Capabilities::can_use_admin_app()) {
            status_header(403);
            exit;
        }
        if (!preg_match('#^alb-photo/(driver|handover|user)/(\d+)$#', $path, $match)) {
            status_header(404);
            exit;
        }
        $filename = '';
        if ($match[1] === 'user') {
            $filename = (string) get_user_meta((int) $match[2], 'alb_photo_path', true);
        } elseif ($match[1] === 'driver') {
            $driver = Alb_Drivers::get((int) $match[2]);
            $filename = $driver['photo_path'] ?? '';
            if ($filename === '' && !empty($driver['user_id'])) {
                $filename = (string) get_user_meta((int) $driver['user_id'], 'alb_photo_path', true);
            }
        } else {
            $row = Alb_Drivers::handover_snapshot((int) $match[2]);
            $filename = $row['snapshot_photo'] ?? '';
        }
        self::output($filename);
    }

    public static function serve_public_photo($token) {
        $scanner = Alb_Scanners::public_view($token);
        if (!$scanner || empty($scanner['current_driver_id'])) {
            status_header(404);
            exit;
        }
        $driver = Alb_Drivers::get((int) $scanner['current_driver_id']);
        $filename = is_array($driver) ? (string) ($driver['photo_path'] ?? '') : '';
        if ($filename === '' && !empty($driver['user_id'])) {
            $filename = (string) get_user_meta((int) $driver['user_id'], 'alb_photo_path', true);
        }
        if ($filename === '') {
            status_header(404);
            exit;
        }
        self::output($filename);
    }

    public static function serve_employee_selfie($token) {
        $employee = Alb_Employee::current();
        if (!$employee || empty($employee['photo_path'])) {
            status_header(403);
            exit;
        }
        $scanner = Alb_Scanners::get_by_qr($token);
        if (!$scanner || (int) $scanner['id'] !== (int) $employee['scanner_id']) {
            status_header(403);
            exit;
        }
        self::output($employee['photo_path']);
    }

    public static function output($filename) {
        $full = self::path($filename);
        if ($full === '') {
            status_header(404);
            exit;
        }
        $mime = mime_content_type($full) ?: 'image/jpeg';
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($full));
        header('X-Content-Type-Options: nosniff');
        readfile($full);
        exit;
    }

    private static function safe_name($filename) {
        $filename = basename((string) $filename);
        return preg_match('/^[A-Za-z0-9._-]+$/', $filename) ? $filename : '';
    }
}
