<?php
/**
 * Inline SVG icons for booking service cards.
 */
if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Service_Icons {

    private static function svg($paths) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $paths
            . '</svg>';
    }

    public static function get_all() {
        return array(
            'website' => self::svg('<circle cx="12" cy="12" r="9"/><path d="M2 12h20"/><path d="M12 3a15 15 0 0 1 0 18"/><path d="M12 3a15 15 0 0 0 0 18"/>'),
            'webapp' => self::svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M9 9v11"/>'),
            'android' => self::svg('<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M10 7h4"/><circle cx="10" cy="17" r="1"/><circle cx="14" cy="17" r="1"/>'),
            'ios' => self::svg('<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 5h2"/><circle cx="12" cy="18" r="1"/>'),
            'crossplatform' => self::svg('<rect x="3" y="5" width="8" height="14" rx="1.5"/><rect x="13" y="5" width="8" height="14" rx="1.5"/><path d="M7 19v2M17 19v2"/>'),
            'androidtv' => self::svg('<rect x="2" y="5" width="20" height="13" rx="2"/><path d="M8 21h8"/><path d="M12 18v3"/>'),
            'security' => self::svg('<path d="M12 3 4 7v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7l-8-4z"/><path d="M9 12l2 2 4-4"/>'),
            'backend' => self::svg('<rect x="3" y="4" width="18" height="6" rx="1"/><rect x="3" y="14" width="18" height="6" rx="1"/><path d="M7 7h.01M7 17h.01"/>'),
            'devops' => self::svg('<path d="M4 7h16v10H4z"/><path d="M8 11h8"/><path d="M12 7v10"/><path d="M7 4h10"/>'),
            'enterprise' => self::svg('<path d="M4 21V9l8-4 8 4v12"/><path d="M9 21v-6h6v6"/><path d="M9 9h6"/>'),
            'aiautomation' => self::svg('<path d="M12 3v3"/><path d="M6 7l2 2"/><path d="M18 7l-2 2"/><rect x="5" y="10" width="14" height="8" rx="2"/><path d="M9 14h6"/><path d="M10 17h4"/>'),
            'aichatbot' => self::svg('<path d="M4 5h16v10H8l-4 4z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/>'),
            'ecommerce' => self::svg('<circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/><path d="M3 4h2l2 12h10l2-8H7"/>'),
            'maintenance' => self::svg('<path d="M14 4l6 6-8 8H6v-6l8-8z"/><path d="M12 6l2 2"/>'),
            'pagespeed' => self::svg('<path d="M13 2 4 14h7l-1 8 10-14H12l1-6z"/>'),
            'uiux' => self::svg('<path d="M4 20l8-16 8 16"/><path d="M7.5 14h9"/>'),
            'branding' => self::svg('<path d="M12 3l2.4 6.8L21 11l-5.2 3.8L17 21l-5-3.2L7 21l1.2-6.2L3 11l6.6-1.2L12 3z"/>'),
            'crm' => self::svg('<path d="M4 19V5h16v14"/><path d="M8 9h8"/><path d="M8 13h5"/><path d="M8 17h3"/>'),
            'bookingsystem' => self::svg('<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h2v2H8z"/>'),
            'pwa' => self::svg('<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/><path d="M3 12h4M17 12h4"/>'),
            'analytics' => self::svg('<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 17V11"/><path d="M12 17V7"/><path d="M16 17v-4"/>'),
            'gdpr' => self::svg('<path d="M12 3 4 7v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7l-8-4z"/><path d="M12 11v4"/><circle cx="12" cy="9" r="1"/>'),
            'secflash' => self::svg('<path d="M13 2 4 14h7l-1 8 10-14H12l1-6z"/><path d="M12 3v2"/>'),
            'seclayers' => self::svg('<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M7 8V6a5 5 0 0 1 10 0v2"/><path d="M8 13h8"/>'),
            'sectamper' => self::svg('<path d="M12 3 4 7v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7l-8-4z"/><path d="M12 8v5"/><circle cx="12" cy="16" r="1"/>'),
            'secruntime' => self::svg('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
            'secobfusc' => self::svg('<path d="M4 7h16v10H4z"/><path d="M8 11h8"/><path d="M10 9v4M14 9v4"/>'),
            'sectoken' => self::svg('<path d="M14 4h6v6"/><path d="M10 14 20 4"/><path d="M5 10v10h10"/>'),
            'seclicense' => self::svg('<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/><path d="M9 1v3M15 1v3"/>'),
            'secintegrity' => self::svg('<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>'),
        );
    }

    public static function get($key) {
        $icons = self::get_all();
        return isset($icons[$key]) ? $icons[$key] : $icons['website'];
    }
}
