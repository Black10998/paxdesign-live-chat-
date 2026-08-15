<?php
/**
 * Cybercrime Support localization — Arabic, English, German.
 *
 * Admin UI follows the WordPress user locale. Customer-facing copy on the
 * case page still uses the portal language pack; this class supplies shared
 * status labels, rejection reasons, and the administrator dashboard strings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_I18n {

    /**
     * @return string ar|de|en
     */
    public static function lang($locale = '') {
        if ($locale === '') {
            if (function_exists('is_admin') && is_admin() && function_exists('get_user_locale')) {
                $locale = (string) get_user_locale();
            } elseif (function_exists('determine_locale')) {
                $locale = (string) determine_locale();
            } elseif (function_exists('get_locale')) {
                $locale = (string) get_locale();
            }
        }
        $lang = strtolower(substr(str_replace('-', '_', (string) $locale), 0, 2));
        if (in_array($lang, array('ar', 'de', 'en'), true)) {
            return $lang;
        }
        return 'en';
    }

    /**
     * @return string
     */
    public static function dir($lang = '') {
        return self::lang($lang) === 'ar' ? 'rtl' : 'ltr';
    }

    /**
     * Locale sent by the customer case page or chat (ar|de|en).
     *
     * @return string
     */
    public static function request_lang() {
        $raw = '';
        if (isset($_POST['locale'])) {
            $raw = wp_unslash($_POST['locale']);
        } elseif (isset($_REQUEST['locale'])) {
            $raw = wp_unslash($_REQUEST['locale']);
        } elseif (isset($_POST['page_language'])) {
            $raw = wp_unslash($_POST['page_language']);
        } elseif (isset($_POST['lang'])) {
            $raw = wp_unslash($_POST['lang']);
        }
        $lang = strtolower(substr(sanitize_key((string) $raw), 0, 2));
        return in_array($lang, array('ar', 'de', 'en'), true) ? $lang : '';
    }

    /**
     * Customer-facing language. CCS defaults to Arabic when unknown.
     *
     * @param int $user_id
     * @return string ar|de|en
     */
    public static function customer_lang($user_id = 0) {
        $req = self::request_lang();
        if ($req !== '') {
            return $req;
        }
        unset($user_id);
        return 'ar';
    }

    /**
     * @param string $key
     * @return array<string, string>
     */
    public static function pack($key) {
        $out = array();
        foreach (array('ar', 'de', 'en') as $lang) {
            $out[$lang] = self::t($key, $lang);
        }
        return $out;
    }

    /**
     * @param string $status
     * @param string $lang
     * @return string
     */
    public static function next_action_text($status, $lang = '') {
        $status = sanitize_key((string) $status);
        if ($status === 'needs_info') {
            $status = 'waiting_for_customer';
        }
        $key = 'next.' . $status;
        $label = self::t($key, $lang);
        return $label !== $key ? $label : self::t('next.default', $lang);
    }

    /**
     * @param string $text
     * @param string $lang
     * @return string
     */
    public static function localize_canned($text, $lang = '') {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $map = self::canned_english_map();
        if (isset($map[$text])) {
            return self::t($map[$text], $lang);
        }
        $lower = strtolower($text);
        foreach ($map as $english => $key) {
            if (strtolower($english) === $lower) {
                return self::t($key, $lang);
            }
        }
        return $text;
    }

    /**
     * @param array<int, mixed> $items
     * @param string            $lang
     * @return list<string>
     */
    public static function localize_list($items, $lang = '') {
        $out = array();
        foreach ((array) $items as $item) {
            $out[] = self::localize_canned((string) $item, $lang);
        }
        return $out;
    }

    /**
     * @param string $item
     * @param string $lang
     * @return string
     */
    public static function missing_field_label($item, $lang = '') {
        $item = trim((string) $item);
        $map = array(
            'incident type'        => 'field.incident_type',
            'incident_type'        => 'field.incident_type',
            'incident date'        => 'field.incident_date',
            'incident_date'        => 'field.incident_date',
            'affected platforms'   => 'field.platforms',
            'platforms'            => 'field.platforms',
            'incident description' => 'field.description',
            'description'          => 'field.description',
            'identity document'    => 'field.identity_document',
            'identity_document'    => 'field.identity_document',
            'evidence files'       => 'field.evidence_files',
            'evidence_files'       => 'field.evidence_files',
            'full_name'            => 'field.full_name',
            'email'                => 'field.email',
            'phone'                => 'field.phone',
            'country'              => 'field.country',
            'identity_accuracy'    => 'field.identity_accuracy',
            'declarations'         => 'field.declarations',
            'urgency'              => 'field.urgency',
            'financial_loss'       => 'field.financial_loss',
        );
        $key = $map[strtolower($item)] ?? $map[$item] ?? '';
        return $key !== '' ? self::t($key, $lang) : self::localize_canned($item, $lang);
    }

    /**
     * @param string $status
     * @param string $lang
     * @return string
     */
    public static function status_changed_text($status, $lang = '') {
        $label = self::status_label($status, $lang);
        return str_replace('%s', $label, self::t('timeline.status_changed', $lang));
    }

    /**
     * @param string $reference_id
     * @param string $status
     * @param string $lang
     * @return array{title:string,body:string,email_subject:string,email_body:string}
     */
    public static function notification_copy($reference_id, $status, $lang = '') {
        $reference_id = sanitize_text_field((string) $reference_id);
        $label = self::status_label($status, $lang);
        $title = str_replace('%s', $reference_id, self::t('notify.title', $lang));
        $body = str_replace(
            array('%1$s', '%2$s'),
            array($reference_id, $label),
            self::t('notify.body', $lang)
        );
        $subject = str_replace(
            array('%1$s', '%2$s'),
            array($reference_id, $label),
            self::t('notify.email_subject', $lang)
        );
        $email = str_replace(
            array('%1$s', '%2$s'),
            array($reference_id, $label),
            self::t('notify.email_body', $lang)
        );
        return array(
            'title'          => $title,
            'body'           => $body,
            'email_subject'  => $subject,
            'email_body'     => $email,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function canned_english_map() {
        return array(
            'Replace the rejected files on this same case, then wait for administrator review.' => 'next.corrections',
            'Share what happened in chat or continue on this page. Facts are saved to this same case.' => 'next.draft',
            'The team asked for more information. Reply or upload the requested files on this same reference.' => 'next.waiting_for_customer',
            'The PAXDesign team is reviewing this case. No action is required unless they ask for more information.' => 'next.in_review',
            'Your report is received and waiting for administrator review.' => 'next.submitted',
            'This case is resolved. You can review the outcome on this reference.' => 'next.resolved',
            'This case is closed. Start a new report only if you need help with a new incident.' => 'next.closed',
            'This case was rejected. Review the decision on this same reference.' => 'next.rejected',
            'Stay on this reference. The team will update the official conversation when there is news.' => 'next.default',
            'No further action is required on this reference unless you start a new report for a new incident.' => 'decision.next_action',
            'No further action is required on this reference. You can start a new report if you have a new incident.' => 'decision.next_action',
            'These are automated preliminary quality checks, not legal verification. A PAXDesign administrator reviews identity and evidence before a final decision.' => 'checks.disclaimer',
            'Correct the rejected files and resubmit them on this same case. Your reference number will not change.' => 'checks.next_rejected',
            'Your files were received. Some items need administrator review before the case can proceed.' => 'checks.next_review',
            'Your submission is queued for the Cybercrime Support team.' => 'checks.next_ok',
            'An identity document is required.' => 'checks.missing_identity',
            'Please replace the identity document with a readable, complete file.' => 'checks.replace_identity',
            'The file is empty. Please upload a complete scan or photo.' => 'check.issue.empty',
            'The file is too small to be a readable document. Please upload a clearer original.' => 'check.issue.too_small',
            'This file type is not accepted. Please upload a PDF or image of the document.' => 'check.issue.type',
            'Identity documents must be a PDF or photo (JPG, PNG, HEIC).' => 'check.issue.identity_type',
            'This file was uploaded more than once in the same submission. Please remove the duplicate.' => 'check.issue.duplicate',
            'The image could not be opened. Please upload a clear JPG, PNG, or PDF.' => 'check.issue.image_open',
            'The image is too small to read. Please photograph the full document.' => 'check.issue.image_small',
            'The image resolution is low. Please retake the photo so all text is readable.' => 'check.issue.image_res',
            'The photo looks too dark, bright, or blurry. Please retake it in even lighting with the full document in frame.' => 'check.issue.image_quality',
            'The PDF could not be read. Please export a valid PDF or upload a photo of the document.' => 'check.issue.pdf_read',
            'The PDF appears incomplete. Please upload the full document.' => 'check.issue.pdf_incomplete',
            'This document appears to be expired. Please upload a currently valid identity document.' => 'check.issue.expired',
            'The new files did not pass preliminary quality checks. Please correct them and try again on this same case.' => 'error.resubmit_checks',
            'Please attach a file or add a message.' => 'error.empty_update',
            'Please sign in.' => 'error.login',
            'Message is required.' => 'error.message_required',
            'Report not found.' => 'error.not_found',
            'This report is closed.' => 'error.closed',
            'You cannot reply to this report.' => 'error.forbidden',
            'Could not save your message.' => 'error.save_failed',
            'Status changed to %s.' => 'timeline.status_changed',
        );
    }

    /**
     * @param string $key
     * @param string $lang
     * @return string
     */
    public static function t($key, $lang = '') {
        $lang = $lang !== '' ? self::lang($lang) : self::lang();
        $dict = self::strings();
        if (isset($dict[$key][$lang]) && (string) $dict[$key][$lang] !== '') {
            return (string) $dict[$key][$lang];
        }
        if (isset($dict[$key]['en'])) {
            return (string) $dict[$key]['en'];
        }
        return (string) $key;
    }

    /**
     * @param string $status
     * @param string $lang
     * @return string
     */
    public static function status_label($status, $lang = '') {
        $status = sanitize_key((string) $status);
        if ($status === 'needs_info') {
            $status = 'waiting_for_customer';
        }
        $key = 'status.' . $status;
        $label = self::t($key, $lang);
        if ($label === $key) {
            return ucfirst(str_replace('_', ' ', $status));
        }
        return $label;
    }

    /**
     * @param string $category
     * @param string $lang
     * @return string
     */
    public static function category_label($category, $lang = '') {
        $category = sanitize_key((string) $category);
        $key = 'category.' . $category;
        $label = self::t($key, $lang);
        return $label !== $key ? $label : $category;
    }

    /**
     * @return list<string>
     */
    public static function rejection_reason_keys() {
        return array(
            'unclear_document',
            'incomplete_information',
            'unverifiable',
            'duplicate',
            'out_of_scope',
            'other',
        );
    }

    /**
     * @param string $lang
     * @return array<string, string>
     */
    public static function rejection_reasons($lang = '') {
        $out = array();
        foreach (self::rejection_reason_keys() as $key) {
            $out[$key] = self::t('reject.reason.' . $key, $lang);
        }
        return $out;
    }

    /**
     * @param string $reason_key
     * @param string $lang
     * @return string
     */
    public static function rejection_reason_text($reason_key, $lang = '') {
        $reason_key = sanitize_key((string) $reason_key);
        if (!in_array($reason_key, self::rejection_reason_keys(), true)) {
            $reason_key = 'other';
        }
        return self::t('reject.reason.' . $reason_key, $lang);
    }

    /**
     * @param string $reason_key
     * @return array<string, string>
     */
    public static function rejection_reason_i18n($reason_key) {
        $out = array();
        foreach (array('ar', 'de', 'en') as $lang) {
            $out[$lang] = self::rejection_reason_text($reason_key, $lang);
        }
        return $out;
    }

    /**
     * Map workflow status to the customer/admin visual status key.
     *
     * @param string $status
     * @return string
     */
    public static function visual_status_key($status) {
        $status = sanitize_key((string) $status);
        switch ($status) {
            case 'draft':
            case 'collecting':
                return 'collecting';
            case 'waiting_for_customer':
            case 'needs_info':
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
     * Professional Apple-style status icon (inline SVG).
     *
     * @param string $status
     * @param int    $size
     * @return string
     */
    public static function status_icon_svg($status, $size = 18) {
        $key = self::visual_status_key($status);
        $size = max(14, min(32, (int) $size));
        $common = 'class="pax-cc-status-icon pax-cc-status-icon--' . esc_attr($key) . '" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false"';
        if ($key === 'rejected') {
            return '<svg ' . $common . '><circle cx="12" cy="12" r="10" fill="currentColor"/><rect x="11" y="6.2" width="2" height="8.2" rx="1" fill="#fff"/><rect x="11" y="16.2" width="2" height="2.1" rx="1" fill="#fff"/></svg>';
        }
        if ($key === 'resolved') {
            return '<svg ' . $common . '><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M7.2 12.3l3.1 3.1 6.5-6.6" fill="none" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        if ($key === 'waiting_for_customer') {
            return '<svg ' . $common . '><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M12 7.2v5.1l3.2 1.9" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        if ($key === 'collecting') {
            return '<svg ' . $common . '><circle cx="12" cy="12" r="10" fill="currentColor"/><circle cx="12" cy="12" r="3.1" fill="#fff"/></svg>';
        }
        if ($key === 'closed') {
            return '<svg ' . $common . '><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M8.2 8.2l7.6 7.6M15.8 8.2l-7.6 7.6" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';
        }
        return '<svg ' . $common . '><circle cx="12" cy="12" r="10" fill="currentColor"/><circle cx="12" cy="12" r="3.4" fill="none" stroke="#fff" stroke-width="2"/></svg>';
    }

    /**
     * @param string $status
     * @param string $label
     * @return string
     */
    public static function status_badge_html($status, $label = '') {
        $status = sanitize_key((string) $status);
        $visual = self::visual_status_key($status);
        if ($label === '') {
            $label = self::status_label($status);
        }
        return '<span class="pax-cc-status pax-cc-status--' . esc_attr($status) . ' pax-cc-status--visual-' . esc_attr($visual) . '">'
            . self::status_icon_svg($status)
            . '<span class="pax-cc-status__label">' . esc_html($label) . '</span>'
            . '</span>';
    }

    /**
     * @param string $field
     * @param string $lang
     * @return string
     */
    public static function field_label($field, $lang = '') {
        $field = sanitize_key((string) $field);
        if ($field === '') {
            return self::t('detail.evidence_field', $lang);
        }
        $label = self::t('field.' . $field, $lang);
        if ($label !== 'field.' . $field) {
            return $label;
        }
        return ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * @param string $type
     * @param string $lang
     * @return string
     */
    public static function author_label($type, $lang = '') {
        $type = sanitize_key((string) $type);
        $label = self::t('author.' . $type, $lang);
        return $label !== 'author.' . $type ? $label : $type;
    }

    /**
     * @param string $channel
     * @param string $lang
     * @return string
     */
    public static function channel_label($channel, $lang = '') {
        $channel = sanitize_key((string) $channel);
        $label = self::t('channel.' . $channel, $lang);
        return $label !== 'channel.' . $channel ? $label : $channel;
    }

    /**
     * @param string $status
     * @param string $lang
     * @return string
     */
    public static function check_status_label($status, $lang = '') {
        $status = sanitize_key((string) $status);
        if ($status === 'accepted_for_review') {
            $status = 'pass';
        } elseif ($status === 'pending_review') {
            $status = 'review';
        }
        $label = self::t('check.' . $status, $lang);
        return $label !== 'check.' . $status ? $label : $status;
    }

    /**
     * Inline SVG icons keyed by workflow status for the admin JS.
     *
     * @return array<string, string>
     */
    public static function status_icons_for_js() {
        $out = array();
        foreach (array('draft', 'submitted', 'in_review', 'waiting_for_customer', 'resolved', 'closed', 'rejected') as $status) {
            $out[$status] = self::status_icon_svg($status, 16);
        }
        return $out;
    }

    public static function js_admin_strings($lang = '') {
        $keys = array(
            'statusSaved', 'replySent', 'noteAdded', 'saving', 'sending', 'addingNote',
            'error', 'internal', 'noTimeline', 'closeTicket', 'closeConfirm',
            'rejectTicket', 'rejectConfirm', 'rejectReason', 'rejectReasonHelp',
            'rejectExplanation', 'rejectExplanationOther', 'rejectSubmit', 'rejectCancel',
            'rejectRequired', 'rejectExplanationRequired',
            'detail.rejection', 'detail.rejection_by', 'detail.rejection_at',
            'decision.next_action',
            'author.customer', 'author.staff', 'author.system', 'author.ai',
            'channel.portal', 'channel.admin', 'channel.chat', 'channel.email',
        );
        $out = array();
        foreach ($keys as $key) {
            $js_key = str_replace(array('.',), array('_',), $key);
            if (strpos($key, 'author.') === 0) {
                $js_key = 'author_' . substr($key, 7);
            } elseif (strpos($key, 'channel.') === 0) {
                $js_key = 'channel_' . substr($key, 8);
            }
            $out[$js_key] = self::t($key, $lang);
        }
        return $out;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function strings() {
        static $strings = null;
        if (is_array($strings)) {
            return $strings;
        }
        $strings = array(
            'nav.portal' => array(
                'ar' => 'بوابة العملاء',
                'de' => 'Kundenportal',
                'en' => 'Customer Portal',
            ),
            'nav.overview' => array(
                'ar' => 'نظرة عامة',
                'de' => 'Übersicht',
                'en' => 'Overview',
            ),
            'nav.projects' => array(
                'ar' => 'المشاريع',
                'de' => 'Projekte',
                'en' => 'Projects',
            ),
            'nav.orders' => array(
                'ar' => 'الطلبات',
                'de' => 'Anfragen',
                'en' => 'Orders',
            ),
            'nav.news' => array(
                'ar' => 'الأخبار',
                'de' => 'News',
                'en' => 'News',
            ),
            'nav.services' => array(
                'ar' => 'الخدمات',
                'de' => 'Leistungen',
                'en' => 'Services',
            ),
            'nav.notifications' => array(
                'ar' => 'الإشعارات',
                'de' => 'Benachrichtigungen',
                'en' => 'Notifications',
            ),
            'nav.cybercrime' => array(
                'ar' => 'دعم الجرائم الإلكترونية',
                'de' => 'Cybercrime Support',
                'en' => 'Cybercrime Support',
            ),
            'nav.unread' => array(
                'ar' => 'بلاغات غير مقروءة',
                'de' => 'Ungelesene Meldungen',
                'en' => 'Unread reports',
            ),
            'nav.back' => array(
                'ar' => 'كل البلاغات',
                'de' => 'Alle Meldungen',
                'en' => 'All reports',
            ),
            'list.title' => array(
                'ar' => 'دعم الجرائم الإلكترونية',
                'de' => 'Cybercrime Support',
                'en' => 'Cybercrime Support',
            ),
            'list.subtitle' => array(
                'ar' => 'إدارة بلاغات العملاء عبر مسار واضح: جديد → قيد المراجعة → بانتظار العميل → تمت الموافقة → مغلق. البلاغات التي تحتاج مراجعة بشرية تظهر في التنبيهات.',
                'de' => 'Kundenmeldungen im klaren Ablauf: Neu → In Prüfung → Wartet auf Kunden → Genehmigt → Geschlossen. Fälle mit menschlicher Prüfung erscheinen unter Hinweise.',
                'en' => 'Manage customer reports through a clear workflow: New → In Review → Waiting for Customer → Approved → Closed. Cases flagged for human review appear in Alerts.',
            ),
            'list.reference' => array(
                'ar' => 'المرجع',
                'de' => 'Referenz',
                'en' => 'Reference',
            ),
            'list.reporter' => array(
                'ar' => 'مقدّم البلاغ',
                'de' => 'Meldende Person',
                'en' => 'Reporter',
            ),
            'list.category' => array(
                'ar' => 'نوع الحادثة',
                'de' => 'Vorfallstyp',
                'en' => 'Incident type',
            ),
            'list.incident_date' => array(
                'ar' => 'تاريخ الحادثة',
                'de' => 'Vorfallsdatum',
                'en' => 'Incident date',
            ),
            'list.status' => array(
                'ar' => 'الحالة',
                'de' => 'Status',
                'en' => 'Status',
            ),
            'list.updated' => array(
                'ar' => 'آخر تحديث',
                'de' => 'Aktualisiert',
                'en' => 'Updated',
            ),
            'list.alerts' => array(
                'ar' => 'التنبيهات',
                'de' => 'Hinweise',
                'en' => 'Alerts',
            ),
            'list.empty' => array(
                'ar' => 'لا توجد بلاغات بعد.',
                'de' => 'Noch keine Meldungen.',
                'en' => 'No reports yet.',
            ),
            'alerts.human_review' => array(
                'ar' => 'يحتاج مراجعة بشرية',
                'de' => 'Menschliche Prüfung erforderlich',
                'en' => 'Needs human review',
            ),
            'detail.reporter' => array(
                'ar' => 'مقدّم البلاغ',
                'de' => 'Meldende Person',
                'en' => 'Reporter',
            ),
            'detail.ccs_reference' => array(
                'ar' => 'مرجع CCS',
                'de' => 'CCS-Referenz',
                'en' => 'CCS reference',
            ),
            'detail.incident_type' => array(
                'ar' => 'نوع الحادثة',
                'de' => 'Vorfallstyp',
                'en' => 'Incident type',
            ),
            'detail.incident_date' => array(
                'ar' => 'تاريخ الحادثة',
                'de' => 'Vorfallsdatum',
                'en' => 'Incident date',
            ),
            'detail.platforms' => array(
                'ar' => 'المنصات المتأثرة',
                'de' => 'Betroffene Plattformen',
                'en' => 'Affected platforms',
            ),
            'detail.financial_loss' => array(
                'ar' => 'الخسارة المالية',
                'de' => 'Finanzieller Verlust',
                'en' => 'Financial loss',
            ),
            'detail.status' => array(
                'ar' => 'الحالة',
                'de' => 'Status',
                'en' => 'Status',
            ),
            'detail.verification' => array(
                'ar' => 'حالة التحقق',
                'de' => 'Prüfstatus',
                'en' => 'Verification',
            ),
            'detail.submitted' => array(
                'ar' => 'تاريخ التقديم',
                'de' => 'Eingereicht',
                'en' => 'Submitted',
            ),
            'detail.updated' => array(
                'ar' => 'آخر تحديث',
                'de' => 'Aktualisiert',
                'en' => 'Updated',
            ),
            'detail.review' => array(
                'ar' => 'المراجعة',
                'de' => 'Prüfung',
                'en' => 'Review',
            ),
            'detail.missing' => array(
                'ar' => 'المطلوب / الناقص',
                'de' => 'Erforderlich / fehlend',
                'en' => 'Required / missing',
            ),
            'detail.next_action' => array(
                'ar' => 'الإجراء التالي: ',
                'de' => 'Nächster Schritt: ',
                'en' => 'Next action: ',
            ),
            'detail.ai_processing' => array(
                'ar' => 'معالجة الذكاء الاصطناعي: ',
                'de' => 'KI-Verarbeitung: ',
                'en' => 'AI processing: ',
            ),
            'detail.narrative' => array(
                'ar' => 'رواية العميل (عرض)',
                'de' => 'Kundenbericht (erweitern)',
                'en' => 'Customer narrative (expand)',
            ),
            'detail.summary' => array(
                'ar' => 'ملخص الحالة',
                'de' => 'Fallzusammenfassung',
                'en' => 'Case summary',
            ),
            'detail.workflow' => array(
                'ar' => 'مسار العمل',
                'de' => 'Workflow',
                'en' => 'Workflow',
            ),
            'detail.activity' => array(
                'ar' => 'النشاط الداخلي',
                'de' => 'Interne Aktivität',
                'en' => 'Internal activity',
            ),
            'detail.activity_hint' => array(
                'ar' => 'هذه المؤشرات للفريق فقط — يرى العميل شارات حالة مبسطة.',
                'de' => 'Diese Hinweise sind nur für das Team — Kunden sehen vereinfachte Statusanzeigen.',
                'en' => 'These indicators are for staff only — customers see simplified status badges.',
            ),
            'detail.checks' => array(
                'ar' => 'فحوصات المستندات الأولية',
                'de' => 'Vorläufige Dokumentprüfung',
                'en' => 'Preliminary document checks',
            ),
            'detail.checks_disclaimer' => array(
                'ar' => 'فحوصات جودة آلية فقط — ليست تحققًا قانونيًا. العناصر غير الواضحة أو غير المتسقة تتطلب حكمك.',
                'de' => 'Nur automatische Qualitätsprüfungen — keine rechtliche Verifizierung. Unklare oder widersprüchliche Punkte erfordern Ihre Beurteilung.',
                'en' => 'Automated quality checks only — not legal verification. Uncertain, inconsistent, or suspicious items require your judgment.',
            ),
            'detail.flags' => array(
                'ar' => 'العلامات',
                'de' => 'Hinweise',
                'en' => 'Flags',
            ),
            'detail.expired' => array(
                'ar' => 'يبدو منتهي الصلاحية',
                'de' => 'Sieht abgelaufen aus',
                'en' => 'Appears expired',
            ),
            'detail.evidence' => array(
                'ar' => 'الأدلة / المستندات',
                'de' => 'Beweise / Dokumente',
                'en' => 'Evidence / documents',
            ),
            'detail.timeline' => array(
                'ar' => 'الجدول الزمني الداخلي',
                'de' => 'Interne Zeitleiste',
                'en' => 'Internal timeline',
            ),
            'detail.actions' => array(
                'ar' => 'إجراءات التذكرة',
                'de' => 'Ticketaktionen',
                'en' => 'Ticket actions',
            ),
            'detail.workflow_status' => array(
                'ar' => 'حالة المسار',
                'de' => 'Workflow-Status',
                'en' => 'Workflow status',
            ),
            'detail.status_hint' => array(
                'ar' => 'يُحفظ التغيير تلقائيًا. إغلاق التذكرة يسمح للعميل ببدء بلاغ جديد.',
                'de' => 'Änderungen werden automatisch gespeichert. Das Schließen erlaubt dem Kunden eine neue Meldung.',
                'en' => 'Changes save automatically. Closing a ticket lets the customer start a new report.',
            ),
            'detail.reply' => array(
                'ar' => 'الرد على العميل',
                'de' => 'Antwort an den Kunden',
                'en' => 'Reply to customer',
            ),
            'detail.message' => array(
                'ar' => 'الرسالة',
                'de' => 'Nachricht',
                'en' => 'Message',
            ),
            'detail.after_reply' => array(
                'ar' => 'بعد الرد، عيّن الحالة إلى',
                'de' => 'Nach der Antwort Status setzen auf',
                'en' => 'After reply, set status to',
            ),
            'detail.reply_hint' => array(
                'ar' => 'يظهر الرد فورًا في الجدول الزمني للعميل.',
                'de' => 'Die Antwort erscheint sofort in der Kundenzeitleiste.',
                'en' => 'The reply appears on the customer timeline immediately.',
            ),
            'detail.send_reply' => array(
                'ar' => 'إرسال الرد',
                'de' => 'Antwort senden',
                'en' => 'Send reply',
            ),
            'detail.internal_note' => array(
                'ar' => 'ملاحظة داخلية (للفريق فقط)',
                'de' => 'Interne Notiz (nur Team)',
                'en' => 'Internal note (staff only)',
            ),
            'detail.note' => array(
                'ar' => 'الملاحظة',
                'de' => 'Notiz',
                'en' => 'Note',
            ),
            'detail.note_hint' => array(
                'ar' => 'غير مرئية للعميل. للتنسيق بين الفريق فقط.',
                'de' => 'Für den Kunden nicht sichtbar. Nur zur Teamabstimmung.',
                'en' => 'Not visible to the customer. Use for staff coordination only.',
            ),
            'detail.add_note' => array(
                'ar' => 'إضافة ملاحظة داخلية',
                'de' => 'Interne Notiz hinzufügen',
                'en' => 'Add internal note',
            ),
            'detail.rejection' => array(
                'ar' => 'قرار الرفض',
                'de' => 'Ablehnungsentscheidung',
                'en' => 'Rejection decision',
            ),
            'detail.rejection_by' => array(
                'ar' => 'قرار المسؤول',
                'de' => 'Entscheidung durch',
                'en' => 'Administrator decision',
            ),
            'detail.rejection_at' => array(
                'ar' => 'التاريخ والوقت',
                'de' => 'Datum / Uhrzeit',
                'en' => 'Date / time',
            ),
            'detail.none' => array(
                'ar' => '—',
                'de' => '—',
                'en' => '—',
            ),
            'detail.no' => array(
                'ar' => 'لا',
                'de' => 'Nein',
                'en' => 'No',
            ),
            'detail.document' => array(
                'ar' => 'مستند',
                'de' => 'Dokument',
                'en' => 'Document',
            ),
            'detail.open_download' => array(
                'ar' => 'فتح / تنزيل',
                'de' => 'Öffnen / herunterladen',
                'en' => 'Open / download',
            ),
            'detail.evidence_field' => array(
                'ar' => 'دليل',
                'de' => 'Beweis',
                'en' => 'Evidence',
            ),
            'check.readable' => array(
                'ar' => 'واضح',
                'de' => 'Lesbar',
                'en' => 'Readable',
            ),
            'check.invalid' => array(
                'ar' => 'غير صالح / غير مكتمل',
                'de' => 'Ungültig / unvollständig',
                'en' => 'Invalid / incomplete',
            ),
            'check.review' => array(
                'ar' => 'يحتاج مراجعة إضافية',
                'de' => 'Weitere Prüfung nötig',
                'en' => 'Needs further review',
            ),
            'verify.review' => array(
                'ar' => 'يحتاج مراجعة إضافية',
                'de' => 'Weitere Prüfung nötig',
                'en' => 'Needs further review',
            ),
            'verify.invalid' => array(
                'ar' => 'مستندات غير صالحة أو غير مكتملة',
                'de' => 'Dokumente ungültig oder unvollständig',
                'en' => 'Documents invalid or incomplete',
            ),
            'verify.pending_evidence' => array(
                'ar' => 'بانتظار الأدلة',
                'de' => 'Beweise ausstehend',
                'en' => 'Pending evidence',
            ),
            'verify.awaiting' => array(
                'ar' => 'بانتظار الفحوصات الأولية',
                'de' => 'Vorprüfung ausstehend',
                'en' => 'Awaiting preliminary checks',
            ),
            'verify.complete' => array(
                'ar' => 'اكتملت الفحوصات الأولية',
                'de' => 'Vorprüfung abgeschlossen',
                'en' => 'Preliminary checks complete',
            ),
            'field.identity_document' => array(
                'ar' => 'وثيقة الهوية',
                'de' => 'Ausweisdokument',
                'en' => 'Identity document',
            ),
            'field.full_name' => array(
                'ar' => 'الاسم القانوني الكامل',
                'de' => 'Vollständiger gesetzlicher Name',
                'en' => 'Full legal name',
            ),
            'field.email' => array(
                'ar' => 'البريد الإلكتروني',
                'de' => 'E-Mail-Adresse',
                'en' => 'Email address',
            ),
            'field.phone' => array(
                'ar' => 'رقم الهاتف',
                'de' => 'Telefonnummer',
                'en' => 'Phone number',
            ),
            'field.country' => array(
                'ar' => 'البلد',
                'de' => 'Land',
                'en' => 'Country',
            ),
            'field.identity_accuracy' => array(
                'ar' => 'تأكيد صحة بيانات الهوية',
                'de' => 'Bestätigung der Identitätsangaben',
                'en' => 'Identity accuracy confirmation',
            ),
            'field.declarations' => array(
                'ar' => 'إقرارات المراجعة',
                'de' => 'Prüferklärungen',
                'en' => 'Review declarations',
            ),
            'field.urgency' => array(
                'ar' => 'درجة الاستعجال',
                'de' => 'Dringlichkeit',
                'en' => 'Urgency',
            ),
            'field.financial_loss' => array(
                'ar' => 'الخسارة المالية',
                'de' => 'Finanzieller Verlust',
                'en' => 'Financial loss',
            ),
            'field.evidence_other' => array(
                'ar' => 'أدلة أخرى',
                'de' => 'Weitere Beweise',
                'en' => 'Other evidence',
            ),
            'field.evidence_screenshot' => array(
                'ar' => 'لقطة شاشة',
                'de' => 'Screenshot',
                'en' => 'Screenshot',
            ),
            'closeTicket' => array(
                'ar' => 'إغلاق التذكرة',
                'de' => 'Ticket schließen',
                'en' => 'Close ticket',
            ),
            'closeConfirm' => array(
                'ar' => 'إغلاق هذه التذكرة؟ يمكن للعميل بدء بلاغ جديد.',
                'de' => 'Dieses Ticket schließen? Der Kunde kann eine neue Meldung starten.',
                'en' => 'Close this ticket? The customer can start a new report.',
            ),
            'rejectTicket' => array(
                'ar' => 'رفض الحالة',
                'de' => 'Fall ablehnen',
                'en' => 'Reject case',
            ),
            'rejectConfirm' => array(
                'ar' => 'رفض هذه الحالة؟ سيظهر للعميل سبب الرفض على نفس مرجع CCS.',
                'de' => 'Diesen Fall ablehnen? Der Kunde sieht den Ablehnungsgrund auf derselben CCS-Referenz.',
                'en' => 'Reject this case? The customer will see the rejection reason on this same CCS reference.',
            ),
            'rejectReason' => array(
                'ar' => 'سبب الرفض',
                'de' => 'Ablehnungsgrund',
                'en' => 'Rejection reason',
            ),
            'rejectReasonHelp' => array(
                'ar' => 'اختر سببًا واضحًا. سيظهر للعميل على صفحة الحالة نفسها.',
                'de' => 'Wählen Sie einen klaren Grund. Der Kunde sieht ihn auf derselben Fallseite.',
                'en' => 'Choose a clear reason. The customer will see it on the same case page.',
            ),
            'rejectExplanation' => array(
                'ar' => 'شرح إضافي (اختياري)',
                'de' => 'Zusätzliche Erklärung (optional)',
                'en' => 'Additional explanation (optional)',
            ),
            'rejectExplanationOther' => array(
                'ar' => 'شرح إضافي (مطلوب)',
                'de' => 'Zusätzliche Erklärung (erforderlich)',
                'en' => 'Additional explanation (required)',
            ),
            'rejectExplanationRequired' => array(
                'ar' => 'يرجى إدخال شرح إضافي عند اختيار سبب آخر.',
                'de' => 'Bitte eine zusätzliche Erklärung angeben.',
                'en' => 'Please enter an additional explanation for this reason.',
            ),
            'decision.next_action' => array(
                'ar' => 'لا يلزم إجراء إضافي على هذا المرجع. يمكنك بدء بلاغ جديد إذا كانت لديك حادثة جديدة.',
                'de' => 'Für diese Referenz ist keine weitere Aktion erforderlich. Starten Sie eine neue Meldung, wenn ein neuer Vorfall vorliegt.',
                'en' => 'No further action is required on this reference. You can start a new report if you have a new incident.',
            ),
            'module.unavailable' => array(
                'ar' => 'وحدة تذاكر الجرائم الإلكترونية غير متاحة.',
                'de' => 'Das Cybercrime-Ticketmodul ist nicht verfügbar.',
                'en' => 'Cybercrime ticket module is not available.',
            ),
            'field.incident_type' => array(
                'ar' => 'نوع الحادثة',
                'de' => 'Vorfallstyp',
                'en' => 'incident type',
            ),
            'field.incident_date' => array(
                'ar' => 'تاريخ الحادثة',
                'de' => 'Vorfallsdatum',
                'en' => 'incident date',
            ),
            'field.platforms' => array(
                'ar' => 'المنصات المتأثرة',
                'de' => 'betroffene Plattformen',
                'en' => 'affected platforms',
            ),
            'field.description' => array(
                'ar' => 'وصف الحادثة',
                'de' => 'Vorfallsbeschreibung',
                'en' => 'incident description',
            ),
            'field.evidence_files' => array(
                'ar' => 'ملفات الأدلة',
                'de' => 'Beweismittel',
                'en' => 'evidence files',
            ),
            'activity.needs_human_review' => array(
                'ar' => 'يحتاج مراجعة بشرية (فحوصات المستندات الأولية)',
                'de' => 'Menschliche Prüfung erforderlich (vorläufige Dokumentprüfung)',
                'en' => 'Needs human review (preliminary document checks)',
            ),
            'activity.customer_replied' => array(
                'ar' => 'رد العميل — بانتظار المراجعة',
                'de' => 'Kunde hat geantwortet — Prüfung ausstehend',
                'en' => 'Customer replied — review pending',
            ),
            'activity.waiting_staff' => array(
                'ar' => 'بانتظار إجراء الفريق',
                'de' => 'Wartet auf Teamaktion',
                'en' => 'Waiting for staff action',
            ),
            'activity.latest_customer' => array(
                'ar' => 'آخر نشاط: رد العميل',
                'de' => 'Letzte Aktivität: Kundenantwort',
                'en' => 'Latest activity: customer reply',
            ),
            'error.message_required' => array(
                'ar' => 'الرسالة مطلوبة.',
                'de' => 'Nachricht ist erforderlich.',
                'en' => 'Message is required.',
            ),
            'error.invalid_status' => array(
                'ar' => 'حالة غير صالحة.',
                'de' => 'Ungültiger Status.',
                'en' => 'Invalid status.',
            ),
            'error.status_failed' => array(
                'ar' => 'تعذر تحديث الحالة.',
                'de' => 'Status konnte nicht aktualisiert werden.',
                'en' => 'Unable to update status.',
            ),
            'rejectSubmit' => array(
                'ar' => 'تأكيد الرفض',
                'de' => 'Ablehnung bestätigen',
                'en' => 'Confirm rejection',
            ),
            'rejectCancel' => array(
                'ar' => 'إلغاء',
                'de' => 'Abbrechen',
                'en' => 'Cancel',
            ),
            'rejectRequired' => array(
                'ar' => 'يرجى اختيار سبب الرفض.',
                'de' => 'Bitte einen Ablehnungsgrund wählen.',
                'en' => 'Please choose a rejection reason.',
            ),
            'reject.reason.unclear_document' => array(
                'ar' => 'المستند المرفوع غير واضح ولا يمكن التحقق من المعلومات المطلوبة.',
                'de' => 'Das hochgeladene Dokument ist unleserlich. Die erforderlichen Angaben können nicht geprüft werden.',
                'en' => 'The uploaded document is unclear and the required information cannot be verified.',
            ),
            'reject.reason.incomplete_information' => array(
                'ar' => 'المعلومات المقدَّمة غير مكتملة.',
                'de' => 'Die angegebenen Informationen sind unvollständig.',
                'en' => 'The information provided is incomplete.',
            ),
            'reject.reason.unverifiable' => array(
                'ar' => 'تعذر التحقق من صحة البلاغ.',
                'de' => 'Der Bericht konnte nicht verifiziert werden.',
                'en' => 'The report could not be verified.',
            ),
            'reject.reason.duplicate' => array(
                'ar' => 'هذا البلاغ مكرر لحالة قائمة على نفس المرجع أو حادثة سابقة.',
                'de' => 'Diese Meldung ist ein Duplikat eines bestehenden Falls.',
                'en' => 'This report duplicates an existing case.',
            ),
            'reject.reason.out_of_scope' => array(
                'ar' => 'الطلب خارج نطاق دعم الجرائم الإلكترونية.',
                'de' => 'Die Anfrage liegt außerhalb des Cybercrime-Supports.',
                'en' => 'This request is outside the scope of Cybercrime Support.',
            ),
            'reject.reason.other' => array(
                'ar' => 'سبب آخر — راجع الشرح الإضافي.',
                'de' => 'Anderer Grund — siehe zusätzliche Erklärung.',
                'en' => 'Other reason — see the additional explanation.',
            ),
            'status.draft' => array(
                'ar' => 'جارٍ جمع المعلومات',
                'de' => 'Angaben werden erfasst',
                'en' => 'Collecting',
            ),
            'status.submitted' => array(
                'ar' => 'جديد',
                'de' => 'Neu',
                'en' => 'New',
            ),
            'status.in_review' => array(
                'ar' => 'قيد المراجعة',
                'de' => 'In Prüfung',
                'en' => 'In Review',
            ),
            'status.waiting_for_customer' => array(
                'ar' => 'بانتظار العميل',
                'de' => 'Wartet auf Kunden',
                'en' => 'Waiting for Customer',
            ),
            'status.resolved' => array(
                'ar' => 'تمت الموافقة',
                'de' => 'Genehmigt',
                'en' => 'Approved',
            ),
            'status.closed' => array(
                'ar' => 'مغلق',
                'de' => 'Geschlossen',
                'en' => 'Closed',
            ),
            'status.rejected' => array(
                'ar' => 'مرفوض',
                'de' => 'Abgelehnt',
                'en' => 'Rejected',
            ),
            'status.desc.draft' => array(
                'ar' => 'يتم جمع المعلومات لهذه الحالة',
                'de' => 'Angaben zu diesem Fall werden erfasst',
                'en' => 'Information is being collected on this case',
            ),
            'status.desc.submitted' => array(
                'ar' => 'تم استلام بلاغ جديد',
                'de' => 'Neue Meldung eingegangen',
                'en' => 'New report received',
            ),
            'status.desc.in_review' => array(
                'ar' => 'الفريق يراجع البلاغ',
                'de' => 'Das Team prüft die Meldung',
                'en' => 'Team is analyzing the report',
            ),
            'status.desc.waiting_for_customer' => array(
                'ar' => 'مطلوب معلومات من العميل',
                'de' => 'Kundenangaben sind erforderlich',
                'en' => 'Customer information is required',
            ),
            'status.desc.resolved' => array(
                'ar' => 'تمت الموافقة على الحالة',
                'de' => 'Der Fall wurde genehmigt',
                'en' => 'The case was approved',
            ),
            'status.desc.closed' => array(
                'ar' => 'اكتملت التذكرة',
                'de' => 'Ticket abgeschlossen',
                'en' => 'Ticket completed',
            ),
            'status.desc.rejected' => array(
                'ar' => 'تم رفض هذه الحالة',
                'de' => 'Dieser Fall wurde abgelehnt',
                'en' => 'This case was rejected',
            ),
            'category.account_takeover' => array(
                'ar' => 'الاستيلاء على الحساب',
                'de' => 'Kontoübernahme',
                'en' => 'Account takeover',
            ),
            'category.phishing_fraud' => array(
                'ar' => 'تصيّد / احتيال',
                'de' => 'Phishing / Betrug',
                'en' => 'Phishing / fraud',
            ),
            'category.identity_theft' => array(
                'ar' => 'سرقة الهوية',
                'de' => 'Identitätsdiebstahl',
                'en' => 'Identity theft',
            ),
            'category.malware_ransomware' => array(
                'ar' => 'برمجيات خبيثة / فدية',
                'de' => 'Malware / Ransomware',
                'en' => 'Malware / ransomware',
            ),
            'category.social_media_recovery' => array(
                'ar' => 'استرداد حساب تواصل اجتماعي',
                'de' => 'Wiederherstellung sozialer Konten',
                'en' => 'Social media recovery',
            ),
            'category.financial_fraud' => array(
                'ar' => 'احتيال مالي',
                'de' => 'Finanzbetrug',
                'en' => 'Financial fraud',
            ),
            'category.data_breach' => array(
                'ar' => 'تسريب بيانات',
                'de' => 'Datenleck',
                'en' => 'Data breach',
            ),
            'category.other' => array(
                'ar' => 'حادثة إلكترونية أخرى',
                'de' => 'Anderer Cybervorfall',
                'en' => 'Other cyber incident',
            ),
            'author.customer' => array(
                'ar' => 'العميل',
                'de' => 'Kunde',
                'en' => 'Customer',
            ),
            'author.staff' => array(
                'ar' => 'المسؤول',
                'de' => 'Administrator',
                'en' => 'Administrator',
            ),
            'author.system' => array(
                'ar' => 'النظام',
                'de' => 'System',
                'en' => 'System',
            ),
            'author.ai' => array(
                'ar' => 'المساعد الذكي',
                'de' => 'KI-Assistent',
                'en' => 'AI assistant',
            ),
            'channel.portal' => array(
                'ar' => 'البوابة',
                'de' => 'Portal',
                'en' => 'Portal',
            ),
            'channel.admin' => array(
                'ar' => 'لوحة التحكم',
                'de' => 'Admin',
                'en' => 'Admin',
            ),
            'channel.chat' => array(
                'ar' => 'الدردشة',
                'de' => 'Chat',
                'en' => 'Chat',
            ),
            'channel.email' => array(
                'ar' => 'البريد الإلكتروني',
                'de' => 'E-Mail',
                'en' => 'Email',
            ),
            'internal' => array(
                'ar' => 'داخلي',
                'de' => 'intern',
                'en' => 'internal',
            ),
            'noTimeline' => array(
                'ar' => 'لا توجد إدخالات في الجدول الزمني بعد.',
                'de' => 'Noch keine Zeitleisten-Einträge.',
                'en' => 'No timeline entries yet.',
            ),
            'statusSaved' => array(
                'ar' => 'تم حفظ الحالة.',
                'de' => 'Status gespeichert.',
                'en' => 'Status saved.',
            ),
            'replySent' => array(
                'ar' => 'تم إرسال الرد إلى العميل.',
                'de' => 'Antwort an den Kunden gesendet.',
                'en' => 'Reply sent to customer.',
            ),
            'noteAdded' => array(
                'ar' => 'تمت إضافة الملاحظة الداخلية.',
                'de' => 'Interne Notiz hinzugefügt.',
                'en' => 'Internal note added.',
            ),
            'saving' => array(
                'ar' => 'جارٍ الحفظ…',
                'de' => 'Wird gespeichert…',
                'en' => 'Saving…',
            ),
            'sending' => array(
                'ar' => 'جارٍ الإرسال…',
                'de' => 'Wird gesendet…',
                'en' => 'Sending…',
            ),
            'addingNote' => array(
                'ar' => 'جارٍ إضافة الملاحظة…',
                'de' => 'Notiz wird hinzugefügt…',
                'en' => 'Adding note…',
            ),
            'error' => array(
                'ar' => 'حدث خطأ. يرجى المحاولة مرة أخرى.',
                'de' => 'Etwas ist schiefgelaufen. Bitte erneut versuchen.',
                'en' => 'Something went wrong. Please try again.',
            ),
            'error.permissions' => array(
                'ar' => 'صلاحيات غير كافية.',
                'de' => 'Unzureichende Berechtigungen.',
                'en' => 'Insufficient permissions.',
            ),
            'error.not_found' => array(
                'ar' => 'لم يتم العثور على البلاغ.',
                'de' => 'Meldung nicht gefunden.',
                'en' => 'Report not found.',
            ),
            'notice.saved' => array(
                'ar' => 'تم الحفظ.',
                'de' => 'Gespeichert.',
                'en' => 'Saved.',
            ),
            'step' => array(
                'ar' => 'الخطوة %d',
                'de' => 'Schritt %d',
                'en' => 'Step %d',
            ),
            'check.pass' => array(
                'ar' => 'مقبول',
                'de' => 'Bestanden',
                'en' => 'Pass',
            ),
            'check.fail' => array(
                'ar' => 'مرفوض',
                'de' => 'Fehlgeschlagen',
                'en' => 'Fail',
            ),
            'check.rejected' => array(
                'ar' => 'مرفوض',
                'de' => 'Abgelehnt',
                'en' => 'Rejected',
            ),
            'empty.dash' => array(
                'ar' => '—',
                'de' => '—',
                'en' => '—',
            ),
            'next.draft' => array(
                'ar' => 'شارك ما حدث في المحادثة أو أكمل هذه الصفحة. تُحفظ المعلومات على نفس البلاغ.',
                'de' => 'Beschreiben Sie den Vorfall im Chat oder setzen Sie diese Seite fort. Die Angaben werden auf derselben Meldung gespeichert.',
                'en' => 'Share what happened in chat or continue on this page. Facts are saved to this same case.',
            ),
            'next.submitted' => array(
                'ar' => 'تم استلام بلاغك وهو بانتظار مراجعة المسؤول.',
                'de' => 'Ihre Meldung ist eingegangen und wartet auf die Prüfung durch einen Administrator.',
                'en' => 'Your report is received and waiting for administrator review.',
            ),
            'next.in_review' => array(
                'ar' => 'فريق PAXDesign يراجع هذا البلاغ. لا يلزم إجراء إلا إذا طُلب منك المزيد.',
                'de' => 'Das PAXDesign-Team prüft diesen Fall. Es ist keine Aktion nötig, außer es werden weitere Angaben angefordert.',
                'en' => 'The PAXDesign team is reviewing this case. No action is required unless they ask for more information.',
            ),
            'next.waiting_for_customer' => array(
                'ar' => 'طلب الفريق معلومات إضافية. يرجى الرد أو رفع الملفات المطلوبة على نفس المرجع.',
                'de' => 'Das Team hat weitere Angaben angefordert. Antworten Sie oder laden Sie die gewünschten Dateien auf derselben Referenz hoch.',
                'en' => 'The team asked for more information. Reply or upload the requested files on this same reference.',
            ),
            'next.resolved' => array(
                'ar' => 'تمت الموافقة على هذا البلاغ. يمكنك مراجعة النتيجة على هذا المرجع.',
                'de' => 'Dieser Fall wurde genehmigt. Sie können das Ergebnis auf dieser Referenz einsehen.',
                'en' => 'This case is resolved. You can review the outcome on this reference.',
            ),
            'next.closed' => array(
                'ar' => 'هذا البلاغ مغلق. ابدأ بلاغاً جديداً فقط إذا كنت بحاجة إلى مساعدة في حادثة جديدة.',
                'de' => 'Dieser Fall ist geschlossen. Starten Sie nur bei einem neuen Vorfall eine neue Meldung.',
                'en' => 'This case is closed. Start a new report only if you need help with a new incident.',
            ),
            'next.rejected' => array(
                'ar' => 'تم رفض هذا البلاغ. راجع قرار الرفض على نفس المرجع.',
                'de' => 'Dieser Fall wurde abgelehnt. Prüfen Sie die Entscheidung auf derselben Referenz.',
                'en' => 'This case was rejected. Review the decision on this same reference.',
            ),
            'next.corrections' => array(
                'ar' => 'استبدل الملفات المرفوضة على نفس البلاغ، ثم انتظر مراجعة المسؤول.',
                'de' => 'Ersetzen Sie die abgelehnten Dateien auf demselben Fall und warten Sie auf die Administratorprüfung.',
                'en' => 'Replace the rejected files on this same case, then wait for administrator review.',
            ),
            'next.default' => array(
                'ar' => 'ابقَ على هذا المرجع. سيحدّث الفريق المحادثة الرسمية عند وجود جديد.',
                'de' => 'Bleiben Sie bei dieser Referenz. Das Team aktualisiert die offizielle Unterhaltung, sobald es Neuigkeiten gibt.',
                'en' => 'Stay on this reference. The team will update the official conversation when there is news.',
            ),
            'timeline.status_changed' => array(
                'ar' => 'تم تغيير الحالة إلى %s.',
                'de' => 'Status geändert zu %s.',
                'en' => 'Status changed to %s.',
            ),
            'notify.title' => array(
                'ar' => 'تحديث بلاغ الجرائم الإلكترونية %s',
                'de' => 'Cybercrime-Meldung %s aktualisiert',
                'en' => 'Cybercrime report %s updated',
            ),
            'notify.body' => array(
                'ar' => 'تم تحديث بلاغك %1$s. الحالة الحالية: %2$s.',
                'de' => 'Ihre Meldung %1$s wurde aktualisiert. Aktueller Status: %2$s.',
                'en' => 'Your report %1$s was updated. Current status: %2$s.',
            ),
            'notify.email_subject' => array(
                'ar' => '[بلاغ الجرائم الإلكترونية %1$s] %2$s',
                'de' => '[Cybercrime-Meldung %1$s] %2$s',
                'en' => '[Cybercrime Report %1$s] %2$s',
            ),
            'notify.email_body' => array(
                'ar' => "تحديث دعم الجرائم الإلكترونية\n\nالمرجع: %1\$s\nالحالة: %2\$s\n\nيمكنك عرض بلاغك من صفحة دعم الجرائم الإلكترونية.",
                'de' => "Update zum Cybercrime Support\n\nReferenz: %1\$s\nStatus: %2\$s\n\nSie können Ihre Meldung auf der Cybercrime-Support-Seite einsehen.",
                'en' => "Cybercrime Support update\n\nReference: %1\$s\nStatus: %2\$s\n\nView your report on the Cybercrime Support page.",
            ),
            'notify.reply_title' => array(
                'ar' => 'رد جديد على بلاغ الجرائم الإلكترونية %s',
                'de' => 'Neue Antwort auf Cybercrime-Meldung %s',
                'en' => 'New reply on cybercrime report %s',
            ),
            'checks.disclaimer' => array(
                'ar' => 'هذه فحوصات جودة أولية آلية وليست تحققاً قانونياً. يراجع مسؤول PAXDesign الهوية والأدلة قبل القرار النهائي.',
                'de' => 'Dies sind automatisierte vorläufige Qualitätsprüfungen, keine rechtliche Verifizierung. Ein PAXDesign-Administrator prüft Identität und Beweise vor der endgültigen Entscheidung.',
                'en' => 'These are automated preliminary quality checks, not legal verification. A PAXDesign administrator reviews identity and evidence before a final decision.',
            ),
            'checks.next_rejected' => array(
                'ar' => 'صحّح الملفات المرفوضة وأعد إرسالها على نفس البلاغ. لن يتغيّر رقم المرجع.',
                'de' => 'Korrigieren Sie die abgelehnten Dateien und senden Sie sie auf demselben Fall erneut. Ihre Referenznummer bleibt gleich.',
                'en' => 'Correct the rejected files and resubmit them on this same case. Your reference number will not change.',
            ),
            'checks.next_review' => array(
                'ar' => 'تم استلام ملفاتك. بعض العناصر تحتاج مراجعة المسؤول قبل متابعة البلاغ.',
                'de' => 'Ihre Dateien sind eingegangen. Einige Punkte benötigen eine Administratorprüfung, bevor der Fall fortgesetzt werden kann.',
                'en' => 'Your files were received. Some items need administrator review before the case can proceed.',
            ),
            'checks.next_ok' => array(
                'ar' => 'تم وضع إرسالك في قائمة انتظار فريق دعم الجرائم الإلكترونية.',
                'de' => 'Ihre Einreichung ist in der Warteschlange des Cybercrime-Support-Teams.',
                'en' => 'Your submission is queued for the Cybercrime Support team.',
            ),
            'checks.missing_identity' => array(
                'ar' => 'يلزم مستند هوية.',
                'de' => 'Ein Ausweisdokument ist erforderlich.',
                'en' => 'An identity document is required.',
            ),
            'checks.replace_identity' => array(
                'ar' => 'يرجى استبدال مستند الهوية بملف واضح وكامل.',
                'de' => 'Bitte ersetzen Sie das Ausweisdokument durch eine lesbare, vollständige Datei.',
                'en' => 'Please replace the identity document with a readable, complete file.',
            ),
            'check.issue.empty' => array(
                'ar' => 'الملف فارغ. يرجى رفع مسح أو صورة كاملة.',
                'de' => 'Die Datei ist leer. Bitte laden Sie einen vollständigen Scan oder ein Foto hoch.',
                'en' => 'The file is empty. Please upload a complete scan or photo.',
            ),
            'check.issue.too_small' => array(
                'ar' => 'الملف صغير جداً ليكون مستنداً واضحاً. يرجى رفع أصل أوضح.',
                'de' => 'Die Datei ist zu klein, um als lesbares Dokument zu gelten. Bitte laden Sie ein klareres Original hoch.',
                'en' => 'The file is too small to be a readable document. Please upload a clearer original.',
            ),
            'check.issue.type' => array(
                'ar' => 'نوع الملف غير مقبول. يرجى رفع PDF أو صورة للمستند.',
                'de' => 'Dieser Dateityp wird nicht akzeptiert. Bitte laden Sie ein PDF oder ein Foto des Dokuments hoch.',
                'en' => 'This file type is not accepted. Please upload a PDF or image of the document.',
            ),
            'check.issue.identity_type' => array(
                'ar' => 'يجب أن يكون مستند الهوية PDF أو صورة (JPG أو PNG أو HEIC).',
                'de' => 'Ausweisdokumente müssen ein PDF oder Foto (JPG, PNG, HEIC) sein.',
                'en' => 'Identity documents must be a PDF or photo (JPG, PNG, HEIC).',
            ),
            'check.issue.duplicate' => array(
                'ar' => 'تم رفع هذا الملف أكثر من مرة في نفس الإرسال. يرجى إزالة النسخة المكررة.',
                'de' => 'Diese Datei wurde in derselben Einreichung mehrfach hochgeladen. Bitte entfernen Sie das Duplikat.',
                'en' => 'This file was uploaded more than once in the same submission. Please remove the duplicate.',
            ),
            'check.issue.image_open' => array(
                'ar' => 'تعذر فتح الصورة. يرجى رفع JPG أو PNG أو PDF واضح.',
                'de' => 'Das Bild konnte nicht geöffnet werden. Bitte laden Sie ein klares JPG, PNG oder PDF hoch.',
                'en' => 'The image could not be opened. Please upload a clear JPG, PNG, or PDF.',
            ),
            'check.issue.image_small' => array(
                'ar' => 'الصورة صغيرة جداً للقراءة. يرجى تصوير المستند بالكامل.',
                'de' => 'Das Bild ist zu klein zum Lesen. Bitte fotografieren Sie das gesamte Dokument.',
                'en' => 'The image is too small to read. Please photograph the full document.',
            ),
            'check.issue.image_res' => array(
                'ar' => 'دقة الصورة منخفضة. يرجى إعادة التصوير حتى يظهر النص بوضوح.',
                'de' => 'Die Bildauflösung ist zu niedrig. Bitte fotografieren Sie erneut, damit der Text lesbar ist.',
                'en' => 'The image resolution is low. Please retake the photo so all text is readable.',
            ),
            'check.issue.image_quality' => array(
                'ar' => 'الصورة داكنة أو ساطعة أو ضبابية جداً. يرجى إعادة التصوير بإضاءة متساوية مع ظهور المستند كاملاً.',
                'de' => 'Das Foto ist zu dunkel, hell oder unscharf. Bitte fotografieren Sie bei gleichmäßiger Beleuchtung mit dem gesamten Dokument im Bild.',
                'en' => 'The photo looks too dark, bright, or blurry. Please retake it in even lighting with the full document in frame.',
            ),
            'check.issue.pdf_read' => array(
                'ar' => 'تعذر قراءة ملف PDF. يرجى تصدير PDF صالح أو رفع صورة للمستند.',
                'de' => 'Das PDF konnte nicht gelesen werden. Bitte exportieren Sie ein gültiges PDF oder laden Sie ein Foto des Dokuments hoch.',
                'en' => 'The PDF could not be read. Please export a valid PDF or upload a photo of the document.',
            ),
            'check.issue.pdf_incomplete' => array(
                'ar' => 'يبدو ملف PDF غير مكتمل. يرجى رفع المستند كاملاً.',
                'de' => 'Das PDF scheint unvollständig zu sein. Bitte laden Sie das vollständige Dokument hoch.',
                'en' => 'The PDF appears incomplete. Please upload the full document.',
            ),
            'check.issue.expired' => array(
                'ar' => 'يبدو أن هذا المستند منتهٍ. يرجى رفع مستند هوية ساري المفعول.',
                'de' => 'Dieses Dokument scheint abgelaufen zu sein. Bitte laden Sie ein aktuell gültiges Ausweisdokument hoch.',
                'en' => 'This document appears to be expired. Please upload a currently valid identity document.',
            ),
            'error.resubmit_checks' => array(
                'ar' => 'لم تجتز الملفات الجديدة فحوصات الجودة الأولية. يرجى تصحيحها والمحاولة مرة أخرى على نفس البلاغ.',
                'de' => 'Die neuen Dateien haben die vorläufigen Qualitätsprüfungen nicht bestanden. Bitte korrigieren Sie sie und versuchen Sie es auf demselben Fall erneut.',
                'en' => 'The new files did not pass preliminary quality checks. Please correct them and try again on this same case.',
            ),
            'error.empty_update' => array(
                'ar' => 'يرجى إرفاق ملف أو إضافة رسالة.',
                'de' => 'Bitte fügen Sie eine Datei oder eine Nachricht hinzu.',
                'en' => 'Please attach a file or add a message.',
            ),
            'error.login' => array(
                'ar' => 'يرجى تسجيل الدخول.',
                'de' => 'Bitte anmelden.',
                'en' => 'Please sign in.',
            ),
            'error.closed' => array(
                'ar' => 'هذا البلاغ مغلق.',
                'de' => 'Diese Meldung ist geschlossen.',
                'en' => 'This report is closed.',
            ),
            'error.forbidden' => array(
                'ar' => 'لا يمكنك الرد على هذا البلاغ.',
                'de' => 'Sie können auf diese Meldung nicht antworten.',
                'en' => 'You cannot reply to this report.',
            ),
            'error.save_failed' => array(
                'ar' => 'تعذر حفظ رسالتك.',
                'de' => 'Ihre Nachricht konnte nicht gespeichert werden.',
                'en' => 'Could not save your message.',
            ),
            'missing.still_needed' => array(
                'ar' => 'لا يزال مطلوباً على نفس البلاغ: %s.',
                'de' => 'Auf demselben Fall noch erforderlich: %s.',
                'en' => 'Still needed on this same case: %s.',
            ),
            'portal.overview.intro' => array(
                'ar' => 'إدارة مشاريع العملاء والأخبار وكتالوج الخدمات. المصادقة عبر وحدة حساب PAXDesign.',
                'de' => 'Verwalten Sie kundenbezogene Projekte, News und den Leistungskatalog. Die Anmeldung nutzt das PAXDesign-Auth-Modul.',
                'en' => 'Manage customer-facing projects, news, and the services catalog. Authentication uses the PAXDesign booking auth module.',
            ),
            'portal.overview.projects_count' => array(
                'ar' => 'المشاريع النشطة: %d',
                'de' => 'Aktive Projekte: %d',
                'en' => 'Active projects: %d',
            ),
            'portal.overview.orders_count' => array(
                'ar' => 'طلبات الخدمة: %d',
                'de' => 'Serviceanfragen: %d',
                'en' => 'Service requests: %d',
            ),
            'portal.overview.news_count' => array(
                'ar' => 'الأخبار المنشورة: %d',
                'de' => 'Veröffentlichte News: %d',
                'en' => 'Published news: %d',
            ),
            'portal.overview.services_count' => array(
                'ar' => 'خدمات الكتالوج: %d',
                'de' => 'Katalogleistungen: %d',
                'en' => 'Catalog services: %d',
            ),
            'portal.synced' => array(
                'ar' => 'تمت مزامنة كتالوج الخدمات.',
                'de' => 'Leistungskatalog synchronisiert.',
                'en' => 'Services catalog synced.',
            ),
            'portal.ref' => array(
                'ar' => 'المرجع',
                'de' => 'Ref.',
                'en' => 'Ref',
            ),
            'portal.title' => array(
                'ar' => 'العنوان',
                'de' => 'Titel',
                'en' => 'Title',
            ),
            'portal.customer' => array(
                'ar' => 'العميل',
                'de' => 'Kunde',
                'en' => 'Customer',
            ),
            'portal.status' => array(
                'ar' => 'الحالة',
                'de' => 'Status',
                'en' => 'Status',
            ),
            'portal.progress' => array(
                'ar' => 'التقدم',
                'de' => 'Fortschritt',
                'en' => 'Progress',
            ),
            'portal.create_project' => array(
                'ar' => 'إنشاء مشروع',
                'de' => 'Projekt erstellen',
                'en' => 'Create project',
            ),
            'portal.no_projects' => array(
                'ar' => 'لا توجد مشاريع بعد.',
                'de' => 'Noch keine Projekte.',
                'en' => 'No projects yet.',
            ),
            'portal.customer_user_id' => array(
                'ar' => 'معرّف مستخدم العميل',
                'de' => 'Kunden-Benutzer-ID',
                'en' => 'Customer user ID',
            ),
            'portal.description' => array(
                'ar' => 'الوصف',
                'de' => 'Beschreibung',
                'en' => 'Description',
            ),
            'portal.orders' => array(
                'ar' => 'طلبات الخدمة',
                'de' => 'Serviceanfragen',
                'en' => 'Service requests',
            ),
            'portal.service' => array(
                'ar' => 'الخدمة',
                'de' => 'Leistung',
                'en' => 'Service',
            ),
            'portal.update' => array(
                'ar' => 'تحديث',
                'de' => 'Aktualisieren',
                'en' => 'Update',
            ),
            'portal.no_orders' => array(
                'ar' => 'لا توجد طلبات خدمة بعد.',
                'de' => 'Noch keine Serviceanfragen.',
                'en' => 'No service requests yet.',
            ),
            'portal.news' => array(
                'ar' => 'الأخبار والإعلانات',
                'de' => 'News & Ankündigungen',
                'en' => 'News & announcements',
            ),
            'portal.news_help' => array(
                'ar' => 'تظهر العناصر المنشورة في التطبيق فوراً. حذف عنصر يزيله من التطبيق عند التحديث التالي.',
                'de' => 'Veröffentlichte Einträge erscheinen sofort in der App. Das Löschen entfernt den Eintrag beim nächsten Refresh.',
                'en' => 'Published items appear in the mobile app immediately. Deleting an item removes it from the app on the next refresh.',
            ),
            'portal.slug' => array(
                'ar' => 'المعرّف',
                'de' => 'Slug',
                'en' => 'Slug',
            ),
            'portal.published' => array(
                'ar' => 'تاريخ النشر',
                'de' => 'Veröffentlicht',
                'en' => 'Published',
            ),
            'portal.edit' => array(
                'ar' => 'تعديل',
                'de' => 'Bearbeiten',
                'en' => 'Edit',
            ),
            'portal.publish' => array(
                'ar' => 'نشر',
                'de' => 'Veröffentlichen',
                'en' => 'Publish',
            ),
            'portal.unpublish' => array(
                'ar' => 'إلغاء النشر',
                'de' => 'Zurückziehen',
                'en' => 'Unpublish',
            ),
            'portal.delete' => array(
                'ar' => 'حذف',
                'de' => 'Löschen',
                'en' => 'Delete',
            ),
            'portal.no_news' => array(
                'ar' => 'لا توجد أخبار بعد.',
                'de' => 'Noch keine News.',
                'en' => 'No news items yet.',
            ),
            'portal.create_news' => array(
                'ar' => 'إنشاء خبر',
                'de' => 'News erstellen',
                'en' => 'Create news item',
            ),
            'portal.edit_news' => array(
                'ar' => 'تعديل الخبر',
                'de' => 'News bearbeiten',
                'en' => 'Edit news item',
            ),
            'portal.services_catalog' => array(
                'ar' => 'كتالوج الخدمات',
                'de' => 'Leistungskatalog',
                'en' => 'Services catalog',
            ),
            'portal.sync_catalog' => array(
                'ar' => 'مزامنة من كتالوج الحجز',
                'de' => 'Aus Buchungskatalog synchronisieren',
                'en' => 'Sync from booking catalog',
            ),
            'portal.services_count_line' => array(
                'ar' => '%d خدمة نشطة في كتالوج العملاء.',
                'de' => '%d aktive Leistungen im Kundenkatalog.',
                'en' => '%d active services in customer catalog.',
            ),
            'portal.name' => array(
                'ar' => 'الاسم',
                'de' => 'Name',
                'en' => 'Name',
            ),
            'portal.no_services' => array(
                'ar' => 'لم تتم مزامنة خدمات بعد.',
                'de' => 'Noch keine Leistungen synchronisiert.',
                'en' => 'No services synced yet.',
            ),
            'portal.send_notification' => array(
                'ar' => 'إرسال إشعار',
                'de' => 'Benachrichtigung senden',
                'en' => 'Send notification',
            ),
            'portal.notify_intro' => array(
                'ar' => 'أرسل إشعاراً إلى عميل محدد. يتطلب التسليم الفوري رمز جهاز مسجّل.',
                'de' => 'Senden Sie eine Benachrichtigung an einen bestimmten Kunden. Push erfordert ein registriertes Gerätetoken.',
                'en' => 'Send a notification to a specific customer. Push delivery requires a registered device token.',
            ),
            'portal.message' => array(
                'ar' => 'الرسالة',
                'de' => 'Nachricht',
                'en' => 'Message',
            ),
            'portal.category' => array(
                'ar' => 'التصنيف',
                'de' => 'Kategorie',
                'en' => 'Category',
            ),
            'portal.planning' => array(
                'ar' => 'تخطيط',
                'de' => 'Planung',
                'en' => 'planning',
            ),
            'portal.in_progress' => array(
                'ar' => 'قيد التنفيذ',
                'de' => 'In Arbeit',
                'en' => 'in progress',
            ),
            'portal.review' => array(
                'ar' => 'مراجعة',
                'de' => 'Prüfung',
                'en' => 'review',
            ),
            'portal.completed' => array(
                'ar' => 'مكتمل',
                'de' => 'Abgeschlossen',
                'en' => 'completed',
            ),
        );
        return $strings;
    }
}
