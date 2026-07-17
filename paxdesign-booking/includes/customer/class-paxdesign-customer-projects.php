<?php
/**
 * Customer projects data access.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Projects {

    public static function list_for_user($user_id, $status = '') {
        global $wpdb;
        $user_id = absint($user_id);
        $table = PAXdesign_Customer_DB::table('projects');
        $sql = "SELECT * FROM $table WHERE customer_user_id = %d";
        $params = array($user_id);
        if ($status !== '') {
            $sql .= " AND status = %s";
            $params[] = sanitize_key($status);
        }
        $sql .= " ORDER BY updated_at DESC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(array(__CLASS__, 'format_project'), $rows ?: array());
    }

    public static function get_for_user($user_id, $project_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . PAXdesign_Customer_DB::table('projects') . " WHERE id = %d AND customer_user_id = %d LIMIT 1",
            absint($project_id),
            absint($user_id)
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $project = self::format_project($row);
        $project['milestones'] = self::milestones((int) $row['id'], 'customer');
        $project['notes'] = self::notes((int) $row['id'], 'customer');
        $project['files'] = self::files((int) $row['id'], 'customer');
        $project['assignees'] = self::assignees((int) $row['id']);
        $project['activity'] = self::activity((int) $row['id'], 30);
        return $project;
    }

    public static function create($data, $actor_id) {
        global $wpdb;
        $customer_id = absint($data['customer_user_id'] ?? 0);
        $title = sanitize_text_field($data['title'] ?? '');
        if ($customer_id <= 0 || $title === '') {
            return new WP_Error('invalid_project', __('Project title and customer are required.', 'paxdesign-booking'), array('status' => 400));
        }
        $now = current_time('mysql', true);
        $wpdb->insert(PAXdesign_Customer_DB::table('projects'), array(
            'project_ref'         => PAXdesign_Customer_DB::generate_ref('PRJ'),
            'customer_user_id'    => $customer_id,
            'title'               => $title,
            'description'         => wp_kses_post($data['description'] ?? ''),
            'status'              => sanitize_key($data['status'] ?? 'planning'),
            'progress'            => min(100, max(0, (int) ($data['progress'] ?? 0))),
            'start_date'          => self::nullable_date($data['start_date'] ?? null),
            'expected_completion' => self::nullable_date($data['expected_completion'] ?? null),
            'chat_session_id'     => sanitize_text_field($data['chat_session_id'] ?? ''),
            'created_at'          => $now,
            'updated_at'          => $now,
            'created_by'          => absint($actor_id),
        ));
        $id = (int) $wpdb->insert_id;
        self::log_activity($id, $actor_id, 'project_created', __('Project created', 'paxdesign-booking'));
        PAXdesign_Customer_Notifications::notify_user($customer_id, 'project', __('New project', 'paxdesign-booking'), $title, 'project', (string) $id, '/projects/' . $id);
        return self::get_for_user($customer_id, $id);
    }

    public static function update($project_id, $data, $actor_id) {
        global $wpdb;
        $project_id = absint($project_id);
        $table = PAXdesign_Customer_DB::table('projects');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $project_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('not_found', __('Project not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $fields = array('updated_at' => current_time('mysql', true));
        foreach (array('title', 'description', 'status') as $key) {
            if (isset($data[$key])) {
                $fields[$key] = $key === 'description' ? wp_kses_post($data[$key]) : sanitize_text_field($data[$key]);
            }
        }
        if (isset($data['progress'])) {
            $fields['progress'] = min(100, max(0, (int) $data['progress']));
        }
        if (isset($data['start_date'])) {
            $fields['start_date'] = self::nullable_date($data['start_date']);
        }
        if (isset($data['expected_completion'])) {
            $fields['expected_completion'] = self::nullable_date($data['expected_completion']);
        }
        $wpdb->update($table, $fields, array('id' => $project_id));
        self::log_activity($project_id, $actor_id, 'project_updated', __('Project updated', 'paxdesign-booking'));
        PAXdesign_Customer_Notifications::notify_user((int) $row['customer_user_id'], 'project', __('Project updated', 'paxdesign-booking'), $fields['title'] ?? $row['title'], 'project', (string) $project_id, '/projects/' . $project_id);
        return self::get_for_user((int) $row['customer_user_id'], $project_id);
    }

    private static function milestones($project_id, $visibility = 'customer') {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, description, status, sort_order, due_date, completed_at FROM " . PAXdesign_Customer_DB::table('project_milestones') . " WHERE project_id = %d ORDER BY sort_order ASC, id ASC",
            $project_id
        ), ARRAY_A);
    }

    private static function notes($project_id, $visibility) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, body, created_at, author_user_id FROM " . PAXdesign_Customer_DB::table('project_notes') . " WHERE project_id = %d AND visibility = %s ORDER BY created_at DESC",
            $project_id,
            $visibility
        ), ARRAY_A);
    }

    private static function files($project_id, $visibility) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, file_name, mime_type, file_size, category, created_at FROM " . PAXdesign_Customer_DB::table('project_files') . " WHERE project_id = %d AND visibility = %s ORDER BY created_at DESC",
            $project_id,
            $visibility
        ), ARRAY_A);
    }

    private static function assignees($project_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, role_label, assigned_at FROM " . PAXdesign_Customer_DB::table('project_assignees') . " WHERE project_id = %d",
            $project_id
        ), ARRAY_A);
        foreach ($rows as &$row) {
            $user = get_user_by('id', (int) $row['user_id']);
            $row['display_name'] = $user ? $user->display_name : '';
            $row['avatar_url'] = $user ? get_avatar_url($user->ID, array('size' => 96)) : '';
        }
        return $rows;
    }

    private static function activity($project_id, $limit = 20) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, summary, created_at FROM " . PAXdesign_Customer_DB::table('project_activity') . " WHERE project_id = %d ORDER BY created_at DESC LIMIT %d",
            $project_id,
            $limit
        ), ARRAY_A);
    }

    public static function log_activity($project_id, $actor_id, $type, $summary, $meta = array()) {
        global $wpdb;
        $wpdb->insert(PAXdesign_Customer_DB::table('project_activity'), array(
            'project_id'    => absint($project_id),
            'actor_user_id' => absint($actor_id),
            'event_type'    => sanitize_key($type),
            'summary'       => sanitize_text_field($summary),
            'meta_json'     => wp_json_encode($meta),
            'created_at'    => current_time('mysql', true),
        ));
    }

    private static function format_project($row) {
        return array(
            'id'                  => (int) $row['id'],
            'ref'                 => $row['project_ref'],
            'title'               => $row['title'],
            'description'         => $row['description'],
            'status'              => $row['status'],
            'progress'            => (int) $row['progress'],
            'start_date'          => $row['start_date'],
            'expected_completion' => $row['expected_completion'],
            'chat_session_id'     => $row['chat_session_id'],
            'updated_at'          => $row['updated_at'],
        );
    }

    private static function nullable_date($value) {
        if (empty($value)) {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts ? gmdate('Y-m-d', $ts) : null;
    }
}
