<?php
/**
 * Cybercrime Support 4-step workflow for the AI assistant.
 *
 * Source of truth: the website intake form (Identity → Incident → Evidence → Review).
 * Chat fills the same CCS case, validates with the same rules, and submits it.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_AI_Workflow {

    const STEP_IDENTITY = 1;
    const STEP_INCIDENT = 2;
    const STEP_EVIDENCE = 3;
    const STEP_REVIEW   = 4;

    /**
     * @return array<int, array<string, string>>
     */
    public static function steps() {
        return array(
            self::STEP_IDENTITY => array(
                'key'   => 'identity',
                'en'    => 'Identity',
                'de'    => 'Identität',
                'ar'    => 'الهوية',
            ),
            self::STEP_INCIDENT => array(
                'key'   => 'incident',
                'en'    => 'Incident',
                'de'    => 'Vorfall',
                'ar'    => 'الحادثة',
            ),
            self::STEP_EVIDENCE => array(
                'key'   => 'evidence',
                'en'    => 'Evidence',
                'de'    => 'Beweise',
                'ar'    => 'الأدلة',
            ),
            self::STEP_REVIEW => array(
                'key'   => 'review',
                'en'    => 'Review / Submission',
                'de'    => 'Prüfung / Übermittlung',
                'ar'    => 'المراجعة / الإرسال',
            ),
        );
    }

    /**
     * Normalize the live CCS row into the website form fields.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function state_from_row($row) {
        $row = is_array($row) ? $row : array();
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : array();
        }
        $attachments = json_decode((string) ($row['attachments'] ?? ''), true);
        if (!is_array($attachments)) {
            $attachments = is_array($row['attachments'] ?? null) ? $row['attachments'] : array();
        }
        $declarations = is_array($payload['declarations'] ?? null) ? $payload['declarations'] : array();
        $has_id = false;
        $has_evidence = false;
        $evidence_names = array();
        $id_names = array();
        foreach ($attachments as $file) {
            if (!is_array($file)) {
                continue;
            }
            $field = sanitize_key((string) ($file['field'] ?? ''));
            $name = (string) ($file['original_name'] ?? $file['name'] ?? '');
            if ($field === 'identity_document') {
                $has_id = true;
                if ($name !== '') {
                    $id_names[] = $name;
                }
            } else {
                $has_evidence = true;
                if ($name !== '') {
                    $evidence_names[] = $name;
                }
            }
        }

        $phone = trim((string) ($row['reporter_phone'] ?? $payload['phone'] ?? ''));
        $phone_digits = preg_replace('/[^\d]/', '', $phone);
        $country_code = strtoupper(sanitize_text_field((string) ($payload['country_code'] ?? '')));
        if ($country_code === '' || !preg_match('/^[A-Z]{2}$/', $country_code)) {
            $country_code = self::country_code_from_text((string) ($row['reporter_country'] ?? ''));
        }

        return array(
            'reference_id'       => (string) ($row['reference_id'] ?? ''),
            'status'             => sanitize_key((string) ($row['status'] ?? 'draft')),
            'full_name'          => trim((string) ($row['reporter_name'] ?? '')),
            'email'              => trim((string) ($row['reporter_email'] ?? '')),
            'phone'              => $phone,
            'phone_digits'       => is_string($phone_digits) ? $phone_digits : '',
            'country'            => trim((string) ($row['reporter_country'] ?? '')),
            'country_code'       => $country_code,
            'identity_document'  => $has_id,
            'identity_files'     => $id_names,
            'identity_accuracy'  => !empty($payload['identity_accuracy']),
            'category'           => sanitize_key((string) ($row['category'] ?? '')),
            'incident_date'      => trim((string) ($payload['incident_date'] ?? '')),
            'incident_time'      => trim((string) ($payload['incident_time'] ?? '')),
            'incident_at'        => trim((string) ($row['incident_at'] ?? '')),
            'platforms'          => trim((string) ($payload['platforms'] ?? '')),
            'description'        => trim((string) ($payload['description'] ?? '')),
            'financial_loss'     => trim((string) ($payload['financial_loss'] ?? '')),
            'financial_currency' => strtoupper(sanitize_text_field((string) ($payload['financial_currency'] ?? 'EUR'))),
            'urgency'            => sanitize_key((string) ($row['urgency'] ?? '')),
            'has_evidence'       => $has_evidence,
            'evidence_files'     => $evidence_names,
            'decl_truthful'      => !empty($declarations['truthful']),
            'decl_false_reports' => !empty($declarations['false_reports']),
            'decl_verification'  => !empty($declarations['verification']),
            'locale'             => sanitize_key((string) ($payload['locale'] ?? '')),
            'chat_session_id'    => (string) ($row['chat_session_id'] ?? ''),
            'fresh_start'        => !empty($payload['fresh_start']),
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param int                  $step
     * @return list<string>
     */
    public static function missing_for_step($state, $step) {
        $state = is_array($state) ? $state : array();
        $step = (int) $step;
        $missing = array();
        if ($step === self::STEP_IDENTITY) {
            if (strlen((string) ($state['full_name'] ?? '')) < 2) {
                $missing[] = 'full_name';
            }
            if (!self::is_valid_email((string) ($state['email'] ?? ''))) {
                $missing[] = 'email';
            }
            if (strlen((string) ($state['phone_digits'] ?? '')) < 6) {
                $missing[] = 'phone';
            }
            if (trim((string) ($state['country'] ?? '')) === '' && trim((string) ($state['country_code'] ?? '')) === '') {
                $missing[] = 'country';
            }
            if (empty($state['identity_document'])) {
                $missing[] = 'identity_document';
            }
            if (empty($state['identity_accuracy'])) {
                $missing[] = 'identity_accuracy';
            }
        } elseif ($step === self::STEP_INCIDENT) {
            $allowed = class_exists('PAXdesign_Cybercrime_Intake')
                ? PAXdesign_Cybercrime_Intake::category_keys()
                : array('account_takeover', 'phishing_fraud', 'identity_theft', 'malware_ransomware', 'social_media_recovery', 'financial_fraud', 'data_breach', 'other');
            if (!in_array((string) ($state['category'] ?? ''), $allowed, true)) {
                $missing[] = 'incident_type';
            }
            if (trim((string) ($state['incident_date'] ?? $state['incident_at'] ?? '')) === '') {
                $missing[] = 'incident_date';
            }
            if (trim((string) ($state['platforms'] ?? '')) === '') {
                $missing[] = 'platforms';
            }
            if (strlen(trim((string) ($state['description'] ?? ''))) < 20) {
                $missing[] = 'description';
            }
        } elseif ($step === self::STEP_EVIDENCE) {
            if (empty($state['has_evidence'])) {
                $missing[] = 'evidence_files';
            }
        } elseif ($step === self::STEP_REVIEW) {
            if (empty($state['decl_truthful']) || empty($state['decl_false_reports']) || empty($state['decl_verification'])) {
                $missing[] = 'declarations';
            }
        }
        return $missing;
    }

    /**
     * @param array<string, mixed> $state
     * @return int
     */
    public static function current_step($state) {
        foreach (array(self::STEP_IDENTITY, self::STEP_INCIDENT, self::STEP_EVIDENCE, self::STEP_REVIEW) as $step) {
            if (!empty(self::missing_for_step($state, $step))) {
                return $step;
            }
        }
        return self::STEP_REVIEW;
    }

    /**
     * @param array<string, mixed> $state
     * @return bool
     */
    public static function can_submit($state) {
        foreach (array(self::STEP_IDENTITY, self::STEP_INCIDENT, self::STEP_EVIDENCE, self::STEP_REVIEW) as $step) {
            if (!empty(self::missing_for_step($state, $step))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Public snapshot for chat, prompts, and the case page.
     *
     * @param array<string, mixed> $row
     * @param string               $lang
     * @return array<string, mixed>
     */
    public static function snapshot($row, $lang = '') {
        $lang = self::normalize_lang($lang);
        $state = self::state_from_row($row);
        $step = self::current_step($state);
        $missing = array();
        $missing_by_step = array();
        foreach (array(self::STEP_IDENTITY, self::STEP_INCIDENT, self::STEP_EVIDENCE, self::STEP_REVIEW) as $n) {
            $keys = self::missing_for_step($state, $n);
            $missing_by_step[$n] = $keys;
            foreach ($keys as $key) {
                $missing[] = $key;
            }
        }
        $steps = self::steps();
        $completed = array();
        foreach ($steps as $n => $meta) {
            if (empty($missing_by_step[$n]) && $n < $step) {
                $completed[] = $meta['key'];
            } elseif (empty($missing_by_step[$n]) && $n <= self::STEP_REVIEW && $step === self::STEP_REVIEW) {
                if ($n < self::STEP_REVIEW || self::can_submit($state)) {
                    $completed[] = $meta['key'];
                }
            }
        }
        return array(
            'step'            => $step,
            'step_key'        => $steps[$step]['key'] ?? 'identity',
            'step_label'      => $steps[$step][$lang] ?? $steps[$step]['en'],
            'completed_steps' => array_values(array_unique($completed)),
            'missing'         => $missing,
            'missing_labels'  => array_map(function ($key) use ($lang) {
                return self::field_label($key, $lang);
            }, $missing),
            'can_submit'      => self::can_submit($state),
            'review'          => self::review_rows($state, $lang),
            'status'          => (string) ($state['status'] ?? ''),
            'reference_id'    => (string) ($state['reference_id'] ?? ''),
        );
    }

    /**
     * Extract website form fields from one natural-language message.
     *
     * @param string               $text
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    public static function extract_from_message($text, $existing = array()) {
        $text = trim((string) $text);
        if ($text === '') {
            return array();
        }
        if (
            class_exists('PAXdesign_Language_Routing')
            && PAXdesign_Language_Routing::detect_language_preference($text) !== ''
        ) {
            return array();
        }
        if (class_exists('PAXdesign_Cybercrime_AI_Case') && PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request($text)) {
            return array();
        }

        $existing = is_array($existing) ? $existing : array();
        $fields = array();
        $normalized = self::normalize_text($text);
        $step = self::current_step($existing);
        $missing = array();
        foreach (array(self::STEP_IDENTITY, self::STEP_INCIDENT, self::STEP_EVIDENCE, self::STEP_REVIEW) as $n) {
            $missing = array_merge($missing, self::missing_for_step($existing, $n));
        }
        $first_missing = (string) ($missing[0] ?? '');

        $name = self::detect_full_name($text);
        if ($name !== '') {
            $fields['reporter_name'] = $name;
        }
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
            $fields['reporter_email'] = strtolower($m[0]);
        }
        $phone = self::detect_phone($text);
        if ($phone === '' && in_array('phone', $missing, true) && !self::looks_like_date($text)) {
            $phone = self::detect_phone_loose($text);
        }
        if ($phone !== '') {
            $fields['reporter_phone'] = $phone;
        }
        $country = self::detect_country($text);
        if ($country !== '') {
            $fields['reporter_country'] = $country['label'];
            $fields['country_code'] = $country['code'];
        }

        if (
            empty($fields['reporter_name'])
            && in_array('full_name', $missing, true)
            && self::looks_like_person_name($text, $first_missing === 'full_name')
        ) {
            $fields['reporter_name'] = preg_replace('/\s+/u', ' ', $text);
        }

        $only_checkboxes_left = self::only_confirmations_missing($existing);
        $confirms = self::is_confirmation($normalized);
        $short_yes = self::is_short_yes($normalized);
        $submit = self::is_submit_intent($text);
        if ($confirms || $short_yes) {
            $identity_missing = self::missing_for_step($existing, self::STEP_IDENTITY);
            if ($identity_missing === array('identity_accuracy')) {
                $fields['identity_accuracy'] = true;
            }
            if (self::review_ready_except_declarations($existing) || self::is_declaration_phrase($normalized) || $only_checkboxes_left) {
                $fields['identity_accuracy'] = true;
                $fields['declarations'] = array(
                    'truthful'      => true,
                    'false_reports' => true,
                    'verification'  => true,
                );
            }
        }

        if ($submit) {
            $fields['submit_intent'] = true;
            if (self::review_ready_except_declarations($existing) || self::can_submit(array_merge($existing, array(
                'decl_truthful' => true,
                'decl_false_reports' => true,
                'decl_verification' => true,
                'identity_accuracy' => true,
            )))) {
                $fields['identity_accuracy'] = true;
                $fields['declarations'] = array(
                    'truthful'      => true,
                    'false_reports' => true,
                    'verification'  => true,
                );
            }
        }

        $ack_only = ($confirms || $short_yes || $submit) && empty($fields['reporter_name']) && empty($fields['reporter_email']) && empty($fields['reporter_phone']);
        if ($ack_only && $step === self::STEP_IDENTITY && empty($fields['identity_accuracy'])) {
            return $fields;
        }

        $label_category = self::detect_category_label($text);
        if ($label_category !== '') {
            $fields['category'] = $label_category;
        }
        if (class_exists('PAXdesign_Cybercrime_AI_Case')) {
            $incident = PAXdesign_Cybercrime_AI_Case::extract_fields_from_message(
                $text,
                array(
                    'category'      => (string) ($existing['category'] ?? ''),
                    'platforms'     => (string) ($existing['platforms'] ?? ''),
                    'description'   => (string) ($existing['description'] ?? ''),
                    'incident_date' => (string) ($existing['incident_date'] ?? ''),
                    'incident_at'   => (string) ($existing['incident_at'] ?? ''),
                )
            );
            if (is_array($incident)) {
                foreach (array('category', 'incident_date', 'incident_time', 'incident_at', 'platforms', 'urgency', 'financial_loss', 'financial_currency') as $key) {
                    if ($key === 'category' && !empty($fields['category'])) {
                        continue;
                    }
                    if (!empty($incident[$key]) && empty($fields[$key])) {
                        $fields[$key] = $incident[$key];
                    }
                }
            }
        }

        if (empty($fields['incident_date']) && in_array('incident_date', $missing, true)) {
            $relative = self::detect_relative_date($normalized);
            if ($relative !== '') {
                $fields['incident_date'] = $relative;
                $fields['incident_at'] = $relative . ' 00:00:00';
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                $fields['incident_date'] = $m[0];
                $fields['incident_at'] = $m[0] . ' 00:00:00';
            }
        }

        $urgency = self::detect_urgency_label($normalized);
        if ($urgency !== '') {
            $fields['urgency'] = $urgency;
        }

        if (
            empty($fields['platforms'])
            && in_array('platforms', $missing, true)
            && $first_missing === 'platforms'
            && !$submit
            && !$short_yes
            && mb_strlen($text) <= 160
            && !self::looks_like_date($text)
        ) {
            $fields['platforms'] = preg_replace('/\s+/u', ' ', $text);
        }

        if (
            in_array('description', $missing, true)
            && $step === self::STEP_INCIDENT
            && mb_strlen($text) >= 20
            && !$submit
            && !$short_yes
            && !self::is_workflow_help_intent($text)
        ) {
            $fields['description'] = $text;
        }

        return $fields;
    }

    /**
     * Merge extracted chat fields into a workflow state (no database).
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function merge_extracted_into_state($state, $fields) {
        $state = is_array($state) ? $state : array();
        $fields = is_array($fields) ? $fields : array();
        if (!empty($fields['reporter_name'])) {
            $state['full_name'] = trim((string) $fields['reporter_name']);
        }
        if (!empty($fields['reporter_email'])) {
            $state['email'] = trim((string) $fields['reporter_email']);
        }
        if (!empty($fields['reporter_phone'])) {
            $state['phone'] = trim((string) $fields['reporter_phone']);
            $digits = preg_replace('/[^\d]/', '', $state['phone']);
            $state['phone_digits'] = is_string($digits) ? $digits : '';
        }
        if (!empty($fields['reporter_country'])) {
            $state['country'] = trim((string) $fields['reporter_country']);
        }
        if (!empty($fields['country_code'])) {
            $state['country_code'] = strtoupper(sanitize_text_field((string) $fields['country_code']));
        }
        if (!empty($fields['identity_accuracy'])) {
            $state['identity_accuracy'] = true;
        }
        if (!empty($fields['declarations']) && is_array($fields['declarations'])) {
            $state['decl_truthful'] = !empty($fields['declarations']['truthful']);
            $state['decl_false_reports'] = !empty($fields['declarations']['false_reports']);
            $state['decl_verification'] = !empty($fields['declarations']['verification']);
        }
        foreach (array('category', 'incident_date', 'incident_time', 'incident_at', 'platforms', 'description', 'urgency', 'financial_loss', 'financial_currency') as $key) {
            if (isset($fields[$key]) && (string) $fields[$key] !== '') {
                $state[$key] = $fields[$key];
            }
        }
        return $state;
    }

    /**
     * Apply one customer message (and optional chat uploads) to the same CCS state.
     *
     * @param array<string, mixed>             $state
     * @param string                           $message
     * @param array<int, array<string, mixed>> $files
     * @return array<string, mixed>
     */
    public static function advance_state($state, $message, $files = array()) {
        $state = self::merge_extracted_into_state($state, self::extract_from_message($message, $state));
        foreach ((array) $files as $file) {
            if (!is_array($file)) {
                continue;
            }
            $field = sanitize_key((string) ($file['field'] ?? ''));
            $name = (string) ($file['name'] ?? $file['original_name'] ?? 'file');
            if ($field === 'identity_document' || (empty($state['identity_document']) && $field === '')) {
                $state['identity_document'] = true;
                $state['identity_files'] = array_values(array_filter(array_merge((array) ($state['identity_files'] ?? array()), array($name))));
            } else {
                $state['has_evidence'] = true;
                $state['evidence_files'] = array_values(array_filter(array_merge((array) ($state['evidence_files'] ?? array()), array($name))));
            }
        }
        return $state;
    }

    /**
     * @param string $text
     * @return bool
     */
    public static function is_submit_intent($text) {
        $normalized = self::normalize_text($text);
        if ($normalized === '') {
            return false;
        }
        return (bool) preg_match(
            '/(?:submit (?:the )?(?:report|case)|send (?:the )?(?:report|case)|file (?:the )?report|أرسل(?:ي)?(?:ي)?\s*(?:البلاغ|التقرير)|ارسل(?:ي)?\s*(?:البلاغ|التقرير)|تقديم البلاغ|أرسل البلاغ|bericht (?:senden|absenden|einreichen)|meldung (?:senden|absenden)|jetzt absenden|^submit$|^senden$|^absenden$|^أرسل$|^ارسل$)/u',
            $normalized
        );
    }

    /**
     * @param string $text
     * @return bool
     */
    public static function is_workflow_help_intent($text) {
        $normalized = self::normalize_text($text);
        if ($normalized === '') {
            return false;
        }
        return (bool) preg_match(
            '/(?:help .{0,24}(?:submit|file|report)|submit a report|file a report|start (?:the )?report|i (?:want|need|would like) to (?:submit|file|report)|أريد (?:تقديم|فتح|إرسال) بلاغ|اريد (?:تقديم|فتح|ارسال) بلاغ|مساعدة.{0,20}بلاغ|bericht.{0,24}(?:erstatten|einreichen)|meldung.{0,24}(?:erstatten|einreichen)|mochte.{0,24}bericht)/u',
            $normalized
        );
    }

    /**
     * Guide / review / submit this draft CCS turn.
     *
     * @param array<string, mixed> $row
     * @param string               $user_message
     * @param string               $language
     * @param int                  $user_id
     * @return array<string, mixed>|null
     */
    public static function decide_turn($row, $user_message, $language = '', $user_id = 0) {
        if (!is_array($row) || empty($row['reference_id'])) {
            return null;
        }
        $status = sanitize_key((string) ($row['status'] ?? ''));
        if ($status !== '' && $status !== 'draft') {
            return null;
        }

        $language = self::normalize_lang($language);
        if (class_exists('PAXdesign_Cybercrime_AI_Case') && PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request($user_message)) {
            $snapshot = self::snapshot($row, $language);
            $row = self::persist_snapshot($row, $snapshot);
            $state = self::state_from_row($row);
            return array(
                'action'    => 'continue_case',
                'skip_llm'  => true,
                'operation' => null,
                'reply'     => self::new_case_opened_copy($snapshot, $state, $language),
                'report'    => $row,
                'snapshot'  => $snapshot,
            );
        }

        $state = self::state_from_row($row);
        $extracted = self::extract_from_message($user_message, $state);
        if (!empty($extracted) && $user_id > 0 && class_exists('PAXdesign_Cybercrime_AI_Case')) {
            $updated = PAXdesign_Cybercrime_AI_Case::apply_extracted_fields(
                (string) $row['reference_id'],
                $user_id,
                $extracted,
                'chat'
            );
            if (is_array($updated)) {
                $row = $updated;
                $state = self::state_from_row($row);
            }
        }

        $snapshot = self::snapshot($row, $language);
        $row = self::persist_snapshot($row, $snapshot);

        $submit = !empty($extracted['submit_intent']) || self::is_submit_intent($user_message);
        if ($submit && self::can_submit($state)) {
            return array(
                'action'    => 'submit_case',
                'skip_llm'  => true,
                'operation' => null,
                'reply'     => '',
                'report'    => $row,
                'snapshot'  => $snapshot,
            );
        }

        return array(
            'action'    => 'continue_case',
            'skip_llm'  => true,
            'operation' => null,
            'reply'     => self::assistant_copy($snapshot, $state, $language, $submit),
            'report'    => $row,
            'snapshot'  => $snapshot,
        );
    }

    /**
     * Submit the same CCS draft using website intake validation.
     *
     * @param array<string, mixed> $row
     * @param int                  $user_id
     * @param string               $language
     * @return array<string, mixed>|WP_Error
     */
    public static function submit_case($row, $user_id, $language = '') {
        if (!class_exists('PAXdesign_Cybercrime_Intake')) {
            return new WP_Error('unavailable', __('Cybercrime Support is temporarily unavailable.', 'paxdesign-booking'));
        }
        $state = self::state_from_row($row);
        if (!self::can_submit($state)) {
            $snapshot = self::snapshot($row, $language);
            return new WP_Error(
                'workflow_incomplete',
                self::assistant_copy($snapshot, $state, $language, true)
            );
        }
        $post = self::build_submit_post($state, $language);
        return PAXdesign_Cybercrime_Intake::complete_draft_report($row, $post, array(), $user_id);
    }

    /**
     * @param array<string, mixed> $state
     * @param string               $language
     * @return array<string, mixed>
     */
    public static function build_submit_post($state, $language = '') {
        $language = self::normalize_lang($language);
        $country = strtoupper((string) ($state['country_code'] ?? ''));
        if ($country === '' || !preg_match('/^[A-Z]{2}$/', $country)) {
            $country = self::country_code_from_text((string) ($state['country'] ?? ''));
        }
        $urgency = sanitize_key((string) ($state['urgency'] ?? ''));
        if ($urgency === '') {
            $urgency = 'low';
        }
        $incident_date = (string) ($state['incident_date'] ?? '');
        if ($incident_date === '' && !empty($state['incident_at'])) {
            $incident_date = substr((string) $state['incident_at'], 0, 10);
        }
        return array(
            'full_name'           => (string) ($state['full_name'] ?? ''),
            'email'               => (string) ($state['email'] ?? ''),
            'phone'               => (string) ($state['phone'] ?? ''),
            'country'             => $country,
            'category'            => (string) ($state['category'] ?? ''),
            'urgency'             => $urgency,
            'platforms'           => (string) ($state['platforms'] ?? ''),
            'description'         => (string) ($state['description'] ?? ''),
            'financial_loss'      => (string) ($state['financial_loss'] ?? ''),
            'financial_currency'  => (string) ($state['financial_currency'] ?? 'EUR'),
            'identity_accuracy'   => 1,
            'decl_truthful'       => 1,
            'decl_false_reports'  => 1,
            'decl_verification'   => 1,
            'locale'              => $language,
            'incident_date'       => $incident_date,
            'incident_time'       => (string) ($state['incident_time'] ?? ''),
            'source'              => 'ai_chat',
            'chat_session_id'     => (string) ($state['chat_session_id'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function persist_snapshot($row, $snapshot) {
        if (!is_array($row) || empty($row['reference_id']) || !is_array($snapshot)) {
            return $row;
        }
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $payload['ai_workflow'] = array(
            'step'            => (int) ($snapshot['step'] ?? self::STEP_IDENTITY),
            'step_key'        => (string) ($snapshot['step_key'] ?? 'identity'),
            'completed_steps' => array_values((array) ($snapshot['completed_steps'] ?? array())),
            'missing'         => array_values((array) ($snapshot['missing'] ?? array())),
            'can_submit'      => !empty($snapshot['can_submit']),
            'updated_at'      => function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s'),
        );
        $encoded = function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload);
        $row['payload'] = is_string($encoded) ? $encoded : (string) ($row['payload'] ?? '');
        if (isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb']) && class_exists('PAXdesign_Cybercrime_Intake')) {
            $GLOBALS['wpdb']->update(
                PAXdesign_Cybercrime_Intake::table_name(),
                array(
                    'payload'    => $row['payload'],
                    'updated_at' => $payload['ai_workflow']['updated_at'],
                ),
                array('reference_id' => (string) $row['reference_id']),
                array('%s', '%s'),
                array('%s')
            );
        }
        return $row;
    }

    /**
     * @param string $key
     * @param string $lang
     * @return string
     */
    public static function field_label($key, $lang = '') {
        $lang = self::normalize_lang($lang);
        $labels = array(
            'full_name' => array('en' => 'full legal name', 'de' => 'vollständiger gesetzlicher Name', 'ar' => 'الاسم القانوني الكامل'),
            'email' => array('en' => 'email address', 'de' => 'E-Mail-Adresse', 'ar' => 'البريد الإلكتروني'),
            'phone' => array('en' => 'phone number', 'de' => 'Telefonnummer', 'ar' => 'رقم الهاتف'),
            'country' => array('en' => 'country', 'de' => 'Land', 'ar' => 'البلد'),
            'identity_document' => array('en' => 'identity document', 'de' => 'Ausweisdokument', 'ar' => 'وثيقة الهوية'),
            'identity_accuracy' => array('en' => 'identity accuracy confirmation', 'de' => 'Bestätigung der Identitätsangaben', 'ar' => 'تأكيد صحة بيانات الهوية'),
            'incident_type' => array('en' => 'incident type', 'de' => 'Vorfallstyp', 'ar' => 'نوع الحادثة'),
            'incident_date' => array('en' => 'incident date', 'de' => 'Datum des Vorfalls', 'ar' => 'تاريخ الحادثة'),
            'platforms' => array('en' => 'affected platforms', 'de' => 'betroffene Plattformen', 'ar' => 'المنصات المتأثرة'),
            'description' => array('en' => 'incident description', 'de' => 'Beschreibung des Vorfalls', 'ar' => 'وصف الحادثة'),
            'evidence_files' => array('en' => 'evidence files', 'de' => 'Beweisdateien', 'ar' => 'ملفات الأدلة'),
            'declarations' => array('en' => 'review declarations', 'de' => 'Prüferklärungen', 'ar' => 'إقرارات المراجعة'),
            'urgency' => array('en' => 'urgency', 'de' => 'Dringlichkeit', 'ar' => 'درجة الاستعجال'),
            'financial_loss' => array('en' => 'financial loss', 'de' => 'finanzieller Verlust', 'ar' => 'الخسارة المالية'),
        );
        if (class_exists('PAXdesign_Cybercrime_I18n')) {
            $mapped = PAXdesign_Cybercrime_I18n::missing_field_label($key, $lang);
            if ($mapped !== '' && $mapped !== $key) {
                return $mapped;
            }
        }
        return $labels[$key][$lang] ?? $labels[$key]['en'] ?? $key;
    }

    /**
     * @param array<string, mixed> $state
     * @param string               $lang
     * @return list<array{label:string,value:string}>
     */
    public static function review_rows($state, $lang = '') {
        $lang = self::normalize_lang($lang);
        $id_files = implode(', ', array_slice((array) ($state['identity_files'] ?? array()), 0, 4));
        $evidence = implode(', ', array_slice((array) ($state['evidence_files'] ?? array()), 0, 6));
        $loss = trim((string) ($state['financial_loss'] ?? ''));
        if ($loss !== '' && !empty($state['financial_currency'])) {
            $loss .= ' ' . $state['financial_currency'];
        }
        $date = (string) ($state['incident_date'] ?? '');
        if ($date === '' && !empty($state['incident_at'])) {
            $date = substr((string) $state['incident_at'], 0, 10);
        }
        if (!empty($state['incident_time'])) {
            $date = trim($date . ' ' . $state['incident_time']);
        }
        $none = '—';
        $yes = $lang === 'ar' ? 'نعم' : ($lang === 'de' ? 'Ja' : 'Yes');
        $no = $lang === 'ar' ? 'لا' : ($lang === 'de' ? 'Nein' : 'No');
        return array(
            array('label' => self::field_label('full_name', $lang), 'value' => (string) ($state['full_name'] ?: $none)),
            array('label' => self::field_label('email', $lang), 'value' => (string) ($state['email'] ?: $none)),
            array('label' => self::field_label('phone', $lang), 'value' => (string) ($state['phone'] ?: $none)),
            array('label' => self::field_label('country', $lang), 'value' => (string) (($state['country'] ?: $state['country_code']) ?: $none)),
            array('label' => self::field_label('identity_document', $lang), 'value' => $id_files !== '' ? $id_files : $none),
            array('label' => self::field_label('incident_type', $lang), 'value' => self::category_display((string) ($state['category'] ?? ''), $lang) ?: $none),
            array('label' => self::field_label('incident_date', $lang), 'value' => $date !== '' ? $date : $none),
            array('label' => self::field_label('platforms', $lang), 'value' => (string) ($state['platforms'] ?: $none)),
            array('label' => self::field_label('urgency', $lang), 'value' => self::urgency_display((string) ($state['urgency'] ?? ''), $lang) ?: $none),
            array('label' => self::field_label('financial_loss', $lang), 'value' => $loss !== '' ? $loss : $none),
            array('label' => self::field_label('description', $lang), 'value' => (string) ($state['description'] ?: $none)),
            array('label' => self::field_label('evidence_files', $lang), 'value' => $evidence !== '' ? $evidence : $none),
            array('label' => self::field_label('declarations', $lang), 'value' => (!empty($state['decl_truthful']) && !empty($state['decl_false_reports']) && !empty($state['decl_verification'])) ? $yes : $no),
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $state
     * @param string               $lang
     * @param bool                 $wanted_submit
     * @return string
     */
    public static function assistant_copy($snapshot, $state, $lang = '', $wanted_submit = false) {
        $lang = self::normalize_lang($lang);
        $step = (int) ($snapshot['step'] ?? self::STEP_IDENTITY);
        $label = (string) ($snapshot['step_label'] ?? '');
        $ref = (string) ($snapshot['reference_id'] ?? '');
        $missing = array_values((array) ($snapshot['missing_labels'] ?? array()));
        $lines = array();

        if ($lang === 'ar') {
            $fresh = !empty($state['fresh_start']);
            $lines[] = $fresh
                ? ('تم فتح بلاغ جديد' . ($ref !== '' ? ' ' . $ref : '') . '. لن نستخدم بيانات أو ملفات أو مرجع الحالة السابقة.')
                : ('ما زلنا على نفس حالة الدعم الخاصة بالجرائم الإلكترونية' . ($ref !== '' ? ' ' . $ref : '') . '.');
            $lines[] = 'الخطوة الحالية من نموذج الموقع: ' . $label . ' (' . $step . '/4).';
        } elseif ($lang === 'de') {
            $fresh = !empty($state['fresh_start']);
            $lines[] = $fresh
                ? ('Ein neuer Cybercrime-Support-Fall' . ($ref !== '' ? ' ' . $ref : '') . ' wurde eröffnet. Vorherige Daten, Dateien und die alte Referenz werden nicht verwendet.')
                : ('Wir bleiben beim selben Cybercrime-Support-Fall' . ($ref !== '' ? ' ' . $ref : '') . '.');
            $lines[] = 'Aktueller Schritt des Website-Formulars: ' . $label . ' (' . $step . '/4).';
        } else {
            $fresh = !empty($state['fresh_start']);
            $lines[] = $fresh
                ? ('A new Cybercrime Support case' . ($ref !== '' ? ' ' . $ref : '') . ' was opened. Previous case data, files, and the previous reference are not used.')
                : ('This is still the same Cybercrime Support case' . ($ref !== '' ? ' ' . $ref : '') . '.');
            $lines[] = 'Current website workflow step: ' . $label . ' (' . $step . '/4).';
        }

        if ($step === self::STEP_REVIEW) {
            $lines[] = '';
            if ($lang === 'ar') {
                $lines[] = 'ملخص ما سيتم إرساله:';
            } elseif ($lang === 'de') {
                $lines[] = 'Zusammenfassung der Übermittlung:';
            } else {
                $lines[] = 'What will be submitted:';
            }
            foreach ((array) ($snapshot['review'] ?? array()) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lines[] = '• ' . (string) ($row['label'] ?? '') . ': ' . (string) ($row['value'] ?? '');
            }
        }

        if (!empty($missing)) {
            $lines[] = '';
            if ($lang === 'ar') {
                $lines[] = 'لا يزال ناقصاً: ' . implode('، ', $missing) . '.';
            } elseif ($lang === 'de') {
                $lines[] = 'Noch erforderlich: ' . implode(', ', $missing) . '.';
            } else {
                $lines[] = 'Still missing: ' . implode(', ', $missing) . '.';
            }
            if ($wanted_submit) {
                if ($lang === 'ar') {
                    $lines[] = 'لا يمكن إرسال البلاغ قبل اكتمال هذه الحقول على نفس الحالة.';
                } elseif ($lang === 'de') {
                    $lines[] = 'Die Meldung kann erst gesendet werden, wenn diese Angaben auf demselben Fall vollständig sind.';
                } else {
                    $lines[] = 'The report cannot be submitted until these items are complete on this same case.';
                }
            }
            $next = $missing[0];
            $lines[] = self::next_prompt($snapshot['missing'][0] ?? '', $lang);
            unset($next);
        } elseif ($step === self::STEP_REVIEW) {
            if ($lang === 'ar') {
                $lines[] = 'جميع متطلبات النموذج مكتملة. أكّد الإقرارات الثلاثة أو اكتب «أرسل البلاغ» لإرسال نفس الحالة.';
            } elseif ($lang === 'de') {
                $lines[] = 'Alle Formularanforderungen sind erfüllt. Bestätigen Sie die drei Erklärungen oder schreiben Sie „Bericht absenden“, um denselben Fall zu senden.';
            } else {
                $lines[] = 'All form requirements are complete. Confirm the three declarations or type “submit report” to send this same case.';
            }
        }

        if (in_array('identity_document', (array) ($snapshot['missing'] ?? array()), true) || in_array('evidence_files', (array) ($snapshot['missing'] ?? array()), true)) {
            if ($lang === 'ar') {
                $lines[] = 'ارفع الملف بزر + بجانب صندوق الرسالة. يُرفق على نفس الحالة.';
            } elseif ($lang === 'de') {
                $lines[] = 'Laden Sie die Datei über die +-Taste neben dem Nachrichtenfeld hoch. Sie wird an denselben Fall angehängt.';
            } else {
                $lines[] = 'Upload the file with the + button next to the message box. It attaches to this same case.';
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param string $key
     * @param string $lang
     * @return string
     */
    private static function next_prompt($key, $lang) {
        $prompts = array(
            'full_name' => array(
                'en' => 'What is your full legal name as shown on your ID or passport?',
                'de' => 'Wie lautet Ihr vollständiger gesetzlicher Name laut Ausweis oder Reisepass?',
                'ar' => 'ما اسمك القانوني الكامل كما هو مكتوب في الهوية أو جواز السفر؟',
            ),
            'email' => array(
                'en' => 'Which email address should we use for this case?',
                'de' => 'Welche E-Mail-Adresse sollen wir für diesen Fall verwenden?',
                'ar' => 'ما البريد الإلكتروني الذي نستخدمه لهذه الحالة؟',
            ),
            'phone' => array(
                'en' => 'What is your phone number, including country code?',
                'de' => 'Wie lautet Ihre Telefonnummer inklusive Ländervorwahl?',
                'ar' => 'ما رقم هاتفك مع رمز الدولة؟',
            ),
            'country' => array(
                'en' => 'Which country should we record for this report?',
                'de' => 'Welches Land sollen wir für diese Meldung speichern?',
                'ar' => 'ما البلد الذي نسجله في هذا البلاغ؟',
            ),
            'identity_document' => array(
                'en' => 'Please upload a clear PDF or photo of your passport, ID card, or driver’s license.',
                'de' => 'Bitte laden Sie ein klares PDF oder Foto Ihres Reisepasses, Ausweises oder Führerscheins hoch.',
                'ar' => 'يرجى رفع صورة واضحة أو PDF لجواز السفر أو بطاقة الهوية أو رخصة القيادة.',
            ),
            'identity_accuracy' => array(
                'en' => 'Please confirm that the identity information you provided is accurate and correct.',
                'de' => 'Bitte bestätigen Sie, dass die angegebenen Identitätsdaten zutreffend und korrekt sind.',
                'ar' => 'يرجى تأكيد أن بيانات الهوية التي قدمتها دقيقة وصحيحة.',
            ),
            'incident_type' => array(
                'en' => 'What happened? Choose the incident type (for example account takeover, phishing, or financial fraud).',
                'de' => 'Was ist passiert? Wählen Sie den Vorfallstyp (z. B. Kontoübernahme, Phishing oder Finanzbetrug).',
                'ar' => 'ماذا حدث؟ حدّد نوع الحادثة (مثل اختراق حساب أو تصيد أو احتيال مالي).',
            ),
            'incident_date' => array(
                'en' => 'On which date did the incident happen?',
                'de' => 'An welchem Datum ist der Vorfall passiert?',
                'ar' => 'في أي تاريخ حدثت الحادثة؟',
            ),
            'platforms' => array(
                'en' => 'Which platforms or services were affected?',
                'de' => 'Welche Plattformen oder Dienste waren betroffen?',
                'ar' => 'ما المنصات أو الخدمات المتأثرة؟',
            ),
            'description' => array(
                'en' => 'Please describe what happened in your own words (at least a short paragraph).',
                'de' => 'Bitte beschreiben Sie den Vorfall in eigenen Worten (mindestens ein kurzer Absatz).',
                'ar' => 'يرجى وصف ما حدث بكلماتك (فقرة قصيرة على الأقل).',
            ),
            'evidence_files' => array(
                'en' => 'Please attach at least one piece of evidence (screenshot, document, or chat export).',
                'de' => 'Bitte hängen Sie mindestens einen Beweis an (Screenshot, Dokument oder Chat-Export).',
                'ar' => 'يرجى إرفاق دليل واحد على الأقل (لقطة شاشة أو مستند أو تصدير محادثة).',
            ),
            'declarations' => array(
                'en' => 'Please confirm the three review declarations, or type “submit report”.',
                'de' => 'Bitte bestätigen Sie die drei Prüferklärungen oder schreiben Sie „Bericht absenden“.',
                'ar' => 'يرجى تأكيد إقرارات المراجعة الثلاثة أو كتابة «أرسل البلاغ».',
            ),
        );
        return $prompts[$key][$lang] ?? $prompts[$key]['en'] ?? '';
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $state
     * @param string               $lang
     * @return string
     */
    public static function new_case_opened_copy($snapshot, $state, $lang = '') {
        $lang = self::normalize_lang($lang);
        $snapshot = is_array($snapshot) ? $snapshot : array();
        $state = is_array($state) ? array_merge($state, array('fresh_start' => true)) : array('fresh_start' => true);
        return self::assistant_copy($snapshot, $state, $lang, false);
    }

    /**
     * @param string $success_message
     * @param string $reference
     * @param string $lang
     * @return string
     */
    public static function submitted_copy($success_message, $reference, $lang = '') {
        $lang = self::normalize_lang($lang);
        $success_message = trim((string) $success_message);
        if ($lang === 'ar') {
            return 'تم إرسال نفس الحالة ' . $reference . ' بنجاح.' . ($success_message !== '' ? ' ' . $success_message : '') . ' احتفظ برقم المرجع للمتابعة.';
        }
        if ($lang === 'de') {
            return 'Derselbe Fall ' . $reference . ' wurde erfolgreich übermittelt.' . ($success_message !== '' ? ' ' . $success_message : '') . ' Bewahren Sie die Referenznummer für die weitere Kommunikation auf.';
        }
        return 'The same case ' . $reference . ' was submitted successfully.' . ($success_message !== '' ? ' ' . $success_message : '') . ' Keep the reference number for follow-up.';
    }

    /**
     * @param string $email
     * @return bool
     */
    public static function is_valid_email($email) {
        $email = trim((string) $email);
        if ($email === '') {
            return false;
        }
        if (function_exists('is_email')) {
            return (bool) is_email($email);
        }
        return (bool) preg_match('/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', $email);
    }

    /**
     * @param string $text
     * @return string
     */
    public static function country_code_from_text($text) {
        $found = self::detect_country($text);
        return $found !== '' ? $found['code'] : '';
    }

    /**
     * @param string $text
     * @return array{code:string,label:string}|string
     */
    public static function detect_country($text) {
        $normalized = self::normalize_text($text);
        if ($normalized === '') {
            return '';
        }
        if (preg_match('/^(?:country|land|البلد)[:\s]+([a-z]{2})$/u', $normalized, $m) || preg_match('/^[a-z]{2}$/u', $normalized)) {
            $code = strtoupper(!empty($m[1]) ? $m[1] : $normalized);
            $aliases = self::country_aliases();
            if (isset($aliases[strtolower($code)])) {
                $hit = $aliases[strtolower($code)];
                return array('code' => $hit['code'], 'label' => $hit['en']);
            }
        }
        foreach (self::country_aliases() as $needle => $hit) {
            if ($needle === strtolower($hit['code'])) {
                continue;
            }
            if (preg_match('/(^|[^a-z\x{0600}-\x{06FF}])' . preg_quote($needle, '/') . '([^a-z\x{0600}-\x{06FF}]|$)/u', $normalized)) {
                return array('code' => $hit['code'], 'label' => $hit['en']);
            }
        }
        return '';
    }

    /**
     * @return array<string, array{code:string,en:string}>
     */
    private static function country_aliases() {
        $rows = array(
            array('AT', 'austria', 'österreich', 'osterreich', 'النمسا'),
            array('DE', 'germany', 'deutschland', 'ألمانيا', 'المانيا'),
            array('EG', 'egypt', 'ägypten', 'agypten', 'مصر'),
            array('SA', 'saudi arabia', 'saudi', 'السعودية', 'السعوديه'),
            array('AE', 'united arab emirates', 'uae', 'emirates', 'الإمارات', 'الامارات'),
            array('US', 'united states', 'usa', 'america', 'الولايات المتحدة', 'امريكا'),
            array('GB', 'united kingdom', 'uk', 'england', 'britain', 'بريطانيا', 'المملكة المتحدة'),
            array('FR', 'france', 'frankreich', 'فرنسا'),
            array('IT', 'italy', 'italien', 'إيطاليا', 'ايطاليا'),
            array('ES', 'spain', 'spanien', 'إسبانيا', 'اسبانيا'),
            array('CH', 'switzerland', 'schweiz', 'سويسرا'),
            array('NL', 'netherlands', 'niederlande', 'holland', 'هولندا'),
            array('TR', 'turkey', 'türkei', 'تركيا'),
            array('IQ', 'iraq', 'irak', 'العراق'),
            array('JO', 'jordan', 'jordanien', 'الأردن', 'الاردن'),
            array('LB', 'lebanon', 'libanon', 'لبنان'),
            array('SY', 'syria', 'syrien', 'سوريا'),
            array('PS', 'palestine', 'palästina', 'فلسطين'),
            array('MA', 'morocco', 'marokko', 'المغرب'),
            array('TN', 'tunisia', 'tunesien', 'تونس'),
            array('DZ', 'algeria', 'algerien', 'الجزائر'),
            array('QA', 'qatar', 'katar', 'قطر'),
            array('KW', 'kuwait', 'الكويت'),
            array('BH', 'bahrain', 'البحرين'),
            array('OM', 'oman', 'عمان'),
            array('YE', 'yemen', 'jemen', 'اليمن'),
            array('CA', 'canada', 'kanada', 'كندا'),
            array('AU', 'australia', 'australien', 'أستراليا', 'استراليا'),
            array('SE', 'sweden', 'schweden', 'السويد'),
            array('NO', 'norway', 'norwegen', 'النرويج'),
            array('PL', 'poland', 'polen', 'بولندا'),
            array('GR', 'greece', 'griechenland', 'اليونان'),
        );
        $out = array();
        foreach ($rows as $row) {
            $code = $row[0];
            $out[strtolower($code)] = array('code' => $code, 'en' => ucwords(str_replace('_', ' ', $row[1])));
            for ($i = 1; $i < count($row); $i++) {
                $out[self::normalize_text($row[$i])] = array('code' => $code, 'en' => ucwords($row[1]));
            }
        }
        return $out;
    }

    /**
     * @param string $text
     * @return string
     */
    private static function detect_full_name($text) {
        if (preg_match('/(?:my (?:full |legal )?name is|full (?:legal )?name[:\s]+|i am called|ich heiße|ich heisse|mein name ist|اسمي(?: الكامل)?(?: هو)?[:\s]+)\s*([^\n.,]{2,80})/iu', $text, $m)) {
            $name = trim($m[1]);
            $name = preg_replace('/\s+/', ' ', $name);
            if (is_string($name) && strlen($name) >= 2 && !self::looks_like_date($name)) {
                return $name;
            }
        }
        return '';
    }

    /**
     * @param string $text
     * @param bool   $asked
     * @return bool
     */
    private static function looks_like_person_name($text, $asked = false) {
        $text = trim((string) $text);
        unset($asked);
        if ($text === '' || mb_strlen($text) < 2 || mb_strlen($text) > 80) {
            return false;
        }
        if (strpos($text, '@') !== false || self::looks_like_date($text)) {
            return false;
        }
        $normalized = self::normalize_text($text);
        if (
            self::is_submit_intent($text)
            || self::is_workflow_help_intent($text)
            || self::is_short_yes($normalized)
            || self::is_confirmation($normalized)
            || self::detect_category_label($text) !== ''
        ) {
            return false;
        }
        if (preg_match('/^(instagram|github|gmail|facebook|whatsapp|paypal|google|icloud|apple|microsoft|binance)$/u', $normalized)) {
            return false;
        }
        $country = self::detect_country($text);
        $words = preg_split('/\s+/u', $text);
        $word_count = is_array($words) ? count($words) : 0;
        if ($country !== '' && $word_count <= 4) {
            return false;
        }
        if ($word_count > 6) {
            return false;
        }
        foreach ((array) $words as $word) {
            if (!preg_match('/^[\p{L}][\p{L}\'\-]{0,40}$/u', $word)) {
                return false;
            }
        }
        if (preg_match('/^(help|submit|report|please|incident|phishing|konto|بلاغ|hilfe|meldung|ich|mochte|einen|bericht|einreichen)/iu', $normalized)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $text
     * @return bool
     */
    private static function looks_like_date($text) {
        $text = trim((string) $text);
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)
            || (bool) preg_match('/^\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}$/', $text);
    }

    /**
     * @param string $text
     * @return string
     */
    private static function detect_phone_loose($text) {
        if (preg_match('/(?:\+|00)?\d[\d\s().\-]{5,18}\d/', $text, $m)) {
            $raw = trim($m[0]);
            $digits = preg_replace('/[^\d]/', '', $raw);
            if (is_string($digits) && strlen($digits) >= 6 && strlen($digits) <= 15 && !self::looks_like_date($text)) {
                return $raw;
            }
        }
        return '';
    }

    /**
     * Match the website category cards in AR / DE / EN.
     *
     * @param string $text
     * @return string
     */
    private static function detect_category_label($text) {
        $normalized = self::normalize_text($text);
        if ($normalized === '') {
            return '';
        }
        $map = array(
            'account_takeover'      => array('account takeover', 'kontoübernahme', 'kontoubernahme', 'استيلاء على حساب', 'account_takeover'),
            'phishing_fraud'        => array('phishing / fraud', 'phishing / betrug', 'phishing', 'تصيد / احتيال', 'تصيد', 'phishing_fraud'),
            'identity_theft'        => array('identity theft', 'identitätsdiebstahl', 'identitatsdiebstahl', 'سرقة هوية', 'identity_theft'),
            'malware_ransomware'    => array('malware / ransomware', 'ransomware', 'malware', 'برمجيات خبيثة / فدية', 'برمجيات خبيثة', 'malware_ransomware'),
            'social_media_recovery' => array('social media recovery', 'social-media-wiederherstellung', 'استرداد حساب تواصل', 'استرداد حساب', 'social_media_recovery'),
            'financial_fraud'       => array('financial fraud', 'finanzbetrug', 'احتيال مالي', 'financial_fraud'),
            'data_breach'           => array('data breach', 'datenleck', 'تسريب بيانات', 'data_breach'),
            'other'                 => array('other cyber incident', 'sonstiges', 'أخرى', 'other'),
        );
        foreach ($map as $key => $needles) {
            foreach ($needles as $needle) {
                $needle = self::normalize_text($needle);
                if ($needle === '') {
                    continue;
                }
                if ($key === 'other' || $needle === 'other') {
                    if ($normalized === $needle) {
                        return $key;
                    }
                    continue;
                }
                if ($normalized === $needle || mb_strpos($normalized, $needle) !== false) {
                    return $key;
                }
            }
        }
        return '';
    }

    /**
     * @param string $normalized
     * @return string
     */
    private static function detect_relative_date($normalized) {
        if (preg_match('/^(today|heute|اليوم)$/u', $normalized) || preg_match('/\b(today|heute)\b/u', $normalized) || mb_strpos($normalized, 'اليوم') !== false) {
            return gmdate('Y-m-d');
        }
        if (preg_match('/^(yesterday|gestern|أمس|امس)$/u', $normalized) || preg_match('/\b(yesterday|gestern)\b/u', $normalized) || mb_strpos($normalized, 'أمس') !== false || mb_strpos($normalized, 'امس') !== false) {
            return gmdate('Y-m-d', time() - 86400);
        }
        return '';
    }

    /**
     * @param string $normalized
     * @return string
     */
    private static function detect_urgency_label($normalized) {
        if (preg_match('/(?:critical|kritisch|حرج)/u', $normalized)) {
            return 'critical';
        }
        if (preg_match('/(?:high urgency|very urgent|\bhigh\b|\bhoch\b|مرتفع|عاجل|dringend)/u', $normalized)) {
            return 'high';
        }
        if (preg_match('/(?:medium|\bmittel\b|متوسط)/u', $normalized)) {
            return 'medium';
        }
        if (preg_match('/(?:low urgency|not urgent|\blow\b|\bniedrig\b|منخفض)/u', $normalized)) {
            return 'low';
        }
        return '';
    }

    /**
     * @param string $key
     * @param string $lang
     * @return string
     */
    private static function category_display($key, $lang) {
        $map = array(
            'account_takeover'      => array('en' => 'Account takeover', 'de' => 'Kontoübernahme', 'ar' => 'استيلاء على حساب'),
            'phishing_fraud'        => array('en' => 'Phishing / fraud', 'de' => 'Phishing / Betrug', 'ar' => 'تصيد / احتيال'),
            'identity_theft'        => array('en' => 'Identity theft', 'de' => 'Identitätsdiebstahl', 'ar' => 'سرقة هوية'),
            'malware_ransomware'    => array('en' => 'Malware / ransomware', 'de' => 'Malware / Ransomware', 'ar' => 'برمجيات خبيثة / فدية'),
            'social_media_recovery' => array('en' => 'Social media recovery', 'de' => 'Social-Media-Wiederherstellung', 'ar' => 'استرداد حساب تواصل'),
            'financial_fraud'       => array('en' => 'Financial fraud', 'de' => 'Finanzbetrug', 'ar' => 'احتيال مالي'),
            'data_breach'           => array('en' => 'Data breach', 'de' => 'Datenleck', 'ar' => 'تسريب بيانات'),
            'other'                 => array('en' => 'Other cyber incident', 'de' => 'Sonstiges', 'ar' => 'أخرى'),
        );
        $key = sanitize_key((string) $key);
        return $map[$key][$lang] ?? $map[$key]['en'] ?? $key;
    }

    /**
     * @param string $key
     * @param string $lang
     * @return string
     */
    private static function urgency_display($key, $lang) {
        $map = array(
            'low'      => array('en' => 'Low', 'de' => 'Niedrig', 'ar' => 'منخفض'),
            'medium'   => array('en' => 'Medium', 'de' => 'Mittel', 'ar' => 'متوسط'),
            'high'     => array('en' => 'High', 'de' => 'Hoch', 'ar' => 'مرتفع'),
            'critical' => array('en' => 'Critical — happening now', 'de' => 'Kritisch — läuft gerade', 'ar' => 'حرج — نشط الآن'),
        );
        $key = sanitize_key((string) $key);
        return $map[$key][$lang] ?? $map[$key]['en'] ?? '';
    }

    /**
     * @param string $text
     * @return string
     */
    private static function detect_phone($text) {
        if (preg_match('/(?:\+|00)\d[\d\s().\-]{6,20}\d/', $text, $m)) {
            $raw = trim($m[0]);
            $raw = preg_replace('/^00/', '+', $raw);
            return is_string($raw) ? $raw : '';
        }
        if (preg_match('/(?:phone|tel|mobil|هاتف|رقم)[:\s]+(\+?\d[\d\s().\-]{6,20}\d)/iu', $text, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * @param string $normalized
     * @return bool
     */
    private static function is_confirmation($normalized) {
        return (bool) preg_match(
            '/(?:i confirm|i agree|i accept|that(?:\'s| is) (?:correct|accurate)|identity.{0,40}(?:accurate|correct)|bestätig|ich stimme|einverstanden|أؤكد|اؤكد|أوافق|اوافق|صحيح)/u',
            $normalized
        );
    }

    /**
     * @param string $normalized
     * @return bool
     */
    private static function is_declaration_phrase($normalized) {
        return (bool) preg_match(
            '/(?:declaration|true and accurate|false (?:or )?misleading|verification|إقرار|اقرار|دقيق|صحيح إلى أقصى|erkl[aä]rung)/u',
            $normalized
        );
    }

    /**
     * @param string $normalized
     * @return bool
     */
    private static function is_short_yes($normalized) {
        return (bool) preg_match('/^(?:yes|yep|yeah|ok|okay|sure|confirm|ja|genau|نعم|ايوا|أيوة|موافق|تم)$/u', $normalized);
    }

    /**
     * @param array<string, mixed> $existing
     * @return bool
     */
    private static function only_confirmations_missing($existing) {
        $state = $existing;
        $state['identity_accuracy'] = true;
        $state['decl_truthful'] = true;
        $state['decl_false_reports'] = true;
        $state['decl_verification'] = true;
        return self::can_submit($state)
            && (
                empty($existing['identity_accuracy'])
                || empty($existing['decl_truthful'])
                || empty($existing['decl_false_reports'])
                || empty($existing['decl_verification'])
            );
    }

    /**
     * @param array<string, mixed> $existing
     * @return bool
     */
    private static function review_ready_except_declarations($existing) {
        foreach (array(self::STEP_IDENTITY, self::STEP_INCIDENT, self::STEP_EVIDENCE) as $step) {
            $probe = $existing;
            if ($step === self::STEP_IDENTITY) {
                $probe['identity_accuracy'] = true;
            }
            $missing = self::missing_for_step($probe, $step);
            if ($step === self::STEP_IDENTITY) {
                $missing = array_values(array_diff($missing, array('identity_accuracy')));
            }
            if (!empty($missing)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $text
     * @return string
     */
    private static function normalize_text($text) {
        $text = trim((string) $text);
        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }
        $text = str_replace(array('’', '‘', '`', 'ö', 'ä', 'ü', 'ß', 'Ö', 'Ä', 'Ü'), array("'", "'", "'", 'o', 'a', 'u', 'ss', 'o', 'a', 'u'), $text);
        $text = preg_replace('/[?؟!.]+$/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return is_string($text) ? trim($text) : '';
    }

    /**
     * @param string $lang
     * @return string
     */
    private static function normalize_lang($lang) {
        $lang = sanitize_key((string) $lang);
        return in_array($lang, array('de', 'en', 'ar'), true) ? $lang : 'en';
    }
}
