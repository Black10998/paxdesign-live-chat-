<?php
/**
 * SVG icons for AI chat quick actions.
 */
if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Chat_Icons {

    private static function svg($paths) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $paths
            . '</svg>';
    }

    public static function get_all() {
        return array(
            'website'  => self::svg('<circle cx="12" cy="12" r="9"/><path d="M2 12h20"/><path d="M12 3a15 15 0 0 1 0 18"/><path d="M12 3a15 15 0 0 0 0 18"/>'),
            'chatbot'  => self::svg('<path d="M4 5h16v10H8l-4 4z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/>'),
            'calendar' => self::svg('<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h2v2H8z"/>'),
            'pricing'  => self::svg('<path d="M4 10h16"/><path d="M6 6h12v12H6z"/><path d="M9 14h6"/><path d="M9 10h.01"/>'),
            'speed'    => self::svg('<path d="M13 2 4 14h7l-1 8 10-14H12l1-6z"/>'),
            'security' => self::svg('<path d="M12 3 4 7v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7l-8-4z"/><path d="M9 12l2 2 4-4"/>'),
            'mobile'   => self::svg('<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/><path d="M3 12h4M17 12h4"/>'),
            'contact'  => self::svg('<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>'),
        );
    }

    public static function get($key) {
        $icons = self::get_all();
        return isset($icons[$key]) ? $icons[$key] : $icons['website'];
    }
}
