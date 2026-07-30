<?php
/**
 * Cybercrime intake reports — storage, uploads, and notifications.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_Intake {

    const TABLE_SUFFIX = 'paxdesign_cybercrime_reports';
    const NONCE_ACTION = 'paxdesign_cybercrime_report';
    const UPLOAD_SUBDIR = 'pax-cybercrime-intake';
    const MAX_FILES = 20;
    const MAX_FILE_BYTES = 26214400; // 25 MB

    /** @var list<string> */
    private static $categories = array(
        'account_takeover',
        'phishing_fraud',
        'identity_theft',
        'malware_ransomware',
        'social_media_recovery',
        'financial_fraud',
        'data_breach',
        'other',
    );

    /** @var list<string> */
    private static $urgency_levels = array('low', 'medium', 'high', 'critical');

    public static function init() {
        add_action('init', array(__CLASS__, 'maybe_create_table'));
        add_action('wp_ajax_paxdesign_cybercrime_report', array(__CLASS__, 'handle_submit'));
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
            customer_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(24) NOT NULL DEFAULT 'submitted',
            reporter_name varchar(190) NOT NULL DEFAULT '',
            reporter_email varchar(190) NOT NULL DEFAULT '',
            reporter_phone varchar(64) NOT NULL DEFAULT '',
            reporter_country varchar(120) NOT NULL DEFAULT '',
            category varchar(64) NOT NULL DEFAULT '',
            urgency varchar(24) NOT NULL DEFAULT '',
            incident_at datetime NULL,
            payload longtext NOT NULL,
            attachments longtext NOT NULL,
            ip_hash varchar(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reference_id (reference_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY customer_user_id (customer_user_id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * @return array<string, mixed>
     */
    public static function public_config() {
        $resume_url = home_url('/cybercrime-support/?pdx_ccs_start=1');
        $login_url  = class_exists('PAXdesign_Auth_Page')
            ? add_query_arg('return_to', rawurlencode($resume_url), PAXdesign_Auth_Page::page_url())
            : wp_login_url($resume_url);

        return array(
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce(self::NONCE_ACTION),
            'maxFiles'      => self::MAX_FILES,
            'maxFileMb'     => (int) floor(self::MAX_FILE_BYTES / 1048576),
            'categories'    => self::$categories,
            'urgencyLevels' => self::$urgency_levels,
            'requireLogin'  => true,
            'isLoggedIn'    => is_user_logged_in(),
            'loginUrl'      => esc_url($login_url),
            'resumeParam'   => 'pdx_ccs_start',
        );
    }

    public static function handle_submit() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('Please sign in to submit a report.', 'paxdesign-booking'),
                'code'    => 'login_required',
            ), 401);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!empty($_POST['website_trap'])) {
            wp_send_json_error(array('message' => __('Request rejected.', 'paxdesign-booking')), 403);
        }

        if (!self::check_rate_limit()) {
            wp_send_json_error(array(
                'message' => __('Too many submissions. Please wait before trying again.', 'paxdesign-booking'),
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
        $user_id = get_current_user_id();
        global $wpdb;

        $payload = array(
            'identity_accuracy'   => !empty($parsed['identity_accuracy']),
            'platforms'           => $parsed['platforms'],
            'description'         => $parsed['description'],
            'financial_loss'      => $parsed['financial_loss'],
            'financial_currency'  => $parsed['financial_currency'],
            'declarations'        => $parsed['declarations'],
            'locale'              => $parsed['locale'],
        );

        $inserted = $wpdb->insert(
            self::table_name(),
            array(
                'reference_id'    => $reference,
                'customer_user_id'=> max(0, (int) $user_id),
                'status'          => 'submitted',
                'reporter_name'   => $parsed['full_name'],
                'reporter_email'  => $parsed['email'],
                'reporter_phone'  => $parsed['phone'],
                'reporter_country'=> $parsed['country'],
                'category'        => $parsed['category'],
                'urgency'         => $parsed['urgency'],
                'incident_at'     => $parsed['incident_at_sql'],
                'payload'         => wp_json_encode($payload),
                'attachments'     => wp_json_encode($uploads),
                'ip_hash'         => self::hash_ip(self::client_ip()),
                'created_at'      => $now,
                'updated_at'      => $now,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            wp_send_json_error(array(
                'message' => __('Could not save your report. Please try again or contact support.', 'paxdesign-booking'),
            ), 500);
        }

        self::notify_admin($reference, $parsed, $uploads);

        wp_send_json_success(array(
            'referenceId' => $reference,
            'message'       => __('Your report has been submitted securely.', 'paxdesign-booking'),
        ));
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>|WP_Error
     */
    private static function parse_submission($post) {
        $full_name = sanitize_text_field($post['full_name'] ?? '');
        $email     = sanitize_email($post['email'] ?? '');
        $phone     = sanitize_text_field($post['phone'] ?? '');
        $country   = sanitize_text_field($post['country'] ?? '');
        $category  = sanitize_key($post['category'] ?? '');
        $urgency   = sanitize_key($post['urgency'] ?? '');
        $platforms = sanitize_textarea_field($post['platforms'] ?? '');
        $description = sanitize_textarea_field($post['description'] ?? '');
        $locale    = sanitize_key($post['locale'] ?? 'ar');
        $financial_loss = isset($post['financial_loss']) ? sanitize_text_field($post['financial_loss']) : '';
        $financial_currency = sanitize_text_field($post['financial_currency'] ?? 'EUR');

        if ($full_name === '' || strlen($full_name) < 2) {
            return new WP_Error('invalid_name', __('Please enter your full legal name.', 'paxdesign-booking'));
        }
        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Please enter a valid email address.', 'paxdesign-booking'));
        }
        if ($phone === '') {
            return new WP_Error('invalid_phone', __('Please enter a phone number.', 'paxdesign-booking'));
        }
        if ($country === '') {
            return new WP_Error('invalid_country', __('Please enter your country.', 'paxdesign-booking'));
        }
        if (!in_array($category, self::$categories, true)) {
            return new WP_Error('invalid_category', __('Please select an incident category.', 'paxdesign-booking'));
        }
        if (!in_array($urgency, self::$urgency_levels, true)) {
            return new WP_Error('invalid_urgency', __('Please select an urgency level.', 'paxdesign-booking'));
        }
        if ($platforms === '') {
            return new WP_Error('invalid_platforms', __('Please list the platforms or services involved.', 'paxdesign-booking'));
        }
        if (strlen($description) < 20) {
            return new WP_Error('invalid_description', __('Please provide a detailed incident description.', 'paxdesign-booking'));
        }

        $identity_accuracy = !empty($post['identity_accuracy']);
        if (!$identity_accuracy) {
            return new WP_Error('identity_accuracy', __('Please confirm that your identity information is accurate.', 'paxdesign-booking'));
        }

        $declarations = array(
            'truthful'     => !empty($post['decl_truthful']),
            'false_reports'=> !empty($post['decl_false_reports']),
            'verification' => !empty($post['decl_verification']),
        );
        if (!$declarations['truthful'] || !$declarations['false_reports'] || !$declarations['verification']) {
            return new WP_Error('declarations', __('Please accept all required declarations before submitting.', 'paxdesign-booking'));
        }

        $incident_at_sql = null;
        $incident_date = sanitize_text_field($post['incident_date'] ?? '');
        $incident_time = sanitize_text_field($post['incident_time'] ?? '');
        if ($incident_date !== '') {
            $combined = trim($incident_date . ' ' . ($incident_time !== '' ? $incident_time : '00:00'));
            $ts = strtotime($combined);
            if ($ts !== false) {
                $incident_at_sql = gmdate('Y-m-d H:i:s', $ts);
            }
        }
        if ($incident_at_sql === null) {
            return new WP_Error('invalid_date', __('Please enter the date of the incident.', 'paxdesign-booking'));
        }

        if (!in_array($locale, array('ar', 'de'), true)) {
            $locale = 'ar';
        }

        return array(
            'full_name'          => $full_name,
            'email'              => $email,
            'phone'              => $phone,
            'country'            => $country,
            'category'           => $category,
            'urgency'            => $urgency,
            'platforms'          => $platforms,
            'description'        => $description,
            'financial_loss'     => $financial_loss,
            'financial_currency' => $financial_currency,
            'identity_accuracy'  => true,
            'declarations'       => $declarations,
            'locale'             => $locale,
            'incident_at_sql'    => $incident_at_sql,
        );
    }

    /**
     * @return array<int, array<string, string>>|WP_Error
     */
    private static function handle_uploads() {
        if (empty($_FILES) || !is_array($_FILES)) {
            return array();
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $allowed = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'pdf'          => 'application/pdf',
            'txt'          => 'text/plain',
            'csv'          => 'text/csv',
            'zip'          => 'application/zip',
            'heic'         => 'image/heic',
            'heif'         => 'image/heif',
        );

        $saved = array();
        $count = 0;

        foreach ($_FILES as $field => $file) {
            if (!is_array($file) || empty($file['name'])) {
                continue;
            }

            $names = $file['name'];
            if (!is_array($names)) {
                $batch = array($file);
            } else {
                $batch = array();
                foreach ($names as $i => $name) {
                    if ($name === '') {
                        continue;
                    }
                    $batch[] = array(
                        'name'     => $name,
                        'type'     => $file['type'][$i] ?? '',
                        'tmp_name' => $file['tmp_name'][$i] ?? '',
                        'error'    => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size'     => $file['size'][$i] ?? 0,
                    );
                }
            }

            foreach ($batch as $single) {
                if ($count >= self::MAX_FILES) {
                    return new WP_Error('too_many_files', __('Too many attachments.', 'paxdesign-booking'));
                }
                if ((int) ($single['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                if ((int) $single['size'] > self::MAX_FILE_BYTES) {
                    return new WP_Error('file_too_large', __('One or more files exceed the size limit.', 'paxdesign-booking'));
                }

                $upload = wp_handle_upload($single, array(
                    'test_form' => false,
                    'mimes'     => $allowed,
                    'unique_filename_callback' => function ($dir, $name, $ext) {
                        return wp_unique_filename($dir, 'ccs-' . wp_generate_password(8, false) . $ext);
                    },
                ));

                if (!empty($upload['error'])) {
                    return new WP_Error('upload_failed', $upload['error']);
                }

                $saved[] = array(
                    'field' => sanitize_key((string) $field),
                    'name'  => basename($upload['file']),
                    'url'   => $upload['url'],
                    'type'  => $upload['type'] ?? '',
                    'size'  => (string) filesize($upload['file']),
                );
                $count++;
            }
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $parsed
     * @param array<int, array<string, string>> $uploads
     */
    private static function notify_admin($reference, $parsed, $uploads) {
        $to = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
        $subject = sprintf('[Cybercrime Report] %s — %s', $reference, $parsed['category']);
        $body = "New cybercrime intake report\n\n"
            . "Reference: {$reference}\n"
            . "Name: {$parsed['full_name']}\n"
            . "Email: {$parsed['email']}\n"
            . "Phone: {$parsed['phone']}\n"
            . "Country: {$parsed['country']}\n"
            . "Category: {$parsed['category']}\n"
            . "Urgency: {$parsed['urgency']}\n"
            . "Incident: {$parsed['incident_at_sql']}\n"
            . "Platforms: {$parsed['platforms']}\n"
            . "Financial loss: {$parsed['financial_loss']} {$parsed['financial_currency']}\n\n"
            . "Description:\n{$parsed['description']}\n\n"
            . 'Attachments: ' . count($uploads) . "\n";

        foreach ($uploads as $file) {
            $body .= '- ' . ($file['name'] ?? '') . ' ' . ($file['url'] ?? '') . "\n";
        }

        wp_mail($to, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
    }

    private static function generate_reference_id() {
        return 'CCS-' . gmdate('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private static function client_ip() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return 'unknown';
    }

    private static function hash_ip($ip) {
        return hash('sha256', $ip . (defined('AUTH_KEY') ? AUTH_KEY : 'pax'));
    }

    private static function check_rate_limit() {
        $key = 'pax_ccs_rl_' . md5(self::hash_ip(self::client_ip()));
        $count = (int) get_transient($key);
        if ($count >= 5) {
            return false;
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * @return array<string, string>
     */
    public static function category_labels() {
        return array(
            'account_takeover'        => __('Account takeover', 'paxdesign-booking'),
            'phishing_fraud'          => __('Phishing / fraud', 'paxdesign-booking'),
            'identity_theft'          => __('Identity theft', 'paxdesign-booking'),
            'malware_ransomware'      => __('Malware / ransomware', 'paxdesign-booking'),
            'social_media_recovery'   => __('Social media recovery', 'paxdesign-booking'),
            'financial_fraud'         => __('Financial fraud', 'paxdesign-booking'),
            'data_breach'             => __('Data breach', 'paxdesign-booking'),
            'other'                   => __('Other cyber incident', 'paxdesign-booking'),
        );
    }

    /**
     * @return string
     */
    public static function category_label($category) {
        $labels = self::category_labels();
        $key = sanitize_key((string) $category);
        return isset($labels[$key]) ? (string) $labels[$key] : $key;
    }

    /**
     * @return string
     */
    public static function status_label($status) {
        $status = sanitize_key((string) $status);
        switch ($status) {
            case 'submitted':
                return __('Submitted — awaiting review', 'paxdesign-booking');
            case 'in_review':
                return __('In review', 'paxdesign-booking');
            case 'needs_info':
                return __('Additional information requested', 'paxdesign-booking');
            case 'resolved':
                return __('Resolved', 'paxdesign-booking');
            case 'closed':
                return __('Closed', 'paxdesign-booking');
            default:
                return ucfirst(str_replace('_', ' ', $status));
        }
    }

    /**
     * @param int $user_id
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public static function list_for_user($user_id, $limit = 5) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();
        $user = get_user_by('id', $user_id);
        $email = ($user instanceof WP_User) ? sanitize_email($user->user_email) : '';

        if ($email !== '') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table
                 WHERE customer_user_id = %d OR (customer_user_id = 0 AND reporter_email = %s)
                 ORDER BY created_at DESC
                 LIMIT %d",
                $user_id,
                $email,
                max(1, min(20, (int) $limit))
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE customer_user_id = %d ORDER BY created_at DESC LIMIT %d",
                $user_id,
                max(1, min(20, (int) $limit))
            ), ARRAY_A);
        }

        if (!is_array($rows)) {
            return array();
        }

        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = array();
            }
            $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
            if (!is_array($attachments)) {
                $attachments = array();
            }
            $out[] = array(
                'reference_id'   => (string) ($row['reference_id'] ?? ''),
                'status'         => (string) ($row['status'] ?? ''),
                'status_label'   => self::status_label((string) ($row['status'] ?? '')),
                'category'       => (string) ($row['category'] ?? ''),
                'category_label' => self::category_label((string) ($row['category'] ?? '')),
                'urgency'        => (string) ($row['urgency'] ?? ''),
                'incident_at'    => (string) ($row['incident_at'] ?? ''),
                'created_at'     => (string) ($row['created_at'] ?? ''),
                'updated_at'     => (string) ($row['updated_at'] ?? ''),
                'description'    => (string) ($payload['description'] ?? ''),
                'platforms'      => (string) ($payload['platforms'] ?? ''),
                'locale'         => (string) ($payload['locale'] ?? ''),
                'attachments'    => count($attachments),
            );
        }

        return $out;
    }

    /**
     * Account-aware AI context for authenticated customers.
     *
     * @param int $user_id
     * @return string
     */
    public static function build_account_context_block($user_id) {
        $reports = self::list_for_user($user_id, 5);
        if (empty($reports)) {
            return '- Cybercrime Support reports: none submitted yet for this account';
        }

        $lines = array('- Cybercrime Support reports (' . count($reports) . ' recent):');
        foreach ($reports as $report) {
            $summary = sprintf(
                '  • %s — %s | status: %s | urgency: %s | submitted: %s',
                (string) ($report['reference_id'] ?? ''),
                (string) ($report['category_label'] ?? ''),
                (string) ($report['status_label'] ?? ''),
                (string) ($report['urgency'] ?? ''),
                (string) ($report['created_at'] ?? '')
            );
            if (!empty($report['incident_at'])) {
                $summary .= ' | incident: ' . (string) $report['incident_at'];
            }
            $lines[] = $summary;
            if (!empty($report['platforms'])) {
                $lines[] = '    platforms: ' . preg_replace('/\s+/', ' ', trim((string) $report['platforms']));
            }
            if (!empty($report['description'])) {
                $desc = trim((string) $report['description']);
                if (strlen($desc) > 220) {
                    $desc = substr($desc, 0, 217) . '...';
                }
                $lines[] = '    reason/summary: ' . $desc;
            }
            if ((int) ($report['attachments'] ?? 0) > 0) {
                $lines[] = '    attachments: ' . (int) $report['attachments'];
            }
            $lines[] = '    latest update: ' . (string) ($report['updated_at'] ?? $report['created_at'] ?? '');
        }

        $lines[] = '- For Cybercrime Support questions, use ONLY these report facts (reference number, category, status, dates, summary).';
        $lines[] = '- If the customer asks for updates and none are listed above, say the report is recorded and the team will contact them when there is news.';

        return implode("\n", $lines);
    }
}
