<?php
/**
 * Cybercrime Support tickets — timeline, status workflow, chat/email sync.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_Tickets {

    const TABLE_MESSAGES = 'paxdesign_cybercrime_messages';
    const SCHEMA_VERSION = '4';

    /** @var list<string> Canonical workflow statuses (admin + database). */
    private static $workflow_statuses = array(
        'draft',
        'submitted',
        'in_review',
        'waiting_for_customer',
        'resolved',
        'closed',
        'rejected',
    );

    /** @var list<string> Legacy values normalized on read/write. */
    private static $legacy_status_map = array(
        'needs_info'         => 'waiting_for_customer',
        'customer_replied'   => 'in_review',
        'waiting_for_staff'  => 'in_review',
    );

    /** @var list<string> */
    private static $closed_statuses = array('resolved', 'closed', 'rejected');

    /** @var list<array<string, mixed>> */
    private static $deferred_customer_notifications = array();

    public static function init() {
        add_action('init', array(__CLASS__, 'ensure_schema'));
        add_action('wp_ajax_paxdesign_cybercrime_active_report', array(__CLASS__, 'ajax_active_report'));
        add_action('wp_ajax_paxdesign_cybercrime_report_detail', array(__CLASS__, 'ajax_report_detail'));
        add_action('wp_ajax_paxdesign_cybercrime_customer_reply', array(__CLASS__, 'ajax_customer_reply'));
        add_action('wp_ajax_paxdesign_cybercrime_customer_resubmit', array(__CLASS__, 'ajax_customer_resubmit'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'), 25);
        add_action('paxdesign_chat_message_appended', array(__CLASS__, 'on_chat_message'), 10, 4);
        add_action('admin_post_paxdesign_cybercrime_update_status', array(__CLASS__, 'admin_update_status'));
        add_action('admin_post_paxdesign_cybercrime_staff_reply', array(__CLASS__, 'admin_staff_reply'));
        add_action('wp_ajax_paxdesign_cybercrime_admin_status', array(__CLASS__, 'ajax_admin_status'));
        add_action('wp_ajax_paxdesign_cybercrime_admin_reply', array(__CLASS__, 'ajax_admin_reply'));
        add_action('wp_ajax_paxdesign_cybercrime_admin_internal_note', array(__CLASS__, 'ajax_admin_internal_note'));
        add_action('wp_ajax_paxdesign_cybercrime_admin_unread', array(__CLASS__, 'ajax_admin_unread'));
        add_action('wp_ajax_paxdesign_cybercrime_admin_mark_read', array(__CLASS__, 'ajax_admin_mark_read'));
        add_action('wp_ajax_paxdesign_cybercrime_mark_read', array(__CLASS__, 'ajax_mark_read'));
        add_action('wp_ajax_paxdesign_cybercrime_report_list', array(__CLASS__, 'ajax_report_list'));
    }

    const ADMIN_NONCE_ACTION = 'paxdesign_cybercrime_admin';

    public static function messages_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_MESSAGES;
    }

    public static function ensure_schema() {
        if (!class_exists('PAXdesign_Cybercrime_Intake')) {
            return;
        }
        PAXdesign_Cybercrime_Intake::ensure_schema();

        global $wpdb;
        $table = self::messages_table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_id bigint(20) unsigned NOT NULL DEFAULT 0,
            reference_id varchar(32) NOT NULL DEFAULT '',
            author_type varchar(16) NOT NULL DEFAULT 'system',
            author_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            channel varchar(16) NOT NULL DEFAULT 'portal',
            subject varchar(255) NOT NULL DEFAULT '',
            body longtext NOT NULL,
            meta_json longtext NOT NULL,
            external_id varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY reference_id (reference_id),
            KEY report_id (report_id),
            KEY external_id (external_id),
            KEY created_at (created_at)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $reports = PAXdesign_Cybercrime_Intake::table_name();
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$reports`", 0);
        if (is_array($columns) && !in_array('chat_session_id', $columns, true)) {
            $wpdb->query("ALTER TABLE `$reports` ADD COLUMN chat_session_id varchar(64) NOT NULL DEFAULT '' AFTER customer_user_id");
        }
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$reports`", 0);
        if (is_array($columns) && !in_array('customer_last_read_message_id', $columns, true)) {
            $wpdb->query("ALTER TABLE `$reports` ADD COLUMN customer_last_read_message_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER chat_session_id");
        }
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$reports`", 0);
        if (is_array($columns) && !in_array('staff_last_read_message_id', $columns, true)) {
            $wpdb->query("ALTER TABLE `$reports` ADD COLUMN staff_last_read_message_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER customer_last_read_message_id");
        }

        update_option('paxdesign_cybercrime_tickets_schema_version', self::SCHEMA_VERSION, false);

        if (get_option('paxdesign_cybercrime_status_migrated') !== self::SCHEMA_VERSION) {
            $wpdb->query("UPDATE `$reports` SET status = 'in_review' WHERE status IN ('customer_replied', 'waiting_for_staff')");
            $wpdb->query("UPDATE `$reports` SET status = 'waiting_for_customer' WHERE status = 'needs_info'");
            update_option('paxdesign_cybercrime_status_migrated', self::SCHEMA_VERSION, false);
        }
    }

    /**
     * @return list<string>
     */
    public static function workflow_statuses() {
        return self::$workflow_statuses;
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function workflow_steps() {
        $t = function ($key, $fallback) {
            return class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::t($key)
                : $fallback;
        };
        return array(
            'draft' => array(
                'label'       => $t('status.draft', __('Collecting', 'paxdesign-booking')),
                'description' => $t('status.desc.draft', __('Information is being collected on this case', 'paxdesign-booking')),
            ),
            'submitted' => array(
                'label'       => $t('status.submitted', __('New', 'paxdesign-booking')),
                'description' => $t('status.desc.submitted', __('New report received', 'paxdesign-booking')),
            ),
            'in_review' => array(
                'label'       => $t('status.in_review', __('In Review', 'paxdesign-booking')),
                'description' => $t('status.desc.in_review', __('Team is analyzing the report', 'paxdesign-booking')),
            ),
            'waiting_for_customer' => array(
                'label'       => $t('status.waiting_for_customer', __('Waiting for Customer', 'paxdesign-booking')),
                'description' => $t('status.desc.waiting_for_customer', __('Customer information is required', 'paxdesign-booking')),
            ),
            'resolved' => array(
                'label'       => $t('status.resolved', __('Approved', 'paxdesign-booking')),
                'description' => $t('status.desc.resolved', __('The case was approved', 'paxdesign-booking')),
            ),
            'closed' => array(
                'label'       => $t('status.closed', __('Closed', 'paxdesign-booking')),
                'description' => $t('status.desc.closed', __('Ticket completed', 'paxdesign-booking')),
            ),
            'rejected' => array(
                'label'       => $t('status.rejected', __('Rejected', 'paxdesign-booking')),
                'description' => $t('status.desc.rejected', __('This case was rejected', 'paxdesign-booking')),
            ),
        );
    }

    /**
     * @param string $status
     * @return string
     */
    public static function normalize_workflow_status($status) {
        $status = sanitize_key((string) $status);
        if (isset(self::$legacy_status_map[$status])) {
            return (string) self::$legacy_status_map[$status];
        }
        if (in_array($status, self::$workflow_statuses, true)) {
            return $status;
        }
        return 'submitted';
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function is_active_status($status) {
        return !in_array(self::normalize_workflow_status($status), self::$closed_statuses, true);
    }

    /**
     * Admin-facing workflow label.
     *
     * @param string $status
     * @return string
     */
    public static function status_label($status, $lang = '') {
        $status = self::normalize_workflow_status($status);
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            return PAXdesign_Cybercrime_I18n::status_label($status, $lang);
        }
        $steps = self::workflow_steps();
        if (isset($steps[$status]['label'])) {
            return (string) $steps[$status]['label'];
        }
        return ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Customer portal badge key (4 simple states).
     *
     * @param string $status
     * @return string collecting|under_review|waiting_for_customer|resolved|closed|rejected
     */
    public static function customer_status_key($status) {
        $status = self::normalize_workflow_status($status);
        switch ($status) {
            case 'draft':
                return 'collecting';
            case 'waiting_for_customer':
                return 'waiting_for_customer';
            case 'resolved':
                return 'resolved';
            case 'closed':
                return 'closed';
            case 'rejected':
                return 'rejected';
            default:
                return 'under_review';
        }
    }

    /**
     * @param string $status
     * @return string
     */
    public static function admin_status_badge_class($status) {
        $status = self::normalize_workflow_status($status);
        return 'pax-cc-status pax-cc-status--' . $status;
    }

    /**
     * @param array<string, mixed>      $row
     * @param array<int, array<mixed>>|null $timeline
     * @return array<int, array<string, string>>
     */
    public static function build_activity_indicators($row, $timeline = null) {
        $indicators = array();
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        $checks = is_array($payload) && isset($payload['document_checks']) && is_array($payload['document_checks'])
            ? $payload['document_checks']
            : array();
        $t = function ($key, $fallback) {
            return class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::t($key)
                : $fallback;
        };
        if (!empty($row['needs_human_review']) || !empty($checks['needs_human_review'])) {
            $indicators[] = array(
                'key'   => 'needs_human_review',
                'label' => $t('activity.needs_human_review', __('Needs human review (preliminary document checks)', 'paxdesign-booking')),
            );
        }
        $raw_status = sanitize_key((string) ($row['status'] ?? ''));
        if (isset(self::$legacy_status_map[$raw_status])) {
            if ($raw_status === 'customer_replied') {
                $indicators[] = array(
                    'key'   => 'customer_replied',
                    'label' => $t('activity.customer_replied', __('Customer replied — review pending', 'paxdesign-booking')),
                );
            } elseif ($raw_status === 'waiting_for_staff') {
                $indicators[] = array(
                    'key'   => 'waiting_for_staff',
                    'label' => $t('activity.waiting_staff', __('Waiting for staff action', 'paxdesign-booking')),
                );
            }
        }

        if (!is_array($timeline) || empty($timeline)) {
            return $indicators;
        }

        $last_customer = null;
        $last_staff = null;
        foreach (array_reverse($timeline) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $author = sanitize_key((string) ($entry['author_type'] ?? ''));
            if ($author === 'customer' && $last_customer === null) {
                $last_customer = $entry;
            }
            if ($author === 'staff' && $last_staff === null) {
                $last_staff = $entry;
            }
            if ($last_customer !== null && $last_staff !== null) {
                break;
            }
        }

        if ($last_customer !== null) {
            $customer_at = (string) ($last_customer['created_at'] ?? '');
            $staff_at = $last_staff ? (string) ($last_staff['created_at'] ?? '') : '';
            if ($staff_at === '' || $customer_at > $staff_at) {
                $indicators[] = array(
                    'key'   => 'latest_customer_reply',
                    'label' => class_exists('PAXdesign_Cybercrime_I18n')
                        ? PAXdesign_Cybercrime_I18n::t('activity.latest_customer')
                        : __('Latest activity: customer reply', 'paxdesign-booking'),
                    'at'    => $customer_at,
                );
            }
        }

        return $indicators;
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function is_valid_workflow_status($status) {
        return in_array(self::normalize_workflow_status($status), self::$workflow_statuses, true);
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>|null
     */
    public static function get_active_report_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = PAXdesign_Cybercrime_Intake::table_name();
        $user = get_user_by('id', $user_id);
        $email = ($user instanceof WP_User) ? sanitize_email($user->user_email) : '';
        $closed = array_map('sanitize_key', self::$closed_statuses);
        $placeholders = implode(',', array_fill(0, count($closed), '%s'));

        if ($email !== '') {
            $sql = "SELECT * FROM $table WHERE status NOT IN ($placeholders)
                    AND (customer_user_id = %d OR (customer_user_id = 0 AND reporter_email = %s))
                    ORDER BY created_at DESC LIMIT 1";
            $params = array_merge($closed, array($user_id, $email));
            $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        } else {
            $sql = "SELECT * FROM $table WHERE status NOT IN ($placeholders) AND customer_user_id = %d
                    ORDER BY created_at DESC LIMIT 1";
            $params = array_merge($closed, array($user_id));
            $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        }

        if (!is_array($row)) {
            return null;
        }

        return self::format_report_row($row, true);
    }

    /**
     * @param string $reference_id
     * @param int    $user_id
     * @return array<string, mixed>|null
     */
    public static function get_report_for_user($reference_id, $user_id) {
        $row = self::get_report_row($reference_id);
        if (!$row || !self::user_can_view_report($row, $user_id)) {
            return null;
        }
        return self::format_report_row($row, true);
    }

    /**
     * @param string $reference_id
     * @return array<string, mixed>|null
     */
    public static function get_report_row($reference_id) {
        global $wpdb;
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '') {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PAXdesign_Cybercrime_Intake::table_name() . ' WHERE reference_id = %s LIMIT 1',
            $reference_id
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @param int                  $user_id
     * @return bool
     */
    public static function user_can_view_report($row, $user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !is_array($row)) {
            return false;
        }
        if ((int) ($row['customer_user_id'] ?? 0) === $user_id) {
            return true;
        }
        $user = get_user_by('id', $user_id);
        if ($user instanceof WP_User) {
            return sanitize_email($user->user_email) === sanitize_email((string) ($row['reporter_email'] ?? ''));
        }
        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @param bool                 $with_timeline
     * @return array<string, mixed>
     */
    public static function format_report_row($row, $with_timeline = false, $timeline_audience = 'customer', $unread_audience = '') {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($attachments)) {
            $attachments = array();
        }

        $raw_status = sanitize_key((string) ($row['status'] ?? ''));
        $workflow_status = self::normalize_workflow_status($raw_status);
        $lang = 'en';
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            $lang = $timeline_audience === 'admin'
                ? PAXdesign_Cybercrime_I18n::lang()
                : PAXdesign_Cybercrime_I18n::customer_lang((int) ($row['customer_user_id'] ?? 0));
        }
        $status_label_i18n = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::pack('status.' . $workflow_status)
            : array();
        if (isset($status_label_i18n['en']) && $status_label_i18n['en'] === 'status.' . $workflow_status) {
            $status_label_i18n = array();
        }

        $out = array(
            'reference_id'    => (string) ($row['reference_id'] ?? ''),
            'status'          => $workflow_status,
            'status_raw'      => $raw_status !== $workflow_status ? $raw_status : '',
            'status_label'    => self::status_label($raw_status, $lang),
            'status_label_i18n' => $status_label_i18n,
            'customer_status' => self::customer_status_key($raw_status),
            'is_active'       => self::is_active_status($raw_status),
            'category'        => (string) ($row['category'] ?? ''),
            'category_label'  => class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::category_label((string) ($row['category'] ?? ''), $lang)
                : PAXdesign_Cybercrime_Intake::category_label((string) ($row['category'] ?? '')),
            'locale'          => $lang,
            'urgency'         => (string) ($row['urgency'] ?? ''),
            'reporter_name'   => (string) ($row['reporter_name'] ?? ''),
            'reporter_email'  => (string) ($row['reporter_email'] ?? ''),
            'incident_at'     => (string) ($row['incident_at'] ?? ''),
            'created_at'      => (string) ($row['created_at'] ?? ''),
            'updated_at'      => (string) ($row['updated_at'] ?? ''),
            'description'     => (string) ($payload['description'] ?? ''),
            'platforms'       => (string) ($payload['platforms'] ?? ''),
            'financial_loss'  => (string) ($payload['financial_loss'] ?? ''),
            'financial_currency' => (string) ($payload['financial_currency'] ?? 'EUR'),
            'attachments'     => $attachments,
            'chat_session_id' => (string) ($row['chat_session_id'] ?? ''),
        );

        $display_desc = self::display_case_description($out, $payload);
        if ($display_desc['replaced']) {
            $out['description'] = $display_desc['text'];
            $out['description_raw'] = $display_desc['raw'];
        }

        $customer_display_name = self::resolve_customer_display_name($row);
        $out['customer_display_name'] = $customer_display_name;
        $read_audience = $unread_audience !== ''
            ? sanitize_key((string) $unread_audience)
            : ($timeline_audience === 'admin' ? 'staff' : 'customer');
        if (!in_array($read_audience, array('staff', 'customer'), true)) {
            $read_audience = 'customer';
        }
        $out['unread_count'] = self::count_unread_for_audience((string) ($row['reference_id'] ?? ''), $read_audience, $row);

        $checks = is_array($payload['document_checks'] ?? null) ? $payload['document_checks'] : array();
        $guided = is_array($payload['guided_case'] ?? null) ? $payload['guided_case'] : array();
        $customer_checks = class_exists('PAXdesign_Cybercrime_Document_Checks')
            ? PAXdesign_Cybercrime_Document_Checks::customer_view($checks, $lang)
            : array();

        $out['original_request'] = array(
            'reporter_name'       => (string) ($row['reporter_name'] ?? ''),
            'reporter_email'      => (string) ($row['reporter_email'] ?? ''),
            'reporter_phone'      => (string) ($row['reporter_phone'] ?? ''),
            'reporter_country'    => (string) ($row['reporter_country'] ?? ''),
            'category'            => (string) ($row['category'] ?? ''),
            'category_label'      => $out['category_label'],
            'urgency'             => (string) ($row['urgency'] ?? ''),
            'incident_at'         => (string) ($row['incident_at'] ?? ''),
            'incident_date'       => (string) ($payload['incident_date'] ?? ''),
            'incident_time'       => (string) ($payload['incident_time'] ?? ''),
            'platforms'           => (string) ($payload['platforms'] ?? ''),
            'description'         => (string) ($payload['description'] ?? ''),
            'financial_loss'      => (string) ($payload['financial_loss'] ?? ''),
            'financial_currency'  => (string) ($payload['financial_currency'] ?? 'EUR'),
        );
        if (!empty($out['description_raw'])) {
            $out['original_request']['description'] = $out['description'];
        }

        $next_action = (string) ($guided['next_action'] ?? $checks['next_action'] ?? '');
        if ($workflow_status === 'draft') {
            $next_action = self::draft_next_action($out['original_request'], $attachments, $lang);
        } elseif ($next_action === '') {
            $next_action = self::default_next_action($workflow_status, $customer_checks, $lang);
        } elseif (class_exists('PAXdesign_Cybercrime_I18n')) {
            $next_action = PAXdesign_Cybercrime_I18n::localize_canned($next_action, $lang);
        }
        $out['checks'] = $timeline_audience === 'admin' ? $checks : $customer_checks;
        $out['needs_human_review'] = !empty($row['needs_human_review']) || !empty($checks['needs_human_review']);
        $out['next_action'] = $next_action;
        $out['correction_required'] = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::localize_list(array_values((array) ($customer_checks['customer_corrections'] ?? array())), $lang)
            : array_values((array) ($customer_checks['customer_corrections'] ?? array()));
        $out['can_resubmit'] = self::is_active_status($raw_status);
        $out['is_draft'] = ($raw_status === 'draft' || $workflow_status === 'draft');
        $missing_keys = self::missing_case_fields($out, $row, $payload);
        $out['missing_fields'] = array();
        foreach ($missing_keys as $item) {
            $out['missing_fields'][] = class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::missing_field_label($item, $lang)
                : $item;
        }
        if (class_exists('PAXdesign_Cybercrime_AI_Workflow') && ($out['is_draft'] || $workflow_status === 'draft')) {
            $out['workflow'] = PAXdesign_Cybercrime_AI_Workflow::snapshot($row, $lang);
            if (!empty($out['workflow']['missing_labels'])) {
                $out['missing_fields'] = array_values((array) $out['workflow']['missing_labels']);
            }
        }
        $out['case_summary'] = self::build_case_summary_text($out);
        $out['rejection'] = self::public_rejection($payload, $workflow_status, $lang);
        if ($workflow_status === 'rejected' && is_array($out['rejection'])) {
            $reject_next = self::customer_rejection_next_action($out['rejection'], $lang);
            if ($reject_next !== '') {
                $out['next_action'] = $reject_next;
            }
        }
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            if ($workflow_status === 'rejected') {
                $out['next_action_i18n'] = PAXdesign_Cybercrime_I18n::pack('decision.next_action');
            } elseif ($workflow_status === 'draft') {
                $out['next_action_i18n'] = array(
                    'ar' => self::draft_next_action($out['original_request'], $attachments, 'ar'),
                    'de' => self::draft_next_action($out['original_request'], $attachments, 'de'),
                    'en' => self::draft_next_action($out['original_request'], $attachments, 'en'),
                );
            } elseif (!empty($customer_checks['customer_corrections'])) {
                $out['next_action_i18n'] = PAXdesign_Cybercrime_I18n::pack('next.corrections');
            } else {
                $out['next_action_i18n'] = array(
                    'ar' => PAXdesign_Cybercrime_I18n::next_action_text($workflow_status, 'ar'),
                    'de' => PAXdesign_Cybercrime_I18n::next_action_text($workflow_status, 'de'),
                    'en' => PAXdesign_Cybercrime_I18n::next_action_text($workflow_status, 'en'),
                );
            }
            if (!empty($out['next_action_i18n'][$lang])) {
                $out['next_action'] = $out['next_action_i18n'][$lang];
            }
        }
        if ($timeline_audience === 'admin' && class_exists('PAXdesign_Cybercrime_I18n')) {
            $out['status_badge_html'] = PAXdesign_Cybercrime_I18n::status_badge_html($workflow_status, (string) $out['status_label']);
        }
        if (class_exists('PAXdesign_Cybercrime_AI_Operations') && !empty($payload['ai_operations']) && is_array($payload['ai_operations'])) {
            $last = end($payload['ai_operations']);
            if (is_array($last)) {
                $out['ai_operation'] = PAXdesign_Cybercrime_AI_Operations::public_operation($last);
            }
        }

        if ($with_timeline) {
            if ($timeline_audience === 'admin') {
                $out['timeline'] = self::list_official_messages(
                    (string) ($row['reference_id'] ?? ''),
                    200,
                    $customer_display_name,
                    'admin'
                );
            } else {
                $out['timeline'] = self::list_customer_messages(
                    (string) ($row['reference_id'] ?? ''),
                    200,
                    $customer_display_name
                );
            }
            if ($timeline_audience === 'admin') {
                $out['activity_indicators'] = self::build_activity_indicators($row, $out['timeline']);
            }
        }

        return $out;
    }

    /**
     * @param string               $status
     * @param array<string, mixed> $checks
     * @return string
     */
    public static function default_next_action($status, $checks = array(), $lang = '') {
        $status = self::normalize_workflow_status($status);
        $corrections = (array) ($checks['customer_corrections'] ?? array());
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            if ($lang === '') {
                $lang = PAXdesign_Cybercrime_I18n::customer_lang();
            }
            if (!empty($corrections)) {
                return PAXdesign_Cybercrime_I18n::t('next.corrections', $lang);
            }
            return PAXdesign_Cybercrime_I18n::next_action_text($status, $lang);
        }
        if (!empty($corrections)) {
            return __('Replace the rejected files on this same case, then wait for administrator review.', 'paxdesign-booking');
        }
        switch ($status) {
            case 'draft':
                return __('Share what happened in chat or continue on this page. Facts are saved to this same case.', 'paxdesign-booking');
            case 'waiting_for_customer':
                return __('The team asked for more information. Reply or upload the requested files on this same reference.', 'paxdesign-booking');
            case 'in_review':
                return __('The PAXDesign team is reviewing this case. No action is required unless they ask for more information.', 'paxdesign-booking');
            case 'submitted':
                return __('Your report is received and waiting for administrator review.', 'paxdesign-booking');
            case 'resolved':
                return __('This case is resolved. You can review the outcome on this reference.', 'paxdesign-booking');
            case 'closed':
                return __('This case is closed. Start a new report only if you need help with a new incident.', 'paxdesign-booking');
            case 'rejected':
                return __('This case was rejected. Review the decision on this same reference.', 'paxdesign-booking');
            default:
                return __('Stay on this reference. The team will update the official conversation when there is news.', 'paxdesign-booking');
        }
    }

    /**
     * @param array<string, mixed> $report
     * @return string
     */
    public static function build_case_summary_text($report) {
        $ref = (string) ($report['reference_id'] ?? '');
        $status = (string) ($report['status_label'] ?? $report['status'] ?? '');
        $category = (string) ($report['category_label'] ?? '');
        $next = (string) ($report['next_action'] ?? '');
        $parts = array_filter(array($ref, $category, $status));
        $text = implode(' · ', $parts);
        if ($next !== '') {
            $text .= '. ' . $next;
        }
        return $text;
    }

    /**
     * @param array<string, mixed>               $original
     * @param array<int, array<string, mixed>>   $attachments
     * @return string
     */
    public static function draft_next_action($original, $attachments = array(), $lang = '') {
        $missing = self::missing_case_fields(array('original_request' => $original, 'attachments' => $attachments), array(), array());
        if (empty($missing)) {
            return class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::t('next.draft', $lang)
                : __('Review the saved details on this page, then submit the case or add evidence.', 'paxdesign-booking');
        }
        $labels = array();
        foreach ($missing as $item) {
            $labels[] = class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::missing_field_label($item, $lang)
                : $item;
        }
        $tpl = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::t('missing.still_needed', $lang)
            : __('Still needed on this same case: %s.', 'paxdesign-booking');
        return sprintf($tpl, implode(', ', $labels));
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $row
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    public static function missing_case_fields($report, $row = array(), $payload = array()) {
        $original = is_array($report['original_request'] ?? null) ? $report['original_request'] : array();
        $attachments = is_array($report['attachments'] ?? null) ? $report['attachments'] : array();
        if (empty($attachments) && is_array($row)) {
            $decoded = json_decode((string) ($row['attachments'] ?? ''), true);
            if (is_array($decoded)) {
                $attachments = $decoded;
            }
        }
        $missing = array();
        if (trim((string) ($original['category'] ?? $report['category'] ?? '')) === '') {
            $missing[] = __('incident type', 'paxdesign-booking');
        }
        if (trim((string) ($original['incident_at'] ?? $original['incident_date'] ?? $report['incident_at'] ?? '')) === '') {
            $missing[] = __('incident date', 'paxdesign-booking');
        }
        if (trim((string) ($original['platforms'] ?? $report['platforms'] ?? '')) === '') {
            $missing[] = __('affected platforms', 'paxdesign-booking');
        }
        $description = trim((string) ($original['description'] ?? $report['description'] ?? ''));
        $category = trim((string) ($original['category'] ?? $report['category'] ?? ''));
        $incident_date = trim((string) ($original['incident_at'] ?? $original['incident_date'] ?? $report['incident_at'] ?? ''));
        $platforms = trim((string) ($original['platforms'] ?? $report['platforms'] ?? ''));
        $has_structured_core = $category !== '' && $incident_date !== '' && $platforms !== '';
        if (strlen($description) < 20 && ! $has_structured_core) {
            $missing[] = __('incident description', 'paxdesign-booking');
        }
        $has_id = false;
        $has_evidence = false;
        foreach ($attachments as $file) {
            if (!is_array($file)) {
                continue;
            }
            $field = sanitize_key((string) ($file['field'] ?? ''));
            if ($field === 'identity_document') {
                $has_id = true;
            } else {
                $has_evidence = true;
            }
        }
        if (!$has_id) {
            $missing[] = __('identity document', 'paxdesign-booking');
        }
        if (trim((string) ($original['reporter_phone'] ?? $report['reporter_phone'] ?? $row['reporter_phone'] ?? '')) === '') {
            $missing[] = 'phone';
        }
        $country = trim((string) ($original['reporter_country'] ?? $report['reporter_country'] ?? $row['reporter_country'] ?? $payload['country_code'] ?? ''));
        if ($country === '') {
            $missing[] = 'country';
        }
        if (!$has_evidence) {
            $missing[] = __('evidence files', 'paxdesign-booking');
        }
        unset($payload);
        return $missing;
    }

    /**
     * Case page / admin display copy — never a pasted chat transcript.
     *
     * @param array<string, mixed> $report
     * @param array<string, mixed> $payload
     * @return array{text:string,raw:string,replaced:bool}
     */
    public static function display_case_description($report, $payload = array()) {
        $raw = trim((string) ($report['description'] ?? $payload['description'] ?? ''));
        $empty = array('text' => $raw, 'raw' => '', 'replaced' => false);
        if ($raw === '' || !class_exists('PAXdesign_Cybercrime_AI_Case')) {
            return $empty;
        }
        if (!PAXdesign_Cybercrime_AI_Case::is_chat_dump_description($raw)) {
            return $empty;
        }
        $summary = PAXdesign_Cybercrime_AI_Case::structured_summary(array(), array(
            'category'            => (string) ($report['category'] ?? ''),
            'incident_date'       => (string) ($payload['incident_date'] ?? ''),
            'incident_at'         => (string) ($report['incident_at'] ?? ''),
            'platforms'           => (string) ($report['platforms'] ?? ''),
            'financial_loss'      => (string) ($report['financial_loss'] ?? ''),
            'financial_currency'  => (string) ($report['financial_currency'] ?? ''),
        ));
        if ($summary === '') {
            return $empty;
        }
        return array(
            'text'     => $summary,
            'raw'      => $raw,
            'replaced' => true,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return string
     */
    public static function resolve_customer_display_name($row) {
        if (!is_array($row)) {
            return '';
        }
        $name = trim(sanitize_text_field((string) ($row['reporter_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }
        $user_id = absint($row['customer_user_id'] ?? 0);
        if ($user_id <= 0) {
            return '';
        }
        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) {
            return '';
        }
        $display = trim(sanitize_text_field($user->display_name));
        if ($display !== '' && $display !== $user->user_login) {
            return $display;
        }
        $first = trim(sanitize_text_field((string) get_user_meta($user_id, 'first_name', true)));
        $last  = trim(sanitize_text_field((string) get_user_meta($user_id, 'last_name', true)));
        $full  = trim($first . ' ' . $last);
        return $full !== '' ? $full : $display;
    }

    /**
     * Official ticket timeline — excludes AI assistant and live-chat widget messages.
     *
     * @param string $reference_id
     * @param int    $limit
     * @return array<int, array<string, mixed>>
     */
    public static function list_official_messages($reference_id, $limit = 200, $customer_display_name = '', $audience = 'admin') {
        if ($customer_display_name === '') {
            $report_row = self::get_report_row($reference_id);
            if (is_array($report_row)) {
                $customer_display_name = self::resolve_customer_display_name($report_row);
            }
        }
        $messages = self::list_messages($reference_id, $limit);
        $official = array();
        foreach ($messages as $entry) {
            if (!is_array($entry) || !self::is_official_timeline_entry($entry)) {
                continue;
            }
            $official[] = self::format_timeline_entry($entry, $customer_display_name, $audience);
        }
        return $official;
    }

    /**
     * Customer-facing timeline — conversation only, no internal/system noise.
     *
     * @param string $reference_id
     * @param int    $limit
     * @param string $customer_display_name
     * @return array<int, array<string, mixed>>
     */
    public static function list_customer_messages($reference_id, $limit = 200, $customer_display_name = '') {
        if ($customer_display_name === '') {
            $report_row = self::get_report_row($reference_id);
            if (is_array($report_row)) {
                $customer_display_name = self::resolve_customer_display_name($report_row);
            }
        }
        $messages = self::list_messages($reference_id, $limit);
        $visible = array();
        foreach ($messages as $entry) {
            if (!is_array($entry) || !self::is_customer_visible_timeline_entry($entry)) {
                continue;
            }
            $visible[] = self::format_timeline_entry($entry, $customer_display_name, 'customer');
        }
        return $visible;
    }

    /**
     * @param array<string, mixed> $entry
     * @return bool
     */
    public static function is_official_timeline_entry($entry) {
        if (!is_array($entry)) {
            return false;
        }
        $author = sanitize_key((string) ($entry['author_type'] ?? ''));
        $channel = sanitize_key((string) ($entry['channel'] ?? ''));
        if ($author === 'ai' || $channel === 'chat') {
            return false;
        }
        return in_array($author, array('customer', 'staff', 'system'), true);
    }

    /**
     * Messages that should increment staff unread counts until the ticket is opened.
     *
     * @param array<string, mixed> $entry
     */
    public static function is_staff_unread_message($entry) {
        if (!is_array($entry) || !self::is_official_timeline_entry($entry)) {
            return false;
        }
        $author = sanitize_key((string) ($entry['author_type'] ?? ''));
        if ($author === 'customer') {
            return true;
        }
        if ($author === 'system') {
            $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : array();
            return sanitize_key((string) ($meta['event'] ?? '')) === 'submitted';
        }
        return false;
    }

    /**
     * Customer portal — only conversation messages intended for the reporter.
     *
     * @param array<string, mixed> $entry
     * @return bool
     */
    public static function is_customer_visible_timeline_entry($entry) {
        if (!is_array($entry) || !self::is_official_timeline_entry($entry)) {
            return false;
        }

        $author = sanitize_key((string) ($entry['author_type'] ?? ''));
        $body = trim((string) ($entry['body'] ?? ''));
        if ($body === '') {
            return false;
        }

        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : array();
        $event = sanitize_key((string) ($meta['event'] ?? ''));

        if (!empty($meta['internal_only'])) {
            return false;
        }

        if ($author === 'customer' || $author === 'staff') {
            return true;
        }

        if ($author !== 'system') {
            return false;
        }

        if ($event === 'submitted') {
            return false;
        }

        if (!empty($meta['visible_to_customer'])) {
            return true;
        }

        if ($event === 'status_change' && !self::is_internal_system_message($body)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $body
     * @return bool
     */
    private static function is_internal_system_message($body) {
        $body = trim((string) $body);
        if ($body === '') {
            return true;
        }

        $patterns = array(
            '/^Status changed to .+\.$/i',
            '/^Customer added a portal reply\.$/i',
            '/^Customer replied by email\.$/i',
        );
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public static function format_timeline_entry($entry, $customer_display_name = '', $audience = 'customer') {
        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : array();
        $author = sanitize_key((string) ($entry['author_type'] ?? ''));
        $event = sanitize_key((string) ($meta['event'] ?? ''));

        $status_key = '';
        if ($event === 'status_change' && !empty($meta['to'])) {
            $status_key = sanitize_key((string) $meta['to']);
        } elseif ($author === 'staff') {
            $status_key = 'waiting_for_customer';
        } elseif ($author === 'customer') {
            $status_key = 'in_review';
        } elseif ($event === 'submitted') {
            $status_key = 'submitted';
        }

        $subject = sanitize_text_field((string) ($entry['subject'] ?? ''));
        $subject_key = '';
        if ($audience === 'admin') {
            if ($subject === '' && $event === 'status_change' && $status_key !== '') {
                $subject_key = 'status_' . $status_key;
            } elseif ($subject === '' && $event === 'submitted') {
                $subject_key = 'report_submitted';
            }
        }

        $customer_display_name = trim(sanitize_text_field((string) $customer_display_name));
        $entry['status'] = $status_key;
        $lang = 'en';
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            $lang = $audience === 'admin'
                ? PAXdesign_Cybercrime_I18n::lang()
                : PAXdesign_Cybercrime_I18n::customer_lang();
        }
        $entry['status_label'] = $status_key !== '' ? self::status_label($status_key, $lang) : '';
        if ($event === 'status_change' && $status_key !== '' && class_exists('PAXdesign_Cybercrime_I18n')) {
            $entry['body'] = PAXdesign_Cybercrime_I18n::status_changed_text($status_key, $lang);
            $entry['body_i18n'] = array(
                'ar' => PAXdesign_Cybercrime_I18n::status_changed_text($status_key, 'ar'),
                'de' => PAXdesign_Cybercrime_I18n::status_changed_text($status_key, 'de'),
                'en' => PAXdesign_Cybercrime_I18n::status_changed_text($status_key, 'en'),
            );
        } elseif ($author === 'system' && class_exists('PAXdesign_Cybercrime_I18n')) {
            $entry['body'] = PAXdesign_Cybercrime_I18n::localize_canned((string) ($entry['body'] ?? ''), $lang);
        }
        $entry['sender_key'] = $author === 'customer' ? 'customer' : 'support';
        $entry['customer_name'] = $author === 'customer' ? $customer_display_name : '';
        $entry['customer_visible'] = self::is_customer_visible_timeline_entry($entry);
        $entry['subject_key'] = $subject_key;
        $entry['subject'] = $audience === 'customer' ? '' : $subject;

        return $entry;
    }

    /**
     * @param string $reference_id
     * @param int    $limit
     * @return array<int, array<string, mixed>>
     */
    public static function list_messages($reference_id, $limit = 200) {
        global $wpdb;
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '') {
            return array();
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::messages_table() . ' WHERE reference_id = %s ORDER BY created_at ASC, id ASC LIMIT %d',
            $reference_id,
            max(1, min(500, (int) $limit))
        ), ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
            if (!is_array($meta)) {
                $meta = array();
            }
            $out[] = array(
                'id'           => (int) ($row['id'] ?? 0),
                'author_type'  => (string) ($row['author_type'] ?? ''),
                'author_user_id' => (int) ($row['author_user_id'] ?? 0),
                'channel'      => (string) ($row['channel'] ?? ''),
                'subject'      => (string) ($row['subject'] ?? ''),
                'body'         => (string) ($row['body'] ?? ''),
                'meta'         => $meta,
                'created_at'   => (string) ($row['created_at'] ?? ''),
            );
        }
        return $out;
    }

    /**
     * @param string               $reference_id
     * @param string               $author_type customer|staff|ai|system
     * @param string               $body
     * @param string               $channel portal|chat|email|admin
     * @param int                  $author_user_id
     * @param array<string, mixed> $meta
     * @param string               $subject
     * @param string               $external_id
     * @return int|false
     */
    public static function add_message($reference_id, $author_type, $body, $channel = 'portal', $author_user_id = 0, $meta = array(), $subject = '', $external_id = '') {
        global $wpdb;
        $reference_id = sanitize_text_field((string) $reference_id);
        $body = trim((string) $body);
        if ($reference_id === '' || $body === '') {
            return false;
        }

        $row = self::get_report_row($reference_id);
        if (!$row) {
            return false;
        }

        $author_type = sanitize_key($author_type);
        if (!in_array($author_type, array('customer', 'staff', 'ai', 'system'), true)) {
            $author_type = 'system';
        }
        $channel = sanitize_key((string) $channel);
        $external_id = sanitize_text_field((string) $external_id);

        if ($external_id !== '') {
            $existing = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . self::messages_table() . ' WHERE external_id = %s LIMIT 1',
                $external_id
            ));
            if ($existing > 0) {
                return $existing;
            }
        }

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            self::messages_table(),
            array(
                'report_id'      => (int) ($row['id'] ?? 0),
                'reference_id'   => $reference_id,
                'author_type'    => $author_type,
                'author_user_id' => max(0, (int) $author_user_id),
                'channel'        => $channel !== '' ? $channel : 'portal',
                'subject'        => sanitize_text_field($subject),
                'body'           => wp_kses_post($body),
                'meta_json'      => wp_json_encode(is_array($meta) ? $meta : array()),
                'external_id'    => $external_id,
                'created_at'     => $now,
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        if (!$inserted) {
            return false;
        }

        $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array('updated_at' => $now),
            array('reference_id' => $reference_id),
            array('%s'),
            array('%s')
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * @param string $reference_id
     * @param string $new_status
     * @param int    $actor_user_id
     * @param string $summary
     * @param bool   $notify_customer
     * @return bool|WP_Error
     */
    public static function update_status($reference_id, $new_status, $actor_user_id = 0, $summary = '', $notify_customer = true, $log_timeline = true) {
        $reference_id = sanitize_text_field((string) $reference_id);
        $new_status = sanitize_key((string) $new_status);
        $new_status = self::normalize_workflow_status($new_status);
        if ($reference_id === '' || !in_array($new_status, self::$workflow_statuses, true)) {
            return new WP_Error('invalid_status', __('Invalid status.', 'paxdesign-booking'));
        }

        $row = self::get_report_row($reference_id);
        if (!$row) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'));
        }

        $old_status = self::normalize_workflow_status((string) ($row['status'] ?? ''));
        if ($old_status === $new_status) {
            return true;
        }

        global $wpdb;
        $now = current_time('mysql', true);
        $updated = $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array('status' => $new_status, 'updated_at' => $now),
            array('reference_id' => $reference_id),
            array('%s', '%s'),
            array('%s')
        );
        if ($updated === false) {
            return new WP_Error('db_error', __('Could not update status.', 'paxdesign-booking'));
        }

        $fresh = self::get_report_row($reference_id);
        if (!$fresh || self::normalize_workflow_status((string) ($fresh['status'] ?? '')) !== $new_status) {
            return new WP_Error('db_error', __('Could not update status.', 'paxdesign-booking'));
        }
        $row = $fresh;

        $label = self::status_label($new_status);
        $message = $summary !== ''
            ? $summary
            : sprintf(__('Status changed to %s.', 'paxdesign-booking'), $label);

        if ($log_timeline) {
            self::add_message(
                $reference_id,
                'system',
                $message,
                'admin',
                $actor_user_id,
                array(
                    'event'               => 'status_change',
                    'from'                => $old_status,
                    'to'                  => $new_status,
                    'visible_to_customer' => $summary !== '' || $new_status === 'rejected',
                )
            );
        }

        $customer_id = (int) ($row['customer_user_id'] ?? 0);
        if ($notify_customer && $customer_id > 0) {
            self::queue_customer_notification(
                $row,
                $reference_id,
                $message,
                $new_status,
                sprintf(__('Cybercrime report %s updated', 'paxdesign-booking'), $reference_id)
            );
        }

        if (in_array($new_status, self::$closed_statuses, true)) {
            self::close_linked_chat_session($row);
        }

        return true;
    }

    /**
     * Store a rejection decision on the same CCS payload, then set status to rejected.
     *
     * @param string $reference_id
     * @param string $reason_key
     * @param string $explanation
     * @param int    $actor_user_id
     * @return bool|WP_Error
     */
    public static function apply_rejection($reference_id, $reason_key, $explanation = '', $actor_user_id = 0) {
        $reference_id = sanitize_text_field((string) $reference_id);
        $reason_key = sanitize_key((string) $reason_key);
        $explanation = sanitize_textarea_field((string) $explanation);
        $actor_user_id = absint($actor_user_id);

        if ($reference_id === '') {
            return new WP_Error('invalid', __('Report not found.', 'paxdesign-booking'));
        }
        $allowed = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::rejection_reason_keys()
            : array('unclear_document', 'incomplete_information', 'unverifiable', 'duplicate', 'out_of_scope', 'other');
        if (!in_array($reason_key, $allowed, true)) {
            return new WP_Error(
                'reason_required',
                class_exists('PAXdesign_Cybercrime_I18n')
                    ? PAXdesign_Cybercrime_I18n::t('rejectRequired')
                    : __('Please choose a rejection reason.', 'paxdesign-booking')
            );
        }
        if ($reason_key === 'other' && $explanation === '') {
            return new WP_Error(
                'reason_required',
                class_exists('PAXdesign_Cybercrime_I18n')
                    ? PAXdesign_Cybercrime_I18n::t('rejectRequired')
                    : __('Please choose a rejection reason.', 'paxdesign-booking')
            );
        }

        $row = self::get_report_row($reference_id);
        if (!$row) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'));
        }

        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }

        $admin_name = '';
        if ($actor_user_id > 0) {
            $user = get_user_by('id', $actor_user_id);
            if ($user instanceof WP_User) {
                $admin_name = trim((string) $user->display_name);
            }
        }

        $reason_i18n = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::rejection_reason_i18n($reason_key)
            : array('en' => $reason_key);
        $reason_text = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::rejection_reason_text($reason_key)
            : $reason_key;

        $now = current_time('mysql', true);
        $payload['rejection'] = array(
            'reason_key'     => $reason_key,
            'reason'         => $reason_text,
            'reason_i18n'    => $reason_i18n,
            'explanation'    => $explanation,
            'admin_user_id'  => $actor_user_id,
            'admin_name'     => $admin_name,
            'decided_at'     => $now,
            'reference_id'   => $reference_id,
            'decision'       => 'rejected',
        );

        global $wpdb;
        $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array(
                'payload'    => wp_json_encode($payload),
                'updated_at' => $now,
            ),
            array('reference_id' => $reference_id),
            array('%s', '%s'),
            array('%s')
        );

        $summary = $reason_text;
        if ($explanation !== '') {
            $summary .= "\n" . $explanation;
        }

        $current = self::normalize_workflow_status((string) ($row['status'] ?? ''));
        if ($current === 'rejected') {
            self::add_message(
                $reference_id,
                'system',
                $summary,
                'admin',
                $actor_user_id,
                array(
                    'event'               => 'rejection_reason',
                    'reason_key'          => $reason_key,
                    'visible_to_customer' => true,
                )
            );
            return true;
        }

        return self::update_status($reference_id, 'rejected', $actor_user_id, $summary, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @param string               $status
     * @return array<string, mixed>|null
     */
    public static function public_rejection($payload, $status = '', $lang = '') {
        $payload = is_array($payload) ? $payload : array();
        $rejection = is_array($payload['rejection'] ?? null) ? $payload['rejection'] : array();
        if (empty($rejection) && self::normalize_workflow_status($status) !== 'rejected') {
            return null;
        }
        if (empty($rejection)) {
            return null;
        }
        $reason_key = sanitize_key((string) ($rejection['reason_key'] ?? ''));
        $reason_i18n = is_array($rejection['reason_i18n'] ?? null) ? $rejection['reason_i18n'] : array();
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            foreach (array('ar', 'de', 'en') as $pack_lang) {
                if (empty($reason_i18n[$pack_lang])) {
                    $reason_i18n[$pack_lang] = $reason_key !== ''
                        ? PAXdesign_Cybercrime_I18n::rejection_reason_text($reason_key, $pack_lang)
                        : (string) ($rejection['reason'] ?? '');
                }
            }
        }
        if ($lang === '' && class_exists('PAXdesign_Cybercrime_I18n')) {
            $lang = PAXdesign_Cybercrime_I18n::customer_lang();
        }
        $reason = '';
        if ($lang !== '' && !empty($reason_i18n[$lang])) {
            $reason = (string) $reason_i18n[$lang];
        } elseif (!empty($reason_i18n['ar'])) {
            $reason = (string) $reason_i18n['ar'];
        } elseif (!empty($reason_i18n['en'])) {
            $reason = (string) $reason_i18n['en'];
        } elseif (!empty($rejection['reason'])) {
            $reason = (string) $rejection['reason'];
        }
        return array(
            'reason_key'    => $reason_key,
            'reason'        => $reason,
            'reason_i18n'   => array(
                'ar' => (string) ($reason_i18n['ar'] ?? $reason),
                'de' => (string) ($reason_i18n['de'] ?? $reason),
                'en' => (string) ($reason_i18n['en'] ?? $reason),
            ),
            'explanation'   => sanitize_textarea_field((string) ($rejection['explanation'] ?? '')),
            'admin_name'    => sanitize_text_field((string) ($rejection['admin_name'] ?? '')),
            'decided_at'    => sanitize_text_field((string) ($rejection['decided_at'] ?? '')),
            'reference_id'  => sanitize_text_field((string) ($rejection['reference_id'] ?? '')),
            'decision'      => 'rejected',
        );
    }

    /**
     * @param array<string, mixed> $rejection
     * @return string
     */
    public static function customer_rejection_next_action($rejection, $lang = '') {
        unset($rejection);
        return class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::t('decision.next_action', $lang)
            : __('No further action is required on this reference unless you start a new report for a new incident.', 'paxdesign-booking');
    }

    /**
     * Queue customer notification + email for shutdown so admin-ajax returns promptly.
     *
     * @param array<string, mixed> $row
     * @param string               $reference_id
     * @param string               $message
     * @param string               $status
     * @param string               $title
     */
    private static function queue_customer_notification($row, $reference_id, $message, $status, $title) {
        if (!is_array($row) || !class_exists('PAXdesign_Customer_Notifications')) {
            return;
        }
        $customer_id = (int) ($row['customer_user_id'] ?? 0);
        if ($customer_id <= 0) {
            return;
        }

        self::$deferred_customer_notifications[] = array(
            'row'          => $row,
            'reference_id' => $reference_id,
            'message'      => $message,
            'status'       => $status,
            'title'        => $title,
            'customer_id'  => $customer_id,
        );

        if (!has_action('shutdown', array(__CLASS__, 'flush_deferred_customer_notifications'))) {
            add_action('shutdown', array(__CLASS__, 'flush_deferred_customer_notifications'), 1);
        }
    }

    public static function flush_deferred_customer_notifications() {
        if (empty(self::$deferred_customer_notifications)) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        $pending = self::$deferred_customer_notifications;
        self::$deferred_customer_notifications = array();

        foreach ($pending as $item) {
            if (!is_array($item)) {
                continue;
            }
            $customer_id = (int) ($item['customer_id'] ?? 0);
            if ($customer_id <= 0 || !class_exists('PAXdesign_Customer_Notifications')) {
                continue;
            }
            $row = is_array($item['row'] ?? null) ? $item['row'] : array();
            $reference_id = sanitize_text_field((string) ($item['reference_id'] ?? ''));
            $message = (string) ($item['message'] ?? '');
            $status = sanitize_key((string) ($item['status'] ?? ''));
            $lang = class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::customer_lang($customer_id)
                : 'ar';
            $copy = class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::notification_copy($reference_id, $status, $lang)
                : array(
                    'title' => (string) ($item['title'] ?? ''),
                    'body'  => (string) ($item['message'] ?? ''),
                    'email_subject' => '',
                    'email_body' => '',
                );
            $title = sanitize_text_field((string) ($copy['title'] ?? ''));
            $message = (string) ($copy['body'] ?? '');

            PAXdesign_Customer_Notifications::notify_user(
                $customer_id,
                'security',
                $title,
                $message,
                'cybercrime',
                $reference_id,
                '/cybercrime/' . $reference_id
            );
            self::email_customer_update($row, $reference_id, $message, $status, $lang, $copy);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $reference_id
     * @param string               $message
     * @param string               $status
     */
    private static function email_customer_update($row, $reference_id, $message, $status, $lang = 'ar', $copy = array()) {
        $email = sanitize_email((string) ($row['reporter_email'] ?? ''));
        if (!is_email($email)) {
            return;
        }
        $subject = is_array($copy) && !empty($copy['email_subject'])
            ? (string) $copy['email_subject']
            : sprintf('[Cybercrime Report %s] %s', $reference_id, self::status_label($status, $lang));
        $body = is_array($copy) && !empty($copy['email_body'])
            ? (string) $copy['email_body'] . "\n\n" . $message . "\n"
            : $message . "\n";
        $view = home_url('/cybercrime-support/?ref=' . rawurlencode($reference_id));
        $body .= "\n" . $view . "\n";
        wp_mail($email, $subject, $body, array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: cybercrime+' . rawurlencode($reference_id) . '@paxdesign.at',
        ));
    }

    /**
     * Record initial submission in ticket timeline.
     *
     * @param string               $reference_id
     * @param int                  $user_id
     * @param array<string, mixed> $parsed
     * @param string               $chat_session_id
     */
    public static function record_submission($reference_id, $user_id, $parsed, $chat_session_id = '') {
        global $wpdb;
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '') {
            return;
        }

        if ($chat_session_id !== '') {
            $wpdb->update(
                PAXdesign_Cybercrime_Intake::table_name(),
                array('chat_session_id' => sanitize_text_field($chat_session_id)),
                array('reference_id' => $reference_id),
                array('%s'),
                array('%s')
            );
        }

        $summary = sprintf(
            __('Report submitted — %s. Reference %s.', 'paxdesign-booking'),
            PAXdesign_Cybercrime_Intake::category_label((string) ($parsed['category'] ?? '')),
            $reference_id
        );
        self::add_message($reference_id, 'system', $summary, 'portal', $user_id, array('event' => 'submitted'));

        if (!empty($parsed['description'])) {
            self::add_message(
                $reference_id,
                'customer',
                (string) $parsed['description'],
                'portal',
                $user_id,
                array('event' => 'initial_description')
            );
        }
    }

    /**
     * @param string $reference_id
     * @param string $session_id
     * @param string $role user|assistant|admin
     * @param string $content
     * @param int    $message_id
     */
    public static function maybe_log_chat_message($reference_id, $session_id, $role, $content, $message_id = 0) {
        $reference_id = sanitize_text_field((string) $reference_id);
        $content = trim((string) $content);
        if ($reference_id === '' || $content === '') {
            return;
        }

        $author_type = 'customer';
        if ($role === 'assistant') {
            $author_type = 'ai';
        } elseif ($role === 'admin') {
            $author_type = 'staff';
        } elseif ($role !== 'user') {
            return;
        }

        $external_id = $message_id > 0 ? 'chat-' . (int) $message_id : '';
        if ($external_id !== '') {
            global $wpdb;
            $exists = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . self::messages_table() . ' WHERE external_id = %s LIMIT 1',
                $external_id
            ));
            if ($exists > 0) {
                return;
            }
        }

        self::add_message(
            $reference_id,
            $author_type,
            $content,
            'chat',
            0,
            array('session_id' => sanitize_text_field((string) $session_id), 'chat_message_id' => (int) $message_id),
            '',
            $external_id
        );
    }

    /**
     * @param string $reference_id
     * @param string $body
     * @param int    $user_id
     * @return int|false|WP_Error
     */
    public static function add_customer_reply($reference_id, $body, $user_id) {
        $row = self::get_report_row($reference_id);
        if (!$row || !self::user_can_view_report($row, $user_id)) {
            return new WP_Error('forbidden', __('You cannot reply to this report.', 'paxdesign-booking'));
        }
        if (!self::is_active_status((string) ($row['status'] ?? ''))) {
            return new WP_Error('closed', __('This report is closed.', 'paxdesign-booking'));
        }

        $message_id = self::add_message($reference_id, 'customer', $body, 'portal', $user_id, array('event' => 'customer_reply'));
        if (!$message_id) {
            return new WP_Error('save_failed', __('Could not save your message.', 'paxdesign-booking'));
        }

        self::update_status($reference_id, 'in_review', $user_id, '', false, false);
        self::notify_staff_reply($row, $reference_id, $body);

        return $message_id;
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $reference_id
     * @param string               $body
     */
    private static function notify_staff_reply($row, $reference_id, $body) {
        $to = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
        $subject = sprintf('[Cybercrime Reply] %s', $reference_id);
        $text = "Customer reply on cybercrime report {$reference_id}\n\n"
            . 'From: ' . ($row['reporter_name'] ?? '') . ' <' . ($row['reporter_email'] ?? '') . ">\n\n"
            . $body . "\n\n"
            . 'Open the exact case: ' . PAXdesign_Cybercrime_Intake::admin_case_url($reference_id) . "\n";
        wp_mail($to, $subject, $text, array('Content-Type: text/plain; charset=UTF-8'));
    }

    /**
     * @param string $reference_id
     * @param string $body
     * @param int    $staff_user_id
     * @return int|false|WP_Error
     */
    public static function add_staff_reply($reference_id, $body, $staff_user_id, $new_status = '') {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Insufficient permissions.', 'paxdesign-booking'));
        }
        $row = self::get_report_row($reference_id);
        if (!$row) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'));
        }

        $message_id = self::add_message($reference_id, 'staff', $body, 'admin', $staff_user_id, array('event' => 'staff_reply'));
        if (!$message_id) {
            return new WP_Error('save_failed', __('Could not save staff reply.', 'paxdesign-booking'));
        }

        $status = sanitize_key((string) $new_status);
        if ($status === 'rejected') {
            $current = self::normalize_workflow_status((string) ($row['status'] ?? ''));
            $status = $current !== '' ? $current : 'in_review';
        }
        if ($status === '') {
            $status = self::is_active_status((string) ($row['status'] ?? '')) ? 'waiting_for_customer' : (string) ($row['status'] ?? '');
        }
        self::update_status($reference_id, $status, $staff_user_id, '', false, false);

        $customer_id = (int) ($row['customer_user_id'] ?? 0);
        if ($customer_id > 0) {
            self::queue_customer_notification(
                $row,
                $reference_id,
                $body,
                $status,
                sprintf(__('New reply on cybercrime report %s', 'paxdesign-booking'), $reference_id)
            );
        }

        return $message_id;
    }

    /**
     * @param string $reference_id
     * @param string $body
     * @param int    $staff_user_id
     * @return int|false|WP_Error
     */
    public static function add_internal_note($reference_id, $body, $staff_user_id) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Insufficient permissions.', 'paxdesign-booking'));
        }
        $body = trim((string) $body);
        if ($body === '') {
            return new WP_Error('message_required', __('Message is required.', 'paxdesign-booking'));
        }
        $row = self::get_report_row($reference_id);
        if (!$row) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'));
        }

        $message_id = self::add_message(
            $reference_id,
            'staff',
            $body,
            'admin',
            $staff_user_id,
            array(
                'event'         => 'internal_note',
                'internal_only' => true,
            )
        );
        if (!$message_id) {
            return new WP_Error('save_failed', __('Could not save internal note.', 'paxdesign-booking'));
        }

        return $message_id;
    }

    /**
     * @param string $reference_id
     * @return array<string, mixed>|null
     */
    public static function get_report_for_admin($reference_id) {
        $row = self::get_report_row($reference_id);
        if (!$row) {
            return null;
        }
        return self::format_report_row($row, true, 'admin');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public static function handle_inbound_email($payload) {
        $from = sanitize_email((string) ($payload['from'] ?? ''));
        $subject = sanitize_text_field((string) ($payload['subject'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $external_id = sanitize_text_field((string) ($payload['message_id'] ?? ''));

        if ($body === '') {
            return new WP_Error('empty_body', __('Empty email body.', 'paxdesign-booking'));
        }

        $reference_id = self::extract_reference_from_text($subject . ' ' . $body);
        if ($reference_id === '') {
            return new WP_Error('no_reference', __('No report reference found in email.', 'paxdesign-booking'));
        }

        $row = self::get_report_row($reference_id);
        if (!$row) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'));
        }

        if ($from !== '' && sanitize_email((string) ($row['reporter_email'] ?? '')) !== $from) {
            return new WP_Error('forbidden', __('Sender email does not match report.', 'paxdesign-booking'));
        }

        if (!self::is_active_status((string) ($row['status'] ?? ''))) {
            return new WP_Error('closed', __('Report is closed.', 'paxdesign-booking'));
        }

        $user_id = (int) ($row['customer_user_id'] ?? 0);
        $message_id = self::add_message(
            $reference_id,
            'customer',
            $body,
            'email',
            $user_id,
            array('event' => 'email_reply', 'subject' => $subject),
            $subject,
            $external_id
        );
        if (!$message_id) {
            return new WP_Error('save_failed', __('Could not store email reply.', 'paxdesign-booking'));
        }

        self::update_status($reference_id, 'in_review', $user_id, '', false, false);
        self::notify_staff_reply($row, $reference_id, $body);

        return array('reference_id' => $reference_id, 'message_id' => $message_id);
    }

    /**
     * @param string $text
     * @return string
     */
    public static function extract_reference_from_text($text) {
        if (preg_match('/\b(CCS-\d{8}-[A-F0-9]{8})\b/i', (string) $text, $matches)) {
            return strtoupper($matches[1]);
        }
        return '';
    }

    /**
     * Build AI context block from ticket timeline.
     *
     * @param string $reference_id
     * @return string
     */
    public static function build_ai_context_block($reference_id) {
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '') {
            return '';
        }
        $report = self::get_report_row($reference_id);
        if (!$report) {
            return '';
        }

        $detail = self::format_report_row($report, true);
        $lines = array(
            '## Cybercrime ticket context (authoritative — reference ' . $reference_id . ')',
            '- LIVE STATUS (authoritative right now — do not use any older status from chat history): ' . ($detail['status_label'] ?? '') . ' (' . ($detail['status'] ?? '') . ')',
            '- Customer-facing status: ' . ($detail['customer_status'] ?? ''),
            '- Category: ' . ($detail['category_label'] ?? ''),
            '- Submitted: ' . ($detail['created_at'] ?? ''),
            '- Last update: ' . ($detail['updated_at'] ?? ''),
            '- Reason/summary: ' . wp_html_excerpt((string) ($detail['description'] ?? ''), 400, '…'),
            '- Next action for the customer: ' . (string) ($detail['next_action'] ?? ''),
            '- Draft / collecting: ' . (!empty($detail['is_draft']) ? 'yes — not yet submitted for administrator review' : 'no'),
            '- Missing fields (ask ONLY these if still empty): ' . (empty($detail['missing_fields']) ? 'none' : implode(', ', (array) $detail['missing_fields'])),
            '- Website workflow (source of truth): Identity → Incident → Evidence → Review / Submission. Chat fills and submits this same form.',
            '- Needs administrator review: ' . (!empty($detail['needs_human_review']) ? 'yes' : 'no'),
            '- Stay on this same reference for the entire workflow: submission → document checks → corrections → administrator review → status changes → customer communication → final outcome.',
            '- Never ask the customer to restart or re-explain facts already listed here.',
        );

        $rejection = is_array($detail['rejection'] ?? null) ? $detail['rejection'] : array();
        if (($detail['status'] ?? '') === 'rejected' && !empty($rejection)) {
            $lines[] = '- Rejection decision (live): reason=' . (string) ($rejection['reason'] ?? $rejection['reason_key'] ?? '');
            if (!empty($rejection['explanation'])) {
                $lines[] = '- Rejection explanation: ' . wp_html_excerpt((string) $rejection['explanation'], 400, '…');
            }
            $lines[] = '- If the customer asks why the case was rejected or what the current status is, answer from this live decision. Do not say the case is still under review.';
        }

        $original = is_array($detail['original_request'] ?? null) ? $detail['original_request'] : array();
        if (!empty($original)) {
            $lines[] = '- Original request on this same reference:';
            $lines[] = '    reporter: ' . (string) ($original['reporter_name'] ?? '');
            $lines[] = '    platforms: ' . (string) ($original['platforms'] ?? '');
            $lines[] = '    incident: ' . (string) ($original['incident_at'] ?? '');
            if (!empty($original['financial_loss'])) {
                $lines[] = '    reported loss: ' . (string) $original['financial_loss'] . ' ' . (string) ($original['financial_currency'] ?? '');
            }
        }

        $checks = is_array($detail['checks'] ?? null) ? $detail['checks'] : array();
        $lines[] = '- Document/evidence checks are PRELIMINARY QUALITY CHECKS, not legal verification.';
        if (!empty($checks['disclaimer'])) {
            $lines[] = '    disclaimer: ' . (string) $checks['disclaimer'];
        }
        if (!empty($checks['files']) && is_array($checks['files'])) {
            foreach (array_slice($checks['files'], 0, 16) as $file) {
                if (!is_array($file)) {
                    continue;
                }
                $lines[] = '    • ' . (string) ($file['filename'] ?? 'file')
                    . ' [' . (string) ($file['field'] ?? '') . '] status='
                    . (string) ($file['customer_status'] ?? $file['status'] ?? '');
            }
        }
        foreach ((array) ($detail['correction_required'] ?? array()) as $fix) {
            $lines[] = '    correction needed: ' . (string) $fix;
        }

        if (!empty($detail['attachments']) && is_array($detail['attachments'])) {
            $names = array();
            foreach ($detail['attachments'] as $file) {
                if (is_array($file) && !empty($file['name'])) {
                    $names[] = (string) $file['name'];
                }
            }
            if (!empty($names)) {
                $lines[] = '- Attachments: ' . implode(', ', array_slice($names, 0, 12));
            }
        }

        $lines[] = '- Official ticket timeline (Support ↔ Customer only — never mix with AI assistant chat):';
        foreach (($detail['timeline'] ?? array()) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $lines[] = '  • [' . ($entry['created_at'] ?? '') . '] '
                . ($entry['sender_label'] ?? $entry['author_type'] ?? '')
                . ': ' . wp_html_excerpt((string) ($entry['body'] ?? ''), 260, '…');
        }

        $lines[] = '- AI assistant chat is a separate channel and is NOT part of this official timeline.';
        $lines[] = '- Never invent reference numbers, staff messages, or status changes not listed above.';
        if (class_exists('PAXdesign_Cybercrime_AI_Operations')) {
            $op_block = PAXdesign_Cybercrime_AI_Operations::prompt_state_block($report);
            if ($op_block !== '') {
                $lines[] = '';
                $lines[] = $op_block;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Compact case snapshot for live customer/chat polling (no timeline).
     *
     * @param string $session_id
     * @param int    $user_id
     * @return array<string, mixed>|null
     */
    public static function public_case_sync_for_session($session_id, $user_id = 0) {
        $session_id = sanitize_text_field((string) $session_id);
        $user_id = absint($user_id);
        if ($user_id <= 0 || $session_id === '') {
            return null;
        }
        $reference = self::get_reference_for_session($session_id);
        if ($reference === '') {
            return null;
        }
        $row = self::get_report_row($reference);
        if (!is_array($row)) {
            return null;
        }
        if ($user_id > 0 && !self::user_can_view_report($row, $user_id)) {
            return null;
        }
        $report = self::format_report_row($row, false);
        if (!is_array($report) || empty($report['reference_id'])) {
            return null;
        }
        return class_exists('PAXdesign_Cybercrime_AI_Case')
            ? PAXdesign_Cybercrime_AI_Case::public_case_sync_payload($report)
            : $report;
    }

    /**
     * @param string $session_id
     * @return string
     */
    public static function get_reference_for_session($session_id) {
        global $wpdb;
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return '';
        }
        $reference = $wpdb->get_var($wpdb->prepare(
            'SELECT reference_id FROM ' . PAXdesign_Cybercrime_Intake::table_name() . ' WHERE chat_session_id = %s LIMIT 1',
            $session_id
        ));
        return is_string($reference) ? sanitize_text_field($reference) : '';
    }

    /**
     * Close the cybercrime report linked to a live-chat session (if still active).
     *
     * @param string $session_id
     * @param int    $actor_user_id
     * @return bool|WP_Error
     */
    public static function close_report_for_chat_session($session_id, $actor_user_id = 0) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return true;
        }

        $reference_id = self::get_reference_for_session($session_id);
        if ($reference_id === '') {
            return true;
        }

        $row = self::get_report_row($reference_id);
        if (!$row || !self::is_active_status((string) ($row['status'] ?? ''))) {
            return true;
        }

        return self::update_status(
            $reference_id,
            'closed',
            $actor_user_id,
            __('Ticket closed with chat.', 'paxdesign-booking'),
            true,
            true
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function close_linked_chat_session($row) {
        if (!is_array($row) || !class_exists('PAXdesign_Chat_Live')) {
            return;
        }
        $session_id = sanitize_text_field((string) ($row['chat_session_id'] ?? ''));
        if ($session_id === '') {
            return;
        }

        $live = PAXdesign_Chat_Live::get_instance();
        if (!method_exists($live, 'admin_close')) {
            return;
        }

        $result = $live->admin_close($session_id);
        if (is_wp_error($result) && $result->get_error_code() !== 'not_found') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PAXdesign Cybercrime] Linked chat close failed: ' . $result->get_error_message());
            }
        }
    }

    /**
     * @param string $session_id
     * @param string $role
     * @param string $content
     * @param int    $message_id
     */
    public static function on_chat_message($session_id, $role, $content, $message_id = 0) {
        unset($role, $content, $message_id);
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return;
        }

        // Link chat session for AI context only — never store widget messages in the official ticket.
        $reference_id = self::get_reference_for_session($session_id);
        if ($reference_id === '') {
            $row = self::get_active_report_row_for_session_context($session_id);
            if ($row) {
                $reference_id = (string) ($row['reference_id'] ?? '');
                if ($reference_id !== '' && empty($row['chat_session_id'])) {
                    global $wpdb;
                    $wpdb->update(
                        PAXdesign_Cybercrime_Intake::table_name(),
                        array('chat_session_id' => $session_id),
                        array('reference_id' => $reference_id),
                        array('%s'),
                        array('%s')
                    );
                }
            }
        }
    }

    /**
     * Link chat to active report for logged-in user when opened from cybercrime page.
     *
     * @param string $session_id
     * @return array<string, mixed>|null
     */
    private static function get_active_report_row_for_session_context($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '' || !is_user_logged_in()) {
            return null;
        }

        $key = md5($session_id);
        $page_context = get_transient('pax_chat_page_ctx_' . $key);
        if ($page_context !== 'cybercrime-support') {
            return null;
        }

        $focus = get_transient('pax_chat_page_ref_' . $key);
        if (is_string($focus) && $focus !== '') {
            return self::get_report_row($focus);
        }

        return self::get_report_row_for_user_active(get_current_user_id());
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>|null
     */
    private static function get_report_row_for_user_active($user_id) {
        $report = self::get_active_report_for_user($user_id);
        if (!$report || empty($report['reference_id'])) {
            return null;
        }
        return self::get_report_row((string) $report['reference_id']);
    }

    /**
     * @param string               $reference_id
     * @param string               $audience staff|customer
     * @param array<string, mixed>|null $row
     * @return int
     */
    public static function count_unread_for_audience($reference_id, $audience, $row = null) {
        $reference_id = sanitize_text_field((string) $reference_id);
        $audience = sanitize_key((string) $audience);
        if ($reference_id === '' || !in_array($audience, array('staff', 'customer'), true)) {
            return 0;
        }
        if (!is_array($row)) {
            $row = self::get_report_row($reference_id);
        }
        if (!is_array($row)) {
            return 0;
        }

        $cursor = $audience === 'staff'
            ? (int) ($row['staff_last_read_message_id'] ?? 0)
            : (int) ($row['customer_last_read_message_id'] ?? 0);

        $count = 0;
        foreach (self::list_messages($reference_id, 500) as $entry) {
            if (!is_array($entry) || (int) ($entry['id'] ?? 0) <= $cursor) {
                continue;
            }
            if ($audience === 'staff' && self::is_staff_unread_message($entry)) {
                $count++;
            } elseif ($audience === 'customer' && self::is_customer_visible_timeline_entry($entry)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param string $reference_id
     * @param string $audience staff|customer
     * @param int    $user_id
     * @return bool
     */
    public static function mark_read_for_audience($reference_id, $audience, $user_id = 0) {
        $reference_id = sanitize_text_field((string) $reference_id);
        $audience = sanitize_key((string) $audience);
        if ($reference_id === '' || !in_array($audience, array('staff', 'customer'), true)) {
            return false;
        }

        $row = self::get_report_row($reference_id);
        if (!is_array($row)) {
            return false;
        }

        $max_id = $audience === 'staff'
            ? (int) ($row['staff_last_read_message_id'] ?? 0)
            : (int) ($row['customer_last_read_message_id'] ?? 0);

        foreach (self::list_messages($reference_id, 500) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = (int) ($entry['id'] ?? 0);
            if ($audience === 'staff' && self::is_staff_unread_message($entry)) {
                $max_id = max($max_id, $id);
            } elseif ($audience === 'customer' && self::is_customer_visible_timeline_entry($entry)) {
                $max_id = max($max_id, $id);
            }
        }

        $column = $audience === 'staff' ? 'staff_last_read_message_id' : 'customer_last_read_message_id';
        $current = (int) ($row[$column] ?? 0);
        if ($max_id <= $current) {
            if ($audience === 'customer') {
                self::mark_customer_notifications_read($row, $user_id);
            }
            return true;
        }

        global $wpdb;
        $updated = $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array($column => $max_id),
            array('reference_id' => $reference_id),
            array('%d'),
            array('%s')
        );

        if ($audience === 'customer') {
            self::mark_customer_notifications_read($row, $user_id);
        }

        return $updated !== false;
    }

    /**
     * @param array<string, mixed> $row
     * @param int                  $user_id
     */
    private static function mark_customer_notifications_read($row, $user_id) {
        if (!class_exists('PAXdesign_Customer_Notifications')) {
            return;
        }
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            $user_id = (int) ($row['customer_user_id'] ?? 0);
        }
        if ($user_id <= 0) {
            return;
        }
        $reference_id = sanitize_text_field((string) ($row['reference_id'] ?? ''));
        if ($reference_id === '') {
            return;
        }
        PAXdesign_Customer_Notifications::mark_read_for_entity($user_id, 'cybercrime', $reference_id);
    }

    /**
     * @param int $limit
     * @return array{total: int, reports: array<int, array{reference_id: string, unread_count: int}>, first_reference_id: string, target_url: string}
     */
    public static function staff_unread_summary($limit = 50) {
        $reports = self::list_reports_for_admin($limit);
        $out = array();
        $total = 0;
        foreach ($reports as $report) {
            if (!is_array($report)) {
                continue;
            }
            $count = (int) ($report['unread_count'] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $total += $count;
            $out[] = array(
                'reference_id' => (string) ($report['reference_id'] ?? ''),
                'unread_count' => $count,
            );
        }
        $first_ref = !empty($out) ? (string) ($out[0]['reference_id'] ?? '') : '';
        $portal_page = 'paxdesign-customer-portal';
        $target_url = $first_ref !== ''
            ? admin_url('admin.php?page=' . $portal_page . '&tab=cybercrime&reference=' . rawurlencode($first_ref))
            : admin_url('admin.php?page=' . $portal_page . '&tab=cybercrime');

        return array(
            'total'               => $total,
            'reports'             => $out,
            'first_reference_id'  => $first_ref,
            'target_url'          => $target_url,
        );
    }

    /**
     * @param int $user_id
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public static function list_reports_for_user($user_id, $limit = 30) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return array();
        }

        global $wpdb;
        $table = PAXdesign_Cybercrime_Intake::table_name();
        $user = get_user_by('id', $user_id);
        $email = ($user instanceof WP_User) ? sanitize_email($user->user_email) : '';
        $lim = max(1, min(50, (int) $limit));

        if ($email !== '') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table
                 WHERE customer_user_id = %d OR (customer_user_id = 0 AND reporter_email = %s)
                 ORDER BY updated_at DESC
                 LIMIT %d",
                $user_id,
                $email,
                $lim
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE customer_user_id = %d ORDER BY updated_at DESC LIMIT %d",
                $user_id,
                $lim
            ), ARRAY_A);
        }

        if (!is_array($rows)) {
            return array();
        }

        $out = array();
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::format_report_row($row, false, 'customer', 'customer');
            }
        }
        return $out;
    }

    /**
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public static function list_reports_for_admin($limit = 50) {
        global $wpdb;
        $table = PAXdesign_Cybercrime_Intake::table_name();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY updated_at DESC LIMIT %d",
            max(1, min(200, (int) $limit))
        ), ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }
        $out = array();
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::format_report_row($row, false, 'admin', 'staff');
            }
        }
        return $out;
    }

    public static function ajax_customer_reply() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => self::customer_i18n('error.login', __('Please sign in.', 'paxdesign-booking')), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference'] ?? ''));
        $body = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        if ($body === '') {
            wp_send_json_error(array('message' => __('Message is required.', 'paxdesign-booking'), 'code' => 'message_required'), 400);
        }

        $result = self::add_customer_reply($reference, $body, get_current_user_id());
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        $report = self::get_report_for_user($reference, get_current_user_id());
        wp_send_json_success(array('message_id' => $result, 'report' => $report));
    }

    public static function ajax_customer_resubmit() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => self::customer_i18n('error.login', __('Please sign in.', 'paxdesign-booking')), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference'] ?? ''));
        $note = sanitize_textarea_field(wp_unslash($_POST['message'] ?? $_POST['note'] ?? ''));
        $result = self::append_customer_evidence($reference, $_FILES, get_current_user_id(), $note);
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $payload = is_array($data) ? $data : array();
            $payload['message'] = $result->get_error_message();
            $payload['code'] = $result->get_error_code();
            wp_send_json_error($payload, 400);
        }

        wp_send_json_success($result);
    }

    /**
     * Append corrected documents/evidence to the same reference without restarting the case.
     *
     * @param string               $reference_id
     * @param array<string, mixed> $files
     * @param int                  $user_id
     * @param string               $note
     * @return array<string, mixed>|WP_Error
     */
    public static function append_customer_evidence($reference_id, $files, $user_id, $note = '') {
        $reference_id = sanitize_text_field((string) $reference_id);
        $user_id = absint($user_id);
        $row = self::get_report_row($reference_id);
        if (!$row || !self::user_can_view_report($row, $user_id)) {
            return new WP_Error('forbidden', __('You cannot update this report.', 'paxdesign-booking'));
        }
        if (!self::is_active_status((string) ($row['status'] ?? ''))) {
            return new WP_Error('closed', __('This report is closed.', 'paxdesign-booking'));
        }

        if (!class_exists('PAXdesign_Cybercrime_Intake')) {
            return new WP_Error('unavailable', __('Upload is temporarily unavailable.', 'paxdesign-booking'));
        }

        $uploads = PAXdesign_Cybercrime_Intake::save_uploaded_files(is_array($files) ? $files : array());
        if (is_wp_error($uploads)) {
            return $uploads;
        }
        if (empty($uploads) && trim($note) === '') {
            return new WP_Error('empty_update', __('Please attach a file or add a message.', 'paxdesign-booking'));
        }

        $existing = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($existing)) {
            $existing = array();
        }
        $existing_hashes = array();
        foreach ($existing as $item) {
            if (is_array($item) && !empty($item['sha256'])) {
                $existing_hashes[] = (string) $item['sha256'];
            }
        }

        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }

        $check_context = array(
            'reporter_name'   => (string) ($row['reporter_name'] ?? ''),
            'email'           => (string) ($row['reporter_email'] ?? ''),
            'category'        => (string) ($row['category'] ?? ''),
            'existing_hashes' => $existing_hashes,
        );
        $new_checks = class_exists('PAXdesign_Cybercrime_Document_Checks')
            ? PAXdesign_Cybercrime_Document_Checks::evaluate_uploads($uploads, $check_context)
            : array();

        $accepted = array();
        $rejected = array();
        $file_checks = is_array($new_checks['files'] ?? null) ? $new_checks['files'] : array();
        foreach ($uploads as $index => $file) {
            $check = is_array($file_checks[$index] ?? null) ? $file_checks[$index] : array();
            if (($check['status'] ?? '') === 'fail') {
                $rejected[] = $file;
                continue;
            }
            $accepted[] = $file;
        }

        if (!empty($rejected)) {
            PAXdesign_Cybercrime_Intake::delete_stored_uploads($rejected);
        }

        if (empty($accepted) && !empty($uploads)) {
            $corrections = array_values((array) ($new_checks['customer_corrections'] ?? array()));
            return new WP_Error(
                'document_check_failed',
                !empty($corrections)
                    ? implode(' ', $corrections)
                    : __('The new files did not pass preliminary quality checks. Please correct them and try again on this same case.', 'paxdesign-booking'),
                array(
                    'corrections'     => $corrections,
                    'document_checks' => class_exists('PAXdesign_Cybercrime_Document_Checks')
                        ? PAXdesign_Cybercrime_Document_Checks::customer_view($new_checks)
                        : array(),
                )
            );
        }

        $merged = array_merge($existing, PAXdesign_Cybercrime_Intake::public_upload_records($accepted));
        $previous_checks = is_array($payload['document_checks'] ?? null) ? $payload['document_checks'] : array();
        $merged_files = array_merge(
            (array) ($previous_checks['files'] ?? array()),
            (array) ($new_checks['files'] ?? array())
        );
        $merged_summary = class_exists('PAXdesign_Cybercrime_Document_Checks')
            ? PAXdesign_Cybercrime_Document_Checks::summarize($merged_files, $check_context)
            : $new_checks;

        $guided = is_array($payload['guided_case'] ?? null) ? $payload['guided_case'] : array();
        $rounds = is_array($guided['correction_rounds'] ?? null) ? $guided['correction_rounds'] : array();
        $rounds[] = array(
            'at'      => gmdate('c'),
            'files'   => count($accepted),
            'note'    => $note,
            'checks'  => class_exists('PAXdesign_Cybercrime_Document_Checks')
                ? PAXdesign_Cybercrime_Document_Checks::customer_view($new_checks)
                : array(),
        );
        $guided['correction_rounds'] = $rounds;
        $guided['needs_human_review'] = !empty($merged_summary['needs_human_review']);
        $guided['next_action'] = (string) ($merged_summary['next_action'] ?? '');
        $payload['guided_case'] = $guided;
        $payload['document_checks'] = $merged_summary;

        $needs_human = !empty($merged_summary['needs_human_review']) ? 1 : 0;
        $now = current_time('mysql', true);
        global $wpdb;
        $update = array(
            'attachments'        => wp_json_encode($merged),
            'payload'            => wp_json_encode($payload),
            'updated_at'         => $now,
            'needs_human_review' => $needs_human,
        );
        $formats = array('%s', '%s', '%s', '%d');
        $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . PAXdesign_Cybercrime_Intake::table_name(), 0);
        if (!is_array($columns) || !in_array('needs_human_review', $columns, true)) {
            unset($update['needs_human_review']);
            $formats = array('%s', '%s', '%s');
        }
        $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            $update,
            array('reference_id' => $reference_id),
            $formats,
            array('%s')
        );

        $names = array();
        foreach ($accepted as $file) {
            $names[] = (string) ($file['original_name'] ?? $file['name'] ?? 'file');
        }
        $body = $note !== ''
            ? $note
            : sprintf(
                /* translators: %s: file names */
                __('Updated files submitted on this case: %s. Preliminary quality checks were run. This is not legal verification.', 'paxdesign-booking'),
                implode(', ', $names)
            );
        self::add_message(
            $reference_id,
            'customer',
            $body,
            'portal',
            $user_id,
            array(
                'event'          => 'customer_resubmit',
                'files'          => $names,
                'visible_to_customer' => true,
            )
        );

        $status = self::normalize_workflow_status((string) ($row['status'] ?? ''));
        if ($status === 'waiting_for_customer' || $status === 'submitted') {
            self::update_status($reference_id, 'in_review', $user_id, '', false, false);
        }

        self::notify_staff_reply($row, $reference_id, $body);

        if ($needs_human && class_exists('PAXdesign_Cybercrime_Admin_Reminders')) {
            do_action('paxdesign_cybercrime_needs_human_review', $reference_id, $merged_summary);
        }

        return array(
            'report'          => self::get_report_for_user($reference_id, $user_id),
            'document_checks' => class_exists('PAXdesign_Cybercrime_Document_Checks')
                ? PAXdesign_Cybercrime_Document_Checks::customer_view($new_checks)
                : array(),
            'accepted_count'  => count($accepted),
            'rejected_count'  => count($rejected),
        );
    }

    public static function admin_update_status() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'paxdesign-booking'));
        }
        check_admin_referer('paxdesign_cybercrime_update_status');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
        $summary = sanitize_textarea_field(wp_unslash($_POST['summary'] ?? ''));

        $result = self::update_status($reference, $status, get_current_user_id(), $summary, true);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        wp_safe_redirect(add_query_arg(array(
            'page'     => PAXdesign_Customer_Admin::MENU_SLUG,
            'tab'      => 'cybercrime',
            'reference'=> $reference,
            'saved'    => '1',
        ), admin_url('admin.php')));
        exit;
    }

    public static function admin_staff_reply() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'paxdesign-booking'));
        }
        check_admin_referer('paxdesign_cybercrime_staff_reply');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        $body = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));

        $result = self::add_staff_reply($reference, $body, get_current_user_id(), $status);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        wp_safe_redirect(add_query_arg(array(
            'page'     => PAXdesign_Customer_Admin::MENU_SLUG,
            'tab'      => 'cybercrime',
            'reference'=> $reference,
            'saved'    => '1',
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * @param string $key
     * @param string $fallback
     * @return string
     */
    private static function customer_i18n($key, $fallback = '') {
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            return PAXdesign_Cybercrime_I18n::t($key, PAXdesign_Cybercrime_I18n::customer_lang());
        }
        return $fallback !== '' ? $fallback : $key;
    }

    private static function admin_i18n($key, $fallback = '') {
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            return PAXdesign_Cybercrime_I18n::t($key);
        }
        return $fallback !== '' ? $fallback : $key;
    }

    public static function ajax_admin_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => self::admin_i18n('error.permissions', __('Insufficient permissions.', 'paxdesign-booking'))), 403);
        }
        check_ajax_referer(self::ADMIN_NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));

        if ($status === 'rejected') {
            $reason_key = sanitize_key(wp_unslash($_POST['reason_key'] ?? ''));
            $explanation = sanitize_textarea_field(wp_unslash($_POST['explanation'] ?? ''));
            $result = self::apply_rejection($reference, $reason_key, $explanation, get_current_user_id());
        } else {
            $result = self::update_status($reference, $status, get_current_user_id(), '', true);
        }
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        $report = self::get_report_for_admin($reference);
        if (!$report) {
            wp_send_json_error(array('message' => self::admin_i18n('error.not_found', __('Report not found.', 'paxdesign-booking'))), 404);
        }

        wp_send_json_success(array(
            'report'  => $report,
            'message' => self::admin_i18n('statusSaved', __('Status saved.', 'paxdesign-booking')),
        ));
    }

    public static function ajax_admin_reply() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => self::admin_i18n('error.permissions', __('Insufficient permissions.', 'paxdesign-booking'))), 403);
        }
        check_ajax_referer(self::ADMIN_NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        $body = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));

        if ($body === '') {
            wp_send_json_error(array('message' => self::admin_i18n('error.message_required', __('Message is required.', 'paxdesign-booking'))), 400);
        }

        $result = self::add_staff_reply($reference, $body, get_current_user_id(), $status);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        $report = self::get_report_for_admin($reference);
        if (!$report) {
            wp_send_json_error(array('message' => self::admin_i18n('error.not_found', __('Report not found.', 'paxdesign-booking'))), 404);
        }

        wp_send_json_success(array(
            'report'  => $report,
            'message' => self::admin_i18n('replySent', __('Reply sent to customer.', 'paxdesign-booking')),
        ));
    }

    public static function ajax_admin_internal_note() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => self::admin_i18n('error.permissions', __('Insufficient permissions.', 'paxdesign-booking'))), 403);
        }
        check_ajax_referer(self::ADMIN_NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        $body = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        $result = self::add_internal_note($reference, $body, get_current_user_id());
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        $report = self::get_report_for_admin($reference);
        if (!$report) {
            wp_send_json_error(array('message' => self::admin_i18n('error.not_found', __('Report not found.', 'paxdesign-booking'))), 404);
        }

        wp_send_json_success(array(
            'report'     => $report,
            'message_id' => $result,
            'message'    => self::admin_i18n('noteAdded', __('Internal note added.', 'paxdesign-booking')),
        ));
    }

    public static function ajax_admin_unread() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'paxdesign-booking')), 403);
        }
        check_ajax_referer(self::ADMIN_NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        if ($reference !== '') {
            $report = self::get_report_for_admin($reference);
            if (!$report) {
                wp_send_json_error(array('message' => self::admin_i18n('error.not_found', __('Report not found.', 'paxdesign-booking'))), 404);
            }
            wp_send_json_success(array(
                'report'  => $report,
                'summary' => self::staff_unread_summary(50),
            ));
        }

        wp_send_json_success(array(
            'summary' => self::staff_unread_summary(50),
        ));
    }

    public static function ajax_admin_mark_read() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'paxdesign-booking')), 403);
        }
        check_ajax_referer(self::ADMIN_NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        if ($reference === '') {
            wp_send_json_error(array('message' => self::admin_i18n('error.not_found', __('Report not found.', 'paxdesign-booking'))), 404);
        }

        $row = self::get_report_row($reference);
        if (!$row) {
            wp_send_json_error(array('message' => self::admin_i18n('error.not_found', __('Report not found.', 'paxdesign-booking'))), 404);
        }

        self::mark_read_for_audience($reference, 'staff', get_current_user_id());

        wp_send_json_success(array(
            'summary' => self::staff_unread_summary(50),
            'report'  => self::get_report_for_admin($reference),
        ));
    }

    public static function ajax_mark_read() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please sign in.', 'paxdesign-booking')), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? $_POST['reference'] ?? ''));
        $row = self::get_report_row($reference);
        if (!$row || !self::user_can_view_report($row, get_current_user_id())) {
            wp_send_json_error(array('message' => self::customer_i18n('error.not_found', __('Report not found.', 'paxdesign-booking'))), 404);
        }

        self::mark_read_for_audience($reference, 'customer', get_current_user_id());
        $report = self::get_report_for_user($reference, get_current_user_id());

        wp_send_json_success(array(
            'unread_count' => 0,
            'report'       => $report,
        ));
    }

    private static function send_customer_nocache_headers() {
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        if (!headers_sent()) {
            header('Cache-Control: no-store, private, max-age=0, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    public static function ajax_report_list() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => self::customer_i18n('error.login', __('Please sign in.', 'paxdesign-booking')), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');
        self::send_customer_nocache_headers();

        $reports = self::list_reports_for_user(get_current_user_id(), 30);
        $active = null;
        $history = array();
        foreach ($reports as $report) {
            if (!is_array($report)) {
                continue;
            }
            if (!empty($report['is_active'])) {
                if ($active === null) {
                    $active = $report;
                }
            } else {
                $history[] = $report;
            }
        }

        wp_send_json_success(array(
            'reports' => $reports,
            'active'  => $active,
            'history' => $history,
        ));
    }

    public static function ajax_active_report() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => self::customer_i18n('error.login', __('Please sign in.', 'paxdesign-booking')), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');
        self::send_customer_nocache_headers();

        $report = self::get_active_report_for_user(get_current_user_id());
        if (!$report) {
            wp_send_json_success(array('active' => false));
        }
        wp_send_json_success(array('active' => true, 'report' => $report));
    }

    public static function ajax_report_detail() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => self::customer_i18n('error.login', __('Please sign in.', 'paxdesign-booking')), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');
        self::send_customer_nocache_headers();

        $reference = sanitize_text_field(wp_unslash($_GET['reference'] ?? $_POST['reference'] ?? ''));
        $report = self::get_report_for_user($reference, get_current_user_id());
        if (!$report) {
            wp_send_json_error(array('message' => self::customer_i18n('error.not_found', __('Report not found.', 'paxdesign-booking'))), 404);
        }
        wp_send_json_success(array('report' => $report));
    }

    public static function register_rest_routes() {
        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/cybercrime/active', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'rest_active_report'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/cybercrime/reports', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'rest_list_reports'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array('PAXdesign_Cybercrime_Intake', 'rest_submit_report'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
            ),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/cybercrime/reports/(?P<reference>[A-Z0-9-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'rest_get_report'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/cybercrime/reports/(?P<reference>[A-Z0-9-]+)/reply', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'rest_customer_reply'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/cybercrime/reports/(?P<reference>[A-Z0-9-]+)/read', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'rest_mark_read'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/webhooks/cybercrime/email', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'rest_inbound_email'),
            'permission_callback' => array(__CLASS__, 'verify_email_webhook'),
        ));
    }

    public static function rest_active_report(WP_REST_Request $request) {
        unset($request);
        $report = self::get_active_report_for_user(PAXdesign_Customer_Auth::current_user_id());
        return rest_ensure_response(array(
            'active' => !empty($report),
            'report' => $report,
        ));
    }

    public static function rest_list_reports(WP_REST_Request $request) {
        unset($request);
        $reports = self::list_reports_for_user(PAXdesign_Customer_Auth::current_user_id(), 30);
        $active = null;
        $history = array();
        foreach ($reports as $report) {
            if (!is_array($report)) {
                continue;
            }
            if (!empty($report['is_active'])) {
                if ($active === null) {
                    $active = $report;
                }
            } else {
                $history[] = $report;
            }
        }
        return rest_ensure_response(array(
            'reports' => $reports,
            'active'  => $active,
            'history' => $history,
        ));
    }

    public static function rest_get_report(WP_REST_Request $request) {
        $reference = strtoupper(sanitize_text_field((string) $request['reference']));
        $report = self::get_report_for_user($reference, PAXdesign_Customer_Auth::current_user_id());
        if (!$report) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response(array('report' => $report));
    }

    public static function rest_mark_read(WP_REST_Request $request) {
        $reference = strtoupper(sanitize_text_field((string) $request['reference']));
        $user_id = PAXdesign_Customer_Auth::current_user_id();
        $report = self::get_report_for_user($reference, $user_id);
        if (!$report) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'), array('status' => 404));
        }
        self::mark_read_for_audience($reference, 'customer', $user_id);
        $report = self::get_report_for_user($reference, $user_id);
        return rest_ensure_response(array('ok' => true, 'report' => $report));
    }

    public static function rest_customer_reply(WP_REST_Request $request) {
        $reference = strtoupper(sanitize_text_field((string) $request['reference']));
        $body = sanitize_textarea_field((string) ($request->get_param('message') ?? ''));
        if ($body === '') {
            return new WP_Error('invalid_message', __('Message is required.', 'paxdesign-booking'), array('status' => 400));
        }
        $result = self::add_customer_reply($reference, $body, PAXdesign_Customer_Auth::current_user_id());
        if (is_wp_error($result)) {
            return $result;
        }
        $report = self::get_report_for_user($reference, PAXdesign_Customer_Auth::current_user_id());
        return rest_ensure_response(array('message_id' => $result, 'report' => $report));
    }

    /**
     * @return bool
     */
    public static function verify_email_webhook(WP_REST_Request $request) {
        $secret = (string) get_option('paxdesign_cybercrime_email_webhook_secret', '');
        if ($secret === '') {
            return false;
        }
        $provided = (string) $request->get_header('x-pax-webhook-secret');
        if ($provided === '' && isset($_SERVER['HTTP_X_PAX_WEBHOOK_SECRET'])) {
            $provided = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_PAX_WEBHOOK_SECRET']));
        }
        return $secret !== '' && hash_equals($secret, $provided);
    }

    public static function rest_inbound_email(WP_REST_Request $request) {
        $result = self::handle_inbound_email(array(
            'from'       => $request->get_param('from'),
            'subject'    => $request->get_param('subject'),
            'body'       => $request->get_param('body'),
            'message_id' => $request->get_param('message_id'),
        ));
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }
}
