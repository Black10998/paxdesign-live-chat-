<?php
/**
 * Update the WordPress OpenAI API key from PAX_OPENAI_API_KEY (never commit keys).
 *
 * Usage (production):
 *   PAX_OPENAI_API_KEY='sk-...' wp eval-file wp-content/plugins/paxdesign-booking/scripts/wp-eval-update-openai-key.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via wp eval-file inside WordPress.\n");
    exit(1);
}

$raw = getenv('PAX_OPENAI_API_KEY');
if (!is_string($raw) || trim($raw) === '') {
    fwrite(STDERR, "Missing PAX_OPENAI_API_KEY environment variable.\n");
    exit(1);
}

$key = sanitize_text_field(trim($raw));
if ($key === '') {
    fwrite(STDERR, "OpenAI API key is empty after sanitization.\n");
    exit(1);
}

update_option('paxdesign_chat_openai_key', $key, false);
update_option('paxdesign_chat_enabled', '1', false);
delete_option('paxdesign_chat_last_error');

if (class_exists('PAXdesign_Chat')) {
    $test = PAXdesign_Chat::get_instance()->test_openai_connection();
    if (is_wp_error($test)) {
        fwrite(STDERR, 'OpenAI verification failed: ' . $test->get_error_message() . "\n");
        exit(1);
    }
    echo 'OK: OpenAI key updated and verified (model: ' . esc_html((string) ($test['model'] ?? 'unknown')) . ")\n";
    exit(0);
}

echo "OK: OpenAI key updated (chat class unavailable for live test).\n";
