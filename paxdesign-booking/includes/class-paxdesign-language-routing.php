<?php
/**
 * Customer language detection and employee spoken-language routing.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Language_Routing {

    const SUPPORTED = array('de', 'en', 'ar');
    const USER_META = 'pax_live_spoken_languages';

    /**
     * @return string One of de|en|ar or empty when unknown.
     */
    public static function detect_text_language($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
            return 'ar';
        }

        if (preg_match('/[äöüßÄÖÜ]/u', $text)) {
            return 'de';
        }

        if (preg_match('/\b(und|oder|ich|Sie|danke|bitte|Hallo|Guten|können|möchte|wir|nicht|auch)\b/u', $text)) {
            return 'de';
        }

        if (preg_match('/\b(the|and|or|you|hello|thanks|please|can|would|your|help)\b/i', $text)) {
            return 'en';
        }

        return 'de';
    }

    /**
     * Detect when a customer wants a human agent (DE / EN / AR).
     */
    public static function is_live_agent_intent($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }

        $patterns = array(
            // German (website widget parity)
            '/(?:mit\s+(?:einem\s+)?(?:mitarbeiter|menschen|echten|support|agent|berater|person)|live\s*(?:agent|chat|support)|(?:kann|möchte|will|darf)\s+ich\s+(?:mit\s+)?(?:einem\s+)?(?:menschen|mitarbeiter|support|agent|berater|person)|(?:kann|darf)\s+ich\s+mit|sprechen\s+(?:sie\s+)?mit|echter?\s+mensch|menschlichen?\s+support|echte\s+person|weiterleiten|übergeben|uebergeben|mitarbeiter\s+sprechen)/iu',
            // English
            '/(?:speak\s+(?:to|with)\s+(?:a\s+)?(?:human|person|agent|representative|support|employee|someone)|talk\s+to\s+(?:a\s+)?(?:human|person|agent|someone|support|employee)|real\s+(?:person|human|agent|employee)|human\s+(?:support|agent)|live\s+(?:agent|support|chat)|connect\s+me\s+(?:to|with)\s+(?:a\s+)?(?:human|agent|support|person|employee)|transfer\s+(?:me\s+)?(?:to\s+)?(?:a\s+)?(?:human|agent|person|representative|support)|(?:need|want)\s+(?:a\s+)?(?:human|person|agent|representative))/iu',
            // Arabic
            '/(?:موظف|موظف(?:اً|ا)?|شخص\s+حقيقي|دعم\s+بشري|تحدث\s+مع|تكلم\s+مع|أريد\s+(?:موظف|شخص|إنسان)|اريد\s+(?:موظف|شخص|إنسان)|وكيل\s+حقيقي|مساعد\s+بشري|خدمة\s+عملاء|ممثل\s+حقيقي)/u',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Localized thanks message after live-agent handoff.
     *
     * @param string $lang de|en|ar
     */
    public static function live_handoff_thanks_message($lang) {
        switch (sanitize_key($lang)) {
            case 'en':
                return 'Connecting you to a Live Agent...';
            case 'ar':
                return 'جاري توصيلك بوكيل مباشر...';
            default:
                return 'Ich verbinde Sie mit einem Live-Agent...';
        }
    }

    /**
     * Localized system notice after live-agent handoff.
     *
     * @param string $lang de|en|ar
     */
    public static function live_handoff_notice_message($lang) {
        switch (sanitize_key($lang)) {
            case 'en':
                return 'A PAXDesign team member has been notified. Please stay in the chat for a moment.';
            case 'ar':
                return 'تم إبلاغ أحد موظفي PAXDesign. يرجى البقاء في الدردشة لحظة.';
            default:
                return 'Ein PAXDesign-Mitarbeiter wurde informiert. Bitte bleiben Sie kurz im Chat.';
        }
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return string
     */
    public static function detect_from_messages($messages) {
        if (!is_array($messages)) {
            return '';
        }

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            if (!is_array($msg) || empty($msg['role']) || (string) $msg['role'] !== 'user') {
                continue;
            }
            $content = isset($msg['content']) ? (string) $msg['content'] : '';
            $lang = self::detect_text_language($content);
            if ($lang !== '') {
                return $lang;
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    public static function normalize_language_list($raw) {
        if (!is_array($raw)) {
            $raw = array($raw);
        }

        $out = array();
        foreach ($raw as $item) {
            $code = sanitize_key((string) $item);
            if ($code !== '' && in_array($code, self::SUPPORTED, true) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }

        if (empty($out)) {
            return array('de', 'en');
        }

        return $out;
    }

    /**
     * @return string[]
     */
    public static function get_user_spoken_languages($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return array('de', 'en');
        }

        $raw = get_user_meta($user_id, self::USER_META, true);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::normalize_language_list($decoded);
            }
        }

        if (is_array($raw)) {
            return self::normalize_language_list($raw);
        }

        return array('de', 'en');
    }

    /**
     * @param string[] $languages
     */
    public static function save_user_spoken_languages($user_id, $languages) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }
        update_user_meta($user_id, self::USER_META, self::normalize_language_list($languages));
    }

    /**
     * @param int    $user_id
     * @param string $language
     */
    public static function user_speaks_language($user_id, $language) {
        $language = sanitize_key((string) $language);
        if ($language === '') {
            return true;
        }
        return in_array($language, self::get_user_spoken_languages($user_id), true);
    }

    /**
     * @param string $language
     * @return int[]
     */
    public static function admin_user_ids_for_language($language) {
        $language = sanitize_key((string) $language);
        $all = array();
        if (class_exists('PAXdesign_APNS')) {
            $all = PAXdesign_APNS::get_admin_user_ids();
        }

        if ($language === '' || empty($all)) {
            return $all;
        }

        $matched = array();
        foreach ($all as $user_id) {
            if (self::user_speaks_language((int) $user_id, $language)) {
                $matched[] = (int) $user_id;
            }
        }

        return !empty($matched) ? $matched : $all;
    }

    /**
     * @param object|null $row
     * @return string
     */
    public static function session_language_from_row($row) {
        if (!$row) {
            return '';
        }

        if (isset($row->customer_language)) {
            $stored = sanitize_key((string) $row->customer_language);
            if ($stored !== '' && in_array($stored, self::SUPPORTED, true)) {
                return $stored;
            }
        }

        if (!class_exists('PAXdesign_Chat_Live')) {
            return '';
        }

        $messages = PAXdesign_Chat_Live::get_instance()->decode_messages(
            isset($row->messages) ? $row->messages : '[]'
        );

        return self::detect_from_messages($messages);
    }

    /**
     * @param string $code
     * @return string
     */
    public static function label($code) {
        switch (sanitize_key((string) $code)) {
            case 'ar':
                return 'العربية';
            case 'en':
                return 'English';
            case 'de':
            default:
                return 'Deutsch';
        }
    }
}
