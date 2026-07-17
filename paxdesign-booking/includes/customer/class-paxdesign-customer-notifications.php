<?php
/**
 * Customer notification center.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Notifications {

    const USER_META_PREFS = 'pax_customer_notification_prefs';
    const USER_META_DEVICES = 'pax_customer_apns_devices';

    public static function notify_user($user_id, $category, $title, $body, $entity_type = '', $entity_id = '', $deep_link = '') {
        global $wpdb;
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }
        if (!self::category_enabled($user_id, $category)) {
            return 0;
        }
        $wpdb->insert(PAXdesign_Customer_DB::table('notifications'), array(
            'user_id'     => $user_id,
            'category'    => sanitize_key($category),
            'title'       => sanitize_text_field($title),
            'body'        => sanitize_textarea_field($body),
            'entity_type' => sanitize_key($entity_type),
            'entity_id'   => sanitize_text_field($entity_id),
            'deep_link'   => sanitize_text_field($deep_link),
            'is_read'     => 0,
            'push_sent'   => 0,
            'created_at'  => current_time('mysql', true),
        ));
        $id = (int) $wpdb->insert_id;
        self::maybe_push($user_id, $title, $body, array(
            'notification_id' => $id,
            'category'        => $category,
            'entity_type'     => $entity_type,
            'entity_id'       => $entity_id,
            'deep_link'       => $deep_link,
        ));
        return $id;
    }

    public static function list_for_user($user_id, $unread_only = false, $limit = 50) {
        global $wpdb;
        $user_id = absint($user_id);
        $table = PAXdesign_Customer_DB::table('notifications');
        $sql = "SELECT * FROM $table WHERE user_id = %d";
        $params = array($user_id);
        if ($unread_only) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = min(100, max(1, (int) $limit));
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(array(__CLASS__, 'format'), $rows ?: array());
    }

    public static function mark_read($user_id, $notification_id) {
        global $wpdb;
        return (bool) $wpdb->update(
            PAXdesign_Customer_DB::table('notifications'),
            array('is_read' => 1, 'read_at' => current_time('mysql', true)),
            array('id' => absint($notification_id), 'user_id' => absint($user_id)),
            array('%d', '%s'),
            array('%d', '%d')
        );
    }

    public static function unread_count($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM " . PAXdesign_Customer_DB::table('notifications') . " WHERE user_id = %d AND is_read = 0",
            absint($user_id)
        ));
    }

    public static function get_prefs($user_id) {
        $prefs = get_user_meta(absint($user_id), self::USER_META_PREFS, true);
        if (!is_array($prefs)) {
            $prefs = array();
        }
        return wp_parse_args($prefs, array(
            'chat'         => true,
            'project'      => true,
            'order'        => true,
            'news'         => true,
            'security'     => true,
            'push_enabled' => true,
        ));
    }

    public static function save_prefs($user_id, $prefs) {
        $clean = array(
            'chat'         => !empty($prefs['chat']),
            'project'      => !empty($prefs['project']),
            'order'        => !empty($prefs['order']),
            'news'         => !empty($prefs['news']),
            'security'     => !empty($prefs['security']),
            'push_enabled' => !empty($prefs['push_enabled']),
        );
        update_user_meta(absint($user_id), self::USER_META_PREFS, $clean);
        return $clean;
    }

    private static function category_enabled($user_id, $category) {
        $prefs = self::get_prefs($user_id);
        $map = array(
            'chat'     => 'chat',
            'project'  => 'project',
            'order'    => 'order',
            'news'     => 'news',
            'security' => 'security',
        );
        $key = isset($map[$category]) ? $map[$category] : 'news';
        return !empty($prefs[$key]);
    }

    private static function maybe_push($user_id, $title, $body, $data) {
        if (!class_exists('PAXdesign_APNS') || !PAXdesign_APNS::is_configured()) {
            return;
        }
        $prefs = self::get_prefs($user_id);
        if (empty($prefs['push_enabled'])) {
            return;
        }
        $devices = get_user_meta(absint($user_id), self::USER_META_DEVICES, true);
        if (!is_array($devices)) {
            return;
        }
        foreach ($devices as $device) {
            if (empty($device['token']) || !empty($device['revoked'])) {
                continue;
            }
            PAXdesign_APNS::send($device, $title, $body, $data, $user_id, false);
        }
    }

    private static function format($row) {
        return array(
            'id'          => (int) $row['id'],
            'category'    => $row['category'],
            'title'       => $row['title'],
            'body'        => $row['body'],
            'entity_type' => $row['entity_type'],
            'entity_id'   => $row['entity_id'],
            'deep_link'   => $row['deep_link'],
            'is_read'     => !empty($row['is_read']),
            'created_at'  => $row['created_at'],
            'read_at'     => $row['read_at'],
        );
    }
}
