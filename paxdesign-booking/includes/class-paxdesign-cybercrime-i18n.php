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
        );
        return $strings;
    }
}
