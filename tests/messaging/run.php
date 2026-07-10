<?php
require __DIR__ . '/bootstrap.php';

function assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function collect_events($channel, $since = 0) {
    $events = array();
    $cursor = $since;
    do {
        $page = PAXdesign_Message_Store::events_since($channel, $cursor, 1000);
        foreach ($page as $event) {
            $events[] = $event;
            $cursor = max($cursor, (int) $event['id']);
        }
    } while (count($page) === 1000);
    return $events;
}

function recreate_schema() {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS test_paxdesign_chat_cursors');
    $wpdb->query('DROP TABLE IF EXISTS test_paxdesign_chat_outbox');
    $wpdb->query('DROP TABLE IF EXISTS test_paxdesign_chat_messages');
    $wpdb->query("CREATE TABLE test_paxdesign_chat_messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        channel VARCHAR(16) NOT NULL DEFAULT 'customer',
        msg_seq BIGINT UNSIGNED NOT NULL,
        client_msg_id VARCHAR(64) NOT NULL,
        role VARCHAR(16) NOT NULL,
        content LONGTEXT NOT NULL,
        meta_json LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY session_seq (session_id, msg_seq),
        UNIQUE KEY session_client (session_id, client_msg_id),
        KEY session_since (session_id, msg_seq)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $wpdb->query("CREATE TABLE test_paxdesign_chat_outbox (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        channel_key VARCHAR(128) NOT NULL,
        event_type VARCHAR(32) NOT NULL,
        payload_json LONGTEXT NOT NULL,
        message_seq BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        KEY channel_event (channel_key, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $wpdb->query("CREATE TABLE test_paxdesign_chat_cursors (
        consumer_key VARCHAR(128) NOT NULL,
        channel_key VARCHAR(128) NOT NULL,
        last_event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_msg_seq BIGINT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (consumer_key, channel_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

recreate_schema();
$session = 'pax_protocol_test';

// Idempotent write acknowledgement: retrying the same client ID returns one row.
$first = PAXdesign_Message_Store::append(
    $session,
    'user',
    'first',
    array('client_msg_id' => 'same-client-id'),
    'customer'
);
$retry = PAXdesign_Message_Store::append(
    $session,
    'user',
    'first',
    array('client_msg_id' => 'same-client-id'),
    'customer'
);
assert_true(!is_wp_error($first) && !is_wp_error($retry), 'Idempotent append failed');
assert_true($first['id'] === $retry['id'], 'Retry returned a different sequence');
assert_true(PAXdesign_Message_Store::count($session) === 1, 'Retry created a duplicate row');

// Concurrent writers: all messages must survive with contiguous server ordering.
$workers = (int) (getenv('PAX_TEST_WORKERS') ?: 8);
$perWorker = (int) (getenv('PAX_TEST_MESSAGES_PER_WORKER') ?: 50);
$processes = array();
$php = PHP_BINARY;
for ($worker = 0; $worker < $workers; $worker++) {
    $command = array($php, __DIR__ . '/worker.php', $session, (string) $worker, (string) $perWorker);
    $pipes = array();
    $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    assert_true(is_resource($process), 'Could not start concurrency worker');
    $processes[] = array($process, $pipes);
}
foreach ($processes as [$process, $pipes]) {
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    assert_true($code === 0, "Worker failed: $stdout $stderr");
}

$expected = 1 + ($workers * $perWorker);
$all = PAXdesign_Message_Store::all_messages($session);
assert_true(count($all) === $expected, 'Concurrent writes were lost');
foreach ($all as $index => $message) {
    assert_true($message['id'] === $index + 1, 'Message sequence contains a gap or reorder');
}

// Durable outbox replay: reconnect from cursor must return every later event once.
$events = collect_events('session:' . $session);
assert_true(count($events) === $expected, 'Outbox event count does not match committed messages');
$cursor = $events[199]['id'];
$replay = collect_events('session:' . $session, $cursor);
assert_true(count($replay) === $expected - 200, 'Reconnect replay lost or duplicated events');
assert_true($replay[0]['id'] > $cursor, 'Reconnect replay did not honor exclusive cursor');

// A committed message without its outbox event is forbidden.
$beforeFailure = PAXdesign_Message_Store::count($session);
$wpdb->failOutboxInsert = true;
$failed = PAXdesign_Message_Store::append(
    $session,
    'user',
    'must rollback',
    array('client_msg_id' => 'outbox-failure'),
    'customer'
);
$wpdb->failOutboxInsert = false;
assert_true(is_wp_error($failed), 'Outbox failure was incorrectly acknowledged');
assert_true(PAXdesign_Message_Store::count($session) === $beforeFailure, 'Message committed without outbox event');

// Delivery/read acknowledgement is monotonic even if stale acknowledgements arrive later.
PAXdesign_Message_Store::acknowledge('device:test', 'session:' . $session, 400, 250);
PAXdesign_Message_Store::acknowledge('device:test', 'session:' . $session, 100, 50);
global $wpdb;
$ack = $wpdb->get_row(
    "SELECT last_event_id, last_msg_seq FROM test_paxdesign_chat_cursors
     WHERE consumer_key='device:test' AND channel_key='session:$session'"
);
assert_true((int) $ack->last_event_id === 400, 'Event acknowledgement moved backwards');
assert_true((int) $ack->last_msg_seq === 250, 'Read acknowledgement moved backwards');

// Full history must not truncate long conversations.
$longSession = 'pax_long_history';
for ($i = 0; $i < 2105; $i++) {
    $message = PAXdesign_Message_Store::append(
        $longSession,
        'user',
        "long-$i",
        array('client_msg_id' => "long:$i"),
        'customer'
    );
    assert_true(!is_wp_error($message), 'Long-history append failed');
}
assert_true(count(PAXdesign_Message_Store::all_messages($longSession)) === 2105, 'Full history was truncated');

$elapsedStart = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    PAXdesign_Message_Store::events_since('session:' . $session, max(0, $expected - 25), 100);
}
$elapsed = microtime(true) - $elapsedStart;
assert_true($elapsed < 5.0, 'Indexed reconnect replay is unexpectedly slow');

echo json_encode(array(
    'status' => 'ok',
    'messages' => $expected,
    'workers' => $workers,
    'outbox_events' => count($events),
    'long_history_messages' => 2105,
    'replay_queries' => 1000,
    'replay_seconds' => round($elapsed, 3),
), JSON_PRETTY_PRINT) . PHP_EOL;
