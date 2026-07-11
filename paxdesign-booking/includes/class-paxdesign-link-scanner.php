<?php
/**
 * URL extraction helpers for customer-sent links.
 * Security verdicts are produced exclusively by PAXdesign_Link_Scan_Service.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Link_Scanner {

    const STATUS_NONE       = 'none';
    const STATUS_SAFE       = 'safe';
    const STATUS_SUSPICIOUS = 'suspicious';
    const STATUS_DANGEROUS  = 'dangerous';

    /**
     * @return array<int, string>
     */
    public static function extract_urls($text) {
        if ($text === '') {
            return array();
        }
        $pattern = '~\bhttps?://[^\s<>"\'\)\]]+~iu';
        if (!preg_match_all($pattern, $text, $matches)) {
            return array();
        }
        $urls = array();
        foreach ($matches[0] as $raw) {
            $url = rtrim((string) $raw, '.,;:!?)');
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function attach_scan_meta($content, $role, $extra = array()) {
        if (!class_exists('PAXdesign_Link_Scan_Service')) {
            return $extra;
        }
        return PAXdesign_Link_Scan_Service::begin_scan_meta($content, $role, $extra);
    }
}
