<?php
/**
 * Persistent AI operations for a Cybercrime Support case and conversation.
 *
 * If the assistant is checking, processing, uploading, or reviewing, that work
 * is stored on the same CCS case payload and the same chat session. Follow-up
 * messages such as "?" must load this state instead of starting a new chat.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_AI_Operations {

    const TYPE_DOCUMENT_CHECK = 'document_check';
    const STATUS_RUNNING      = 'running';
    const STATUS_COMPLETE     = 'complete';
    const STATUS_FAILED       = 'failed';
    const STALE_SECONDS       = 120;
    const MAX_OPERATIONS      = 12;

    /**
     * Decide how this authenticated CCS turn should be handled.
     *
     * @param string                         $session_id
     * @param int                            $user_id
     * @param string                         $user_message
     * @param string                         $language de|en|ar
     * @param array<string, mixed>|null      $known_report Case already loaded by ingest this turn
     * @return array<string, mixed>
     */
    public static function decide_turn($session_id, $user_id, $user_message, $language = '', $known_report = null) {
        $session_id = sanitize_text_field((string) $session_id);
        $user_id = absint($user_id);
        $user_message = trim((string) $user_message);
        $language = self::normalize_language($language);

        $empty = array(
            'action'    => 'continue',
            'skip_llm'  => false,
            'operation' => null,
            'reply'     => '',
            'report'    => null,
        );

        if ($session_id === '' || $user_id <= 0 || !class_exists('PAXdesign_Cybercrime_Tickets')) {
            return $empty;
        }

        $row = null;
        if (is_array($known_report) && !empty($known_report['reference_id'])) {
            $loaded = PAXdesign_Cybercrime_Tickets::get_report_row((string) $known_report['reference_id']);
            $row = is_array($loaded) && !empty($loaded['reference_id']) ? $loaded : $known_report;
        }
        if (!is_array($row) || empty($row['reference_id'])) {
            $row = self::load_case_row($session_id, $user_id);
        }
        if (!is_array($row) || empty($row['reference_id'])) {
            return $empty;
        }

        if (
            class_exists('PAXdesign_Cybercrime_AI_Case')
            && PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request($user_message)
        ) {
            $previous = '';
            if (is_array($known_report)) {
                $previous = sanitize_text_field((string) ($known_report['previous_reference'] ?? $known_report['replaces_reference'] ?? ''));
            }
            if ($previous === '') {
                $payload_prev = is_array($row['payload'] ?? null)
                    ? $row['payload']
                    : json_decode((string) ($row['payload'] ?? ''), true);
                if (is_array($payload_prev)) {
                    $previous = sanitize_text_field((string) ($payload_prev['replaces_reference'] ?? ''));
                }
            }
            if ($previous === '' && !PAXdesign_Cybercrime_AI_Case::is_verified_new_draft($row, '')) {
                $previous = sanitize_text_field((string) ($row['reference_id'] ?? ''));
            }
            if (!PAXdesign_Cybercrime_AI_Case::is_verified_new_draft($row, $previous)) {
                $opened = PAXdesign_Cybercrime_AI_Case::open_new_case_for_user($user_id, $session_id, $previous);
                if (is_array($opened) && !empty($opened['reference_id'])) {
                    $fresh = PAXdesign_Cybercrime_Tickets::get_report_row((string) $opened['reference_id']);
                    if (is_array($fresh) && !empty($fresh['reference_id'])) {
                        $row = $fresh;
                    } else {
                        $row = $opened;
                    }
                    if ($previous === '') {
                        $previous = sanitize_text_field((string) ($opened['previous_reference'] ?? ''));
                    }
                }
            }
            $new_reference = sanitize_text_field((string) ($row['reference_id'] ?? ''));
            if ($new_reference === '' || ($previous !== '' && strcasecmp($new_reference, $previous) === 0) || !PAXdesign_Cybercrime_AI_Case::is_verified_new_draft($row, $previous)) {
                return array(
                    'action'    => 'continue_case',
                    'skip_llm'  => true,
                    'operation' => null,
                    'reply'     => __('A new Cybercrime Support case was not created. The previous reference cannot be reused. Please send “new report” again.', 'paxdesign-booking'),
                    'report'    => $row,
                );
            }
            self::remember_ccs_session($session_id, $new_reference);
            $snapshot = class_exists('PAXdesign_Cybercrime_AI_Workflow')
                ? PAXdesign_Cybercrime_AI_Workflow::snapshot($row, $language)
                : array();
            $state = class_exists('PAXdesign_Cybercrime_AI_Workflow')
                ? PAXdesign_Cybercrime_AI_Workflow::state_from_row($row)
                : array();
            $reply = class_exists('PAXdesign_Cybercrime_AI_Workflow')
                ? PAXdesign_Cybercrime_AI_Workflow::new_case_opened_copy($snapshot, $state, $language)
                : '';
            return array(
                'action'    => 'continue_case',
                'skip_llm'  => true,
                'operation' => null,
                'reply'     => $reply,
                'report'    => $row,
            );
        }

        self::remember_ccs_session($session_id, (string) $row['reference_id']);

        $running = self::running_operation($row);
        if (is_array($running) && self::is_stale($running)) {
            $row = self::fail_operation(
                $row,
                (string) ($running['id'] ?? ''),
                __('The previous file check timed out. I will run it again on this same case.', 'paxdesign-booking')
            );
            $running = null;
        }

        if (is_array($running)) {
            return array(
                'action'    => 'status',
                'skip_llm'  => true,
                'operation' => self::public_operation($running),
                'reply'     => self::still_running_copy($language, $running),
                'report'    => $row,
            );
        }

        $needs_check = self::case_needs_document_check($row);
        $probe = self::is_status_probe($user_message);
        $asks_check = self::is_check_request($user_message);
        $same_case = self::is_same_case_continuation($user_message);
        $status = sanitize_key((string) ($row['status'] ?? ''));
        $is_draft = ($status === '' || $status === 'draft');

        if ($is_draft && class_exists('PAXdesign_Cybercrime_AI_Workflow')) {
            $workflow = PAXdesign_Cybercrime_AI_Workflow::decide_turn($row, $user_message, $language, $user_id);
            if (is_array($workflow) && !empty($workflow['action']) && $workflow['action'] !== 'continue') {
                return $workflow;
            }
        }

        if ($needs_check) {
            return array(
                'action'    => 'start_document_check',
                'skip_llm'  => $probe || $same_case || $asks_check || self::is_short_followup($user_message) || self::last_assistant_claimed_processing($session_id),
                'operation' => null,
                'reply'     => '',
                'report'    => $row,
            );
        }

        if (!$is_draft && class_exists('PAXdesign_Cybercrime_AI_Workflow')) {
            $workflow = PAXdesign_Cybercrime_AI_Workflow::decide_turn($row, $user_message, $language, $user_id);
            if (is_array($workflow) && !empty($workflow['action']) && $workflow['action'] !== 'continue') {
                return $workflow;
            }
        }

        if ($same_case) {
            $latest = self::latest_operation($row);
            $reply = self::continuation_copy($language, $row, $latest);
            return array(
                'action'    => 'continue_case',
                'skip_llm'  => true,
                'operation' => is_array($latest) ? self::public_operation($latest) : null,
                'reply'     => $reply,
                'report'    => $row,
            );
        }

        return array(
            'action'    => 'continue',
            'skip_llm'  => false,
            'operation' => self::latest_operation($row) ? self::public_operation(self::latest_operation($row)) : null,
            'reply'     => '',
            'report'    => $row,
        );
    }

    /**
     * Persist a running document-check operation and a visible chat message.
     *
     * @param string $session_id
     * @param int    $user_id
     * @param string $language
     * @return array<string, mixed>|WP_Error
     */
    public static function start_document_check($session_id, $user_id, $language = '') {
        $session_id = sanitize_text_field((string) $session_id);
        $user_id = absint($user_id);
        $language = self::normalize_language($language);
        $row = self::load_case_row($session_id, $user_id);
        if (!is_array($row) || empty($row['reference_id'])) {
            return new WP_Error('case_unavailable', __('Could not open your Cybercrime Support case.', 'paxdesign-booking'));
        }

        $existing = self::running_operation($row);
        if (is_array($existing) && !self::is_stale($existing)) {
            return array(
                'operation' => self::public_operation($existing),
                'message'   => null,
                'report'    => $row,
                'reused'    => true,
            );
        }

        $op_id = self::new_operation_id();
        $label = self::checking_label($language);
        $now = self::now_mysql();
        $operation = array(
            'id'               => $op_id,
            'type'             => self::TYPE_DOCUMENT_CHECK,
            'status'           => self::STATUS_RUNNING,
            'label'            => $label,
            'started_at'       => $now,
            'finished_at'      => '',
            'session_id'       => $session_id,
            'reference_id'     => (string) $row['reference_id'],
            'chat_message_seq' => 0,
            'result_summary'   => '',
        );

        $message = null;
        if (class_exists('PAXdesign_Chat_Live')) {
            $message = PAXdesign_Chat_Live::get_instance()->append_message(
                $session_id,
                'assistant',
                $label,
                array(
                    'ccs_operation_id'     => $op_id,
                    'ccs_operation_status' => self::STATUS_RUNNING,
                    'ccs_operation_type'   => self::TYPE_DOCUMENT_CHECK,
                    'ccs_operation_label'  => $label,
                    'attachment_type'      => 'ccs_operation',
                )
            );
            if (is_array($message) && !empty($message['id'])) {
                $operation['chat_message_seq'] = (int) $message['id'];
            }
        }

        $row = self::save_operation($row, $operation, 'document_check');
        self::set_lock((string) $row['reference_id'], $op_id);

        return array(
            'operation' => self::public_operation($operation),
            'message'   => is_array($message) ? $message : null,
            'report'    => $row,
            'reused'    => false,
        );
    }

    /**
     * Run the existing document checks on this case's uploads and finish the operation.
     *
     * @param string $session_id
     * @param int    $user_id
     * @param string $operation_id
     * @param string $language
     * @return array<string, mixed>|WP_Error
     */
    public static function complete_document_check($session_id, $user_id, $operation_id = '', $language = '') {
        $session_id = sanitize_text_field((string) $session_id);
        $user_id = absint($user_id);
        $operation_id = sanitize_text_field((string) $operation_id);
        $language = self::normalize_language($language);
        $row = self::load_case_row($session_id, $user_id);
        if (!is_array($row) || empty($row['reference_id'])) {
            return new WP_Error('case_unavailable', __('Could not open your Cybercrime Support case.', 'paxdesign-booking'));
        }

        $operation = $operation_id !== '' ? self::find_operation($row, $operation_id) : self::running_operation($row);
        if (!is_array($operation)) {
            $operation = self::latest_operation($row);
        }
        if (!is_array($operation)) {
            return new WP_Error('operation_missing', __('No file check is in progress for this case.', 'paxdesign-booking'));
        }

        $reference = (string) $row['reference_id'];
        $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($attachments)) {
            $attachments = array();
        }

        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }

        $context = array(
            'reporter_name'   => (string) ($row['reporter_name'] ?? ''),
            'email'           => (string) ($row['reporter_email'] ?? ''),
            'category'        => (string) ($row['category'] ?? ''),
            'existing_hashes' => array(),
        );

        $summary = array();
        if (class_exists('PAXdesign_Cybercrime_Document_Checks')) {
            $summary = PAXdesign_Cybercrime_Document_Checks::evaluate_uploads($attachments, $context);
        }
        if (!is_array($summary)) {
            $summary = array();
        }

        $payload['document_checks'] = $summary;
        $payload['pending_document_check'] = false;
        $guided = is_array($payload['guided_case'] ?? null) ? $payload['guided_case'] : array();
        $guided['next_action'] = (string) ($summary['next_action'] ?? $guided['next_action'] ?? '');
        $payload['guided_case'] = $guided;

        $needs_human = !empty($summary['needs_human_review']) ? 1 : 0;
        $result_text = self::result_copy($language, $summary, $reference);
        $now = self::now_mysql();
        $operation['status'] = self::STATUS_COMPLETE;
        $operation['finished_at'] = $now;
        $operation['result_summary'] = wp_html_excerpt($result_text, 280, '…');
        $operation['result'] = class_exists('PAXdesign_Cybercrime_Document_Checks')
            ? PAXdesign_Cybercrime_Document_Checks::customer_view($summary)
            : $summary;

        $payload = self::write_operation_into_payload($payload, $operation, 'awaiting_customer');

        global $wpdb;
        $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array(
                'payload'            => wp_json_encode($payload),
                'needs_human_review' => $needs_human,
                'updated_at'         => $now,
            ),
            array('reference_id' => $reference),
            array('%s', '%d', '%s'),
            array('%s')
        );

        if (!empty($operation['chat_message_seq']) && class_exists('PAXdesign_Message_Store')) {
            PAXdesign_Message_Store::update_message_meta(
                $session_id,
                (int) $operation['chat_message_seq'],
                array(
                    'ccs_operation_status' => self::STATUS_COMPLETE,
                    'ccs_operation_label'  => $operation['label'],
                )
            );
        }

        $result_message = null;
        if (class_exists('PAXdesign_Chat_Live')) {
            $result_message = PAXdesign_Chat_Live::get_instance()->append_message(
                $session_id,
                'assistant',
                $result_text,
                array(
                    'ccs_operation_id'     => (string) $operation['id'],
                    'ccs_operation_status' => self::STATUS_COMPLETE,
                    'ccs_operation_type'   => self::TYPE_DOCUMENT_CHECK,
                    'ccs_operation_label'  => $operation['label'],
                    'attachment_type'      => 'ccs_operation',
                )
            );
        }

        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            PAXdesign_Cybercrime_Tickets::add_message(
                $reference,
                'system',
                sprintf(
                    /* translators: %s: CCS reference */
                    __('Preliminary file check completed on %s from AI chat.', 'paxdesign-booking'),
                    $reference
                ),
                'portal',
                $user_id,
                array(
                    'event'               => 'ai_document_check',
                    'visible_to_customer' => true,
                    'operation_id'        => (string) $operation['id'],
                )
            );
        }

        self::clear_lock($reference);
        $fresh = PAXdesign_Cybercrime_Tickets::get_report_row($reference);

        return array(
            'operation'      => self::public_operation($operation),
            'message'        => is_array($result_message) ? $result_message : null,
            'report'         => is_array($fresh) ? $fresh : $row,
            'result_text'    => $result_text,
            'document_checks'=> $summary,
        );
    }

    /**
     * Persist an assistant reply that must stay on this same CCS conversation.
     *
     * @param string               $session_id
     * @param string               $content
     * @param array<string, mixed> $operation
     * @return array<string, mixed>|null
     */
    public static function persist_assistant_reply($session_id, $content, $operation = array()) {
        $session_id = sanitize_text_field((string) $session_id);
        $content = trim((string) $content);
        if ($session_id === '' || $content === '' || !class_exists('PAXdesign_Chat_Live')) {
            return null;
        }
        $extra = array();
        if (is_array($operation) && !empty($operation['id'])) {
            $extra['ccs_operation_id'] = sanitize_text_field((string) $operation['id']);
            $extra['ccs_operation_status'] = sanitize_key((string) ($operation['status'] ?? self::STATUS_RUNNING));
            $extra['ccs_operation_type'] = sanitize_key((string) ($operation['type'] ?? ''));
            $extra['ccs_operation_label'] = sanitize_text_field((string) ($operation['label'] ?? ''));
            $extra['attachment_type'] = 'ccs_operation';
        }
        $saved = PAXdesign_Chat_Live::get_instance()->append_message($session_id, 'assistant', $content, $extra);
        return is_array($saved) ? $saved : null;
    }

    /**
     * Compact operation payload for SSE / REST / case sync.
     *
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    public static function public_operation($operation) {
        if (!is_array($operation) || empty($operation['id'])) {
            return array();
        }
        return array(
            'id'           => (string) ($operation['id'] ?? ''),
            'type'         => sanitize_key((string) ($operation['type'] ?? '')),
            'status'       => sanitize_key((string) ($operation['status'] ?? '')),
            'label'        => (string) ($operation['label'] ?? ''),
            'reference_id' => (string) ($operation['reference_id'] ?? ''),
            'started_at'   => (string) ($operation['started_at'] ?? ''),
            'finished_at'  => (string) ($operation['finished_at'] ?? ''),
            'summary'      => (string) ($operation['result_summary'] ?? ''),
        );
    }

    /**
     * @param string $text
     * @return bool
     */
    public static function is_status_probe($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }
        $normalized = self::normalize_match_text($text);
        if (preg_match('/^[?؟!.\s]+$/u', $text)) {
            return true;
        }
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            return false;
        }
        return (bool) preg_match(
            '/^(status|update|any (news|update)|still (there|checking|running|processing)|hello|hi|ping|and|so|well|ok|okay|läuft noch|fertig|und|noch da|was ist los|was ist passiert|was bleibt|wie geht(?:e)?s weiter|any progress|checking|done|what happened|what(?:\'s| is) (?:next|left|remaining)|keep going|go (?:on|ahead)|proceed|continue|weiter|fortsetzen|mach weiter|جاهز|شو صار|وينك|ماذا حدث|ماذا حصل|ماذا بقي|ما الذي حدث|ماذا تبقى|تابع|استمر|كمل|أكمل|نعم|ايوا|أيوة|أيوا)$/u',
            $normalized
        );
    }

    /**
     * True when the message belongs to the current CCS case instead of a new chat.
     *
     * Language-preference names, status probes, and continue/yes follow-ups
     * must not reset the conversation.
     *
     * @param string $text
     * @return bool
     */
    public static function is_same_case_continuation($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }
        if (
            class_exists('PAXdesign_Cybercrime_AI_Case')
            && PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request($text)
        ) {
            return false;
        }
        if (self::is_status_probe($text)) {
            return true;
        }
        if (class_exists('PAXdesign_Language_Routing')) {
            $preference = PAXdesign_Language_Routing::detect_language_preference($text);
            if ($preference !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $text
     * @return bool
     */
    public static function is_check_request($text) {
        $normalized = self::normalize_match_text($text);
        if ($normalized === '') {
            return false;
        }
        return (bool) preg_match(
            '/\b(check|checking|verify|verifying|prüf(?:e|en)?|überprüf\w*|تحقق)\b.{0,48}\b(file|files|document|documents|upload|uploads|evidence|datei|dateien|dokument|الملف|الملفات|المستند)\b/u',
            $normalized
        );
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @param array<string, mixed>             $document_checks
     * @return bool
     */
    public static function attachments_need_check($attachments, $document_checks) {
        $attachments = is_array($attachments) ? $attachments : array();
        if (empty($attachments)) {
            return false;
        }
        $document_checks = is_array($document_checks) ? $document_checks : array();
        $checked = array();
        foreach ((array) ($document_checks['files'] ?? array()) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $name = strtolower((string) ($file['filename'] ?? $file['name'] ?? ''));
            $hash = strtolower((string) ($file['sha256'] ?? ''));
            if ($name !== '') {
                $checked['name:' . $name] = true;
            }
            if ($hash !== '') {
                $checked['hash:' . $hash] = true;
            }
        }
        if (empty($checked)) {
            return true;
        }
        foreach ($attachments as $file) {
            if (!is_array($file)) {
                continue;
            }
            $name = strtolower((string) ($file['original_name'] ?? $file['name'] ?? ''));
            $hash = strtolower((string) ($file['sha256'] ?? ''));
            $known = ($name !== '' && !empty($checked['name:' . $name]))
                || ($hash !== '' && !empty($checked['hash:' . $hash]));
            if (!$known) {
                return true;
            }
        }
        return false;
    }

    /**
     * Lines injected into the CCS system prompt so the model cannot restart.
     *
     * @param array<string, mixed> $row
     * @return string
     */
    public static function prompt_state_block($row) {
        if (!is_array($row)) {
            return '';
        }
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $ops = is_array($payload['ai_operations'] ?? null) ? $payload['ai_operations'] : array();
        $workflow = is_array($payload['ai_workflow'] ?? null) ? $payload['ai_workflow'] : array();
        $lines = array(
            '## Persistent conversation and operation state (authoritative)',
            '- This is a CONTINUATION of the same authenticated Cybercrime Support conversation and the same CCS case. Never greet as a new chat (no “Guten Tag”, “Wie kann ich Ihnen helfen?”, “Hello, how can I help?”, “مرحباً! كيف يمكنني مساعدتك اليوم؟”).',
            '- The website form is the source of truth. Chat completes the SAME 4 steps: 1 Identity, 2 Incident, 3 Evidence, 4 Review / Submission.',
            '- Fill website fields from natural language. Ask only for genuinely missing required fields. Never restart unless the customer explicitly requests a new case.',
            '- At Review, summarize what will be submitted and what is still missing. Submit this same CCS case when all requirements are satisfied and the customer confirms.',
            '- A language-preference message (arabic / English / Deutsch / العربية) only switches reply language. Keep this same case. Do not greet. Do not restart intake. Recap the last result in the requested language.',
            '- Short follow-ups (?, نعم, تابع, ماذا حدث؟, ماذا بقي؟) continue this same case. They never start a new conversation.',
            '- Start a new case or conversation only when the customer explicitly asks (Start a new case / New report / Start from scratch / أريد فتح بلاغ جديد / ابدأ من الصفر). Then the live case context is the NEW reference only.',
            '- Never restart the questionnaire. Never ask for facts already saved on this case.',
            '- Never claim you are checking, processing, uploading, or reviewing unless a tracked operation below is status=running.',
            '- If a tracked operation is running, tell the customer it is still running and that results will appear in this same conversation.',
            '- If verification already finished, use the saved results. Do not invent a second check.',
            '- Current AI workflow step: ' . sanitize_key((string) ($workflow['step'] ?? 'intake')),
        );
        if (empty($ops)) {
            $lines[] = '- Tracked operations: none.';
        } else {
            $lines[] = '- Tracked operations (newest last):';
            foreach (array_slice($ops, -6) as $op) {
                if (!is_array($op)) {
                    continue;
                }
                $lines[] = '    • ' . (string) ($op['id'] ?? '')
                    . ' type=' . (string) ($op['type'] ?? '')
                    . ' status=' . (string) ($op['status'] ?? '')
                    . ' label=' . (string) ($op['label'] ?? '')
                    . (!empty($op['result_summary']) ? ' result=' . (string) $op['result_summary'] : '');
            }
        }
        return implode("\n", $lines);
    }

    /**
     * @param string $session_id
     * @param int    $user_id
     * @return array<string, mixed>|null
     */
    private static function load_case_row($session_id, $user_id) {
        $reference = '';
        if (class_exists('PAXdesign_Cybercrime_Tickets')) {
            $reference = PAXdesign_Cybercrime_Tickets::get_reference_for_session($session_id);
        }
        if ($reference === '' && class_exists('PAXdesign_Chat')) {
            $stored = get_transient('pax_chat_page_ref_' . md5($session_id));
            if (is_string($stored) && $stored !== '') {
                $reference = sanitize_text_field($stored);
            }
        }
        if ($reference === '' && class_exists('PAXdesign_Cybercrime_AI_Case')) {
            $ensured = PAXdesign_Cybercrime_AI_Case::ensure_case_for_user($user_id, $session_id, '');
            return is_array($ensured) ? $ensured : null;
        }
        if ($reference === '') {
            return null;
        }
        $row = PAXdesign_Cybercrime_Tickets::get_report_row($reference);
        if (!$row || !PAXdesign_Cybercrime_Tickets::user_can_view_report($row, $user_id)) {
            return null;
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return bool
     */
    private static function case_needs_document_check($row) {
        $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        if (!empty($payload['pending_document_check'])) {
            return true;
        }
        return self::attachments_need_check(
            is_array($attachments) ? $attachments : array(),
            is_array($payload['document_checks'] ?? null) ? $payload['document_checks'] : array()
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return bool
     */
    private static function has_unchecked_chat_files($row) {
        $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($attachments) || empty($attachments)) {
            return false;
        }
        foreach ($attachments as $file) {
            if (is_array($file) && sanitize_key((string) ($file['source'] ?? '')) === 'ai_chat') {
                return self::case_needs_document_check($row);
            }
        }
        return self::case_needs_document_check($row);
    }

    /**
     * @param string $session_id
     * @return bool
     */
    private static function last_assistant_claimed_processing($session_id) {
        if (!class_exists('PAXdesign_Message_Store')) {
            return false;
        }
        $rows = PAXdesign_Message_Store::all_messages($session_id, 'customer');
        if (!is_array($rows) || empty($rows)) {
            return false;
        }
        for ($i = count($rows) - 1; $i >= 0; $i--) {
            $role = (string) ($rows[$i]['role'] ?? '');
            if ($role === 'admin') {
                $role = 'assistant';
            }
            if ($role !== 'assistant') {
                continue;
            }
            $content = self::normalize_match_text((string) ($rows[$i]['content'] ?? ''));
            return (bool) preg_match(
                '/\b(check(ing)?|verif(y|ying)|process(ing)?|upload(ing)?|review(ing)?|please wait|kurz warten|prüfen|überprüf|please wait while i)\b/u',
                $content
            );
        }
        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function running_operation($row) {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload) || empty($payload['ai_operations']) || !is_array($payload['ai_operations'])) {
            return null;
        }
        for ($i = count($payload['ai_operations']) - 1; $i >= 0; $i--) {
            $op = $payload['ai_operations'][$i];
            if (is_array($op) && sanitize_key((string) ($op['status'] ?? '')) === self::STATUS_RUNNING) {
                return $op;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function latest_operation($row) {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload) || empty($payload['ai_operations']) || !is_array($payload['ai_operations'])) {
            return null;
        }
        $last = end($payload['ai_operations']);
        return is_array($last) ? $last : null;
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $operation_id
     * @return array<string, mixed>|null
     */
    private static function find_operation($row, $operation_id) {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload) || empty($payload['ai_operations']) || !is_array($payload['ai_operations'])) {
            return null;
        }
        foreach ($payload['ai_operations'] as $op) {
            if (is_array($op) && (string) ($op['id'] ?? '') === $operation_id) {
                return $op;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $operation
     * @return bool
     */
    private static function is_stale($operation) {
        $started = strtotime((string) ($operation['started_at'] ?? '') . ' UTC');
        if ($started === false) {
            return false;
        }
        return (time() - $started) > self::STALE_SECONDS;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $operation
     * @param string               $step
     * @return array<string, mixed>
     */
    private static function save_operation($row, $operation, $step) {
        $reference = (string) ($row['reference_id'] ?? '');
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $payload = self::write_operation_into_payload($payload, $operation, $step);
        $now = self::now_mysql();
        global $wpdb;
        $wpdb->update(
            PAXdesign_Cybercrime_Intake::table_name(),
            array(
                'payload'    => wp_json_encode($payload),
                'updated_at' => $now,
            ),
            array('reference_id' => $reference),
            array('%s', '%s'),
            array('%s')
        );
        $row['payload'] = wp_json_encode($payload);
        $row['updated_at'] = $now;
        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $operation
     * @param string               $step
     * @return array<string, mixed>
     */
    private static function write_operation_into_payload($payload, $operation, $step) {
        $ops = is_array($payload['ai_operations'] ?? null) ? $payload['ai_operations'] : array();
        $replaced = false;
        foreach ($ops as $i => $existing) {
            if (is_array($existing) && (string) ($existing['id'] ?? '') === (string) ($operation['id'] ?? '')) {
                $ops[$i] = $operation;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $ops[] = $operation;
        }
        if (count($ops) > self::MAX_OPERATIONS) {
            $ops = array_slice($ops, -self::MAX_OPERATIONS);
        }
        $payload['ai_operations'] = array_values($ops);
        $workflow = is_array($payload['ai_workflow'] ?? null) ? $payload['ai_workflow'] : array();
        $workflow['step'] = sanitize_key((string) $step);
        $workflow['last_operation_id'] = (string) ($operation['id'] ?? '');
        $workflow['updated_at'] = self::now_mysql();
        $payload['ai_workflow'] = $workflow;
        return $payload;
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $operation_id
     * @param string               $reason
     * @return array<string, mixed>
     */
    private static function fail_operation($row, $operation_id, $reason) {
        $operation = self::find_operation($row, $operation_id);
        if (!is_array($operation)) {
            return $row;
        }
        $operation['status'] = self::STATUS_FAILED;
        $operation['finished_at'] = self::now_mysql();
        $operation['result_summary'] = sanitize_text_field($reason);
        self::clear_lock((string) ($row['reference_id'] ?? ''));
        return self::save_operation($row, $operation, 'awaiting_customer');
    }

    /**
     * @param string               $language
     * @param array<string, mixed> $operation
     * @return string
     */
    private static function still_running_copy($language, $operation) {
        $type = sanitize_key((string) ($operation['type'] ?? ''));
        if ($language === 'de') {
            if ($type === self::TYPE_DOCUMENT_CHECK) {
                return 'Ihre Dateien werden noch geprüft. Ich stelle die Prüfergebnisse bereit, sobald die Kontrolle abgeschlossen ist.';
            }
            return 'Die Verarbeitung läuft noch in diesemselben Fall. Ich informiere Sie hier, sobald sie abgeschlossen ist.';
        }
        if ($language === 'ar') {
            if ($type === self::TYPE_DOCUMENT_CHECK) {
                return 'لا تزال ملفاتك قيد الفحص. سأزوّدك بنتائج التحقق فور اكتمال الفحص.';
            }
            return 'لا تزال العملية قيد التنفيذ على نفس الحالة. سأظهر النتيجة هنا فور انتهائها.';
        }
        if ($type === self::TYPE_DOCUMENT_CHECK) {
            return 'Your files are still being checked. I’ll provide the verification results as soon as the check is complete.';
        }
        return 'That operation is still running on this same case. I’ll share the result here as soon as it finishes.';
    }

    /**
     * @param string                    $language
     * @param array<string, mixed>      $row
     * @param array<string, mixed>|null $operation
     * @return string
     */
    private static function continuation_copy($language, $row, $operation) {
        $reference = (string) ($row['reference_id'] ?? '');
        $status = sanitize_key((string) ($row['status'] ?? ''));
        if (is_array($operation) && sanitize_key((string) ($operation['status'] ?? '')) === self::STATUS_COMPLETE) {
            $summary = trim((string) ($operation['result_summary'] ?? ''));
            if ($language === 'de') {
                return 'Wir sind weiterhin bei Fall ' . $reference . '. Die Dateiprüfung ist abgeschlossen.'
                    . ($summary !== '' ? ' ' . $summary : '');
            }
            if ($language === 'ar') {
                return 'ما زلنا على نفس الحالة ' . $reference . '. اكتمل فحص الملفات.'
                    . ($summary !== '' ? ' ' . $summary : '');
            }
            return 'This is still case ' . $reference . '. The file check is already complete.'
                . ($summary !== '' ? ' ' . $summary : '');
        }
        if ($language === 'de') {
            return 'Wir sind weiterhin bei Ihrem Cybercrime-Support-Fall ' . $reference
                . ' (Status: ' . $status . '). Wie kann ich bei diesemselben Fall weiterhelfen?';
        }
        if ($language === 'ar') {
            return 'ما زلنا على حالة الدعم الخاصة بالجرائم الإلكترونية ' . $reference
                . ' (الحالة: ' . $status . '). كيف يمكنني المتابعة على نفس الحالة؟';
        }
        return 'This is still your Cybercrime Support case ' . $reference
            . ' (status: ' . $status . '). How can I continue on this same case?';
    }

    /**
     * @param string               $language
     * @param array<string, mixed> $summary
     * @param string               $reference
     * @return string
     */
    private static function result_copy($language, $summary, $reference) {
        $summary = is_array($summary) ? $summary : array();
        $files = is_array($summary['files'] ?? null) ? $summary['files'] : array();
        $corrections = array_values((array) ($summary['customer_corrections'] ?? array()));
        $next = trim((string) ($summary['next_action'] ?? ''));
        $disclaimer = trim((string) ($summary['disclaimer'] ?? ''));
        $lines = array();

        if ($language === 'de') {
            $lines[] = 'Die Prüfung der hochgeladenen Dateien für Fall ' . $reference . ' ist abgeschlossen.';
        } elseif ($language === 'ar') {
            $lines[] = 'اكتمل فحص الملفات المرفوعة للحالة ' . $reference . '.';
        } else {
            $lines[] = 'The uploaded-file check for case ' . $reference . ' is complete.';
        }

        foreach (array_slice($files, 0, 12) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $name = (string) ($file['filename'] ?? $file['name'] ?? 'file');
            $status = (string) ($file['customer_status'] ?? $file['status'] ?? '');
            $lines[] = '• ' . $name . ' — ' . $status;
        }
        foreach (array_slice($corrections, 0, 8) as $fix) {
            $fix = trim((string) $fix);
            if ($fix !== '') {
                $lines[] = $fix;
            }
        }
        if ($next !== '') {
            $lines[] = $next;
        }
        if ($disclaimer !== '') {
            $lines[] = $disclaimer;
        }
        return implode("\n", $lines);
    }

    /**
     * @param string $language
     * @return string
     */
    private static function checking_label($language) {
        if ($language === 'de') {
            return 'Hochgeladene Dateien werden geprüft…';
        }
        if ($language === 'ar') {
            return 'جارٍ فحص الملفات المرفوعة…';
        }
        return 'Checking uploaded files…';
    }

    /**
     * @param string $text
     * @return bool
     */
    private static function is_short_followup($text) {
        $text = trim((string) $text);
        return $text === '' || mb_strlen($text) <= 48;
    }

    /**
     * @param string $session_id
     * @param string $reference
     */
    private static function remember_ccs_session($session_id, $reference) {
        if (class_exists('PAXdesign_Chat')) {
            PAXdesign_Chat::get_instance()->set_session_page_context($session_id, 'cybercrime-support', $reference, '');
            return;
        }
        $key = md5($session_id);
        set_transient('pax_chat_page_ctx_' . $key, 'cybercrime-support', DAY_IN_SECONDS);
        if ($reference !== '') {
            set_transient('pax_chat_page_ref_' . $key, $reference, DAY_IN_SECONDS);
        }
    }

    /**
     * @param string $reference
     * @param string $op_id
     */
    private static function set_lock($reference, $op_id) {
        if ($reference === '') {
            return;
        }
        set_transient('pax_ccs_op_lock_' . md5($reference), sanitize_text_field($op_id), self::STALE_SECONDS);
    }

    /**
     * @param string $reference
     */
    private static function clear_lock($reference) {
        if ($reference === '') {
            return;
        }
        delete_transient('pax_ccs_op_lock_' . md5($reference));
    }

    /**
     * @return string
     */
    private static function new_operation_id() {
        try {
            $suffix = strtoupper(bin2hex(random_bytes(4)));
        } catch (Exception $e) {
            $suffix = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));
        }
        return 'op-' . gmdate('YmdHis') . '-' . $suffix;
    }

    /**
     * @return string
     */
    private static function now_mysql() {
        if (function_exists('current_time')) {
            return current_time('mysql', true);
        }
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param string $language
     * @return string
     */
    private static function normalize_language($language) {
        $language = sanitize_key((string) $language);
        return in_array($language, array('de', 'en', 'ar'), true) ? $language : 'en';
    }

    /**
     * @param string $text
     * @return string
     */
    private static function normalize_match_text($text) {
        $text = strtolower(trim((string) $text));
        $text = str_replace(array('’', '‘', '`'), "'", $text);
        $text = preg_replace('/[?؟!.]+$/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return is_string($text) ? trim($text) : '';
    }
}
