<?php
/**
 * PAXDesign exclusive VIP avatar preset catalog.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Avatar_Vip_Presets {

    const COUNT = 10;
    const ID_PREFIX = 'pax-vip-';

    /**
     * @return array<int, array{id:string,label:string,url:string,type:string,locked?:bool}>
     */
    public static function catalog_for_user($user_id = 0) {
        $user_id = absint($user_id);
        $grants = class_exists('PAXdesign_Customer_Avatar')
            ? PAXdesign_Customer_Avatar::vip_grants_for_user($user_id)
            : array();
        $items = array();
        foreach (self::definitions() as $def) {
            $id = isset($def['id']) ? (string) $def['id'] : '';
            if ($id === '' || !self::file_exists($id)) {
                continue;
            }
            $items[] = array(
                'id'     => $id,
                'label'  => isset($def['label']) ? (string) $def['label'] : $id,
                'url'    => self::url_for_id($id),
                'type'   => 'vip',
                'locked' => !in_array($id, $grants, true),
            );
        }
        return $items;
    }

    /**
     * @return array{id:string,label:string,url:string,type:string}|null
     */
    public static function find($id) {
        $id = self::sanitize_id($id);
        if ($id === '' || !self::file_exists($id)) {
            return null;
        }
        foreach (self::definitions() as $def) {
            if (($def['id'] ?? '') === $id) {
                return array(
                    'id'    => $id,
                    'label' => (string) ($def['label'] ?? $id),
                    'url'   => self::url_for_id($id),
                    'type'  => 'vip',
                );
            }
        }
        return null;
    }

    /**
     * @param string $id
     * @return string
     */
    public static function sanitize_id($id) {
        $id = sanitize_key((string) $id);
        if (!preg_match('/^pax-vip-(\d{2})$/', $id, $matches)) {
            return '';
        }
        $num = (int) $matches[1];
        if ($num < 1 || $num > self::COUNT) {
            return '';
        }
        return sprintf('pax-vip-%02d', $num);
    }

    /**
     * @param string $id
     * @return bool
     */
    public static function is_vip($id) {
        return self::sanitize_id($id) !== '';
    }

    /**
     * @param string $id
     * @return bool
     */
    public static function exists($id) {
        $id = self::sanitize_id($id);
        return $id !== '' && self::file_exists($id);
    }

    /**
     * @param string $id
     * @return string
     */
    public static function url_for_id($id) {
        $id = self::sanitize_id($id);
        if ($id === '' || !self::file_exists($id)) {
            return '';
        }
        $url = PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/customer-auth/images/avatars-vip/' . $id . '.svg';
        return esc_url_raw(add_query_arg('v', PAXDESIGN_BOOKING_VERSION, $url));
    }

    /**
     * @param string $id
     * @return bool
     */
    private static function file_exists($id) {
        $id = self::sanitize_id($id);
        if ($id === '') {
            return false;
        }
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'assets/customer-auth/images/avatars-vip/' . $id . '.svg';
        return is_readable($path);
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    private static function definitions() {
        static $definitions = null;
        if ($definitions !== null) {
            return $definitions;
        }
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/avatar-vip-preset-labels.php';
        if (!is_readable($path)) {
            $definitions = array();
            return $definitions;
        }
        $loaded = include $path;
        $definitions = is_array($loaded) ? $loaded : array();
        return $definitions;
    }
}
