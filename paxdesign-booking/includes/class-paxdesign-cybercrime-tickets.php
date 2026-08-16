<?php
/**
 * Cybercrime Support tickets — timeline, status workflow, chat/email sync.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_Tickets {

    const TABLE_MESSAGES = 'paxdesign_cybercrime_messages';
    const SCHEMA_VERSION = '3';

    /** @var list<string> Canonical workflow statuses (admin + database). */
    private static $workflow_statuses = array(
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
        add_action('wp_ajax_paxdesign_cybercrime_admin_delete_message', array(__CLASS__, 'ajax_admin_delete_message'));
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
     * @param string $lang de|en|ar
     * @return array<string, array{label: string, description: string}>
     */
    public static function workflow_steps($lang = '') {
        if ($lang === '' && class_exists('PAXdesign_Cybercrime_I18n')) {
            $lang = 'de';
        }
        $t = function ($key, $fallback) use ($lang) {
            if (class_exists('PAXdesign_Cybercrime_I18n')) {
                return PAXdesign_Cybercrime_I18n::t($key, $lang);
            }
            return $fallback;
        };
        return array(
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
                'label'       => $t('status.resolved', __('Resolved', 'paxdesign-booking')),
                'description' => $t('status.desc.resolved', __('Issue solved', 'paxdesign-booking')),
            ),
            'closed' => array(
                'label'       => $t('status.closed', __('Closed', 'paxdesign-booking')),
                'description' => $t('status.desc.closed', __('Ticket completed', 'paxdesign-booking')),
            ),
            'rejected' => array(
                'label'       => $t('status.rejected', 'مرفوض'),
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
     * @param string $lang de|en|ar
     * @return string
     */
    public static function status_label($status, $lang = '') {
        $status = self::normalize_workflow_status($status);
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            return PAXdesign_Cybercrime_I18n::status_label($status, $lang !== '' ? $lang : 'de');
        }
        $steps = self::workflow_steps($lang);
        if (isset($steps[$status]['label'])) {
            return (string) $steps[$status]['label'];
        }
        return ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Customer portal badge key.
     *
     * @param string $status
     * @return string under_review|waiting_for_customer|resolved|closed|rejected
     */
    public static function customer_status_key($status) {
        $status = self::normalize_workflow_status($status);
        switch ($status) {
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
        $raw_status = sanitize_key((string) ($row['status'] ?? ''));
        if (isset(self::$legacy_status_map[$raw_status])) {
            if ($raw_status === 'customer_replied') {
                $indicators[] = array(
                    'key'   => 'customer_replied',
                    'label' => __('Customer replied — review pending', 'paxdesign-booking'),
                );
            } elseif ($raw_status === 'waiting_for_staff') {
                $indicators[] = array(
                    'key'   => 'waiting_for_staff',
                    'label' => __('Waiting for staff action', 'paxdesign-booking'),
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
                    'label' => __('Latest activity: customer reply', 'paxdesign-booking'),
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
        $attachments = self::collect_report_attachments((string) ($row['reference_id'] ?? ''), $row);

        $raw_status = sanitize_key((string) ($row['status'] ?? ''));
        $workflow_status = self::normalize_workflow_status($raw_status);
        $lang = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::from_report($row)
            : 'de';
        $status_label_i18n = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::pack('status.' . $workflow_status)
            : array();
        $next_action_i18n = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::next_action_pack($workflow_status)
            : array();
        $category = (string) ($row['category'] ?? '');
        $category_label = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::category_label($category, $lang)
            : PAXdesign_Cybercrime_Intake::category_label($category);

        $out = array(
            'reference_id'    => (string) ($row['reference_id'] ?? ''),
            'status'          => $workflow_status,
            'status_raw'      => $raw_status !== $workflow_status ? $raw_status : '',
            'status_label'    => self::status_label($raw_status, $lang),
            'status_label_i18n' => $status_label_i18n,
            'next_action_i18n' => $next_action_i18n,
            'customer_status' => self::customer_status_key($raw_status),
            'is_active'       => self::is_active_status($raw_status),
            'category'        => $category,
            'category_label'  => $category_label,
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

        $customer_display_name = self::resolve_customer_display_name($row);
        $out['customer_display_name'] = $customer_display_name;
        $read_audience = $unread_audience !== ''
            ? sanitize_key((string) $unread_audience)
            : ($timeline_audience === 'admin' ? 'staff' : 'customer');
        if (!in_array($read_audience, array('staff', 'customer'), true)) {
            $read_audience = 'customer';
        }
        $out['unread_count'] = self::count_unread_for_audience((string) ($row['reference_id'] ?? ''), $read_audience, $row);

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
            self::append_report_sync_meta(
                $out,
                $out['timeline'] ?? array(),
                (string) ($row['reference_id'] ?? ''),
                $row
            );
        }

        return $out;
    }

    /**
     * Derive admin sync metadata from the timeline payload the client renders.
     *
     * @param array<string, mixed>              $out
     * @param array<int, array<string, mixed>>  $timeline
     * @param string                            $reference_id
     * @param array<string, mixed>|null         $row
     */
    public static function append_report_sync_meta(array &$out, array $timeline = array(), $reference_id = '', $row = null) {
        $max_id = 0;
        $count = 0;
        foreach ($timeline as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $count++;
            $max_id = max($max_id, (int) ($entry['id'] ?? 0));
        }
        $evidence_signature = self::timeline_evidence_signature($timeline);
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '' && !empty($out['reference_id'])) {
            $reference_id = sanitize_text_field((string) $out['reference_id']);
        }
        $stored_attachments = $reference_id !== ''
            ? self::collect_stored_attachments($reference_id, $row)
            : array();
        $attachments_signature = self::attachments_signature($stored_attachments);
        $out['timeline_max_id'] = $max_id;
        $out['timeline_count'] = $count;
        $out['timeline_evidence_signature'] = $evidence_signature;
        $out['attachments_count'] = count($stored_attachments);
        $out['attachments_signature'] = $attachments_signature;
        $out['sync_revision'] = self::build_sync_revision(
            (string) ($out['updated_at'] ?? ''),
            $max_id,
            $count,
            (string) ($out['status'] ?? ''),
            $evidence_signature,
            $attachments_signature
        );
    }

    /**
     * Stable signature of per-message evidence-request flags for client sync.
     *
     * @param array<int, array<string, mixed>> $timeline
     * @return string
     */
    public static function timeline_evidence_signature(array $timeline) {
        $parts = array();
        foreach ($timeline as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : array();
            $flag = (!empty($meta['request_evidence']) || !empty($entry['request_evidence'])) ? '1' : '0';
            $parts[] = (int) ($entry['id'] ?? 0) . ':' . $flag;
        }
        return implode(',', $parts);
    }

    /**
     * @param string $updated_at
     * @param int    $timeline_max_id
     * @param int    $timeline_count
     * @param string $status
     * @param string $evidence_signature
     * @param string $attachments_signature
     * @return string
     */
    public static function build_sync_revision($updated_at, $timeline_max_id, $timeline_count, $status = '', $evidence_signature = '', $attachments_signature = '') {
        return hash(
            'crc32b',
            sanitize_text_field((string) $updated_at)
            . '|' . max(0, (int) $timeline_max_id)
            . '|' . max(0, (int) $timeline_count)
            . '|' . sanitize_key((string) $status)
            . '|' . sanitize_text_field((string) $evidence_signature)
            . '|' . sanitize_text_field((string) $attachments_signature)
        );
    }

    /**
     * Canonicalize one stored attachment record without dropping legacy fields.
     *
     * @param array<string, mixed> $attachment
     * @return array<string, string>|null
     */
    public static function canonicalize_attachment_record($attachment) {
        if (!is_array($attachment)) {
            return null;
        }

        $name = sanitize_file_name((string) ($attachment['name'] ?? ''));
        if ($name === '') {
            foreach (array('path', 'url', 'file') as $source_key) {
                if (empty($attachment[$source_key])) {
                    continue;
                }
                $raw = (string) $attachment[$source_key];
                $path_part = parse_url($raw, PHP_URL_PATH);
                $candidate = sanitize_file_name(basename($path_part !== null && $path_part !== '' ? $path_part : $raw));
                if ($candidate !== '') {
                    $name = $candidate;
                    break;
                }
            }
        }
        if ($name === '') {
            return null;
        }

        $out = array(
            'field' => sanitize_key((string) ($attachment['field'] ?? '')),
            'name'  => $name,
            'type'  => sanitize_mime_type((string) ($attachment['type'] ?? '')),
            'size'  => (string) ($attachment['size'] ?? ''),
        );
        if (!empty($attachment['path'])) {
            $out['path'] = ltrim(str_replace('\\', '/', (string) $attachment['path']), '/');
        }
        if (!empty($attachment['url'])) {
            $out['url'] = (string) $attachment['url'];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $attachment
     * @return array<string, string>|null
     */
    public static function normalize_stored_attachment($attachment) {
        return self::canonicalize_attachment_record($attachment);
    }

    /**
     * @param array<string, string>      $existing
     * @param array<string, string>      $incoming
     * @return array<string, string>
     */
    public static function merge_attachment_records(array $existing, array $incoming) {
        $out = $existing;
        foreach (array('field', 'name', 'path', 'url', 'type', 'size') as $key) {
            $current = (string) ($out[$key] ?? '');
            $next = (string) ($incoming[$key] ?? '');
            if ($current === '' && $next !== '') {
                $out[$key] = $next;
            }
        }
        if (!empty($incoming['path'])) {
            $out['path'] = (string) $incoming['path'];
        }
        if (!empty($incoming['url'])) {
            $out['url'] = (string) $incoming['url'];
        }
        return $out;
    }

    /**
     * @param array<string, string> $attachment
     * @return string
     */
    public static function attachment_record_key(array $attachment) {
        $name = sanitize_file_name((string) ($attachment['name'] ?? ''));
        if ($name !== '') {
            return 'name:' . $name;
        }
        $path = ltrim(str_replace('\\', '/', (string) ($attachment['path'] ?? '')), '/');
        if ($path !== '') {
            return 'path:' . $path;
        }
        $url = (string) ($attachment['url'] ?? '');
        if ($url !== '') {
            return 'url:' . $url;
        }
        return '';
    }

    /**
     * @param array<int, array<string, mixed>> ...$lists
     * @return array<int, array<string, string>>
     */
    public static function merge_attachment_lists(array ...$lists) {
        $merged = array();
        $index = array();

        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $attachment) {
                $canonical = self::canonicalize_attachment_record($attachment);
                if (!$canonical) {
                    continue;
                }
                $key = self::attachment_record_key($canonical);
                if ($key === '') {
                    $merged[] = $canonical;
                    continue;
                }
                if (isset($index[$key])) {
                    $merged[$index[$key]] = self::merge_attachment_records($merged[$index[$key]], $canonical);
                    continue;
                }
                $index[$key] = count($merged);
                $merged[] = $canonical;
            }
        }

        return array_values($merged);
    }

    /**
     * Collect attachment records stored on timeline messages (all rows, not capped by chat noise).
     *
     * @param string $reference_id
     * @return array<int, array<string, mixed>>
     */
    public static function collect_message_attachments($reference_id) {
        global $wpdb;
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '') {
            return array();
        }

        $like = '%' . $wpdb->esc_like('"attachments"') . '%';
        $rows = $wpdb->get_col($wpdb->prepare(
            'SELECT meta_json FROM ' . self::messages_table() . '
             WHERE reference_id = %s AND meta_json LIKE %s
             ORDER BY id ASC',
            $reference_id,
            $like
        ));
        if (!is_array($rows)) {
            return array();
        }

        $attachments = array();
        foreach ($rows as $meta_json) {
            $meta = json_decode((string) $meta_json, true);
            if (!is_array($meta) || empty($meta['attachments']) || !is_array($meta['attachments'])) {
                continue;
            }
            foreach ($meta['attachments'] as $attachment) {
                $attachments[] = $attachment;
            }
        }
        return $attachments;
    }

    /**
     * Merge new uploads into the report attachments column without removing existing files.
     *
     * @param string                              $reference_id
     * @param array<int, array<string, mixed>>    $new_attachments
     * @return bool
     */
    public static function append_report_attachments($reference_id, array $new_attachments) {
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '' || empty($new_attachments)) {
            return false;
        }

        $row = static::get_report_row($reference_id);
        if (!is_array($row)) {
            return false;
        }

        $existing_raw = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($existing_raw)) {
            $existing_raw = array();
        }
        $existing_canonical = self::merge_attachment_lists($existing_raw);
        $final = self::merge_attachment_lists($existing_raw, $new_attachments);
        if (count($final) < count($existing_canonical)) {
            error_log('[PAXdesign Cybercrime] Refusing to shrink attachments while appending for ' . $reference_id);
            $final = self::merge_attachment_lists($existing_canonical, $new_attachments);
        }

        global $wpdb;
        $updated = $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array(
                'attachments' => wp_json_encode($final),
                'updated_at'  => current_time('mysql', true),
            ),
            array('reference_id' => $reference_id),
            array('%s', '%s'),
            array('%s')
        );
        return $updated !== false;
    }

    /**
     * Merge report-level and timeline message attachment records.
     *
     * @param string                    $reference_id
     * @param array<string, mixed>|null $row
     * @return array<int, array<string, string>>
     */
    public static function collect_stored_attachments($reference_id, $row = null) {
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '') {
            return array();
        }
        if (!is_array($row)) {
            $row = static::get_report_row($reference_id);
        }

        $lists = array();
        if (is_array($row)) {
            $stored = json_decode((string) ($row['attachments'] ?? ''), true);
            if (is_array($stored)) {
                $lists[] = $stored;
            }
        }

        $message_attachments = static::collect_message_attachments($reference_id);
        if (!empty($message_attachments)) {
            $lists[] = $message_attachments;
        }

        $merged = self::merge_attachment_lists(...$lists);
        if (!class_exists('PAXdesign_Cybercrime_Intake')) {
            return $merged;
        }

        foreach ($merged as $i => $attachment) {
            $merged[$i] = PAXdesign_Cybercrime_Intake::recover_attachment_record($attachment);
        }
        return $merged;
    }

    /**
     * @param string                    $reference_id
     * @param array<string, mixed>|null $row
     * @return array<int, array<string, mixed>>
     */
    public static function collect_report_attachments($reference_id, $row = null) {
        $stored = self::collect_stored_attachments($reference_id, $row);
        if (class_exists('PAXdesign_Cybercrime_Intake')) {
            return PAXdesign_Cybercrime_Intake::enrich_attachments($reference_id, $stored);
        }
        return $stored;
    }

    /**
     * Stable signature of all stored attachments for client sync.
     *
     * @param array<int, array<string, string>> $attachments
     * @return string
     */
    public static function attachments_signature(array $attachments) {
        $parts = array();
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $name = sanitize_file_name((string) ($attachment['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $parts[] = $name . ':' . (string) ($attachment['size'] ?? '') . ':' . (string) ($attachment['path'] ?? '');
        }
        sort($parts, SORT_STRING);
        return count($parts) . ':' . implode(',', $parts);
    }

    /**
     * @param string                    $reference_id
     * @param string                    $file_name
     * @param array<string, mixed>|null $row
     * @return array<string, string>|null
     */
    public static function find_stored_attachment($reference_id, $file_name, $row = null) {
        $file_name = sanitize_file_name((string) $file_name);
        if ($file_name === '') {
            return null;
        }
        foreach (self::collect_stored_attachments($reference_id, $row) as $attachment) {
            if (sanitize_file_name((string) ($attachment['name'] ?? '')) === $file_name) {
                return $attachment;
            }
        }
        return null;
    }

    /**
     * Persist the union of report + timeline attachments on the report row.
     *
     * @param string $reference_id
     * @return bool
     */
    public static function sync_report_attachments_column($reference_id) {
        $reference_id = sanitize_text_field((string) $reference_id);
        if ($reference_id === '') {
            return false;
        }

        $row = static::get_report_row($reference_id);
        if (!is_array($row)) {
            return false;
        }

        $existing_raw = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($existing_raw)) {
            $existing_raw = array();
        }
        $existing_canonical = self::merge_attachment_lists($existing_raw);
        $collected = self::collect_stored_attachments($reference_id);
        $final = self::merge_attachment_lists($existing_raw, $collected, static::collect_message_attachments($reference_id));

        if (count($final) < count($existing_canonical)) {
            error_log('[PAXdesign Cybercrime] Refusing to shrink attachments for ' . $reference_id);
            $final = self::merge_attachment_lists($existing_canonical, $collected);
        }

        global $wpdb;
        $updated = $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array(
                'attachments' => wp_json_encode($final),
                'updated_at'  => current_time('mysql', true),
            ),
            array('reference_id' => $reference_id),
            array('%s', '%s'),
            array('%s')
        );
        return $updated !== false;
    }

    /**
     * @param string               $reference_id
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    public static function report_sync_snapshot($reference_id, $row = null) {
        if (!is_array($row)) {
            $row = self::get_report_row($reference_id);
        }
        if (!is_array($row)) {
            return array(
                'updated_at'       => '',
                'timeline_max_id'  => 0,
                'timeline_count'   => 0,
                'status'           => '',
                'sync_revision'    => '',
            );
        }
        $timeline = self::list_official_messages($reference_id, 200, '', 'admin');
        $snapshot = array(
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'status'     => self::normalize_workflow_status((string) ($row['status'] ?? '')),
        );
        self::append_report_sync_meta($snapshot, $timeline, $reference_id, $row);
        return array(
            'updated_at'                  => (string) ($snapshot['updated_at'] ?? ''),
            'timeline_max_id'             => (int) ($snapshot['timeline_max_id'] ?? 0),
            'timeline_count'              => (int) ($snapshot['timeline_count'] ?? 0),
            'timeline_evidence_signature' => (string) ($snapshot['timeline_evidence_signature'] ?? ''),
            'attachments_count'           => (int) ($snapshot['attachments_count'] ?? 0),
            'attachments_signature'       => (string) ($snapshot['attachments_signature'] ?? ''),
            'status'                      => (string) ($snapshot['status'] ?? ''),
            'sync_revision'               => (string) ($snapshot['sync_revision'] ?? ''),
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
        $report_row = null;
        if ($customer_display_name === '') {
            $report_row = self::get_report_row($reference_id);
            if (is_array($report_row)) {
                $customer_display_name = self::resolve_customer_display_name($report_row);
            }
        }
        $lang = (is_array($report_row) && class_exists('PAXdesign_Cybercrime_I18n'))
            ? PAXdesign_Cybercrime_I18n::from_report($report_row)
            : self::report_lang_for_reference($reference_id);
        $messages = self::list_messages($reference_id, $limit);
        $official = array();
        foreach ($messages as $entry) {
            if (!is_array($entry) || !self::is_official_timeline_entry($entry)) {
                continue;
            }
            $entry['reference_id'] = $reference_id;
            $official[] = self::format_timeline_entry($entry, $customer_display_name, $audience, $lang);
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
        $report_row = null;
        if ($customer_display_name === '') {
            $report_row = self::get_report_row($reference_id);
            if (is_array($report_row)) {
                $customer_display_name = self::resolve_customer_display_name($report_row);
            }
        }
        $lang = (is_array($report_row) && class_exists('PAXdesign_Cybercrime_I18n'))
            ? PAXdesign_Cybercrime_I18n::from_report($report_row)
            : self::report_lang_for_reference($reference_id);
        $messages = self::list_messages($reference_id, $limit);
        $visible = array();
        foreach ($messages as $entry) {
            if (!is_array($entry) || !self::is_customer_visible_timeline_entry($entry)) {
                continue;
            }
            $entry['reference_id'] = $reference_id;
            $visible[] = self::format_timeline_entry($entry, $customer_display_name, 'customer', $lang);
        }
        return $visible;
    }

    /**
     * @param string $reference_id
     * @return string de|en|ar
     */
    private static function report_lang_for_reference($reference_id) {
        if (!class_exists('PAXdesign_Cybercrime_I18n')) {
            return 'de';
        }
        $row = self::get_report_row($reference_id);
        return is_array($row) ? PAXdesign_Cybercrime_I18n::from_report($row) : 'de';
    }

    /**
     * @param array<string, mixed> $entry
     * @return bool
     */
    public static function is_official_timeline_entry($entry) {
        if (!is_array($entry)) {
            return false;
        }
        if (self::is_deleted_timeline_entry($entry)) {
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
     * @param array<string, mixed> $entry
     * @return bool
     */
    public static function is_deleted_timeline_entry($entry) {
        if (!is_array($entry)) {
            return false;
        }
        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : array();
        return !empty($meta['deleted']);
    }

    /**
     * Admin conversation row type for UI labelling.
     *
     * @param array<string, mixed> $entry
     * @return string customer|staff|internal|system
     */
    public static function admin_timeline_kind($entry) {
        if (!is_array($entry)) {
            return 'system';
        }
        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : array();
        if (!empty($meta['internal_only'])) {
            return 'internal';
        }
        $author = sanitize_key((string) ($entry['author_type'] ?? ''));
        if ($author === 'customer') {
            return 'customer';
        }
        if ($author === 'staff') {
            return 'staff';
        }
        return 'system';
    }

    /**
     * @param string $kind
     * @return string
     */
    public static function admin_timeline_label($kind) {
        switch (sanitize_key((string) $kind)) {
            case 'customer':
                return __('Customer', 'paxdesign-booking');
            case 'staff':
                return __('You / Staff', 'paxdesign-booking');
            case 'internal':
                return __('Internal Note', 'paxdesign-booking');
            default:
                return __('System', 'paxdesign-booking');
        }
    }

    /**
     * Staff replies visible to the customer may be permanently removed by admins.
     *
     * @param array<string, mixed> $entry
     * @return bool
     */
    public static function is_deletable_staff_message($entry) {
        if (!is_array($entry) || self::is_deleted_timeline_entry($entry)) {
            return false;
        }
        if (sanitize_key((string) ($entry['author_type'] ?? '')) !== 'staff') {
            return false;
        }
        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : array();
        if (!empty($meta['internal_only'])) {
            return false;
        }
        return sanitize_key((string) ($meta['event'] ?? '')) === 'staff_reply';
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
    public static function format_timeline_entry($entry, $customer_display_name = '', $audience = 'customer', $lang = '') {
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
        $entry['status_label'] = $status_key !== '' ? self::status_label($status_key, $lang) : '';
        $entry['status_label_i18n'] = ($status_key !== '' && class_exists('PAXdesign_Cybercrime_I18n'))
            ? PAXdesign_Cybercrime_I18n::pack('status.' . $status_key)
            : array();
        $entry['sender_key'] = $author === 'customer' ? 'customer' : 'support';
        $entry['customer_name'] = $author === 'customer' ? $customer_display_name : '';
        $entry['customer_visible'] = self::is_customer_visible_timeline_entry($entry);
        $entry['subject_key'] = $subject_key;
        $entry['subject'] = $audience === 'customer' ? '' : $subject;
        $entry['request_evidence'] = !empty($meta['request_evidence']) ? 1 : 0;
        $entry['id'] = (int) ($entry['id'] ?? 0);
        $deletable = self::is_deletable_staff_message($entry);
        $entry['allow_delete'] = $deletable ? 1 : 0;
        $entry['can_delete'] = $deletable;
        if ($audience === 'admin') {
            $kind = self::admin_timeline_kind($entry);
            $entry['timeline_kind'] = $kind;
            $entry['timeline_label'] = self::admin_timeline_label($kind);
        }

        if (!empty($meta['attachments']) && is_array($meta['attachments']) && class_exists('PAXdesign_Cybercrime_Intake')) {
            $reference_id = sanitize_text_field((string) ($entry['reference_id'] ?? ''));
            if ($reference_id === '' && !empty($entry['report_id'])) {
                $report_row = self::get_report_row_by_id((int) $entry['report_id']);
                $reference_id = is_array($report_row) ? (string) ($report_row['reference_id'] ?? '') : '';
            }
            if ($reference_id !== '') {
                $entry['attachments'] = PAXdesign_Cybercrime_Intake::enrich_attachments($reference_id, $meta['attachments']);
            }
        }

        return $entry;
    }

    /**
     * @param int $report_id
     * @return array<string, mixed>|null
     */
    private static function get_report_row_by_id($report_id) {
        global $wpdb;
        $report_id = absint($report_id);
        if ($report_id <= 0) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PAXdesign_Cybercrime_Intake::table_name() . ' WHERE id = %d LIMIT 1',
            $report_id
        ), ARRAY_A);
        return is_array($row) ? $row : null;
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

        $lang = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::from_report($row)
            : 'de';
        $label = self::status_label($new_status, $lang);
        $message = $summary !== ''
            ? $summary
            : (class_exists('PAXdesign_Cybercrime_I18n')
                ? sprintf(PAXdesign_Cybercrime_I18n::t('timeline.status_changed', $lang), $label)
                : sprintf(__('Status changed to %s.', 'paxdesign-booking'), $label));

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
        $title = class_exists('PAXdesign_Cybercrime_I18n')
            ? sprintf(PAXdesign_Cybercrime_I18n::t('notify.status.title', $lang), $reference_id)
            : sprintf(__('Cybercrime report %s updated', 'paxdesign-booking'), $reference_id);
        if ($notify_customer && $customer_id > 0) {
            self::queue_customer_notification(
                $row,
                $reference_id,
                $message,
                $new_status,
                $title
            );
        } elseif ($notify_customer) {
            self::email_customer_update($row, $reference_id, $message, $new_status);
        }
        self::email_admin_status($row, $reference_id, $message, $new_status);

        if (in_array($new_status, self::$closed_statuses, true)) {
            self::close_linked_chat_session($row);
        }

        return true;
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
            $title = sanitize_text_field((string) ($item['title'] ?? ''));

            PAXdesign_Customer_Notifications::notify_user(
                $customer_id,
                'security',
                $title,
                $message,
                'cybercrime',
                $reference_id,
                home_url('/cybercrime-support/?ref=' . rawurlencode($reference_id))
            );
            self::email_customer_update($row, $reference_id, $message, $status);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $reference_id
     * @param string               $message
     * @param string               $status
     */
    private static function email_customer_update($row, $reference_id, $message, $status) {
        $email = sanitize_email((string) ($row['reporter_email'] ?? ''));
        if (!is_email($email)) {
            return;
        }
        $lang = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::from_report($row)
            : 'de';
        $label = self::status_label($status, $lang);
        $view = home_url('/cybercrime-support/?ref=' . rawurlencode($reference_id));
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            $subject = sprintf(PAXdesign_Cybercrime_I18n::t('email.status.customer.subject', $lang), $reference_id, $label);
            $body = sprintf(
                PAXdesign_Cybercrime_I18n::t('email.status.customer.body', $lang),
                $reference_id,
                $label,
                $message,
                $view
            );
        } else {
            $subject = sprintf('[Cybercrime Report %s] %s', $reference_id, $label);
            $body = "Cybercrime Support update\n\n"
                . "Reference: {$reference_id}\n"
                . 'Status: ' . $label . "\n\n"
                . $message . "\n\n"
                . 'View your report: ' . $view . "\n";
        }
        wp_mail($email, $subject, $body, array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: cybercrime+' . rawurlencode($reference_id) . '@paxdesign.at',
        ));
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $reference_id
     * @param string               $message
     * @param string               $status
     */
    private static function email_admin_status($row, $reference_id, $message, $status) {
        $to = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
        if (!is_email($to)) {
            return;
        }
        $lang = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::from_report($row)
            : 'de';
        $label = self::status_label($status, $lang);
        $admin_url = admin_url('admin.php?page=paxdesign-customer-portal&tab=cybercrime&reference=' . rawurlencode($reference_id));
        $name = (string) ($row['reporter_name'] ?? '');
        $email = (string) ($row['reporter_email'] ?? '');
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            $subject = sprintf(PAXdesign_Cybercrime_I18n::t('email.status.admin.subject', $lang), $reference_id, $label);
            $body = sprintf(
                PAXdesign_Cybercrime_I18n::t('email.status.admin.body', $lang),
                $reference_id,
                $name,
                $email,
                $label,
                $message,
                $admin_url
            );
        } else {
            $subject = sprintf('[Cybercrime %s] Status: %s', $reference_id, $label);
            $body = "Status change\n\nReference: {$reference_id}\nStatus: {$label}\n\n{$message}\n";
        }
        wp_mail($to, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
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
     * Customer evidence upload + optional message (resubmit flow).
     *
     * @param string $reference_id
     * @param string $body
     * @param int    $user_id
     * @return int|WP_Error
     */
    public static function add_customer_evidence($reference_id, $body, $user_id) {
        $row = self::get_report_row($reference_id);
        if (!$row || !self::user_can_view_report($row, $user_id)) {
            return new WP_Error('forbidden', __('You cannot update this report.', 'paxdesign-booking'));
        }
        if (!self::is_active_status((string) ($row['status'] ?? ''))) {
            return new WP_Error('closed', __('This report is closed.', 'paxdesign-booking'));
        }

        if (!class_exists('PAXdesign_Cybercrime_Intake')) {
            return new WP_Error('config', __('Uploads are unavailable.', 'paxdesign-booking'));
        }

        $uploads = PAXdesign_Cybercrime_Intake::handle_request_uploads();
        if (is_wp_error($uploads)) {
            return $uploads;
        }

        $body = trim((string) $body);
        $has_files = !empty($uploads);
        if (!$has_files && $body === '') {
            return new WP_Error('message_required', __('Please attach a file or add a message.', 'paxdesign-booking'), array('code' => 'message_required'));
        }

        if ($body === '') {
            $lang = class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::from_report($row)
                : 'de';
            $body = class_exists('PAXdesign_Cybercrime_I18n')
                ? PAXdesign_Cybercrime_I18n::t('evidence.uploaded', $lang)
                : __('Customer uploaded evidence.', 'paxdesign-booking');
        }

        $meta = array('event' => 'customer_evidence');
        if ($has_files) {
            $meta['attachments'] = $uploads;
        }

        $message_id = self::add_message($reference_id, 'customer', $body, 'portal', $user_id, $meta);
        if (!$message_id) {
            return new WP_Error('save_failed', __('Could not save your update.', 'paxdesign-booking'));
        }

        if ($has_files) {
            if (!self::append_report_attachments($reference_id, $uploads)) {
                error_log('[PAXdesign Cybercrime] Could not append report attachments for ' . $reference_id);
            }
            if (!self::sync_report_attachments_column($reference_id)) {
                error_log('[PAXdesign Cybercrime] Could not sync report attachments for ' . $reference_id);
            }
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
            . $body . "\n";
        wp_mail($to, $subject, $text, array('Content-Type: text/plain; charset=UTF-8'));
    }

    /**
     * @param string $reference_id
     * @param string $body
     * @param int    $staff_user_id
     * @return int|false|WP_Error
     */
    public static function add_staff_reply($reference_id, $body, $staff_user_id, $new_status = '', $request_evidence = false) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Insufficient permissions.', 'paxdesign-booking'));
        }
        $row = self::get_report_row($reference_id);
        if (!$row) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'));
        }

        $meta = array('event' => 'staff_reply');
        if ($request_evidence) {
            $meta['request_evidence'] = 1;
        }

        $message_id = self::add_message($reference_id, 'staff', $body, 'admin', $staff_user_id, $meta);
        if (!$message_id) {
            return new WP_Error('save_failed', __('Could not save staff reply.', 'paxdesign-booking'));
        }

        $status = sanitize_key((string) $new_status);
        if ($request_evidence) {
            $status = 'waiting_for_customer';
        } elseif ($status === '') {
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
     * @param int    $message_id
     * @param int    $staff_user_id
     * @return bool|WP_Error
     */
    public static function delete_staff_message($reference_id, $message_id, $staff_user_id) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Insufficient permissions.', 'paxdesign-booking'));
        }

        $reference_id = sanitize_text_field((string) $reference_id);
        $message_id = absint($message_id);
        if ($reference_id === '' || $message_id <= 0) {
            return new WP_Error('invalid_request', __('Invalid message.', 'paxdesign-booking'));
        }

        $row = self::get_report_row($reference_id);
        if (!$row) {
            return new WP_Error('not_found', __('Report not found.', 'paxdesign-booking'));
        }

        global $wpdb;
        $message_row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::messages_table() . ' WHERE id = %d AND reference_id = %s LIMIT 1',
            $message_id,
            $reference_id
        ), ARRAY_A);
        if (!is_array($message_row)) {
            return new WP_Error('not_found', __('Message not found.', 'paxdesign-booking'));
        }

        $meta = json_decode((string) ($message_row['meta_json'] ?? ''), true);
        if (!is_array($meta)) {
            $meta = array();
        }
        $entry = array(
            'id'          => $message_id,
            'author_type' => (string) ($message_row['author_type'] ?? ''),
            'meta'        => $meta,
        );
        if (!self::is_deletable_staff_message($entry)) {
            return new WP_Error('not_deletable', __('This message cannot be deleted.', 'paxdesign-booking'));
        }

        $deleted = $wpdb->delete(
            self::messages_table(),
            array(
                'id'           => $message_id,
                'reference_id' => $reference_id,
            ),
            array('%d', '%s')
        );
        if (!$deleted) {
            return new WP_Error('delete_failed', __('Could not delete message.', 'paxdesign-booking'));
        }

        $now = current_time('mysql', true);
        $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array('updated_at' => $now),
            array('reference_id' => $reference_id),
            array('%s'),
            array('%s')
        );

        return true;
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
            '- Status: ' . ($detail['status_label'] ?? '') . ' (' . ($detail['status'] ?? '') . ')',
            '- Category: ' . ($detail['category_label'] ?? ''),
            '- Submitted: ' . ($detail['created_at'] ?? ''),
            '- Last update: ' . ($detail['updated_at'] ?? ''),
            '- Reason/summary: ' . wp_html_excerpt((string) ($detail['description'] ?? ''), 400, '…'),
        );

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

        return implode("\n", $lines);
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
                $out[] = self::format_report_row($row, false, 'customer', 'staff');
            }
        }
        return $out;
    }

    public static function ajax_customer_reply() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please sign in.', 'paxdesign-booking'), 'code' => 'login_required'), 401);
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
            wp_send_json_error(array('message' => __('Please sign in.', 'paxdesign-booking'), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');

        if (!empty($_POST['website_trap'])) {
            wp_send_json_error(array('message' => __('Request rejected.', 'paxdesign-booking'), 'code' => 'request_rejected'), 403);
        }

        $reference = sanitize_text_field(wp_unslash($_POST['reference'] ?? ''));
        $body = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        $result = self::add_customer_evidence($reference, $body, get_current_user_id());
        if (is_wp_error($result)) {
            $data = array('message' => $result->get_error_message());
            $code = $result->get_error_code();
            if ($code !== '') {
                $data['code'] = $code;
            }
            wp_send_json_error($data, 400);
        }

        $report = self::get_report_for_user($reference, get_current_user_id());
        $lang = is_array($report) && class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::from_report($report)
            : 'de';
        $success = class_exists('PAXdesign_Cybercrime_I18n')
            ? PAXdesign_Cybercrime_I18n::t('evidence.success', $lang)
            : __('Your evidence was uploaded successfully.', 'paxdesign-booking');

        wp_send_json_success(array(
            'message_id' => $result,
            'message'    => $success,
            'report'     => $report,
        ));
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
        $request_evidence = !empty($_POST['request_evidence']) && wp_unslash($_POST['request_evidence']) !== '0';

        $result = self::add_staff_reply($reference, $body, get_current_user_id(), $status, $request_evidence);
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

    public static function ajax_admin_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'paxdesign-booking')), 403);
        }
        check_ajax_referer(self::ADMIN_NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));

        $result = self::update_status($reference, $status, get_current_user_id(), '', true);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        $report = self::get_report_for_admin($reference);
        if (!$report) {
            wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
        }

        wp_send_json_success(array(
            'report'  => $report,
            'message' => __('Status saved.', 'paxdesign-booking'),
        ));
    }

    public static function ajax_admin_reply() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'paxdesign-booking')), 403);
        }
        check_ajax_referer(self::ADMIN_NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        $body = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
        $request_evidence = !empty($_POST['request_evidence']) && wp_unslash($_POST['request_evidence']) !== '0';

        if ($body === '') {
            wp_send_json_error(array('message' => __('Message is required.', 'paxdesign-booking')), 400);
        }

        $result = self::add_staff_reply($reference, $body, get_current_user_id(), $status, $request_evidence);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        $report = self::get_report_for_admin($reference);
        if (!$report) {
            wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
        }

        $success = $request_evidence
            ? __('Evidence request sent to customer.', 'paxdesign-booking')
            : __('Reply sent to customer.', 'paxdesign-booking');

        wp_send_json_success(array(
            'report'     => $report,
            'message_id' => $result,
            'message'    => $success,
        ));
    }

    public static function ajax_admin_internal_note() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'paxdesign-booking')), 403);
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
            wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
        }

        wp_send_json_success(array(
            'report'     => $report,
            'message_id' => $result,
            'message'    => __('Internal note added.', 'paxdesign-booking'),
        ));
    }

    public static function ajax_admin_delete_message() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'paxdesign-booking')), 403);
        }
        check_ajax_referer(self::ADMIN_NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_POST['reference_id'] ?? ''));
        $message_id = absint($_POST['message_id'] ?? 0);

        $result = self::delete_staff_message($reference, $message_id, get_current_user_id());
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        $report = self::get_report_for_admin($reference);
        if (!$report) {
            wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
        }

        wp_send_json_success(array(
            'report'  => $report,
            'message' => __('Message deleted.', 'paxdesign-booking'),
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
                wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
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
            wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
        }

        $row = self::get_report_row($reference);
        if (!$row) {
            wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
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
            wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
        }

        self::mark_read_for_audience($reference, 'customer', get_current_user_id());
        $report = self::get_report_for_user($reference, get_current_user_id());

        wp_send_json_success(array(
            'unread_count' => 0,
            'report'       => $report,
        ));
    }

    public static function ajax_report_list() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please sign in.', 'paxdesign-booking'), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');

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
            wp_send_json_error(array('message' => __('Please sign in.', 'paxdesign-booking'), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');

        $report = self::get_active_report_for_user(get_current_user_id());
        if (!$report) {
            wp_send_json_success(array('active' => false));
        }
        wp_send_json_success(array('active' => true, 'report' => $report));
    }

    public static function ajax_report_detail() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please sign in.', 'paxdesign-booking'), 'code' => 'login_required'), 401);
        }
        check_ajax_referer(PAXdesign_Cybercrime_Intake::NONCE_ACTION, 'nonce');

        $reference = sanitize_text_field(wp_unslash($_GET['reference'] ?? $_POST['reference'] ?? ''));
        $report = self::get_report_for_user($reference, get_current_user_id());
        if (!$report) {
            wp_send_json_error(array('message' => __('Report not found.', 'paxdesign-booking')), 404);
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
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'rest_list_reports'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_customer'),
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
