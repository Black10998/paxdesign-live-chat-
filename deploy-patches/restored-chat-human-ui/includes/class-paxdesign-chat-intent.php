<?php
/**
 * Customer-assistant intent detection (website widget + iOS app share this backend).
 * No WordPress runtime required — safe for unit tests.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Chat_Intent {

    const GENERAL          = 'general';
    const ACCOUNT_REQUEST  = 'account_request';
    const ACCOUNT_STATUS   = 'account_status';
    const APPOINTMENT      = 'appointment';
    const INVOICE          = 'invoice';
    const PROJECT          = 'project';
    const CCS_STATUS       = 'ccs_status';
    const LIVE_AGENT       = 'live_agent';

    /**
     * @param string $latest_message
     * @param string $recent_conversation Optional recent user/assistant transcript.
     * @return string One of the class constants.
     */
    public static function detect($latest_message, $recent_conversation = '') {
        $latest = trim((string) $latest_message);
        $blob = strtolower($latest . "\n" . (string) $recent_conversation);

        if ($latest === '') {
            return self::GENERAL;
        }

        if (self::is_new_request_phrasing($latest)) {
            return self::GENERAL;
        }

        if (self::is_live_agent_phrasing($latest)) {
            return self::LIVE_AGENT;
        }

        if (self::matches($blob, array(
            'appointment', 'termin', 'booking', 'booked', 'gebucht', 'موعد', 'حجز',
        )) && self::is_lookup_phrasing($latest, $blob)) {
            return self::APPOINTMENT;
        }

        if (self::matches($blob, array(
            'invoice', 'rechnung', 'invoices', 'document', 'documents', 'file', 'files',
            'فاتورة', 'فاتورتي', 'ملف',
        )) && self::is_lookup_phrasing($latest, $blob)) {
            return self::INVOICE;
        }

        if (self::matches($blob, array(
            'project', 'projects', 'projekt', 'projekte', 'مشروع', 'مشروعي',
        )) && self::is_lookup_phrasing($latest, $blob)) {
            return self::PROJECT;
        }

        if (self::matches($blob, array(
            'cybercrime', 'cyber crime', 'report', 'reference', 'ticket', 'بلاغ', 'التقرير', 'رقم البلاغ',
        )) && self::is_lookup_phrasing($latest, $blob)) {
            return self::CCS_STATUS;
        }

        if (self::is_status_phrasing($latest, $blob) && self::is_existing_item_phrasing($latest, $blob)) {
            return self::ACCOUNT_STATUS;
        }

        if (self::is_existing_item_phrasing($latest, $blob)) {
            return self::ACCOUNT_REQUEST;
        }

        if (self::is_short_followup($latest) && self::recent_mentions_account_item($recent_conversation)) {
            return self::is_status_phrasing($latest, $blob) ? self::ACCOUNT_STATUS : self::ACCOUNT_REQUEST;
        }

        return self::GENERAL;
    }

    /**
     * @param string $intent
     * @return bool
     */
    public static function is_account_lookup($intent) {
        return in_array($intent, array(
            self::ACCOUNT_REQUEST,
            self::ACCOUNT_STATUS,
            self::APPOINTMENT,
            self::INVOICE,
            self::PROJECT,
            self::CCS_STATUS,
        ), true);
    }

    /**
     * Always-on rules for website + app assistant.
     *
     * @return string
     */
    public static function operating_rules_block() {
        return implode("\n", array(
            '## Understanding the customer (mandatory)',
            '- Read the COMPLETE latest customer message and the recent conversation before answering.',
            '- Identify the actual intent first (existing request, status, appointment, invoice/file, project, Cybercrime report, new service, live agent, form help).',
            '- Answer that intent directly with facts. Never echo or rephrase the question.',
            '- Never ask "What is your request?", "Worum geht es?", "What do you need?", or "Which request?" when account context already lists a request, order, booking, project, or report.',
            '- Use ONLY facts from the account context and this prompt. If something is not listed, say it is not on file. Never guess or invent a reference, status, date, or next step.',
            '- Keep the reply short and useful: the item, current status, relevant details, and the single next step for the customer.',
            '- Follow-up messages refer to the same item unless the customer clearly names a different one.',
            '- Reply in the customer\'s language (German, English, or Arabic).',
        ));
    }

    /**
     * Extra instruction for the detected intent.
     *
     * @param string $intent
     * @param bool   $is_logged_in
     * @return string
     */
    public static function instruction_block($intent, $is_logged_in) {
        $intent = (string) $intent;
        $logged = (bool) $is_logged_in;

        if (self::is_account_lookup($intent) && !$logged) {
            return implode("\n", array(
                '## Current intent: personal account lookup (visitor is not logged in)',
                '- They asked about a submitted request, status, appointment, invoice, project, or report.',
                '- You cannot see their account data in this session.',
                '- Tell them you can look it up after they sign in. Do not invent a request. Do not ask them to retell it as a substitute for looking it up.',
            ));
        }

        switch ($intent) {
            case self::ACCOUNT_REQUEST:
                return implode("\n", array(
                    '## Current intent: identify the request they already submitted',
                    '- Find their submitted item in account context (service request/order, booking, project, or Cybercrime report).',
                    '- If exactly one exists, that IS their request. State what they submitted, the reference, the date, and the current status.',
                    '- If several exist, lead with the most recent and briefly list the others.',
                    '- Include the request description/summary from account context. Do not ask them what the request was.',
                    '- End with the single next step (wait for the team, open the portal, or nothing more needed).',
                ));
            case self::ACCOUNT_STATUS:
                return implode("\n", array(
                    '## Current intent: status of their submitted request',
                    '- Look up the matching item in account context. If they did not name one, use the most recent submitted request/order/report/booking.',
                    '- Answer with: what the request is, current status, last update if listed, and what they should do next.',
                    '- If no updates are listed, say the item is recorded and the team will contact them when there is news. Do not invent updates.',
                    '- Do not restart a form and do not ask "What is your request?"',
                ));
            case self::APPOINTMENT:
                return implode("\n", array(
                    '## Current intent: appointment / booking',
                    '- Answer from upcoming and submitted bookings in account context (service, date, time, status, message).',
                    '- If none are listed, say no appointment is on file. Do not invent a time.',
                ));
            case self::INVOICE:
                return implode("\n", array(
                    '## Current intent: invoice / shared file',
                    '- Answer from shared files/invoices in account context.',
                    '- If none are listed, say no invoice or file is available in the portal yet.',
                ));
            case self::PROJECT:
                return implode("\n", array(
                    '## Current intent: project',
                    '- Answer from projects in account context (title, reference, status, progress, description, expected completion).',
                    '- If none are listed, say no project is on file yet.',
                ));
            case self::CCS_STATUS:
                return implode("\n", array(
                    '## Current intent: Cybercrime Support report',
                    '- Answer from Cybercrime Support report facts in account context (reference, category, status, dates, summary, updates).',
                    '- If a FOCUS report is marked, use that one.',
                    '- Do not restart the website form. Do not treat this chat as an official ticket update.',
                ));
            case self::LIVE_AGENT:
                return implode("\n", array(
                    '## Current intent: live person',
                    '- The customer asked for a human. Do not ask what their existing request is if account context already has it.',
                    '- A short confirmation is enough; the system may already be handing them to a teammate.',
                ));
            default:
                return implode("\n", array(
                    '## Current intent: general',
                    '- Answer the latest message on its merits.',
                    '- If they later ask about "my request" or "the status", use account context — do not forget items already listed.',
                    '- At most one focused follow-up, and only if a needed fact is truly missing from account context.',
                ));
        }
    }

    /**
     * @param string $text
     * @param array<int, string> $needles
     * @return bool
     */
    private static function matches($text, array $needles) {
        $text = (string) $text;
        foreach ($needles as $needle) {
            $needle = (string) $needle;
            if ($needle === '') {
                continue;
            }
            $found = function_exists('mb_stripos')
                ? mb_stripos($text, $needle)
                : stripos($text, $needle);
            if ($found !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $latest
     * @return bool
     */
    private static function is_new_request_phrasing($latest) {
        return (bool) preg_match(
            '/\b(want to (submit|make|send|create)|would like to (submit|make|send)|neue anfrage|anfrage stellen|möchte (eine |einen )?(anfrage|auftrag)|أريد (أن )?(أقدم|أرسل)|اريد (أن )?(أقدم|أرسل))\b/iu',
            $latest
        );
    }

    /**
     * @param string $latest
     * @return bool
     */
    private static function is_live_agent_phrasing($latest) {
        return (bool) preg_match(
            '/(?:speak\s+(?:to|with)\s+(?:a\s+)?(?:human|person|agent)|talk to (?:a )?(?:human|person|agent)|live\s*(?:agent|support|chat)|mitarbeiter|echten menschen|موظف|شخص حقيقي)/iu',
            $latest
        );
    }

    /**
     * @param string $latest
     * @param string $blob
     * @return bool
     */
    private static function is_lookup_phrasing($latest, $blob) {
        return self::is_existing_item_phrasing($latest, $blob)
            || self::is_status_phrasing($latest, $blob)
            || (bool) preg_match('/\b(my|mine|meine[rns]?|mein|meinen|طلبي|موعدي|مشروعي|فاتورتي)\b/iu', $latest);
    }

    /**
     * @param string $latest
     * @param string $blob
     * @return bool
     */
    private static function is_existing_item_phrasing($latest, $blob) {
        if (preg_match('/\b(submitted|eingereicht|geschickt|gesendet|gestellt habe|I sent|I submitted|قدمته|أرسلته|الطلب الذي)\b/iu', $latest)) {
            return true;
        }
        if (preg_match('/\b(the request I|request I submitted|anfrage die ich|auftrag den ich|welche anfrage|welchen auftrag|what request did I|طلب(?:ي)?(?: الذي)?)\b/iu', $latest)) {
            return true;
        }
        if (preg_match('/\b(my request|meine anfrage|meinen auftrag|mein auftrag|meine aufträge|serviceanfrage|طلبي|الطلب المقدم)\b/iu', $latest)) {
            return true;
        }
        if (preg_match('/\b(what (is|was) (the |my )?(request|order|submission)|was (ist|habe ich)|ما (هو|هي) (الطلب|طلب))\b/iu', $latest)) {
            return true;
        }
        return false;
    }

    /**
     * @param string $latest
     * @param string $blob
     * @return bool
     */
    private static function is_status_phrasing($latest, $blob) {
        return (bool) preg_match(
            '/\b(status|stand|update|updates|progress|fortschritt|any news|any update|wo steht|حالة|تحديث|أين وصل)\b/iu',
            $latest . ' ' . $blob
        );
    }

    /**
     * @param string $latest
     * @return bool
     */
    private static function is_short_followup($latest) {
        $latest = trim($latest);
        if ($latest === '') {
            return false;
        }
        $len = function_exists('mb_strlen') ? mb_strlen($latest) : strlen($latest);
        if ($len > 48) {
            return false;
        }
        return (bool) preg_match('/\b(and|und|status|stand|now|jetzt|what about|und der|حالة|ماذا عن)\b/iu', $latest);
    }

    /**
     * @param string $recent
     * @return bool
     */
    private static function recent_mentions_account_item($recent) {
        return (bool) preg_match(
            '/\b(ORD-|BKG-|PRJ-|CCS-|request|anfrage|auftrag|order|booking|termin|project|projekt|report|بلاغ|طلب)\b/iu',
            (string) $recent
        );
    }
}
