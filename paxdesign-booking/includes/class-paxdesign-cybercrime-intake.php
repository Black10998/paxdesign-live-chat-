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
    const SCHEMA_VERSION = '3';
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

    /**
     * Ensure table exists and required columns/indexes are present.
     */
    public static function ensure_schema() {
        self::maybe_create_table();

        global $wpdb;
        $table = self::table_name();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            self::maybe_create_table();
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        if (!is_array($columns)) {
            $columns = array();
        }

        if (!in_array('customer_user_id', $columns, true)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN customer_user_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER reference_id");
            $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        }
        if (is_array($columns) && in_array('customer_user_id', $columns, true)) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM `$table` WHERE Key_name = 'customer_user_id'", ARRAY_A);
            if (empty($indexes)) {
                $wpdb->query("ALTER TABLE `$table` ADD KEY customer_user_id (customer_user_id)");
            }
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        if (!is_array($columns)) {
            $columns = array();
        }
        if (!in_array('needs_human_review', $columns, true)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN needs_human_review tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER status");
        }
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        if (is_array($columns) && !in_array('last_staff_reminder_at', $columns, true)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN last_staff_reminder_at datetime NULL AFTER updated_at");
        }
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        if (is_array($columns) && in_array('needs_human_review', $columns, true)) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM `$table` WHERE Key_name = 'needs_human_review'", ARRAY_A);
            if (empty($indexes)) {
                $wpdb->query("ALTER TABLE `$table` ADD KEY needs_human_review (needs_human_review)");
            }
        }

        update_option('paxdesign_cybercrime_schema_version', self::SCHEMA_VERSION, false);
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
            needs_human_review tinyint(1) unsigned NOT NULL DEFAULT 0,
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
            last_staff_reminder_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY reference_id (reference_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY customer_user_id (customer_user_id),
            KEY needs_human_review (needs_human_review)
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
            'activeReport'  => self::safe_active_report_for_current_user(),
            'checksDisclaimer' => __('Automated preliminary quality checks are not legal verification. Uncertain files are sent to an administrator.', 'paxdesign-booking'),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function safe_active_report_for_current_user() {
        if (!is_user_logged_in() || !class_exists('PAXdesign_Cybercrime_Tickets')) {
            return null;
        }
        try {
            return PAXdesign_Cybercrime_Tickets::get_active_report_for_user(get_current_user_id());
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PAXdesign Cybercrime] activeReport config failed: ' . $e->getMessage());
            }
            return null;
        }
    }

    public static function handle_submit() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('Please sign in to submit a report.', 'paxdesign-booking'),
                'code'    => 'login_required',
            ), 401);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $result = self::create_report($_POST, $_FILES, get_current_user_id());
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $payload = is_array($data) ? $data : array();
            $payload['message'] = $result->get_error_message();
            $payload['code'] = $result->get_error_code();
            $status = isset($payload['status']) ? (int) $payload['status'] : 400;
            unset($payload['status']);
            wp_send_json_error($payload, $status);
        }

        wp_send_json_success($result);
    }

    /**
     * Shared intake used by the website AJAX form and the customer iOS REST API.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param int                  $user_id
     * @return array<string, mixed>|WP_Error
     */
    public static function create_report($post, $files, $user_id) {
        $post = is_array($post) ? $post : array();
        $files = is_array($files) ? $files : array();
        $user_id = absint($user_id);

        if ($user_id <= 0) {
            return new WP_Error(
                'login_required',
                __('Please sign in to submit a report.', 'paxdesign-booking'),
                array('status' => 401)
            );
        }

        if (!empty($post['website_trap'])) {
            return new WP_Error('forbidden', __('Request rejected.', 'paxdesign-booking'), array('status' => 403));
        }

        if (!self::check_rate_limit()) {
            return new WP_Error(
                'rate_limited',
                __('Too many submissions. Please wait before trying again.', 'paxdesign-booking'),
                array('status' => 429, 'retry_after' => 3600)
            );
        }

        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            PAXdesign_Cybercrime_Tickets::ensure_schema();
            $active = PAXdesign_Cybercrime_Tickets::get_active_report_for_user($user_id);
            if ($active) {
                $active_row = PAXdesign_Cybercrime_Tickets::get_report_row((string) ($active['reference_id'] ?? ''));
                $active_status = is_array($active_row) ? sanitize_key((string) ($active_row['status'] ?? '')) : '';
                if ($active_status === 'draft' && is_array($active_row)) {
                    return self::complete_draft_report($active_row, $post, $files, $user_id);
                }
                return new WP_Error(
                    'active_report_exists',
                    __('You already have an open report. View your existing report to add updates or messages.', 'paxdesign-booking'),
                    array('status' => 409, 'activeReport' => $active)
                );
            }
        }

        $parsed = self::parse_submission($post);
        if (is_wp_error($parsed)) {
            return new WP_Error(
                $parsed->get_error_code(),
                $parsed->get_error_message(),
                array('status' => 400)
            );
        }

        if (!self::has_uploaded_file($files, 'identity_document')) {
            return new WP_Error(
                'identity_document_required',
                __('Please upload an identity document before submitting.', 'paxdesign-booking'),
                array('status' => 400)
            );
        }

        $uploads = self::handle_uploads($files);
        if (is_wp_error($uploads)) {
            return new WP_Error(
                $uploads->get_error_code(),
                $uploads->get_error_message(),
                array('status' => 400)
            );
        }

        $check_context = array(
            'reporter_name' => (string) ($parsed['full_name'] ?? ''),
            'email'         => (string) ($parsed['email'] ?? ''),
            'category'      => (string) ($parsed['category'] ?? ''),
        );
        $document_checks = class_exists('PAXdesign_Cybercrime_Document_Checks')
            ? PAXdesign_Cybercrime_Document_Checks::evaluate_uploads($uploads, $check_context)
            : array();

        if (class_exists('PAXdesign_Cybercrime_Document_Checks')
            && PAXdesign_Cybercrime_Document_Checks::has_blocking_identity_failure($document_checks)
        ) {
            self::delete_stored_uploads($uploads);
            $corrections = array_values((array) ($document_checks['customer_corrections'] ?? array()));
            $message = !empty($corrections)
                ? implode(' ', $corrections)
                : __('The identity document did not pass preliminary quality checks. Please upload a readable, complete document.', 'paxdesign-booking');
            return new WP_Error(
                'document_check_failed',
                $message,
                array(
                    'status'           => 400,
                    'corrections'      => $corrections,
                    'document_checks'  => class_exists('PAXdesign_Cybercrime_Document_Checks')
                        ? PAXdesign_Cybercrime_Document_Checks::customer_view($document_checks)
                        : array(),
                )
            );
        }

        self::ensure_schema();

        $reference = self::generate_reference_id();
        $now = current_time('mysql', true);
        global $wpdb;
        $chat_session_id = sanitize_text_field(wp_unslash($post['chat_session_id'] ?? ''));

        $payload = array(
            'identity_accuracy'   => !empty($parsed['identity_accuracy']),
            'platforms'           => $parsed['platforms'],
            'description'         => $parsed['description'],
            'financial_loss'      => $parsed['financial_loss'],
            'financial_currency'  => $parsed['financial_currency'],
            'declarations'        => $parsed['declarations'],
            'locale'              => $parsed['locale'],
            'source'              => sanitize_key((string) ($post['source'] ?? 'web')),
            'incident_date'       => (string) ($parsed['incident_date'] ?? ''),
            'incident_time'       => (string) ($parsed['incident_time'] ?? ''),
            'document_checks'     => $document_checks,
            'guided_case'         => array(
                'needs_human_review' => !empty($document_checks['needs_human_review']),
                'next_action'        => (string) ($document_checks['next_action'] ?? ''),
                'correction_rounds'  => array(),
            ),
        );

        $needs_human = !empty($document_checks['needs_human_review']) ? 1 : 0;

        $insert_row = array(
            'reference_id'         => $reference,
            'customer_user_id'     => max(0, (int) $user_id),
            'status'               => 'submitted',
            'needs_human_review'   => $needs_human,
            'reporter_name'        => $parsed['full_name'],
            'reporter_email'       => $parsed['email'],
            'reporter_phone'       => $parsed['phone'],
            'reporter_country'     => $parsed['country'],
            'category'             => $parsed['category'],
            'urgency'              => $parsed['urgency'],
            'incident_at'          => $parsed['incident_at_sql'],
            'payload'              => wp_json_encode($payload),
            'attachments'          => wp_json_encode(self::public_upload_records($uploads)),
            'ip_hash'              => self::hash_ip(self::client_ip()),
            'created_at'           => $now,
            'updated_at'           => $now,
        );

        $inserted = $wpdb->insert(
            self::table_name(),
            $insert_row,
            array('%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            $db_error = (string) $wpdb->last_error;
            if ($db_error !== '' && strpos($db_error, 'needs_human_review') !== false) {
                unset($insert_row['needs_human_review']);
                $inserted = $wpdb->insert(
                    self::table_name(),
                    $insert_row,
                    array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                $db_error = (string) $wpdb->last_error;
            }
            if ($db_error !== '') {
                error_log('[PAXdesign Cybercrime] Insert failed: ' . $db_error);
            }

            $error_data = array('status' => 500);
            if ($db_error !== '' && (defined('WP_DEBUG') && WP_DEBUG || current_user_can('manage_options'))) {
                $error_data['detail'] = $db_error;
            }

            return new WP_Error(
                'db_insert_failed',
                __('Could not save your report. Please try again or contact support.', 'paxdesign-booking'),
                $error_data
            );
        }

        self::notify_admin($reference, $parsed, $uploads, $document_checks);
        self::notify_customer_submitted($user_id, $reference, $parsed);

        if ($needs_human && class_exists('PAXdesign_Cybercrime_Admin_Reminders')) {
            do_action('paxdesign_cybercrime_needs_human_review', $reference, $document_checks);
        }

        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            PAXdesign_Cybercrime_Tickets::record_submission($reference, $user_id, $parsed, $chat_session_id);
        }

        $report = null;
        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            $report = PAXdesign_Cybercrime_Tickets::get_report_for_user($reference, $user_id);
        }

        return array(
            'referenceId' => $reference,
            'message'     => __('Your report has been submitted securely.', 'paxdesign-booking'),
            'report'      => $report,
        );
    }

    /**
     * REST submit from the authenticated customer iOS app (same table as the website).
     *
     * @return WP_REST_Response|WP_Error
     */
    public static function rest_submit_report(WP_REST_Request $request) {
        $post = $request->get_params();
        if (!is_array($post)) {
            $post = array();
        }
        if (!empty($_POST) && is_array($_POST)) {
            foreach ($_POST as $key => $value) {
                if (!array_key_exists($key, $post) || $post[$key] === '' || $post[$key] === null) {
                    $post[$key] = wp_unslash($value);
                }
            }
        }
        $files = $request->get_file_params();
        if ((!is_array($files) || empty($files)) && !empty($_FILES) && is_array($_FILES)) {
            $files = $_FILES;
        }
        $result = self::create_report(
            $post,
            is_array($files) ? $files : array(),
            PAXdesign_Customer_Auth::current_user_id()
        );
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    /**
     * @param array<string, mixed> $files
     * @param string               $field
     */
    private static function has_uploaded_file($files, $field) {
        if (empty($files[$field]) || !is_array($files[$field])) {
            return false;
        }
        $file = $files[$field];
        $name = $file['name'] ?? '';
        if (is_array($name)) {
            foreach ($name as $item) {
                if (is_string($item) && $item !== '') {
                    return true;
                }
            }
            return false;
        }
        if (!is_string($name) || $name === '') {
            return false;
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        return $error !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>|WP_Error
     */
    private static function parse_submission($post) {
        $full_name = sanitize_text_field($post['full_name'] ?? '');
        $email     = sanitize_email($post['email'] ?? '');
        $phone     = sanitize_text_field($post['phone'] ?? '');
        $phone_code = sanitize_text_field($post['phone_country_code'] ?? '');
        $phone_local = sanitize_text_field($post['phone_local'] ?? '');
        $country_code = strtoupper(sanitize_text_field($post['country'] ?? ''));
        $country   = self::resolve_country_label($country_code, sanitize_key($post['locale'] ?? 'en'));
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
            $local_digits = preg_replace('/[^\d]/', '', $phone_local);
            if ($phone_code !== '' && $local_digits !== '') {
                $phone = trim($phone_code . ' ' . $local_digits);
            } elseif ($local_digits !== '') {
                $phone = $local_digits;
            }
        }
        if ($phone === '' || strlen(preg_replace('/[^\d]/', '', $phone)) < 6) {
            return new WP_Error('invalid_phone', __('Please enter a valid phone number.', 'paxdesign-booking'));
        }
        if ($country === '') {
            return new WP_Error('invalid_country', __('Please select your country.', 'paxdesign-booking'));
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

        if (!in_array($locale, array('ar', 'de', 'en'), true)) {
            $locale = 'ar';
        }

        return array(
            'full_name'          => $full_name,
            'email'              => $email,
            'phone'              => $phone,
            'country'            => $country,
            'country_code'       => $country_code,
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
            'incident_date'      => $incident_date,
            'incident_time'      => $incident_time,
        );
    }

    /**
     * Resolve ISO country code to a readable label for storage.
     *
     * @param string $code   ISO 3166-1 alpha-2.
     * @param string $locale Portal locale.
     */
    private static function resolve_country_label($code, $locale = 'en') {
        $code = strtoupper(sanitize_text_field((string) $code));
        if ($code === '' || !preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }
        if (function_exists('pax_ccs_country_by_code') && function_exists('pax_ccs_pick_lang')) {
            $country = pax_ccs_country_by_code($code);
            if (is_array($country) && !empty($country['name'])) {
                $lang = in_array($locale, array('ar', 'de', 'en'), true) ? $locale : 'en';
                $label = pax_ccs_pick_lang($country['name'], $lang);
                if ($label !== '') {
                    return $label;
                }
            }
        }
        return $code;
    }

    /**
     * @return array<int, array<string, string>>|WP_Error
     */
    private static function handle_uploads($files = null) {
        if ($files === null) {
            $files = $_FILES;
        }
        if (empty($files) || !is_array($files)) {
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
            'doc'          => 'application/msword',
            'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'heic'         => 'image/heic',
            'heif'         => 'image/heif',
        );

        $saved = array();
        $count = 0;

        add_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));

        try {
            foreach ($files as $field => $file) {
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
                    $upload_error = (int) ($single['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($upload_error === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($upload_error !== UPLOAD_ERR_OK) {
                        return new WP_Error(
                            'upload_failed',
                            self::upload_error_message($upload_error)
                        );
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
                        error_log('[PAXdesign Cybercrime] Upload failed: ' . $upload['error']);
                        return new WP_Error('upload_failed', $upload['error']);
                    }

                    $saved[] = array(
                        'field'         => sanitize_key((string) $field),
                        'name'          => basename($upload['file']),
                        'original_name' => sanitize_file_name((string) ($single['name'] ?? '')),
                        'url'           => $upload['url'],
                        'type'          => $upload['type'] ?? '',
                        'size'          => (string) filesize($upload['file']),
                        'path'          => $upload['file'],
                        'sha256'        => is_readable($upload['file']) ? hash_file('sha256', $upload['file']) : '',
                    );
                    $count++;
                }
            }
        } finally {
            remove_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
        }

        return $saved;
    }

    /**
     * Store new uploads for an existing case (same reference).
     *
     * @param array<string, mixed> $files
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public static function save_uploaded_files($files) {
        return self::handle_uploads($files);
    }

    /**
     * Records safe to persist in the attachments JSON (no server path).
     *
     * @param array<int, array<string, mixed>> $uploads
     * @return array<int, array<string, mixed>>
     */
    public static function public_upload_records($uploads) {
        $out = array();
        foreach ((array) $uploads as $file) {
            if (!is_array($file)) {
                continue;
            }
            $out[] = array(
                'field'         => sanitize_key((string) ($file['field'] ?? '')),
                'name'          => (string) ($file['name'] ?? ''),
                'original_name' => (string) ($file['original_name'] ?? ''),
                'url'           => (string) ($file['url'] ?? ''),
                'type'          => (string) ($file['type'] ?? ''),
                'size'          => (string) ($file['size'] ?? ''),
                'sha256'        => (string) ($file['sha256'] ?? ''),
            );
        }
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $uploads
     */
    public static function delete_stored_uploads($uploads) {
        foreach ((array) $uploads as $file) {
            if (!is_array($file)) {
                continue;
            }
            $path = (string) ($file['path'] ?? '');
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param string $reference
     * @return string
     */
    public static function admin_case_url($reference) {
        if (class_exists('PAXdesign_Cybercrime_Admin_Reminders')) {
            return PAXdesign_Cybercrime_Admin_Reminders::admin_case_url($reference);
        }
        return admin_url('admin.php?page=paxdesign-customer-portal&tab=cybercrime&reference=' . rawurlencode((string) $reference));
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
        if (!wp_mkdir_p($dirs['path'])) {
            error_log('[PAXdesign Cybercrime] Could not create upload directory: ' . $dirs['path']);
        } else {
            self::ensure_upload_dir_hardened($dirs['path']);
        }
        return $dirs;
    }

    /**
     * Deny direct web access to sensitive intake uploads (Apache/LiteSpeed).
     *
     * @param string $path
     */
    private static function ensure_upload_dir_hardened($path) {
        if (!is_string($path) || $path === '' || !is_dir($path)) {
            return;
        }

        $index = trailingslashit($path) . 'index.php';
        if (!is_file($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        $htaccess = trailingslashit($path) . '.htaccess';
        if (is_file($htaccess)) {
            return;
        }

        $rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
        file_put_contents($htaccess, $rules);
    }

    /**
     * @param int $code PHP upload error code.
     * @return string
     */
    private static function upload_error_message($code) {
        switch ((int) $code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('One or more files exceed the server upload limit.', 'paxdesign-booking');
            case UPLOAD_ERR_PARTIAL:
                return __('A file upload was interrupted. Please try again.', 'paxdesign-booking');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return __('The server could not store uploaded files. Please contact support.', 'paxdesign-booking');
            default:
                return __('File upload failed. Please try again.', 'paxdesign-booking');
        }
    }

    /**
     * @param array<string, mixed> $parsed
     * @param array<int, array<string, mixed>> $uploads
     * @param array<string, mixed> $document_checks
     */
    private static function notify_admin($reference, $parsed, $uploads, $document_checks = array()) {
        $to = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
        $admin_url = self::admin_case_url($reference);
        $needs_human = !empty($document_checks['needs_human_review']);
        $subject = $needs_human
            ? 'A Cybercrime Support request requires your review.'
            : sprintf('[Cybercrime Report] %s — %s', $reference, $parsed['category']);
        $body = ($needs_human ? "A Cybercrime Support request requires your review.\n\n" : '')
            . "New cybercrime intake report\n\n"
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
            $body .= '- ' . ($file['original_name'] ?? $file['name'] ?? '') . "\n";
        }

        if ($needs_human) {
            $body .= "\nAutomated preliminary checks flagged this case for administrator review (not legal verification).\n";
            $reasons = (array) ($document_checks['human_review_reasons'] ?? array());
            if (!empty($reasons)) {
                $body .= 'Flags: ' . implode(', ', array_slice($reasons, 0, 12)) . "\n";
            }
        }

        $body .= "\nOpen the exact case:\n{$admin_url}\n";

        wp_mail($to, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
    }

    /**
     * @param int                  $user_id
     * @param string               $reference
     * @param array<string, mixed> $parsed
     */
    private static function notify_customer_submitted($user_id, $reference, $parsed) {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !class_exists('PAXdesign_Customer_Notifications')) {
            return;
        }

        $category_label = self::category_label((string) ($parsed['category'] ?? ''));
        PAXdesign_Customer_Notifications::notify_user(
            $user_id,
            'security',
            __('Cybercrime report received', 'paxdesign-booking'),
            sprintf(
                /* translators: 1: reference id, 2: category label */
                __('Reference %1$s — %2$s. Your report is recorded and awaiting review.', 'paxdesign-booking'),
                $reference,
                $category_label
            ),
            'cybercrime',
            $reference,
            '/cybercrime/' . $reference
        );
    }

    public static function generate_reference_id() {
        return 'CCS-' . gmdate('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    /**
     * @return list<string>
     */
    public static function category_keys() {
        return self::$categories;
    }

    /**
     * @return list<string>
     */
    public static function urgency_keys() {
        return self::$urgency_levels;
    }

    /**
     * Open one draft CCS case for a logged-in customer (chat + page share this row).
     *
     * @param int    $user_id
     * @param string $session_id
     * @param bool   $force_new When true, never reuse an existing CCS reference.
     * @return array<string, mixed>|WP_Error
     */
    public static function create_draft_for_user($user_id, $session_id = '', $force_new = false) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error(
                'login_required',
                __('Please sign in to start a Cybercrime Support case.', 'paxdesign-booking'),
                array('status' => 401)
            );
        }

        $session_id = sanitize_text_field((string) $session_id);
        $force_new = (bool) $force_new;
        $replaced_reference = '';

        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            PAXdesign_Cybercrime_Tickets::ensure_schema();
            if ($force_new) {
                PAXdesign_Cybercrime_Tickets::detach_chat_session($session_id);
                $replaced_reference = self::supersede_open_draft_for_user($user_id);
            } else {
                $active = PAXdesign_Cybercrime_Tickets::get_active_report_for_user($user_id);
                if (is_array($active) && !empty($active['reference_id'])) {
                    $row = PAXdesign_Cybercrime_Tickets::get_report_row((string) $active['reference_id']);
                    if (is_array($row)) {
                        return $row;
                    }
                }
            }
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return new WP_Error('invalid_user', __('Account not found.', 'paxdesign-booking'), array('status' => 403));
        }

        self::ensure_schema();
        $reference = self::generate_reference_id();
        $now = current_time('mysql', true);
        $session_id = sanitize_text_field((string) $session_id);
        $payload = array(
            'platforms'          => '',
            'description'        => '',
            'financial_loss'     => '',
            'financial_currency' => 'EUR',
            'locale'             => 'ar',
            'source'             => 'ai_chat',
            'fresh_start'        => $force_new,
            'replaces_reference' => $replaced_reference,
            'incident_date'      => '',
            'incident_time'      => '',
            'document_checks'    => array(),
            'guided_case'        => array(
                'opened_via'  => 'ai_chat',
                'is_draft'    => true,
                'next_action' => __('Continue in chat or on this page. Facts you share are saved to this same case.', 'paxdesign-booking'),
            ),
        );

        $display_name = trim((string) $user->display_name);
        if ($display_name === '') {
            $display_name = trim((string) $user->user_login);
        }

        $insert_row = array(
            'reference_id'     => $reference,
            'customer_user_id' => $user_id,
            'status'           => 'draft',
            'reporter_name'    => $display_name,
            'reporter_email'   => sanitize_email($user->user_email),
            'reporter_phone'   => '',
            'reporter_country' => '',
            'category'         => '',
            'urgency'          => '',
            'payload'          => wp_json_encode($payload),
            'attachments'      => wp_json_encode(array()),
            'ip_hash'          => self::hash_ip(self::client_ip()),
            'created_at'       => $now,
            'updated_at'       => $now,
        );

        global $wpdb;
        $inserted = $wpdb->insert(
            self::table_name(),
            $insert_row,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        if (!$inserted) {
            return new WP_Error(
                'db_insert_failed',
                __('Could not open your Cybercrime Support case. Please try again.', 'paxdesign-booking'),
                array('status' => 500)
            );
        }

        if ($session_id !== '' && class_exists('PAXdesign_Cybercrime_Tickets')) {
            $wpdb->update(
                self::table_name(),
                array('chat_session_id' => $session_id),
                array('reference_id' => $reference),
                array('%s'),
                array('%s')
            );
        }

        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            PAXdesign_Cybercrime_Tickets::add_message(
                $reference,
                'system',
                sprintf(
                    /* translators: %s: CCS reference */
                    __('Case %s opened. Information from AI chat and this page is saved here.', 'paxdesign-booking'),
                    $reference
                ),
                'portal',
                $user_id,
                array(
                    'event'               => 'draft_opened',
                    'visible_to_customer' => true,
                    'source'              => 'ai_chat',
                )
            );
        }

        $row = class_exists('PAXdesign_Cybercrime_Tickets')
            ? PAXdesign_Cybercrime_Tickets::get_report_row($reference)
            : null;
        return is_array($row) ? $row : $insert_row;
    }

    /**
     * Close a leftover draft so an explicit new-case request cannot reuse it.
     * Does not close the live chat session and does not notify the customer.
     *
     * @param int $user_id
     * @return string Previous draft reference, or empty.
     */
    private static function supersede_open_draft_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !class_exists('PAXdesign_Cybercrime_Tickets')) {
            return '';
        }
        $active = PAXdesign_Cybercrime_Tickets::get_active_report_for_user($user_id);
        $reference = is_array($active) ? sanitize_text_field((string) ($active['reference_id'] ?? '')) : '';
        if ($reference === '') {
            return '';
        }
        $row = PAXdesign_Cybercrime_Tickets::get_report_row($reference);
        if (!is_array($row) || sanitize_key((string) ($row['status'] ?? '')) !== 'draft') {
            return '';
        }

        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $payload['superseded'] = true;
        $payload['superseded_at'] = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
        $encoded = function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload);

        global $wpdb;
        $wpdb->update(
            self::table_name(),
            array(
                'status'           => 'closed',
                'chat_session_id'  => '',
                'payload'          => is_string($encoded) ? $encoded : '',
                'updated_at'       => $payload['superseded_at'],
            ),
            array('reference_id' => $reference),
            array('%s', '%s', '%s', '%s'),
            array('%s')
        );

        PAXdesign_Cybercrime_Tickets::add_message(
            $reference,
            'system',
            sprintf(
                /* translators: %s: CCS reference */
                __('Draft %s was closed because the customer started a new report.', 'paxdesign-booking'),
                $reference
            ),
            'portal',
            $user_id,
            array(
                'event'                => 'draft_superseded',
                'visible_to_customer'  => false,
                'source'               => 'ai_chat',
            )
        );

        return $reference;
    }

    /**
     * Complete an AI/chat draft using the existing intake form on the same reference.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param int                  $user_id
     * @return array<string, mixed>|WP_Error
     */
    public static function complete_draft_report($row, $post, $files, $user_id) {
        $user_id = absint($user_id);
        $reference = sanitize_text_field((string) ($row['reference_id'] ?? ''));
        if ($reference === '' || $user_id <= 0) {
            return new WP_Error('invalid', __('Could not update this report.', 'paxdesign-booking'), array('status' => 400));
        }
        if (class_exists('PAXdesign_Cybercrime_Tickets') && !PAXdesign_Cybercrime_Tickets::user_can_view_report($row, $user_id)) {
            return new WP_Error('forbidden', __('You cannot update this report.', 'paxdesign-booking'), array('status' => 403));
        }

        $parsed = self::parse_submission($post);
        if (is_wp_error($parsed)) {
            return new WP_Error($parsed->get_error_code(), $parsed->get_error_message(), array('status' => 400));
        }

        if (!self::has_uploaded_file($files, 'identity_document')) {
            $existing = json_decode((string) ($row['attachments'] ?? ''), true);
            $has_id = false;
            if (is_array($existing)) {
                foreach ($existing as $file) {
                    if (is_array($file) && sanitize_key((string) ($file['field'] ?? '')) === 'identity_document') {
                        $has_id = true;
                        break;
                    }
                }
            }
            if (!$has_id) {
                return new WP_Error(
                    'identity_document_required',
                    __('Please upload an identity document before submitting.', 'paxdesign-booking'),
                    array('status' => 400)
                );
            }
        }

        $uploads = array();
        if (self::has_uploaded_file($files, 'identity_document') || self::has_any_evidence_file($files)) {
            $uploads = self::handle_uploads($files);
            if (is_wp_error($uploads)) {
                return new WP_Error($uploads->get_error_code(), $uploads->get_error_message(), array('status' => 400));
            }
        }

        $check_context = array(
            'reporter_name' => (string) ($parsed['full_name'] ?? ''),
            'email'         => (string) ($parsed['email'] ?? ''),
            'category'      => (string) ($parsed['category'] ?? ''),
        );
        $document_checks = class_exists('PAXdesign_Cybercrime_Document_Checks') && !empty($uploads)
            ? PAXdesign_Cybercrime_Document_Checks::evaluate_uploads($uploads, $check_context)
            : (json_decode((string) ($row['payload'] ?? ''), true)['document_checks'] ?? array());
        if (!is_array($document_checks)) {
            $document_checks = array();
        }

        if (class_exists('PAXdesign_Cybercrime_Document_Checks')
            && !empty($uploads)
            && PAXdesign_Cybercrime_Document_Checks::has_blocking_identity_failure($document_checks)
        ) {
            self::delete_stored_uploads($uploads);
            $corrections = array_values((array) ($document_checks['customer_corrections'] ?? array()));
            $message = !empty($corrections)
                ? implode(' ', $corrections)
                : __('The identity document did not pass preliminary quality checks. Please upload a readable, complete document.', 'paxdesign-booking');
            return new WP_Error('document_check_failed', $message, array('status' => 400, 'corrections' => $corrections));
        }

        $existing_payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($existing_payload)) {
            $existing_payload = array();
        }
        $existing_attachments = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($existing_attachments)) {
            $existing_attachments = array();
        }
        $new_records = !empty($uploads) ? self::public_upload_records($uploads) : array();
        $attachments = array_merge($existing_attachments, $new_records);

        $payload = array_merge($existing_payload, array(
            'identity_accuracy'  => !empty($parsed['identity_accuracy']),
            'platforms'          => $parsed['platforms'],
            'description'        => $parsed['description'],
            'financial_loss'     => $parsed['financial_loss'],
            'financial_currency' => $parsed['financial_currency'],
            'declarations'       => $parsed['declarations'],
            'locale'             => $parsed['locale'],
            'source'             => sanitize_key((string) ($post['source'] ?? ($existing_payload['source'] ?? 'web'))),
            'incident_date'      => (string) ($parsed['incident_date'] ?? ''),
            'incident_time'      => (string) ($parsed['incident_time'] ?? ''),
            'document_checks'    => $document_checks,
        ));
        $guided = is_array($payload['guided_case'] ?? null) ? $payload['guided_case'] : array();
        $guided['is_draft'] = false;
        $guided['submitted_via'] = 'form';
        $payload['guided_case'] = $guided;

        $needs_human = !empty($document_checks['needs_human_review']) ? 1 : 0;
        $now = current_time('mysql', true);
        global $wpdb;
        $wpdb->update(
            self::table_name(),
            array(
                'status'             => 'submitted',
                'needs_human_review' => $needs_human,
                'reporter_name'      => $parsed['full_name'],
                'reporter_email'     => $parsed['email'],
                'reporter_phone'     => $parsed['phone'],
                'reporter_country'   => $parsed['country'],
                'category'           => $parsed['category'],
                'urgency'            => $parsed['urgency'],
                'incident_at'        => $parsed['incident_at_sql'],
                'payload'            => wp_json_encode($payload),
                'attachments'        => wp_json_encode($attachments),
                'updated_at'         => $now,
            ),
            array('reference_id' => $reference),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%s')
        );

        self::notify_admin($reference, $parsed, array_merge($existing_attachments, $uploads), $document_checks);
        self::notify_customer_submitted($user_id, $reference, $parsed);
        if ($needs_human && class_exists('PAXdesign_Cybercrime_Admin_Reminders')) {
            do_action('paxdesign_cybercrime_needs_human_review', $reference, $document_checks);
        }
        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            $chat_session_id = sanitize_text_field(wp_unslash($post['chat_session_id'] ?? ($row['chat_session_id'] ?? '')));
            PAXdesign_Cybercrime_Tickets::record_submission($reference, $user_id, $parsed, $chat_session_id);
        }

        $report = class_exists('PAXdesign_Cybercrime_Tickets')
            ? PAXdesign_Cybercrime_Tickets::get_report_for_user($reference, $user_id)
            : null;

        return array(
            'referenceId' => $reference,
            'message'     => __('Your report has been submitted securely.', 'paxdesign-booking'),
            'report'      => $report,
        );
    }

    /**
     * @param array<string, mixed> $files
     * @return bool
     */
    private static function has_any_evidence_file($files) {
        foreach (array('evidence_screenshots', 'evidence_documents', 'evidence_chats', 'evidence_other') as $field) {
            if (self::has_uploaded_file($files, $field)) {
                return true;
            }
        }
        return false;
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
        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            return PAXdesign_Cybercrime_Tickets::status_label($status);
        }
        $status = sanitize_key((string) $status);
        switch ($status) {
            case 'submitted':
                return __('New', 'paxdesign-booking');
            case 'in_review':
                return __('In Review', 'paxdesign-booking');
            case 'needs_info':
            case 'waiting_for_customer':
                return __('Waiting for Customer', 'paxdesign-booking');
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
            $attachment_names = array();
            foreach ($attachments as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }
                $name = sanitize_file_name((string) ($attachment['name'] ?? ''));
                if ($name !== '') {
                    $attachment_names[] = $name;
                }
            }

            $checks = is_array($payload['document_checks'] ?? null) ? $payload['document_checks'] : array();
            $guided = is_array($payload['guided_case'] ?? null) ? $payload['guided_case'] : array();
            $checks_bits = array();
            if (!empty($checks['files']) && is_array($checks['files'])) {
                foreach ($checks['files'] as $check_file) {
                    if (!is_array($check_file)) {
                        continue;
                    }
                    $checks_bits[] = (string) ($check_file['filename'] ?? 'file') . '=' . (string) ($check_file['customer_status'] ?? $check_file['status'] ?? '');
                }
            }

            $out[] = array(
                'reference_id'       => (string) ($row['reference_id'] ?? ''),
                'status'             => (string) ($row['status'] ?? ''),
                'status_label'       => self::status_label((string) ($row['status'] ?? '')),
                'category'           => (string) ($row['category'] ?? ''),
                'category_label'     => self::category_label((string) ($row['category'] ?? '')),
                'urgency'            => (string) ($row['urgency'] ?? ''),
                'incident_at'        => (string) ($row['incident_at'] ?? ''),
                'created_at'         => (string) ($row['created_at'] ?? ''),
                'updated_at'         => (string) ($row['updated_at'] ?? ''),
                'description'        => (string) ($payload['description'] ?? ''),
                'platforms'          => (string) ($payload['platforms'] ?? ''),
                'locale'             => (string) ($payload['locale'] ?? ''),
                'financial_loss'     => (string) ($payload['financial_loss'] ?? ''),
                'financial_currency' => (string) ($payload['financial_currency'] ?? ''),
                'attachments'        => count($attachments),
                'attachment_names'   => $attachment_names,
                'needs_human_review' => !empty($row['needs_human_review']) || !empty($checks['needs_human_review']),
                'next_action'        => (string) ($guided['next_action'] ?? $checks['next_action'] ?? ''),
                'checks_summary'     => implode('; ', array_slice($checks_bits, 0, 12)),
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
    /**
     * @param int    $user_id
     * @param string $reference_id
     * @return array<int, array<string, string>>
     */
    private static function list_report_updates($user_id, $reference_id) {
        $user_id = absint($user_id);
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($user_id <= 0 || $reference_id === '' || !class_exists('PAXdesign_Customer_DB')) {
            return array();
        }

        global $wpdb;
        $table = PAXdesign_Customer_DB::table('notifications');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT title, body, created_at FROM $table
             WHERE user_id = %d AND entity_type = %s AND entity_id = %s
             ORDER BY created_at DESC
             LIMIT 5",
            $user_id,
            'cybercrime',
            $reference_id
        ), ARRAY_A);

        if (!is_array($rows)) {
            return array();
        }

        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = array(
                'title'      => sanitize_text_field((string) ($row['title'] ?? '')),
                'body'       => sanitize_textarea_field((string) ($row['body'] ?? '')),
                'created_at' => (string) ($row['created_at'] ?? ''),
            );
        }

        return $out;
    }

    /**
     * Account-aware AI context for authenticated customers.
     *
     * @param int    $user_id
     * @param string $focus_reference Optional report reference the customer is asking about now.
     * @return string
     */
    public static function build_account_context_block($user_id, $focus_reference = '') {
        $reports = self::list_for_user($user_id, 5);
        if (empty($reports)) {
            return '- Cybercrime Support reports: none submitted yet for this account';
        }

        $focus_reference = sanitize_text_field((string) $focus_reference);
        if ($focus_reference !== '') {
            usort($reports, function ($a, $b) use ($focus_reference) {
                $a_match = (($a['reference_id'] ?? '') === $focus_reference) ? 0 : 1;
                $b_match = (($b['reference_id'] ?? '') === $focus_reference) ? 0 : 1;
                return $a_match <=> $b_match;
            });
        }

        $lines = array('- Cybercrime Support reports (' . count($reports) . ' recent):');
        if ($focus_reference !== '') {
            $lines[] = '- Active focus reference for this chat: ' . $focus_reference;
        }

        foreach ($reports as $report) {
            $reference_id = (string) ($report['reference_id'] ?? '');
            $is_focus = ($focus_reference !== '' && $reference_id === $focus_reference);
            if ($is_focus) {
                $lines[] = '  [FOCUS — customer opened chat about this report]';
            }

            $summary = sprintf(
                '  • %s — %s | status: %s | urgency: %s | submitted: %s',
                $reference_id,
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
            if (!empty($report['financial_loss'])) {
                $lines[] = '    reported financial loss: ' . (string) $report['financial_loss'] . ' ' . (string) ($report['financial_currency'] ?? '');
            }
            $attachment_names = is_array($report['attachment_names'] ?? null) ? $report['attachment_names'] : array();
            if (!empty($attachment_names)) {
                $lines[] = '    attachments (' . count($attachment_names) . '): ' . implode(', ', array_slice($attachment_names, 0, 8));
            } elseif ((int) ($report['attachments'] ?? 0) > 0) {
                $lines[] = '    attachments: ' . (int) $report['attachments'];
            }
            $updates = self::list_report_updates($user_id, $reference_id);
            if (!empty($updates)) {
                $lines[] = '    updates/messages:';
                foreach ($updates as $update) {
                    $lines[] = '      - ' . (string) ($update['created_at'] ?? '') . ': '
                        . (string) ($update['title'] ?? '') . ' — ' . (string) ($update['body'] ?? '');
                }
            } else {
                $lines[] = '    updates/messages: none yet beyond initial submission';
            }
            $lines[] = '    last status change: ' . (string) ($report['updated_at'] ?? $report['created_at'] ?? '');
            if (!empty($report['next_action'])) {
                $lines[] = '    what still needs to be done: ' . (string) $report['next_action'];
            }
            if (!empty($report['checks_summary'])) {
                $lines[] = '    document checks (preliminary, not legal verification): ' . (string) $report['checks_summary'];
            }
        }

        $lines[] = '- For Cybercrime Support questions, use ONLY these report facts (reference number, category, status, dates, summary, updates, attachments, checks, next action).';
        $lines[] = '- Stay on the same reference. Never start a new case if one already exists unless it is closed.';
        $lines[] = '- If the customer asks for updates and none are listed above, say the report is recorded and the team will contact them when there is news.';
        $lines[] = '- If files were rejected by preliminary checks, tell the customer exactly what to correct and that they can resubmit on the same reference.';

        return implode("\n", $lines);
    }
}
