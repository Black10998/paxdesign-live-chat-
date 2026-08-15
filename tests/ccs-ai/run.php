<?php
/**
 * CCS AI workflow checks: one short next-step prompt, one extract/save path.
 */

function ccs_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

echo "CCS AI workflow checks\n";

$root = dirname(__DIR__, 2) . '/paxdesign-booking/includes';
foreach (array(
    'class-paxdesign-cybercrime-ai-workflow.php',
    'class-paxdesign-cybercrime-ai-operations.php',
    'class-paxdesign-cybercrime-ai-case.php',
    'class-paxdesign-chat.php',
) as $file) {
    $path = $root . '/' . $file;
    ccs_assert(is_file($path), $file . ' must exist');
    exec('php -l ' . escapeshellarg($path), $out, $code);
    ccs_assert($code === 0, 'Syntax error in ' . $file);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $key));
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return trim(strip_tags((string) $text));
    }
}
if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

require_once $root . '/class-paxdesign-cybercrime-ai-workflow.php';

$phone = PAXdesign_Cybercrime_AI_Workflow::next_prompt('phone', 'ar');
ccs_assert($phone === 'أعطني رقم الهاتف.', 'Phone prompt must be exactly the short Arabic request');
ccs_assert(strpos($phone, "\n") === false, 'Phone prompt must be one line');

$country = PAXdesign_Cybercrime_AI_Workflow::next_prompt('country', 'ar');
ccs_assert($country === 'ما البلد؟', 'Country prompt must be exactly the short Arabic question');

$id = PAXdesign_Cybercrime_AI_Workflow::next_prompt('identity_document', 'ar');
ccs_assert($id === 'ارفع وثيقة الهوية من زر +.', 'Identity prompt must ask for the + upload');

$state = array(
    'full_name' => 'Ahmad Test',
    'email' => 'ahmad@example.com',
    'phone' => '',
    'phone_digits' => '',
    'country' => '',
    'country_code' => '',
    'identity_document' => false,
    'identity_accuracy' => false,
    'category' => '',
    'incident_date' => '',
    'incident_at' => '',
    'platforms' => '',
    'description' => '',
    'has_evidence' => false,
    'decl_truthful' => false,
    'decl_false_reports' => false,
    'decl_verification' => false,
    'status' => 'draft',
    'reference_id' => 'CCS-TEST',
);
$missing = PAXdesign_Cybercrime_AI_Workflow::missing_for_step($state, 1);
ccs_assert($missing[0] === 'phone', 'After name and email, phone is the next missing identity field');

$snapshot = array(
    'step' => 1,
    'step_label' => 'الهوية',
    'missing' => $missing,
    'reference_id' => 'CCS-TEST',
);
$reply = PAXdesign_Cybercrime_AI_Workflow::assistant_copy($snapshot, $state, 'ar', false, 'ok');
ccs_assert($reply === 'أعطني رقم الهاتف.', 'Follow-up replies must ask only for the next missing item');
ccs_assert(strpos($reply, 'CCS-TEST') === false, 'Follow-up replies must not repeat the reference number');
ccs_assert(strpos($reply, 'الخطوة') === false, 'Follow-up replies must not recap the workflow step');

$extracted = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('+43680111222', $state);
ccs_assert(!empty($extracted['reporter_phone']), 'A phone-only message must save into the same CCS case phone field');

$combo = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('رقمي +4368181624228 وأنا في النمسا.', $state);
ccs_assert(!empty($combo['reporter_phone']), 'A natural-language phone answer must be saved');
ccs_assert(($combo['country_code'] ?? '') === 'AT', 'The same message must also save the country');
$combo_state = PAXdesign_Cybercrime_AI_Workflow::merge_extracted_into_state($state, $combo);
$combo_missing = array();
foreach (array(1, 2, 3, 4) as $n) {
    $combo_missing = array_merge($combo_missing, PAXdesign_Cybercrime_AI_Workflow::missing_for_step($combo_state, $n));
}
ccs_assert(!in_array('phone', $combo_missing, true), 'After saving the phone it must not still be missing');
ccs_assert(!in_array('country', $combo_missing, true), 'After saving the country it must not still be missing');
$combo_snapshot = array('step' => 1, 'missing' => $combo_missing, 'reference_id' => 'CCS-TEST');
$combo_reply = PAXdesign_Cybercrime_AI_Workflow::assistant_copy(
    $combo_snapshot,
    $combo_state,
    'ar',
    false,
    'رقمي +4368181624228 وأنا في النمسا.',
    PAXdesign_Cybercrime_AI_Workflow::extracted_case_fields($combo)
);
ccs_assert($combo_reply !== 'أعطني رقم الهاتف.', 'The assistant must not repeat the phone question after it was answered');
ccs_assert($combo_reply !== 'ما البلد؟', 'The assistant must not ask for country after it was saved in the same turn');
ccs_assert($combo_reply === 'ارفع وثيقة الهوية من زر +.', 'After phone and country, ask only for the next missing identity item');

$english = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('My number is +4368181624228 and I am in Austria.', $state);
ccs_assert(!empty($english['reporter_phone']) && ($english['country_code'] ?? '') === 'AT', 'English phone+country answers must save both fields');

$german = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('Meine Nummer ist +4368181624228 und ich bin in Österreich.', $state);
ccs_assert(!empty($german['reporter_phone']) && ($german['country_code'] ?? '') === 'AT', 'German phone+country answers must save both fields');

$multi = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('Ahmad Ali, ahmad@test.com, +43680111222, Austria', array(
    'full_name' => '', 'email' => '', 'phone' => '', 'phone_digits' => '', 'country' => '', 'country_code' => '',
    'identity_document' => false, 'identity_accuracy' => false, 'category' => '', 'incident_date' => '', 'incident_at' => '',
    'platforms' => '', 'description' => '', 'has_evidence' => false, 'decl_truthful' => false, 'decl_false_reports' => false,
    'decl_verification' => false, 'status' => 'draft',
));
ccs_assert(($multi['reporter_name'] ?? '') !== '', 'A combined identity sentence must save the name');
ccs_assert(($multi['reporter_email'] ?? '') === 'ahmad@test.com', 'A combined identity sentence must save the email');
ccs_assert(!empty($multi['reporter_phone']), 'A combined identity sentence must save the phone');
ccs_assert(($multi['country_code'] ?? '') === 'AT', 'A combined identity sentence must save the country');

$after_phone = $state;
$after_phone['phone'] = '+43680111222';
$after_phone['phone_digits'] = '43680111222';
$correction = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('غير الرقم إلى +43680999888', $after_phone);
ccs_assert(($correction['reporter_phone'] ?? '') === '+43680999888', 'A correction must overwrite the same CCS phone field');

$upload_claim = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('لقد رفعت وثيقة الهوية بالفعل.', $combo_state);
ccs_assert(!empty($upload_claim['identity_upload_claim']), 'An “already uploaded” message must be checked against the real case files');
ccs_assert(PAXdesign_Cybercrime_AI_Workflow::claims_existing_upload('لقد رفعت وثيقة الهوية بالفعل.'), 'Upload claims must be recognized from natural language');

$with_file = $combo_state;
$with_file['identity_document'] = true;
$with_file['identity_files'] = array('id.jpg');
$after_claim_missing = PAXdesign_Cybercrime_AI_Workflow::missing_for_step($with_file, 1);
ccs_assert(!in_array('identity_document', $after_claim_missing, true), 'If the identity file is already on the case, do not ask for it again');

$question = 'لماذا تحتاج رقم الهاتف؟';
ccs_assert(PAXdesign_Cybercrime_AI_Workflow::is_customer_question($question), 'A why-question must be treated as a question, not as a missing field');
ccs_assert(PAXdesign_Cybercrime_AI_Workflow::should_use_model($question, array(), $snapshot, $missing), 'Unparsed questions must go to the model instead of repeating the last prompt');
ccs_assert(!PAXdesign_Cybercrime_AI_Workflow::should_use_model('رقمي +4368181624228 وأنا في النمسا.', $combo, $combo_snapshot, array('phone')), 'A successful extract must stay on the fast next-step path');
ccs_assert(PAXdesign_Cybercrime_AI_Workflow::should_use_model('اسألني عن البلد أولاً', array(), $snapshot, $missing), 'A changed instruction must be understood instead of repeating the previous question');

$nested = PAXdesign_Cybercrime_AI_Workflow::state_from_row(array(
    'reference_id' => 'CCS-TEST',
    'status' => 'draft',
    'reporter_name' => 'Ahmad Test',
    'reporter_email' => 'ahmad@example.com',
    'original_request' => array(
        'reporter_phone' => '+43680111222',
        'reporter_country' => 'Austria',
        'country_code' => 'AT',
    ),
    'payload' => '',
    'attachments' => array(),
));
ccs_assert($nested['phone'] === '+43680111222', 'Formatted CCS rows must still expose the saved phone');
ccs_assert($nested['country_code'] === 'AT', 'Formatted CCS rows must still expose the saved country');
ccs_assert(!in_array('phone', PAXdesign_Cybercrime_AI_Workflow::missing_for_step($nested, 1), true), 'A phone stored under original_request must not be asked again');

$draft_row = array(
    'reference_id' => 'CCS-TEST',
    'status' => 'draft',
    'reporter_name' => 'Ahmad Test',
    'reporter_email' => 'ahmad@example.com',
    'reporter_phone' => '',
    'reporter_country' => '',
    'payload' => '{}',
    'attachments' => '[]',
);
$combo_turn = PAXdesign_Cybercrime_AI_Workflow::decide_turn($draft_row, 'رقمي +4368181624228 وأنا في النمسا.', 'ar', 0);
ccs_assert(is_array($combo_turn), 'A natural-language identity answer must produce a workflow turn');
ccs_assert(!empty($combo_turn['skip_llm']), 'A successful extract must not fall through to a repeated model questionnaire');
ccs_assert(($combo_turn['reply'] ?? '') === 'ارفع وثيقة الهوية من زر +.', 'The turn must advance to the next missing requirement');
ccs_assert(($combo_turn['reply'] ?? '') !== 'أعطني رقم الهاتف.', 'The turn must not repeat the phone question');

$question_turn = PAXdesign_Cybercrime_AI_Workflow::decide_turn($draft_row, 'لماذا تحتاج رقم الهاتف؟', 'ar', 0);
ccs_assert(($question_turn['action'] ?? '') === 'continue', 'A customer question must leave the questionnaire path');
ccs_assert(empty($question_turn['skip_llm']), 'A customer question must be answered by the model, not the previous prompt');

$missing_file_turn = PAXdesign_Cybercrime_AI_Workflow::decide_turn($draft_row, 'لقد رفعت وثيقة الهوية بالفعل.', 'ar', 0);
ccs_assert(strpos((string) ($missing_file_turn['reply'] ?? ''), 'لا أرى وثيقة الهوية') !== false, 'An upload claim must be checked against real files');

$with_id_row = $draft_row;
$with_id_row['reporter_phone'] = '+4368181624228';
$with_id_row['reporter_country'] = 'Austria';
$with_id_row['payload'] = json_encode(array('country_code' => 'AT'));
$with_id_row['attachments'] = json_encode(array(array('field' => 'identity_document', 'original_name' => 'id.jpg')));
$present_file_turn = PAXdesign_Cybercrime_AI_Workflow::decide_turn($with_id_row, 'لقد رفعت وثيقة الهوية بالفعل.', 'ar', 0);
ccs_assert(($present_file_turn['reply'] ?? '') === 'أكد أن بيانات الهوية صحيحة.', 'If the identity file is on the case, ask the next missing item');
ccs_assert(strpos((string) ($present_file_turn['reply'] ?? ''), 'وثيقة الهوية من زر') === false, 'Do not ask to upload an identity file that is already on the case');

$change_turn = PAXdesign_Cybercrime_AI_Workflow::decide_turn($draft_row, 'اسألني عن البلد أولاً', 'ar', 0);
ccs_assert(empty($change_turn['skip_llm']), 'If the customer changes the current question, follow that instruction');

$country_fields = PAXdesign_Cybercrime_AI_Workflow::extract_from_message('النمسا', $after_phone);
ccs_assert(($country_fields['country_code'] ?? '') === 'AT', 'A country-only answer must map onto the same CCS case');
$after_country_missing = PAXdesign_Cybercrime_AI_Workflow::missing_for_step($after_phone, 1);
ccs_assert($after_country_missing[0] === 'country', 'After the phone is saved, country is the next missing item');
$country_snapshot = array('step' => 1, 'missing' => $after_country_missing, 'reference_id' => 'CCS-TEST');
$country_reply = PAXdesign_Cybercrime_AI_Workflow::assistant_copy($country_snapshot, $after_phone, 'ar');
ccs_assert($country_reply === 'ما البلد؟', 'After receiving the phone, the next prompt must ask only for the country');

$ref_reply = PAXdesign_Cybercrime_AI_Workflow::assistant_copy($snapshot, $state, 'ar', false, 'ما رقم المرجع؟');
ccs_assert($ref_reply === 'CCS-TEST', 'Reference questions may return the number; other turns must not');

echo "OK: CCS AI workflow checks passed\n";
