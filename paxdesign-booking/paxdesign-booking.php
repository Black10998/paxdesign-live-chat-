<?php
/*
Plugin Name: PAXdesign Booking System
Description: Professional booking system with minimal chat-style interface and team management
Version: 3.174.9
Author: PAXdesign
Author URI: https://paxdesign.at
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: paxdesign-booking
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent double-loading if two copies of the plugin are somehow active
if (defined('PAXDESIGN_BOOKING_VERSION')) {
    return;
}

// Define plugin constants
define('PAXDESIGN_BOOKING_VERSION', '3.174.9');
define('PAXDESIGN_BOOKING_DB_VERSION', '2.1');
define('PAXDESIGN_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAXDESIGN_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-litespeed-compat.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-hostinger-compat.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-admin-compat.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-update-checker.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-email-templates.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-service-icons.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-chat-knowledge.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-chat-log.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-api-time.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-db.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-ajax-json-guard.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-message-store.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-chat-live.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-live-chat-shortcode.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-web-push.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-live-chat-pwa.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-apns.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-asc-bootstrap.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-device-sessions.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-live-chat-permissions.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-team-messaging.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-team-registry.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-chat-event-bus.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-language-routing.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-platform-store.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-auth-log.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-live-chat-mobile-api.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-chat.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-chat-icons.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-chat-quick-links.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-link-scanner.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-link-scan-service.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/class-paxdesign-settings-admin.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/auth/class-paxdesign-auth-module.php';
require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/class-paxdesign-customer-platform.php';
PAXdesign_Booking_Update_Checker::init();
PAXdesign_DB::init();
PAXdesign_Ajax_JSON_Guard::init();
PAXdesign_Message_Store::init();

add_filter('upload_mimes', function ($mimes) {
    $mimes['m4a'] = 'audio/mp4';
    $mimes['aac'] = 'audio/aac';
    $mimes['caf'] = 'audio/x-caf';
    return $mimes;
});

PAXdesign_Settings_Admin::init();
PAXdesign_Admin_Compat::init();
PAXdesign_Chat_Log::get_instance();
PAXdesign_Chat_Live::get_instance();
PAXdesign_Chat_Quick_Links::init();
PAXdesign_Link_Scan_Service::init();
PAXdesign_Live_Chat_Shortcode::init();
PAXdesign_Live_Chat_PWA::init();
PAXdesign_APNS::init();
PAXdesign_Live_Chat_Permissions::init();
PAXdesign_Live_Chat_Mobile_API::init();
PAXdesign_Auth_Module::init();
PAXdesign_Customer_Platform::init();
PAXdesign_Auth_Log::init();

/**
 * Main Plugin Class
 */
class PAXdesign_Booking {
    
    private static $instance = null;
    private $current_email_alt_body = '';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 9999);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_chat_assets'), 9999);
        add_action('wp_footer', array($this, 'render_booking_widget'));
        add_action('wp_ajax_paxdesign_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_paxdesign_submit_booking', array($this, 'handle_booking_submission'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_paxdesign_update_team_settings', array($this, 'handle_team_settings_update'));
        add_action('wp_ajax_paxdesign_send_test_email', array($this, 'handle_test_email'));
        add_action('wp_ajax_paxdesign_get_team_members', array($this, 'handle_get_team_members'));
        add_action('wp_ajax_nopriv_paxdesign_get_team_members', array($this, 'handle_get_team_members'));
        add_action('plugins_loaded', array($this, 'maybe_upgrade_database'));
        // Configure SMTP if enabled
        add_action('phpmailer_init', array($this, 'configure_smtp'));
    }

    /**
     * Configure PHPMailer with SMTP settings when SMTP is enabled.
     * Hooked into phpmailer_init so it applies to every wp_mail() call.
     */
    public function configure_smtp($phpmailer) {
        if (!get_option('paxdesign_smtp_enabled', false)) {
            return;
        }

        $host     = get_option('paxdesign_smtp_host', '');
        $port     = (int) get_option('paxdesign_smtp_port', 587);
        $user     = get_option('paxdesign_smtp_user', '');
        $pass     = get_option('paxdesign_smtp_pass', '');
        $enc      = get_option('paxdesign_smtp_encryption', 'tls');
        $from     = get_option('paxdesign_smtp_from_email', 'info@paxdesign.at');
        $fromname = get_option('paxdesign_smtp_from_name', 'PAXdesign');

        if (empty($host) || empty($user)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = $port;
        $phpmailer->Username   = $user;
        $phpmailer->Password   = $pass;
        $phpmailer->SMTPSecure = $enc === 'ssl' ? 'ssl' : 'tls';
        $phpmailer->From       = $from;
        $phpmailer->FromName   = $fromname;
        $phpmailer->CharSet    = 'UTF-8';
    }

    /**
     * Public AJAX endpoint: returns all team members with their current
     * availability and enabled state. Called by the frontend widget on every
     * open so availability changes in the admin are reflected immediately.
     *
     * Returns ALL members (include_disabled=true) so the JS can apply
     * enabled/availability filtering itself — this prevents a race where a
     * member toggled back to enabled would be missing from the response.
     * Sensitive fields (email) are stripped before sending.
     */
    public function handle_get_team_members() {
        $members = $this->get_team_members(true); // include disabled
        $public  = array();
        foreach ($members as $key => $m) {
            $public[$key] = array(
                'name'         => $m['name'],
                'role'         => $m['role'],
                'role_en'      => isset($m['role_en']) ? $m['role_en'] : '',
                'image'        => $m['image'],
                'has_services' => isset($m['has_services']) ? (bool) $m['has_services'] : false,
                'is_founder'   => !empty($m['is_founder']),
                'availability' => isset($m['availability']) ? $m['availability'] : 'available',
                'enabled'      => isset($m['enabled'])      ? (bool) $m['enabled']      : true,
                'order'        => isset($m['order'])         ? (int)  $m['order']        : 999,
            );
        }
        // Sort by order so JS receives them in display order
        uasort($public, function($a, $b) { return $a['order'] - $b['order']; });
        wp_send_json_success(array_values($public) === $public ? $public : $public);
    }

    /**
     * Send a test email to verify SMTP configuration.
     */
    public function handle_test_email() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $to = isset($_POST['to']) ? sanitize_email($_POST['to']) : get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');

        $sent = wp_mail(
            $to,
            'PAXdesign Booking – SMTP Test',
            "Diese E-Mail bestätigt, dass Ihre SMTP-Konfiguration korrekt funktioniert.\n\nPAXdesign Booking System",
            array('Content-Type: text/plain; charset=UTF-8', 'From: ' . $this->get_from_header())
        );

        if ($sent) {
            wp_send_json_success(array('message' => 'Test-E-Mail erfolgreich gesendet an ' . $to));
        } else {
            global $phpmailer;
            $error = isset($phpmailer) && method_exists($phpmailer, 'ErrorInfo') ? $phpmailer->ErrorInfo : 'Unbekannter Fehler';
            wp_send_json_error(array('message' => 'Senden fehlgeschlagen: ' . $error));
        }
    }
    
    public function enqueue_assets() {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'paxdesign-booking-styles',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/css/booking-styles.css',
            array(),
            PAXDESIGN_BOOKING_VERSION
        );
        
        wp_enqueue_script(
            'paxdesign-booking-script',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/booking-script.js',
            array('jquery'),
            PAXDESIGN_BOOKING_VERSION,
            true
        );
        
        wp_localize_script('paxdesign-booking-script', 'paxdesignBooking', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('paxdesign_booking_nonce'),
            'teamMembers' => $this->get_team_members(),
            'services' => $this->get_services(),
            'serviceNameMap' => $this->get_service_name_map(),
            'serviceIcons' => PAXdesign_Service_Icons::get_all(),
        ));

        wp_add_inline_style(
            'paxdesign-booking-styles',
            '#paxdesign-booking-root.paxdesign-booking,#paxdesign-booking-root.paxdesign-booking-wrapper{'
            . '--accent:#363636!important;--primary:#ffffff!important;--secondary:#7e7e7e!important;'
            . '--global--color-primary:#ffffff!important;--global--color-secondary:#7e7e7e!important;'
            . '--global--color-accent:#363636!important;--wp--preset--color--primary:#ffffff!important;'
            . '--wp--preset--color--secondary:#7e7e7e!important;--wp--preset--color--accent:#363636!important;'
            . '--e-global-color-primary:#ffffff!important;--e-global-color-secondary:#7e7e7e!important;'
            . '--e-global-color-text:#ffffff!important;--e-global-color-accent:#363636!important;'
            . '--theme-color:#363636!important;--link-color:#f3f6fd!important;'
            . '--button-bg:#292929!important;--button-color:#f3f6fd!important;'
            . 'color:#ffffff!important;'
            . '}'
        );
    }

    public function enqueue_chat_assets() {
        if (is_admin()) {
            return;
        }
        PAXdesign_Chat::get_instance()->enqueue_assets();
    }
    
    public function get_team_members($include_disabled = false) {
        // Get all team members with their base data
        $all_members = array(
            'ahmad' => array(
                'name' => 'Ahmad Alkhalaf',
                'role' => 'Gründer & Geschäftsführer – PAXDesign',
                'role_en' => 'Founder & CEO – PAXDesign',
                'email' => get_option('paxdesign_booking_email_ahmad', 'info@paxdesign.at'),
                'image' => PAXdesign_Chat_Live::DEFAULT_AGENT_AVATAR,
                'has_services' => true,
                'is_founder' => true,
            ),
        );
        
        // Get settings from database
        $settings = get_option('paxdesign_booking_team_settings', array());
        
        // Apply settings to each member
        foreach ($all_members as $key => &$member) {
            if (isset($settings[$key])) {
                // Convert to boolean explicitly to handle string/int values
                $member['enabled'] = isset($settings[$key]['enabled']) ? (bool)$settings[$key]['enabled'] : true;
                $member['order'] = isset($settings[$key]['order']) ? (int)$settings[$key]['order'] : 999;
                $member['availability'] = isset($settings[$key]['availability']) ? $settings[$key]['availability'] : 'available';
            } else {
                $member['enabled'] = true;
                $member['order'] = 999;
                $member['availability'] = 'available';
            }
        }
        
        // Filter out disabled members unless requested
        if (!$include_disabled) {
            $all_members = array_filter($all_members, function($member) {
                // Explicitly check for true boolean value
                return !empty($member['enabled']) && $member['enabled'] === true;
            });
        }
        
        // Sort by order
        uasort($all_members, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        return $all_members;
    }
    
    public function get_services() {
        $catalog = include PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/service-features.php';
        $feature_map = isset($catalog['features']) ? $catalog['features'] : array();
        $category_map = isset($catalog['categories']) ? $catalog['categories'] : array();

        $services = array(
            'website' => array(
                'name' => 'Website',
                'description' => 'Moderne, responsive Website mit professionellem Design und SEO-Optimierung für maximale Online-Präsenz',
            ),
            'webapp' => array(
                'name' => 'Web App',
                'description' => 'Individuell entwickelte Webanwendung zur Digitalisierung Ihrer Geschäftsprozesse mit benutzerfreundlicher Oberfläche',
            ),
            'android' => array(
                'name' => 'Android App',
                'description' => 'Native Android-App mit optimaler Performance und professioneller Play Store Veröffentlichung',
            ),
            'ios' => array(
                'name' => 'iOS App',
                'description' => 'Native iOS-App entwickelt mit Swift, optimiert für iPhone und iPad mit App Store Integration',
            ),
            'crossplatform' => array(
                'name' => 'iOS + Android',
                'description' => 'Effiziente Cross-Platform-Lösung für maximale Reichweite mit einheitlichem Nutzererlebnis',
                'popular' => true
            ),
            'androidtv' => array(
                'name' => 'Android TV',
                'description' => 'Speziell für TV-Geräte optimierte Anwendung mit Fernbedienungssteuerung und 4K-Streaming',
            ),
            'security' => array(
                'name' => 'IT-Sicherheit',
                'description' => 'Umfassende Sicherheitsanalyse mit Penetration Testing und DSGVO-Konformitätsprüfung',
            ),
            'backend' => array(
                'name' => 'Backend System',
                'description' => 'Hochperformante Backend-Architektur mit REST/GraphQL APIs und optimiertem Datenbankdesign',
            ),
            'devops' => array(
                'name' => 'Server & DevOps',
                'description' => 'Professionelle Cloud-Infrastruktur mit automatisierter CI/CD Pipeline und kontinuierlichem Monitoring',
            ),
            'enterprise' => array(
                'name' => 'Enterprise',
                'description' => 'Maßgeschneiderte Komplettlösung mit dediziertem Team, 24/7 Support und SLA-Garantie',
                'premium' => true
            ),
            'aiautomation' => array(
                'name' => 'AI Automation',
                'description' => 'KI-Lösungen zur Automatisierung wiederkehrender Aufgaben und zur Steigerung der Effizienz',
            ),
            'aichatbot' => array(
                'name' => 'AI Chatbot',
                'description' => 'Intelligenter Assistent für Website oder Shop mit automatischen Antworten und Weiterleitung',
            ),
            'ecommerce' => array(
                'name' => 'E-Commerce Shop',
                'description' => 'Professioneller Online-Shop mit Zahlungssystem, Produktverwaltung und conversion-optimierten Seiten',
            ),
            'maintenance' => array(
                'name' => 'Monthly Maintenance',
                'description' => 'Monatlicher Website-Service für Sicherheit, Geschwindigkeit, Updates und technischen Support',
            ),
            'pagespeed' => array(
                'name' => 'Website Speed Optimization',
                'description' => 'Umfassende Performance-Optimierung für schnellere Ladezeiten und bessere Nutzererfahrung',
            ),
            'uiux' => array(
                'name' => 'UI/UX Design',
                'description' => 'Professionelle, benutzerfreundliche Interfaces für Web und Mobile',
            ),
            'branding' => array(
                'name' => 'Branding & Identity',
                'description' => 'Ganzheitliche visuelle Identität mit Logo, Farben, Typografie und Markenauftritt',
            ),
            'crm' => array(
                'name' => 'CRM System',
                'description' => 'Maßgeschneidertes System zur Verwaltung von Kunden, Aufträgen und Follow-ups',
            ),
            'bookingsystem' => array(
                'name' => 'Appointment Booking System',
                'description' => 'Intelligentes Buchungssystem für Praxen, Büros und Dienstleistungen mit automatischen Benachrichtigungen',
            ),
            'pwa' => array(
                'name' => 'Website to App',
                'description' => 'Wandeln Sie Ihre Website in eine installierbare App mit nativer App-Erfahrung um',
            ),
            'analytics' => array(
                'name' => 'Data Analytics & Reports',
                'description' => 'Intelligente Dashboards für Umsatz, Kunden, Bestellungen und Website-Traffic',
            ),
            'gdpr' => array(
                'name' => 'GDPR & Cookie Setup',
                'description' => 'Rechtliche und technische Einrichtung von Datenschutz- und Cookie-Richtlinien',
            ),
            'secflash' => array(
                'name' => 'Military Flash Protection',
                'description' => 'Militärisch inspirierter Schutz gegen unbefugtes Kopieren, Clonen und Manipulation',
            ),
            'seclayers' => array(
                'name' => 'Encrypted Protection Layers',
                'description' => 'Verschlüsselte Schutzschichten für JavaScript, CSS und interne Systemlogik',
            ),
            'sectamper' => array(
                'name' => 'Anti-Tamper Shield',
                'description' => 'Erkennt unautorisierte Änderungen an Dateien, Builds oder Systemkomponenten',
            ),
            'secruntime' => array(
                'name' => 'Secure Runtime Mode',
                'description' => 'Sicherer Seitenbetrieb mit reduzierter Datenoffenlegung in der Oberfläche',
            ),
            'secobfusc' => array(
                'name' => 'Obfuscated Source Protection',
                'description' => 'Minifizierung und Verschleierung Ihres Quellcodes gegen Reverse Engineering',
            ),
            'sectoken' => array(
                'name' => 'Token-Based Asset Access',
                'description' => 'Sensible Dateien und Assets nur über temporäre, signierte Tokens',
            ),
            'seclicense' => array(
                'name' => 'Server-Side License Verification',
                'description' => 'Lizenzprüfung auf dem Server — geschützte Funktionen erst nach validierter Freigabe',
            ),
            'secintegrity' => array(
                'name' => 'Integrity Check',
                'description' => 'Hash- und Checksum-Validierung stellt unveränderte Dateien sicher',
            ),
        );

        foreach ($services as $key => &$service) {
            $service['icon'] = $key;
            if (isset($feature_map[$key])) {
                $service['features'] = $feature_map[$key];
            }
            if (isset($category_map[$key])) {
                $service['category'] = $category_map[$key];
            }
        }
        unset($service);

        return $services;
    }

    /**
     * Map display names from the pricing page (data-service) to internal service keys.
     */
    public function get_service_name_map() {
        $map = array();
        foreach ($this->get_services() as $key => $service) {
            $map[$service['name']] = $key;
        }
        return $map;
    }

    /**
     * Resolve a service key from a display name or key string.
     */
    public function resolve_service_key($value) {
        if (empty($value)) {
            return '';
        }
        $services = $this->get_services();
        if (isset($services[$value])) {
            return $value;
        }
        $map = $this->get_service_name_map();
        if (isset($map[$value])) {
            return $map[$value];
        }
        return '';
    }

    /**
     * Ensure database schema stays current across plugin updates.
     * Idempotent: checks each column individually and never deletes data.
     */
    public function maybe_upgrade_database() {
        paxdesign_booking_upgrade_database();
        PAXdesign_Message_Store::maybe_upgrade();
        PAXdesign_Link_Scan_Service::maybe_upgrade();
        paxdesign_booking_upgrade_live_notify_defaults();
        if (class_exists('PAXdesign_Team_Messaging')) {
            PAXdesign_Team_Messaging::maybe_reconcile_store();
        }
        paxdesign_booking_upgrade_auth_page();
    }

    /**
     * Parse service details submitted from the booking widget.
     */
    private function parse_service_details($raw) {
        if (empty($raw)) {
            return null;
        }

        if (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode(wp_unslash($raw), true);
        }

        if (!is_array($data)) {
            return null;
        }

        $features = array();
        if (!empty($data['features']) && is_array($data['features'])) {
            foreach ($data['features'] as $feature) {
                $feature = sanitize_text_field($feature);
                if ($feature !== '') {
                    $features[] = $feature;
                }
            }
        }

        $details = array(
            'name'        => sanitize_text_field(isset($data['name']) ? $data['name'] : ''),
            'card_id'     => sanitize_key(isset($data['cardId']) ? $data['cardId'] : (isset($data['card_id']) ? $data['card_id'] : '')),
            'description' => sanitize_textarea_field(isset($data['description']) ? $data['description'] : ''),
            'features'    => $features,
            'category'    => sanitize_text_field(isset($data['category']) ? $data['category'] : ''),
        );

        if ($details['name'] === '' && $details['description'] === '' && empty($details['features'])) {
            return null;
        }

        return $details;
    }

    /**
     * Resolve full service details for display, storage, and emails.
     */
    private function get_booking_service_details($booking_data) {
        if (!empty($booking_data['service_details']) && is_array($booking_data['service_details'])) {
            return $booking_data['service_details'];
        }

        if (empty($booking_data['service'])) {
            return null;
        }

        $services = $this->get_services();
        if (!isset($services[$booking_data['service']])) {
            return null;
        }

        $service = $services[$booking_data['service']];

        return array(
            'name'        => $service['name'],
            'card_id'     => $booking_data['service'],
            'description' => isset($service['description']) ? $service['description'] : '',
            'features'    => isset($service['features']) ? $service['features'] : array(),
            'category'    => isset($service['category']) ? $service['category'] : '',
        );
    }

    /**
     * Plain-text service block for admin and customer emails.
     */
    private function format_service_details_block($booking_data) {
        $details = $this->get_booking_service_details($booking_data);
        if (!$details) {
            return '';
        }

        $lines   = array('GEWÄHLTER SERVICE:', $details['name']);

        if (!empty($details['category'])) {
            $lines[] = 'Kategorie: ' . $details['category'];
        }

        if (!empty($details['card_id'])) {
            $lines[] = 'Service-ID: ' . $details['card_id'];
        }

        if (!empty($details['description'])) {
            $lines[] = '';
            $lines[] = 'Beschreibung:';
            $lines[] = $details['description'];
        }

        if (!empty($details['features'])) {
            $lines[] = '';
            $lines[] = 'Leistungsumfang:';
            foreach ($details['features'] as $feature) {
                $lines[] = '• ' . $feature;
            }
        }

        return implode("\n", $lines) . "\n\n";
    }
    
    public function render_booking_widget() {
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/booking-widget.php';
    }
    
    public function handle_booking_submission() {
        check_ajax_referer('paxdesign_booking_nonce', 'nonce');
        
        $booking_data = array(
            'member'  => isset($_POST['member'])  ? sanitize_text_field($_POST['member'])       : '',
            'service' => isset($_POST['service']) ? sanitize_text_field($_POST['service'])      : '',
            'date'    => isset($_POST['date'])    ? sanitize_text_field($_POST['date'])         : '',
            'time'    => isset($_POST['time'])    ? sanitize_text_field($_POST['time'])         : '',
            'name'    => isset($_POST['name'])    ? sanitize_text_field($_POST['name'])         : '',
            'email'   => isset($_POST['email'])   ? sanitize_email($_POST['email'])             : '',
            'phone'   => isset($_POST['phone'])   ? sanitize_text_field($_POST['phone'])        : '',
            'purpose' => isset($_POST['purpose']) ? sanitize_text_field($_POST['purpose'])      : '',
            'message' => isset($_POST['message']) ? sanitize_textarea_field($_POST['message'])  : '',
        );

        if (isset($_POST['service_details'])) {
            $booking_data['service_details'] = $this->parse_service_details($_POST['service_details']);
        }

        if (!empty($booking_data['service_details']['card_id']) && empty($booking_data['service'])) {
            $booking_data['service'] = $this->resolve_service_key($booking_data['service_details']['card_id']);
        }
        
        if (empty($booking_data['member']) || empty($booking_data['date']) || 
            empty($booking_data['time']) || empty($booking_data['name']) || 
            empty($booking_data['email'])) {
            wp_send_json_error(array('message' => 'Bitte füllen Sie alle Pflichtfelder aus.'));
            return;
        }

        if (!empty($booking_data['service'])) {
            $resolved_service = $this->resolve_service_key($booking_data['service']);
            if (empty($resolved_service)) {
                wp_send_json_error(array('message' => 'Ungültiger Service.'));
                return;
            }
            $booking_data['service'] = $resolved_service;
        }

        if (!empty($booking_data['service_details']) && empty($booking_data['service_details']['card_id'])) {
            $booking_data['service_details']['card_id'] = $booking_data['service'];
        }

        // Validate that the selected member exists
        $team_members = $this->get_team_members();
        if (!isset($team_members[$booking_data['member']])) {
            wp_send_json_error(array('message' => 'Ungültiger Ansprechpartner.'));
            return;
        }
        
        $member_info = $team_members[$booking_data['member']];
        
        // Save booking first — success must not depend on email delivery
        if (!$this->save_booking_to_database($booking_data)) {
            wp_send_json_error(array('message' => 'Buchung konnte nicht gespeichert werden. Bitte versuchen Sie es erneut oder kontaktieren Sie uns direkt.'));
            return;
        }

        // Attempt to send notification email; log failure but do not block the booking
        $email_sent = $this->send_booking_email($booking_data, $member_info);
        if (!$email_sent) {
            paxdesign_booking_log_error('PAXdesign Booking: wp_mail() failed for booking by ' . $booking_data['email']);
        }

        wp_send_json_success(array(
            'message'     => 'Termin erfolgreich gebucht!',
            'booking_data' => $booking_data,
            'member_info'  => $member_info,
        ));
    }
    
    /**
     * Build a shared formatted date + service string used in both emails.
     */
    private function build_email_parts($booking_data) {
        $date_obj      = DateTime::createFromFormat('Y-m-d', $booking_data['date']);
        $formatted_date = $date_obj ? $date_obj->format('d.m.Y') : $booking_data['date'];

        $service_name = '';
        $service_details = $this->get_booking_service_details($booking_data);
        if ($service_details && !empty($service_details['name'])) {
            $service_name = $service_details['name'];
        } elseif (!empty($booking_data['service'])) {
            $services = $this->get_services();
            if (isset($services[$booking_data['service']])) {
                $service_name = $services[$booking_data['service']]['name'];
            }
        }

        $purpose_labels = array(
            'beratung'  => 'Beratungsgespräch',
            'projekt'   => 'Projektbesprechung',
            'support'   => 'Technischer Support',
            'demo'      => 'Produkt-Demo',
            'angebot'   => 'Angebotserstellung',
            'sonstiges' => 'Sonstiges',
        );
        $purpose_label = isset($purpose_labels[$booking_data['purpose']])
            ? $purpose_labels[$booking_data['purpose']]
            : $booking_data['purpose'];

        return array(
            'date'             => $formatted_date,
            'service_name'     => $service_name,
            'service_details'  => $service_details,
            'purpose_label'    => $purpose_label,
        );
    }

    /**
     * Resolve the From header using the configured SMTP from-email/name,
     * falling back to the notification address if SMTP is not configured.
     */
    private function get_from_header() {
        $from_email = get_option('paxdesign_smtp_from_email', '');
        $from_name  = get_option('paxdesign_smtp_from_name', 'PAXdesign');

        if (empty($from_email)) {
            // Fall back to the admin notification address so there is always
            // a consistent, non-hardcoded sender.
            $from_email = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
        }

        return $from_name . ' <' . $from_email . '>';
    }

    /**
     * Send HTML e-mail with plain-text fallback.
     */
    private function send_html_mail($to, $subject, $html, $plain, $headers) {
        $this->current_email_alt_body = $plain;
        add_action('phpmailer_init', array($this, 'set_email_alt_body'), 20);

        $mail_headers = $headers;
        $has_content_type = false;
        foreach ($mail_headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $has_content_type = true;
                break;
            }
        }
        if (!$has_content_type) {
            $mail_headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $sent = wp_mail($to, $subject, $html, $mail_headers);

        remove_action('phpmailer_init', array($this, 'set_email_alt_body'), 20);
        $this->current_email_alt_body = '';

        return $sent;
    }

    public function set_email_alt_body($phpmailer) {
        if (!empty($this->current_email_alt_body)) {
            $phpmailer->AltBody = $this->current_email_alt_body;
        }
    }

    /**
     * Send admin notification + customer confirmation.
     * Both emails are sent independently — one failing does not block the other.
     */
    private function send_booking_email($booking_data, $member_info) {
        $parts = $this->build_email_parts($booking_data);

        $admin_plain = "Neue Terminbuchung bei PAXdesign\n"
            . "=====================================\n\n"
            . "ANSPRECHPARTNER:\n"
            . $member_info['name'] . " – " . $member_info['role'] . "\n\n"
            . $this->format_service_details_block($booking_data)
            . "TERMIN:\n"
            . "Datum:   " . $parts['date'] . "\n"
            . "Uhrzeit: " . $booking_data['time'] . " Uhr\n\n"
            . "KUNDE:\n"
            . "Name:     " . $booking_data['name'] . "\n"
            . "E-Mail:   " . $booking_data['email'] . "\n"
            . "Telefon:  " . $booking_data['phone'] . "\n\n"
            . "DETAILS:\n"
            . "Zweck:    " . $parts['purpose_label'] . "\n"
            . "Nachricht:\n" . $booking_data['message'] . "\n";

        $admin_html = PAXdesign_Email_Templates::render_admin_booking($booking_data, $member_info, $parts);

        $admin_headers = array(
            'From: ' . $this->get_from_header(),
            'Reply-To: ' . $booking_data['name'] . ' <' . $booking_data['email'] . '>',
        );

        $notification_email = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');

        $member_email = isset($member_info['email']) ? $member_info['email'] : '';
        if ($member_email && $member_email !== $notification_email) {
            $admin_headers[] = 'CC: ' . $member_info['name'] . ' <' . $member_email . '>';
        }

        $admin_sent = $this->send_html_mail(
            $notification_email,
            'Neue Terminbuchung: ' . $booking_data['name'],
            $admin_html,
            $admin_plain,
            $admin_headers
        );

        if (!$admin_sent) {
            paxdesign_booking_log_error('PAXdesign Booking: admin notification wp_mail() failed for booking by ' . $booking_data['email']);
        }

        $this->send_customer_confirmation($booking_data, $member_info, $parts);

        return $admin_sent;
    }

    private function send_customer_confirmation($booking_data, $member_info, $parts = null) {
        if ($parts === null) {
            $parts = $this->build_email_parts($booking_data);
        }

        $service_block = $this->format_service_details_block($booking_data);
        if ($service_block === '' && !empty($parts['service_name'])) {
            $service_block = "Service:          " . $parts['service_name'] . "\n";
        } else {
            $service_block = str_replace(
                array('GEWÄHLTER SERVICE:', 'Kategorie:', 'Service-ID:', 'Beschreibung:', 'Leistungsumfang:', '• '),
                array('Service:', 'Kategorie:       ', 'Service-ID:      ', 'Beschreibung:', 'Leistungsumfang:', '- '),
                rtrim($service_block)
            ) . "\n";
        }

        $reply_to_email = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
        $brand = PAXdesign_Email_Templates::get_brand_config();

        $customer_plain = "Hallo " . $booking_data['name'] . ",\n\n"
            . "vielen Dank für Ihre Terminanfrage bei PAXDesign!\n\n"
            . "Wir haben Ihre Buchung erhalten und werden uns in Kürze bei Ihnen melden,\n"
            . "um den Termin zu bestätigen.\n\n"
            . "IHRE BUCHUNGSDETAILS:\n"
            . "-------------------------------------\n"
            . "Ansprechpartner:  " . $member_info['name'] . "\n"
            . "Position:         " . $member_info['role'] . "\n"
            . $service_block
            . "Datum:            " . $parts['date'] . "\n"
            . "Uhrzeit:          " . $booking_data['time'] . " Uhr\n"
            . "Zweck:            " . $parts['purpose_label'] . "\n"
            . "-------------------------------------\n\n"
            . "Bei Fragen erreichen Sie uns unter:\n"
            . "Telefon: " . $brand['phone'] . "\n"
            . "E-Mail:  " . $reply_to_email . "\n\n"
            . "Mit freundlichen Grüßen\n"
            . "Ihr PAXDesign Team\n\n"
            . "---\n"
            . $brand['legal_name'] . "\n"
            . $brand['address'] . "\n"
            . $brand['site_url'] . "\n";

        $customer_html = PAXdesign_Email_Templates::render_customer_confirmation($booking_data, $member_info, $parts);

        $customer_headers = array(
            'From: ' . $this->get_from_header(),
            'Reply-To: PAXDesign <' . $reply_to_email . '>',
        );

        $sent = $this->send_html_mail(
            $booking_data['email'],
            'Ihre Terminanfrage bei PAXDesign – ' . $parts['date'],
            $customer_html,
            $customer_plain,
            $customer_headers
        );

        if (!$sent) {
            paxdesign_booking_log_error('PAXdesign Booking: customer confirmation wp_mail() failed to ' . $booking_data['email']);
        }

        return $sent;
    }
    
    private function save_booking_to_database($booking_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'paxdesign_bookings';

        paxdesign_booking_upgrade_database();

        $service_details_json = null;
        if (!empty($booking_data['service_details']) && is_array($booking_data['service_details'])) {
            $service_details_json = wp_json_encode($booking_data['service_details'], JSON_UNESCAPED_UNICODE);
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'team_member' => $booking_data['member'],
                'service' => !empty($booking_data['service']) ? $booking_data['service'] : null,
                'service_details' => $service_details_json,
                'booking_date' => $booking_data['date'],
                'booking_time' => $booking_data['time'],
                'customer_name' => $booking_data['name'],
                'customer_email' => $booking_data['email'],
                'customer_phone' => $booking_data['phone'],
                'purpose' => $booking_data['purpose'],
                'message' => $booking_data['message'],
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            paxdesign_booking_log_error('PAXdesign Booking: database insert failed — ' . $wpdb->last_error);
            return false;
        }

        return true;
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'PAXdesign Booking',
            'Booking System',
            'manage_options',
            'paxdesign-booking',
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'paxdesign-booking',
            'Team Management',
            'Team Management',
            'manage_options',
            'paxdesign-booking-team',
            array($this, 'render_team_management_page')
        );
        
        add_submenu_page(
            'paxdesign-booking',
            'KI-Chat-Verlauf',
            'KI-Chat-Verlauf',
            'manage_options',
            'paxdesign-chat-history',
            array($this, 'render_chat_history_page')
        );

        add_submenu_page(
            'paxdesign-booking',
            'Live Chat',
            'Live Chat',
            'manage_options',
            'paxdesign-chat-live',
            array($this, 'render_chat_live_page')
        );

        add_submenu_page(
            'paxdesign-booking',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'paxdesign-booking-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        // Notification emails
        register_setting('paxdesign_booking_settings', 'paxdesign_booking_notification_email');
        register_setting('paxdesign_booking_settings', 'paxdesign_booking_email_ahmad');
        register_setting('paxdesign_booking_settings', 'paxdesign_booking_logo_url');
        register_setting('paxdesign_booking_settings', 'paxdesign_booking_services_url');
        register_setting('paxdesign_booking_settings', 'paxdesign_booking_contact_url');
        register_setting('paxdesign_booking_settings', 'paxdesign_booking_phone');
        // SMTP
        register_setting('paxdesign_booking_settings', 'paxdesign_smtp_enabled');
        register_setting('paxdesign_booking_settings', 'paxdesign_smtp_host');
        register_setting('paxdesign_booking_settings', 'paxdesign_smtp_port');
        register_setting('paxdesign_booking_settings', 'paxdesign_smtp_user');
        register_setting('paxdesign_booking_settings', 'paxdesign_smtp_pass');
        register_setting('paxdesign_booking_settings', 'paxdesign_smtp_encryption');
        register_setting('paxdesign_booking_settings', 'paxdesign_smtp_from_email');
        register_setting('paxdesign_booking_settings', 'paxdesign_smtp_from_name');
        register_setting('paxdesign_booking_settings', 'paxdesign_live_admin_url');
        register_setting('paxdesign_booking_settings', 'paxdesign_live_notify_email');
        register_setting('paxdesign_booking_settings', 'paxdesign_live_notify_emails');
        register_setting('paxdesign_booking_settings', 'paxdesign_live_whatsapp_phone');
        register_setting('paxdesign_booking_settings', 'paxdesign_apns_key_id', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_apns_team_id', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_apns_bundle_id', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_apns_key_p8', array(
            'sanitize_callback' => function ($value) {
                return is_string($value) ? trim($value) : '';
            },
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_apple_web_service_id', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting(
            'paxdesign_booking_settings',
            'paxdesign_live_whatsapp_callmebot_key',
            array(
                'sanitize_callback' => function ($value) {
                    return PAXdesign_Chat::sanitize_secret_option($value, 'paxdesign_live_whatsapp_callmebot_key');
                },
            )
        );
        register_setting('paxdesign_booking_settings', 'paxdesign_live_chat_agent_name', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_live_chat_agent_avatar', array(
            'sanitize_callback' => 'esc_url_raw',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_live_chat_agent_role', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_live_chat_agent_tagline', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('paxdesign_booking_settings', 'paxdesign_live_chat_agent_bio', array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        PAXdesign_Chat::get_instance()->register_settings();
    }
    
    public function render_admin_page() {
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    public function render_chat_history_page() {
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/chat-history-page.php';
    }

    public function render_chat_live_page() {
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/chat-live-page.php';
    }

    public function render_settings_page() {
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/settings-page.php';
    }
    
    public function render_team_management_page() {
        // Debug: Show current settings if debug parameter is present
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo '<div class="notice notice-info"><pre>';
            echo 'Current Settings in Database:' . "\n";
            print_r(get_option('paxdesign_booking_team_settings', array()));
            echo "\n\nTeam Members (filtered):";
            print_r($this->get_team_members(false));
            echo "\n\nTeam Members (all):";
            print_r($this->get_team_members(true));
            echo '</pre></div>';
        }
        include PAXDESIGN_BOOKING_PLUGIN_DIR . 'templates/team-management-page.php';
    }
    
    public function enqueue_admin_assets($hook) {
        if (PAXdesign_Admin_Compat::is_block_widgets_screen($hook)) {
            return;
        }

        // Settings page assets are handled by PAXdesign_Settings_Admin.
        if (PAXdesign_Settings_Admin::is_screen($hook)) {
            return;
        }

        // WordPress builds the hook as "{parent_slug}_page_{submenu_slug}".
        $plugin_pages = array(
            'toplevel_page_paxdesign-booking',
            'paxdesign-booking_page_paxdesign-booking-team',
            'paxdesign-booking_page_paxdesign-chat-history',
            'paxdesign-booking_page_paxdesign-chat-live',
        );
        if (!in_array($hook, $plugin_pages, true)) {
            return;
        }

        wp_enqueue_style(
            'paxdesign-booking-admin',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/css/admin-styles.css',
            array('wp-admin', 'common'),
            PAXDESIGN_BOOKING_VERSION
        );

        $admin_data = array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('paxdesign_admin_nonce'),
            'smtpEnabled'  => (bool) get_option('paxdesign_smtp_enabled', false),
            'notifEmail'   => get_option('paxdesign_booking_notification_email', 'info@paxdesign.at'),
            'liveAgent'    => PAXdesign_Chat_Live::get_agent_public_config(),
            'currentEmployee' => PAXdesign_Chat_Live::resolve_employee_identity(get_current_user_id()),
            'adminUrl'     => class_exists('PAXdesign_Live_Chat_PWA') ? PAXdesign_Live_Chat_PWA::get_admin_panel_url() : 'https://paxdesign.at/live-chat-admin/',
            'quickReplies' => PAXdesign_Chat_Live::get_admin_quick_replies(),
        'quickLinks'   => class_exists('PAXdesign_Chat_Quick_Links') ? PAXdesign_Chat_Quick_Links::get_links() : array(),
        'tourCompleted' => (bool) get_user_meta(get_current_user_id(), 'pax_live_dashboard_tour_completed', true),
        );

        if ($hook === 'paxdesign-booking_page_paxdesign-chat-live') {
            wp_enqueue_style(
                'paxdesign-live-chat-dashboard',
                PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/css/live-chat-dashboard.css',
                array(),
                PAXDESIGN_BOOKING_VERSION
            );
            wp_enqueue_style(
                'paxdesign-live-chat-app',
                PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/css/live-chat-app.css',
                array('paxdesign-live-chat-dashboard'),
                PAXDESIGN_BOOKING_VERSION
            );
            wp_enqueue_script(
                'paxdesign-chat-live-admin',
                PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/chat-live-admin.js',
                array('jquery'),
                PAXDESIGN_BOOKING_VERSION,
                true
            );
            wp_localize_script('paxdesign-chat-live-admin', 'paxdesignAdmin', $admin_data);
            if (class_exists('PAXdesign_Live_Chat_PWA')) {
                PAXdesign_Live_Chat_PWA::enqueue_assets();
                add_action('admin_head', array('PAXdesign_Live_Chat_PWA', 'print_admin_head_tags'), 2);
            }
            return;
        }

        if ($hook === 'paxdesign-booking_page_paxdesign-chat-history') {
            wp_enqueue_script(
                'paxdesign-chat-history-admin',
                PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/chat-history-admin.js',
                array('jquery'),
                PAXDESIGN_BOOKING_VERSION,
                true
            );
            wp_localize_script('paxdesign-chat-history-admin', 'paxdesignAdmin', $admin_data);
            return;
        }

        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_script(
            'paxdesign-booking-admin',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery', 'jquery-ui-sortable'),
            PAXDESIGN_BOOKING_VERSION,
            true
        );
        wp_localize_script('paxdesign-booking-admin', 'paxdesignAdmin', $admin_data);
    }
    
    public function handle_team_settings_update() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : array();
        
        // Ensure proper type conversion for all settings
        foreach ($settings as $member_id => &$member_settings) {
            if (isset($member_settings['enabled'])) {
                // Convert to proper boolean
                $member_settings['enabled'] = filter_var($member_settings['enabled'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($member_settings['order'])) {
                $member_settings['order'] = (int)$member_settings['order'];
            }
        }
        
        // update_option() returns false both on DB error AND when the value is
        // identical to what is already stored. Use get_option after writing to
        // confirm the data is present regardless of the return value.
        update_option('paxdesign_booking_team_settings', $settings);
        $saved = get_option('paxdesign_booking_team_settings', array());

        wp_send_json_success(array(
            'message'  => 'Settings saved successfully',
            'settings' => $saved,
        ));
    }
}

function paxdesign_booking_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paxdesign_bookings';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        team_member varchar(50) NOT NULL,
        service varchar(50) NULL DEFAULT NULL,
        service_details longtext NULL,
        booking_date date NOT NULL,
        booking_time varchar(10) NOT NULL,
        customer_name varchar(255) NOT NULL,
        customer_email varchar(255) NOT NULL,
        customer_phone varchar(50),
        purpose varchar(100),
        message text,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    paxdesign_booking_upgrade_database();

    require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/class-paxdesign-customer-db.php';
    PAXdesign_Customer_DB::install();
    
    add_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
    add_option('paxdesign_booking_email_ahmad', 'info@paxdesign.at');
    add_option('paxdesign_live_whatsapp_phone', '4368120543638');
    add_option('paxdesign_live_whatsapp_callmebot_key', '3515631');
    add_option('paxdesign_live_notify_emails', "info@paxdesign.at\nal.kahalaf.ahmad@gmail.com");

    require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/auth/class-paxdesign-auth-page.php';
    PAXdesign_Auth_Page::ensure_page();
}
register_activation_hook(__FILE__, 'paxdesign_booking_activate');

/**
 * Ensure Live-Agent WhatsApp + email defaults are present after updates.
 */
function paxdesign_booking_upgrade_live_notify_defaults() {
    $defaults_version = '3';
    if (get_option('paxdesign_live_notify_defaults_version') === $defaults_version) {
        return;
    }

    if (!get_option('paxdesign_live_admin_url')) {
        update_option('paxdesign_live_admin_url', 'https://paxdesign.at/live-chat-admin/', false);
    }

    if (!get_option('paxdesign_live_whatsapp_phone')) {
        update_option('paxdesign_live_whatsapp_phone', '4368120543638', false);
    }

    $key = trim((string) get_option('paxdesign_live_whatsapp_callmebot_key', ''));
    if ($key === '') {
        update_option('paxdesign_live_whatsapp_callmebot_key', '3515631', false);
    }

    $emails = trim((string) get_option('paxdesign_live_notify_emails', ''));
    if ($emails === '') {
        update_option(
            'paxdesign_live_notify_emails',
            "info@paxdesign.at\nal.kahalaf.ahmad@gmail.com",
            false
        );
    } else {
        $list = preg_split('/[\s,;]+/', $emails);
        $merged = array();
        foreach ($list as $part) {
            $email = sanitize_email($part);
            if ($email !== '') {
                $merged[] = $email;
            }
        }
        foreach (array('info@paxdesign.at', 'al.kahalaf.ahmad@gmail.com') as $required) {
            if (!in_array($required, $merged, true)) {
                $merged[] = $required;
            }
        }
        update_option('paxdesign_live_notify_emails', implode("\n", $merged), false);
    }

    update_option('paxdesign_live_notify_defaults_version', $defaults_version, false);
}

/**
 * Ensure the dedicated /account authentication page exists after updates.
 */
function paxdesign_booking_upgrade_auth_page() {
    require_once PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/auth/class-paxdesign-auth-page.php';
    $page_id = (int) get_option(PAXdesign_Auth_Page::OPTION_PAGE_ID, 0);
    if (get_option('paxdesign_auth_page_version') === '1' && $page_id > 0 && get_post($page_id)) {
        return;
    }
    $page_id = PAXdesign_Auth_Page::ensure_page();
    if ($page_id > 0) {
        update_option('paxdesign_auth_page_version', '1', false);
    }
}

/**
 * Upgrade bookings table schema safely without deleting existing rows.
 */
function paxdesign_booking_upgrade_database() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'paxdesign_bookings';

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
        paxdesign_booking_activate();
        return;
    }

    $columns = array(
        array(
            'name'       => 'service',
            'definition' => 'varchar(50) NULL DEFAULT NULL',
            'after'      => 'team_member',
        ),
        array(
            'name'       => 'service_details',
            'definition' => 'longtext NULL',
            'after'      => 'service',
        ),
    );

    foreach ($columns as $column) {
        paxdesign_booking_add_column_if_missing($table_name, $column['name'], $column['definition'], $column['after']);
    }

    PAXdesign_Chat_Log::create_table();
    PAXdesign_Chat_Live::upgrade_schema();
    PAXdesign_Message_Store::create_tables();
    PAXdesign_Chat_Log::maybe_purge_old_logs();

    update_option('paxdesign_booking_db_version', PAXDESIGN_BOOKING_DB_VERSION);
}

/**
 * Write operational errors to debug.log only when WP_DEBUG_LOG is enabled.
 */
function paxdesign_booking_log_error($message) {
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($message);
    }
}

/**
 * Add a column to the bookings table when it does not already exist.
 */
function paxdesign_booking_add_column_if_missing($table_name, $column_name, $definition, $after = null) {
    global $wpdb;

    $existing = $wpdb->get_results(
        $wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $column_name)
    );

    if (!empty($existing)) {
        return true;
    }

    if ($after !== null && $after !== '') {
        $after_exists = $wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $after)
        );
        if (empty($after_exists)) {
            $after = null;
        }
    }

    $after_sql = ($after !== null && $after !== '') ? " AFTER `{$after}`" : '';
    $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$definition}{$after_sql}";
    $result = $wpdb->query($sql);

    if ($result === false) {
        paxdesign_booking_log_error('PAXdesign Booking DB migration failed for column ' . $column_name . ': ' . $wpdb->last_error);
        return false;
    }

    paxdesign_booking_log_error('PAXdesign Booking DB migration: added column ' . $column_name);
    return true;
}

/**
 * Check whether a bookings table column exists.
 */
function paxdesign_booking_column_exists($column_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paxdesign_bookings';
    $existing = $wpdb->get_results(
        $wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $column_name)
    );
    return !empty($existing);
}

function paxdesign_booking_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'paxdesign_booking_deactivate');

function paxdesign_booking_init() {
    PAXdesign_Booking::get_instance();
    PAXdesign_Chat::get_instance();
}
add_action('plugins_loaded', 'paxdesign_booking_init');
