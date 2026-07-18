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
        self::notify_staff_new_order($id, $user_id, $service_label);
        return self::get_for_user($user_id, $id);
    }

    /**
     * Notify authorized staff when a customer submits a new order.
     */
    private static function notify_staff_new_order($order_id, $customer_user_id, $service_label) {
        global $wpdb;
        $order_id = absint($order_id);
        $customer_user_id = absint($customer_user_id);
        if ($order_id <= 0) {
            return;
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT order_ref, created_at FROM " . PAXdesign_Customer_DB::table('orders') . " WHERE id = %d LIMIT 1",
            $order_id
        ), ARRAY_A);
        if (!$row) {
            return;
        }
        $customer = get_user_by('id', $customer_user_id);
        $customer_name = $customer instanceof WP_User ? $customer->display_name : __('Customer', 'paxdesign-booking');
        $ref = (string) ($row['order_ref'] ?? '');
        $title = sprintf(__('New request from %s', 'paxdesign-booking'), $customer_name);
        $body = trim($service_label . ($ref !== '' ? ' · ' . $ref : ''));
        if (class_exists('PAXdesign_APNS')) {
            PAXdesign_APNS::notify_new_customer_order($order_id, $ref, $customer_name, $service_label, $body);
        }
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
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, body, created_at FROM " . PAXdesign_Customer_DB::table('order_notes') . " WHERE order_id = %d AND visibility = %s ORDER BY created_at DESC",
            $order_id,
            $visibility
        ), ARRAY_A);
        return self::normalize_int_ids($rows ?: array());
    }

    private static function files($order_id, $visibility) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, file_name, mime_type, file_size, kind, created_at FROM " . PAXdesign_Customer_DB::table('order_files') . " WHERE order_id = %d AND visibility = %s ORDER BY created_at DESC",
            $order_id,
            $visibility
        ), ARRAY_A);
        foreach ($rows ?: array() as &$row) {
            $row['id'] = (int) $row['id'];
            $row['file_size'] = (int) ($row['file_size'] ?? 0);
            $row['download_url'] = rest_url('pdx/v1/customer/orders/' . $order_id . '/files/' . $row['id'] . '/download');
        }
        unset($row);
        return $rows ?: array();
    }

    private static function activity($order_id, $limit) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_type, summary, created_at FROM " . PAXdesign_Customer_DB::table('order_activity') . " WHERE order_id = %d ORDER BY created_at DESC LIMIT %d",
            $order_id,
            $limit
        ), ARRAY_A);
        return self::normalize_int_ids($rows ?: array());
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function normalize_int_ids($rows) {
        foreach ($rows as &$row) {
            if (isset($row['id'])) {
                $row['id'] = (int) $row['id'];
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_for_staff($status = '', $limit = 100) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('orders');
        $sql = "SELECT o.*, u.display_name AS customer_name, u.user_email AS customer_email
                FROM $table o
                LEFT JOIN {$wpdb->users} u ON u.ID = o.customer_user_id
                WHERE 1=1";
        $params = array();
        if ($status !== '') {
            $sql .= " AND o.status = %s";
            $params[] = sanitize_key($status);
        }
        $sql .= " ORDER BY o.created_at DESC LIMIT %d";
        $params[] = max(1, min(200, (int) $limit));
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(array(__CLASS__, 'format_staff_order_summary'), $rows ?: array());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_for_staff($order_id) {
        global $wpdb;
        $order_id = absint($order_id);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, u.display_name AS customer_name, u.user_email AS customer_email
             FROM " . PAXdesign_Customer_DB::table('orders') . " o
             LEFT JOIN {$wpdb->users} u ON u.ID = o.customer_user_id
             WHERE o.id = %d LIMIT 1",
            $order_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $order = self::format_staff_order_summary($row);
        $order['notes'] = self::staff_notes($order_id);
        $order['files'] = self::staff_files($order_id);
        $order['activity'] = self::activity($order_id, 50);
        if ((int) ($row['assigned_user_id'] ?? 0) > 0) {
            $assignee = get_user_by('id', (int) $row['assigned_user_id']);
            $order['assigned'] = array(
                'user_id'      => (int) $row['assigned_user_id'],
                'display_name' => $assignee instanceof WP_User ? $assignee->display_name : '',
            );
        }
        return $order;
    }

    private static function staff_notes($order_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, body, visibility, created_at FROM " . PAXdesign_Customer_DB::table('order_notes') . " WHERE order_id = %d ORDER BY created_at DESC",
            absint($order_id)
        ), ARRAY_A);
    }

    private static function staff_files($order_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, file_name, mime_type, file_size, kind, visibility, created_at FROM " . PAXdesign_Customer_DB::table('order_files') . " WHERE order_id = %d ORDER BY created_at DESC",
            absint($order_id)
        ), ARRAY_A);
        foreach ($rows as &$row) {
            $row['download_url'] = rest_url('pdx/v1/customer/staff/orders/' . absint($order_id) . '/files/' . (int) $row['id'] . '/download');
        }
        return $rows;
    }

    private static function format_staff_order_summary($row) {
        return array(
            'id'             => (int) $row['id'],
            'ref'            => (string) ($row['order_ref'] ?? ''),
            'service_slug'   => (string) ($row['service_slug'] ?? ''),
            'service_label'  => (string) ($row['service_label'] ?? ''),
            'status'         => (string) ($row['status'] ?? ''),
            'description'    => (string) ($row['description'] ?? ''),
            'customer_user_id' => (int) ($row['customer_user_id'] ?? 0),
            'customer_name'  => (string) ($row['customer_name'] ?? ''),
            'customer_email' => (string) ($row['customer_email'] ?? ''),
            'project_id'     => (int) ($row['project_id'] ?? 0),
            'expected_delivery' => $row['expected_delivery'] ?? null,
            'created_at'     => (string) ($row['created_at'] ?? ''),
            'updated_at'     => (string) ($row['updated_at'] ?? ''),
        );
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

    public static function get_file_for_user($user_id, $order_id, $file_id) {
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_user_id FROM " . PAXdesign_Customer_DB::table('orders') . " WHERE id = %d AND customer_user_id = %d LIMIT 1",
            absint($order_id),
            absint($user_id)
        ), ARRAY_A);
        if (!$order) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . PAXdesign_Customer_DB::table('order_files') . " WHERE id = %d AND order_id = %d AND visibility = 'customer' LIMIT 1",
            absint($file_id),
            absint($order_id)
        ), ARRAY_A);
    }

    /**
     * Aggregated file library for customer portal (projects + orders).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function library_for_user($user_id, $limit = 50) {
        global $wpdb;
        $user_id = absint($user_id);
        $limit = max(1, min(100, (int) $limit));
        $items = array();

        $project_files = $wpdb->get_results($wpdb->prepare(
            "SELECT pf.id, pf.file_name, pf.mime_type, pf.file_size, pf.category AS kind, pf.created_at, p.id AS parent_id, p.title AS parent_title
             FROM " . PAXdesign_Customer_DB::table('project_files') . " pf
             INNER JOIN " . PAXdesign_Customer_DB::table('projects') . " p ON p.id = pf.project_id
             WHERE p.customer_user_id = %d AND pf.visibility = 'customer'
             ORDER BY pf.created_at DESC LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
        foreach ($project_files ?: array() as $row) {
            $file_id = (int) ($row['id'] ?? 0);
            $parent_id = (int) ($row['parent_id'] ?? 0);
            if ($file_id <= 0) {
                continue;
            }
            $items[] = array(
                'id'           => $file_id,
                'source'       => 'project',
                'parent_id'    => $parent_id,
                'parent_title' => (string) ($row['parent_title'] ?? ''),
                'file_name'    => (string) ($row['file_name'] ?? ''),
                'mime_type'    => (string) ($row['mime_type'] ?? 'application/octet-stream'),
                'file_size'    => (int) ($row['file_size'] ?? 0),
                'kind'         => (string) ($row['kind'] ?? 'file'),
                'created_at'   => (string) ($row['created_at'] ?? ''),
                'download_url' => rest_url('pdx/v1/customer/projects/' . $parent_id . '/files/' . $file_id . '/download'),
            );
        }

        $order_files = $wpdb->get_results($wpdb->prepare(
            "SELECT ofl.id, ofl.file_name, ofl.mime_type, ofl.file_size, ofl.kind, ofl.created_at, o.id AS parent_id, o.service_label AS parent_title
             FROM " . PAXdesign_Customer_DB::table('order_files') . " ofl
             INNER JOIN " . PAXdesign_Customer_DB::table('orders') . " o ON o.id = ofl.order_id
             WHERE o.customer_user_id = %d AND ofl.visibility = 'customer'
             ORDER BY ofl.created_at DESC LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
        foreach ($order_files ?: array() as $row) {
            $file_id = (int) ($row['id'] ?? 0);
            $parent_id = (int) ($row['parent_id'] ?? 0);
            if ($file_id <= 0) {
                continue;
            }
            $items[] = array(
                'id'           => $file_id,
                'source'       => 'order',
                'parent_id'    => $parent_id,
                'parent_title' => (string) ($row['parent_title'] ?? ''),
                'file_name'    => (string) ($row['file_name'] ?? ''),
                'mime_type'    => (string) ($row['mime_type'] ?? 'application/octet-stream'),
                'file_size'    => (int) ($row['file_size'] ?? 0),
                'kind'         => (string) ($row['kind'] ?? 'file'),
                'created_at'   => (string) ($row['created_at'] ?? ''),
                'download_url' => rest_url('pdx/v1/customer/orders/' . $parent_id . '/files/' . $file_id . '/download'),
            );
        }

        usort($items, static function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return array_slice($items, 0, $limit);
    }
}
