<?php
/**
 * Regression: legacy url-only + new path attachments survive merge/sync and resolve on disk.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

function ap_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

function sanitize_text_field($value)
{
    return trim(strip_tags((string) $value));
}

function sanitize_key($value)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function sanitize_file_name($value)
{
    $value = (string) $value;
    $value = preg_replace('/[^a-zA-Z0-9._\-]/', '-', $value);
    return trim($value, '.-');
}

function sanitize_mime_type($value)
{
    $value = strtolower(trim((string) $value));
    return strpos($value, '/') !== false ? $value : '';
}

function trailingslashit($string)
{
    return rtrim((string) $string, '/\\') . '/';
}

function wp_json_encode($value)
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function wp_check_filetype($filename)
{
    $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
    $map = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    );
    return array(
        'ext' => $ext,
        'type' => $map[$ext] ?? '',
    );
}

function wp_create_nonce($action)
{
    return hash('sha256', (string) $action);
}

function add_query_arg(array $args, $url)
{
    $query = http_build_query($args);
    return strpos((string) $url, '?') === false ? $url . '?' . $query : $url . '&' . $query;
}

function admin_url($path = '')
{
    return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
}

function current_time($type, $gmt = false)
{
    return gmdate('Y-m-d H:i:s');
}

class Test_WPDB
{
    public $prefix = 'wp_';
    public $last_update = null;

    /** @var array<string, mixed>|null */
    public static $report_row = null;

    public function prepare($query, ...$args)
    {
        return (string) $query;
    }

    public function get_row($query, $output = OBJECT)
    {
        return self::$report_row;
    }

    public function update($table, $data, $where, $formats = array(), $whereFormats = array())
    {
        $this->last_update = array(
            'table' => $table,
            'data' => $data,
            'where' => $where,
        );
        return 1;
    }
}

$GLOBALS['wpdb'] = new Test_WPDB();

$uploadRoot = sys_get_temp_dir() . '/pax-ccs-attachment-test-' . getmypid();
$uploadSubdir = $uploadRoot . '/pax-cybercrime-intake';
if (!is_dir($uploadSubdir) && !mkdir($uploadSubdir, 0777, true) && !is_dir($uploadSubdir)) {
    fwrite(STDERR, "Could not create temp upload directory\n");
    exit(1);
}

$legacyName = 'ccs-legacy-old.png';
$newName = 'ccs-new-evidence.png';
$legacyBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
file_put_contents($uploadSubdir . '/' . $legacyName, $legacyBytes);
file_put_contents($uploadSubdir . '/' . $newName, $legacyBytes);

function wp_upload_dir()
{
    global $uploadRoot;
    return array(
        'basedir' => $uploadRoot,
        'baseurl' => 'https://example.test/wp-content/uploads',
    );
}

require_once dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-cybercrime-intake.php';
require_once dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-cybercrime-tickets.php';

class Test_Cybercrime_Tickets extends PAXdesign_Cybercrime_Tickets
{
    /** @var array<string, mixed>|null */
    public static $test_row = null;

    /** @var array<int, array<string, mixed>> */
    public static $test_messages = array();

    public static function get_report_row($reference_id)
    {
        return self::$test_row;
    }

    public static function list_messages($reference_id, $limit = 200)
    {
        return self::$test_messages;
    }

    public static function table_name()
    {
        return 'wp_paxdesign_cybercrime_reports';
    }
}

$reference = 'CCS-ATT-TEST';
$legacyAttachment = array(
    'field' => 'evidence_files',
    'name' => $legacyName,
    'url' => 'https://example.test/wp-content/uploads/pax-cybercrime-intake/' . $legacyName,
    'type' => 'image/png',
    'size' => (string) strlen($legacyBytes),
);
$newAttachment = array(
    'field' => 'evidence_files',
    'name' => $newName,
    'path' => 'pax-cybercrime-intake/' . $newName,
    'type' => 'image/png',
    'size' => (string) strlen($legacyBytes),
);

Test_Cybercrime_Tickets::$test_row = array(
    'reference_id' => $reference,
    'attachments' => wp_json_encode(array($legacyAttachment)),
);
Test_WPDB::$report_row = Test_Cybercrime_Tickets::$test_row;
Test_Cybercrime_Tickets::$test_messages = array(
    array(
        'meta' => array(
            'attachments' => array($newAttachment),
        ),
    ),
);

$stored = Test_Cybercrime_Tickets::collect_stored_attachments($reference, Test_Cybercrime_Tickets::$test_row);
ap_assert(count($stored) === 2, 'collect_stored_attachments keeps legacy and new records');
ap_assert(
    PAXdesign_Cybercrime_Intake::resolve_attachment_path($stored[0]) !== ''
    || PAXdesign_Cybercrime_Intake::resolve_attachment_path($stored[1]) !== '',
    'stored records resolve to files on disk'
);

$syncOk = Test_Cybercrime_Tickets::sync_report_attachments_column($reference);
ap_assert($syncOk, 'sync_report_attachments_column succeeds');
$synced = json_decode((string) ($GLOBALS['wpdb']->last_update['data']['attachments'] ?? ''), true);
ap_assert(is_array($synced) && count($synced) === 2, 'sync persists both attachments without shrinking');

Test_Cybercrime_Tickets::$test_row['attachments'] = wp_json_encode($synced);
$afterSync = Test_Cybercrime_Tickets::collect_stored_attachments($reference, Test_Cybercrime_Tickets::$test_row);
ap_assert(count($afterSync) === 2, 'refresh after sync still exposes both attachments');

$enriched = PAXdesign_Cybercrime_Intake::enrich_attachments($reference, $afterSync);
ap_assert(count($enriched) === 2, 'enrich keeps both attachments');
foreach ($enriched as $item) {
    ap_assert(!empty($item['url']), 'enriched attachment has secure download URL: ' . ($item['name'] ?? ''));
    ap_assert(!empty($item['is_image']), 'enriched attachment is marked as image: ' . ($item['name'] ?? ''));
    ap_assert(
        PAXdesign_Cybercrime_Intake::resolve_attachment_path($item) !== '',
        'enriched attachment resolves to readable file: ' . ($item['name'] ?? '')
    );
}

$foundLegacy = Test_Cybercrime_Tickets::find_stored_attachment($reference, $legacyName, Test_Cybercrime_Tickets::$test_row);
$foundNew = Test_Cybercrime_Tickets::find_stored_attachment($reference, $newName, Test_Cybercrime_Tickets::$test_row);
ap_assert(is_array($foundLegacy) && is_array($foundNew), 'find_stored_attachment resolves legacy and new files');

@unlink($uploadSubdir . '/' . $legacyName);
@unlink($uploadSubdir . '/' . $newName);
@rmdir($uploadSubdir);
@rmdir($uploadRoot);

fwrite(STDOUT, "All attachment pipeline regression checks passed.\n");
