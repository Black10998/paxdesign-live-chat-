<?php
/**
 * PAXDesign customer level system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Levels {

    const COUNT = 10;
    const META_LEVEL = 'pax_customer_level';

    /**
     * @return array<int, array{metal:string,title:string,description:string}>
     */
    public static function definitions() {
        static $definitions = null;
        if ($definitions !== null) {
            return $definitions;
        }
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/customer-level-definitions.php';
        if (!is_readable($path)) {
            $definitions = array();
            return $definitions;
        }
        $loaded = include $path;
        $definitions = is_array($loaded) ? $loaded : array();
        return $definitions;
    }

    /**
     * @param int $level
     * @return int
     */
    public static function sanitize_level($level) {
        $level = absint($level);
        if ($level > self::COUNT) {
            return 0;
        }
        return $level;
    }

    /**
     * @param int $level
     * @return array{metal:string,title:string,description:string}|null
     */
    public static function get_level($level) {
        $level = self::sanitize_level($level);
        if ($level <= 0) {
            return null;
        }
        $definitions = self::definitions();
        return isset($definitions[$level]) && is_array($definitions[$level]) ? $definitions[$level] : null;
    }

    /**
     * @param int $level
     * @return string
     */
    public static function label_for_level($level) {
        $def = self::get_level($level);
        if (!$def) {
            return '';
        }
        return sprintf(
            __('Level %1$s %2$s', 'paxdesign-booking'),
            sprintf('%02d', $level),
            (string) ($def['metal'] ?? '')
        );
    }

    /**
     * @param int $level
     * @return string
     */
    public static function avatar_id_for_level($level) {
        $level = self::sanitize_level($level);
        if ($level <= 0) {
            return '';
        }
        return sprintf('pax-vip-%02d', $level);
    }

    /**
     * @param int $user_id
     * @return int
     */
    public static function level_for_user($user_id) {
        return self::sanitize_level((int) get_user_meta(absint($user_id), self::META_LEVEL, true));
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>
     */
    public static function profile_fields($user_id) {
        $level = self::level_for_user($user_id);
        if ($level <= 0) {
            return array(
                'customer_level'      => 0,
                'level_label'         => '',
                'level_title'         => '',
                'level_description'   => '',
                'level_metal'         => '',
                'has_customer_level'  => false,
            );
        }
        $def = self::get_level($level);
        return array(
            'customer_level'      => $level,
            'level_label'         => self::label_for_level($level),
            'level_title'         => $def ? (string) ($def['title'] ?? '') : '',
            'level_description'   => $def ? (string) ($def['description'] ?? '') : '',
            'level_metal'         => $def ? (string) ($def['metal'] ?? '') : '',
            'has_customer_level'  => true,
        );
    }

    /**
     * @param int $user_id
     * @param int $level
     * @return bool
     */
    public static function set_level_for_user($user_id, $level) {
        $user_id = absint($user_id);
        $level = self::sanitize_level($level);
        if ($user_id <= 0) {
            return false;
        }
        if ($level <= 0) {
            return self::clear_level_for_user($user_id);
        }
        if (!self::get_level($level)) {
            return false;
        }
        update_user_meta($user_id, self::META_LEVEL, $level);
        if (class_exists('PAXdesign_Customer_Avatar')) {
            $avatar_id = self::avatar_id_for_level($level);
            PAXdesign_Customer_Avatar::grant_vip_avatar($user_id, $avatar_id, true);
        }
        return true;
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function clear_level_for_user($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }
        $previous = self::level_for_user($user_id);
        delete_user_meta($user_id, self::META_LEVEL);
        if ($previous > 0 && class_exists('PAXdesign_Customer_Avatar')) {
            PAXdesign_Customer_Avatar::revoke_vip_avatar($user_id, self::avatar_id_for_level($previous));
        }
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function catalog() {
        $items = array();
        for ($level = 1; $level <= self::COUNT; $level++) {
            $def = self::get_level($level);
            if (!$def) {
                continue;
            }
            $items[] = array_merge(
                array(
                    'level'      => $level,
                    'label'      => self::label_for_level($level),
                    'avatar_id'  => self::avatar_id_for_level($level),
                ),
                $def
            );
        }
        return $items;
    }
}
