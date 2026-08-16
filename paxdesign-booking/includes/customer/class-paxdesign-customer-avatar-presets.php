<?php
/**
 * PAXDesign customer avatar preset catalog.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Avatar_Presets {

    const COUNT = 100;
    const PRESET_NONE = 'pax-none';

    /**
     * @return array<int, array{id:string,label:string,url:string,type?:string}>
     */
    public static function catalog() {
        $items = array(
            array(
                'id'    => self::PRESET_NONE,
                'label' => __('No profile picture', 'paxdesign-booking'),
                'url'   => '',
                'type'  => 'none',
            ),
        );
        foreach (self::definitions() as $def) {
            $id = isset($def['id']) ? (string) $def['id'] : '';
            if ($id === '' || !self::file_exists($id)) {
                continue;
            }
            $items[] = array(
                'id'    => $id,
                'label' => isset($def['label']) ? (string) $def['label'] : $id,
                'url'   => self::normalize_asset_url(self::url_for_id($id)),
                'type'  => 'portrait',
            );
        }
        return $items;
    }

    /**
     * @return array{id:string,label:string,url:string,type?:string}|null
     */
    public static function find($id) {
        $id = self::sanitize_id($id);
        if ($id === '') {
            return null;
        }
        if (self::is_none($id)) {
            return array(
                'id'    => self::PRESET_NONE,
                'label' => __('No profile picture', 'paxdesign-booking'),
                'url'   => '',
                'type'  => 'none',
            );
        }
        if (!self::file_exists($id)) {
            return null;
        }
        foreach (self::definitions() as $def) {
            if (($def['id'] ?? '') === $id) {
                return array(
                    'id'    => $id,
                    'label' => (string) ($def['label'] ?? $id),
                    'url'   => self::normalize_asset_url(self::url_for_id($id)),
                    'type'  => 'portrait',
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
        if ($id === self::PRESET_NONE) {
            return $id;
        }
        if (preg_match('/^pax-(\d{2,3})$/', $id, $matches)) {
            $num = (int) $matches[1];
            if ($num >= 1 && $num <= self::COUNT) {
                return $num === 100 ? 'pax-100' : sprintf('pax-%02d', $num);
            }
        }
        return '';
    }

    /**
     * @param string $id
     * @return bool
     */
    public static function is_none($id) {
        return self::sanitize_id($id) === self::PRESET_NONE;
    }

    /**
     * @param string $id
     * @return bool
     */
    public static function exists($id) {
        $id = self::sanitize_id($id);
        if ($id === self::PRESET_NONE) {
            return true;
        }
        return $id !== '' && self::file_exists($id);
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function auto_id_for_user($user_id) {
        $user_id = absint($user_id);
        $index = ($user_id % self::COUNT) + 1;
        return $index === 100 ? 'pax-100' : sprintf('pax-%02d', $index);
    }

    /**
     * @return string
     */
    public static function random_id() {
        $index = wp_rand(1, self::COUNT);
        return $index === 100 ? 'pax-100' : sprintf('pax-%02d', $index);
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
        $url = PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/customer-auth/images/avatars/' . $id . '.gif';
        return esc_url_raw(add_query_arg('v', PAXDESIGN_BOOKING_VERSION, $url));
    }

    /**
     * Rewrite legacy SVG preset paths to the current GIF assets.
     *
     * @param string $url
     * @return string
     */
    public static function normalize_asset_url($url) {
        $url = (string) $url;
        if ($url === '') {
            return '';
        }
        $normalized = preg_replace('#(/avatars(?:-vip)?/pax-(?:vip-)?\d{2,3})\.svg(\?|$)#i', '$1.gif$2', $url);
        if ($normalized === null) {
            return $url;
        }
        if (preg_match('#/avatars(?:-vip)?/pax-(?:vip-)?\d{2,3}\.gif(\?|$)#i', $normalized)) {
            if (strpos($normalized, 'v=') === false) {
                $normalized = add_query_arg('v', PAXDESIGN_BOOKING_VERSION, $normalized);
            }
        }
        return esc_url_raw($normalized);
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
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'assets/customer-auth/images/avatars/' . $id . '.gif';
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
        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/data/avatar-preset-labels.php';
        if (!is_readable($path)) {
            $definitions = array();
            return $definitions;
        }
        $loaded = include $path;
        $definitions = is_array($loaded) ? $loaded : array();
        return $definitions;
    }
}
