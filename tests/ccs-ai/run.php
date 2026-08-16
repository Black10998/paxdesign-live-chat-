<?php
/**
 * CCS checks for the live 3.174.91 baseline.
 * The later GitHub CCS AI form-fill workflow is not part of production.
 */

function ccs_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

echo "CCS baseline checks\n";

$root = dirname(__DIR__, 2) . '/paxdesign-booking';
$includes = $root . '/includes';

foreach (array(
    'class-paxdesign-cybercrime-ai-workflow.php',
    'class-paxdesign-cybercrime-ai-operations.php',
    'class-paxdesign-cybercrime-ai-case.php',
) as $file) {
    ccs_assert(!is_file($includes . '/' . $file), $file . ' must not exist on the production baseline');
}

$boot = file_get_contents($root . '/paxdesign-booking.php');
ccs_assert(strpos($boot, 'class-paxdesign-cybercrime-i18n.php') !== false, 'compact CCS i18n must be loaded');
ccs_assert(strpos($boot, 'class-paxdesign-cybercrime-ai-workflow.php') === false, 'bootstrap must not require CCS AI workflow');

$knowledge = file_get_contents($includes . '/class-paxdesign-chat-knowledge.php');
ccs_assert(strpos($knowledge, 'ONE clear step at a time') !== false, 'CCS assistant must guide one step at a time');
ccs_assert(strpos($knowledge, '1 Identity → 2 Incident → 3 Evidence → 4 Review') !== false, 'CCS steps must match the website form');
ccs_assert(strpos($knowledge, 'do not restart the form') !== false, 'CCS status questions must not restart the form');

$tickets = file_get_contents($includes . '/class-paxdesign-cybercrime-tickets.php');
ccs_assert(strpos($tickets, "'rejected'") !== false, 'tickets must include rejected status');

$i18n = file_get_contents($includes . '/class-paxdesign-cybercrime-i18n.php');
ccs_assert(strpos($i18n, 'مرفوض') !== false, 'rejected label must be Arabic مرفوض');

$admin = file_get_contents($root . '/includes/customer/class-paxdesign-customer-admin.php');
ccs_assert(strpos($admin, 'pax-cc-reject-ticket') !== false, 'admin must expose the مرفوض action');

echo "OK: CCS baseline checks passed\n";
