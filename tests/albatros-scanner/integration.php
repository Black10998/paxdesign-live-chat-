<?php
/**
 * Runtime integration test for the Handy-Box phone inventory and the employee
 * data-refusal status, exercised against a real MySQL/MariaDB database.
 *
 * It loads the production Alb_Phones and Alb_Drivers classes unchanged and
 * runs them through a lightweight WordPress/$wpdb shim (same approach as
 * tests/messaging). This validates the real SQL and lifecycle logic:
 * create, assign, return, status changes, inventory counts, assignment
 * history, and that a refusal never deletes stored data.
 *
 * Requires a MySQL DSN (defaults to the local socket, override with
 * PAX_TEST_DSN / PAX_TEST_DB_USER / PAX_TEST_DB_PASS).
 */

error_reporting(E_ALL & ~E_DEPRECATED);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['__alb_fail'] = 0;
function alb_it_ok($cond, $message) {
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $GLOBALS['__alb_fail']++;
}

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct($code = '', $message = '', $data = array()) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string) $v))); }
function sanitize_textarea_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_email($v) { return trim((string) $v); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)); }
function absint($v) { return abs((int) $v); }
function wp_generate_password($len = 12, $s = true, $e = false) { return substr(bin2hex(random_bytes(16)), 0, $len); }

class Alb_Install {
    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'alb_' . $name;
    }
}
class Alb_Settings {
    public static function now_mysql() { return gmdate('Y-m-d H:i:s'); }
    public static function format_date($v) { return $v ? (string) $v : ''; }
    public static function format_datetime($v) { return $v ? (string) $v : ''; }
    public static function timezone() { return new DateTimeZone('UTC'); }
    public static function get() { return array('items_per_page' => 25); }
}
class Alb_Audit {
    public static $log = array();
    public static function record($data) { self::$log[] = $data; }
}
class Alb_Branches {
    public static function normalize($v) { $v = strtolower(trim((string) $v)); return in_array($v, array('wien', 'graz'), true) ? $v : ''; }
    public static function label($v) { $v = self::normalize($v); return $v === '' ? '—' : ucfirst($v); }
    public static function keys() { return array('wien', 'graz'); }
}
class Alb_I18n {
    public static function t($key, $r = array(), $l = '') { return $key; }
}
class Alb_Users {
    public static function photo_path($id) { return ''; }
}
class Alb_Photos {
    public static function admin_url($type, $id, $v = '') { return ''; }
}
class Alb_Scanners {
    // Minimal stand-in: Handy-Box only needs driver_id resolution when no
    // inline employee fields are supplied (the case exercised here).
    public static function person_id_from_request($data, $user_id) {
        return (int) ($data['driver_id'] ?? 0);
    }
    public static function table() { global $wpdb; return $wpdb->prefix . 'alb_scanners'; }
}

class Test_WPDB {
    public $prefix = 'albit_';
    public $insert_id = 0;
    private $pdo;
    public function __construct() {
        $this->pdo = new PDO(
            getenv('PAX_TEST_DSN') ?: 'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=pax_chat_test;charset=utf8mb4',
            getenv('PAX_TEST_DB_USER') ?: 'root',
            getenv('PAX_TEST_DB_PASS') ?: '',
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
    }
    public function get_charset_collate() { return ''; }
    public function esc_like($text) { return addcslashes((string) $text, '_%\\'); }
    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        $index = 0;
        return preg_replace_callback('/%[dsf]/', function ($m) use (&$index, $args) {
            $value = $args[$index++] ?? null;
            if ($m[0] === '%d') return (string) ((int) $value);
            if ($m[0] === '%f') return (string) ((float) $value);
            return $this->pdo->quote((string) $value);
        }, $query);
    }
    public function query($query) { return $this->pdo->exec($query); }
    public function get_var($query) { $r = $this->pdo->query($query); return $r ? $r->fetchColumn() : null; }
    public function get_row($query, $output = ARRAY_A) { $r = $this->pdo->query($query); $row = $r ? $r->fetch(PDO::FETCH_ASSOC) : false; return $row ?: null; }
    public function get_results($query, $output = ARRAY_A) { $r = $this->pdo->query($query); return $r ? $r->fetchAll(PDO::FETCH_ASSOC) : array(); }
    public function insert($table, $data, $formats = array()) {
        $cols = array_keys($data);
        $vals = array_map(function ($v) {
            return $v === null ? 'NULL' : (is_int($v) ? (string) $v : $this->pdo->quote((string) $v));
        }, array_values($data));
        $ok = $this->pdo->exec('INSERT INTO ' . $table . ' (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $vals) . ')');
        $this->insert_id = (int) $this->pdo->lastInsertId();
        return $ok !== false ? 1 : false;
    }
    public function update($table, $data, $where, $f = array(), $wf = array()) {
        $sets = array();
        foreach ($data as $k => $v) {
            $sets[] = '`' . $k . '`=' . ($v === null ? 'NULL' : (is_int($v) ? (string) $v : $this->pdo->quote((string) $v)));
        }
        $cond = array();
        foreach ($where as $k => $v) {
            $cond[] = '`' . $k . '`=' . (is_int($v) ? (string) $v : $this->pdo->quote((string) $v));
        }
        return $this->pdo->exec('UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE ' . implode(' AND ', $cond));
    }
}

$wpdb = new Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;

$drivers = $wpdb->prefix . 'alb_drivers';
$phones = $wpdb->prefix . 'alb_phones';
$assignments = $wpdb->prefix . 'alb_phone_assignments';
foreach (array($assignments, $phones, $drivers) as $tbl) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $tbl);
}
$wpdb->query("CREATE TABLE $drivers (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    first_name varchar(120) NOT NULL DEFAULT '',
    last_name varchar(120) NOT NULL DEFAULT '',
    phone varchar(60) NOT NULL DEFAULT '',
    email varchar(190) NOT NULL DEFAULT '',
    employee_code varchar(60) NOT NULL DEFAULT '',
    branch varchar(20) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'active',
    photo_path varchar(190) NOT NULL DEFAULT '',
    user_id bigint(20) unsigned DEFAULT NULL,
    phone_verified tinyint(1) NOT NULL DEFAULT 0,
    phone_verified_at datetime DEFAULT NULL,
    phone_data_refused tinyint(1) NOT NULL DEFAULT 0,
    photo_refused tinyint(1) NOT NULL DEFAULT 0,
    notes text NULL,
    created_at datetime NOT NULL,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    updated_at datetime NOT NULL,
    updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$wpdb->query("CREATE TABLE $phones (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    model varchar(160) NOT NULL DEFAULT '',
    serial_number varchar(120) NOT NULL DEFAULT '',
    imei varchar(40) NOT NULL DEFAULT '',
    branch varchar(20) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'available',
    current_driver_id bigint(20) unsigned DEFAULT NULL,
    current_assignment_id bigint(20) unsigned DEFAULT NULL,
    assigned_date date DEFAULT NULL,
    date_added date DEFAULT NULL,
    notes text NULL,
    created_at datetime NOT NULL,
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    updated_at datetime NOT NULL,
    updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$wpdb->query("CREATE TABLE $assignments (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    phone_id bigint(20) unsigned NOT NULL,
    driver_id bigint(20) unsigned DEFAULT NULL,
    previous_driver_id bigint(20) unsigned DEFAULT NULL,
    action varchar(20) NOT NULL DEFAULT 'assign',
    assigned_at datetime NOT NULL,
    recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
    snapshot_name varchar(190) NOT NULL DEFAULT '',
    snapshot_phone varchar(60) NOT NULL DEFAULT '',
    notes text NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

require_once dirname(__DIR__, 2) . '/albatros-scanner/includes/class-drivers.php';
require_once dirname(__DIR__, 2) . '/albatros-scanner/includes/class-phones.php';

// ---------------------------------------------------------------------------
// Employee data-refusal status
// ---------------------------------------------------------------------------
$normal = Alb_Drivers::create(array('first_name' => 'Max', 'last_name' => 'Muster', 'phone' => '+43111', 'branch' => 'wien'), 1);
alb_it_ok(!is_wp_error($normal), 'normal employee registration succeeds');
alb_it_ok($normal['phone_status'] === 'provided', 'employee with a phone is Provided');
alb_it_ok($normal['photo_status'] === 'missing', 'employee without photo is Missing');

$refused = Alb_Drivers::create(array('first_name' => 'Lea', 'last_name' => 'Ott', 'phone_data_refused' => 1, 'photo_refused' => 1), 1);
alb_it_ok($refused['phone_status'] === 'refused' && $refused['photo_status'] === 'refused', 'both refusals can be set independently at creation');
alb_it_ok((int) $refused['phone_data_refused'] === 1 && (int) $refused['photo_refused'] === 1, 'refusal flags persist to the database');

$onlyPhoto = Alb_Drivers::create(array('first_name' => 'Tom', 'last_name' => 'Kai', 'phone' => '+43222', 'photo_refused' => 1), 1);
alb_it_ok($onlyPhoto['phone_status'] === 'provided' && $onlyPhoto['photo_status'] === 'refused', 'refusals are independent (phone provided, photo refused)');

// Set a refusal on an employee who already has a phone: data must remain.
$edited = Alb_Drivers::update($normal['id'], array('phone_data_refused' => 1), 1);
alb_it_ok($edited['phone_status'] === 'refused', 'a phone-data refusal can be added later');
$reload = Alb_Drivers::get($normal['id']);
alb_it_ok($reload['phone'] === '+43111', 'existing phone data is NOT deleted when a refusal is set');

// Remove the refusal again and confirm the value is still usable.
$cleared = Alb_Drivers::update($normal['id'], array('phone_data_refused' => 0), 1);
alb_it_ok($cleared['phone_status'] === 'provided' && $cleared['phone'] === '+43111', 'refusal can be removed and the original data is intact');

// ---------------------------------------------------------------------------
// Handy-Box inventory lifecycle
// ---------------------------------------------------------------------------
$c0 = Alb_Phones::counts();
alb_it_ok((int) $c0['total'] === 0 && (int) $c0['available'] === 0, 'empty inventory reports zero phones');
alb_it_ok(count(Alb_Phones::box_items()) === 0, 'empty Handy-Box has no phones');

$p1 = Alb_Phones::create(array('model' => 'iPhone 13', 'serial_number' => 'SN-001', 'imei' => '111111111111111', 'branch' => 'wien'), 1);
$p2 = Alb_Phones::create(array('model' => 'Galaxy S22', 'serial_number' => 'SN-002', 'imei' => '222222222222222'), 1);
$p3 = Alb_Phones::create(array('model' => 'Pixel 7', 'serial_number' => 'SN-003'), 1);
alb_it_ok(!is_wp_error($p1) && $p1['status'] === 'available', 'a new phone is added as Available');
alb_it_ok(count(Alb_Phones::box_items()) === 3, 'multiple available phones all appear in the box');

$dupSerial = Alb_Phones::create(array('model' => 'X', 'serial_number' => 'SN-001'), 1);
alb_it_ok(is_wp_error($dupSerial) && $dupSerial->get_error_code() === 'alb_conflict', 'duplicate serial number is rejected');
$dupImei = Alb_Phones::create(array('model' => 'X', 'imei' => '111111111111111'), 1);
alb_it_ok(is_wp_error($dupImei) && $dupImei->get_error_code() === 'alb_conflict', 'duplicate IMEI is rejected');
$noModel = Alb_Phones::create(array('serial_number' => 'SN-009'), 1);
alb_it_ok(is_wp_error($noModel), 'a phone without a model is rejected');

// Assign P1 to the employee
$assigned = Alb_Phones::assign($p1['id'], $normal['id'], '', 'handover', 1);
alb_it_ok(!is_wp_error($assigned) && $assigned['status'] === 'assigned', 'assigning a phone sets status Assigned');
alb_it_ok((int) $assigned['current_driver_id'] === (int) $normal['id'], 'assigned phone is linked to the employee');
$box = Alb_Phones::box_items();
alb_it_ok(!in_array($p1['id'], array_map(function ($x) { return $x['id']; }, $box), true), 'assigned phone leaves the Handy-Box');
$cA = Alb_Phones::counts();
alb_it_ok((int) $cA['available'] === 2 && (int) $cA['assigned'] === 1, 'inventory counts update after assignment');
$driverPhones = Alb_Phones::assigned_to_driver($normal['id']);
alb_it_ok(count($driverPhones) === 1 && (int) $driverPhones[0]['id'] === (int) $p1['id'], 'employee page can list the phone to return');
$hist = Alb_Phones::history($p1['id']);
alb_it_ok(count($hist) === 1 && $hist[0]['action'] === 'assign', 'assignment is recorded in history');

// Return P1
$returned = Alb_Phones::return_phone($p1['id'], 'back', 1);
alb_it_ok($returned['status'] === 'available' && $returned['current_driver_id'] === null, 'returning a phone sets it Available and unlinks the employee');
$box2 = Alb_Phones::box_items();
alb_it_ok(in_array($p1['id'], array_map(function ($x) { return $x['id']; }, $box2), true), 'returned phone is back in the Handy-Box');
$hist2 = Alb_Phones::history($p1['id']);
alb_it_ok(count($hist2) === 2 && $hist2[0]['action'] === 'return', 'return is appended to history (assignment history preserved)');
$p1reload = Alb_Phones::get($p1['id']);
alb_it_ok($p1reload['serial_number'] === 'SN-001' && $p1reload['imei'] === '111111111111111', 'device data survives an assign/return cycle');

// Damaged / lost / retired
$dmg = Alb_Phones::change_status($p2['id'], 'damaged', 'cracked', 1);
alb_it_ok($dmg['status'] === 'damaged', 'a phone can be marked Damaged');
$assignP3 = Alb_Phones::assign($p3['id'], $onlyPhoto['id'], '', '', 1);
$lost = Alb_Phones::change_status($p3['id'], 'lost', '', 1);
alb_it_ok($lost['status'] === 'lost' && $lost['current_driver_id'] === null, 'marking an assigned phone Lost clears the active assignment');
$retired = Alb_Phones::change_status($p1['id'], 'retired', '', 1);
alb_it_ok($retired['status'] === 'retired', 'a phone can be Retired');
$assignReq = Alb_Phones::change_status($p2['id'], 'assigned', '', 1);
alb_it_ok(is_wp_error($assignReq), 'status endpoint refuses to fake an assignment');

$cFinal = Alb_Phones::counts();
alb_it_ok((int) $cFinal['total'] === 3, 'total count includes every non-deleted phone');
alb_it_ok((int) $cFinal['damaged'] === 1 && (int) $cFinal['lost'] === 1 && (int) $cFinal['retired'] === 1, 'damaged/lost/retired are counted separately');
alb_it_ok((int) $cFinal['available'] === 0 && count(Alb_Phones::box_items()) === 0, 'no available phones means an empty box');

foreach (array($assignments, $phones, $drivers) as $tbl) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $tbl);
}

if ($GLOBALS['__alb_fail'] > 0) {
    fwrite(STDERR, $GLOBALS['__alb_fail'] . " integration assertion(s) failed\n");
    exit(1);
}
echo "All Handy-Box + refusal integration checks passed.\n";
