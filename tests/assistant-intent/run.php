<?php
/**
 * Unit checks for customer-assistant intent detection (no WordPress required).
 */

function ai_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__, 2);
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

require_once $root . '/paxdesign-booking/includes/class-paxdesign-chat-intent.php';

echo "Assistant intent checks\n";

$cases = array(
    array('What is the request I submitted?', '', PAXdesign_Chat_Intent::ACCOUNT_REQUEST),
    array('What is the status of the request I submitted?', '', PAXdesign_Chat_Intent::ACCOUNT_STATUS),
    array('Was ist die Anfrage, die ich gestellt habe?', '', PAXdesign_Chat_Intent::ACCOUNT_REQUEST),
    array('Was ist der Status meiner Anfrage?', '', PAXdesign_Chat_Intent::ACCOUNT_STATUS),
    array('ما هو الطلب الذي قدمته؟', '', PAXdesign_Chat_Intent::ACCOUNT_REQUEST),
    array('ما هي حالة طلبي؟', '', PAXdesign_Chat_Intent::ACCOUNT_STATUS),
    array('When is my appointment?', '', PAXdesign_Chat_Intent::APPOINTMENT),
    array('Where is my invoice?', '', PAXdesign_Chat_Intent::INVOICE),
    array('What is the status of my project?', '', PAXdesign_Chat_Intent::PROJECT),
    array('What is the status of my Cybercrime report?', '', PAXdesign_Chat_Intent::CCS_STATUS),
    array('I want to submit a request for a new website', '', PAXdesign_Chat_Intent::GENERAL),
    array('and the status?', "ASSISTANT: Your request ORD-123 Website is received.\nUSER: and the status?", PAXdesign_Chat_Intent::ACCOUNT_STATUS),
);

foreach ($cases as $case) {
    $got = PAXdesign_Chat_Intent::detect($case[0], $case[1]);
    ai_assert($got === $case[2], 'Intent for "' . $case[0] . '" expected ' . $case[2] . ', got ' . $got);
}

ai_assert(PAXdesign_Chat_Intent::is_account_lookup(PAXdesign_Chat_Intent::ACCOUNT_REQUEST), 'submitted-request intent is an account lookup');
ai_assert(PAXdesign_Chat_Intent::is_account_lookup(PAXdesign_Chat_Intent::ACCOUNT_STATUS), 'status intent is an account lookup');
ai_assert(!PAXdesign_Chat_Intent::is_account_lookup(PAXdesign_Chat_Intent::GENERAL), 'general is not an account lookup');

$rules = PAXdesign_Chat_Intent::operating_rules_block();
ai_assert(strpos($rules, 'Never echo or rephrase the question') !== false, 'operating rules forbid echoing');
ai_assert(strpos($rules, 'What is your request?') !== false, 'operating rules forbid asking what the request is');

$logged_in = PAXdesign_Chat_Intent::instruction_block(PAXdesign_Chat_Intent::ACCOUNT_REQUEST, true);
ai_assert(strpos($logged_in, 'Do not ask them what the request was') !== false, 'logged-in request intent must not re-ask');

$guest = PAXdesign_Chat_Intent::instruction_block(PAXdesign_Chat_Intent::ACCOUNT_STATUS, false);
ai_assert(strpos($guest, 'not logged in') !== false, 'guest status lookup must require sign-in instead of inventing data');

$chat = file_get_contents($root . '/paxdesign-booking/includes/class-paxdesign-chat.php');
ai_assert(strpos($chat, 'conversation_prompt_inputs') !== false, 'chat must extract latest user message for intent');
ai_assert(strpos($chat, 'PAXdesign_Chat_Intent::detect') !== false, 'chat system prompt must run intent detection');
ai_assert(strpos($chat, 'complete_authenticated_customer_chat') !== false, 'iOS/app path must keep the same backend completion method');
ai_assert(strpos($chat, 'stream_authenticated_customer_chat') !== false, 'website path must keep the same backend stream method');

$knowledge = file_get_contents($root . '/paxdesign-booking/includes/class-paxdesign-chat-knowledge.php');
ai_assert(strpos($knowledge, 'request details:') !== false, 'account context must include submitted request details');
ai_assert(strpos($knowledge, 'the items below ARE that request') !== false, 'account context must treat listed items as the submitted request');
ai_assert(strpos($knowledge, 'Worum geht es?') !== false, 'live-agent prompt must not qualify when the request is already known');

$boot = file_get_contents($root . '/paxdesign-booking/paxdesign-booking.php');
ai_assert(strpos($boot, 'class-paxdesign-chat-intent.php') !== false, 'plugin bootstrap must load intent detection');

$overlay = $root . '/deploy-patches/restored-chat-human-ui/includes/class-paxdesign-chat-intent.php';
ai_assert(is_file($overlay), 'overlay must include intent detection for production deploy');
ai_assert(md5_file($overlay) === md5_file($root . '/paxdesign-booking/includes/class-paxdesign-chat-intent.php'), 'overlay intent file must match plugin');

echo "OK: assistant intent checks passed\n";
