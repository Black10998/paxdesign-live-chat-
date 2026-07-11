<?php
/**
 * Configurable quick website links for staff → customer live chat.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Chat_Quick_Links {

    const OPTION_KEY = 'paxdesign_chat_quick_links';

    public static function init() {
        add_action('wp_ajax_paxdesign_chat_live_admin_send_link', array(__CLASS__, 'handle_admin_send_link'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaults() {
        return array(
            array('id' => 'services',  'label' => 'Services',  'url' => '/services',  'icon' => '📁', 'sort' => 0),
            array('id' => 'projects',  'label' => 'Projects',  'url' => '/projects',  'icon' => '🚀', 'sort' => 1),
            array('id' => 'pricing',   'label' => 'Pricing',   'url' => '/pricing',   'icon' => '💶', 'sort' => 2),
            array('id' => 'contact',   'label' => 'Contact',   'url' => '/contact',   'icon' => '📞', 'sort' => 3),
            array('id' => 'about',     'label' => 'About Us',  'url' => '/about',     'icon' => 'ℹ️', 'sort' => 4),
            array('id' => 'faq',       'label' => 'FAQ',       'url' => '/faq',       'icon' => '❓', 'sort' => 5),
            array('id' => 'portfolio', 'label' => 'Portfolio', 'url' => '/portfolio', 'icon' => '🎨', 'sort' => 6),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function get_links() {
        $raw = get_option(self::OPTION_KEY, '');
        if ($raw === '' || $raw === false) {
            return self::defaults();
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return self::defaults();
        }
        $links = array();
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
            $url   = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
            if ($label === '' || $url === '') {
                continue;
            }
            $links[] = array(
                'id'    => isset($item['id']) ? sanitize_key((string) $item['id']) : sanitize_key($label),
                'label' => $label,
                'url'   => $url,
                'icon'  => isset($item['icon']) ? sanitize_text_field((string) $item['icon']) : '🔗',
                'sort'  => isset($item['sort']) ? (int) $item['sort'] : 0,
            );
        }
        usort($links, function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });
        return $links;
    }

    /**
     * @param array<int, array<string, mixed>> $links
     */
    public static function save_links($links) {
        if (!is_array($links)) {
            return false;
        }
        $clean = array();
        $sort  = 0;
        foreach ($links as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
            $url   = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
            if ($label === '' || $url === '') {
                continue;
            }
            $clean[] = array(
                'id'    => isset($item['id']) && $item['id'] !== '' ? sanitize_key((string) $item['id']) : sanitize_key($label . '-' . $sort),
                'label' => $label,
                'url'   => $url,
                'icon'  => isset($item['icon']) ? sanitize_text_field((string) $item['icon']) : '🔗',
                'sort'  => $sort,
            );
            $sort++;
        }
        return update_option(self::OPTION_KEY, wp_json_encode($clean));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find_link($link_id) {
        $link_id = sanitize_key((string) $link_id);
        if ($link_id === '') {
            return null;
        }
        foreach (self::get_links() as $link) {
            if ($link['id'] === $link_id) {
                return $link;
            }
        }
        return null;
    }

    public static function handle_admin_send_link() {
        check_ajax_referer('paxdesign_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'), 403);
        }

        $live = PAXdesign_Chat_Live::get_instance();
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        $link_id    = isset($_POST['link_id']) ? sanitize_key(wp_unslash($_POST['link_id'])) : '';

        $link = self::find_link($link_id);
        if (!$link) {
            wp_send_json_error(array('message' => 'Link nicht gefunden.'), 404);
        }

        $result = $live->admin_send_link_card($session_id, $link);
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            wp_send_json_error(array('message' => $result->get_error_message()), is_array($data) && !empty($data['status']) ? (int) $data['status'] : 500);
        }

        wp_send_json_success($result);
    }
}
