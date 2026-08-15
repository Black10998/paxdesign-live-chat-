<?php
/**
 * Administrator review reminders for Cybercrime Support cases.
 *
 * Sends email when a case needs human review or remains pending without
 * staff action, with a direct link to the exact report in wp-admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_Admin_Reminders {

    const CRON_HOOK = 'paxdesign_cybercrime_admin_review_reminders';
    const PENDING_SECONDS = 14400; // 4 hours
    const REMINDER_COOLDOWN = DAY_IN_SECONDS;
    const EMAIL_SUBJECT = 'A Cybercrime Support request requires your review.';

    public static function init() {
        add_action('init', array(__CLASS__, 'schedule'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'send_pending_reminders'));
        add_action('paxdesign_cybercrime_needs_human_review', array(__CLASS__, 'notify_human_review'), 10, 2);
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 600, 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Direct wp-admin URL for a Cybercrime Support case.
     *
     * @param string $reference
     * @return string
     */
    public static function admin_case_url($reference) {
        $reference = sanitize_text_field((string) $reference);
        $page = class_exists('PAXdesign_Customer_Admin')
            ? PAXdesign_Customer_Admin::MENU_SLUG
            : 'paxdesign-customer-portal';
        return admin_url('admin.php?page=' . $page . '&tab=cybercrime&reference=' . rawurlencode($reference));
    }

    /**
     * Immediate notification when automated checks require a human.
     *
     * @param string               $reference
     * @param array<string, mixed> $summary
     */
    public static function notify_human_review($reference, $summary = array()) {
        $reference = sanitize_text_field((string) $reference);
        if ($reference === '') {
            return;
        }
        self::send_review_email($reference, $summary, 'human_review');
        self::mark_reminder_sent($reference);
    }

    public static function send_pending_reminders() {
        if (!class_exists('PAXdesign_Cybercrime_Intake') || !class_exists('PAXdesign_Cybercrime_Tickets')) {
            return;
        }
        PAXdesign_Cybercrime_Tickets::ensure_schema();

        global $wpdb;
        $table = PAXdesign_Cybercrime_Intake::table_name();
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::PENDING_SECONDS);
        $cooldown = gmdate('Y-m-d H:i:s', time() - self::REMINDER_COOLDOWN);

        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        $has_flag = is_array($columns) && in_array('needs_human_review', $columns, true);
        $has_reminder = is_array($columns) && in_array('last_staff_reminder_at', $columns, true);

        $sql = "SELECT * FROM `$table` WHERE status IN ('submitted','in_review','waiting_for_customer')";
        if ($has_flag) {
            $sql = "SELECT * FROM `$table` WHERE (
                status IN ('submitted','in_review')
                OR (needs_human_review = 1 AND status NOT IN ('resolved','closed'))
            )";
        }
        $sql .= $wpdb->prepare(' AND updated_at <= %s', $cutoff);
        if ($has_reminder) {
            $sql .= $wpdb->prepare(' AND (last_staff_reminder_at IS NULL OR last_staff_reminder_at = %s OR last_staff_reminder_at <= %s)', '0000-00-00 00:00:00', $cooldown);
        }
        $sql .= ' ORDER BY updated_at ASC LIMIT 25';

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $reference = sanitize_text_field((string) ($row['reference_id'] ?? ''));
            if ($reference === '') {
                continue;
            }
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            $summary = is_array($payload) && isset($payload['document_checks']) && is_array($payload['document_checks'])
                ? $payload['document_checks']
                : array();
            self::send_review_email($reference, $summary, 'pending');
            self::mark_reminder_sent($reference);
        }
    }

    /**
     * @param string               $reference
     * @param array<string, mixed> $summary
     * @param string               $reason human_review|pending|submitted
     */
    public static function send_review_email($reference, $summary = array(), $reason = 'pending') {
        $to = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
        if (!is_email($to)) {
            return;
        }

        $url = self::admin_case_url($reference);
        $row = class_exists('PAXdesign_Cybercrime_Tickets')
            ? PAXdesign_Cybercrime_Tickets::get_report_row($reference)
            : null;
        $status = is_array($row) ? (string) ($row['status'] ?? '') : '';
        $category = is_array($row) ? (string) ($row['category'] ?? '') : '';
        $name = is_array($row) ? (string) ($row['reporter_name'] ?? '') : '';

        $why = 'This Cybercrime Support case is waiting for administrator action.';
        if ($reason === 'human_review') {
            $why = 'Automated preliminary document checks flagged this case for administrator review. This is not legal verification.';
        } elseif ($reason === 'submitted') {
            $why = 'A new Cybercrime Support request has been submitted and requires review.';
        }

        $flags = array();
        foreach ((array) ($summary['human_review_reasons'] ?? array()) as $flag) {
            $flag = sanitize_text_field((string) $flag);
            if ($flag !== '') {
                $flags[] = $flag;
            }
        }

        $body = self::EMAIL_SUBJECT . "\n\n"
            . $why . "\n\n"
            . 'Reference: ' . $reference . "\n"
            . 'Status: ' . $status . "\n"
            . 'Category: ' . $category . "\n"
            . 'Reporter: ' . $name . "\n";
        if (!empty($flags)) {
            $body .= 'Review flags: ' . implode(', ', array_slice($flags, 0, 12)) . "\n";
        }
        $body .= "\nOpen the exact case:\n" . $url . "\n";

        wp_mail(
            $to,
            self::EMAIL_SUBJECT,
            $body,
            array('Content-Type: text/plain; charset=UTF-8')
        );
    }

    /**
     * @param string $reference
     */
    public static function mark_reminder_sent($reference) {
        if (!class_exists('PAXdesign_Cybercrime_Intake')) {
            return;
        }
        global $wpdb;
        $table = PAXdesign_Cybercrime_Intake::table_name();
        $now = current_time('mysql', true);
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        if (is_array($columns) && in_array('last_staff_reminder_at', $columns, true)) {
            $wpdb->update(
                $table,
                array('last_staff_reminder_at' => $now),
                array('reference_id' => sanitize_text_field($reference)),
                array('%s'),
                array('%s')
            );
        }
    }
}
