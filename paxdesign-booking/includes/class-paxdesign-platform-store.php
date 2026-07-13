<?php
/**
 * Site-wide platform data for iOS business modules (tasks, calendar, files, activity, settings).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Platform_Store {

    const OPTION_KEY = 'paxdesign_platform_store_v1';
    const MAX_ACTIVITY = 250;

    /**
     * @return array<string, mixed>
     */
    private static function default_store() {
        return array(
            'tasks'    => array(),
            'calendar' => array(),
            'files'    => array(),
            'activity' => array(),
            'customers' => array(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_store() {
        $data = get_option(self::OPTION_KEY, array());
        if (!is_array($data)) {
            $data = array();
        }
        return array_merge(self::default_store(), $data);
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function save_store($store) {
        update_option(self::OPTION_KEY, $store, false);
    }

    private static function new_id($prefix) {
        return $prefix . '_' . wp_generate_uuid4();
    }

    private static function now_iso() {
        return gmdate('c');
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function normalize_task($entry) {
        $priority = isset($entry['priority']) ? sanitize_key($entry['priority']) : 'medium';
        if (!in_array($priority, array('low', 'medium', 'high'), true)) {
            $priority = 'medium';
        }
        $assigned_to = isset($entry['assigned_to']) ? (int) $entry['assigned_to'] : 0;
        if ($assigned_to < 0) {
            $assigned_to = 0;
        }
        $assigned_name = '';
        if ($assigned_to > 0) {
            $assigned = get_user_by('id', $assigned_to);
            if ($assigned instanceof WP_User) {
                $assigned_name = sanitize_text_field($assigned->display_name);
            } else {
                $assigned_to = 0;
            }
        }
        return array(
            'id'           => !empty($entry['id']) ? sanitize_text_field($entry['id']) : self::new_id('task'),
            'title'        => sanitize_text_field($entry['title'] ?? ''),
            'notes'        => sanitize_textarea_field($entry['notes'] ?? ''),
            'due_date'     => !empty($entry['due_date']) ? sanitize_text_field($entry['due_date']) : null,
            'is_completed' => !empty($entry['is_completed']),
            'priority'     => $priority,
            'created_at'   => !empty($entry['created_at']) ? sanitize_text_field($entry['created_at']) : self::now_iso(),
            'created_by'   => isset($entry['created_by']) ? (int) $entry['created_by'] : (int) get_current_user_id(),
            'assigned_to'  => $assigned_to,
            'assigned_name'=> $assigned_name,
            'updated_at'   => self::now_iso(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_tasks($user_id = 0, $can_manage_all = true) {
        $store = self::get_store();
        $items = isset($store['tasks']) && is_array($store['tasks']) ? $store['tasks'] : array();
        $uid = (int) $user_id;
        if (!$can_manage_all && $uid > 0) {
            $items = array_values(array_filter($items, function ($task) use ($uid) {
                $assigned_to = isset($task['assigned_to']) ? (int) $task['assigned_to'] : 0;
                $created_by  = isset($task['created_by']) ? (int) $task['created_by'] : 0;
                return $assigned_to === $uid || $created_by === $uid;
            }));
        }
        usort($items, function ($a, $b) {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });
        return array_values($items);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public static function save_task($payload, $actor_user_id = 0, $can_assign = true) {
        $title = sanitize_text_field($payload['title'] ?? '');
        if ($title === '') {
            return new WP_Error('invalid_task', __('Task title is required.', 'paxdesign-booking'), array('status' => 400));
        }

        $actor_user_id = (int) $actor_user_id;
        if ($actor_user_id <= 0) {
            $actor_user_id = (int) get_current_user_id();
        }

        $store = self::get_store();
        $tasks = isset($store['tasks']) && is_array($store['tasks']) ? $store['tasks'] : array();
        $id    = !empty($payload['id']) ? sanitize_text_field($payload['id']) : '';
        $found = false;

        foreach ($tasks as $index => $task) {
            if (!empty($task['id']) && $task['id'] === $id) {
                $merged = array_merge($task, $payload);
                if (!$can_assign) {
                    $existing_assignee = isset($task['assigned_to']) ? (int) $task['assigned_to'] : 0;
                    $merged['assigned_to'] = $existing_assignee > 0 ? $existing_assignee : $actor_user_id;
                } else {
                    $merged['assigned_to'] = isset($payload['assigned_to']) ? (int) $payload['assigned_to'] : (int) ($task['assigned_to'] ?? 0);
                }
                $tasks[$index] = self::normalize_task($merged);
                $found = true;
                $saved = $tasks[$index];
                break;
            }
        }

        if (!$found) {
            $payload['created_by'] = $actor_user_id;
            if (!$can_assign) {
                $payload['assigned_to'] = $actor_user_id;
            } else {
                $payload['assigned_to'] = isset($payload['assigned_to']) ? (int) $payload['assigned_to'] : 0;
            }
            $saved = self::normalize_task($payload);
            array_unshift($tasks, $saved);
        }

        $store['tasks'] = $tasks;
        self::save_store($store);
        self::append_activity('tasks', __('Task saved', 'paxdesign-booking'), $title, 'action');
        return $saved;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_team_members() {
        $members = array();
        $seen = array();

        $current = wp_get_current_user();
        if ($current instanceof WP_User && !empty($current->ID)) {
            $members[] = array(
                'user_id' => (int) $current->ID,
                'name'    => sanitize_text_field($current->display_name),
                'email'   => sanitize_email($current->user_email),
                'role'    => 'current',
            );
            $seen[(int) $current->ID] = true;
        }

        foreach (PAXdesign_Live_Chat_Permissions::list_staff_for_api() as $member) {
            $uid = isset($member['user_id']) ? (int) $member['user_id'] : 0;
            if ($uid <= 0 || isset($seen[$uid]) || empty($member['enabled'])) {
                continue;
            }
            $members[] = array(
                'user_id' => $uid,
                'name'    => sanitize_text_field((string) ($member['name'] ?? '')),
                'email'   => sanitize_email((string) ($member['email'] ?? '')),
                'role'    => !empty($member['permissions'][PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS]) ? 'manager' : 'staff',
            );
            $seen[$uid] = true;
        }

        usort($members, function ($a, $b) {
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return array_values($members);
    }

    /**
     * @return true|WP_Error
     */
    public static function delete_task($id) {
        $store = self::get_store();
        $tasks = isset($store['tasks']) && is_array($store['tasks']) ? $store['tasks'] : array();
        $next  = array();
        $deleted = false;
        foreach ($tasks as $task) {
            if (!empty($task['id']) && $task['id'] === $id) {
                $deleted = true;
                continue;
            }
            $next[] = $task;
        }
        if (!$deleted) {
            return new WP_Error('not_found', __('Task not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $store['tasks'] = $next;
        self::save_store($store);
        return true;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function normalize_customer_profile($entry) {
        $session_id = sanitize_text_field($entry['session_id'] ?? '');
        $visible_raw = isset($entry['visible_details']) && is_array($entry['visible_details']) ? $entry['visible_details'] : array();
        $visible = array(
            'show_email'   => !empty($visible_raw['show_email']),
            'show_phone'   => !empty($visible_raw['show_phone']),
            'show_company' => !empty($visible_raw['show_company']),
            'show_notes'   => !empty($visible_raw['show_notes']),
        );

        return array(
            'session_id'       => $session_id,
            'display_name'     => sanitize_text_field($entry['display_name'] ?? ''),
            'avatar_url'       => esc_url_raw((string) ($entry['avatar_url'] ?? '')),
            'email'            => sanitize_email((string) ($entry['email'] ?? '')),
            'phone'            => sanitize_text_field((string) ($entry['phone'] ?? '')),
            'company'          => sanitize_text_field((string) ($entry['company'] ?? '')),
            'notes'            => sanitize_textarea_field((string) ($entry['notes'] ?? '')),
            'visible_details'  => $visible,
            'updated_at'       => !empty($entry['updated_at']) ? sanitize_text_field((string) $entry['updated_at']) : self::now_iso(),
            'updated_by'       => isset($entry['updated_by']) ? (int) $entry['updated_by'] : (int) get_current_user_id(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function list_customer_session_rows() {
        if (!class_exists('PAXdesign_Chat_Log')) {
            return array();
        }

        PAXdesign_Chat_Log::create_table();
        if (class_exists('PAXdesign_Chat_Live')) {
            PAXdesign_Chat_Live::upgrade_schema();
        }

        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();
        $rows = $wpdb->get_results(
            "SELECT session_id, customer_name, updated_at FROM $table WHERE customer_name <> '' ORDER BY updated_at DESC LIMIT 300",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_customer_profiles() {
        $store = self::get_store();
        $stored = isset($store['customers']) && is_array($store['customers']) ? $store['customers'] : array();

        $profiles = array();
        foreach ($stored as $session_id => $entry) {
            $normalized = self::normalize_customer_profile(is_array($entry) ? $entry : array());
            if ($normalized['session_id'] === '') {
                $normalized['session_id'] = sanitize_text_field((string) $session_id);
            }
            if ($normalized['session_id'] === '') {
                continue;
            }
            $profiles[$normalized['session_id']] = $normalized;
        }

        foreach (self::list_customer_session_rows() as $row) {
            $session_id = sanitize_text_field((string) ($row['session_id'] ?? ''));
            if ($session_id === '') {
                continue;
            }
            $session_name = sanitize_text_field((string) ($row['customer_name'] ?? ''));
            if (!isset($profiles[$session_id])) {
                $profiles[$session_id] = self::normalize_customer_profile(array(
                    'session_id' => $session_id,
                    'display_name' => $session_name,
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ));
            } elseif ($profiles[$session_id]['display_name'] === '' && $session_name !== '') {
                $profiles[$session_id]['display_name'] = $session_name;
            }
            if (!empty($row['updated_at']) && empty($profiles[$session_id]['updated_at'])) {
                $profiles[$session_id]['updated_at'] = sanitize_text_field((string) $row['updated_at']);
            }
        }

        $items = array_values($profiles);
        usort($items, function ($a, $b) {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public static function save_customer_profile($payload) {
        $session_id = sanitize_text_field($payload['session_id'] ?? '');
        if ($session_id === '') {
            return new WP_Error('invalid_customer_profile', __('Session ID is required.', 'paxdesign-booking'), array('status' => 400));
        }

        $store = self::get_store();
        $stored = isset($store['customers']) && is_array($store['customers']) ? $store['customers'] : array();
        $previous = isset($stored[$session_id]) && is_array($stored[$session_id]) ? $stored[$session_id] : array();
        $merged = array_merge($previous, $payload, array('session_id' => $session_id));
        $merged['updated_at'] = self::now_iso();
        $merged['updated_by'] = (int) get_current_user_id();
        $saved = self::normalize_customer_profile($merged);
        if ($saved['display_name'] === '') {
            $saved['display_name'] = __('Kunde', 'paxdesign-booking');
        }

        $stored[$session_id] = $saved;
        $store['customers'] = $stored;
        self::save_store($store);

        if (class_exists('PAXdesign_Chat_Log')) {
            global $wpdb;
            $wpdb->update(
                PAXdesign_Chat_Log::table_name(),
                array(
                    'customer_name' => $saved['display_name'],
                    'updated_at'    => current_time('mysql'),
                ),
                array('session_id' => $session_id),
                array('%s', '%s'),
                array('%s')
            );
        }

        self::append_activity('customers', __('Customer profile saved', 'paxdesign-booking'), $saved['display_name'], 'action');
        return $saved;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function normalize_event($entry) {
        $category = isset($entry['category']) ? sanitize_key($entry['category']) : 'appointment';
        if (!in_array($category, array('meeting', 'appointment', 'reminder', 'liveSession'), true)) {
            $category = 'appointment';
        }
        return array(
            'id'         => !empty($entry['id']) ? sanitize_text_field($entry['id']) : self::new_id('event'),
            'title'      => sanitize_text_field($entry['title'] ?? ''),
            'notes'      => sanitize_textarea_field($entry['notes'] ?? ''),
            'start_date' => sanitize_text_field($entry['start_date'] ?? self::now_iso()),
            'end_date'   => sanitize_text_field($entry['end_date'] ?? self::now_iso()),
            'category'   => $category,
            'created_by' => isset($entry['created_by']) ? (int) $entry['created_by'] : (int) get_current_user_id(),
            'updated_at' => self::now_iso(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_events() {
        $store = self::get_store();
        $items = isset($store['calendar']) && is_array($store['calendar']) ? $store['calendar'] : array();
        usort($items, function ($a, $b) {
            return strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? ''));
        });
        return array_values($items);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public static function save_event($payload) {
        $title = sanitize_text_field($payload['title'] ?? '');
        if ($title === '') {
            return new WP_Error('invalid_event', __('Event title is required.', 'paxdesign-booking'), array('status' => 400));
        }

        $store  = self::get_store();
        $events = isset($store['calendar']) && is_array($store['calendar']) ? $store['calendar'] : array();
        $id     = !empty($payload['id']) ? sanitize_text_field($payload['id']) : '';
        $found  = false;

        foreach ($events as $index => $event) {
            if (!empty($event['id']) && $event['id'] === $id) {
                $merged = array_merge($event, $payload);
                $events[$index] = self::normalize_event($merged);
                $found = true;
                $saved = $events[$index];
                break;
            }
        }

        if (!$found) {
            $saved = self::normalize_event($payload);
            $events[] = $saved;
        }

        $store['calendar'] = $events;
        self::save_store($store);
        self::append_activity('calendar', __('Event saved', 'paxdesign-booking'), $title, 'action');
        return $saved;
    }

    /**
     * @return true|WP_Error
     */
    public static function delete_event($id) {
        $store  = self::get_store();
        $events = isset($store['calendar']) && is_array($store['calendar']) ? $store['calendar'] : array();
        $next   = array();
        $deleted = false;
        foreach ($events as $event) {
            if (!empty($event['id']) && $event['id'] === $id) {
                $deleted = true;
                continue;
            }
            $next[] = $event;
        }
        if (!$deleted) {
            return new WP_Error('not_found', __('Event not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $store['calendar'] = $next;
        self::save_store($store);
        return true;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function normalize_file($entry) {
        $category = isset($entry['category']) ? sanitize_key($entry['category']) : 'other';
        if (!in_array($category, array('contracts', 'invoices', 'guides', 'media', 'other'), true)) {
            $category = 'other';
        }
        $attachment_id = isset($entry['attachment_id']) ? (int) $entry['attachment_id'] : 0;
        $url = '';
        if ($attachment_id > 0) {
            $url = (string) wp_get_attachment_url($attachment_id);
        } elseif (!empty($entry['url'])) {
            $url = esc_url_raw($entry['url']);
        }
        return array(
            'id'            => !empty($entry['id']) ? sanitize_text_field($entry['id']) : self::new_id('file'),
            'name'          => sanitize_text_field($entry['name'] ?? ''),
            'category'      => $category,
            'size_label'    => sanitize_text_field($entry['size_label'] ?? ''),
            'detail'        => sanitize_textarea_field($entry['detail'] ?? ''),
            'modified_at'   => !empty($entry['modified_at']) ? sanitize_text_field($entry['modified_at']) : self::now_iso(),
            'attachment_id' => $attachment_id,
            'url'           => $url,
            'created_by'    => isset($entry['created_by']) ? (int) $entry['created_by'] : (int) get_current_user_id(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_files() {
        $store = self::get_store();
        $items = isset($store['files']) && is_array($store['files']) ? $store['files'] : array();
        usort($items, function ($a, $b) {
            return strcmp((string) ($b['modified_at'] ?? ''), (string) ($a['modified_at'] ?? ''));
        });
        return array_values($items);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public static function save_file($payload) {
        $name = sanitize_text_field($payload['name'] ?? '');
        if ($name === '') {
            return new WP_Error('invalid_file', __('File name is required.', 'paxdesign-booking'), array('status' => 400));
        }
        $saved = self::normalize_file($payload);
        $store = self::get_store();
        $files = isset($store['files']) && is_array($store['files']) ? $store['files'] : array();
        array_unshift($files, $saved);
        $store['files'] = $files;
        self::save_store($store);
        self::append_activity('files', __('File added', 'paxdesign-booking'), $name, 'action');
        return $saved;
    }

    /**
     * @return true|WP_Error
     */
    public static function delete_file($id) {
        $store = self::get_store();
        $files = isset($store['files']) && is_array($store['files']) ? $store['files'] : array();
        $next  = array();
        $deleted = false;
        foreach ($files as $file) {
            if (!empty($file['id']) && $file['id'] === $id) {
                $deleted = true;
                continue;
            }
            $next[] = $file;
        }
        if (!$deleted) {
            return new WP_Error('not_found', __('File not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $store['files'] = $next;
        self::save_store($store);
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_activity($module = '') {
        $store = self::get_store();
        $items = isset($store['activity']) && is_array($store['activity']) ? $store['activity'] : array();
        if ($module !== '') {
            $module = sanitize_key($module);
            $items  = array_values(array_filter($items, function ($row) use ($module) {
                return isset($row['module']) && $row['module'] === $module;
            }));
        }
        return array_values($items);
    }

    /**
     * @param string $module
     * @param string $title
     * @param string $detail
     * @param string $severity
     * @param string $category
     * @return array<string, mixed>
     */
    public static function append_activity($module, $title, $detail = '', $severity = 'info', $category = '') {
        $store = self::get_store();
        $items = isset($store['activity']) && is_array($store['activity']) ? $store['activity'] : array();
        $entry = array(
            'id'        => self::new_id('act'),
            'timestamp' => self::now_iso(),
            'category'  => $category !== '' ? sanitize_text_field($category) : sanitize_text_field($module),
            'title'     => sanitize_text_field($title),
            'detail'    => sanitize_textarea_field($detail),
            'module'    => sanitize_key($module),
            'severity'  => in_array($severity, array('info', 'success', 'warning', 'action'), true) ? $severity : 'info',
            'user_id'   => (int) get_current_user_id(),
        );
        array_unshift($items, $entry);
        if (count($items) > self::MAX_ACTIVITY) {
            $items = array_slice($items, 0, self::MAX_ACTIVITY);
        }
        $store['activity'] = $items;
        self::save_store($store);
        return $entry;
    }

    public static function clear_activity() {
        $store = self::get_store();
        $store['activity'] = array();
        self::save_store($store);
        return true;
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>
     */
    public static function get_user_settings($user_id) {
        $settings = get_user_meta((int) $user_id, 'pax_platform_module_settings', true);
        return is_array($settings) ? $settings : array();
    }

    /**
     * @param int $user_id
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function save_user_settings($user_id, $settings) {
        if (!is_array($settings)) {
            $settings = array();
        }
        $clean = array();
        foreach ($settings as $key => $value) {
            $clean[sanitize_key($key)] = is_bool($value) ? $value : (bool) $value;
        }
        update_user_meta((int) $user_id, 'pax_platform_module_settings', $clean);
        return $clean;
    }

    /**
     * @return array<string, bool>
     */
    public static function module_permissions_for_user($user = null) {
        $user = $user ?: wp_get_current_user();
        $perms = PAXdesign_Live_Chat_Permissions::get_effective_permissions($user);
        $view_chats = !empty($perms[PAXdesign_Live_Chat_Permissions::PERM_VIEW_CHATS]);
        $reply      = !empty($perms[PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS]);
        $manage     = !empty($perms[PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS]);
        $settings   = !empty($perms[PAXdesign_Live_Chat_Permissions::PERM_MANAGE_SETTINGS]);
        $ratings    = !empty($perms[PAXdesign_Live_Chat_Permissions::PERM_VIEW_RATINGS]);
        $task_assign = !empty($perms[PAXdesign_Live_Chat_Permissions::PERM_ASSIGN_TEAM_TASKS]);
        $customer_profiles = !empty($perms[PAXdesign_Live_Chat_Permissions::PERM_MANAGE_CUSTOMER_PROFILES]);
        $team_permissions = !empty($perms[PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS]);

        return array(
            'view_dashboard'          => $view_chats || $manage,
            'view_calendar'           => $view_chats,
            'view_tasks'              => $view_chats,
            'view_files'              => $view_chats || $settings,
            'view_reports'            => $ratings || $manage,
            'view_activity_log'       => $view_chats,
            'view_employee_dashboard' => $view_chats && !$manage,
            'manage_tasks'            => $reply || $manage,
            'manage_calendar'         => $reply || $manage,
            'manage_files'            => $settings || $manage,
            'export_reports'          => $manage || $ratings,
            'assign_team_tasks'       => $manage || $task_assign,
            'manage_customer_profiles'=> $manage || $customer_profiles,
            'manage_team_permissions' => $manage || $team_permissions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function dashboard_payload() {
        $live = PAXdesign_Chat_Live::get_instance();
        $list = $live->get_live_list_data();
        if (is_wp_error($list)) {
            return array('error' => $list->get_error_message());
        }

        $sessions = isset($list['sessions']) && is_array($list['sessions']) ? $list['sessions'] : array();
        $customer = array_values(array_filter($sessions, function ($row) {
            $sid = isset($row['session_id']) ? (string) $row['session_id'] : '';
            return strpos($sid, 'team_') !== 0;
        }));

        $tasks = self::list_tasks();
        $open_tasks = 0;
        $overdue_tasks = 0;
        $now = time();
        foreach ($tasks as $task) {
            if (empty($task['is_completed'])) {
                $open_tasks++;
                if (!empty($task['due_date'])) {
                    $due = strtotime((string) $task['due_date']);
                    if ($due !== false && $due < $now) {
                        $overdue_tasks++;
                    }
                }
            }
        }

        $chart = array();
        $series = array();
        for ($i = 6; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', strtotime("-{$i} days"));
            $session_count = 0;
            $live_count_day = 0;
            foreach ($customer as $session) {
                $updated = isset($session['updated_at']) ? strtotime((string) $session['updated_at']) : 0;
                if ($updated > 0 && gmdate('Y-m-d', $updated) === $day) {
                    $session_count++;
                }
                $handler = isset($session['handler']) ? (string) $session['handler'] : '';
                $created = isset($session['created_at']) ? strtotime((string) $session['created_at']) : 0;
                if ($handler === 'live_request' && $created > 0 && gmdate('Y-m-d', $created) === $day) {
                    $live_count_day++;
                }
            }
            $message_count = class_exists('PAXdesign_Message_Store')
                ? PAXdesign_Message_Store::count_messages_on_day($day, 'customer')
                : 0;
            $team_count = class_exists('PAXdesign_Message_Store')
                ? PAXdesign_Message_Store::count_messages_on_day($day, 'team')
                : 0;
            $chart[] = array('label' => $day, 'value' => $session_count);
            $series[] = array(
                'label'         => $day,
                'sessions'      => $session_count,
                'messages'      => $message_count,
                'live_requests' => $live_count_day,
                'team_messages' => $team_count,
            );
        }

        $recent_sessions = array_sum(array_column(array_slice($series, -3), 'sessions'));
        $earlier_sessions = array_sum(array_column(array_slice($series, 0, 3), 'sessions'));
        $recent_messages = array_sum(array_column(array_slice($series, -3), 'messages'));
        $earlier_messages = array_sum(array_column(array_slice($series, 0, 3), 'messages'));
        $recent_live = array_sum(array_column(array_slice($series, -3), 'live_requests'));
        $earlier_live = array_sum(array_column(array_slice($series, 0, 3), 'live_requests'));

        $trends = array(
            'sessions_pct'      => self::percent_delta($recent_sessions, $earlier_sessions),
            'messages_pct'      => self::percent_delta($recent_messages, $earlier_messages),
            'live_requests_pct' => self::percent_delta($recent_live, $earlier_live),
        );

        $live_n = 0;
        $active_n = 0;
        $closed_n = 0;
        foreach ($customer as $session) {
            $handler = isset($session['handler']) ? (string) $session['handler'] : '';
            if ($handler === 'live_request') {
                $live_n++;
            } elseif ($handler === 'closed') {
                $closed_n++;
            } else {
                $active_n++;
            }
        }

        return array(
            'sessions_total'   => count($customer),
            'live_count'       => (int) ($list['live_count'] ?? 0),
            'open_tasks'       => $open_tasks,
            'overdue_tasks'    => $overdue_tasks,
            'upcoming_events'  => count(self::upcoming_events(5)),
            'activity_chart'   => $chart,
            'activity_series'  => $series,
            'trends'           => $trends,
            'category_totals'  => array(
                array('label' => 'live', 'value' => $live_n),
                array('label' => 'active', 'value' => $active_n),
                array('label' => 'closed', 'value' => $closed_n),
                array('label' => 'tasks', 'value' => $open_tasks),
            ),
            'server_time'      => current_time('mysql'),
        );
    }

    /**
     * @param int|float $recent
     * @param int|float $earlier
     * @return float
     */
    private static function percent_delta($recent, $earlier) {
        $recent = (float) $recent;
        $earlier = (float) $earlier;
        if ($earlier <= 0) {
            return $recent > 0 ? 100.0 : 0.0;
        }
        return round((($recent - $earlier) / $earlier) * 100, 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function upcoming_events($limit = 5) {
        $events = self::list_events();
        $now    = time();
        $upcoming = array();
        foreach ($events as $event) {
            $end = isset($event['end_date']) ? strtotime((string) $event['end_date']) : 0;
            if ($end >= $now) {
                $upcoming[] = $event;
            }
        }
        usort($upcoming, function ($a, $b) {
            return strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? ''));
        });
        return array_slice($upcoming, 0, max(1, (int) $limit));
    }

    /**
     * @return array<string, mixed>
     */
    public static function reports_payload() {
        $dashboard = self::dashboard_payload();
        $live = PAXdesign_Chat_Live::get_instance();
        $list = $live->get_live_list_data();
        $sessions = (!is_wp_error($list) && isset($list['sessions'])) ? $list['sessions'] : array();

        $live_n = 0;
        $active_n = 0;
        $closed_n = 0;
        foreach ($sessions as $session) {
            $sid = isset($session['session_id']) ? (string) $session['session_id'] : '';
            if (strpos($sid, 'team_') === 0) {
                continue;
            }
            $handler = isset($session['handler']) ? (string) $session['handler'] : '';
            if ($handler === 'live_request') {
                $live_n++;
            } elseif ($handler === 'closed') {
                $closed_n++;
            } else {
                $active_n++;
            }
        }

        return array(
            'overview'     => $dashboard,
            'session_mix'  => array(
                array('label' => 'live', 'value' => $live_n),
                array('label' => 'active', 'value' => $active_n),
                array('label' => 'closed', 'value' => $closed_n),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function employee_payload($user_id) {
        $live = PAXdesign_Chat_Live::get_instance();
        $list = $live->get_live_list_data();
        $sessions = (!is_wp_error($list) && isset($list['sessions'])) ? $list['sessions'] : array();

        $assigned = 0;
        $unread = 0;
        foreach ($sessions as $session) {
            $sid = isset($session['session_id']) ? (string) $session['session_id'] : '';
            if (strpos($sid, 'team_') === 0) {
                continue;
            }
            $handler = isset($session['handler']) ? (string) $session['handler'] : '';
            $needs = !empty($session['needs_reply']);
            if ($handler === 'admin' || $needs) {
                $assigned++;
            }
            if ($needs) {
                $unread++;
            }
        }

        $tasks = self::list_tasks();
        $open_tasks = 0;
        foreach ($tasks as $task) {
            if (empty($task['is_completed'])) {
                $open_tasks++;
            }
        }

        $user = get_userdata((int) $user_id);
        return array(
            'user_id'         => (int) $user_id,
            'name'            => $user ? $user->display_name : '',
            'role_label'      => PAXdesign_Live_Chat_Permissions::is_super_admin($user) ? 'super_admin' : (
                PAXdesign_Live_Chat_Permissions::can($user, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS) ? 'manager' : 'staff'
            ),
            'assigned_chats'  => $assigned,
            'unread_chats'    => $unread,
            'open_tasks'      => $open_tasks,
            'permissions'     => PAXdesign_Live_Chat_Permissions::get_effective_permissions($user),
            'module_permissions' => self::module_permissions_for_user($user),
        );
    }

    /**
     * @param string $query
     * @return array<int, array<string, mixed>>
     */
    public static function search($query) {
        $q = strtolower(trim($query));
        if ($q === '') {
            return array();
        }

        $results = array();
        $live = PAXdesign_Chat_Live::get_instance();
        $list = $live->get_live_list_data();
        if (!is_wp_error($list) && !empty($list['sessions'])) {
            foreach ($list['sessions'] as $session) {
                $hay = strtolower(
                    (isset($session['customer_name']) ? $session['customer_name'] : '') . ' ' .
                    (isset($session['last_preview']) ? $session['last_preview'] : '') . ' ' .
                    (isset($session['detected_service']) ? $session['detected_service'] : '')
                );
                if (strpos($hay, $q) !== false) {
                    $results[] = array(
                        'type'     => 'session',
                        'id'       => $session['session_id'] ?? '',
                        'title'    => $session['customer_name'] ?? '',
                        'subtitle' => $session['last_preview'] ?? '',
                        'module'   => (isset($session['session_id']) && strpos((string) $session['session_id'], 'team_') === 0) ? 'team' : 'chats',
                    );
                }
            }
        }

        foreach (self::list_tasks() as $task) {
            $hay = strtolower(($task['title'] ?? '') . ' ' . ($task['notes'] ?? ''));
            if (strpos($hay, $q) !== false) {
                $results[] = array(
                    'type' => 'task', 'id' => $task['id'], 'title' => $task['title'], 'subtitle' => $task['notes'] ?? '', 'module' => 'tasks',
                );
            }
        }
        foreach (self::list_events() as $event) {
            $hay = strtolower(($event['title'] ?? '') . ' ' . ($event['notes'] ?? ''));
            if (strpos($hay, $q) !== false) {
                $results[] = array(
                    'type' => 'event', 'id' => $event['id'], 'title' => $event['title'], 'subtitle' => $event['notes'] ?? '', 'module' => 'calendar',
                );
            }
        }
        foreach (self::list_files() as $file) {
            $hay = strtolower(($file['name'] ?? '') . ' ' . ($file['detail'] ?? ''));
            if (strpos($hay, $q) !== false) {
                $results[] = array(
                    'type' => 'document', 'id' => $file['id'], 'title' => $file['name'], 'subtitle' => $file['detail'] ?? '', 'module' => 'files',
                );
            }
        }
        foreach (self::list_customer_profiles() as $profile) {
            $hay = strtolower(
                ($profile['display_name'] ?? '') . ' ' .
                ($profile['email'] ?? '') . ' ' .
                ($profile['phone'] ?? '') . ' ' .
                ($profile['company'] ?? '')
            );
            if (strpos($hay, $q) !== false) {
                $results[] = array(
                    'type' => 'customer', 'id' => $profile['session_id'] ?? '', 'title' => $profile['display_name'] ?? '', 'subtitle' => $profile['email'] ?? '', 'module' => 'customers',
                );
            }
        }
        foreach (self::list_activity() as $entry) {
            $hay = strtolower(($entry['title'] ?? '') . ' ' . ($entry['detail'] ?? ''));
            if (strpos($hay, $q) !== false) {
                $results[] = array(
                    'type' => 'activity', 'id' => $entry['id'], 'title' => $entry['title'], 'subtitle' => $entry['detail'] ?? '', 'module' => 'activity_log',
                );
            }
        }

        return array_slice($results, 0, 40);
    }

    /**
     * @return array<string, mixed>
     */
    public static function notifications_summary() {
        $dashboard = self::dashboard_payload();
        return array(
            'unread_chats'  => (int) ($dashboard['sessions_total'] ?? 0),
            'live_requests' => (int) ($dashboard['live_count'] ?? 0),
            'open_tasks'    => (int) ($dashboard['open_tasks'] ?? 0),
            'server_time'   => current_time('mysql'),
        );
    }
}
