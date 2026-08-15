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
    /** Anonymous / IP-based AI chat requests per minute. */
    const RATE_LIMIT_MAX    = 30;
    /** Authenticated customer chat sends per minute (messages only, not polls). */
    const RATE_LIMIT_MAX_AUTHENTICATED = 120;
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
        add_action('wp_ajax_paxdesign_chat_ccs_attach', array($this, 'handle_ccs_attach'));
        add_action('wp_ajax_paxdesign_test_openai', array($this, 'handle_test_openai'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function is_enabled() {
        return (bool) get_option('paxdesign_chat_enabled', true);
    }

    public function register_assets() {
        if (!$this->is_enabled() || is_admin()) {
            return;
        }

        wp_register_script(
            'paxdesign-chat-script',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/chat-script.js',
            array('paxdesign-booking-script'),
            PAXDESIGN_BOOKING_VERSION,
            array('strategy' => 'defer', 'in_footer' => true)
        );

        if (wp_script_is('paxdesign-booking-script', 'enqueued') || wp_script_is('paxdesign-booking-script', 'registered')) {
            wp_localize_script('paxdesign-booking-script', 'paxdesignChat', $this->get_frontend_config());
        }
    }

    public function enqueue_assets() {
        $this->register_assets();
        wp_enqueue_script('paxdesign-chat-script');
    }

    /**
     * @return array<string, mixed>
     */
    private function get_frontend_config() {
        $auth_payload = class_exists('PAXdesign_Customer_Auth')
            ? PAXdesign_Customer_Auth::user_payload()
            : array('logged_in' => is_user_logged_in(), 'verified' => false, 'id' => get_current_user_id());
        $chat_session_id = '';
        if (!empty($auth_payload['logged_in']) && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $chat_session_id = PAXdesign_Customer_Chat_Bridge::lookup_primary_session_id((int) $auth_payload['id']);
        }

        $ai_identity = PAXdesign_Chat_Live::get_ai_assistant_identity();

        return array(
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'restUrl'          => rest_url('paxdesign/v1/chat'),
            'nonce'            => wp_create_nonce('paxdesign_chat_nonce'),
            'enabled'          => true,
            'requireLogin'     => get_option('paxdesign_customer_require_login_for_chat', '1') === '1',
            'auth'             => $auth_payload,
            'chatSessionId'    => $chat_session_id,
            'chatSessionHasMessages' => false,
            'chatMessageCount' => 0,
            'aiAssistant'      => $ai_identity,
            'authGate'         => array(
                'title'       => __('Continue to Live Chat', 'paxdesign-booking'),
                'subtitle'    => __('Sign in or create a free account to message our team. Your conversation stays synced across the website and app.', 'paxdesign-booking'),
                'signIn'      => __('Sign In', 'paxdesign-booking'),
                'register'    => __('Create Account', 'paxdesign-booking'),
                'verifyHint'  => __('Verify your email to start chatting.', 'paxdesign-booking'),
            ),
            'quickActions'     => array(),
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
            'i18n'               => self::chat_widget_i18n(),
            'sounds'             => array(
                'typing'    => 'https://paxdesign.at/wp-content/uploads/2026/06/freesound_community-writing-a-text-message-41141.mp3',
                'openClose' => PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/sounds/pax-chat-available.wav',
                'incoming'  => PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/sounds/pax-message.wav',
                'send'      => PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/sounds/pax-send.wav',
            ),
            'readinessDebug'     => (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options'))
                || (current_user_can('manage_options') && isset($_GET['pax_chat_debug']) && sanitize_text_field(wp_unslash($_GET['pax_chat_debug'])) === '1'),
        );
    }

    public function get_quick_actions() {
        return array();
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

    /**
     * Localized strings for the website chat widget (de / en / ar).
     *
     * @return array<string, array<string, string>>
     */
    private static function chat_widget_i18n() {
        if (!class_exists('PAXdesign_Language_Routing')) {
            return array();
        }
        $keys = array('staff_takeover', 'staff_returned_to_ai', 'chat_closed');
        $out  = array();
        foreach ($keys as $key) {
            $camel = lcfirst(str_replace('_', '', ucwords($key, '_')));
            $out[$camel] = array(
                'de' => PAXdesign_Language_Routing::system_notice($key, 'de'),
                'en' => PAXdesign_Language_Routing::system_notice($key, 'en'),
                'ar' => PAXdesign_Language_Routing::system_notice($key, 'ar'),
            );
        }
        $readiness = array(
            'readinessConnecting' => array(
                'de' => 'Verbindung wird hergestellt …',
                'en' => 'Connecting to chat …',
                'ar' => 'جاري الاتصال بالدردشة …',
            ),
            'readinessAuthenticating' => array(
                'de' => 'Anmeldung wird geprüft …',
                'en' => 'Verifying your sign-in …',
                'ar' => 'جاري التحقق من تسجيل الدخول …',
            ),
            'readinessSession' => array(
                'de' => 'Chat-Sitzung wird vorbereitet …',
                'en' => 'Preparing your chat session …',
                'ar' => 'جاري تجهيز جلسة الدردشة …',
            ),
            'readinessHistory' => array(
                'de' => 'Nachrichtenverlauf wird geladen …',
                'en' => 'Loading conversation history …',
                'ar' => 'جاري تحميل سجل المحادثة …',
            ),
            'readinessRealtime' => array(
                'de' => 'Echtzeit-Verbindung wird aufgebaut …',
                'en' => 'Establishing real-time connection …',
                'ar' => 'جاري إنشاء اتصال فوري …',
            ),
            'readinessSyncing' => array(
                'de' => 'Chat-Status wird synchronisiert …',
                'en' => 'Synchronizing chat status …',
                'ar' => 'جاري مزامنة حالة الدردشة …',
            ),
            'readinessAuthFailed' => array(
                'de' => 'Bitte melden Sie sich an, um den Chat zu nutzen.',
                'en' => 'Please sign in to use chat.',
                'ar' => 'يرجى تسجيل الدخول لاستخدام الدردشة.',
            ),
            'readinessSessionFailed' => array(
                'de' => 'Die Chat-Sitzung konnte nicht gestartet werden.',
                'en' => 'Could not start your chat session.',
                'ar' => 'تعذر بدء جلسة الدردشة.',
            ),
            'readinessNetworkFailed' => array(
                'de' => 'Verbindung zum Server fehlgeschlagen. Bitte prüfen Sie Ihre Internetverbindung.',
                'en' => 'Could not reach the server. Please check your connection.',
                'ar' => 'تعذر الاتصال بالخادم. يرجى التحقق من اتصالك.',
            ),
            'readinessStreamFailed' => array(
                'de' => 'Die Echtzeit-Verbindung konnte nicht hergestellt werden.',
                'en' => 'Real-time connection could not be established.',
                'ar' => 'تعذر إنشاء الاتصال الفوري.',
            ),
            'readinessAiFailed' => array(
                'de' => 'Der KI-Assistent ist derzeit nicht verfügbar.',
                'en' => 'The AI assistant is currently unavailable.',
                'ar' => 'مساعد KI غير متاح حالياً.',
            ),
            'readinessLiveFailed' => array(
                'de' => 'Die Live-Anfrage konnte nicht bestätigt werden. Bitte erneut versuchen.',
                'en' => 'Could not confirm your live agent request. Please try again.',
                'ar' => 'تعذر تأكيد طلب موظف الدردشة. يرجى المحاولة مرة أخرى.',
            ),
            'readinessGenericFailed' => array(
                'de' => 'Der Chat konnte nicht geladen werden.',
                'en' => 'Chat could not be loaded.',
                'ar' => 'تعذر تحميل الدردشة.',
            ),
            'readinessRetry' => array(
                'de' => 'Erneut versuchen',
                'en' => 'Retry',
                'ar' => 'إعادة المحاولة',
            ),
            'readinessClose' => array(
                'de' => 'Schließen',
                'en' => 'Close',
                'ar' => 'إغلاق',
            ),
            'supportConnected' => array(
                'de' => 'Support ist verbunden',
                'en' => 'Support is connected',
                'ar' => 'الدعم متصل',
            ),
        );
        return array_merge($out, $readiness);
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

    public function get_system_prompt($customer_language = '') {
        $prompt = PAXdesign_Chat_Knowledge::build_system_prompt($this->get_chat_settings_for_prompt());
        if ($customer_language !== '') {
            $prompt = PAXdesign_Chat_Knowledge::apply_customer_language($prompt, $customer_language);
        }
        return $prompt;
    }

    /**
     * System prompt with language rules and optional authenticated account context.
     *
     * @param string $customer_language de|en|ar
     * @param int    $user_id
     * @param string $session_id
     * @return string
     */
    public function build_ai_system_prompt($customer_language = '', $user_id = 0, $session_id = '') {
        $prompt = $this->get_system_prompt($customer_language);
        $user_id = absint($user_id);
        $page_context = $this->resolve_page_context($session_id);
        if (
            $page_context !== 'cybercrime-support'
            && class_exists('PAXdesign_Cybercrime_AI_Case')
            && PAXdesign_Cybercrime_AI_Case::is_ccs_session($session_id)
        ) {
            $page_context = 'cybercrime-support';
        }
        $focus_reference = ($page_context === 'cybercrime-support') ? $this->resolve_page_reference($session_id) : '';
        if ($page_context === 'cybercrime-support' && $focus_reference === '' && $user_id > 0 && class_exists('PAXdesign_Cybercrime_Tickets')) {
            $active = PAXdesign_Cybercrime_Tickets::get_active_report_for_user($user_id);
            if (is_array($active) && !empty($active['reference_id'])) {
                $focus_reference = (string) $active['reference_id'];
            }
        }

        if ($user_id > 0 && class_exists('PAXdesign_Chat_Knowledge')) {
            $context = PAXdesign_Chat_Knowledge::build_customer_account_context_block($user_id, $session_id, $focus_reference);
            if ($context !== '') {
                $prompt .= "\n\n" . $context;
            }
        }

        if ($page_context === 'cybercrime-support' && class_exists('PAXdesign_Chat_Knowledge')) {
            $page_language = $this->resolve_page_language($session_id);
            if ($page_language === '' && in_array($customer_language, array('de', 'en', 'ar'), true)) {
                $page_language = $customer_language;
            }
            $prompt .= "\n\n" . PAXdesign_Chat_Knowledge::build_cybercrime_support_context_block($page_language, $focus_reference);
        }

        return $prompt;
    }

    /**
     * Write authenticated CCS chat facts into the customer's real case before the model replies.
     *
     * @param string $session_id
     * @param int    $user_id
     * @param string $user_message
     * @return array<string, mixed>|WP_Error|null
     */
    private function ingest_ccs_case_from_chat($session_id, $user_id, $user_message) {
        if (!class_exists('PAXdesign_Cybercrime_AI_Case') || !PAXdesign_Cybercrime_AI_Case::is_ccs_session($session_id)) {
            return null;
        }
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error(
                'login_required',
                __('Sign in to use Cybercrime Support AI.', 'paxdesign-booking'),
                array('status' => 401) + PAXdesign_Cybercrime_AI_Case::login_required_payload()
            );
        }
        return PAXdesign_Cybercrime_AI_Case::ingest_chat_message(
            $user_id,
            $session_id,
            $user_message,
            $this->resolve_page_reference($session_id)
        );
    }

    /**
     * @param array<string, mixed>|WP_Error|null $report
     */
    private function emit_ccs_case_sse($report) {
        if (is_wp_error($report) || !is_array($report) || empty($report['reference_id'])) {
            return;
        }
        if (!class_exists('PAXdesign_Cybercrime_AI_Case')) {
            return;
        }
        if (class_exists('PAXdesign_Cybercrime_Tickets') && empty($report['original_request']) && isset($report['payload'])) {
            $formatted = PAXdesign_Cybercrime_Tickets::format_report_row($report, true);
            if (is_array($formatted) && !empty($formatted['reference_id'])) {
                $report = $formatted;
            }
        }
        echo 'data: ' . wp_json_encode(array(
            'type'   => 'ccs_case',
            'report' => PAXdesign_Cybercrime_AI_Case::public_case_sync_payload($report),
        )) . "\n\n";
        $this->flush_sse_output();
    }

    /**
     * @param array<string, mixed>|null $operation
     * @param array<string, mixed>|null $message
     */
    private function emit_ccs_operation_sse($operation, $message = null) {
        if (!is_array($operation) || empty($operation['id'])) {
            return;
        }
        $payload = array(
            'type'      => 'ccs_operation',
            'operation' => $operation,
        );
        if (is_array($message) && !empty($message['id'])) {
            $payload['message'] = class_exists('PAXdesign_Chat_Live')
                ? PAXdesign_Chat_Live::get_instance()->format_sse_message_payload($message, 0)
                : $message;
        }
        echo 'data: ' . wp_json_encode($payload) . "\n\n";
        $this->flush_sse_output();
    }

    /**
     * Keep CCS operations on the same case/conversation before the model replies.
     *
     * @param string                         $session_id
     * @param int                            $user_id
     * @param string                         $user_message
     * @param string                         $language
     * @param array<string, mixed>|WP_Error|null $ccs_report
     * @param bool                           $streaming
     * @param string                         $assistant_client_id
     * @param array<string, mixed>|null      $user_entry
     * @return array<string, mixed>
     */
    private function apply_ccs_operation_turn($session_id, $user_id, $user_message, $language, $ccs_report, $streaming = false, $assistant_client_id = '', $user_entry = null) {
        $out = array(
            'skip_llm'   => false,
            'assistant'  => null,
            'operation'  => null,
            'report'     => $ccs_report,
            'processing' => null,
        );
        if (is_wp_error($ccs_report)) {
            return $out;
        }
        if (!class_exists('PAXdesign_Cybercrime_AI_Operations') || !class_exists('PAXdesign_Cybercrime_AI_Case')) {
            return $out;
        }
        if (!PAXdesign_Cybercrime_AI_Case::is_ccs_session($session_id)) {
            return $out;
        }

        $decision = PAXdesign_Cybercrime_AI_Operations::decide_turn(
            $session_id,
            $user_id,
            $user_message,
            $language,
            is_array($ccs_report) ? $ccs_report : null
        );
        $action = (string) ($decision['action'] ?? 'continue');
        if ($action === 'continue') {
            if (!empty($decision['operation'])) {
                $out['operation'] = $decision['operation'];
            }
            return $out;
        }

        if ($action === 'status' || $action === 'continue_case' || $action === 'submit_case') {
            if ($action === 'submit_case' && class_exists('PAXdesign_Cybercrime_AI_Workflow')) {
                $submitted = PAXdesign_Cybercrime_AI_Workflow::submit_case(
                    is_array($decision['report'] ?? null) ? $decision['report'] : $ccs_report,
                    $user_id,
                    $language
                );
                if (is_wp_error($submitted)) {
                    $reply = $submitted->get_error_message();
                    $decision['report'] = is_array($decision['report'] ?? null) ? $decision['report'] : $ccs_report;
                } else {
                    $reference = (string) ($submitted['referenceId'] ?? $submitted['reference_id'] ?? '');
                    $reply = class_exists('PAXdesign_Cybercrime_AI_Workflow')
                        ? PAXdesign_Cybercrime_AI_Workflow::submitted_copy(
                            (string) ($submitted['message'] ?? ''),
                            $reference,
                            $language
                        )
                        : (string) ($submitted['message'] ?? '');
                    if (is_array($submitted['report'] ?? null)) {
                        $decision['report'] = $submitted['report'];
                    }
                    $out['report'] = $decision['report'] ?? $ccs_report;
                    if ($streaming) {
                        $this->emit_ccs_case_sse($out['report']);
                    }
                }
                $decision['reply'] = $reply;
            }
            $reply = (string) ($decision['reply'] ?? '');
            $operation = is_array($decision['operation'] ?? null) ? $decision['operation'] : array();
            $assistant_client_id = $this->assistant_client_id_for_turn($assistant_client_id, $user_entry);
            $assistant = PAXdesign_Cybercrime_AI_Operations::persist_assistant_reply($session_id, $reply, $operation, $assistant_client_id);
            $out['skip_llm'] = true;
            $out['assistant'] = $assistant;
            $out['operation'] = $operation;
            $out['report'] = $decision['report'] ?? $ccs_report;
            if ($streaming) {
                $this->emit_ccs_operation_sse($operation, $assistant);
                if (is_array($assistant)) {
                    $formatted = class_exists('PAXdesign_Chat_Live')
                        ? PAXdesign_Chat_Live::get_instance()->format_sse_message_payload($assistant, 0)
                        : $assistant;
                    echo 'data: ' . wp_json_encode(array(
                        'type'    => 'done',
                        'message' => $formatted,
                    )) . "\n\n";
                    $this->flush_sse_output();
                }
            }
            return $out;
        }

        if ($action !== 'start_document_check') {
            return $out;
        }

        $started = PAXdesign_Cybercrime_AI_Operations::start_document_check($session_id, $user_id, $language);
        if (is_wp_error($started)) {
            return $out;
        }
        $operation = is_array($started['operation'] ?? null) ? $started['operation'] : array();
        $processing = is_array($started['message'] ?? null) ? $started['message'] : null;
        $out['processing'] = $processing;
        $out['operation'] = $operation;
        if ($streaming) {
            $this->emit_ccs_operation_sse($operation, $processing);
        }

        $completed = PAXdesign_Cybercrime_AI_Operations::complete_document_check(
            $session_id,
            $user_id,
            (string) ($operation['id'] ?? ''),
            $language
        );
        if (is_wp_error($completed)) {
            return $out;
        }

        $operation = is_array($completed['operation'] ?? null) ? $completed['operation'] : $operation;
        $assistant = is_array($completed['message'] ?? null) ? $completed['message'] : null;
        $out['operation'] = $operation;
        $out['assistant'] = $assistant;
        $out['report'] = $completed['report'] ?? $ccs_report;
        $out['skip_llm'] = !empty($decision['skip_llm']);
        if ($streaming) {
            $this->emit_ccs_operation_sse($operation, $assistant);
            $this->emit_ccs_case_sse($out['report']);
        }
        return $out;
    }

    /**
     * Persist page context for a chat session (website POST or customer REST).
     *
     * @param string $session_id
     * @param string $context
     * @param string $reference
     * @param string $language
     */
    public function set_session_page_context($session_id, $context = '', $reference = '', $language = '') {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return;
        }
        $key = md5($session_id);
        $context = sanitize_key((string) $context);
        if ($context !== '') {
            set_transient('pax_chat_page_ctx_' . $key, $context, DAY_IN_SECONDS);
        }
        $language = sanitize_key((string) $language);
        if (in_array($language, array('de', 'en', 'ar'), true)) {
            set_transient('pax_chat_page_lang_' . $key, $language, DAY_IN_SECONDS);
        }
        $reference = sanitize_text_field((string) $reference);
        if ($reference !== '') {
            set_transient('pax_chat_page_ref_' . $key, $reference, DAY_IN_SECONDS);
        }
    }

    /**
     * After an explicit new CCS case, ignore earlier chat turns in the model prompt.
     *
     * @param string $session_id
     */
    public function reset_ccs_conversation_epoch($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return;
        }
        set_transient('pax_ccs_history_after_' . md5($session_id), (string) time(), DAY_IN_SECONDS);
    }

    /**
     * @param string $session_id
     * @return int
     */
    private function ccs_conversation_epoch($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return 0;
        }
        $stored = get_transient('pax_ccs_history_after_' . md5($session_id));
        $epoch = absint($stored);
        return $epoch > 0 ? $epoch : 0;
    }

    /**
     * One assistant client_msg_id per customer turn so retries cannot insert a second reply.
     *
     * @param string                    $assistant_client_id
     * @param array<string, mixed>|null $user_entry
     * @return string
     */
    private function assistant_client_id_for_turn($assistant_client_id, $user_entry = null) {
        $assistant_client_id = sanitize_text_field((string) $assistant_client_id);
        if ($assistant_client_id !== '') {
            return $assistant_client_id;
        }
        $user_client = is_array($user_entry) ? sanitize_text_field((string) ($user_entry['client_msg_id'] ?? '')) : '';
        if ($user_client !== '') {
            return 'ccs-asst:' . $user_client;
        }
        $user_id = is_array($user_entry) ? absint($user_entry['id'] ?? 0) : 0;
        if ($user_id > 0) {
            return 'ccs-asst:' . $user_id;
        }
        return '';
    }

    /**
     * Assistant that immediately follows this customer message. Null if a newer
     * customer message came first (this turn is no longer the latest).
     *
     * @param string                    $session_id
     * @param array<string, mixed>|null $user_entry
     * @param array<int, array<string, mixed>>|null $recent
     * @return array<string, mixed>|null
     */
    private function assistant_following_user($session_id, $user_entry, $recent = null) {
        if (!is_array($user_entry)) {
            return null;
        }
        $user_seq = absint($user_entry['id'] ?? $user_entry['seq'] ?? 0);
        if ($user_seq <= 0) {
            return null;
        }
        if (!is_array($recent)) {
            if (!class_exists('PAXdesign_Message_Store')) {
                return null;
            }
            $recent = PAXdesign_Message_Store::latest_messages($session_id, 24, 'customer');
        }
        if (!is_array($recent)) {
            return null;
        }
        foreach ($recent as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $seq = absint($msg['id'] ?? $msg['seq'] ?? 0);
            if ($seq <= $user_seq) {
                continue;
            }
            $role = (string) ($msg['role'] ?? '');
            if ($role === 'user') {
                return null;
            }
            if ($role === 'assistant') {
                return $msg;
            }
        }
        return null;
    }

    /**
     * @param string                    $session_id
     * @param array<string, mixed>|null $user_entry
     * @return string lock name when acquired, otherwise empty
     */
    private function lock_customer_turn($session_id, $user_entry) {
        if (!class_exists('PAXdesign_DB')) {
            return '';
        }
        $id = is_array($user_entry) ? absint($user_entry['id'] ?? 0) : 0;
        $cid = is_array($user_entry) ? sanitize_text_field((string) ($user_entry['client_msg_id'] ?? '')) : '';
        $key = $id > 0 ? (string) $id : $cid;
        if ($key === '') {
            return '';
        }
        $name = 'pax_turn_' . md5(sanitize_text_field((string) $session_id) . ':' . $key);
        $got = PAXdesign_DB::acquire_named_lock($name, 20);
        return $got === 1 ? $name : '';
    }

    /**
     * @param string $name
     */
    private function unlock_customer_turn($name) {
        $name = sanitize_text_field((string) $name);
        if ($name !== '' && class_exists('PAXdesign_DB')) {
            PAXdesign_DB::release_named_lock($name);
        }
    }

    /**
     * Reuse a stored customer row instead of inserting a second turn.
     * Looks up client_msg_id first so a stale retry of an older message cannot
     * be appended after a newer phone/desktop message.
     *
     * @param string $session_id
     * @param string $user_message
     * @param string $client_msg_id
     * @return array{user:array<string,mixed>,assistant:?array<string,mixed>}|null
     */
    private function matching_user_turn($session_id, $user_message, $client_msg_id = '') {
        if (!class_exists('PAXdesign_Message_Store')) {
            return null;
        }
        $user_message = trim((string) $user_message);
        $client_msg_id = sanitize_text_field((string) $client_msg_id);
        if ($session_id === '' || ($user_message === '' && $client_msg_id === '')) {
            return null;
        }
        if ($client_msg_id !== '') {
            $by_id = PAXdesign_Message_Store::find_by_client_id($session_id, $client_msg_id);
            if (is_array($by_id) && (($by_id['role'] ?? '') === 'user')) {
                return array(
                    'user'      => $by_id,
                    'assistant' => $this->assistant_following_user($session_id, $by_id),
                );
            }
        }
        $recent = PAXdesign_Message_Store::latest_messages($session_id, 24, 'customer');
        if (!is_array($recent) || empty($recent)) {
            return null;
        }
        $last_user = null;
        $assistant_after = null;
        $answered_same_text = null;
        foreach ($recent as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $role = (string) ($msg['role'] ?? '');
            if ($role === 'user') {
                $last_user = $msg;
                $assistant_after = null;
            } elseif ($role === 'assistant' && is_array($last_user)) {
                $assistant_after = $msg;
                if (trim((string) ($last_user['content'] ?? '')) === $user_message) {
                    $answered_same_text = array(
                        'user'      => $last_user,
                        'assistant' => $msg,
                    );
                }
            }
        }
        if (is_array($last_user) && trim((string) ($last_user['content'] ?? '')) === $user_message) {
            return array(
                'user'      => $last_user,
                'assistant' => is_array($assistant_after) ? $assistant_after : null,
            );
        }
        if (is_array($answered_same_text)) {
            return $answered_same_text;
        }
        return null;
    }

    /**
     * @param PAXdesign_Chat_Live $live
     * @param string              $session_id
     * @param string              $user_message
     * @param array<string, mixed> $extra
     * @return array{entry:array<string,mixed>|WP_Error|null,assistant:?array<string,mixed>,lock:string}
     */
    private function resolve_user_turn($live, $session_id, $user_message, $extra) {
        $client_msg_id = sanitize_text_field((string) ($extra['client_msg_id'] ?? ''));
        $match = $this->matching_user_turn($session_id, $user_message, $client_msg_id);
        if (is_array($match) && !empty($match['user'])) {
            $user = $match['user'];
            $user['_deduplicated'] = true;
            $assistant = is_array($match['assistant'] ?? null) ? $match['assistant'] : null;
            $lock = '';
            if (empty($assistant['id'])) {
                $lock = $this->lock_customer_turn($session_id, $user);
                $again = $this->assistant_following_user($session_id, $user);
                if (is_array($again) && !empty($again['id'])) {
                    $assistant = $again;
                    $this->unlock_customer_turn($lock);
                    $lock = '';
                }
            }
            return array(
                'entry'     => $user,
                'assistant' => $assistant,
                'lock'      => $lock,
            );
        }
        $entry = $live->append_message($session_id, 'user', $user_message, $extra);
        $lock = '';
        $assistant = null;
        if (is_array($entry) && !empty($entry['id'])) {
            $lock = $this->lock_customer_turn($session_id, $entry);
            $again = $this->assistant_following_user($session_id, $entry);
            if (is_array($again) && !empty($again['id'])) {
                $assistant = $again;
                $this->unlock_customer_turn($lock);
                $lock = '';
            }
        }
        return array(
            'entry'     => $entry,
            'assistant' => $assistant,
            'lock'      => $lock,
        );
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, mixed>|null $assistant
     * @param array<string, mixed>|null $ccs_report
     */
    private function emit_reused_assistant_sse($entry, $assistant, $ccs_report = null) {
        $live = class_exists('PAXdesign_Chat_Live') ? PAXdesign_Chat_Live::get_instance() : null;
        $this->send_sse_headers();
        echo 'data: ' . wp_json_encode(array(
            'type'    => 'user',
            'message' => $live ? $live->format_sse_message_payload($entry, 0) : $entry,
        )) . "\n\n";
        $this->flush_sse_output();
        if (is_array($ccs_report)) {
            $this->emit_ccs_case_sse($ccs_report);
        }
        if (is_array($assistant) && !empty($assistant['id'])) {
            $formatted = $live ? $live->format_sse_message_payload($assistant, 0) : $assistant;
            echo 'data: ' . wp_json_encode(array(
                'type'    => 'done',
                'message' => $formatted,
            )) . "\n\n";
            $this->flush_sse_output();
        }
        echo "data: [DONE]\n\n";
        exit;
    }

    /**
     * @param string                        $session_id
     * @param array<string, mixed>          $entry
     * @param array<string, mixed>|null     $assistant
     * @param array<string, mixed>|null     $ccs_report
     * @return array<string, mixed>
     */
    private function reused_assistant_payload($session_id, $entry, $assistant, $ccs_report = null) {
        $live = PAXdesign_Chat_Live::get_instance();
        $formatted_user = $live->format_sse_message_payload($entry, 0);
        $formatted_assistant = is_array($assistant)
            ? $live->format_sse_message_payload($assistant, 0)
            : array();
        return array(
            'session_id'    => $session_id,
            'handler'       => $live->get_handler($session_id),
            'message'       => $formatted_user,
            'assistant'     => $formatted_assistant,
            'processing'    => null,
            'ccs_operation' => null,
            'ccs_case'      => (class_exists('PAXdesign_Cybercrime_AI_Case') && is_array($ccs_report))
                ? PAXdesign_Cybercrime_AI_Case::public_case_sync_payload($ccs_report)
                : null,
        );
    }

    private function persist_page_context_from_request($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        $reference = isset($_POST['page_reference']) ? sanitize_text_field(wp_unslash($_POST['page_reference'])) : '';
        $last_user = $this->last_user_message_from_request();
        if (
            class_exists('PAXdesign_Cybercrime_AI_Case')
            && PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request($last_user)
        ) {
            $reference = '';
        } elseif ($reference !== '' && $session_id !== '' && class_exists('PAXdesign_Cybercrime_Tickets')) {
            $bound = PAXdesign_Cybercrime_Tickets::get_reference_for_session($session_id);
            if (is_string($bound) && $bound !== '' && strcasecmp($bound, $reference) !== 0) {
                $reference = $bound;
            }
        }
        $this->set_session_page_context(
            $session_id,
            isset($_POST['page_context']) ? wp_unslash($_POST['page_context']) : '',
            $reference,
            isset($_POST['page_language']) ? wp_unslash($_POST['page_language']) : ''
        );
    }

    /**
     * @return string
     */
    private function last_user_message_from_request() {
        if (isset($_POST['message'])) {
            $direct = trim(sanitize_textarea_field(wp_unslash($_POST['message'])));
            if ($direct !== '') {
                return $direct;
            }
        }
        $messages_raw = isset($_POST['messages']) ? wp_unslash($_POST['messages']) : '';
        $messages = is_string($messages_raw) ? json_decode($messages_raw, true) : $messages_raw;
        if (!is_array($messages)) {
            return '';
        }
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (!is_array($messages[$i]) || (($messages[$i]['role'] ?? '') !== 'user')) {
                continue;
            }
            return trim(sanitize_textarea_field((string) ($messages[$i]['content'] ?? '')));
        }
        return '';
    }

    /**
     * @param string $session_id
     * @return string
     */
    private function resolve_page_context($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id !== '') {
            $stored = get_transient('pax_chat_page_ctx_' . md5($session_id));
            if (is_string($stored) && $stored !== '') {
                return sanitize_key($stored);
            }
        }

        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        if ($referrer !== '' && strpos($referrer, '/cybercrime-support') !== false) {
            return 'cybercrime-support';
        }

        if ($session_id !== '' && class_exists('PAXdesign_Cybercrime_Tickets')) {
            $bound = PAXdesign_Cybercrime_Tickets::get_reference_for_session($session_id);
            if (is_string($bound) && $bound !== '') {
                return 'cybercrime-support';
            }
        }

        return '';
    }

    /**
     * @param string $session_id
     * @return string
     */
    private function resolve_page_reference($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id !== '') {
            $stored = get_transient('pax_chat_page_ref_' . md5($session_id));
            if (is_string($stored) && $stored !== '') {
                return sanitize_text_field($stored);
            }
        }

        if (isset($_POST['page_reference'])) {
            $reference = sanitize_text_field(wp_unslash($_POST['page_reference']));
            if ($reference !== '') {
                return $reference;
            }
        }

        if ($session_id !== '' && class_exists('PAXdesign_Cybercrime_Tickets')) {
            $bound = PAXdesign_Cybercrime_Tickets::get_reference_for_session($session_id);
            if (is_string($bound) && $bound !== '') {
                return $bound;
            }
        }

        return '';
    }

    /**
     * @param string $session_id
     * @return string
     */
    private function resolve_page_language($session_id) {
        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return '';
        }

        $stored = get_transient('pax_chat_page_lang_' . md5($session_id));
        if (is_string($stored) && in_array($stored, array('de', 'en', 'ar'), true)) {
            return $stored;
        }

        return '';
    }

    /**
     * @param string $session_id
     * @param string $user_message
     * @return string de|en|ar
     */
    private function resolve_and_persist_customer_language($session_id, $user_message) {
        if (!class_exists('PAXdesign_Language_Routing')) {
            return 'de';
        }
        $language = PAXdesign_Language_Routing::resolve_session_language($session_id, $user_message);
        PAXdesign_Language_Routing::persist_session_language($session_id, $language);
        $this->set_session_page_context($session_id, '', '', $language);
        return $language;
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @return string de|en|ar
     */
    private function detect_language_from_messages($messages) {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (!is_array($messages[$i]) || ($messages[$i]['role'] ?? '') !== 'user') {
                continue;
            }
            $text = trim((string) ($messages[$i]['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (class_exists('PAXdesign_Language_Routing')) {
                $language = PAXdesign_Language_Routing::detect_text_language($text);
                return $language !== '' ? $language : 'de';
            }
            break;
        }
        return 'de';
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
        $this->persist_page_context_from_request($session_id);
        $ccs_chat = class_exists('PAXdesign_Cybercrime_AI_Case')
            && PAXdesign_Cybercrime_AI_Case::is_ccs_session($session_id);
        $require_login = get_option('paxdesign_customer_require_login_for_chat', '1') === '1' || $ccs_chat;
        if ($require_login && get_current_user_id() <= 0) {
            $payload = $ccs_chat
                ? PAXdesign_Cybercrime_AI_Case::login_required_payload()
                : array(
                    'message' => __('Sign in or create an account to use Live Chat.', 'paxdesign-booking'),
                    'code'    => 'login_required',
                );
            wp_send_json_error($payload, 401);
        }
        if (get_current_user_id() > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $live = PAXdesign_Chat_Live::get_instance();
            $user_id = get_current_user_id();
            $session_id = PAXdesign_Customer_Chat_Bridge::resolve_ajax_session($user_id, $session_id);
            $last_user = '';
            if (isset($_POST['message'])) {
                $last_user = sanitize_textarea_field(wp_unslash($_POST['message']));
            }
            if ($last_user === '') {
                for ($i = count($messages) - 1; $i >= 0; $i--) {
                    if (is_array($messages[$i]) && ($messages[$i]['role'] ?? '') === 'user') {
                        $last_user = sanitize_textarea_field((string) ($messages[$i]['content'] ?? ''));
                        break;
                    }
                }
            }
            if ($last_user !== '') {
                PAXdesign_Customer_Chat_Bridge::materialize_session($session_id, $user_id);
                $assistant_client_id = isset($_POST['assistant_client_msg_id'])
                    ? sanitize_text_field(wp_unslash($_POST['assistant_client_msg_id']))
                    : '';
                $client_msg_id = isset($_POST['client_msg_id'])
                    ? sanitize_text_field(wp_unslash($_POST['client_msg_id']))
                    : '';
                $result = $this->stream_authenticated_customer_chat(
                    $session_id,
                    $last_user,
                    $client_msg_id,
                    $assistant_client_id
                );
                if (is_wp_error($result)) {
                    $status = 500;
                    $error_data = $result->get_error_data();
                    if (is_array($error_data) && !empty($error_data['status'])) {
                        $status = (int) $error_data['status'];
                    }
                    wp_send_json_error(array('message' => $result->get_error_message()), $status);
                }
                exit;
            }
        }
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

        $assistant_client_id = isset($_POST['assistant_client_msg_id'])
            ? sanitize_text_field(wp_unslash($_POST['assistant_client_msg_id']))
            : '';
        $this->stream_chat_response($messages, $session_id, $assistant_client_id);
    }

    /**
     * Authenticated CCS chat: attach evidence/documents to the same CCS case.
     */
    public function handle_ccs_attach() {
        if (!$this->is_enabled()) {
            wp_send_json_error(array('message' => 'Chat ist derzeit nicht verfügbar.'), 503);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'paxdesign_chat_nonce')) {
            status_header(403);
            wp_send_json_error(array('message' => 'Sitzung abgelaufen. Bitte laden Sie die Seite neu.'), 403);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            $payload = class_exists('PAXdesign_Cybercrime_AI_Case')
                ? PAXdesign_Cybercrime_AI_Case::login_required_payload()
                : array(
                    'message' => __('Sign in or create an account to use Live Chat.', 'paxdesign-booking'),
                    'code'    => 'login_required',
                );
            wp_send_json_error($payload, 401);
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        $this->persist_page_context_from_request($session_id);
        if (class_exists('PAXdesign_Customer_Chat_Bridge')) {
            $session_id = PAXdesign_Customer_Chat_Bridge::resolve_ajax_session($user_id, $session_id);
        }
        if ($session_id === '' || !class_exists('PAXdesign_Cybercrime_AI_Case') || !PAXdesign_Cybercrime_AI_Case::is_ccs_session($session_id)) {
            wp_send_json_error(array(
                'message' => __('File uploads in this chat are only available for your Cybercrime Support case.', 'paxdesign-booking'),
            ), 400);
        }

        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error(array('message' => __('Please choose a file to upload.', 'paxdesign-booking')), 400);
        }

        $file = $_FILES['file'];
        $kind = $this->detect_ccs_upload_kind($file);
        if (!class_exists('PAXdesign_Customer_Media')) {
            wp_send_json_error(array('message' => __('Upload is not available.', 'paxdesign-booking')), 500);
        }

        $upload = PAXdesign_Customer_Media::handle_upload($file, $kind);
        if (is_wp_error($upload)) {
            wp_send_json_error(array('message' => $upload->get_error_message()), 400);
        }

        if (class_exists('PAXdesign_Customer_Chat_Bridge')) {
            PAXdesign_Customer_Chat_Bridge::materialize_session($session_id, $user_id);
        }

        $client_msg_id = isset($_POST['client_msg_id'])
            ? sanitize_text_field(wp_unslash($_POST['client_msg_id']))
            : '';
        $caption = isset($_POST['caption']) ? sanitize_textarea_field(wp_unslash($_POST['caption'])) : '';
        $filename = sanitize_file_name((string) ($upload['name'] ?? ($file['name'] ?? 'file')));
        if ($caption === '') {
            $caption = $filename;
        }

        $message_extra = array(
            'attachment_type' => $kind,
            'client_msg_id'   => $client_msg_id,
        );
        if ($kind === 'image') {
            $message_extra['image_url'] = $upload['url'];
        } else {
            $message_extra['file_url']  = $upload['url'];
            $message_extra['file_name'] = $filename;
            $message_extra['file_mime'] = (string) ($upload['mime'] ?? '');
        }

        $live = PAXdesign_Chat_Live::get_instance();
        $entry = $live->append_message($session_id, 'user', $caption, $message_extra);
        if (is_wp_error($entry) || !$entry) {
            wp_send_json_error(array('message' => __('Could not attach the file to this conversation.', 'paxdesign-booking')), 500);
        }

        $upload['size'] = isset($file['size']) ? (string) $file['size'] : (string) ($upload['size'] ?? '');
        $case_row = PAXdesign_Cybercrime_AI_Case::attach_chat_upload($user_id, $session_id, $upload, $kind, $caption);
        if (is_wp_error($case_row)) {
            wp_send_json_error(array('message' => $case_row->get_error_message()), 400);
        }

        $language = $this->resolve_page_language($session_id);
        $response = array(
            'message'   => $entry,
            'ccs_case'  => PAXdesign_Cybercrime_AI_Case::public_case_sync_payload(
                class_exists('PAXdesign_Cybercrime_Tickets') && is_array($case_row)
                    ? PAXdesign_Cybercrime_Tickets::format_report_row($case_row, false)
                    : $case_row
            ),
        );

        if (class_exists('PAXdesign_Cybercrime_AI_Operations')) {
            $decision = PAXdesign_Cybercrime_AI_Operations::decide_turn($session_id, $user_id, 'please check the uploaded files', $language);
            if ((string) ($decision['action'] ?? '') === 'start_document_check') {
                $op_turn = $this->apply_ccs_operation_turn($session_id, $user_id, 'please check the uploaded files', $language, $case_row, false);
                if (!empty($op_turn['operation'])) {
                    $response['ccs_operation'] = $op_turn['operation'];
                }
                if (!empty($op_turn['processing'])) {
                    $response['processing'] = $op_turn['processing'];
                }
                if (!empty($op_turn['assistant'])) {
                    $response['assistant'] = $op_turn['assistant'];
                }
                if (!empty($op_turn['report']) && is_array($op_turn['report'])) {
                    $response['ccs_case'] = PAXdesign_Cybercrime_AI_Case::public_case_sync_payload(
                        PAXdesign_Cybercrime_Tickets::format_report_row($op_turn['report'], false)
                    );
                }
            }
        }

        wp_send_json_success($response);
    }

    /**
     * @param array<string, mixed> $file
     * @return string image|file
     */
    private function detect_ccs_upload_kind($file) {
        $name = isset($file['name']) ? strtolower((string) $file['name']) : '';
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $image = array('jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif', 'heic', 'heif');
        if (in_array($ext, $image, true)) {
            return 'image';
        }
        $type = isset($file['type']) ? strtolower((string) $file['type']) : '';
        if (strpos($type, 'image/') === 0) {
            return 'image';
        }
        return 'file';
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
        if (!$this->check_rate_limit($client_ip, self::RATE_LIMIT_MAX)) {
            return new WP_Error(
                'rate_limit',
                __('Too many requests. Please wait a moment and try again.', 'paxdesign-booking'),
                array('status' => 429, 'retry_after' => self::RATE_LIMIT_WINDOW)
            );
        }

        return array('ok' => true);
    }

    private function stream_chat_response($messages, $session_id = '', $assistant_client_id = '') {
        if (class_exists('PAXdesign_Cybercrime_AI_Case') && PAXdesign_Cybercrime_AI_Case::is_ccs_session($session_id) && get_current_user_id() <= 0) {
            status_header(401);
            wp_send_json_error(PAXdesign_Cybercrime_AI_Case::login_required_payload(), 401);
        }

        $validated = $this->validate_messages($messages);
        if (is_wp_error($validated)) {
            status_header(400);
            wp_send_json_error(array('message' => $validated->get_error_message()));
        }

        $client_ip = $this->get_client_ip();
        if (!$this->check_rate_limit($client_ip, self::RATE_LIMIT_MAX)) {
            status_header(429);
            header('Retry-After: ' . self::RATE_LIMIT_WINDOW);
            wp_send_json_error(array(
                'message' => __('Too many requests. Please wait a moment and try again.', 'paxdesign-booking'),
            ));
        }

        $customer_language = $this->detect_language_from_messages($validated);
        $user_id = get_current_user_id();

        $worker_url = trim(get_option('paxdesign_chat_worker_url', ''));
        if (!empty($worker_url)) {
            $this->proxy_to_worker($worker_url, $validated, $customer_language, $user_id, $session_id);
            return;
        }

        $api_key = $this->get_openai_api_key();

        if (empty($api_key)) {
            status_header(503);
            wp_send_json_error(array('message' => 'Chat ist derzeit nicht konfiguriert.'));
        }

        $this->stream_openai_response($api_key, $validated, $session_id, $assistant_client_id, $customer_language, $user_id);
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
            return new WP_Error('empty', 'Keine gültige Nachricht.', array('status' => 400));
        }

        return $validated;
    }

    private function check_rate_limit($ip, $max = null) {
        return $this->check_rate_limit_for_key('ip:' . $ip, $max ?? self::RATE_LIMIT_MAX);
    }

    private function check_rate_limit_for_key($key, $max = null) {
        $limit = $max ?? self::RATE_LIMIT_MAX;
        $transient_key = 'paxdesign_chat_rl_' . md5($key);
        $data = get_transient($transient_key);

        if ($data === false) {
            set_transient($transient_key, array('count' => 1, 'start' => time()), self::RATE_LIMIT_WINDOW);
            return true;
        }

        if ($data['count'] >= $limit) {
            return false;
        }

        $data['count']++;
        set_transient($transient_key, $data, self::RATE_LIMIT_WINDOW);
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

    private function proxy_to_worker($worker_url, $messages, $customer_language = '', $user_id = 0, $session_id = '') {
        $secret = get_option('paxdesign_chat_worker_secret', '');

        $headers = array(
            'Content-Type'  => 'application/json',
            'Accept'        => 'text/event-stream',
        );
        if (!empty($secret)) {
            $headers['X-PAX-Chat-Token'] = $secret;
        }

        $worker_messages = array_merge(
            array(array('role' => 'system', 'content' => $this->build_ai_system_prompt($customer_language, $user_id, $session_id))),
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

    private function stream_openai_response($api_key, $messages, $session_id = '', $assistant_client_id = '', $customer_language = '', $user_id = 0) {
        if (!function_exists('curl_init')) {
            status_header(503);
            wp_send_json_error(array('message' => 'Chat-Server unterstützt keine Streaming-Verbindung (cURL fehlt).'));
        }

        $openai_messages = array(
            array('role' => 'system', 'content' => $this->build_ai_system_prompt($customer_language, $user_id, $session_id)),
        );
        foreach ($messages as $msg) {
            if ($msg['role'] !== 'system') {
                $openai_messages[] = $msg;
            }
        }

        $openai_messages = $this->trim_conversation_history($openai_messages);

        $this->send_sse_headers();

        if ($session_id !== '' && class_exists('PAXdesign_Chat_Live')) {
            PAXdesign_Chat_Live::get_instance()->mark_assistant_typing($session_id);
        }

        $models     = $this->get_model_candidates();
        $last_error = 'Keine Antwort vom KI-Backend erhalten.';

        foreach ($models as $model) {
            $state = array(
                'line_buffer' => '',
                'has_content' => false,
                'full_content'=> '',
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
                $stored = null;
                if ($session_id !== '' && class_exists('PAXdesign_Chat_Live')) {
                    $stored = PAXdesign_Chat_Live::get_instance()->append_message(
                        $session_id,
                        'assistant',
                        $state['full_content'],
                        array('client_msg_id' => $assistant_client_id)
                    );
                }
                update_option('paxdesign_chat_last_model', $model, false);
                delete_option('paxdesign_chat_last_error');
                update_option('paxdesign_chat_last_test', time(), false);
                $this->log_event('openai_success', array('model' => $model));
                if (is_array($stored)) {
                    $payload = PAXdesign_Chat_Live::get_instance()->format_sse_message_payload($stored, 0);
                    echo 'data: ' . wp_json_encode(array('type' => 'done', 'message' => $payload)) . "\n\n";
                }
                if ($session_id !== '' && class_exists('PAXdesign_Chat_Live')) {
                    PAXdesign_Chat_Live::get_instance()->clear_assistant_typing($session_id);
                }
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
        if ($session_id !== '' && class_exists('PAXdesign_Chat_Live')) {
            PAXdesign_Chat_Live::get_instance()->clear_assistant_typing($session_id);
        }
        echo 'data: ' . wp_json_encode(array('type' => 'error', 'text' => $last_error)) . "\n\n";
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
                $state['full_content'] .= $text;
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
    private function build_openai_payload($model, $messages, $stream = true) {
        $payload = array(
            'model'                 => $model,
            'messages'              => $messages,
            'stream'                => (bool) $stream,
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
     * Stream an authenticated customer AI response over SSE.
     * Caller must verify auth and session ownership before invoking.
     *
     * @return true|WP_Error True when stream completed; WP_Error before headers are sent.
     */
    public function stream_authenticated_customer_chat($session_id, $user_message, $client_msg_id = '', $assistant_client_id = '') {
        if (!$this->is_enabled()) {
            return new WP_Error('disabled', __('Chat is currently unavailable.', 'paxdesign-booking'), array('status' => 503));
        }

        $live = PAXdesign_Chat_Live::get_instance();
        $session_id = $live->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', __('Invalid session.', 'paxdesign-booking'), array('status' => 400));
        }

        $handler = $live->get_handler($session_id);
        if ($handler === PAXdesign_Chat_Live::HANDLER_CLOSED) {
            $user_id = get_current_user_id();
            if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
                $session_id = PAXdesign_Customer_Chat_Bridge::reopen_closed_session($session_id);
                $live->ensure_session($session_id);
                $handler = $live->get_handler($session_id);
            } else {
                return new WP_Error('chat_closed', __('This conversation is closed.', 'paxdesign-booking'), array('status' => 409));
            }
        }

        if ($live->is_ai_blocked($session_id)) {
            $user_message = sanitize_textarea_field((string) $user_message);
            if ($user_message === '') {
                return new WP_Error('empty', __('Message is required.', 'paxdesign-booking'), array('status' => 400));
            }
            $message = __('A team member is handling your conversation. Please wait for their reply.', 'paxdesign-booking');
            if ($handler === PAXdesign_Chat_Live::HANDLER_LIVE) {
                $message = __('Your request was forwarded to our team. A team member will reply here shortly.', 'paxdesign-booking');
            }
            $live->ensure_session($session_id);
            $user_extra = array('client_msg_id' => sanitize_text_field((string) $client_msg_id));
            if (class_exists('PAXdesign_Link_Scanner')) {
                $user_extra = PAXdesign_Link_Scanner::attach_scan_meta($user_message, 'user', $user_extra);
            }
            $saved = $live->append_message($session_id, 'user', $user_message, $user_extra);
            if (is_wp_error($saved) || !$saved) {
                return new WP_Error('ai_blocked', $message, array('status' => 409));
            }
            $this->send_sse_headers();
            echo 'data: ' . wp_json_encode(array(
                'type'    => 'handoff',
                'handler' => $handler,
                'message' => $live->format_sse_message_payload($saved, 0),
                'notice'  => $message,
            )) . "\n\n";
            $this->flush_sse_output();
            echo "data: [DONE]\n\n";
            exit;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || !$this->check_rate_limit_for_key('customer:' . $user_id, self::RATE_LIMIT_MAX_AUTHENTICATED)) {
            return new WP_Error(
                'rate_limit',
                __('Too many requests. Please wait a moment and try again.', 'paxdesign-booking'),
                array('status' => 429, 'retry_after' => self::RATE_LIMIT_WINDOW)
            );
        }

        $user_message = sanitize_textarea_field((string) $user_message);
        if ($user_message === '') {
            return new WP_Error('empty', __('Message is required.', 'paxdesign-booking'), array('status' => 400));
        }

        $live->ensure_session($session_id);
        $extra = array(
            'client_msg_id' => sanitize_text_field((string) $client_msg_id),
        );
        if (class_exists('PAXdesign_Link_Scanner')) {
            $extra = PAXdesign_Link_Scanner::attach_scan_meta($user_message, 'user', $extra);
        }

        $turn = $this->resolve_user_turn($live, $session_id, $user_message, $extra);
        $entry = $turn['entry'];
        $lock = (string) ($turn['lock'] ?? '');
        try {
        if (is_wp_error($entry)) {
            return $entry;
        }
        if (!$entry) {
            return new WP_Error('send_failed', __('Could not save your message.', 'paxdesign-booking'), array('status' => 500));
        }
        if (is_array($turn['assistant'] ?? null) && !empty($turn['assistant']['id'])) {
            $this->emit_reused_assistant_sse($entry, $turn['assistant']);
        }

        $ccs_report = $this->ingest_ccs_case_from_chat($session_id, $user_id, $user_message);
        if (is_wp_error($ccs_report)) {
            return $ccs_report;
        }

        $customer_language = $this->resolve_and_persist_customer_language($session_id, $user_message);

        if (
            class_exists('PAXdesign_Language_Routing')
            && PAXdesign_Language_Routing::is_live_agent_intent($user_message)
        ) {
            $lang = $customer_language;
            $escalation = $live->escalate_authenticated_to_live($session_id, $user_id, $lang);
            if (is_wp_error($escalation)) {
                return $escalation;
            }
            $this->send_sse_headers();
            echo 'data: ' . wp_json_encode(array(
                'type'     => 'handoff',
                'handler'  => PAXdesign_Chat_Live::HANDLER_LIVE,
                'message'  => $entry,
                'assistant'=> !empty($escalation['thanks']) ? $escalation['thanks'] : null,
                'notice'   => class_exists('PAXdesign_Language_Routing')
                    ? PAXdesign_Language_Routing::live_handoff_notice_message($lang)
                    : '',
            )) . "\n\n";
            $this->flush_sse_output();
            $this->emit_ccs_case_sse($ccs_report);
            echo "data: [DONE]\n\n";
            exit;
        }

        $this->send_sse_headers();
        echo 'data: ' . wp_json_encode(array('type' => 'user', 'message' => $entry)) . "\n\n";
        $this->flush_sse_output();
        $this->emit_ccs_case_sse($ccs_report);

        $ccs_ops = $this->apply_ccs_operation_turn(
            $session_id,
            $user_id,
            $user_message,
            $customer_language,
            $ccs_report,
            true,
            $assistant_client_id,
            is_array($entry) ? $entry : null
        );
        if (!empty($ccs_ops['report'])) {
            $ccs_report = $ccs_ops['report'];
        }
        if (!empty($ccs_ops['skip_llm'])) {
            $this->emit_ccs_case_sse($ccs_report);
            echo "data: [DONE]\n\n";
            exit;
        }

        $messages = $this->prepare_authenticated_llm_messages($session_id, $user_message, $ccs_report);
        if (is_wp_error($messages)) {
            echo 'data: ' . wp_json_encode(array(
                'type'    => 'error',
                'message' => $messages->get_error_message(),
            )) . "\n\n";
            echo "data: [DONE]\n\n";
            exit;
        }

        $worker_url = trim(get_option('paxdesign_chat_worker_url', ''));
        if ($worker_url !== '') {
            $this->proxy_to_worker($worker_url, $messages, $customer_language, $user_id, $session_id);
            exit;
        }

        $api_key = $this->get_openai_api_key();
        if ($api_key === '') {
            echo 'data: ' . wp_json_encode(array(
                'type'    => 'error',
                'message' => __('Chat is not configured.', 'paxdesign-booking'),
            )) . "\n\n";
            echo "data: [DONE]\n\n";
            exit;
        }

        $this->stream_openai_response(
            $api_key,
            $messages,
            $session_id,
            sanitize_text_field((string) $assistant_client_id),
            $customer_language,
            $user_id
        );
        exit;
        } finally {
            $this->unlock_customer_turn($lock);
        }
    }

    /**
     * Non-SSE authenticated customer AI reply (mobile REST).
     *
     * @return array|WP_Error
     */
    public function complete_authenticated_customer_chat($session_id, $user_message, $client_msg_id = '', $assistant_client_id = '') {
        if (!$this->is_enabled()) {
            return new WP_Error('disabled', __('Chat is temporarily unavailable. Please try again shortly.', 'paxdesign-booking'), array('status' => 503));
        }

        $live = PAXdesign_Chat_Live::get_instance();
        $session_id = $live->sanitize_session_id($session_id);
        if ($session_id === '') {
            return new WP_Error('invalid_session', __('We could not find your conversation. Please reopen chat.', 'paxdesign-booking'), array('status' => 400));
        }

        $handler = $live->get_handler($session_id);
        if ($handler === PAXdesign_Chat_Live::HANDLER_CLOSED) {
            $user_id = get_current_user_id();
            if ($user_id > 0 && class_exists('PAXdesign_Customer_Chat_Bridge')) {
                $session_id = PAXdesign_Customer_Chat_Bridge::reopen_closed_session($session_id);
                $live->ensure_session($session_id);
                $handler = $live->get_handler($session_id);
            } else {
                return new WP_Error('chat_closed', __('This conversation is closed. Start a new chat from the website if you need help.', 'paxdesign-booking'), array('status' => 409));
            }
        }

        if ($live->is_ai_blocked($session_id)) {
            $message = __('A team member is handling your conversation. Please wait for their reply.', 'paxdesign-booking');
            if ($handler === PAXdesign_Chat_Live::HANDLER_LIVE) {
                $message = __('Your request was forwarded to our team. A team member will reply here shortly.', 'paxdesign-booking');
            }
            $live->ensure_session($session_id);
            $user_extra = array('client_msg_id' => sanitize_text_field((string) $client_msg_id));
            if (class_exists('PAXdesign_Link_Scanner')) {
                $user_extra = PAXdesign_Link_Scanner::attach_scan_meta($user_message, 'user', $user_extra);
            }
            $saved = $live->append_message($session_id, 'user', $user_message, $user_extra);
            if (is_wp_error($saved) || !$saved) {
                return new WP_Error('ai_blocked', $message, array('status' => 409));
            }
            return array(
                'session_id' => $session_id,
                'handler'    => $handler,
                'message'    => $saved,
                'assistant'  => null,
                'notice'     => $message,
            );
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || !$this->check_rate_limit_for_key('customer:' . $user_id, self::RATE_LIMIT_MAX_AUTHENTICATED)) {
            return new WP_Error(
                'rate_limit',
                __('Too many messages. Please wait a moment and try again.', 'paxdesign-booking'),
                array('status' => 429, 'retry_after' => self::RATE_LIMIT_WINDOW)
            );
        }

        $user_message = sanitize_textarea_field((string) $user_message);
        if ($user_message === '') {
            return new WP_Error('empty', __('Please enter a message.', 'paxdesign-booking'), array('status' => 400));
        }

        $live->ensure_session($session_id);
        $customer_language = $this->resolve_and_persist_customer_language($session_id, $user_message);

        $extra = array('client_msg_id' => sanitize_text_field((string) $client_msg_id));
        if (class_exists('PAXdesign_Link_Scanner')) {
            $extra = PAXdesign_Link_Scanner::attach_scan_meta($user_message, 'user', $extra);
        }

        $turn = $this->resolve_user_turn($live, $session_id, $user_message, $extra);
        $entry = $turn['entry'];
        $lock = (string) ($turn['lock'] ?? '');
        try {
        if (is_wp_error($entry)) {
            return $entry;
        }
        if (!$entry) {
            return new WP_Error('send_failed', __('Your message could not be sent. Please try again.', 'paxdesign-booking'), array('status' => 500));
        }
        if (is_array($turn['assistant'] ?? null) && !empty($turn['assistant']['id'])) {
            $payload = $this->reused_assistant_payload($session_id, $entry, $turn['assistant']);
            return $payload;
        }

        $ccs_report = $this->ingest_ccs_case_from_chat($session_id, $user_id, $user_message);
        if (is_wp_error($ccs_report)) {
            return $ccs_report;
        }

        if (
            class_exists('PAXdesign_Language_Routing')
            && PAXdesign_Language_Routing::is_live_agent_intent($user_message)
        ) {
            $lang = $customer_language !== '' ? $customer_language : 'de';
            $escalation = $live->escalate_authenticated_to_live($session_id, $user_id, $lang);
            if (is_wp_error($escalation)) {
                return $escalation;
            }
            $formatted_user = $live->format_sse_message_payload($entry, 0);
            if (!empty($formatted_user['role']) && $formatted_user['role'] === 'user') {
                $row = $live->get_session_row($session_id);
                $session_context = array(
                    'wp_user_id'    => ($row && !empty($row->wp_user_id)) ? (int) $row->wp_user_id : $user_id,
                    'customer_name' => ($row && !empty($row->customer_name)) ? (string) $row->customer_name : '',
                );
                $identity = PAXdesign_Chat_Live::resolve_customer_identity($session_context['wp_user_id'], $session_context['customer_name']);
                $formatted_user['sender_id']     = $identity['id'];
                $formatted_user['sender_name']   = $identity['name'];
                $formatted_user['sender_avatar'] = $identity['avatar'];
                $formatted_user['sender_role']   = $identity['role'];
            }
            $formatted_assistant = !empty($escalation['thanks'])
                ? $live->format_sse_message_payload($escalation['thanks'], 0)
                : array();
            if (!empty($formatted_assistant['role']) && $formatted_assistant['role'] === 'assistant') {
                $ai = PAXdesign_Chat_Live::get_ai_assistant_identity();
                $formatted_assistant['sender_name']   = $ai['name'];
                $formatted_assistant['sender_avatar'] = $ai['avatar'];
                $formatted_assistant['sender_role']   = $ai['role'];
            }
            $notice_text = class_exists('PAXdesign_Language_Routing')
                ? PAXdesign_Language_Routing::live_handoff_notice_message($lang)
                : '';
            return array(
                'session_id' => $session_id,
                'handler'    => PAXdesign_Chat_Live::HANDLER_LIVE,
                'message'    => $formatted_user,
                'assistant'  => $formatted_assistant,
                'notice'     => $notice_text,
                'handoff'    => true,
                'ccs_case'   => (class_exists('PAXdesign_Cybercrime_AI_Case') && is_array($ccs_report))
                    ? PAXdesign_Cybercrime_AI_Case::public_case_sync_payload($ccs_report)
                    : null,
            );
        }

        $ccs_ops = $this->apply_ccs_operation_turn(
            $session_id,
            $user_id,
            $user_message,
            $customer_language,
            $ccs_report,
            false,
            $assistant_client_id,
            is_array($entry) ? $entry : null
        );
        if (!empty($ccs_ops['report'])) {
            $ccs_report = $ccs_ops['report'];
        }
        if (!empty($ccs_ops['skip_llm'])) {
            $row = $live->get_session_row($session_id);
            $session_context = array(
                'wp_user_id'    => ($row && !empty($row->wp_user_id)) ? (int) $row->wp_user_id : $user_id,
                'customer_name' => ($row && !empty($row->customer_name)) ? (string) $row->customer_name : '',
            );
            $formatted_user = $live->format_sse_message_payload($entry, 0);
            $formatted_assistant = is_array($ccs_ops['assistant'] ?? null)
                ? $live->format_sse_message_payload($ccs_ops['assistant'], 0)
                : array();
            if (!empty($formatted_user['role']) && $formatted_user['role'] === 'user') {
                $identity = PAXdesign_Chat_Live::resolve_customer_identity($session_context['wp_user_id'], $session_context['customer_name']);
                $formatted_user['sender_id']     = $identity['id'];
                $formatted_user['sender_name']   = $identity['name'];
                $formatted_user['sender_avatar'] = $identity['avatar'];
                $formatted_user['sender_role']   = $identity['role'];
            }
            if (!empty($formatted_assistant['role']) && $formatted_assistant['role'] === 'assistant') {
                $ai = PAXdesign_Chat_Live::get_ai_assistant_identity();
                $formatted_assistant['sender_name']   = $ai['name'];
                $formatted_assistant['sender_avatar'] = $ai['avatar'];
                $formatted_assistant['sender_role']   = $ai['role'];
            }
            $formatted_processing = is_array($ccs_ops['processing'] ?? null)
                ? $live->format_sse_message_payload($ccs_ops['processing'], 0)
                : null;
            return array(
                'session_id' => $session_id,
                'handler'    => $live->get_handler($session_id),
                'message'    => $formatted_user,
                'assistant'  => $formatted_assistant,
                'processing' => $formatted_processing,
                'ccs_operation' => is_array($ccs_ops['operation'] ?? null) ? $ccs_ops['operation'] : null,
                'ccs_case'   => (class_exists('PAXdesign_Cybercrime_AI_Case') && is_array($ccs_report))
                    ? PAXdesign_Cybercrime_AI_Case::public_case_sync_payload($ccs_report)
                    : null,
            );
        }

        $messages = $this->prepare_authenticated_llm_messages($session_id, $user_message, $ccs_report);
        if (is_wp_error($messages)) {
            return $messages;
        }

        $live->mark_assistant_typing($session_id);
        $completion = $this->request_openai_completion($messages, $customer_language, $user_id, $session_id);
        $live->clear_assistant_typing($session_id);
        if (is_wp_error($completion)) {
            return $completion;
        }

        $already = $this->assistant_following_user($session_id, is_array($entry) ? $entry : null);
        if (is_array($already) && !empty($already['id'])) {
            $assistant = $already;
        } else {
            $assistant_extra = array('client_msg_id' => sanitize_text_field((string) $assistant_client_id));
            $assistant = $live->append_message($session_id, 'assistant', $completion['content'], $assistant_extra);
            if (is_wp_error($assistant)) {
                return $assistant;
            }
        }

        $row = $live->get_session_row($session_id);
        $session_context = array(
            'wp_user_id'    => ($row && !empty($row->wp_user_id)) ? (int) $row->wp_user_id : $user_id,
            'customer_name' => ($row && !empty($row->customer_name)) ? (string) $row->customer_name : '',
        );
        $formatted_user = $live->format_sse_message_payload($entry, 0);
        $formatted_assistant = is_array($assistant)
            ? $live->format_sse_message_payload($assistant, 0)
            : array();

        if (!empty($formatted_user['role']) && $formatted_user['role'] === 'user') {
            $identity = PAXdesign_Chat_Live::resolve_customer_identity($session_context['wp_user_id'], $session_context['customer_name']);
            $formatted_user['sender_id']     = $identity['id'];
            $formatted_user['sender_name']   = $identity['name'];
            $formatted_user['sender_avatar'] = $identity['avatar'];
            $formatted_user['sender_role']   = $identity['role'];
        }
        if (!empty($formatted_assistant['role']) && $formatted_assistant['role'] === 'assistant') {
            $ai = PAXdesign_Chat_Live::get_ai_assistant_identity();
            $formatted_assistant['sender_name']   = $ai['name'];
            $formatted_assistant['sender_avatar'] = $ai['avatar'];
            $formatted_assistant['sender_role']   = $ai['role'];
        }

        return array(
            'session_id' => $session_id,
            'handler'    => $live->get_handler($session_id),
            'message'    => $formatted_user,
            'assistant'  => $formatted_assistant,
            'ccs_operation' => is_array($ccs_ops['operation'] ?? null) ? $ccs_ops['operation'] : null,
            'ccs_case'   => (class_exists('PAXdesign_Cybercrime_AI_Case') && is_array($ccs_report))
                ? PAXdesign_Cybercrime_AI_Case::public_case_sync_payload($ccs_report)
                : null,
        );
        } finally {
            $this->unlock_customer_turn($lock);
        }
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @param string $customer_language
     * @return array{content:string,model:string}|WP_Error
     */
    private function request_openai_completion($messages, $customer_language = '', $user_id = 0, $session_id = '') {
        $api_key = $this->get_openai_api_key();
        if ($api_key === '') {
            return new WP_Error('not_configured', __('Chat is not configured yet. Please contact support.', 'paxdesign-booking'), array('status' => 503));
        }

        $openai_messages = array(
            array('role' => 'system', 'content' => $this->build_ai_system_prompt($customer_language, $user_id, $session_id)),
        );
        foreach ($messages as $msg) {
            if ($msg['role'] !== 'system') {
                $openai_messages[] = $msg;
            }
        }
        $openai_messages = $this->trim_conversation_history($openai_messages);
        $models = $this->get_model_candidates();
        $last_error = __('No response from the assistant. Please try again.', 'paxdesign-booking');

        foreach ($models as $model) {
            $payload = wp_json_encode($this->build_openai_payload($model, $openai_messages, false));
            if ($payload === false) {
                continue;
            }
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'timeout' => 90,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => $payload,
            ));
            if (is_wp_error($response)) {
                $last_error = __('Connection error. Please try again.', 'paxdesign-booking');
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($code >= 200 && $code < 300 && is_array($body)) {
                $content = isset($body['choices'][0]['message']['content'])
                    ? trim((string) $body['choices'][0]['message']['content'])
                    : '';
                if ($content !== '') {
                    update_option('paxdesign_chat_last_model', $model, false);
                    delete_option('paxdesign_chat_last_error');
                    return array('content' => $content, 'model' => $model);
                }
            }
            $last_error = $this->format_openai_error_message($code, $body, $model);
            if ($code === 401) {
                break;
            }
        }

        update_option('paxdesign_chat_last_error', $last_error, false);
        return new WP_Error('openai_failed', $last_error, array('status' => 502));
    }

    /**
     * @return array<int, array{role:string,content:string}>
     */
    private function build_openai_messages_from_session($session_id) {
        if (!class_exists('PAXdesign_Message_Store')) {
            return array();
        }

        $rows = PAXdesign_Message_Store::all_messages($session_id, 'customer');
        $epoch = $this->ccs_conversation_epoch($session_id);
        $messages = array();
        foreach ($rows as $row) {
            if ($epoch > 0 && isset($row['ts']) && absint($row['ts']) > 0 && absint($row['ts']) < $epoch) {
                continue;
            }
            $role = isset($row['role']) ? (string) $row['role'] : '';
            if ($role === 'admin') {
                $role = 'assistant';
            }
            if (!in_array($role, array('user', 'assistant', 'system'), true)) {
                continue;
            }
            $content = isset($row['content']) ? trim((string) $row['content']) : '';
            if ($content === '') {
                continue;
            }
            $messages[] = array(
                'role'    => $role,
                'content' => $content,
            );
        }

        return $messages;
    }

    /**
     * Keep CCS conversation history when preparing the model prompt.
     * Never fall back to a single isolated user message while a case is active.
     *
     * @param string                         $session_id
     * @param string                         $user_message
     * @param array<string, mixed>|WP_Error|null $ccs_report
     * @return array<int, array{role:string,content:string}>|WP_Error
     */
    private function prepare_authenticated_llm_messages($session_id, $user_message, $ccs_report = null) {
        $new_case = class_exists('PAXdesign_Cybercrime_AI_Case')
            && PAXdesign_Cybercrime_AI_Case::is_explicit_new_case_request($user_message);
        if ($new_case) {
            $reference = '';
            if (is_array($ccs_report) && !empty($ccs_report['reference_id'])) {
                $reference = sanitize_text_field((string) $ccs_report['reference_id']);
            }
            $prompt = 'The customer explicitly started a NEW Cybercrime Support case'
                . ($reference !== '' ? ' ' . $reference : '')
                . '. Do not reuse any previous CCS reference, case data, uploaded files, workflow progress, or earlier conversation about the old case. Start Identity (step 1 of 4) on this new draft. Customer message: '
                . trim((string) $user_message);
            return $this->validate_messages(array(
                array('role' => 'user', 'content' => $prompt),
            ));
        }

        $history = $this->trim_conversation_history($this->build_openai_messages_from_session($session_id), 24);
        $validated = $this->validate_messages($history);
        if (!is_wp_error($validated) && !empty($validated)) {
            return $validated;
        }

        $fallback = trim((string) $user_message);
        if (is_array($ccs_report) && !empty($ccs_report['reference_id'])) {
            $reference = sanitize_text_field((string) $ccs_report['reference_id']);
            $status = sanitize_key((string) ($ccs_report['status'] ?? ''));
            $fallback = 'Continue CCS case ' . $reference
                . ($status !== '' ? ' (status: ' . $status . ')' : '')
                . ' in this same conversation. Do not greet as a new chat. Do not restart intake. Customer message: '
                . trim((string) $user_message);
        }

        return $this->validate_messages(array(
            array('role' => 'user', 'content' => $fallback),
        ));
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
        $customer_language = isset($context['customer_language']) ? sanitize_key((string) $context['customer_language']) : '';
        $staff_language = isset($context['staff_language']) ? sanitize_key((string) $context['staff_language']) : '';

        if ($staff_language === '' && class_exists('PAXdesign_Language_Routing')) {
            foreach (array_reverse($messages) as $msg) {
                if (!is_array($msg) || ($msg['role'] ?? '') !== 'admin') {
                    continue;
                }
                $staff_language = PAXdesign_Language_Routing::detect_text_language((string) ($msg['content'] ?? ''));
                if ($staff_language !== '') {
                    break;
                }
            }
        }
        if ($customer_language === '' && class_exists('PAXdesign_Language_Routing')) {
            $customer_language = PAXdesign_Language_Routing::detect_text_language($customer_text);
        }

        $reply_language = 'de';
        if ($staff_language === 'ar' || $customer_language === 'ar') {
            $reply_language = 'ar';
        } elseif ($staff_language === 'en' || $customer_language === 'en') {
            $reply_language = 'en';
        }

        if ($reply_language === 'ar') {
            $system = 'أنت مساعد صامت لموظف دعم PAXdesign (تصميم مواقع، SEO، أنظمة حجز). '
                . 'الموظف يكتب بنفسه — لا ترسل أبداً رسائل للعميل. '
                . 'أنشئ 2–3 اقتراحات رد قصيرة واحترافية على آخر رسالة من العميل. '
                . 'اكتب كل الاقتراحات بالعربية الطبيعية فقط. لا تستخدم الألمانية أو الإنجليزية. '
                . 'كل اقتراح بحد أقصى جملتين، جاهز للإرسال، ودود ومحدد. '
                . 'أجب فقط بصيغة JSON: {"suggestions":["…","…"]} بدون Markdown.';
        } elseif ($reply_language === 'en') {
            $system = 'You are a silent assistant for the PAXdesign live support agent (web design, SEO, booking systems). '
                . 'The agent writes themselves — you NEVER send messages to the customer. '
                . 'Create exactly 2–3 short, professional reply suggestions for the latest customer message. '
                . 'Write every suggestion in natural English only. '
                . 'Each suggestion max 2 sentences, ready to send, friendly and concrete. '
                . 'Reply ONLY as JSON: {"suggestions":["…","…"]} without Markdown.';
        } else {
            $system = 'Du bist ein stiller Assistent für den Live-Support-Mitarbeiter von PAXdesign (Webdesign, SEO, Buchungssysteme). '
                . 'Der Mitarbeiter schreibt selbst — du sendest NIEMALS Nachrichten an den Kunden. '
                . 'Erstelle genau 2–3 kurze, professionelle Antwortvorschläge auf die letzte Kundennachricht. '
                . 'Antworte ausschließlich auf Deutsch. '
                . 'Jeder Vorschlag max. 2 Sätze, direkt sendbar, freundlich und konkret. '
                . 'Antworte NUR als JSON: {"suggestions":["…","…"]} ohne Markdown.';
        }

        $user_parts = array();
        if ($customer_name !== '') {
            $user_parts[] = $reply_language === 'ar'
                ? 'اسم العميل: ' . $customer_name
                : ($reply_language === 'en' ? 'Customer name: ' . $customer_name : 'Kundenname: ' . $customer_name);
        }
        if ($service !== '') {
            $user_parts[] = $reply_language === 'ar'
                ? 'الموضوع المكتشف: ' . $service
                : ($reply_language === 'en' ? 'Detected topic: ' . $service : 'Erkanntes Thema: ' . $service);
        }
        if ($reply_language !== 'de') {
            $user_parts[] = $reply_language === 'ar' ? 'لغة المحادثة: العربية' : 'Conversation language: English';
        }
        if (!empty($history)) {
            $user_parts[] = $reply_language === 'ar' ? 'سجل المحادثة (مختصر):' : ($reply_language === 'en' ? 'Conversation excerpt:' : 'Bisheriger Verlauf (Auszug):');
            foreach ($history as $turn) {
                $label = $turn['role'] === 'user'
                    ? ($reply_language === 'ar' ? 'العميل' : ($reply_language === 'en' ? 'Customer' : 'Kunde'))
                    : ($reply_language === 'ar' ? 'الدعم' : ($reply_language === 'en' ? 'Support' : 'Support'));
                $user_parts[] = $label . ': ' . $turn['content'];
            }
        }
        $user_parts[] = ($reply_language === 'ar'
            ? 'آخر رسالة من العميل (اقترح ردوداً لها): '
            : ($reply_language === 'en'
                ? 'Latest customer message (suggest replies for): '
                : 'Letzte Kundennachricht (Antwortvorschläge dafür): '))
            . ($customer_text !== '' ? $customer_text : ($reply_language === 'ar' ? '[صورة بدون نص]' : ($reply_language === 'en' ? '[Image without text]' : '[Bild/Foto ohne Text]')));

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
        header('X-Accel-Buffering: no');
        if (function_exists('litespeed_finish_request')) {
            header('X-LiteSpeed-Cache-Control: no-cache');
        }
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            ob_end_clean();
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
        if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
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
        register_setting('paxdesign_booking_settings', 'paxdesign_chat_quick_links', array(
            'sanitize_callback' => function ($value) {
                if (is_array($value)) {
                    PAXdesign_Chat_Quick_Links::save_links($value);
                    return get_option(PAXdesign_Chat_Quick_Links::OPTION_KEY, '');
                }
                $decoded = json_decode((string) $value, true);
                if (is_array($decoded)) {
                    PAXdesign_Chat_Quick_Links::save_links($decoded);
                    return get_option(PAXdesign_Chat_Quick_Links::OPTION_KEY, '');
                }
                return (string) $value;
            },
        ));
    }
}
