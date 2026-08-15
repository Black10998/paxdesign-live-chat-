<?php
/**
 * Cybercrime Support AI case sync — chat and the case page share one CCS record.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_AI_Case {

    const CONTEXT = 'cybercrime-support';

    /**
     * Known platforms extracted from natural-language incident reports.
     *
     * @var array<string, string>
     */
    private static $platform_aliases = array(
        'github'     => 'GitHub',
        'gmail'      => 'Gmail',
        'google'     => 'Google',
        'email'      => 'Email',
        'e-mail'     => 'Email',
        'mail'       => 'Email',
        'outlook'    => 'Outlook',
        'hotmail'    => 'Hotmail',
        'yahoo'      => 'Yahoo',
        'icloud'     => 'iCloud',
        'apple'      => 'Apple',
        'facebook'   => 'Facebook',
        'instagram'  => 'Instagram',
        'whatsapp'   => 'WhatsApp',
        'telegram'   => 'Telegram',
        'tiktok'     => 'TikTok',
        'youtube'    => 'YouTube',
        'linkedin'   => 'LinkedIn',
        'snapchat'   => 'Snapchat',
        'discord'    => 'Discord',
        'twitter'    => 'X',
        'paypal'     => 'PayPal',
        'amazon'     => 'Amazon',
        'ebay'       => 'eBay',
        'binance'    => 'Binance',
        'coinbase'   => 'Coinbase',
        'microsoft'  => 'Microsoft',
        'steam'      => 'Steam',
        'netflix'    => 'Netflix',
        'dropbox'    => 'Dropbox',
        'slack'      => 'Slack',
        'zoom'       => 'Zoom',
        'reddit'     => 'Reddit',
        'pinterest'  => 'Pinterest',
        'uber'       => 'Uber',
        'airbnb'     => 'Airbnb',
    );

    public static function init() {
        // Chat ingest is invoked from the authenticated chat pipeline so each
        // message is written to the CCS case before the model replies.
    }

    /**
     * @param string $session_id
     * @return bool
     */
    public static function is_ccs_session($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id !== '') {
            $stored = get_transient('pax_chat_page_ctx_' . md5($session_id));
            if (is_string($stored) && sanitize_key($stored) === self::CONTEXT) {
                return true;
            }
        }

        $referrer = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';
        if ($referrer !== '' && strpos($referrer, '/cybercrime-support') !== false) {
            return true;
        }

        if (isset($_POST['page_context']) && sanitize_key(wp_unslash($_POST['page_context'])) === self::CONTEXT) {
            return true;
        }

        if ($session_id !== '' && class_exists('PAXdesign_Cybercrime_Tickets')) {
            $bound = PAXdesign_Cybercrime_Tickets::get_reference_for_session($session_id);
            if (is_string($bound) && $bound !== '') {
                return true;
            }
        }

        if ($session_id !== '') {
            $stored_ref = get_transient('pax_chat_page_ref_' . md5($session_id));
            if (is_string($stored_ref) && $stored_ref !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Payload for unauthenticated CCS chat attempts.
     *
     * @return array<string, mixed>
     */
    public static function login_required_payload() {
        $config = class_exists('PAXdesign_Cybercrime_Intake')
            ? PAXdesign_Cybercrime_Intake::public_config()
            : array();

        return array(
            'message'   => __('Sign in to use Cybercrime Support AI. Your information is saved to your own case.', 'paxdesign-booking'),
            'code'      => 'login_required',
            'login_url' => (string) ($config['loginUrl'] ?? ''),
            'context'   => self::CONTEXT,
        );
    }

    /**
     * Ingest one authenticated customer chat message into the real CCS case.
     *
     * @param int    $user_id
     * @param string $session_id
     * @param string $message
     * @param string $focus_reference
     * @return array<string, mixed>|WP_Error
     */
    public static function ingest_chat_message($user_id, $session_id, $message, $focus_reference = '') {
        $user_id = absint($user_id);
        $session_id = sanitize_text_field((string) $session_id);
        $message = trim((string) $message);

        if ($user_id <= 0) {
            return new WP_Error(
                'login_required',
                __('Sign in to use Cybercrime Support AI.', 'paxdesign-booking'),
                array('status' => 401) + self::login_required_payload()
            );
        }

        if (!class_exists('PAXdesign_Cybercrime_Tickets') || !class_exists('PAXdesign_Cybercrime_Intake')) {
            return new WP_Error('unavailable', __('Cybercrime Support is temporarily unavailable.', 'paxdesign-booking'), array('status' => 503));
        }

        $row = self::ensure_case_for_user($user_id, $session_id, $focus_reference, $message);
        if (is_wp_error($row)) {
            return $row;
        }
        if (!is_array($row) || empty($row['reference_id'])) {
            return new WP_Error('case_unavailable', __('Could not open your Cybercrime Support case.', 'paxdesign-booking'), array('status' => 500));
        }

        $reference = (string) $row['reference_id'];
        self::bind_session($reference, $session_id, $user_id);

        $explicit_new = self::is_explicit_new_case_request($message);
        if ($explicit_new && class_exists('PAXdesign_Chat')) {
            PAXdesign_Chat::get_instance()->reset_ccs_conversation_epoch($session_id);
        }

        $skip_extract = $explicit_new;
        if (
            !$skip_extract
            && class_exists('PAXdesign_Cybercrime_AI_Operations')
            && PAXdesign_Cybercrime_AI_Operations::is_same_case_continuation($message)
            && !(class_exists('PAXdesign_Cybercrime_AI_Workflow') && (
                PAXdesign_Cybercrime_AI_Workflow::is_submit_intent($message)
                || PAXdesign_Cybercrime_AI_Workflow::is_workflow_help_intent($message)
            ))
        ) {
            $skip_extract = true;
        }

        $extracted = array();
        if ($message !== '' && !$skip_extract) {
            $existing = self::case_fields_from_row($row);
            $extracted = self::extract_fields_from_message($message, $existing);
        }
        if ($message !== '' && class_exists('PAXdesign_Cybercrime_AI_Workflow')) {
            $wf_fields = PAXdesign_Cybercrime_AI_Workflow::extract_from_message(
                $message,
                PAXdesign_Cybercrime_AI_Workflow::state_from_row($row)
            );
            unset($wf_fields['submit_intent']);
            if (!empty($wf_fields)) {
                $extracted = array_merge($extracted, $wf_fields);
            }
        }
        if (!empty($extracted)) {
            $updated = self::apply_extracted_fields($reference, $user_id, $extracted, 'chat');
            if (!is_wp_error($updated) && is_array($updated)) {
                $row = $updated;
            }
        }
        if (class_exists('PAXdesign_Cybercrime_AI_Workflow')) {
            $payload_now = json_decode((string) ($row['payload'] ?? ''), true);
            $locale = is_array($payload_now) ? sanitize_key((string) ($payload_now['locale'] ?? '')) : '';
            $row = PAXdesign_Cybercrime_AI_Workflow::persist_snapshot(
                $row,
                PAXdesign_Cybercrime_AI_Workflow::snapshot($row, $locale)
            );
        }

        if (class_exists('PAXdesign_Chat')) {
            PAXdesign_Chat::get_instance()->set_session_page_context($session_id, self::CONTEXT, $reference, '');
        } else {
            set_transient('pax_chat_page_ref_' . md5($session_id), $reference, DAY_IN_SECONDS);
        }

        return PAXdesign_Cybercrime_Tickets::format_report_row(
            is_array($row) && isset($row['payload']) ? $row : PAXdesign_Cybercrime_Tickets::get_report_row($reference),
            true
        );
    }

    /**
     * @param int    $user_id
     * @param string $session_id
     * @param string $focus_reference
     * @param string $latest_message
     * @return array<string, mixed>|WP_Error
     */
    public static function ensure_case_for_user($user_id, $session_id = '', $focus_reference = '', $latest_message = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('login_required', __('Please sign in.', 'paxdesign-booking'), array('status' => 401));
        }

        PAXdesign_Cybercrime_Tickets::ensure_schema();
        PAXdesign_Cybercrime_Intake::ensure_schema();

        $explicit_new = self::is_explicit_new_case_request($latest_message);
        $session_id = sanitize_text_field((string) $session_id);
        $focus_reference = sanitize_text_field((string) $focus_reference);

        if ($focus_reference !== '' && !$explicit_new) {
            $focused = PAXdesign_Cybercrime_Tickets::get_report_row($focus_reference);
            if (is_array($focused) && PAXdesign_Cybercrime_Tickets::user_can_view_report($focused, $user_id)) {
                return $focused;
            }
        }

        if ($session_id !== '' && !$explicit_new) {
            $bound_ref = PAXdesign_Cybercrime_Tickets::get_reference_for_session($session_id);
            if (is_string($bound_ref) && $bound_ref !== '') {
                $bound = PAXdesign_Cybercrime_Tickets::get_report_row($bound_ref);
                if (is_array($bound) && PAXdesign_Cybercrime_Tickets::user_can_view_report($bound, $user_id)) {
                    return $bound;
                }
            }
        }

        $active = PAXdesign_Cybercrime_Tickets::get_active_report_for_user($user_id);
        if (is_array($active) && !empty($active['reference_id']) && !$explicit_new) {
            $row = PAXdesign_Cybercrime_Tickets::get_report_row((string) $active['reference_id']);
            if (is_array($row) && PAXdesign_Cybercrime_Tickets::user_can_view_report($row, $user_id)) {
                return $row;
            }
        }

        return PAXdesign_Cybercrime_Intake::create_draft_for_user($user_id, $session_id, $explicit_new);
    }

    /**
     * A new CCS case/conversation starts only on an explicit customer request.
     *
     * Follow-ups, language switches, and “help me submit a report” stay on the
     * current reference. “ابدأ من الصفر” / “Start a new case” must not.
     *
     * @param string $text
     * @return bool
     */
    public static function is_explicit_new_case_request($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }
        $normalized = self::normalize_match_text($text);
        if ($normalized === '') {
            return false;
        }
        $len = function_exists('mb_strlen') ? mb_strlen($normalized) : strlen($normalized);
        if ($len > 140) {
            return false;
        }
        $explicit = (bool) preg_match(
            '/(?:start (?:a |the )?(?:brand )?new (?:case|report|conversation|chat)|open (?:a |the )?new (?:case|report)|submit (?:a |the )?new (?:case|report)|file (?:a |the )?new (?:case|report)|start (?:over|from scratch)|from scratch|brand new (?:case|report)|want (?:a |to (?:start|open|file|submit) (?:a )?)?new (?:case|report)|أريد (?:فتح|تقديم|إرسال|ارسال) بلاغ جديد|اريد (?:فتح|تقديم|ارسال) بلاغ جديد|افتح بلاغ جديد|أبدأ (?:من الصفر|من جديد|بلاغ جديد)|ابدأ (?:من الصفر|من جديد|بلاغ جديد)|ابدا (?:من الصفر|من جديد|بلاغ جديد)|ابدئي (?:من الصفر|من جديد)|من الصفر|حالة جديدة تماما|تقرير جديد تماما|neuen fall (?:starten|eroffnen|eröffnen)|neuen bericht|von vorne|von neuem|neu beginnen|neuer fall|neue meldung)/u',
            $normalized
        );
        $short = $len <= 48 && (bool) preg_match(
            '/^(?:new report|new case|start over|start from scratch|from scratch|بلاغ جديد|تقرير جديد|حالة جديدة|من الصفر|أبدأ من الصفر|ابدأ من الصفر|ابدا من الصفر|ابدأ من جديد|neuen fall|neuen bericht|neues (?:gespräch|ticket)|von vorne|von neuem|neu beginnen)$/u',
            $normalized
        );
        return $explicit || $short;
    }

    /**
     * Heuristic extraction of CCS fields from one customer message.
     * Safe to call without WordPress (tests stub sanitizers).
     *
     * @param string               $text
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    public static function extract_fields_from_message($text, $existing = array()) {
        $text = trim((string) $text);
        if ($text === '') {
            return array();
        }

        $existing = is_array($existing) ? $existing : array();
        $fields = array();
        $normalized = self::normalize_match_text($text);

        $category = self::detect_category($normalized, $text);
        if ($category !== '') {
            $fields['category'] = $category;
        }

        $date = self::detect_incident_date($text, $normalized);
        if (!empty($date['incident_date'])) {
            $fields['incident_date'] = $date['incident_date'];
            if (!empty($date['incident_time'])) {
                $fields['incident_time'] = $date['incident_time'];
            }
            if (!empty($date['incident_at_sql'])) {
                $fields['incident_at'] = $date['incident_at_sql'];
            }
        }

        $platforms = self::detect_platforms($normalized, $text);
        if (!empty($platforms)) {
            $replace = self::looks_like_correction($normalized);
            $merged = self::merge_platform_list(
                $replace ? '' : (string) ($existing['platforms'] ?? ''),
                $platforms,
                $replace
            );
            if ($merged !== '') {
                $fields['platforms'] = $merged;
                $fields['platforms_replace'] = $replace;
            }
        }

        $urgency = self::detect_urgency($normalized);
        if ($urgency !== '') {
            $fields['urgency'] = $urgency;
        }

        $loss = self::detect_financial_loss($text);
        if ($loss !== null) {
            $fields['financial_loss'] = $loss['amount'];
            if (!empty($loss['currency'])) {
                $fields['financial_currency'] = $loss['currency'];
            }
        }

        $summary = self::structured_summary($fields, $existing);
        if ($summary !== '') {
            $existing_desc = trim((string) ($existing['description'] ?? ''));
            if (self::should_replace_description($existing_desc, $summary, $text)) {
                $fields['description'] = $summary;
            }
        }

        return $fields;
    }

    /**
     * Persist extracted fields onto the customer's own CCS row.
     *
     * @param string               $reference_id
     * @param int                  $user_id
     * @param array<string, mixed> $fields
     * @param string               $source
     * @return array<string, mixed>|WP_Error
     */
    public static function apply_extracted_fields($reference_id, $user_id, $fields, $source = 'chat') {
        $reference_id = sanitize_text_field((string) $reference_id);
        $user_id = absint($user_id);
        $fields = is_array($fields) ? $fields : array();
        $source = sanitize_key((string) $source);

        if ($reference_id === '' || $user_id <= 0 || empty($fields)) {
            return new WP_Error('invalid', __('Nothing to save.', 'paxdesign-booking'));
        }

        $row = PAXdesign_Cybercrime_Tickets::get_report_row($reference_id);
        if (!$row || !PAXdesign_Cybercrime_Tickets::user_can_view_report($row, $user_id)) {
            return new WP_Error('forbidden', __('You cannot update this report.', 'paxdesign-booking'), array('status' => 403));
        }
        if (!PAXdesign_Cybercrime_Tickets::is_active_status((string) ($row['status'] ?? ''))) {
            return $row;
        }

        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }

        $changed = array();
        $update = array();

        if (!empty($fields['category'])) {
            $category = sanitize_key((string) $fields['category']);
            $allowed = class_exists('PAXdesign_Cybercrime_Intake')
                ? PAXdesign_Cybercrime_Intake::category_keys()
                : array();
            if (in_array($category, $allowed, true) && $category !== (string) ($row['category'] ?? '')) {
                $update['category'] = $category;
                $changed[] = 'incident type';
            }
        }

        if (!empty($fields['urgency'])) {
            $urgency = sanitize_key((string) $fields['urgency']);
            $levels = class_exists('PAXdesign_Cybercrime_Intake')
                ? PAXdesign_Cybercrime_Intake::urgency_keys()
                : array('low', 'medium', 'high', 'critical');
            if (in_array($urgency, $levels, true) && $urgency !== (string) ($row['urgency'] ?? '')) {
                $update['urgency'] = $urgency;
                $changed[] = 'urgency';
            }
        }

        if (!empty($fields['incident_at']) || !empty($fields['incident_date'])) {
            $incident_at = (string) ($fields['incident_at'] ?? '');
            $incident_date = sanitize_text_field((string) ($fields['incident_date'] ?? ''));
            $incident_time = sanitize_text_field((string) ($fields['incident_time'] ?? ($payload['incident_time'] ?? '')));
            if ($incident_at === '' && $incident_date !== '') {
                $combined = trim($incident_date . ' ' . ($incident_time !== '' ? $incident_time : '00:00'));
                $ts = strtotime($combined);
                if ($ts !== false) {
                    $incident_at = gmdate('Y-m-d H:i:s', $ts);
                }
            }
            if ($incident_at !== '' && $incident_at !== (string) ($row['incident_at'] ?? '')) {
                $update['incident_at'] = $incident_at;
                $payload['incident_date'] = $incident_date !== '' ? $incident_date : substr($incident_at, 0, 10);
                if ($incident_time !== '') {
                    $payload['incident_time'] = $incident_time;
                }
                $changed[] = 'incident date';
            }
        }

        if (!empty($fields['platforms'])) {
            $next_platforms = sanitize_textarea_field((string) $fields['platforms']);
            $current = (string) ($payload['platforms'] ?? '');
            if ($next_platforms !== '' && strcasecmp($next_platforms, $current) !== 0) {
                $payload['platforms'] = $next_platforms;
                $changed[] = 'affected platforms';
            }
        }

        if (!empty($fields['description'])) {
            $desc = sanitize_textarea_field((string) $fields['description']);
            if ($desc !== '' && $desc !== (string) ($payload['description'] ?? '')) {
                $payload['description'] = $desc;
                $changed[] = 'summary';
            }
        }

        if (isset($fields['financial_loss']) && (string) $fields['financial_loss'] !== '') {
            $loss = sanitize_text_field((string) $fields['financial_loss']);
            if ($loss !== (string) ($payload['financial_loss'] ?? '')) {
                $payload['financial_loss'] = $loss;
                $changed[] = 'financial loss';
            }
        }
        if (!empty($fields['financial_currency'])) {
            $payload['financial_currency'] = strtoupper(sanitize_text_field((string) $fields['financial_currency']));
        }

        if (!empty($fields['reporter_name'])) {
            $name = sanitize_text_field((string) $fields['reporter_name']);
            if (strlen($name) >= 2 && $name !== (string) ($row['reporter_name'] ?? '')) {
                $update['reporter_name'] = $name;
                $changed[] = 'full legal name';
            }
        }
        if (!empty($fields['reporter_email'])) {
            $email = sanitize_email((string) $fields['reporter_email']);
            $valid = $email !== '' && (function_exists('is_email') ? is_email($email) : (bool) preg_match('/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', $email));
            if ($valid && strcasecmp($email, (string) ($row['reporter_email'] ?? '')) !== 0) {
                $update['reporter_email'] = $email;
                $changed[] = 'email';
            }
        }
        if (!empty($fields['identity_accuracy']) && empty($payload['identity_accuracy'])) {
            $payload['identity_accuracy'] = true;
            $changed[] = 'identity accuracy';
        }
        if (!empty($fields['declarations']) && is_array($fields['declarations'])) {
            $next_decl = array(
                'truthful'      => !empty($fields['declarations']['truthful']),
                'false_reports' => !empty($fields['declarations']['false_reports']),
                'verification'  => !empty($fields['declarations']['verification']),
            );
            $current_decl = is_array($payload['declarations'] ?? null) ? $payload['declarations'] : array();
            if ($next_decl !== array(
                'truthful' => !empty($current_decl['truthful']),
                'false_reports' => !empty($current_decl['false_reports']),
                'verification' => !empty($current_decl['verification']),
            )) {
                $payload['declarations'] = $next_decl;
                $changed[] = 'review declarations';
            }
        }
        if (!empty($fields['country_code'])) {
            $code = strtoupper(sanitize_text_field((string) $fields['country_code']));
            if (preg_match('/^[A-Z]{2}$/', $code)) {
                $payload['country_code'] = $code;
            }
        }

        if (!empty($fields['reporter_phone'])) {
            $phone = sanitize_text_field((string) $fields['reporter_phone']);
            if ($phone !== '' && $phone !== (string) ($row['reporter_phone'] ?? '')) {
                $update['reporter_phone'] = $phone;
                $changed[] = 'phone';
            }
        }
        if (!empty($fields['reporter_country'])) {
            $country = sanitize_text_field((string) $fields['reporter_country']);
            if ($country !== '' && $country !== (string) ($row['reporter_country'] ?? '')) {
                $update['reporter_country'] = $country;
                $changed[] = 'country';
            }
        }

        $payload['source'] = $source === 'chat' ? 'ai_chat' : sanitize_key((string) ($payload['source'] ?? $source));
        $payload['last_chat_update_at'] = current_time('mysql', true);
        $guided = is_array($payload['guided_case'] ?? null) ? $payload['guided_case'] : array();
        $guided['updated_via'] = $source === 'chat' ? 'ai_chat' : $source;
        $payload['guided_case'] = $guided;

        if (empty($changed) && empty($update)) {
            return $row;
        }

        $now = current_time('mysql', true);
        $update['payload'] = wp_json_encode($payload);
        $update['updated_at'] = $now;

        global $wpdb;
        $formats = array();
        foreach ($update as $key => $value) {
            $formats[] = ($key === 'needs_human_review') ? '%d' : '%s';
        }
        $updated = $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            $update,
            array('reference_id' => $reference_id, 'customer_user_id' => $user_id),
            $formats,
            array('%s', '%d')
        );

        if ($updated === false || (int) $updated === 0) {
            if (!PAXdesign_Cybercrime_Tickets::user_can_view_report($row, $user_id)) {
                return new WP_Error('forbidden', __('You cannot update this report.', 'paxdesign-booking'), array('status' => 403));
            }
            $wpdb->update(
                PAXdesign_Cybercrime_Intake::table_name(),
                $update,
                array('reference_id' => $reference_id),
                $formats,
                array('%s')
            );
        }

        $fresh = PAXdesign_Cybercrime_Tickets::get_report_row($reference_id);
        if (!is_array($fresh) || !PAXdesign_Cybercrime_Tickets::user_can_view_report($fresh, $user_id)) {
            return new WP_Error('forbidden', __('You cannot update this report.', 'paxdesign-booking'), array('status' => 403));
        }

        $label = implode(', ', array_unique($changed));
        $body = sprintf(
            /* translators: 1: list of updated fields, 2: CCS reference */
            __('Case updated from AI chat (%1$s) on %2$s.', 'paxdesign-booking'),
            $label !== '' ? $label : __('details', 'paxdesign-booking'),
            $reference_id
        );
        PAXdesign_Cybercrime_Tickets::add_message(
            $reference_id,
            'system',
            $body,
            'portal',
            $user_id,
            array(
                'event'              => 'ai_case_update',
                'visible_to_customer'=> true,
                'source'             => 'ai_chat',
                'fields'             => array_values(array_unique($changed)),
            )
        );

        return $fresh;
    }

    /**
     * Attach a chat media upload to the authenticated customer's CCS case.
     *
     * @param int                  $user_id
     * @param string               $session_id
     * @param array<string, mixed> $upload
     * @param string               $kind
     * @param string               $caption
     * @return array<string, mixed>|WP_Error|null
     */
    public static function attach_chat_upload($user_id, $session_id, $upload, $kind = 'file', $caption = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !is_array($upload)) {
            return new WP_Error('login_required', __('Please sign in.', 'paxdesign-booking'), array('status' => 401));
        }

        $row = self::ensure_case_for_user($user_id, $session_id, '');
        if (is_wp_error($row)) {
            return $row;
        }
        $reference = (string) ($row['reference_id'] ?? '');
        if ($reference === '' || !PAXdesign_Cybercrime_Tickets::user_can_view_report($row, $user_id)) {
            return new WP_Error('forbidden', __('You cannot update this report.', 'paxdesign-booking'), array('status' => 403));
        }
        if (!PAXdesign_Cybercrime_Tickets::is_active_status((string) ($row['status'] ?? ''))) {
            return $row;
        }

        $field = self::guess_attachment_field($kind, $caption, (string) ($upload['name'] ?? ''), $row);
        $path = (string) ($upload['file'] ?? $upload['path'] ?? '');
        $sha256 = (string) ($upload['sha256'] ?? '');
        if ($sha256 === '' && $path !== '' && is_readable($path)) {
            $sha256 = hash_file('sha256', $path);
        }
        $size = (string) ($upload['size'] ?? '');
        if ($size === '' && $path !== '' && is_file($path)) {
            $size = (string) filesize($path);
        }
        $record = array(
            'field'         => $field,
            'name'          => sanitize_file_name((string) ($upload['name'] ?? 'file')),
            'original_name' => sanitize_file_name((string) ($upload['name'] ?? 'file')),
            'url'           => esc_url_raw((string) ($upload['url'] ?? '')),
            'path'          => $path,
            'type'          => sanitize_text_field((string) ($upload['mime'] ?? $upload['type'] ?? '')),
            'size'          => $size,
            'sha256'        => $sha256,
            'source'        => 'ai_chat',
        );
        if ($record['url'] === '' && $record['name'] === 'file') {
            return $row;
        }

        $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($attachments)) {
            $attachments = array();
        }
        $attachments[] = $record;

        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $payload['last_chat_update_at'] = current_time('mysql', true);
        $payload['pending_document_check'] = true;

        global $wpdb;
        $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array(
                'attachments' => wp_json_encode($attachments),
                'payload'     => wp_json_encode($payload),
                'updated_at'  => current_time('mysql', true),
            ),
            array('reference_id' => $reference),
            array('%s', '%s', '%s'),
            array('%s')
        );

        $fresh = PAXdesign_Cybercrime_Tickets::get_report_row($reference);
        if (is_array($fresh) && class_exists('PAXdesign_Cybercrime_AI_Workflow')) {
            $fresh = PAXdesign_Cybercrime_AI_Workflow::persist_snapshot(
                $fresh,
                PAXdesign_Cybercrime_AI_Workflow::snapshot($fresh)
            );
        }
        PAXdesign_Cybercrime_Tickets::add_message(
            $reference,
            'system',
            sprintf(
                /* translators: 1: file name, 2: CCS reference */
                __('File “%1$s” attached to case %2$s from AI chat.', 'paxdesign-booking'),
                $record['name'],
                $reference
            ),
            'portal',
            $user_id,
            array(
                'event'               => 'ai_case_attachment',
                'visible_to_customer' => true,
                'field'               => $field,
            )
        );

        return is_array($fresh) ? $fresh : $row;
    }

    /**
     * Compact case payload safe to send to the same authenticated customer.
     *
     * @param array<string, mixed>|null $report
     * @return array<string, mixed>
     */
    public static function public_case_sync_payload($report) {
        if (!is_array($report)) {
            return array();
        }
        $original = is_array($report['original_request'] ?? null) ? $report['original_request'] : array();
        $out = array(
            'reference_id'     => (string) ($report['reference_id'] ?? ''),
            'status'           => (string) ($report['status'] ?? ''),
            'status_label'     => (string) ($report['status_label'] ?? ''),
            'customer_status'  => (string) ($report['customer_status'] ?? ''),
            'is_active'        => !empty($report['is_active']),
            'is_draft'         => !empty($report['is_draft']) || ((string) ($report['status'] ?? '')) === 'draft',
            'category'         => (string) ($report['category'] ?? ''),
            'category_label'   => (string) ($report['category_label'] ?? ''),
            'incident_at'      => (string) ($report['incident_at'] ?? ''),
            'platforms'        => (string) ($report['platforms'] ?? ''),
            'financial_loss'   => (string) ($report['financial_loss'] ?? ''),
            'description'      => (string) ($report['description'] ?? ''),
            'next_action'      => (string) ($report['next_action'] ?? ''),
            'missing_fields'   => array_values((array) ($report['missing_fields'] ?? array())),
            'original_request' => $original,
            'created_at'       => (string) ($report['created_at'] ?? ''),
            'updated_at'       => (string) ($report['updated_at'] ?? ''),
            'rejection'        => is_array($report['rejection'] ?? null) ? $report['rejection'] : null,
            'workflow'         => is_array($report['workflow'] ?? null) ? $report['workflow'] : null,
        );
        if (empty($out['workflow']) && class_exists('PAXdesign_Cybercrime_AI_Workflow') && ($out['is_draft'] || isset($report['payload']))) {
            $out['workflow'] = PAXdesign_Cybercrime_AI_Workflow::snapshot($report, (string) ($report['locale'] ?? ''));
        }
        if (class_exists('PAXdesign_Cybercrime_AI_Operations')) {
            $operation = is_array($report['ai_operation'] ?? null) ? $report['ai_operation'] : null;
            if (!$operation) {
                $payload = json_decode((string) ($report['payload'] ?? ''), true);
                if (is_array($payload) && !empty($payload['ai_operations']) && is_array($payload['ai_operations'])) {
                    $last = end($payload['ai_operations']);
                    if (is_array($last)) {
                        $operation = $last;
                    }
                }
            }
            if (is_array($operation) && !empty($operation['id'])) {
                $out['ai_operation'] = PAXdesign_Cybercrime_AI_Operations::public_operation($operation);
            }
        }
        return $out;
    }

    /**
     * @param string $session_id
     * @param string $role
     * @param string $content
     * @param int    $message_id
     */
    public static function on_chat_message($session_id, $role, $content, $message_id = 0) {
        unset($message_id);
        if ($role !== 'user' || !self::is_ccs_session($session_id)) {
            return;
        }
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }
        $focus = '';
        $key = md5(sanitize_text_field((string) $session_id));
        $stored = get_transient('pax_chat_page_ref_' . $key);
        if (is_string($stored)) {
            $focus = $stored;
        }
        self::ingest_chat_message($user_id, $session_id, (string) $content, $focus);
    }

    /**
     * @param string $reference
     * @param string $session_id
     * @param int    $user_id
     */
    private static function bind_session($reference, $session_id, $user_id) {
        $reference = sanitize_text_field((string) $reference);
        $session_id = sanitize_text_field((string) $session_id);
        $user_id = absint($user_id);
        if ($reference === '' || $session_id === '' || $user_id <= 0) {
            return;
        }
        $row = PAXdesign_Cybercrime_Tickets::get_report_row($reference);
        if (!$row || !PAXdesign_Cybercrime_Tickets::user_can_view_report($row, $user_id)) {
            return;
        }
        if ((string) ($row['chat_session_id'] ?? '') === $session_id) {
            return;
        }
        global $wpdb;
        $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array('chat_session_id' => $session_id, 'updated_at' => current_time('mysql', true)),
            array('reference_id' => $reference),
            array('%s', '%s'),
            array('%s')
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function case_fields_from_row($row) {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        return array(
            'category'           => (string) ($row['category'] ?? ''),
            'urgency'            => (string) ($row['urgency'] ?? ''),
            'incident_at'        => (string) ($row['incident_at'] ?? ''),
            'platforms'          => (string) ($payload['platforms'] ?? ''),
            'description'        => (string) ($payload['description'] ?? ''),
            'full_name'          => (string) ($row['reporter_name'] ?? ''),
            'email'              => (string) ($row['reporter_email'] ?? ''),
            'phone'              => (string) ($row['reporter_phone'] ?? ''),
            'country'            => (string) ($row['reporter_country'] ?? ''),
            'identity_accuracy'  => !empty($payload['identity_accuracy']),
        );
    }

    /**
     * @param string                    $kind
     * @param string                    $caption
     * @param string                    $filename
     * @param array<string, mixed>|null $row
     * @return string
     */
    private static function guess_attachment_field($kind, $caption, $filename, $row = null) {
        $hay = self::normalize_match_text($caption . ' ' . $filename . ' ' . $kind);
        if (preg_match('/\b(passport|national id|identity|ausweis|reisepass|هوية|جواز)\b/u', $hay)) {
            return 'identity_document';
        }
        $needs_id = true;
        if (is_array($row) && class_exists('PAXdesign_Cybercrime_AI_Workflow')) {
            $state = PAXdesign_Cybercrime_AI_Workflow::state_from_row($row);
            $needs_id = empty($state['identity_document']);
        } elseif (is_array($row)) {
            $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
            $needs_id = true;
            if (is_array($attachments)) {
                foreach ($attachments as $file) {
                    if (is_array($file) && sanitize_key((string) ($file['field'] ?? '')) === 'identity_document') {
                        $needs_id = false;
                        break;
                    }
                }
            }
        }
        if ($needs_id) {
            return 'identity_document';
        }
        if (preg_match('/\b(screenshot|screen shot|png|img_|photo)/u', $hay) || preg_match('/\.(jpe?g|png|gif|webp|heic|heif)$/i', $filename)) {
            return 'evidence_screenshots';
        }
        if (preg_match('/\b(chat|whatsapp|telegram|imessage)\b/u', $hay) || preg_match('/\.(txt|csv)$/i', $filename)) {
            return 'evidence_chats';
        }
        if (preg_match('/\.(pdf|docx?)$/i', $filename)) {
            return 'evidence_documents';
        }
        return 'evidence_other';
    }

    /**
     * @param string $text
     * @return string
     */
    private static function normalize_match_text($text) {
        $text = strtolower((string) $text);
        $text = str_replace(array('’', '‘', '`'), "'", $text);
        return $text;
    }

    /**
     * @param string $normalized
     * @param string $original
     * @return string
     */
    private static function detect_category($normalized, $original) {
        unset($original);
        $map = array(
            'account_takeover'       => array('account takeover', 'kontoübernahme', 'استيلاء على حساب', 'compromised', 'hacked', 'takeover', 'taken over', 'account stolen', 'logged in from', 'unauthorized access', 'اختراق', 'تم اختراق', 'übernommen', 'gehackt', 'kompromittiert'),
            'financial_fraud'        => array('financial fraud', 'finanzbetrug', 'احتيال مالي', 'wire fraud', 'bank transfer', 'unauthorized payment', 'stolen money'),
            'identity_theft'         => array('identity theft', 'stolen identity', 'impersonat', 'سرقة هوية', 'identitätsdiebstahl'),
            'malware_ransomware'     => array('malware / ransomware', 'ransomware', 'malware', 'virus', 'trojan', 'برمجيات خبيثة', 'فدية'),
            'social_media_recovery'  => array('social media recovery', 'social-media-wiederherstellung', 'instagram hack', 'facebook hack', 'tiktok hack', 'recover my', 'استرداد حساب تواصل', 'استرداد حساب'),
            'data_breach'            => array('data breach', 'leaked data', 'database leak', 'تسريب بيانات', 'تسريب', 'datenleck'),
            'phishing_fraud'         => array('phishing / fraud', 'phishing / betrug', 'phish', 'scam email', 'fake email', 'spoof', 'تصيد / احتيال', 'تصيد', 'phishing', 'betrug'),
            'other'                  => array('other cyber incident'),
        );
        foreach ($map as $category => $needles) {
            foreach ($needles as $needle) {
                if ($needle !== '' && mb_strpos($normalized, $needle) !== false) {
                    return $category;
                }
            }
        }
        return '';
    }

    /**
     * @param string $text
     * @param string $normalized
     * @return array<string, string>
     */
    private static function detect_incident_date($text, $normalized) {
        $out = array();
        $year_now = (int) gmdate('Y');

        if (preg_match('/\b(today|heute)\b/u', $normalized) || $normalized === 'اليوم') {
            return self::date_parts((int) gmdate('Y'), (int) gmdate('n'), (int) gmdate('j'));
        }
        if (preg_match('/\b(yesterday|gestern)\b/u', $normalized) || mb_strpos($normalized, 'أمس') !== false || mb_strpos($normalized, 'امس') !== false) {
            $ts = time() - 86400;
            return self::date_parts((int) gmdate('Y', $ts), (int) gmdate('n', $ts), (int) gmdate('j', $ts));
        }

        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m)) {
            return self::date_parts((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/\b(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})\b/', $text, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $y = (int) $m[3];
            if ($a > 12) {
                return self::date_parts($y, $b, $a);
            }
            return self::date_parts($y, $a, $b);
        }

        $months = array(
            'january' => 1, 'jan' => 1, 'januar' => 1, 'يناير' => 1,
            'february' => 2, 'feb' => 2, 'februar' => 2, 'فبراير' => 2,
            'march' => 3, 'mar' => 3, 'märz' => 3, 'marz' => 3, 'مارس' => 3,
            'april' => 4, 'apr' => 4, 'أبريل' => 4, 'ابريل' => 4,
            'may' => 5, 'mai' => 5, 'مايو' => 5,
            'june' => 6, 'jun' => 6, 'juni' => 6, 'يونيو' => 6,
            'july' => 7, 'jul' => 7, 'juli' => 7, 'يوليو' => 7,
            'august' => 8, 'aug' => 8, 'أغسطس' => 8, 'اغسطس' => 8,
            'september' => 9, 'sep' => 9, 'sept' => 9, 'سبتمبر' => 9,
            'october' => 10, 'oct' => 10, 'oktober' => 10, 'أكتوبر' => 10, 'اكتوبر' => 10,
            'november' => 11, 'nov' => 11, 'نوفمبر' => 11,
            'december' => 12, 'dec' => 12, 'dezember' => 12, 'ديسمبر' => 12,
        );

        if (preg_match('/\b([a-zäöü]+|يناير|فبراير|مارس|أبريل|ابريل|مايو|يونيو|يوليو|أغسطس|اغسطس|سبتمبر|أكتوبر|اكتوبر|نوفمبر|ديسمبر)\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s*(\d{4}))?\b/u', $normalized, $m)) {
            $month = $months[$m[1]] ?? 0;
            $day = (int) $m[2];
            $year = !empty($m[3]) ? (int) $m[3] : $year_now;
            if ($month > 0) {
                return self::date_parts($year, $month, $day);
            }
        }
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+([a-zäöü]+|يناير|فبراير|مارس|أبريل|ابريل|مايو|يونيو|يوليو|أغسطس|اغسطس|سبتمبر|أكتوبر|اكتوبر|نوفمبر|ديسمبر)(?:,?\s*(\d{4}))?\b/u', $normalized, $m)) {
            $month = $months[$m[2]] ?? 0;
            $day = (int) $m[1];
            $year = !empty($m[3]) ? (int) $m[3] : $year_now;
            if ($month > 0) {
                return self::date_parts($year, $month, $day);
            }
        }

        return $out;
    }

    /**
     * @param int $year
     * @param int $month
     * @param int $day
     * @return array<string, string>
     */
    private static function date_parts($year, $month, $day) {
        if (!checkdate($month, $day, $year)) {
            return array();
        }
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        return array(
            'incident_date'    => $date,
            'incident_at_sql'  => $date . ' 00:00:00',
        );
    }

    /**
     * @param string $normalized
     * @param string $original
     * @return list<string>
     */
    private static function detect_platforms($normalized, $original) {
        unset($original);
        $found = array();
        foreach (self::$platform_aliases as $needle => $label) {
            $pattern = '/(^|[^a-z0-9])' . preg_quote($needle, '/') . '([^a-z0-9]|$)/u';
            if (preg_match($pattern, $normalized)) {
                $found[$label] = $label;
            }
        }
        return array_values($found);
    }

    /**
     * @param string       $current
     * @param list<string> $incoming
     * @param bool         $replace
     * @return string
     */
    public static function merge_platform_list($current, $incoming, $replace = false) {
        $incoming = array_values(array_filter(array_map('trim', (array) $incoming)));
        if ($replace) {
            return implode(', ', $incoming);
        }
        $existing = array();
        foreach (preg_split('/\s*,\s*/', (string) $current) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $existing[strtolower($part)] = $part;
            }
        }
        foreach ($incoming as $item) {
            $key = strtolower($item);
            if (!isset($existing[$key])) {
                $existing[$key] = $item;
            }
        }
        return implode(', ', array_values($existing));
    }

    /**
     * @param string $normalized
     * @return bool
     */
    private static function looks_like_correction($normalized) {
        return (bool) preg_match('/\b(actually|correction|correct that|not|instead of|i meant|change (it|that) to|eigentlich|nicht|ليس|تصحيح)\b/u', $normalized);
    }

    /**
     * @param string $normalized
     * @return string
     */
    private static function detect_urgency($normalized) {
        if (preg_match('/\b(critical|urgent now|happening now|حق الآن|kritisch)\b/u', $normalized)) {
            return 'critical';
        }
        if (preg_match('/\b(high urgency|very urgent|urgent|عاجل|dringend)\b/u', $normalized)) {
            return 'high';
        }
        if (preg_match('/\b(low urgency|not urgent|منخفض)\b/u', $normalized)) {
            return 'low';
        }
        return '';
    }

    /**
     * @param string $text
     * @return array{amount:string,currency:string}|null
     */
    private static function detect_financial_loss($text) {
        $normalized = self::normalize_match_text($text);
        if (preg_match('/\b(did not lose( any)?( money)?|no money (was )?lost|no financial loss|lost nothing|kein(e)? (geld|verlust)|keine finanzielle|لم (أخسر|اخسر)|بدون خسارة)\b/u', $normalized)) {
            return array('amount' => 'No', 'currency' => '');
        }
        if (preg_match('/(?:lost|stole|stolen|loss of|خسارت|verlust)\s*(?:about\s*)?(€|eur|usd|\$|£)?\s*([0-9]{1,7}(?:[.,][0-9]{1,2})?)/iu', $text, $m)) {
            $amount = str_replace(',', '.', $m[2]);
            $symbol = strtoupper(trim($m[1]));
            $currency = 'EUR';
            if ($symbol === '$' || $symbol === 'USD') {
                $currency = 'USD';
            } elseif ($symbol === '£') {
                $currency = 'GBP';
            }
            return array('amount' => $amount, 'currency' => $currency);
        }
        return null;
    }

    /**
     * Compact structured summary for the case page — never the raw chat message.
     *
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $existing
     * @return string
     */
    public static function structured_summary($fields, $existing = array()) {
        $fields = is_array($fields) ? $fields : array();
        $existing = is_array($existing) ? $existing : array();
        $category = sanitize_key((string) ($fields['category'] ?? $existing['category'] ?? ''));
        $label = '';
        if ($category !== '' && class_exists('PAXdesign_Cybercrime_Intake')) {
            $label = PAXdesign_Cybercrime_Intake::category_label($category);
        } elseif ($category !== '') {
            $label = str_replace('_', ' ', $category);
        }
        $date = sanitize_text_field((string) ($fields['incident_date'] ?? $existing['incident_date'] ?? ''));
        if ($date === '' && !empty($fields['incident_at'])) {
            $date = substr((string) $fields['incident_at'], 0, 10);
        }
        if ($date === '' && !empty($existing['incident_at'])) {
            $date = substr((string) $existing['incident_at'], 0, 10);
        }
        $platforms = sanitize_textarea_field((string) ($fields['platforms'] ?? $existing['platforms'] ?? ''));
        $loss = sanitize_text_field((string) ($fields['financial_loss'] ?? $existing['financial_loss'] ?? ''));

        $parts = array();
        if ($label !== '') {
            $parts[] = $label;
        }
        if ($date !== '') {
            $parts[] = 'Date: ' . $date;
        }
        if ($platforms !== '') {
            $parts[] = 'Platforms: ' . $platforms;
        }
        if ($loss !== '') {
            $loss_label = (strcasecmp($loss, 'No') === 0 || $loss === '0') ? 'No' : $loss;
            if (!empty($fields['financial_currency']) && $loss_label !== 'No') {
                $loss_label .= ' ' . strtoupper((string) $fields['financial_currency']);
            }
            $parts[] = 'Financial loss: ' . $loss_label;
        }

        return implode('. ', $parts);
    }

    /**
     * @param string $existing
     * @param string $summary
     * @param string $raw_message
     * @return bool
     */
    public static function should_replace_description($existing, $summary, $raw_message = '') {
        $existing = trim((string) $existing);
        $summary = trim((string) $summary);
        if ($summary === '') {
            return false;
        }
        if ($existing === '' || strcasecmp($existing, $summary) === 0) {
            return true;
        }
        $raw_message = trim((string) $raw_message);
        if ($raw_message !== '' && stripos($existing, $raw_message) !== false) {
            return true;
        }
        if (preg_match('/\b(Platforms:|Date:|Financial loss:)\b/i', $existing)) {
            return true;
        }
        return strlen($existing) <= 180;
    }

    /**
     * True when a stored description is a pasted chat transcript rather than structured case facts.
     *
     * @param string $text
     * @return bool
     */
    public static function is_chat_dump_description($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }
        if (preg_match('/\b(Date:|Platforms:|Financial loss:)\b/i', $text) && strlen($text) <= 280) {
            return false;
        }
        return strlen($text) > 220;
    }
}
