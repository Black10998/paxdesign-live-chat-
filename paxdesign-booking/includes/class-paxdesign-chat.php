<?php
/**
 * PAXdesign AI Chat Assistant
 *
 * Secure server-side chat proxy with rate limiting, input validation,
 * and optional Cloudflare Worker backend. Extensible architecture for
 * future CRM, booking, and knowledge-base integrations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Chat {

    const RATE_LIMIT_WINDOW = 60;
    const RATE_LIMIT_MAX    = 15;
    const MAX_MESSAGE_LEN   = 2000;
    const MAX_MESSAGES      = 30;

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_paxdesign_chat', array($this, 'handle_chat'));
        add_action('wp_ajax_nopriv_paxdesign_chat', array($this, 'handle_chat'));
        add_action('wp_ajax_paxdesign_test_openai', array($this, 'handle_test_openai'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function is_enabled() {
        return (bool) get_option('paxdesign_chat_enabled', true);
    }

    public function enqueue_assets() {
        if (!$this->is_enabled() || is_admin()) {
            return;
        }

        wp_enqueue_script(
            'paxdesign-chat-script',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/chat-script.js',
            array('paxdesign-booking-script'),
            PAXDESIGN_BOOKING_VERSION,
            true
        );

        wp_localize_script('paxdesign-chat-script', 'paxdesignChat', array(
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'restUrl'          => rest_url('paxdesign/v1/chat'),
            'nonce'            => wp_create_nonce('paxdesign_chat_nonce'),
            'enabled'          => true,
            'quickActions'     => $this->get_quick_actions(),
            'contactUrl'       => get_option('paxdesign_booking_contact_url', home_url('/')),
            'phone'            => $this->get_contact_phone(),
            'email'            => $this->get_contact_email(),
            'greeting'         => $this->get_greeting(),
            'ctaText'          => $this->get_cta_text(),
            'autoBooking'      => $this->is_auto_booking_enabled(),
            'showPrices'       => $this->should_show_prices(),
            'serviceNameMap'   => PAXdesign_Chat_Knowledge::get_booking_name_map(),
            'bookingServices'  => array_values(array_map(function ($s) {
                return $s['booking_name'];
            }, PAXdesign_Chat_Knowledge::get_service_catalog())),
            'liveAgent'        => PAXdesign_Chat_Live::get_agent_public_config(),
            'customerAgentLabel' => 'Live Chat',
            'liveEntryPrompt'    => 'Möchten Sie mit einem Live-Agent chatten?',
            'sounds'             => array(
                'typing'    => 'https://paxdesign.at/wp-content/uploads/2026/06/freesound_community-writing-a-text-message-41141.mp3',
                'openClose' => 'https://paxdesign.at/wp-content/uploads/2026/06/u_8e8ungop1x-intro_cinematic-270840.mp3',
            ),
        ));
    }

    public function get_quick_actions() {
        $cta = $this->get_cta_text();
        return array(
            array(
                'label'   => 'Live Chat',
                'message' => 'Ich möchte mit einem Live-Agent sprechen.',
                'intent'  => 'live',
            ),
            array(
                'label'   => 'Website erstellen',
                'message' => 'Ich möchte eine Website erstellen lassen.',
            ),
            array(
                'label'   => 'AI Chatbot',
                'message' => 'Ich interessiere mich für einen AI Chatbot.',
            ),
            array(
                'label'   => 'Termin vereinbaren',
                'message' => 'Ich möchte einen Termin zur kostenlosen Erstberatung vereinbaren.',
                'intent'  => 'booking',
            ),
            array(
                'label'   => 'Leistungen',
                'message' => 'Welche Leistungen bietet PAXDesign an?',
            ),
            array(
                'label'   => 'Website beschleunigen',
                'message' => 'Meine Website ist langsam. Was kann PAXDesign tun?',
            ),
            array(
                'label'   => 'IT-Sicherheit',
                'message' => 'Ich möchte die IT-Sicherheit verbessern.',
            ),
            array(
                'label'   => 'App entwickeln',
                'message' => 'Ich möchte eine mobile App entwickeln lassen.',
            ),
            array(
                'label'   => $cta,
                'message' => 'Ich möchte ' . strtolower($cta) . '.',
                'intent'  => 'booking',
            ),
        );
    }

    public function get_greeting() {
        $greeting = trim(get_option('paxdesign_chat_greeting', ''));
        if ($greeting !== '') {
            return $greeting;
        }
        return 'Hallo! Ich bin der PAXDesign KI-Assistent. Wie kann ich Ihnen bei Ihrem digitalen Projekt helfen?';
    }

    public function get_cta_text() {
        $cta = trim(get_option('paxdesign_chat_cta_text', ''));
        return $cta !== '' ? $cta : 'Kostenlose Erstberatung buchen';
    }

    public function get_contact_phone() {
        $phone = trim(get_option('paxdesign_chat_phone', ''));
        if ($phone !== '') {
            return $phone;
        }
        return get_option('paxdesign_booking_phone', '+43 681 20543638');
    }

    public function get_contact_email() {
        $email = trim(get_option('paxdesign_chat_email', ''));
        if ($email !== '') {
            return $email;
        }
        return get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
    }

    public function should_show_prices() {
        return (bool) get_option('paxdesign_chat_show_prices', false);
    }

    public function is_auto_booking_enabled() {
        return (bool) get_option('paxdesign_chat_auto_booking', true);
    }

    public function get_chat_settings_for_prompt() {
        return array(
            'phone'            => $this->get_contact_phone(),
            'email'            => $this->get_contact_email(),
            'response_style'   => trim(get_option('paxdesign_chat_response_style', '')),
            'show_prices'      => $this->should_show_prices(),
            'auto_booking'     => $this->is_auto_booking_enabled(),
            'cta_text'         => $this->get_cta_text(),
            'primary_services' => trim(get_option('paxdesign_chat_primary_services', '')),
            'price_hints'      => trim(get_option('paxdesign_chat_price_hints', '')),
        );
    }

    public function get_system_prompt() {
        return PAXdesign_Chat_Knowledge::build_system_prompt($this->get_chat_settings_for_prompt());
    }

    public function register_rest_routes() {
        register_rest_route('paxdesign/v1', '/chat', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_handle_chat'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
    }

    public function rest_permission_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('forbidden', 'Invalid nonce', array('status' => 403));
        }
        return true;
    }

    public function rest_handle_chat($request) {
        $body = $request->get_json_params();
        return $this->process_chat_request($body, $request);
    }

    public function handle_chat() {
        if (!$this->is_enabled()) {
            wp_send_json_error(array('message' => 'Chat ist derzeit nicht verfügbar.'), 503);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'paxdesign_chat_nonce')) {
            status_header(403);
            wp_send_json_error(array('message' => 'Sitzung abgelaufen. Bitte laden Sie die Seite neu.'), 403);
        }

        $honeypot = isset($_POST['website']) ? sanitize_text_field(wp_unslash($_POST['website'])) : '';
        if (!empty($honeypot)) {
            wp_send_json_error(array('message' => 'Anfrage abgelehnt.'), 403);
        }

        $messages_raw = isset($_POST['messages']) ? wp_unslash($_POST['messages']) : '';
        $messages     = json_decode($messages_raw, true);

        if (!is_array($messages)) {
            wp_send_json_error(array('message' => 'Ungültige Anfrage.'), 400);
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        if ($session_id !== '' && preg_match('/^pax_[a-z0-9_]+$/i', $session_id)) {
            $live = PAXdesign_Chat_Live::get_instance();
            if ($live->is_ai_blocked($session_id)) {
                status_header(409);
                $handler = $live->get_handler($session_id);
                $message = 'Ein PAXDesign-Mitarbeiter übernimmt diesen Chat. Bitte warten Sie auf die Antwort.';
                if ($handler === PAXdesign_Chat_Live::HANDLER_LIVE) {
                    $message = 'Ihre Anfrage wurde weitergeleitet. Ein Mitarbeiter meldet sich in Kürze im Chat.';
                } elseif ($handler === PAXdesign_Chat_Live::HANDLER_CLOSED) {
                    $message = 'Dieser Chat wurde geschlossen.';
                }
                wp_send_json_error(array('message' => $message));
            }
        }

        $this->stream_chat_response($messages);
    }

    /**
     * Admin AJAX: test OpenAI connectivity without exposing secrets.
     */
    public function handle_test_openai() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $result = $this->test_openai_connection();
        if (is_wp_error($result)) {
            update_option('paxdesign_chat_last_error', $result->get_error_message(), false);
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        delete_option('paxdesign_chat_last_error');
        update_option('paxdesign_chat_last_model', $result['model'], false);
        update_option('paxdesign_chat_last_test', time(), false);

        wp_send_json_success(array(
            'message' => $result['message'],
            'model'   => $result['model'],
        ));
    }

    private function process_chat_request($body, $request = null) {
        if (!$this->is_enabled()) {
            return new WP_Error('disabled', 'Chat ist derzeit nicht verfügbar.', array('status' => 503));
        }

        $messages = isset($body['messages']) ? $body['messages'] : array();
        if (!is_array($messages)) {
            return new WP_Error('invalid', 'Ungültige Anfrage.', array('status' => 400));
        }

        $validated = $this->validate_messages($messages);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $client_ip = $this->get_client_ip();
        if (!$this->check_rate_limit($client_ip)) {
            return new WP_Error('rate_limit', 'Zu viele Anfragen. Bitte warten Sie einen Moment.', array('status' => 429));
        }

        return array('ok' => true);
    }

    private function stream_chat_response($messages) {
        $validated = $this->validate_messages($messages);
        if (is_wp_error($validated)) {
            status_header(400);
            wp_send_json_error(array('message' => $validated->get_error_message()));
        }

        $client_ip = $this->get_client_ip();
        if (!$this->check_rate_limit($client_ip)) {
            status_header(429);
            wp_send_json_error(array('message' => 'Zu viele Anfragen. Bitte warten Sie einen Moment.'));
        }

        $worker_url = trim(get_option('paxdesign_chat_worker_url', ''));
        if (!empty($worker_url)) {
            $this->proxy_to_worker($worker_url, $validated);
            return;
        }

        $api_key = $this->get_openai_api_key();

        if (empty($api_key)) {
            status_header(503);
            wp_send_json_error(array('message' => 'Chat ist derzeit nicht konfiguriert.'));
        }

        $this->stream_openai_response($api_key, $validated);
    }

    private function validate_messages($messages) {
        if (count($messages) > self::MAX_MESSAGES) {
            return new WP_Error('too_many', 'Zu viele Nachrichten in der Konversation.');
        }

        $validated = array();
        foreach ($messages as $msg) {
            if (!isset($msg['role']) || !isset($msg['content'])) {
                continue;
            }

            $role = sanitize_text_field($msg['role']);
            if (!in_array($role, array('user', 'assistant', 'system'), true)) {
                continue;
            }

            $content = sanitize_textarea_field($msg['content']);
            if (empty($content)) {
                continue;
            }

            if (mb_strlen($content) > self::MAX_MESSAGE_LEN) {
                $content = mb_substr($content, 0, self::MAX_MESSAGE_LEN);
            }

            $validated[] = array('role' => $role, 'content' => $content);
        }

        if (empty($validated)) {
            return new WP_Error('empty', 'Keine gültige Nachricht.');
        }

        return $validated;
    }

    private function check_rate_limit($ip) {
        $key  = 'paxdesign_chat_rl_' . md5($ip);
        $data = get_transient($key);

        if ($data === false) {
            set_transient($key, array('count' => 1, 'start' => time()), self::RATE_LIMIT_WINDOW);
            return true;
        }

        if ($data['count'] >= self::RATE_LIMIT_MAX) {
            return false;
        }

        $data['count']++;
        set_transient($key, $data, self::RATE_LIMIT_WINDOW);
        return true;
    }

    private function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return $ip ?: 'unknown';
    }

    private function proxy_to_worker($worker_url, $messages) {
        $secret = get_option('paxdesign_chat_worker_secret', '');

        $headers = array(
            'Content-Type'  => 'application/json',
            'Accept'        => 'text/event-stream',
        );
        if (!empty($secret)) {
            $headers['X-PAX-Chat-Token'] = $secret;
        }

        $worker_messages = array_merge(
            array(array('role' => 'system', 'content' => $this->get_system_prompt())),
            array_values(array_filter($this->trim_conversation_history($messages), function ($msg) {
                return $msg['role'] !== 'system';
            }))
        );

        $payload = wp_json_encode(array(
            'messages' => $worker_messages,
        ));

        $this->send_sse_headers();

        $ch = curl_init(rtrim($worker_url, '/') . '/agents/ChatAgent/default');
        if (!$ch) {
            echo "data: " . wp_json_encode(array('error' => 'Verbindungsfehler.')) . "\n\n";
            exit;
        }

        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $this->curl_headers($headers),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
                echo $data;
                if (function_exists('ob_flush')) {
                    ob_flush();
                }
                flush();
                return strlen($data);
            },
        ));

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 400) {
            $this->log_event('worker_error', array('code' => $http_code));
        }
        exit;
    }

    private function stream_openai_response($api_key, $messages) {
        if (!function_exists('curl_init')) {
            status_header(503);
            wp_send_json_error(array('message' => 'Chat-Server unterstützt keine Streaming-Verbindung (cURL fehlt).'));
        }

        $openai_messages = array(
            array('role' => 'system', 'content' => $this->get_system_prompt()),
        );
        foreach ($messages as $msg) {
            if ($msg['role'] !== 'system') {
                $openai_messages[] = $msg;
            }
        }

        $openai_messages = $this->trim_conversation_history($openai_messages);

        $this->send_sse_headers();

        $models     = $this->get_model_candidates();
        $last_error = 'Keine Antwort vom KI-Backend erhalten.';

        foreach ($models as $model) {
            $state = array(
                'line_buffer' => '',
                'has_content' => false,
                'api_error'   => '',
            );

            $payload = wp_json_encode($this->build_openai_payload($model, $openai_messages));
            if ($payload === false) {
                continue;
            }

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            if (!$ch) {
                $last_error = 'Verbindung zum KI-Dienst konnte nicht hergestellt werden.';
                continue;
            }

            curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $this->curl_headers(array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                )),
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TCP_NODELAY    => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_WRITEFUNCTION  => function ($handle, $data) use (&$state) {
                    return $this->process_openai_stream_chunk($data, $state);
                },
            ));

            curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($state['has_content']) {
                update_option('paxdesign_chat_last_model', $model, false);
                delete_option('paxdesign_chat_last_error');
                update_option('paxdesign_chat_last_test', time(), false);
                $this->log_event('openai_success', array('model' => $model));
                echo "data: [DONE]\n\n";
                exit;
            }

            $last_error = $this->resolve_openai_failure_message($http_code, $state, $curl_error, $model);
            $this->log_event('openai_model_failed', array(
                'model'    => $model,
                'http'     => $http_code,
                'error'    => $last_error,
                'curl'     => $curl_error,
            ));

            if ($http_code === 401) {
                break;
            }
        }

        update_option('paxdesign_chat_last_error', $last_error, false);
        echo 'data: ' . wp_json_encode(array('type' => 'error', 'message' => $last_error)) . "\n\n";
        echo "data: [DONE]\n\n";
        exit;
    }

    /**
     * Process one OpenAI SSE chunk with line buffering.
     *
     * @param string $data Raw chunk from cURL.
     * @param array  $state Stream state (by reference).
     * @return int Bytes processed.
     */
    private function process_openai_stream_chunk($data, &$state) {
        $state['line_buffer'] .= $data;

        while (($newline = strpos($state['line_buffer'], "\n")) !== false) {
            $line = trim(substr($state['line_buffer'], 0, $newline));
            $state['line_buffer'] = substr($state['line_buffer'], $newline + 1);

            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }

            if (strpos($line, 'data: ') !== 0) {
                continue;
            }

            $json = json_decode(substr($line, 6), true);
            if (!is_array($json)) {
                continue;
            }

            if (!empty($json['error']['message'])) {
                $state['api_error'] = sanitize_text_field($json['error']['message']);
                continue;
            }

            $choice = isset($json['choices'][0]) ? $json['choices'][0] : array();
            $delta  = isset($choice['delta']) ? $choice['delta'] : array();
            $text   = isset($delta['content']) ? (string) $delta['content'] : '';

            if ($text !== '') {
                $state['has_content'] = true;
                echo 'data: ' . wp_json_encode(array('type' => 'text', 'text' => $text)) . "\n\n";
                $this->flush_sse_output();
            }

            if (isset($choice['finish_reason']) && $choice['finish_reason'] === 'length' && !$state['has_content']) {
                $state['api_error'] = 'Token-Limit erreicht, bevor eine Antwort generiert wurde. Bitte ein anderes Modell wählen.';
            }
        }

        return strlen($data);
    }

    /**
     * Build OpenAI chat/completions payload for a model.
     */
    private function build_openai_payload($model, $messages) {
        $payload = array(
            'model'                 => $model,
            'messages'              => $messages,
            'stream'                => true,
            'max_completion_tokens' => $this->get_max_completion_tokens($model),
        );

        if (!$this->is_reasoning_model($model)) {
            $payload['temperature'] = 0.7;
        }

        return $payload;
    }

    /**
     * Models to try in order (configured model first, then safe fallbacks).
     */
    private function get_model_candidates() {
        $primary = sanitize_text_field(get_option('paxdesign_chat_model', 'gpt-4o'));
        if ($primary === '') {
            $primary = 'gpt-4o';
        }

        $candidates = array('gpt-4o-mini');
        if ($primary !== 'gpt-4o-mini') {
            $candidates[] = $primary;
        }
        foreach (array('gpt-4o') as $fallback) {
            if (!in_array($fallback, $candidates, true)) {
                $candidates[] = $fallback;
            }
        }

        return $candidates;
    }

    private function get_max_completion_tokens($model) {
        if ($this->is_reasoning_model($model)) {
            return 4096;
        }
        return 768;
    }

    /**
     * Keep recent turns only — smaller payloads reach the model faster.
     */
    private function trim_conversation_history($messages, $max_turns = 12) {
        if (!is_array($messages) || count($messages) <= $max_turns) {
            return $messages;
        }

        $system = array();
        $rest   = array();
        foreach ($messages as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'system') {
                $system[] = $msg;
            } else {
                $rest[] = $msg;
            }
        }

        if (count($rest) <= $max_turns) {
            return array_merge($system, $rest);
        }

        return array_merge($system, array_slice($rest, -$max_turns));
    }

    private function is_reasoning_model($model) {
        return (bool) preg_match('/^(gpt-5|o\d)/i', (string) $model);
    }

    private function get_openai_api_key() {
        $api_key = trim(get_option('paxdesign_chat_openai_key', ''));
        if ($api_key === '' && defined('PAXDESIGN_OPENAI_API_KEY') && PAXDESIGN_OPENAI_API_KEY) {
            $api_key = PAXDESIGN_OPENAI_API_KEY;
        }
        return $api_key;
    }

    /**
     * Non-streaming connectivity test for admin UI.
     *
     * @return array|WP_Error
     */
    public function test_openai_connection() {
        $api_key = $this->get_openai_api_key();
        if ($api_key === '') {
            return new WP_Error('missing_key', 'Kein OpenAI API Key hinterlegt.');
        }

        if (!function_exists('curl_init')) {
            return new WP_Error('missing_curl', 'cURL ist auf dem Server nicht verfügbar.');
        }

        $models = $this->get_model_candidates();
        $last_error = 'OpenAI-Verbindung fehlgeschlagen.';

        foreach ($models as $model) {
            $payload = wp_json_encode(array(
                'model'                 => $model,
                'messages'              => array(
                    array('role' => 'user', 'content' => 'Antworte nur mit dem Wort OK.'),
                ),
                'max_completion_tokens' => $this->is_reasoning_model($model) ? 512 : 32,
            ));

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => $payload,
            ));

            if (is_wp_error($response)) {
                $last_error = 'Verbindungsfehler: ' . $response->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code >= 200 && $code < 300 && is_array($body)) {
                $content = isset($body['choices'][0]['message']['content'])
                    ? trim((string) $body['choices'][0]['message']['content'])
                    : '';

                if ($content !== '') {
                    return array(
                        'model'   => $model,
                        'message' => sprintf('Verbindung erfolgreich. Modell „%s“ antwortet.', $model),
                    );
                }

                $last_error = sprintf(
                    'Modell „%s“ antwortete ohne Text (evtl. Token-Budget zu niedrig). Fallback wird versucht.',
                    $model
                );
                continue;
            }

            $last_error = $this->format_openai_error_message($code, $body, $model);
            if ($code === 401) {
                break;
            }
        }

        return new WP_Error('openai_failed', $last_error);
    }

    /**
     * Generate 2–3 short reply suggestions for a live admin agent (never auto-sent).
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed>             $target_message
     * @param array<string, string>            $context
     * @return array<int, string>|WP_Error
     */
    public function generate_admin_reply_suggestions($messages, $target_message, $context = array()) {
        $api_key = $this->get_openai_api_key();
        if ($api_key === '') {
            return new WP_Error('missing_key', 'Kein OpenAI API Key hinterlegt.');
        }

        $customer_text = isset($target_message['content']) ? trim((string) $target_message['content']) : '';
        if ($customer_text === '' && empty($target_message['image_url'])) {
            return new WP_Error('empty_message', 'Keine Kundennachricht zum Analysieren.');
        }

        $history = array();
        foreach ($messages as $msg) {
            if (!is_array($msg) || empty($msg['role']) || empty($msg['content'])) {
                continue;
            }
            if ($msg['role'] === 'system') {
                continue;
            }
            if (isset($msg['id']) && (int) $msg['id'] === (int) ($target_message['id'] ?? 0)) {
                break;
            }
            $history[] = array(
                'role'    => $msg['role'] === 'admin' ? 'assistant' : $msg['role'],
                'content' => (string) $msg['content'],
            );
        }
        $history = $this->trim_conversation_history($history, 10);

        $service = isset($context['service']) ? trim((string) $context['service']) : '';
        $customer_name = isset($context['customer_name']) ? trim((string) $context['customer_name']) : '';

        $system = 'Du bist ein stiller Assistent für den Live-Support-Mitarbeiter von PAXdesign (Webdesign, SEO, Buchungssysteme). '
            . 'Der Mitarbeiter schreibt selbst — du sendest NIEMALS Nachrichten an den Kunden. '
            . 'Erstelle genau 2–3 kurze, professionelle Antwortvorschläge auf die letzte Kundennachricht. '
            . 'Antworte in der Sprache des Kunden (Deutsch oder Arabisch). '
            . 'Jeder Vorschlag max. 2 Sätze, direkt sendbar, freundlich und konkret. '
            . 'Antworte NUR als JSON: {"suggestions":["…","…"]} ohne Markdown.';

        $user_parts = array();
        if ($customer_name !== '') {
            $user_parts[] = 'Kundenname: ' . $customer_name;
        }
        if ($service !== '') {
            $user_parts[] = 'Erkanntes Thema: ' . $service;
        }
        if (!empty($history)) {
            $user_parts[] = 'Bisheriger Verlauf (Auszug):';
            foreach ($history as $turn) {
                $label = $turn['role'] === 'user' ? 'Kunde' : 'Support';
                $user_parts[] = $label . ': ' . $turn['content'];
            }
        }
        $user_parts[] = 'Letzte Kundennachricht (Antwortvorschläge dafür): ' . ($customer_text !== '' ? $customer_text : '[Bild/Foto ohne Text]');

        $openai_messages = array(
            array('role' => 'system', 'content' => $system),
            array('role' => 'user', 'content' => implode("\n", $user_parts)),
        );

        $models = array('gpt-4o-mini');
        foreach ($this->get_model_candidates() as $candidate) {
            if (!in_array($candidate, $models, true)) {
                $models[] = $candidate;
            }
        }

        $last_error = 'KI-Vorschläge konnten nicht generiert werden.';
        foreach ($models as $model) {
            $payload_data = array(
                'model'                 => $model,
                'messages'              => $openai_messages,
                'max_completion_tokens' => $this->is_reasoning_model($model) ? 1024 : 512,
                'response_format'       => array('type' => 'json_object'),
            );
            if (!$this->is_reasoning_model($model)) {
                $payload_data['temperature'] = 0.6;
            }
            $payload = wp_json_encode($payload_data);
            if ($payload === false) {
                continue;
            }

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'timeout' => 25,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => $payload,
            ));

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code < 200 || $code >= 300 || !is_array($body)) {
                $last_error = $this->format_openai_error_message($code, is_array($body) ? $body : array(), $model);
                if ($code === 401) {
                    break;
                }
                continue;
            }

            $content = isset($body['choices'][0]['message']['content'])
                ? trim((string) $body['choices'][0]['message']['content'])
                : '';
            if ($content === '') {
                $last_error = 'Ungültige KI-Antwort.';
                continue;
            }

            $parsed = json_decode($content, true);
            if (is_array($parsed) && !empty($parsed['suggestions']) && is_array($parsed['suggestions'])) {
                $suggestions = $this->normalize_admin_suggestions($parsed['suggestions']);
                if (count($suggestions) >= 1) {
                    return $suggestions;
                }
            }

            $fallback = $this->parse_admin_suggestion_text($content);
            if (count($fallback) >= 1) {
                return $fallback;
            }

            $last_error = 'Ungültige KI-Antwort.';
            continue;
        }

        return new WP_Error('suggestions_failed', $last_error);
    }

    /**
     * @return array<int, string>
     */
    private function parse_admin_suggestion_text($content) {
        $parsed = json_decode($content, true);
        if (is_array($parsed) && !empty($parsed['suggestions']) && is_array($parsed['suggestions'])) {
            return $this->normalize_admin_suggestions($parsed['suggestions']);
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $items = array();
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[\-\*\d\.\)\s]+/', '', trim((string) $line)));
            if ($line !== '') {
                $items[] = $line;
            }
            if (count($items) >= 3) {
                break;
            }
        }
        return $this->normalize_admin_suggestions($items);
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, string>
     */
    private function normalize_admin_suggestions($items) {
        $suggestions = array();
        foreach ($items as $item) {
            $text = sanitize_textarea_field(trim((string) $item));
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > 600) {
                $text = mb_substr($text, 0, 600);
            }
            $suggestions[] = $text;
            if (count($suggestions) >= 3) {
                break;
            }
        }
        return $suggestions;
    }

    private function resolve_openai_failure_message($http_code, $state, $curl_error, $model) {
        if (!empty($state['api_error'])) {
            return $this->sanitize_user_error_message($state['api_error'], $model);
        }

        if ($curl_error) {
            return 'Verbindungsfehler zum KI-Dienst. Bitte später erneut versuchen.';
        }

        if ($http_code === 401) {
            return 'OpenAI API Key ungültig oder abgelaufen.';
        }

        if ($http_code === 429) {
            return 'OpenAI Rate-Limit erreicht. Bitte kurz warten.';
        }

        if ($http_code >= 400) {
            return sprintf('OpenAI-Fehler (%1$d) für Modell „%2$s“.', $http_code, $model);
        }

        if ($this->is_reasoning_model($model)) {
            return sprintf(
                'Modell „%s“ lieferte keine sichtbare Antwort. Es wird automatisch ein Fallback-Modell versucht.',
                $model
            );
        }

        return 'Keine Antwort vom KI-Backend erhalten.';
    }

    private function format_openai_error_message($http_code, $body, $model) {
        $message = '';
        if (is_array($body) && !empty($body['error']['message'])) {
            $message = $this->sanitize_user_error_message($body['error']['message'], $model);
        }

        if ($message !== '') {
            return $message;
        }

        if ($http_code === 404) {
            return sprintf('Modell „%s“ ist für Ihren API-Key nicht verfügbar.', $model);
        }

        return sprintf('OpenAI-Fehler (%1$d) für Modell „%2$s“.', $http_code, $model);
    }

    private function sanitize_user_error_message($message, $model) {
        $message = wp_strip_all_tags((string) $message);
        $message = preg_replace('/sk-[A-Za-z0-9_-]+/', '[API-KEY]', $message);

        if (stripos($message, 'model') !== false && stripos($message, 'does not exist') !== false) {
            return sprintf('Modell „%s“ existiert nicht oder ist nicht freigeschaltet.', $model);
        }

        if (stripos($message, 'max_tokens') !== false || stripos($message, 'max_completion_tokens') !== false) {
            return sprintf('Token-Limit für Modell „%s“ unzureichend. Fallback-Modell wird verwendet.', $model);
        }

        return $message;
    }

    private function flush_sse_output() {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
    }

    /**
     * Preserve secret fields when the settings form submits an empty password input.
     */
    public static function sanitize_secret_option($value, $option_name) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return get_option($option_name, '');
        }
        return sanitize_text_field($value);
    }

    private function send_sse_headers() {
        if (headers_sent()) {
            return;
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    private function curl_headers($headers) {
        $out = array();
        foreach ($headers as $key => $value) {
            $out[] = $key . ': ' . $value;
        }
        return $out;
    }

    private function log_event($event, $context = array()) {
        error_log('PAXdesign Chat [' . $event . ']: ' . wp_json_encode($context));
    }

    public function register_settings() {
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_enabled');
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_openai_key', array(
            'sanitize_callback' => function ($value) {
                return PAXdesign_Chat::sanitize_secret_option($value, 'paxdesign_chat_openai_key');
            },
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_model', array(
            'sanitize_callback' => function ($value) {
                $value = sanitize_text_field($value);
                return $value !== '' ? $value : 'gpt-4o';
            },
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_worker_url');
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_worker_secret', array(
            'sanitize_callback' => function ($value) {
                return PAXdesign_Chat::sanitize_secret_option($value, 'paxdesign_chat_worker_secret');
            },
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_greeting', array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_response_style', array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_show_prices', array(
            'sanitize_callback' => function ($value) {
                return !empty($value) ? '1' : '';
            },
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_auto_booking', array(
            'sanitize_callback' => function ($value) {
                return !empty($value) ? '1' : '';
            },
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_phone', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_email', array(
            'sanitize_callback' => 'sanitize_email',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_primary_services', array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_cta_text', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_price_hints', array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
    }
}
