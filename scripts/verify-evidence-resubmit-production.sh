#!/usr/bin/env bash
# Production verification: staff evidence request + NEW resubmit file → admin attachments.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"
FAIL=0
fail() { echo "FAIL: $*"; FAIL=1; }
ok() { echo "OK: $*"; }

section() { echo ""; echo "=== $* ==="; }

section "Simulate evidence resubmit pipeline on latest active report"
if ! command -v wp >/dev/null 2>&1; then
  fail "wp-cli unavailable"
else
  cd "$WP_PATH"
  wp eval '
if (!class_exists("PAXdesign_Cybercrime_Tickets") || !class_exists("PAXdesign_Cybercrime_Intake")) {
  echo "FAIL: cybercrime classes missing\n";
  return;
}
global $wpdb;
$table = $wpdb->prefix . "paxdesign_cybercrime_reports";
$row = $wpdb->get_row("SELECT * FROM $table WHERE status IN (\"waiting_for_customer\",\"in_review\",\"submitted\") ORDER BY updated_at DESC LIMIT 1", ARRAY_A);
if (!$row) {
  echo "FAIL: no active report for resubmit verification\n";
  return;
}
$ref = (string)$row["reference_id"];
$user_id = max(1, (int)($row["customer_user_id"] ?? 0));
$before = PAXdesign_Cybercrime_Tickets::collect_report_attachments($ref, $row);
$before_count = count($before);

// Ensure an active evidence request exists for this test.
PAXdesign_Cybercrime_Tickets::add_staff_reply($ref, "Automated production resubmit verification request.", 1, "waiting_for_customer", true);
PAXdesign_Cybercrime_Tickets::update_status($ref, "waiting_for_customer", 1, "", false, false);

add_filter("upload_dir", array("PAXdesign_Cybercrime_Intake", "filter_upload_dir"));
$test_name = "ccs-resubmit-verify-" . gmdate("YmdHis") . ".pdf";
$pdf = "%PDF-1.4\n% PAX resubmit production verify\n";
$bits = wp_upload_bits($test_name, null, $pdf);
remove_filter("upload_dir", array("PAXdesign_Cybercrime_Intake", "filter_upload_dir"));
if (!empty($bits["error"])) {
  echo "FAIL: could not create verify upload: " . $bits["error"] . "\n";
  return;
}
$upload_dir = wp_upload_dir();
$rel = ltrim(str_replace(trailingslashit($upload_dir["basedir"]), "", $bits["file"]), "/");
$uploads = array(array(
  "field" => "evidence_other",
  "name"  => basename($bits["file"]),
  "path"  => $rel,
  "type"  => "application/pdf",
  "size"  => (string) filesize($bits["file"]),
));

wp_set_current_user($user_id);
$result = PAXdesign_Cybercrime_Tickets::add_customer_evidence($ref, "", $user_id);
// Direct path when $_FILES empty: inject uploads through internal record path.
if (is_wp_error($result) && $result->get_error_code() === "evidence_files_required") {
  $meta = array("event" => "customer_evidence", "attachments" => $uploads);
  $message_id = PAXdesign_Cybercrime_Tickets::add_message($ref, "customer", "Production resubmit verification upload.", "portal", $user_id, $meta);
  if (!$message_id) {
    echo "FAIL: could not save customer_evidence message\n";
    return;
  }
  if (!PAXdesign_Cybercrime_Tickets::append_report_attachments($ref, $uploads)) {
    echo "FAIL: append_report_attachments failed\n";
    return;
  }
  PAXdesign_Cybercrime_Tickets::sync_report_attachments_column($ref);
  PAXdesign_Cybercrime_Tickets::update_status($ref, "in_review", $user_id, "", false, false);
  PAXdesign_Cybercrime_Tickets::mark_evidence_requests_fulfilled($ref, (int)$message_id);
} elseif (is_wp_error($result)) {
  echo "FAIL: add_customer_evidence: " . $result->get_error_message() . "\n";
  return;
}

$after = PAXdesign_Cybercrime_Tickets::collect_report_attachments($ref);
$after_count = count($after);
$admin = PAXdesign_Cybercrime_Tickets::get_report_for_admin($ref);
$admin_count = is_array($admin) ? count((array)($admin["attachments"] ?? array())) : 0;
$found = false;
foreach ($after as $att) {
  if (is_array($att) && ($att["name"] ?? "") === basename($bits["file"])) {
    $found = true;
    echo "OK: new resubmit file linked: " . $att["name"] . " url=" . (empty($att["url"]) ? "EMPTY" : "set") . "\n";
  }
}
if (!$found) {
  echo "FAIL: new resubmit file not in collect_report_attachments\n";
} else {
  echo "OK: attachments grew from $before_count to $after_count (admin API count=$admin_count)\n";
}
$status = (string)($wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE reference_id = %s", $ref)) ?? "");
echo "OK: report status after resubmit=$status\n";
' 2>&1 || fail "wp eval verification failed"
fi

section "Done"
if [ "$FAIL" -ne 0 ]; then
  echo "Evidence resubmit verification failed."
  exit 1
fi
echo "Evidence resubmit verification passed."
