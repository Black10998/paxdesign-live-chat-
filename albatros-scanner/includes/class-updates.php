<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Updates {
    const TRANSIENT = 'alb_update_check';

    public static function current() {
        return ALB_SCANNER_VERSION;
    }

    public static function payload($fresh = false) {
        if (!$fresh) {
            $cached = get_transient(self::TRANSIENT);
            if (is_array($cached) && !empty($cached['current'])) {
                return $cached;
            }
        }
        $current = self::current();
        $latest = $current;
        $source = 'local';
        $feed = defined('ALB_SCANNER_UPDATE_FEED') ? ALB_SCANNER_UPDATE_FEED : '';
        if ($feed !== '') {
            $response = wp_remote_get($feed, array(
                'timeout' => 8,
                'redirection' => 2,
                'headers' => array('Accept' => 'text/plain'),
            ));
            if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
                $body = (string) wp_remote_retrieve_body($response);
                if (preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/m', $body, $match) || preg_match("/ALB_SCANNER_VERSION',\s*'([0-9.]+)'/", $body, $match)) {
                    $latest = $match[1];
                    $source = 'feed';
                }
            }
        }
        $available = version_compare($latest, $current, '>');
        $payload = array(
            'current' => $current,
            'latest' => $latest,
            'available' => $available,
            'source' => $source,
            'checked_at' => Alb_Settings::now_mysql(),
            'checked_at_display' => Alb_Settings::format_datetime(Alb_Settings::now_mysql()),
        );
        set_transient(self::TRANSIENT, $payload, 5 * MINUTE_IN_SECONDS);
        return $payload;
    }
}
