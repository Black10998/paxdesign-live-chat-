<?php
/**
 * Career / job application intake — secure uploads and email delivery.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Career_Intake {

    const TABLE_SUFFIX = 'paxdesign_career_applications';
    const NONCE_ACTION = 'paxdesign_career_application';
    const UPLOAD_SUBDIR = 'pax-career-applications';
    const MAX_CV_BYTES = 5242880; // 5 MB
    const MAX_OPTIONAL_BYTES = 5242880;
    const MAX_CERT_FILES = 5;

    /** @var list<string> */
    private static $positions = array(
        'full_stack',
        'frontend',
        'backend',
        'ui_ux',
        'devops',
        'project_manager',
    );

    public static function init() {
        add_action('init', array(__CLASS__, 'maybe_create_table'));
        add_action('wp_ajax_paxdesign_career_application', array(__CLASS__, 'handle_submit'));
        add_action('wp_ajax_nopriv_paxdesign_career_application', array(__CLASS__, 'handle_submit'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function maybe_create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reference_id varchar(32) NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'submitted',
            applicant_email varchar(190) NOT NULL DEFAULT '',
            position varchar(64) NOT NULL DEFAULT '',
            payload longtext NOT NULL,
            attachments longtext NOT NULL,
            ip_hash varchar(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reference_id (reference_id),
            KEY created_at (created_at),
            KEY applicant_email (applicant_email)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * @return array<string, mixed>
     */
    public static function public_config() {
        return array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
            'action'       => 'paxdesign_career_application',
            'maxCvMb'      => (int) floor(self::MAX_CV_BYTES / 1048576),
            'maxOptionalMb'=> (int) floor(self::MAX_OPTIONAL_BYTES / 1048576),
            'maxCertFiles' => self::MAX_CERT_FILES,
            'privacyUrl'   => esc_url(home_url('/datenschutz/')),
        );
    }

    public static function handle_submit() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!empty($_POST['website_trap'])) {
            wp_send_json_error(array('message' => __('Request rejected.', 'paxdesign-booking')), 403);
        }

        if (!self::check_rate_limit()) {
            wp_send_json_error(array(
                'message' => __('Too many applications. Please wait before trying again.', 'paxdesign-booking'),
            ), 429);
        }

        $parsed = self::parse_submission($_POST);
        if (is_wp_error($parsed)) {
            wp_send_json_error(array('message' => $parsed->get_error_message()), 400);
        }

        $uploads = self::handle_uploads();
        if (is_wp_error($uploads)) {
            wp_send_json_error(array('message' => $uploads->get_error_message()), 400);
        }

        $reference = self::generate_reference_id();
        $now = current_time('mysql', true);
        global $wpdb;

        $inserted = $wpdb->insert(
            self::table_name(),
            array(
                'reference_id'    => $reference,
                'status'          => 'submitted',
                'applicant_email' => $parsed['email'],
                'position'        => $parsed['position'],
                'payload'         => wp_json_encode($parsed),
                'attachments'     => wp_json_encode($uploads),
                'ip_hash'         => self::hash_ip(self::client_ip()),
                'created_at'      => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            wp_send_json_error(array(
                'message' => __('Could not save your application. Please try again.', 'paxdesign-booking'),
            ), 500);
        }

        $mailed = self::notify_admin($reference, $parsed, $uploads);
        if (!$mailed) {
            error_log('[PAXdesign Career] wp_mail failed for reference ' . $reference);
        }

        self::notify_applicant($parsed, $reference);

        wp_send_json_success(array(
            'message'   => __('Thank you! Your application has been submitted successfully.', 'paxdesign-booking'),
            'reference' => $reference,
        ));
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>|WP_Error
     */
    private static function parse_submission($post) {
        $first = sanitize_text_field(wp_unslash($post['first_name'] ?? ''));
        $last = sanitize_text_field(wp_unslash($post['last_name'] ?? ''));
        $email = sanitize_email(wp_unslash($post['email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($post['phone'] ?? ''));
        $address = sanitize_text_field(wp_unslash($post['address'] ?? ''));
        $city = sanitize_text_field(wp_unslash($post['city'] ?? ''));
        $zip = sanitize_text_field(wp_unslash($post['zip'] ?? ''));
        $position = sanitize_key(wp_unslash($post['desired_position'] ?? ''));
        $available = sanitize_text_field(wp_unslash($post['available_from'] ?? ''));
        $salary = sanitize_text_field(wp_unslash($post['salary_expectation'] ?? ''));
        $experience = sanitize_key(wp_unslash($post['experience_years'] ?? ''));
        $work_model = sanitize_key(wp_unslash($post['work_model'] ?? ''));
        $agile = sanitize_key(wp_unslash($post['agile_experience'] ?? ''));
        $skills = sanitize_textarea_field(wp_unslash($post['skills'] ?? ''));
        $motivation = sanitize_textarea_field(wp_unslash($post['motivation'] ?? ''));
        $portfolio = esc_url_raw(wp_unslash($post['portfolio_url'] ?? ''));
        $privacy = !empty($post['privacy_accepted']);
        $talent_pool = !empty($post['talent_pool']);
        $newsletter = !empty($post['newsletter']);

        if ($first === '' || $last === '') {
            return new WP_Error('missing_name', __('Please enter your first and last name.', 'paxdesign-booking'));
        }
        if ($email === '' || !is_email($email)) {
            return new WP_Error('invalid_email', __('Please enter a valid email address.', 'paxdesign-booking'));
        }
        if ($phone === '') {
            return new WP_Error('missing_phone', __('Please enter your phone number.', 'paxdesign-booking'));
        }
        if ($position === '' || !in_array($position, self::$positions, true)) {
            return new WP_Error('invalid_position', __('Please select a desired position.', 'paxdesign-booking'));
        }
        if (!$privacy) {
            return new WP_Error('privacy_required', __('Please accept the privacy policy.', 'paxdesign-booking'));
        }
        if ($skills === '' || $motivation === '') {
            return new WP_Error('missing_screening', __('Please complete the screening questions.', 'paxdesign-booking'));
        }

        if (empty($_FILES['cv']) || (int) ($_FILES['cv']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return new WP_Error('missing_cv', __('Please upload your CV (PDF, max 5 MB).', 'paxdesign-booking'));
        }

        return array(
            'first_name'         => $first,
            'last_name'          => $last,
            'full_name'          => trim($first . ' ' . $last),
            'email'              => $email,
            'phone'              => $phone,
            'address'            => $address,
            'city'               => $city,
            'zip'                => $zip,
            'position'           => $position,
            'position_label'     => self::position_label($position),
            'available_from'     => $available,
            'salary_expectation' => $salary,
            'experience_years'   => $experience,
            'experience_label'   => self::experience_label($experience),
            'work_model'         => $work_model,
            'work_model_label'   => self::work_model_label($work_model),
            'agile_experience'   => $agile,
            'agile_label'        => self::agile_label($agile),
            'skills'             => $skills,
            'motivation'         => $motivation,
            'portfolio_url'      => $portfolio,
            'talent_pool'        => $talent_pool,
            'newsletter'         => $newsletter,
        );
    }

    /**
     * @return array<int, array<string, string>>|WP_Error
     */
    private static function handle_uploads() {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $allowed = array('pdf' => 'application/pdf');
        $saved = array();

        add_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
        try {
            if (!empty($_FILES['cv']) && is_array($_FILES['cv']) && !empty($_FILES['cv']['name'])) {
                $cv = self::upload_single($_FILES['cv'], $allowed, self::MAX_CV_BYTES, 'cv');
                if (is_wp_error($cv)) {
                    return $cv;
                }
                $saved[] = $cv;
            }

            if (!empty($_FILES['cover_letter']) && is_array($_FILES['cover_letter']) && !empty($_FILES['cover_letter']['name'])) {
                $cl = self::upload_single($_FILES['cover_letter'], $allowed, self::MAX_OPTIONAL_BYTES, 'cover_letter');
                if (is_wp_error($cl)) {
                    return $cl;
                }
                $saved[] = $cl;
            }

            if (!empty($_FILES['certificates']) && is_array($_FILES['certificates'])) {
                $cert_files = self::normalize_file_batch($_FILES['certificates']);
                if (count($cert_files) > self::MAX_CERT_FILES) {
                    return new WP_Error('too_many_files', __('Too many certificate files.', 'paxdesign-booking'));
                }
                foreach ($cert_files as $file) {
                    $cert = self::upload_single($file, $allowed, self::MAX_OPTIONAL_BYTES, 'certificate');
                    if (is_wp_error($cert)) {
                        return $cert;
                    }
                    $saved[] = $cert;
                }
            }
        } finally {
            remove_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
        }

        if (empty($saved)) {
            return new WP_Error('missing_cv', __('Please upload your CV (PDF, max 5 MB).', 'paxdesign-booking'));
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, string> $allowed
     * @param int $max_bytes
     * @param string $field
     * @return array<string, string>|WP_Error
     */
    private static function upload_single($file, $allowed, $max_bytes, $field) {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return new WP_Error('missing_file', __('A required file is missing.', 'paxdesign-booking'));
        }
        if ($error !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_failed', self::upload_error_message($error));
        }
        if ((int) ($file['size'] ?? 0) > $max_bytes) {
            return new WP_Error('file_too_large', __('One or more files exceed the size limit.', 'paxdesign-booking'));
        }

        $upload = wp_handle_upload($file, array(
            'test_form' => false,
            'mimes'     => $allowed,
            'unique_filename_callback' => function ($dir, $name, $ext) use ($field) {
                return wp_unique_filename($dir, 'career-' . $field . '-' . wp_generate_password(8, false) . $ext);
            },
        ));

        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error']);
        }

        return array(
            'field' => sanitize_key($field),
            'name'  => basename($upload['file']),
            'path'  => $upload['file'],
            'url'   => $upload['url'],
            'type'  => $upload['type'] ?? 'application/pdf',
            'size'  => (string) filesize($upload['file']),
        );
    }

    /**
     * @param array<string, mixed> $file_field
     * @return array<int, array<string, mixed>>
     */
    private static function normalize_file_batch($file_field) {
        $batch = array();
        $names = $file_field['name'] ?? '';
        if (!is_array($names)) {
            if ($names !== '') {
                $batch[] = $file_field;
            }
            return $batch;
        }
        foreach ($names as $i => $name) {
            if ($name === '') {
                continue;
            }
            $batch[] = array(
                'name'     => $name,
                'type'     => $file_field['type'][$i] ?? '',
                'tmp_name' => $file_field['tmp_name'][$i] ?? '',
                'error'    => $file_field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $file_field['size'][$i] ?? 0,
            );
        }
        return $batch;
    }

    /**
     * @param array<string, string> $dirs
     * @return array<string, string>
     */
    public static function filter_upload_dir($dirs) {
        if (!is_array($dirs)) {
            return $dirs;
        }
        $subdir = '/' . self::UPLOAD_SUBDIR;
        if (strpos((string) ($dirs['subdir'] ?? ''), self::UPLOAD_SUBDIR) === false) {
            $dirs['subdir'] = $subdir;
            $dirs['path']   = ($dirs['basedir'] ?? '') . $subdir;
            $dirs['url']    = ($dirs['baseurl'] ?? '') . $subdir;
        }
        wp_mkdir_p($dirs['path']);
        return $dirs;
    }

    /**
     * @param string $reference
     * @param array<string, mixed> $parsed
     * @param array<int, array<string, string>> $uploads
     */
    private static function notify_admin($reference, $parsed, $uploads) {
        $to = get_option('paxdesign_booking_notification_email', get_option('admin_email'));
        if (!is_email($to)) {
            $to = get_option('admin_email');
        }

        $subject = sprintf(
            '[PAXdesign Karriere] %s — %s',
            $reference,
            $parsed['position_label']
        );

        $body = "Neue Bewerbung über paxdesign.at/karriere/\n\n"
            . "Referenz: {$reference}\n"
            . "Name: {$parsed['full_name']}\n"
            . "E-Mail: {$parsed['email']}\n"
            . "Telefon: {$parsed['phone']}\n"
            . "Adresse: {$parsed['address']}, {$parsed['zip']} {$parsed['city']}\n\n"
            . "Position: {$parsed['position_label']}\n"
            . "Verfügbar ab: {$parsed['available_from']}\n"
            . "Gehaltsvorstellung: {$parsed['salary_expectation']}\n\n"
            . "Berufserfahrung: {$parsed['experience_label']}\n"
            . "Arbeitsmodell: {$parsed['work_model_label']}\n"
            . "Agile Methoden: {$parsed['agile_label']}\n"
            . "Portfolio: {$parsed['portfolio_url']}\n\n"
            . "Technische Skills:\n{$parsed['skills']}\n\n"
            . "Motivation:\n{$parsed['motivation']}\n\n"
            . 'Talent Pool: ' . ($parsed['talent_pool'] ? 'Ja' : 'Nein') . "\n"
            . 'Newsletter: ' . ($parsed['newsletter'] ? 'Ja' : 'Nein') . "\n\n"
            . 'Anhänge: ' . count($uploads) . "\n";

        foreach ($uploads as $file) {
            $body .= '- ' . ($file['name'] ?? '') . "\n";
        }

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $parsed['full_name'] . ' <' . $parsed['email'] . '>',
        );

        $attachment_paths = array();
        foreach ($uploads as $file) {
            if (!empty($file['path']) && is_readable($file['path'])) {
                $attachment_paths[] = $file['path'];
            }
        }

        return wp_mail($to, $subject, $body, $headers, $attachment_paths);
    }

    /**
     * @param array<string, mixed> $parsed
     * @param string $reference
     */
    private static function notify_applicant($parsed, $reference) {
        $subject = __('Your application at PAXdesign was received', 'paxdesign-booking');
        $body = sprintf(
            __("Hello %1\$s,\n\nThank you for your application for the position \"%2\$s\".\n\nYour reference number is: %3\$s\n\nWe will review your documents and get back to you.\n\nBest regards,\nPAXdesign Team", 'paxdesign-booking'),
            $parsed['first_name'],
            $parsed['position_label'],
            $reference
        );
        wp_mail(
            $parsed['email'],
            $subject,
            $body,
            array(
                'Content-Type: text/plain; charset=UTF-8',
                'Reply-To: PAXdesign <' . get_option('paxdesign_booking_notification_email', get_option('admin_email')) . '>',
            )
        );
    }

    private static function generate_reference_id() {
        return 'JOB-' . strtoupper(wp_generate_password(8, false, false));
    }

    private static function check_rate_limit() {
        $key = 'pax_career_rl_' . md5(self::hash_ip(self::client_ip()));
        $count = (int) get_transient($key);
        if ($count >= 3) {
            return false;
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }

    private static function client_ip() {
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }
            $raw = sanitize_text_field(wp_unslash($_SERVER[$key]));
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $raw = trim(explode(',', $raw)[0]);
            }
            if (filter_var($raw, FILTER_VALIDATE_IP)) {
                return $raw;
            }
        }
        return '0.0.0.0';
    }

    private static function hash_ip($ip) {
        return hash('sha256', (string) $ip . (defined('AUTH_SALT') ? AUTH_SALT : 'pax'));
    }

    /**
     * @param int $code
     * @return string
     */
    private static function upload_error_message($code) {
        switch ((int) $code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('One or more files exceed the upload limit.', 'paxdesign-booking');
            case UPLOAD_ERR_PARTIAL:
                return __('A file upload was interrupted. Please try again.', 'paxdesign-booking');
            default:
                return __('File upload failed. Please try again.', 'paxdesign-booking');
        }
    }

    public static function position_label($key) {
        $labels = array(
            'full_stack'      => 'Full Stack Developer',
            'frontend'        => 'Frontend Developer',
            'backend'         => 'Backend Developer',
            'ui_ux'           => 'UI/UX Designer',
            'devops'          => 'DevOps Engineer',
            'project_manager' => 'Project Manager',
        );
        return $labels[$key] ?? $key;
    }

    private static function experience_label($key) {
        $labels = array(
            '0-1'  => '0–1 Jahre',
            '1-3'  => '1–3 Jahre',
            '3-5'  => '3–5 Jahre',
            '5+'   => '5+ Jahre',
        );
        return $labels[$key] ?? $key;
    }

    private static function work_model_label($key) {
        $labels = array(
            'remote'  => 'Remote',
            'hybrid'  => 'Hybrid',
            'onsite'  => 'Vor Ort',
            'flexible'=> 'Flexibel',
        );
        return $labels[$key] ?? $key;
    }

    private static function agile_label($key) {
        $labels = array(
            'experienced' => 'Ja, umfangreiche Erfahrung',
            'basic'       => 'Ja, grundlegende Kenntnisse',
            'willing'     => 'Nein, aber lernbereit',
        );
        return $labels[$key] ?? $key;
    }
}
