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

function wp_salt($scheme = 'auth')
{
    return 'test-salt-' . (string) $scheme;
}

function get_current_user_id()
{
    return Test_Cybercrime_Tickets::$current_user_id;
}

function is_user_logged_in()
{
    return Test_Cybercrime_Tickets::$current_user_id > 0;
}

function current_user_can($cap)
{
    return Test_Cybercrime_Tickets::$current_user_id === 1;
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

    /** @var array<int, string> */
    public static $message_meta_json = array();

    public function esc_like($text)
    {
        return addcslashes((string) $text, '_%\\');
    }

    public function get_col($query, $column = 0)
    {
        unset($query, $column);
        return self::$message_meta_json;
    }

    public function update($table, $data, $where, $formats = array(), $whereFormats = array())
    {
        $this->last_update = array(
            'table' => $table,
            'data' => $data,
            'where' => $where,
        );
        if (isset($data['attachments']) && is_array(self::$report_row)) {
            self::$report_row['attachments'] = $data['attachments'];
            Test_Cybercrime_Tickets::$test_row = self::$report_row;
        }
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

    public static $current_user_id = 1;

    public static function get_report_row($reference_id)
    {
        return self::$test_row;
    }

    public static function list_messages($reference_id, $limit = 200)
    {
        return self::$test_messages;
    }

    public static function messages_table()
    {
        return 'wp_paxdesign_cybercrime_messages';
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
Test_WPDB::$message_meta_json = array(
    wp_json_encode(Test_Cybercrime_Tickets::$test_messages[0]['meta']),
);

$pdfName = 'ccs-new-evidence.pdf';
file_put_contents($uploadSubdir . '/' . $pdfName, '%PDF-1.4 test');
$pdfAttachment = array(
    'field' => 'evidence_other',
    'name' => $pdfName,
    'path' => 'pax-cybercrime-intake/' . $pdfName,
    'type' => 'application/pdf',
    'size' => (string) filesize($uploadSubdir . '/' . $pdfName),
);

$appendOk = Test_Cybercrime_Tickets::append_report_attachments($reference, array($pdfAttachment));
ap_assert($appendOk, 'append_report_attachments merges a new PDF into the report row');
$appended = json_decode((string) ($GLOBALS['wpdb']->last_update['data']['attachments'] ?? ''), true);
ap_assert(is_array($appended) && count($appended) === 2, 'append keeps legacy file and adds new PDF');

Test_Cybercrime_Tickets::$test_row['attachments'] = wp_json_encode($appended);
Test_WPDB::$report_row = Test_Cybercrime_Tickets::$test_row;
Test_Cybercrime_Tickets::$test_messages[] = array(
    'meta' => array(
        'attachments' => array($newAttachment, $pdfAttachment),
    ),
);
Test_WPDB::$message_meta_json[] = wp_json_encode(end(Test_Cybercrime_Tickets::$test_messages)['meta']);

$stored = Test_Cybercrime_Tickets::collect_stored_attachments($reference, Test_Cybercrime_Tickets::$test_row);
ap_assert(count($stored) === 3, 'collect_stored_attachments keeps legacy, image, and PDF uploads');
ap_assert(
    PAXdesign_Cybercrime_Intake::resolve_attachment_path($stored[0]) !== ''
    || PAXdesign_Cybercrime_Intake::resolve_attachment_path($stored[1]) !== '',
    'stored records resolve to files on disk'
);

$syncOk = Test_Cybercrime_Tickets::sync_report_attachments_column($reference);
ap_assert($syncOk, 'sync_report_attachments_column succeeds');
$synced = json_decode((string) ($GLOBALS['wpdb']->last_update['data']['attachments'] ?? ''), true);
ap_assert(is_array($synced) && count($synced) === 3, 'sync persists all attachments without shrinking');

Test_Cybercrime_Tickets::$test_row['attachments'] = wp_json_encode($synced);
$afterSync = Test_Cybercrime_Tickets::collect_stored_attachments($reference, Test_Cybercrime_Tickets::$test_row);
ap_assert(count($afterSync) === 3, 'refresh after sync still exposes all attachments');

$enriched = PAXdesign_Cybercrime_Intake::enrich_attachments($reference, $afterSync);
ap_assert(count($enriched) === 3, 'enrich keeps all attachments');
$imageCount = 0;
$pdfCount = 0;
foreach ($enriched as $item) {
    ap_assert(!empty($item['url']), 'enriched attachment has secure download URL: ' . ($item['name'] ?? ''));
    if (!empty($item['is_image'])) {
        $imageCount++;
    }
    if (($item['type'] ?? '') === 'application/pdf') {
        $pdfCount++;
    }
    ap_assert(
        PAXdesign_Cybercrime_Intake::resolve_attachment_path($item) !== '',
        'enriched attachment resolves to readable file: ' . ($item['name'] ?? '')
    );
}
ap_assert($imageCount >= 2, 'image uploads remain marked as images');
ap_assert($pdfCount === 1, 'pdf upload remains available as document');

$heicName = 'ccs-mobile.heic';
file_put_contents($uploadSubdir . '/' . $heicName, 'fake-heic-bytes');
$heicAttachment = array(
    'field' => 'evidence_files',
    'name' => $heicName,
    'path' => 'pax-cybercrime-intake/' . $heicName,
    'type' => 'image/heic',
    'size' => (string) filesize($uploadSubdir . '/' . $heicName),
);
$heicEnriched = PAXdesign_Cybercrime_Intake::enrich_attachments($reference, array($heicAttachment));
ap_assert(count($heicEnriched) === 1, 'HEIC attachment enriches');
ap_assert(empty($heicEnriched[0]['is_image']), 'HEIC is not marked browser-previewable');
ap_assert(!empty($heicEnriched[0]['url']), 'HEIC still gets secure download URL');

$token = PAXdesign_Cybercrime_Intake::attachment_access_token($reference, array('name' => $legacyName), 1);
ap_assert($token !== '', 'attachment access token is generated');
ap_assert(
    PAXdesign_Cybercrime_Intake::verify_attachment_access_token($reference, $legacyName, $token, 1),
    'attachment access token verifies for same user'
);
ap_assert(
    !PAXdesign_Cybercrime_Intake::verify_attachment_access_token($reference, $legacyName, $token, 2),
    'attachment access token rejects other users'
);
ap_assert(
    PAXdesign_Cybercrime_Intake::attachment_access_token($reference, array('name' => $legacyName), 1) === $token,
    'attachment access token is stable across calls'
);

$foundLegacy = Test_Cybercrime_Tickets::find_stored_attachment($reference, $legacyName, Test_Cybercrime_Tickets::$test_row);
$foundNew = Test_Cybercrime_Tickets::find_stored_attachment($reference, $newName, Test_Cybercrime_Tickets::$test_row);
ap_assert(is_array($foundLegacy) && is_array($foundNew), 'find_stored_attachment resolves legacy and new files');

@unlink($uploadSubdir . '/' . $heicName);
@unlink($uploadSubdir . '/' . $legacyName);
@unlink($uploadSubdir . '/' . $newName);
@unlink($uploadSubdir . '/' . $pdfName);
@rmdir($uploadSubdir);
@rmdir($uploadRoot);

fwrite(STDOUT, "All attachment pipeline regression checks passed.\n");
