<?php
/**
 * Cybercrime Support tickets — timeline, status workflow, chat/email sync.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_Tickets {

    const TABLE_MESSAGES = 'paxdesign_cybercrime_messages';
    const SCHEMA_VERSION = '2';

    /** @var list<string> Canonical workflow statuses (admin + database). */
    private static $workflow_statuses = array(
        'submitted',
        'in_review',
        'waiting_for_customer',
        'resolved',
        'closed',
    );

    /** @var list<string> Legacy values normalized on read/write. */
    private static $legacy_status_map = array(
        'needs_info'         => 'waiting_for_customer',
        'customer_replied'   => 'in_review',
        'waiting_for_staff'  => 'in_review',
    );

    /** @var list<string> */
    private static $closed_statuses = array('resolved', 'closed');

    public static function init() {
        add_action('init', array(__CLASS__, 'ensure_schema'));
        add_action('wp_ajax_paxdesign_cybercrime_active_report', array(__CLASS__, 'ajax_active_report'));
        add_action('wp_ajax_paxdesign_cybercrime_report_detail', array(__CLASS__, 'ajax_report_detail'));
        add_action('wp_ajax_paxdesign_cybercrime_customer_reply', array(__CLASS__, 'ajax_customer_reply'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'), 25);
        add_action('paxdesign_chat_message_appended', array(__CLASS__, 'on_chat_message'), 10, 4);
        add_action('admin_post_paxdesign_cybercrime_update_status', array(__CLASS__, 'admin_update_status'));
        add_action('admin_post_paxdesign_cybercrime_staff_reply', array(__CLASS__, 'admin_staff_reply'));
    }

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
        return array(
            'submitted' => array(
                'label'       => __('New', 'paxdesign-booking'),
                'description' => __('New report received', 'paxdesign-booking'),
            ),
            'in_review' => array(
                'label'       => __('In Review', 'paxdesign-booking'),
                'description' => __('Team is analyzing the report', 'paxdesign-booking'),
            ),
            'waiting_for_customer' => array(
                'label'       => __('Waiting for Customer', 'paxdesign-booking'),
                'description' => __('Customer information is required', 'paxdesign-booking'),
            ),
            'resolved' => array(
                'label'       => __('Resolved', 'paxdesign-booking'),
                'description' => __('Issue solved', 'paxdesign-booking'),
            ),
            'closed' => array(
                'label'       => __('Closed', 'paxdesign-booking'),
                'description' => __('Ticket completed', 'paxdesign-booking'),
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
    public static function status_label($status) {
        $status = self::normalize_workflow_status($status);
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
     * @return string under_review|waiting_for_customer|resolved|closed
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
    public static function format_report_row($row, $with_timeline = false, $timeline_audience = 'customer') {
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

        $out = array(
            'reference_id'    => (string) ($row['reference_id'] ?? ''),
            'status'          => $workflow_status,
            'status_raw'      => $raw_status !== $workflow_status ? $raw_status : '',
            'status_label'    => self::status_label($raw_status),
            'customer_status' => self::customer_status_key($raw_status),
            'is_active'       => self::is_active_status($raw_status),
            'category'        => (string) ($row['category'] ?? ''),
            'category_label'  => PAXdesign_Cybercrime_Intake::category_label((string) ($row['category'] ?? '')),
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
        $entry['status_label'] = $status_key !== '' ? self::status_label($status_key) : '';
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
                    'visible_to_customer' => $summary !== '',
                )
            );
        }

        $customer_id = (int) ($row['customer_user_id'] ?? 0);
        if ($notify_customer && $customer_id > 0 && class_exists('PAXdesign_Customer_Notifications')) {
            PAXdesign_Customer_Notifications::notify_user(
                $customer_id,
                'security',
                sprintf(__('Cybercrime report %s updated', 'paxdesign-booking'), $reference_id),
                $message,
                'cybercrime',
                $reference_id,
                home_url('/cybercrime-support/?ref=' . rawurlencode($reference_id))
            );
            self::email_customer_update($row, $reference_id, $message, $new_status);
        }

        return true;
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
        $subject = sprintf('[Cybercrime Report %s] %s', $reference_id, self::status_label($status));
        $body = "Cybercrime Support update\n\n"
            . "Reference: {$reference_id}\n"
            . 'Status: ' . self::status_label($status) . "\n\n"
            . $message . "\n\n"
            . 'View your report: ' . home_url('/cybercrime-support/?ref=' . rawurlencode($reference_id)) . "\n\n"
            . "Reply to this email to add a message to your report.\n";
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
            . $body . "\n";
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
        if ($status === '') {
            $status = self::is_active_status((string) ($row['status'] ?? '')) ? 'waiting_for_customer' : (string) ($row['status'] ?? '');
        }
        self::update_status($reference_id, $status, $staff_user_id, '', false, false);

        return $message_id;
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
                $out[] = self::format_report_row($row, false);
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
            $secret = (string) (defined('AUTH_KEY') ? AUTH_KEY : '');
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
