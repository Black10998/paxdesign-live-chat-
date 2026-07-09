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
        return array(
            'id'           => !empty($entry['id']) ? sanitize_text_field($entry['id']) : self::new_id('task'),
            'title'        => sanitize_text_field($entry['title'] ?? ''),
            'notes'        => sanitize_textarea_field($entry['notes'] ?? ''),
            'due_date'     => !empty($entry['due_date']) ? sanitize_text_field($entry['due_date']) : null,
            'is_completed' => !empty($entry['is_completed']),
            'priority'     => $priority,
            'created_at'   => !empty($entry['created_at']) ? sanitize_text_field($entry['created_at']) : self::now_iso(),
            'created_by'   => isset($entry['created_by']) ? (int) $entry['created_by'] : (int) get_current_user_id(),
            'updated_at'   => self::now_iso(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function list_tasks() {
        $store = self::get_store();
        $items = isset($store['tasks']) && is_array($store['tasks']) ? $store['tasks'] : array();
        usort($items, function ($a, $b) {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });
        return array_values($items);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public static function save_task($payload) {
        $title = sanitize_text_field($payload['title'] ?? '');
        if ($title === '') {
            return new WP_Error('invalid_task', __('Task title is required.', 'paxdesign-booking'), array('status' => 400));
        }

        $store = self::get_store();
        $tasks = isset($store['tasks']) && is_array($store['tasks']) ? $store['tasks'] : array();
        $id    = !empty($payload['id']) ? sanitize_text_field($payload['id']) : '';
        $found = false;

        foreach ($tasks as $index => $task) {
            if (!empty($task['id']) && $task['id'] === $id) {
                $merged = array_merge($task, $payload);
                $tasks[$index] = self::normalize_task($merged);
                $found = true;
                $saved = $tasks[$index];
                break;
            }
        }

        if (!$found) {
            $saved = self::normalize_task($payload);
            array_unshift($tasks, $saved);
        }

        $store['tasks'] = $tasks;
        self::save_store($store);
        self::append_activity('tasks', __('Task saved', 'paxdesign-booking'), $title, 'action');
        return $saved;
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
        for ($i = 6; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', strtotime("-{$i} days"));
            $count = 0;
            foreach ($customer as $session) {
                $updated = isset($session['updated_at']) ? strtotime((string) $session['updated_at']) : 0;
                if ($updated > 0 && gmdate('Y-m-d', $updated) === $day) {
                    $count++;
                }
            }
            $chart[] = array('label' => $day, 'value' => $count);
        }

        return array(
            'sessions_total'   => count($customer),
            'live_count'       => (int) ($list['live_count'] ?? 0),
            'open_tasks'       => $open_tasks,
            'overdue_tasks'    => $overdue_tasks,
            'upcoming_events'  => count(self::upcoming_events(5)),
            'activity_chart'   => $chart,
            'server_time'      => current_time('mysql'),
        );
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
