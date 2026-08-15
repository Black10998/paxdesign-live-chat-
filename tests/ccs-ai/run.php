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

$after_phone = $state;
$after_phone['phone'] = '+43680111222';
$after_phone['phone_digits'] = '43680111222';
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
