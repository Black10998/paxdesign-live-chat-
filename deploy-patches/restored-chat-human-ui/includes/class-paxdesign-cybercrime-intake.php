<?php
/**
 * Cybercrime intake reports — storage, uploads, and notifications.
 * Evidence resubmit uploads require files when staff requested evidence.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_Intake {

    const TABLE_SUFFIX = 'paxdesign_cybercrime_reports';
    const NONCE_ACTION = 'paxdesign_cybercrime_report';
    const ATTACHMENT_ACTION = 'paxdesign_cybercrime_attachment';
    const UPLOAD_SUBDIR = 'pax-cybercrime-intake';
    const SCHEMA_VERSION = '2';
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
        add_action('wp_ajax_paxdesign_cybercrime_attachment', array(__CLASS__, 'ajax_download_attachment'));
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

        $user_id = get_current_user_id();
        $account_email = '';
        if ( $user_id > 0 ) {
            $account_user = get_user_by( 'id', $user_id );
            if ( $account_user instanceof WP_User ) {
                $account_email = sanitize_email( $account_user->user_email );
            }
        }

        return array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
            'maxFiles'      => self::MAX_FILES,
            'maxFileMb'     => (int) floor( self::MAX_FILE_BYTES / 1048576 ),
            'categories'    => self::$categories,
            'urgencyLevels' => self::$urgency_levels,
            'requireLogin'  => true,
            'isLoggedIn'    => is_user_logged_in(),
            'accountEmail'  => is_email( $account_email ) ? $account_email : '',
            'emailLocked'   => is_user_logged_in() && is_email( $account_email ),
            'loginUrl'      => esc_url( $login_url ),
            'resumeParam'   => 'pdx_ccs_start',
            'activeReport'  => self::safe_active_report_for_current_user(),
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

        if (!empty($_POST['website_trap'])) {
            wp_send_json_error(array('message' => __('Request rejected.', 'paxdesign-booking')), 403);
        }

        if (!self::check_rate_limit()) {
            wp_send_json_error(array(
                'message' => __('Too many submissions. Please wait before trying again.', 'paxdesign-booking'),
            ), 429);
        }

        $user_id = get_current_user_id();

        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            PAXdesign_Cybercrime_Tickets::ensure_schema();
            $active = PAXdesign_Cybercrime_Tickets::get_active_report_for_user($user_id);
            if ($active) {
                wp_send_json_error(array(
                    'message'      => __('You already have an open report. View your existing report to add updates or messages.', 'paxdesign-booking'),
                    'code'         => 'active_report_exists',
                    'activeReport' => $active,
                ), 409);
            }
        }

        $parsed = self::parse_submission( $_POST, $user_id );
        if (is_wp_error($parsed)) {
            wp_send_json_error(array('message' => $parsed->get_error_message()), 400);
        }

        if (
            empty($_FILES['identity_document']['name'])
            || (int) ($_FILES['identity_document']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            wp_send_json_error(array(
                'message' => __('Please upload an identity document before submitting.', 'paxdesign-booking'),
                'code'    => 'identity_document_required',
            ), 400);
        }

        $uploads = self::handle_uploads();
        if (is_wp_error($uploads)) {
            wp_send_json_error(array('message' => $uploads->get_error_message()), 400);
        }

        self::ensure_schema();

        $reference = self::generate_reference_id();
        $now = current_time('mysql', true);
        global $wpdb;
        $user_id = get_current_user_id();
        $chat_session_id = sanitize_text_field(wp_unslash($_POST['chat_session_id'] ?? ''));

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
                'reference_id'     => $reference,
                'customer_user_id' => max(0, (int) $user_id),
                'status'           => 'submitted',
                'reporter_name'    => $parsed['full_name'],
                'reporter_email'   => $parsed['email'],
                'reporter_phone'   => $parsed['phone'],
                'reporter_country' => $parsed['country'],
                'category'         => $parsed['category'],
                'urgency'          => $parsed['urgency'],
                'incident_at'      => $parsed['incident_at_sql'],
                'payload'          => wp_json_encode($payload),
                'attachments'      => wp_json_encode($uploads),
                'ip_hash'          => self::hash_ip(self::client_ip()),
                'created_at'       => $now,
                'updated_at'       => $now,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            $db_error = (string) $wpdb->last_error;
            if ($db_error !== '') {
                error_log('[PAXdesign Cybercrime] Insert failed: ' . $db_error);
            }

            $response = array(
                'message' => __('Could not save your report. Please try again or contact support.', 'paxdesign-booking'),
                'code'    => 'db_insert_failed',
            );
            if ($db_error !== '' && (defined('WP_DEBUG') && WP_DEBUG || current_user_can('manage_options'))) {
                $response['detail'] = $db_error;
            }

            wp_send_json_error($response, 500);
        }

        self::notify_admin($reference, $parsed, $uploads);
        self::notify_customer_submitted($user_id, $reference, $parsed);

        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            PAXdesign_Cybercrime_Tickets::record_submission($reference, $user_id, $parsed, $chat_session_id);
        }

        $lang = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::normalize((string) ($parsed['locale'] ?? 'ar'))
            : 'en';
        $success = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::t('submit.success', $lang)
            : __('Your report has been submitted securely.', 'paxdesign-booking');

        wp_send_json_success(array(
            'referenceId' => $reference,
            'message'     => $success,
        ));
    }

    /**
     * @param array<string, mixed> $post
     * @param int                  $user_id Authenticated customer user ID when available.
     * @return array<string, mixed>|WP_Error
     */
    private static function parse_submission( $post, $user_id = 0 ) {
        $full_name = sanitize_text_field( $post['full_name'] ?? '' );
        $email     = sanitize_email( $post['email'] ?? '' );
        $user_id   = absint( $user_id );
        if ( $user_id <= 0 && is_user_logged_in() ) {
            $user_id = get_current_user_id();
        }
        if ( $user_id > 0 ) {
            $user = get_user_by( 'id', $user_id );
            if ( ! $user instanceof WP_User ) {
                return new WP_Error( 'invalid_user', __( 'Invalid account.', 'paxdesign-booking' ) );
            }
            $email = sanitize_email( $user->user_email );
            if ( ! is_email( $email ) ) {
                return new WP_Error( 'invalid_account_email', __( 'Your account does not have a valid email address.', 'paxdesign-booking' ) );
            }
        } elseif ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'paxdesign-booking' ) );
        }
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

        if ( $full_name === '' || strlen( $full_name ) < 2 ) {
            return new WP_Error( 'invalid_name', __( 'Please enter your full legal name.', 'paxdesign-booking' ) );
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
     * Process $_FILES for cybercrime intake/resubmit (authenticated callers only).
     *
     * @return array<int, array<string, string>>|WP_Error
     */
    public static function handle_request_uploads() {
        return self::handle_uploads();
    }

    /**
     * Flatten and normalize $_FILES for mobile/desktop multipart uploads.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function normalized_upload_files() {
        if (empty($_FILES) || !is_array($_FILES)) {
            return array();
        }

        $normalized = array();
        foreach ($_FILES as $field => $file) {
            if (!is_array($file)) {
                continue;
            }
            $field = sanitize_key(preg_replace('/\[\]$/', '', (string) $field));
            if ($field === '') {
                continue;
            }

            $names = $file['name'] ?? null;
            if ($names === null || $names === '') {
                continue;
            }

            if (!is_array($names)) {
                $normalized[$field][] = array(
                    'name'     => (string) $names,
                    'type'     => (string) ($file['type'] ?? ''),
                    'tmp_name' => (string) ($file['tmp_name'] ?? ''),
                    'error'    => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
                    'size'     => (int) ($file['size'] ?? 0),
                );
                continue;
            }

            foreach ($names as $i => $name) {
                if ($name === '') {
                    continue;
                }
                $normalized[$field][] = array(
                    'name'     => (string) $name,
                    'type'     => (string) ($file['type'][$i] ?? ''),
                    'tmp_name' => (string) ($file['tmp_name'][$i] ?? ''),
                    'error'    => (int) ($file['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size'     => (int) ($file['size'][$i] ?? 0),
                );
            }
        }

        return $normalized;
    }

    /**
     * @param string $field
     * @return bool
     */
    private static function is_evidence_upload_field($field) {
        $field = sanitize_key((string) $field);
        if ($field === '') {
            return false;
        }
        if ($field === 'identity_document' || $field === 'evidence_other' || $field === 'evidence_resubmit') {
            return true;
        }
        return strpos($field, 'evidence_') === 0;
    }

    /**
     * @return bool
     */
    private static function is_evidence_resubmit_request() {
        return !empty($_POST['evidence_resubmit']) || !empty($_POST['pax_evidence_resubmit']);
    }

    /**
     * @return array<int, array<string, string>>|WP_Error
     */
    private static function handle_uploads() {
        $files = self::normalized_upload_files();
        if (empty($files)) {
            $content_type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
            $content_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($content_length > 1024 && stripos($content_type, 'multipart/form-data') !== false) {
                return new WP_Error(
                    'upload_failed',
                    __('The uploaded files could not be received. Try fewer or smaller files, then submit again.', 'paxdesign-booking')
                );
            }
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
            foreach ($files as $field => $batch) {
                if (!self::is_evidence_upload_field($field)) {
                    continue;
                }
                if (!is_array($batch)) {
                    continue;
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

                    $upload_dir = wp_upload_dir();
                    $rel_path = str_replace(trailingslashit($upload_dir['basedir']), '', $upload['file']);
                    $rel_path = ltrim(str_replace('\\', '/', $rel_path), '/');

                    $saved[] = array(
                        'field' => $field,
                        'name'  => basename($upload['file']),
                        'path'  => $rel_path,
                        'type'  => $upload['type'] ?? '',
                        'size'  => (string) filesize($upload['file']),
                    );
                    $count++;
                }
            }
        } finally {
            remove_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
        }

        if (empty($saved) && !empty($files)) {
            $had_candidate = false;
            foreach ($files as $field => $batch) {
                if (!self::is_evidence_upload_field($field) || !is_array($batch)) {
                    continue;
                }
                foreach ($batch as $single) {
                    if ((int) ($single['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $had_candidate = true;
                        break 2;
                    }
                }
            }
            if ($had_candidate) {
                return new WP_Error(
                    'upload_failed',
                    __('None of the selected files could be stored. Check file type and size, then try again.', 'paxdesign-booking')
                );
            }
        }

        if (empty($saved) && self::is_evidence_resubmit_request()) {
            $content_type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
            $content_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($content_length > 1024 && stripos($content_type, 'multipart/form-data') !== false) {
                return new WP_Error(
                    'upload_failed',
                    __('The uploaded files could not be received. Try fewer or smaller files, then submit again.', 'paxdesign-booking')
                );
            }
            return new WP_Error(
                'evidence_files_required',
                __('Please attach at least one evidence file before submitting.', 'paxdesign-booking')
            );
        }

        return $saved;
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
     * @param array<int, array<string, string>> $uploads
     */
    private static function notify_admin($reference, $parsed, $uploads) {
        $to = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
        $lang = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::normalize((string) ($parsed['locale'] ?? 'de'))
            : 'de';
        $category = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::category_label((string) ($parsed['category'] ?? ''), $lang)
            : (string) ($parsed['category'] ?? '');
        $subject = class_exists('PAXdesign_Cybercrime_I18n')
            ? sprintf(PAXdesign_Cybercrime_I18n::t('email.submit.admin.subject', $lang), $reference, $category)
            : sprintf('[Cybercrime Report] %s — %s', $reference, $parsed['category']);
        $body = "New cybercrime intake report\n\n"
            . "Reference: {$reference}\n"
            . "Name: {$parsed['full_name']}\n"
            . "Email: {$parsed['email']}\n"
            . "Phone: {$parsed['phone']}\n"
            . "Country: {$parsed['country']}\n"
            . "Category: {$category}\n"
            . "Locale: {$lang}\n"
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

    /**
     * @param int                  $user_id
     * @param string               $reference
     * @param array<string, mixed> $parsed
     */
    private static function notify_customer_submitted($user_id, $reference, $parsed) {
        $lang = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::normalize((string) ($parsed['locale'] ?? 'de'))
            : 'de';
        $status_label = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::status_label('submitted', $lang)
            : __('New', 'paxdesign-booking');
        $category_label = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::category_label((string) ($parsed['category'] ?? ''), $lang)
            : self::category_label((string) ($parsed['category'] ?? ''));
        $view = home_url('/cybercrime-support/?ref=' . rawurlencode($reference));

        $user_id = absint($user_id);
        if ($user_id > 0 && class_exists('PAXdesign_Customer_Notifications')) {
            $title = class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::t('notify.customer.title', $lang)
                : __('Cybercrime report received', 'paxdesign-booking');
            $body = class_exists('PAXdesign_Cybercrime_I18n')
                ? sprintf(PAXdesign_Cybercrime_I18n::t('notify.customer.body', $lang), $reference, $category_label)
                : sprintf(
                    __('Reference %1$s — %2$s. Your report is recorded and awaiting review.', 'paxdesign-booking'),
                    $reference,
                    $category_label
                );
            PAXdesign_Customer_Notifications::notify_user(
                $user_id,
                'security',
                $title,
                $body,
                'cybercrime',
                $reference,
                $view
            );
        }

        $email = sanitize_email((string) ($parsed['email'] ?? ''));
        if (!is_email($email) || !class_exists('PAXdesign_Cybercrime_I18n')) {
            return;
        }
        $subject = sprintf(PAXdesign_Cybercrime_I18n::t('email.submit.customer.subject', $lang), $reference);
        $body = sprintf(
            PAXdesign_Cybercrime_I18n::t('email.submit.customer.body', $lang),
            $reference,
            $status_label,
            $category_label,
            $view
        );
        wp_mail($email, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
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
        }

        $lines[] = '- For Cybercrime Support questions, use ONLY these report facts (reference number, category, status, dates, summary, updates, attachments).';
        $lines[] = '- If the customer asks for updates and none are listed above, say the report is recorded and the team will contact them when there is news.';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $attachment
     * @return string Absolute filesystem path or empty string.
     */
    public static function resolve_attachment_path($attachment) {
        if (!is_array($attachment)) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $basedir = trailingslashit((string) ($upload_dir['basedir'] ?? ''));
        if ($basedir === '') {
            return '';
        }

        $candidates = array();
        if (!empty($attachment['path'])) {
            $rel = ltrim(str_replace('\\', '/', (string) $attachment['path']), '/');
            $candidates[] = $basedir . $rel;
        }

        if (!empty($attachment['url'])) {
            $baseurl = rtrim((string) ($upload_dir['baseurl'] ?? ''), '/');
            $url = (string) $attachment['url'];
            if ($baseurl !== '' && strpos($url, $baseurl) === 0) {
                $rel = ltrim(substr($url, strlen($baseurl)), '/');
                if ($rel !== '') {
                    $candidates[] = $basedir . $rel;
                }
            }
        }

        $name = sanitize_file_name((string) ($attachment['name'] ?? ''));
        if ($name !== '') {
            $candidates[] = $basedir . self::UPLOAD_SUBDIR . '/' . $name;
            foreach (glob($basedir . self::UPLOAD_SUBDIR . '/*/' . $name) ?: array() as $match) {
                $candidates[] = $match;
            }
            foreach (glob($basedir . self::UPLOAD_SUBDIR . '/*/*/' . $name) ?: array() as $match) {
                $candidates[] = $match;
            }
        }

        foreach ($candidates as $path) {
            $path = str_replace('\\', '/', (string) $path);
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * Fill in a relative path when legacy records only stored a public URL.
     *
     * @param array<string, string> $attachment
     * @return array<string, string>
     */
    public static function recover_attachment_record(array $attachment) {
        $path = self::resolve_attachment_path($attachment);
        if ($path === '') {
            return $attachment;
        }
        $upload_dir = wp_upload_dir();
        $basedir = trailingslashit((string) ($upload_dir['basedir'] ?? ''));
        if ($basedir === '') {
            return $attachment;
        }
        $rel = ltrim(str_replace('\\', '/', str_replace($basedir, '', $path)), '/');
        if ($rel !== '') {
            $attachment['path'] = $rel;
        }
        return $attachment;
    }

    /**
     * @param string $path
     * @return string
     */
    public static function detect_attachment_mime($path) {
        $path = (string) $path;
        if ($path === '' || !is_readable($path)) {
            return '';
        }
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if (is_string($mime) && $mime !== '') {
                return sanitize_mime_type($mime);
            }
        }
        $checked = wp_check_filetype(basename($path));
        if (is_array($checked) && !empty($checked['type'])) {
            return sanitize_mime_type((string) $checked['type']);
        }
        return '';
    }

    /**
     * @param string $path
     * @param string $mime
     * @return bool
     */
    public static function verify_image_file($path, $mime = '') {
        $path = (string) $path;
        if ($path === '' || !is_readable($path)) {
            return false;
        }
        if ($mime === '') {
            $mime = self::detect_attachment_mime($path);
        }
        if (!self::is_image_mime($mime)) {
            return false;
        }
        if (in_array($mime, array('image/heic', 'image/heif'), true)) {
            return true;
        }
        if (function_exists('getimagesize')) {
            $info = @getimagesize($path);
            return is_array($info) && !empty($info[0]) && !empty($info[1]);
        }
        return true;
    }

    /**
     * @param string $mime
     * @return bool
     */
    public static function is_image_mime($mime) {
        $mime = strtolower(sanitize_mime_type((string) $mime));
        return strpos($mime, 'image/') === 0;
    }

    /**
     * @param string $reference_id
     * @param array<string, string> $attachment
     * @return string
     */
    public static function attachment_nonce($reference_id, $attachment) {
        $reference_id = sanitize_text_field((string) $reference_id);
        $name = sanitize_file_name((string) ($attachment['name'] ?? ''));
        if ($reference_id === '' || $name === '') {
            return '';
        }
        return wp_create_nonce('pax_ccs_att_' . $reference_id . '_' . md5($name));
    }

    /**
     * Authenticated download URL for a protected intake attachment.
     *
     * @param string               $reference_id
     * @param array<string, string> $attachment
     * @return string
     */
    public static function attachment_download_url($reference_id, $attachment) {
        $reference_id = sanitize_text_field((string) $reference_id);
        $name = sanitize_file_name((string) ($attachment['name'] ?? ''));
        if ($reference_id === '' || $name === '') {
            return '';
        }
        $nonce = self::attachment_nonce($reference_id, $attachment);
        if ($nonce === '') {
            return '';
        }
        return add_query_arg(
            array(
                'action'    => self::ATTACHMENT_ACTION,
                'reference' => $reference_id,
                'file'      => $name,
                '_wpnonce'  => $nonce,
            ),
            admin_url('admin-ajax.php')
        );
    }

    /**
     * @param string                         $reference_id
     * @param array<int, array<string, mixed>> $attachments
     * @return array<int, array<string, mixed>>
     */
    public static function enrich_attachments($reference_id, $attachments) {
        if (!is_array($attachments)) {
            return array();
        }
        $out = array();
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $resolved = self::recover_attachment_record($attachment);
            $name = sanitize_file_name((string) ($resolved['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $path = self::resolve_attachment_path($resolved);
            $available = ($path !== '' && is_readable($path));
            $type = (string) ($resolved['type'] ?? '');
            if ($available && $type === '') {
                $type = self::detect_attachment_mime($path);
            }
            $is_image = $available && self::verify_image_file($path, $type);
            $item = array(
                'field'     => sanitize_key((string) ($resolved['field'] ?? '')),
                'name'      => $name,
                'type'      => $type,
                'size'      => (string) ($resolved['size'] ?? ''),
                'is_image'  => $is_image,
                'url'       => $available ? self::attachment_download_url($reference_id, $resolved) : '',
            );
            if (!empty($resolved['path'])) {
                $item['path'] = (string) $resolved['path'];
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Stream a protected attachment after auth checks.
     */
    public static function ajax_download_attachment() {
        $reference = sanitize_text_field(wp_unslash($_GET['reference'] ?? ''));
        $file = sanitize_file_name(wp_unslash($_GET['file'] ?? ''));
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if ($reference === '' || $file === '' || $nonce === '') {
            wp_die(esc_html__('Invalid attachment request.', 'paxdesign-booking'), '', array('response' => 400));
        }

        if (!wp_verify_nonce($nonce, 'pax_ccs_att_' . $reference . '_' . md5($file))) {
            wp_die(esc_html__('Invalid or expired link.', 'paxdesign-booking'), '', array('response' => 403));
        }

        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please sign in.', 'paxdesign-booking'), '', array('response' => 401));
        }

        $row = class_exists('PAXdesign_Cybercrime_Tickets')
            ? PAXdesign_Cybercrime_Tickets::get_report_row($reference)
            : null;
        if (!$row) {
            wp_die(esc_html__('Report not found.', 'paxdesign-booking'), '', array('response' => 404));
        }

        $user_id = get_current_user_id();
        $allowed = current_user_can('manage_options');
        if (!$allowed && class_exists('PAXdesign_Cybercrime_Tickets')) {
            $allowed = PAXdesign_Cybercrime_Tickets::user_can_view_report($row, $user_id);
        }
        if (!$allowed) {
            wp_die(esc_html__('You cannot access this file.', 'paxdesign-booking'), '', array('response' => 403));
        }

        $match = null;
        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            $match = PAXdesign_Cybercrime_Tickets::find_stored_attachment($reference, $file, $row);
        }
        if (!$match) {
            $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
            if (!is_array($attachments)) {
                $attachments = array();
            }
            foreach ($attachments as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }
                if (sanitize_file_name((string) ($attachment['name'] ?? '')) === $file) {
                    $match = $attachment;
                    break;
                }
            }
        }

        if (!$match) {
            wp_die(esc_html__('Attachment not found.', 'paxdesign-booking'), '', array('response' => 404));
        }

        $match = self::recover_attachment_record(is_array($match) ? $match : array());
        $path = self::resolve_attachment_path($match);
        if ($path === '' || !is_readable($path)) {
            wp_die(esc_html__('File is unavailable.', 'paxdesign-booking'), '', array('response' => 404));
        }

        $mime = !empty($match['type']) ? (string) $match['type'] : (function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream');
        $mime = sanitize_mime_type($mime);
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace(array('"', "\r", "\n"), '', $file) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            wp_die(esc_html__('Could not read file.', 'paxdesign-booking'), '', array('response' => 500));
        }
        while (!feof($handle)) {
            echo fread($handle, 8192);
            if (function_exists('flush')) {
                flush();
            }
        }
        fclose($handle);
        exit;
    }
}
