<?php
/**
 * Compact Cybercrime Support locale helper (de/en/ar) for the restored WP baseline.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_I18n {

    /**
     * @param string $lang
     * @return string de|en|ar
     */
    public static function normalize($lang) {
        $lang = strtolower(sanitize_key((string) $lang));
        if (strpos($lang, 'ar') === 0) {
            return 'ar';
        }
        if (strpos($lang, 'en') === 0) {
            return 'en';
        }
        return 'de';
    }

    /**
     * @param array<string, mixed> $row
     * @return string de|en|ar
     */
    public static function from_report($row) {
        $payload = array();
        if (is_array($row) && isset($row['payload'])) {
            $payload = is_array($row['payload']) ? $row['payload'] : json_decode((string) $row['payload'], true);
            if (!is_array($payload)) {
                $payload = array();
            }
        }
        $locale = sanitize_text_field((string) ($payload['locale'] ?? $row['locale'] ?? ''));
        if ($locale !== '') {
            return self::normalize($locale);
        }
        $user_id = absint($row['customer_user_id'] ?? 0);
        if ($user_id > 0 && class_exists('PAXdesign_Language_Routing')) {
            $spoken = PAXdesign_Language_Routing::get_user_spoken_languages($user_id);
            if (!empty($spoken[0])) {
                return self::normalize((string) $spoken[0]);
            }
        }
        return 'de';
    }

    /**
     * @param string $key
     * @param string $lang
     * @return string
     */
    public static function t($key, $lang = 'de') {
        $lang = self::normalize($lang);
        $pack = self::strings();
        if (isset($pack[$key][$lang]) && $pack[$key][$lang] !== '') {
            return $pack[$key][$lang];
        }
        if (isset($pack[$key]['en'])) {
            return $pack[$key]['en'];
        }
        return $key;
    }

    /**
     * @param string $status
     * @param string $lang
     * @return string
     */
    public static function status_label($status, $lang = 'de') {
        $status = sanitize_key((string) $status);
        return self::t('status.' . $status, $lang);
    }

    /**
     * @param string $key
     * @return array{de?:string,en?:string,ar?:string}
     */
    public static function pack($key) {
        $all = self::strings();
        $key = (string) $key;
        return isset($all[$key]) && is_array($all[$key]) ? $all[$key] : array();
    }

    /**
     * @param string $status
     * @return array{de?:string,en?:string,ar?:string}
     */
    public static function next_action_pack($status) {
        return self::pack('next.' . sanitize_key((string) $status));
    }

    /**
     * @param string $category
     * @param string $lang
     * @return string
     */
    public static function category_label($category, $lang = 'de') {
        $category = sanitize_key((string) $category);
        $label = self::t('category.' . $category, $lang);
        return $label !== 'category.' . $category ? $label : $category;
    }

    /**
     * @return array<string, array{de:string,en:string,ar:string}>
     */
    private static function strings() {
        return array(
            'status.submitted' => array(
                'de' => 'Neu',
                'en' => 'New',
                'ar' => 'جديد',
            ),
            'status.in_review' => array(
                'de' => 'In Prüfung',
                'en' => 'In Review',
                'ar' => 'قيد المراجعة',
            ),
            'status.waiting_for_customer' => array(
                'de' => 'Wartet auf Kunde',
                'en' => 'Waiting for Customer',
                'ar' => 'بانتظار العميل',
            ),
            'status.resolved' => array(
                'de' => 'Gelöst',
                'en' => 'Resolved',
                'ar' => 'تم الحل',
            ),
            'status.closed' => array(
                'de' => 'Geschlossen',
                'en' => 'Closed',
                'ar' => 'مغلق',
            ),
            'status.rejected' => array(
                'de' => 'مرفوض',
                'en' => 'مرفوض',
                'ar' => 'مرفوض',
            ),
            'status.desc.rejected' => array(
                'de' => 'Dieser Fall wurde abgelehnt',
                'en' => 'This case was rejected',
                'ar' => 'تم رفض هذا البلاغ',
            ),
            'email.submit.customer.subject' => array(
                'de' => 'Cybercrime-Meldung %s eingegangen',
                'en' => 'Cybercrime report %s received',
                'ar' => 'تم استلام بلاغ الجرائم الإلكترونية %s',
            ),
            'email.submit.customer.body' => array(
                'de' => "Guten Tag,\n\nIhre Cybercrime-Meldung wurde aufgenommen.\n\nReferenz: %s\nStatus: %s\nKategorie: %s\n\nWir prüfen den Fall und melden uns mit dem nächsten Schritt.\n\nBericht ansehen: %s\n",
                'en' => "Hello,\n\nYour Cybercrime report has been recorded.\n\nReference: %s\nStatus: %s\nCategory: %s\n\nWe will review the case and contact you with the next step.\n\nView report: %s\n",
                'ar' => "مرحباً،\n\nتم تسجيل بلاغ الجرائم الإلكترونية الخاص بكم.\n\nالمرجع: %s\nالحالة: %s\nالتصنيف: %s\n\nسنراجع البلاغ ونتواصل معكم بالخطوة التالية.\n\nعرض البلاغ: %s\n",
            ),
            'email.status.customer.subject' => array(
                'de' => '[Cybercrime %s] %s',
                'en' => '[Cybercrime %s] %s',
                'ar' => '[الجرائم الإلكترونية %s] %s',
            ),
            'email.status.customer.body' => array(
                'de' => "Aktualisierung Ihrer Cybercrime-Meldung\n\nReferenz: %s\nStatus: %s\n\n%s\n\nBericht ansehen: %s\n",
                'en' => "Update on your Cybercrime report\n\nReference: %s\nStatus: %s\n\n%s\n\nView report: %s\n",
                'ar' => "تحديث بلاغ الجرائم الإلكترونية\n\nالمرجع: %s\nالحالة: %s\n\n%s\n\nعرض البلاغ: %s\n",
            ),
            'email.submit.admin.subject' => array(
                'de' => '[Cybercrime Report] %s — %s',
                'en' => '[Cybercrime Report] %s — %s',
                'ar' => '[بلاغ جرائم إلكترونية] %s — %s',
            ),
            'notify.customer.title' => array(
                'de' => 'Cybercrime-Meldung eingegangen',
                'en' => 'Cybercrime report received',
                'ar' => 'تم استلام بلاغ الجرائم الإلكترونية',
            ),
            'notify.customer.body' => array(
                'de' => 'Referenz %1$s — %2$s. Ihre Meldung ist aufgenommen und wartet auf Prüfung.',
                'en' => 'Reference %1$s — %2$s. Your report is recorded and awaiting review.',
                'ar' => 'المرجع %1$s — %2$s. تم تسجيل بلاغكم وهو بانتظار المراجعة.',
            ),
            'evidence.uploaded' => array(
                'de' => 'Kunde hat Nachweise hochgeladen.',
                'en' => 'Customer uploaded evidence.',
                'ar' => 'قام العميل برفع الأدلة.',
            ),
            'evidence.success' => array(
                'de' => 'Nachweise erfolgreich übermittelt.',
                'en' => 'Evidence submitted successfully.',
                'ar' => 'تم إرسال الأدلة بنجاح.',
            ),
            'action.reject' => array(
                'de' => 'مرفوض',
                'en' => 'مرفوض',
                'ar' => 'مرفوض',
            ),
            'action.reject_confirm' => array(
                'de' => 'Diesen Fall als مرفوض (abgelehnt) markieren? Der Kunde wird per E-Mail informiert.',
                'en' => 'Mark this case as مرفوض (Rejected)? The customer will be emailed.',
                'ar' => 'هل تريد وسم هذا البلاغ كمرفوض؟ سيتم إرسال بريد إلى العميل.',
            ),
            'timeline.status_changed' => array(
                'de' => 'Status geändert zu %s.',
                'en' => 'Status changed to %s.',
                'ar' => 'تم تغيير الحالة إلى %s.',
            ),
            'notify.status.title' => array(
                'de' => 'Cybercrime-Meldung %s aktualisiert',
                'en' => 'Cybercrime report %s updated',
                'ar' => 'تم تحديث بلاغ الجرائم الإلكترونية %s',
            ),
            'email.status.admin.subject' => array(
                'de' => '[Cybercrime %s] Status: %s',
                'en' => '[Cybercrime %s] Status: %s',
                'ar' => '[الجرائم الإلكترونية %s] الحالة: %s',
            ),
            'email.status.admin.body' => array(
                'de' => "Statusänderung einer Cybercrime-Meldung\n\nReferenz: %s\nKunde: %s <%s>\nStatus: %s\n\n%s\n\nAdmin: %s\n",
                'en' => "Cybercrime report status change\n\nReference: %s\nCustomer: %s <%s>\nStatus: %s\n\n%s\n\nAdmin: %s\n",
                'ar' => "تغيير حالة بلاغ الجرائم الإلكترونية\n\nالمرجع: %s\nالعميل: %s <%s>\nالحالة: %s\n\n%s\n\nالإدارة: %s\n",
            ),
            'submit.success' => array(
                'de' => 'Ihre Meldung wurde sicher übermittelt.',
                'en' => 'Your report has been submitted securely.',
                'ar' => 'تم إرسال بلاغكم بأمان.',
            ),
            'category.account_takeover' => array(
                'de' => 'Kontoübernahme',
                'en' => 'Account takeover',
                'ar' => 'الاستيلاء على الحساب',
            ),
            'category.phishing_fraud' => array(
                'de' => 'Phishing / Betrug',
                'en' => 'Phishing / fraud',
                'ar' => 'تصيد / احتيال',
            ),
            'category.identity_theft' => array(
                'de' => 'Identitätsdiebstahl',
                'en' => 'Identity theft',
                'ar' => 'سرقة الهوية',
            ),
            'category.malware_ransomware' => array(
                'de' => 'Malware / Ransomware',
                'en' => 'Malware / ransomware',
                'ar' => 'برمجيات خبيثة / فدية',
            ),
            'category.social_media_recovery' => array(
                'de' => 'Wiederherstellung sozialer Medien',
                'en' => 'Social media recovery',
                'ar' => 'استعادة حساب التواصل الاجتماعي',
            ),
            'category.financial_fraud' => array(
                'de' => 'Finanzbetrug',
                'en' => 'Financial fraud',
                'ar' => 'احتيال مالي',
            ),
            'category.data_breach' => array(
                'de' => 'Datenleck',
                'en' => 'Data breach',
                'ar' => 'اختراق بيانات',
            ),
            'category.other' => array(
                'de' => 'Anderer Cyber-Vorfall',
                'en' => 'Other cyber incident',
                'ar' => 'حادثة إلكترونية أخرى',
            ),
            'next.submitted' => array(
                'de' => 'Wir prüfen Ihre Meldung und melden uns mit dem nächsten Schritt.',
                'en' => 'We are reviewing your report and will contact you with the next step.',
                'ar' => 'نراجع بلاغكم وسنتواصل معكم بالخطوة التالية.',
            ),
            'next.in_review' => array(
                'de' => 'Das Team prüft den Fall. Sie müssen jetzt nichts tun.',
                'en' => 'The team is reviewing the case. No action is needed from you right now.',
                'ar' => 'الفريق يراجع البلاغ. لا يلزم إجراء منكم الآن.',
            ),
            'next.waiting_for_customer' => array(
                'de' => 'Bitte antworten Sie mit den angeforderten Angaben.',
                'en' => 'Please reply with the requested information.',
                'ar' => 'يرجى الرد بالمعلومات المطلوبة.',
            ),
            'next.resolved' => array(
                'de' => 'Der Fall wurde gelöst. Sie können bei Bedarf eine neue Meldung starten.',
                'en' => 'This case was resolved. You can start a new report if needed.',
                'ar' => 'تم حل البلاغ. يمكنكم فتح بلاغ جديد عند الحاجة.',
            ),
            'next.closed' => array(
                'de' => 'Dieses Ticket ist geschlossen.',
                'en' => 'This ticket is closed.',
                'ar' => 'تم إغلاق هذا البلاغ.',
            ),
            'next.rejected' => array(
                'de' => 'Dieser Fall wurde als مرفوض markiert. Details stehen in der E-Mail und im Portal.',
                'en' => 'This case was marked مرفوض. Details are in the email and portal.',
                'ar' => 'تم وسم هذا البلاغ كمرفوض. التفاصيل في البريد وفي الصفحة.',
            ),
            'status.desc.submitted' => array(
                'de' => 'Neue Meldung eingegangen',
                'en' => 'New report received',
                'ar' => 'تم استلام بلاغ جديد',
            ),
            'status.desc.in_review' => array(
                'de' => 'Das Team prüft die Meldung',
                'en' => 'Team is analyzing the report',
                'ar' => 'الفريق يراجع البلاغ',
            ),
            'status.desc.waiting_for_customer' => array(
                'de' => 'Angaben des Kunden erforderlich',
                'en' => 'Customer information is required',
                'ar' => 'مطلوب معلومات من العميل',
            ),
            'status.desc.resolved' => array(
                'de' => 'Fall gelöst',
                'en' => 'Issue solved',
                'ar' => 'تم حل المشكلة',
            ),
            'status.desc.closed' => array(
                'de' => 'Ticket abgeschlossen',
                'en' => 'Ticket completed',
                'ar' => 'اكتمل البلاغ',
            ),
        );
    }
}
