<?php
/**
 * Canonical API timestamp formatting — always UTC ISO-8601 with Z suffix.
 */
if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_API_Time {
    /**
     * @param string|int|float|null $value
     * @param bool                  $stored_as_utc When true, bare MySQL strings are interpreted as UTC.
     * @return string ISO-8601 UTC (e.g. 2026-07-14T12:30:45Z) or empty.
     */
    public static function format($value, $stored_as_utc = false) {
        $ts = self::unix($value, $stored_as_utc);
        if ($ts <= 0) {
            return '';
        }
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    /**
     * @param string|int|float|null $value
     * @param bool                  $stored_as_utc
     * @return int Unix timestamp (UTC) or 0.
     */
    public static function unix($value, $stored_as_utc = false) {
        if ($value === null || $value === '' || $value === false) {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $raw = trim((string) $value);
        if ($raw === '' || $raw === '0') {
            return 0;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $raw)) {
            $parsed = strtotime($raw);
            return $parsed ? (int) $parsed : 0;
        }

        if ($stored_as_utc) {
            $parsed = strtotime($raw . ' UTC');
            return $parsed ? (int) $parsed : 0;
        }

        $parsed = mysql2date('U', $raw, false);
        return $parsed ? (int) $parsed : 0;
    }
}
