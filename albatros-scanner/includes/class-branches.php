<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Branches {
    const WIEN = 'wien';
    const GRAZ = 'graz';

    public static function keys() {
        return array(self::WIEN, self::GRAZ);
    }

    public static function normalize($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, self::keys(), true) ? $value : '';
    }

    public static function label($value) {
        $value = self::normalize($value);
        return $value === '' ? Alb_I18n::t('branch.empty') : Alb_I18n::t('branch.' . $value);
    }
}
