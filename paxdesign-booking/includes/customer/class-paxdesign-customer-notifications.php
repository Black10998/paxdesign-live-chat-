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
        self::maybe_push($user_id, $title, $body, self::build_apns_data(array(
            'notification_id' => $id,
            'category'        => $category,
            'entity_type'     => $entity_type,
            'entity_id'       => $entity_id,
            'deep_link'       => $deep_link,
            'user_id'         => $user_id,
        )));
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
        $updated = (bool) $wpdb->update(
            PAXdesign_Customer_DB::table('notifications'),
            array('is_read' => 1, 'read_at' => current_time('mysql', true)),
            array('id' => absint($notification_id), 'user_id' => absint($user_id)),
            array('%d', '%s'),
            array('%d', '%d')
        );
        if ($updated) {
            self::push_badge_sync($user_id);
        }
        return $updated;
    }

    /**
     * Mark all unread notifications for an entity as read (e.g. cybercrime ticket opened).
     *
     * @return int Number of rows updated.
     */
    public static function mark_read_for_entity($user_id, $entity_type, $entity_id) {
        global $wpdb;
        $user_id = absint($user_id);
        $entity_type = sanitize_key((string) $entity_type);
        $entity_id = sanitize_text_field((string) $entity_id);
        if ($user_id <= 0 || $entity_type === '' || $entity_id === '') {
            return 0;
        }
        $table = PAXdesign_Customer_DB::table('notifications');
        $updated = (int) $wpdb->query($wpdb->prepare(
            "UPDATE $table SET is_read = 1, read_at = %s WHERE user_id = %d AND entity_type = %s AND entity_id = %s AND is_read = 0",
            current_time('mysql', true),
            $user_id,
            $entity_type,
            $entity_id
        ));
        if ($updated > 0) {
            self::push_badge_sync($user_id);
        }
        return $updated;
    }

    /**
     * Silent badge refresh for the customer's other devices after read state changes.
     */
    public static function push_badge_sync($user_id) {
        if (!class_exists('PAXdesign_APNS') || !PAXdesign_APNS::is_configured()) {
            return;
        }
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }
        $prefs = self::get_prefs($user_id);
        if (empty($prefs['push_enabled'])) {
            return;
        }
        $devices = get_user_meta($user_id, self::USER_META_DEVICES, true);
        if (!is_array($devices)) {
            return;
        }
        $data = self::build_apns_data(array(
            'notification_id' => 1,
            'category'        => 'news',
            'type'            => 'badge_sync',
            'event'           => 'badge_sync',
            'user_id'         => $user_id,
        ));
        foreach ($devices as $device) {
            if (empty($device['token']) || !empty($device['revoked'])) {
                continue;
            }
            PAXdesign_APNS::send($device, '', '', $data, $user_id, true);
        }
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function build_apns_data(array $data) {
        $category = sanitize_key((string) ($data['category'] ?? 'news'));
        $type_map = array(
            'chat'     => 'message',
            'order'    => 'order_update',
            'project'  => 'project_update',
            'news'     => 'news',
            'security' => 'security_alert',
        );
        $data['type'] = isset($type_map[$category]) ? $type_map[$category] : 'message';
        $data['event'] = 'customer_' . $category;
        if (!empty($data['user_id'])) {
            $data['user_id'] = absint($data['user_id']);
        }
        if (($data['entity_type'] ?? '') === 'chat' && !empty($data['entity_id'])) {
            $data['session_id'] = (string) $data['entity_id'];
        }
        return $data;
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
        if (!empty($data['notification_id'])) {
            global $wpdb;
            $wpdb->update(
                PAXdesign_Customer_DB::table('notifications'),
                array('push_sent' => 1),
                array('id' => absint($data['notification_id']), 'user_id' => absint($user_id)),
                array('%d'),
                array('%d', '%d')
            );
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

    public static function delete_for_entity($entity_type, $entity_id) {
        global $wpdb;
        $entity_type = sanitize_key((string) $entity_type);
        $entity_id = sanitize_text_field((string) $entity_id);
        if ($entity_type === '' || $entity_id === '') {
            return 0;
        }
        return (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . PAXdesign_Customer_DB::table('notifications') . ' WHERE entity_type = %s AND entity_id = %s',
            $entity_type,
            $entity_id
        ));
    }

    public static function broadcast_news($news_id, $title, $excerpt) {
        $news_id = absint($news_id);
        if ($news_id <= 0) {
            return;
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT slug, audience FROM " . PAXdesign_Customer_DB::table('news') . " WHERE id = %d AND status = 'published' LIMIT 1",
            $news_id
        ), ARRAY_A);
        if (!$row) {
            return;
        }

        $slug = sanitize_title((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            $slug = (string) $news_id;
        }
        $deep_link = '/news/' . $slug;

        $role = PAXdesign_Auth::customer_role();
        $users = get_users(array(
            'role'   => $role,
            'fields' => array('ID'),
            'number' => 500,
        ));
        foreach ($users as $user) {
            $uid = (int) $user->ID;
            if (class_exists('PAXdesign_Customer_News') && !PAXdesign_Customer_News::user_matches_audience($row, $uid)) {
                continue;
            }
            self::notify_user($uid, 'news', $title, $excerpt, 'news', $slug, $deep_link);
        }
    }

    /**
     * Recent notifications across all users for owner / staff administration.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function list_recent_for_admin($limit = 80) {
        global $wpdb;
        $limit = max(1, min(200, (int) $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name AS customer_name, u.user_email AS customer_email
             FROM " . self::table_or_notifications() . " n
             LEFT JOIN {$wpdb->users} u ON u.ID = n.user_id
             ORDER BY n.created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
        $out = array();
        foreach ($rows ?: array() as $row) {
            $item = self::format($row);
            $item['user_id'] = (int) ($row['user_id'] ?? 0);
            $item['customer_name'] = (string) ($row['customer_name'] ?? '');
            $item['customer_email'] = (string) ($row['customer_email'] ?? '');
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Send a notification to every manageable customer (and optionally one user).
     *
     * @return array<string, mixed>
     */
    public static function broadcast($title, $body, $category = 'general', $user_id = 0) {
        $title = sanitize_text_field((string) $title);
        $body = sanitize_textarea_field((string) $body);
        $category = sanitize_key((string) $category);
        if ($category === '') {
            $category = 'general';
        }
        $sent = 0;
        $user_id = absint($user_id);
        if ($user_id > 0) {
            $id = self::notify_user($user_id, $category, $title, $body, 'broadcast', '', '/account/#/notifications');
            return array('sent' => $id > 0 ? 1 : 0, 'notification_id' => $id);
        }

        $result = class_exists('PAXdesign_Customer_Registry')
            ? PAXdesign_Customer_Registry::query_manageable_customers('', 1, 100)
            : array('users' => array());
        $users = isset($result['users']) && is_array($result['users']) ? $result['users'] : array();
        foreach ($users as $user) {
            $uid = $user instanceof WP_User ? (int) $user->ID : 0;
            if ($uid <= 0) {
                continue;
            }
            if (self::notify_user($uid, $category, $title, $body, 'broadcast', '', '/account/#/notifications')) {
                $sent++;
            }
        }
        return array('sent' => $sent);
    }

    /**
     * @return string
     */
    private static function table_or_notifications() {
        return PAXdesign_Customer_DB::table('notifications');
    }
}
