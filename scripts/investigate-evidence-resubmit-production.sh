#!/usr/bin/env bash
# Trace evidence RESUBMIT workflow on production (not original intake attachments).
# Focus: customer_evidence messages, staff request_evidence rows, orphan uploads.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"
UPLOADS="${WP_PATH%/}/wp-content/uploads/pax-cybercrime-intake"

section() { echo ""; echo "=== $* ==="; }

section "Evidence resubmit message timeline (all reports)"
if command -v wp >/dev/null 2>&1; then
  cd "$WP_PATH"
  wp eval '
global $wpdb;
$reports = $wpdb->prefix . "paxdesign_cybercrime_reports";
$msgs = $wpdb->prefix . "paxdesign_cybercrime_messages";
$refs = $wpdb->get_col("SELECT reference_id FROM $reports WHERE status IN (\"in_review\",\"waiting_for_customer\",\"submitted\") ORDER BY updated_at DESC LIMIT 5");
foreach ($refs as $ref) {
  echo "---- Report $ref ----\n";
  $rows = $wpdb->get_results($wpdb->prepare(
    "SELECT id, author_type, created_at, body, meta_json FROM $msgs WHERE reference_id = %s ORDER BY id ASC",
    $ref
  ), ARRAY_A);
  foreach ($rows as $row) {
    $meta = json_decode((string)($row["meta_json"] ?? ""), true);
    if (!is_array($meta)) { $meta = array(); }
    $event = $meta["event"] ?? "";
    $att = 0;
    if (!empty($meta["attachments"]) && is_array($meta["attachments"])) {
      $att = count($meta["attachments"]);
    }
    $req = !empty($meta["request_evidence"]) ? " request_evidence=1" : "";
    $ful = !empty($meta["evidence_fulfilled"]) ? " evidence_fulfilled=1" : "";
    echo "  msg#" . $row["id"] . " " . $row["author_type"] . " event=" . $event . " attachments=" . $att . $req . $ful . " at=" . $row["created_at"] . "\n";
    if ($att > 0) {
      foreach ($meta["attachments"] as $a) {
        if (!is_array($a)) continue;
        echo "    file: " . ($a["name"] ?? "?") . " path=" . ($a["path"] ?? "") . "\n";
      }
    }
  }
}
' 2>&1 || echo "wp eval failed"
fi

section "Staff evidence requests still active"
if command -v wp >/dev/null 2>&1; then
  cd "$WP_PATH"
  wp eval '
global $wpdb;
$msgs = $wpdb->prefix . "paxdesign_cybercrime_messages";
$rows = $wpdb->get_results(
  "SELECT id, reference_id, created_at, meta_json FROM $msgs WHERE author_type = \"staff\" AND meta_json LIKE \"%request_evidence%\" ORDER BY id DESC LIMIT 10",
  ARRAY_A
);
foreach ($rows as $row) {
  $meta = json_decode((string)($row["meta_json"] ?? ""), true);
  $active = (is_array($meta) && !empty($meta["request_evidence"]) && empty($meta["evidence_fulfilled"])) ? "ACTIVE" : "fulfilled/inactive";
  echo "staff msg#" . $row["id"] . " ref=" . $row["reference_id"] . " $active at=" . $row["created_at"] . "\n";
}
' 2>&1 || true
fi

section "Upload directory (all files, sorted by mtime)"
if [ -d "$UPLOADS" ]; then
  find "$UPLOADS" -type f ! -name 'index.php' ! -name '.htaccess' -printf '%TY-%Tm-%Td %TH:%TM:%TS %s %f\n' 2>/dev/null | sort -r
  echo "Total: $(find "$UPLOADS" -type f ! -name 'index.php' ! -name '.htaccess' 2>/dev/null | wc -l) files"
fi

section "Orphan files (on disk but not referenced in any report/message attachment JSON)"
if command -v wp >/dev/null 2>&1 && [ -d "$UPLOADS" ]; then
  cd "$WP_PATH"
  wp eval '
global $wpdb;
$reports = $wpdb->prefix . "paxdesign_cybercrime_reports";
$msgs = $wpdb->prefix . "paxdesign_cybercrime_messages";
$known = array();
$report_rows = $wpdb->get_results("SELECT attachments FROM $reports", ARRAY_A);
foreach ($report_rows as $row) {
  $atts = json_decode((string)($row["attachments"] ?? ""), true);
  if (!is_array($atts)) continue;
  foreach ($atts as $a) {
    if (is_array($a) && !empty($a["name"])) { $known[(string)$a["name"]] = true; }
  }
}
$msg_rows = $wpdb->get_results("SELECT meta_json FROM $msgs WHERE meta_json LIKE \"%attachments%\"", ARRAY_A);
foreach ($msg_rows as $row) {
  $meta = json_decode((string)($row["meta_json"] ?? ""), true);
  if (!is_array($meta) || empty($meta["attachments"])) continue;
  foreach ($meta["attachments"] as $a) {
    if (is_array($a) && !empty($a["name"])) { $known[(string)$a["name"]] = true; }
  }
}
$dir = wp_upload_dir()["basedir"] . "/pax-cybercrime-intake";
if (!is_dir($dir)) { echo "No upload dir\n"; return; }
foreach (scandir($dir) as $f) {
  if ($f === "." || $f === ".." || $f === "index.php" || $f === ".htaccess") continue;
  $path = $dir . "/" . $f;
  if (!is_file($path)) continue;
  if (empty($known[$f])) {
    echo "ORPHAN: $f size=" . filesize($path) . " mtime=" . gmdate("Y-m-d H:i:s", filemtime($path)) . " UTC\n";
  }
}
' 2>&1 || true
fi

section "PHP upload limits"
php -r 'echo "upload_max_filesize=" . ini_get("upload_max_filesize") . "\npost_max_size=" . ini_get("post_max_size") . "\nmax_file_uploads=" . ini_get("max_file_uploads") . "\n";' 2>/dev/null || true

section "Done"
