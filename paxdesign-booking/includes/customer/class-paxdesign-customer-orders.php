<?php
/**
 * Customer orders / service requests (not PayPal module purchases).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Orders {

    public static function list_for_user($user_id, $status = '') {
        global $wpdb;
        $user_id = absint($user_id);
        $table = PAXdesign_Customer_DB::table('orders');
        $sql = "SELECT * FROM $table WHERE customer_user_id = %d";
        $params = array($user_id);
        if ($status !== '') {
            $sql .= " AND status = %s";
            $params[] = sanitize_key($status);
        }
        $sql .= " ORDER BY updated_at DESC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(array(__CLASS__, 'format_order'), $rows ?: array());
    }

    public static function get_for_user($user_id, $order_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . PAXdesign_Customer_DB::table('orders') . " WHERE id = %d AND customer_user_id = %d LIMIT 1",
            absint($order_id),
            absint($user_id)
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $order = self::format_order($row);
        $order['notes'] = self::notes((int) $row['id'], 'customer');
        $order['files'] = self::files((int) $row['id'], 'customer');
        $order['activity'] = self::activity((int) $row['id'], 30);
        if ((int) $row['assigned_user_id'] > 0) {
            $user = get_user_by('id', (int) $row['assigned_user_id']);
            $order['assigned'] = array(
                'user_id'      => (int) $row['assigned_user_id'],
                'display_name' => $user ? $user->display_name : '',
            );
        }
        return $order;
    }

    public static function create_request($user_id, $data) {
        global $wpdb;
        $user_id = absint($user_id);
        $service = sanitize_key($data['service_slug'] ?? '');
        $service_label = sanitize_text_field($data['service_label'] ?? '');
        if ($service === '' && $service_label === '') {
            return new WP_Error('invalid_order', __('A service is required for this request.', 'paxdesign-booking'), array('status' => 400));
        }
        if ($service !== '' && $service_label === '') {
            $svc = PAXdesign_Customer_Services::get_by_slug($service);
            $service_label = $svc ? $svc['name'] : $service;
        }
        $now = current_time('mysql', true);
        $wpdb->insert(PAXdesign_Customer_DB::table('orders'), array(
            'order_ref'         => PAXdesign_Customer_DB::generate_ref('ORD'),
            'customer_user_id'  => $user_id,
            'project_id'        => absint($data['project_id'] ?? 0),
            'service_slug'      => $service,
            'service_label'     => $service_label,
            'status'            => 'received',
            'description'       => sanitize_textarea_field($data['description'] ?? ''),
            'assigned_user_id'  => 0,
            'booking_id'        => absint($data['booking_id'] ?? 0),
            'expected_delivery' => null,
            'created_at'        => $now,
            'updated_at'        => $now,
            'created_by'        => $user_id,
        ));
        $id = (int) $wpdb->insert_id;
        self::log_activity($id, $user_id, 'order_created', __('Service request submitted', 'paxdesign-booking'));
        PAXdesign_Customer_Notifications::notify_user($user_id, 'order', __('Request received', 'paxdesign-booking'), $service_label, 'order', (string) $id, '/orders/' . $id);
        return self::get_for_user($user_id, $id);
    }

    public static function link_bookings_for_user($user_id) {
        global $wpdb;
        $user = get_user_by('id', absint($user_id));
        if (!$user) {
            return 0;
        }
        $bookings = $wpdb->prefix . 'paxdesign_bookings';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bookings));
        if ($exists !== $bookings) {
            return 0;
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, service, customer_name, message, status, created_at FROM $bookings WHERE customer_email = %s ORDER BY created_at DESC LIMIT 20",
            $user->user_email
        ));
        $linked = 0;
        foreach ($rows as $row) {
            $already = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM " . PAXdesign_Customer_DB::table('orders') . " WHERE booking_id = %d",
                (int) $row->id
            ));
            if ($already > 0) {
                continue;
            }
            $wpdb->insert(PAXdesign_Customer_DB::table('orders'), array(
                'order_ref'         => PAXdesign_Customer_DB::generate_ref('BKG'),
                'customer_user_id'  => $user_id,
                'service_slug'      => sanitize_key($row->service ?? ''),
                'service_label'     => sanitize_text_field($row->service ?? 'Appointment'),
                'status'            => sanitize_key($row->status ?? 'pending'),
                'description'       => sanitize_textarea_field($row->message ?? ''),
                'booking_id'        => (int) $row->id,
                'created_at'        => $row->created_at,
                'updated_at'        => $row->created_at,
                'created_by'        => $user_id,
            ));
            $linked++;
        }
        return $linked;
    }

    private static function notes($order_id, $visibility) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, body, created_at FROM " . PAXdesign_Customer_DB::table('order_notes') . " WHERE order_id = %d AND visibility = %s ORDER BY created_at DESC",
            $order_id,
            $visibility
        ), ARRAY_A);
    }

    private static function files($order_id, $visibility) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, file_name, mime_type, file_size, kind, created_at FROM " . PAXdesign_Customer_DB::table('order_files') . " WHERE order_id = %d AND visibility = %s ORDER BY created_at DESC",
            $order_id,
            $visibility
        ), ARRAY_A);
    }

    private static function activity($order_id, $limit) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, summary, created_at FROM " . PAXdesign_Customer_DB::table('order_activity') . " WHERE order_id = %d ORDER BY created_at DESC LIMIT %d",
            $order_id,
            $limit
        ), ARRAY_A);
    }

    public static function log_activity($order_id, $actor_id, $type, $summary, $meta = array()) {
        global $wpdb;
        $wpdb->insert(PAXdesign_Customer_DB::table('order_activity'), array(
            'order_id'      => absint($order_id),
            'actor_user_id' => absint($actor_id),
            'event_type'    => sanitize_key($type),
            'summary'       => sanitize_text_field($summary),
            'meta_json'     => wp_json_encode($meta),
            'created_at'    => current_time('mysql', true),
        ));
    }

    private static function format_order($row) {
        return array(
            'id'                => (int) $row['id'],
            'ref'               => $row['order_ref'],
            'service_slug'      => $row['service_slug'],
            'service_label'     => $row['service_label'],
            'status'            => $row['status'],
            'description'       => $row['description'],
            'project_id'        => (int) $row['project_id'],
            'booking_id'        => (int) $row['booking_id'],
            'expected_delivery' => $row['expected_delivery'],
            'updated_at'        => $row['updated_at'],
            'created_at'        => $row['created_at'],
        );
    }

    public static function staff_update($order_id, $data, $actor_id) {
        global $wpdb;
        $order_id = absint($order_id);
        $table = PAXdesign_Customer_DB::table('orders');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $order_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', __('Order not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $fields = array('updated_at' => current_time('mysql', true));
        if (isset($data['status'])) {
            $fields['status'] = sanitize_key($data['status']);
        }
        if (isset($data['assigned_user_id'])) {
            $fields['assigned_user_id'] = absint($data['assigned_user_id']);
        }
        if (isset($data['expected_delivery'])) {
            $fields['expected_delivery'] = empty($data['expected_delivery']) ? null : gmdate('Y-m-d', strtotime((string) $data['expected_delivery']));
        }
        if (isset($data['note']) && trim((string) $data['note']) !== '') {
            $note = sanitize_textarea_field($data['note']);
            $visibility = sanitize_key($data['note_visibility'] ?? 'customer');
            $wpdb->insert(PAXdesign_Customer_DB::table('order_notes'), array(
                'order_id'       => $order_id,
                'author_user_id' => absint($actor_id),
                'visibility'     => in_array($visibility, array('customer', 'internal'), true) ? $visibility : 'customer',
                'body'           => $note,
                'created_at'     => current_time('mysql', true),
            ));
        }
        $wpdb->update($table, $fields, array('id' => $order_id));
        self::log_activity($order_id, $actor_id, 'order_updated', __('Request updated', 'paxdesign-booking'));
        PAXdesign_Customer_Notifications::notify_user(
            (int) $row['customer_user_id'],
            'order',
            __('Request updated', 'paxdesign-booking'),
            $fields['status'] ?? $row['status'],
            'order',
            (string) $order_id,
            '/orders/' . $order_id
        );
        return self::get_for_user((int) $row['customer_user_id'], $order_id);
    }
}
