<?php
require __DIR__ . '/bootstrap.php';

$session = $argv[1] ?? '';
$worker = (int) ($argv[2] ?? 0);
$count = (int) ($argv[3] ?? 0);

for ($i = 0; $i < $count; $i++) {
    $clientId = sprintf('worker:%d:%d', $worker, $i);
    $message = PAXdesign_Message_Store::append(
        $session,
        'user',
        "worker-$worker-message-$i",
        array('client_msg_id' => $clientId),
        'customer'
    );
    if (is_wp_error($message)) {
        fwrite(STDERR, $message->get_error_code() . ': ' . $message->get_error_message() . PHP_EOL);
        exit(1);
    }
}
