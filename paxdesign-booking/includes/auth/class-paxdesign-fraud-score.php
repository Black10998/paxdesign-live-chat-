<?php
/**
 * Pure Device Risk scoring. No I/O, no audio, no WordPress calls.
 *
 * 0–49 allow, 50–74 watch (allow + log), 75–100 extra verification.
 * Never used to hard-block a normal human session.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Fraud_Score {

    const ACTION_ALLOW     = 'allow';
    const ACTION_WATCH     = 'watch';
    const ACTION_CHALLENGE = 'challenge';

    const THRESHOLD_WATCH     = 50;
    const THRESHOLD_CHALLENGE = 75;

    /**
     * @param array<string, mixed> $signals  Browser/device signals from the collector.
     * @param array<string, mixed> $context  IP / session / account stats from the server.
     * @return array{score:int,action:string,reasons:array<int,string>,fingerprint_hash:string}
     */
    public static function evaluate(array $signals, array $context = array()) {
        if (!empty($context['owner'])) {
            return array(
                'score'             => 0,
                'action'            => self::ACTION_ALLOW,
                'reasons'           => array('owner_bypass'),
                'fingerprint_hash'  => self::fingerprint_hash($signals),
            );
        }

        $score   = 0;
        $reasons = array();
        $ua      = strtolower((string) ($signals['ua'] ?? ($context['ua'] ?? '')));

        if (!empty($signals['webdriver']) || !empty($context['webdriver'])) {
            $score += 40;
            $reasons[] = 'bot_webdriver';
        }

        if (self::ua_looks_headless($ua)) {
            $score += 35;
            $reasons[] = 'bot_headless_ua';
        }

        $renderer = strtolower((string) ($signals['webgl_renderer'] ?? ''));
        if ($renderer !== '' && self::webgl_looks_headless($renderer)) {
            $score += 20;
            $reasons[] = 'bot_headless_webgl';
        }

        $canvas = (string) ($signals['canvas'] ?? '');
        $vendor = (string) ($signals['webgl_vendor'] ?? '');
        if ($canvas === '' && $vendor === '' && $renderer === '') {
            $score += 15;
            $reasons[] = 'missing_graphics_fingerprint';
        }

        $hw = (int) ($signals['hardware_concurrency'] ?? 0);
        if ($hw <= 0 || $hw >= 256) {
            $score += 10;
            $reasons[] = 'abnormal_hardware';
        }

        $collected_ms = (int) ($signals['collected_ms'] ?? ($context['collected_ms'] ?? 0));
        if ($collected_ms > 0 && $collected_ms < 4) {
            $score += 10;
            $reasons[] = 'collection_too_fast';
        }

        $langs = $signals['languages'] ?? '';
        if (is_array($langs)) {
            $langs = implode(',', $langs);
        }
        if (trim((string) $langs) === '') {
            $score += 6;
            $reasons[] = 'missing_languages';
        }

        if (empty($signals['timezone'])) {
            $score += 6;
            $reasons[] = 'missing_timezone';
        }

        $plugins = (int) ($signals['plugins'] ?? -1);
        if ($plugins === 0 && strpos($ua, 'chrome') !== false && strpos($ua, 'mobile') === false) {
            $score += 4;
            $reasons[] = 'chrome_zero_plugins';
        }

        if (!empty($context['missing_device'])) {
            $score += 12;
            $reasons[] = 'missing_device_id';
        }

        if (!empty($context['missing_signals'])) {
            $score += 10;
            $reasons[] = 'missing_signals';
        }

        $fp_accounts = (int) ($context['fingerprint_accounts'] ?? 0);
        if ($fp_accounts >= 5) {
            $score += 50;
            $reasons[] = 'multi_account_fingerprint';
        } elseif ($fp_accounts >= 3) {
            $score += 35;
            $reasons[] = 'multi_account_fingerprint';
        }

        $ip_accounts = (int) ($context['ip_accounts'] ?? 0);
        if ($ip_accounts >= 8) {
            $score += 30;
            $reasons[] = 'multi_account_ip';
        } elseif ($ip_accounts >= 4) {
            $score += 20;
            $reasons[] = 'multi_account_ip';
        }

        $ip_velocity = (int) ($context['ip_velocity'] ?? 0);
        if ($ip_velocity >= 60) {
            $score += 25;
            $reasons[] = 'abnormal_request_rate';
        } elseif ($ip_velocity >= 30) {
            $score += 12;
            $reasons[] = 'elevated_request_rate';
        }

        $failed = (int) ($context['failed_logins'] ?? 0);
        if ($failed >= 8) {
            $score += 20;
            $reasons[] = 'credential_abuse';
        } elseif ($failed >= 4) {
            $score += 10;
            $reasons[] = 'credential_abuse';
        }

        if (!empty($context['scrape_pattern'])) {
            $score += 20;
            $reasons[] = 'scraping_pattern';
        }

        if (!empty($context['known_device']) && $score < 75) {
            $score -= 25;
            $reasons[] = 'known_device_trust';
        }

        $score = (int) max(0, min(100, $score));
        $action = self::ACTION_ALLOW;
        if ($score >= self::THRESHOLD_CHALLENGE) {
            $action = self::ACTION_CHALLENGE;
        } elseif ($score >= self::THRESHOLD_WATCH) {
            $action = self::ACTION_WATCH;
        }

        return array(
            'score'            => $score,
            'action'           => $action,
            'reasons'          => $reasons,
            'fingerprint_hash' => self::fingerprint_hash($signals),
        );
    }

    /**
     * Stable device hash from graphics + environment (no IP, no audio).
     */
    public static function fingerprint_hash(array $signals) {
        $langs = $signals['languages'] ?? '';
        if (is_array($langs)) {
            $langs = implode(',', $langs);
        }
        $parts = array(
            (string) ($signals['canvas'] ?? ''),
            (string) ($signals['webgl_vendor'] ?? ''),
            (string) ($signals['webgl_renderer'] ?? ''),
            (int) ($signals['screen_w'] ?? 0),
            (int) ($signals['screen_h'] ?? 0),
            (int) ($signals['color_depth'] ?? 0),
            (string) ($signals['timezone'] ?? ''),
            (int) ($signals['hardware_concurrency'] ?? 0),
            (string) ($signals['platform'] ?? ''),
            (string) $langs,
            (string) ($signals['vendor'] ?? ''),
        );
        return hash('sha256', implode('|', $parts));
    }

    public static function ua_looks_headless($ua) {
        $ua = strtolower((string) $ua);
        if ($ua === '') {
            return false;
        }
        foreach (array('headlesschrome', 'headless', 'phantomjs', 'slimerjs', 'puppeteer', 'playwright') as $needle) {
            if (strpos($ua, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function webgl_looks_headless($renderer) {
        $renderer = strtolower((string) $renderer);
        foreach (array('swiftshader', 'llvmpipe', 'mesa offscreen', 'osmesa', 'virtualbox') as $needle) {
            if (strpos($renderer, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
