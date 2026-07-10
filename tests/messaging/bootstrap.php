<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct($code, $message, $data = array()) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function absint($value) { return abs((int) $value); }
function wp_json_encode($value) { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
function current_time($type, $gmt = false) { return gmdate('Y-m-d H:i:s'); }
function wp_html_excerpt($text, $length, $more = '') {
    $text = strip_tags((string) $text);
    return strlen($text) > $length ? substr($text, 0, $length) . $more : $text;
}
function wp_generate_uuid4() {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}
function get_option($key, $default = false) {
    return $key === 'paxdesign_message_store_schema' ? '2.0' : $default;
}
function update_option($key, $value, $autoload = null) { return true; }

class Test_WPDB {
    public $prefix = 'test_';
    public $insert_id = 0;
    public $failOutboxInsert = false;
    private $pdo;

    public function __construct() {
        $this->pdo = new PDO(
            getenv('PAX_TEST_DSN') ?: 'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=pax_chat_test;charset=utf8mb4',
            getenv('PAX_TEST_DB_USER') ?: 'root',
            getenv('PAX_TEST_DB_PASS') ?: '',
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
    }

    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        $index = 0;
        return preg_replace_callback('/%[dsf]/', function ($match) use (&$index, $args) {
            $value = $args[$index++] ?? null;
            if ($match[0] === '%d') return (string) ((int) $value);
            if ($match[0] === '%f') return (string) ((float) $value);
            return $this->pdo->quote((string) $value);
        }, $query);
    }

    public function query($query) {
        return $this->pdo->exec($query);
    }

    public function get_var($query) {
        $result = $this->pdo->query($query);
        return $result ? $result->fetchColumn() : null;
    }

    public function get_row($query) {
        $result = $this->pdo->query($query);
        $row = $result ? $result->fetch(PDO::FETCH_OBJ) : false;
        return $row ?: null;
    }

    public function get_results($query) {
        $result = $this->pdo->query($query);
        return $result ? $result->fetchAll(PDO::FETCH_OBJ) : array();
    }

    public function insert($table, $data, $formats = array()) {
        if ($this->failOutboxInsert && str_ends_with($table, 'paxdesign_chat_outbox')) {
            return false;
        }
        $columns = array_keys($data);
        $quoted = array_map(function ($value) {
            return is_int($value) ? (string) $value : $this->pdo->quote((string) $value);
        }, array_values($data));
        $result = $this->pdo->exec(
            "INSERT INTO $table (`" . implode('`,`', $columns) . '`) VALUES (' . implode(',', $quoted) . ')'
        );
        $this->insert_id = (int) $this->pdo->lastInsertId();
        return $result;
    }

    public function update($table, $data, $where, $formats = array(), $whereFormats = array()) {
        $sets = array();
        foreach ($data as $key => $value) {
            $sets[] = "`$key`=" . $this->pdo->quote((string) $value);
        }
        $conditions = array();
        foreach ($where as $key => $value) {
            $conditions[] = "`$key`=" . $this->pdo->quote((string) $value);
        }
        return $this->pdo->exec("UPDATE $table SET " . implode(',', $sets) . ' WHERE ' . implode(' AND ', $conditions));
    }

    public function delete($table, $where, $whereFormats = array()) {
        $conditions = array();
        foreach ($where as $key => $value) {
            $conditions[] = "`$key`=" . $this->pdo->quote((string) $value);
        }
        return $this->pdo->exec("DELETE FROM $table WHERE " . implode(' AND ', $conditions));
    }
}

$GLOBALS['wpdb'] = new Test_WPDB();
require_once dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-message-store.php';
