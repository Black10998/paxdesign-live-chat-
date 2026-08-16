#!/usr/bin/env bash
# Read-only production audit: cybercrime evidence uploads, DB records, deployed PHP.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"
PLUGIN="${WP_PATH%/}/wp-content/plugins/paxdesign-booking"
THEME="${WP_PATH%/}/wp-content/themes/navein"
UPLOADS="${WP_PATH%/}/wp-content/uploads/pax-cybercrime-intake"
TICKETS="${PLUGIN}/includes/class-paxdesign-cybercrime-tickets.php"
INTAKE="${PLUGIN}/includes/class-paxdesign-cybercrime-intake.php"

section() { echo ""; echo "=== $* ==="; }

section "Server identity"
whoami
hostname
date -u

section "Deployed plugin markers (tickets.php)"
if [ -f "$TICKETS" ]; then
  grep -n "append_report_attachments\|collect_message_attachments\|mark_evidence_requests_fulfilled\|add_customer_evidence" "$TICKETS" | head -20 || true
  ls -la "$TICKETS"
else
  echo "MISSING: $TICKETS"
fi

section "Deployed plugin markers (intake.php)"
if [ -f "$INTAKE" ]; then
  grep -n "normalized_upload_files\|handle_request_uploads\|UPLOAD_SUBDIR" "$INTAKE" | head -15 || true
  ls -la "$INTAKE"
else
  echo "MISSING: $INTAKE"
fi

section "Deployed portal JS markers"
PORTAL_JS="${THEME}/assets/js/apple-cybercrime-support.js"
if [ -f "$PORTAL_JS" ]; then
  grep -n "evidenceSuccessUntil\|uploaded_count\|evidence_request_active" "$PORTAL_JS" | head -10 || true
  ls -la "$PORTAL_JS"
else
  echo "MISSING: $PORTAL_JS"
fi

section "Recent upload files (last 14 days)"
if [ -d "$UPLOADS" ]; then
  find "$UPLOADS" -type f ! -name 'index.php' ! -name '.htaccess' -mtime -14 -printf '%TY-%Tm-%Td %TH:%TM %s %p\n' 2>/dev/null | sort -r | head -40
  echo "Total files under intake dir: $(find "$UPLOADS" -type f ! -name 'index.php' ! -name '.htaccess' 2>/dev/null | wc -l)"
else
  echo "MISSING upload dir: $UPLOADS"
fi

section "WP-CLI: recent cybercrime reports attachment counts"
if command -v wp >/dev/null 2>&1; then
  cd "$WP_PATH"
  wp eval '
global $wpdb;
$table = $wpdb->prefix . "paxdesign_cybercrime_reports";
$msg_table = $wpdb->prefix . "paxdesign_cybercrime_messages";
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
  echo "MISSING table: $table\n";
  return;
}
$rows = $wpdb->get_results("SELECT reference_id, status, updated_at, attachments FROM $table ORDER BY updated_at DESC LIMIT 12", ARRAY_A);
foreach ($rows as $row) {
  $ref = $row["reference_id"];
  $stored = json_decode((string)($row["attachments"] ?? ""), true);
  $stored_count = is_array($stored) ? count($stored) : 0;
  $msg_count = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $msg_table WHERE reference_id = %s AND meta_json LIKE %s",
    $ref, "%\"attachments\"%"
  ));
  $recent_evidence = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $msg_table WHERE reference_id = %s AND meta_json LIKE %s AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)",
    $ref, "%\"customer_evidence\"%"
  ));
  echo $ref . " | status=" . ($row["status"] ?? "") . " | updated=" . ($row["updated_at"] ?? "") . " | report_attachments=" . $stored_count . " | msg_with_attachments=" . $msg_count . " | recent_evidence_msgs=" . $recent_evidence . "\n";
  if (is_array($stored) && !empty($stored)) {
    foreach (array_slice($stored, -5) as $att) {
      if (!is_array($att)) continue;
      $name = $att["name"] ?? "?";
      $path = $att["path"] ?? "";
      $exists = "no";
      if ($path !== "") {
        $full = wp_upload_dir()["basedir"] . "/" . ltrim($path, "/");
        $exists = is_readable($full) ? "yes" : "no";
      }
      echo "  - $name path=$path file_exists=$exists\n";
    }
  }
}
' 2>&1 || echo "wp eval failed"
else
  echo "wp-cli not available"
fi

section "WP-CLI: message meta rows containing recent upload filenames"
if command -v wp >/dev/null 2>&1; then
  cd "$WP_PATH"
  wp eval '
global $wpdb;
$msg_table = $wpdb->prefix . "paxdesign_cybercrime_messages";
$rows = $wpdb->get_results("SELECT id, reference_id, author_type, created_at, meta_json FROM $msg_table WHERE meta_json LIKE \"%attachments%\" ORDER BY id DESC LIMIT 20", ARRAY_A);
if (!is_array($rows)) { echo "No message attachment rows\n"; return; }
foreach ($rows as $row) {
  $meta = json_decode((string)($row["meta_json"] ?? ""), true);
  $names = array();
  if (is_array($meta) && !empty($meta["attachments"]) && is_array($meta["attachments"])) {
    foreach ($meta["attachments"] as $att) {
      if (is_array($att) && !empty($att["name"])) {
        $names[] = (string)$att["name"];
      }
    }
  }
  echo "msg#" . ($row["id"] ?? "") . " ref=" . ($row["reference_id"] ?? "") . " author=" . ($row["author_type"] ?? "") . " at=" . ($row["created_at"] ?? "") . " files=" . implode(",", $names) . "\n";
}
if (class_exists("PAXdesign_Cybercrime_Tickets")) {
  $ref = $wpdb->get_var("SELECT reference_id FROM {$wpdb->prefix}paxdesign_cybercrime_reports ORDER BY updated_at DESC LIMIT 1");
  if ($ref) {
    echo "Latest report collect_report_attachments for $ref:\n";
    $enriched = PAXdesign_Cybercrime_Tickets::collect_report_attachments($ref);
    echo "count=" . count($enriched) . "\n";
    foreach ($enriched as $att) {
      echo "  - " . ($att["name"] ?? "?") . " url=" . (empty($att["url"]) ? "EMPTY" : "set") . "\n";
    }
  }
}
' 2>&1 || echo "wp eval failed"
fi

section "WP-CLI: collect_report_attachments for latest waiting_for_customer report"
if command -v wp >/dev/null 2>&1; then
  cd "$WP_PATH"
  wp eval '
global $wpdb;
$table = $wpdb->prefix . "paxdesign_cybercrime_reports";
$row = $wpdb->get_row("SELECT * FROM $table WHERE status = \"waiting_for_customer\" ORDER BY updated_at DESC LIMIT 1", ARRAY_A);
if (!$row) {
  echo "No waiting_for_customer reports\n";
  $row = $wpdb->get_row("SELECT * FROM $table ORDER BY updated_at DESC LIMIT 1", ARRAY_A);
}
if (!$row) { echo "No reports\n"; return; }
$ref = $row["reference_id"];
echo "Report: $ref status=" . $row["status"] . "\n";
if (!class_exists("PAXdesign_Cybercrime_Tickets")) {
  echo "Tickets class missing\n";
  return;
}
$stored = PAXdesign_Cybercrime_Tickets::collect_stored_attachments($ref, $row);
$enriched = PAXdesign_Cybercrime_Tickets::collect_report_attachments($ref, $row);
echo "stored_count=" . count($stored) . " enriched_count=" . count($enriched) . "\n";
foreach ($enriched as $att) {
  echo "  enriched: " . ($att["name"] ?? "?") . " url=" . (empty($att["url"]) ? "EMPTY" : "set") . " is_image=" . (int)($att["is_image"] ?? 0) . "\n";
}
$formatted = PAXdesign_Cybercrime_Tickets::format_report_row($row, false, "customer", "staff");
echo "format_report_row attachments_count=" . count($formatted["attachments"] ?? []) . " signature=" . ($formatted["attachments_signature"] ?? "") . "\n";
' 2>&1 || echo "wp eval failed"
fi

section "OPcache / PHP"
php -v 2>/dev/null | head -1 || true
php -r 'echo "opcache_enabled=" . (function_exists("opcache_get_status") && @opcache_get_status(false) ? "yes" : "no") . "\n";' 2>/dev/null || true

section "Done"
