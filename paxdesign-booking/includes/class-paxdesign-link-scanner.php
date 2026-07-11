<?php
/**
 * Heuristic URL scanner for customer-sent links (staff-facing only).
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
     * @return array{status: string, urls: array<int, array{url: string, status: string}>}
     */
    public static function scan_text($text) {
        $urls = self::extract_urls((string) $text);
        if (empty($urls)) {
            return array('status' => self::STATUS_NONE, 'urls' => array());
        }

        $worst   = self::STATUS_SAFE;
        $results = array();
        foreach ($urls as $url) {
            $status = self::scan_url($url);
            $results[] = array('url' => $url, 'status' => $status);
            if ($status === self::STATUS_DANGEROUS) {
                $worst = self::STATUS_DANGEROUS;
            } elseif ($status === self::STATUS_SUSPICIOUS && $worst !== self::STATUS_DANGEROUS) {
                $worst = self::STATUS_SUSPICIOUS;
            }
        }

        return array('status' => $worst, 'urls' => $results);
    }

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

    public static function scan_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return self::STATUS_NONE;
        }

        $lower = strtolower($url);
        if (preg_match('~^(javascript|data|file|vbscript|blob):~i', $lower)) {
            return self::STATUS_DANGEROUS;
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return self::STATUS_DANGEROUS;
        }

        $host = strtolower((string) $parts['host']);

        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
            return self::STATUS_SUSPICIOUS;
        }

        if (strpos($host, 'xn--') !== false) {
            return self::STATUS_SUSPICIOUS;
        }

        if (preg_match('/@(?!)/', $url) && strpos($url, '@') < strpos($url, '://') + 10) {
            return self::STATUS_DANGEROUS;
        }

        $suspicious_tlds = array('tk', 'ml', 'ga', 'cf', 'gq', 'zip', 'mov', 'top', 'xyz', 'click', 'loan');
        $tld = '';
        if (preg_match('/\.([a-z0-9-]{2,24})$/i', $host, $m)) {
            $tld = strtolower($m[1]);
        }
        if ($tld !== '' && in_array($tld, $suspicious_tlds, true)) {
            return self::STATUS_SUSPICIOUS;
        }

        if (substr_count($host, '.') >= 4) {
            return self::STATUS_SUSPICIOUS;
        }

        $phishing_keywords = array('login', 'verify', 'password', 'banking', 'wallet', 'secure-update', 'account-locked');
        $path = strtolower((string) ($parts['path'] ?? '') . ($parts['query'] ?? ''));
        $hits = 0;
        foreach ($phishing_keywords as $keyword) {
            if (strpos($host, $keyword) !== false || strpos($path, $keyword) !== false) {
                $hits++;
            }
        }
        if ($hits >= 2) {
            return self::STATUS_SUSPICIOUS;
        }

        if (strlen($url) > 300) {
            return self::STATUS_SUSPICIOUS;
        }

        return self::STATUS_SAFE;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function attach_scan_meta($content, $role, $extra = array()) {
        if ($role !== 'user') {
            return $extra;
        }
        $scan = self::scan_text($content);
        if ($scan['status'] === self::STATUS_NONE) {
            return $extra;
        }
        $extra['link_scan_status'] = $scan['status'];
        $extra['link_scan_urls']   = wp_json_encode($scan['urls']);
        return $extra;
    }
}
