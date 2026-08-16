#!/usr/bin/env bash
# Production verification: enriched attachment URLs resolve, tokens verify, files readable.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"
FAIL=0
fail() { echo "FAIL: $*"; FAIL=1; }
ok() { echo "OK: $*"; }

section() { echo ""; echo "=== $* ==="; }

INTAKE="${WP_PATH%/}/wp-content/plugins/paxdesign-booking/includes/class-paxdesign-cybercrime-intake.php"

section "Live intake.php attachment handlers"
if [ ! -f "$INTAKE" ]; then
  fail "intake.php missing at $INTAKE"
else
  grep -q 'attachment_access_token' "$INTAKE" || fail 'attachment_access_token missing on server'
  grep -q 'can_browser_preview_image' "$INTAKE" || fail 'can_browser_preview_image missing on server'
  grep -q 'readfile' "$INTAKE" || fail 'readfile streaming missing on server'
  ok 'live intake.php includes attachment download fixes'
fi

section "Attachment enrich + token + disk checks on latest report with files"
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
$row = $wpdb->get_row(
  "SELECT * FROM $table WHERE attachments IS NOT NULL AND attachments != \"\" AND attachments != \"[]\" ORDER BY updated_at DESC LIMIT 1",
  ARRAY_A
);
if (!$row) {
  echo "FAIL: no report with attachments\n";
  return;
}
$ref = (string)$row["reference_id"];
$admin_id = 1;
wp_set_current_user($admin_id);
$attachments = PAXdesign_Cybercrime_Tickets::collect_report_attachments($ref, $row);
if (!is_array($attachments) || count($attachments) < 1) {
  echo "FAIL: collect_report_attachments empty for $ref\n";
  return;
}
$enriched = PAXdesign_Cybercrime_Intake::enrich_attachments($ref, $attachments);
echo "OK: report $ref has " . count($enriched) . " enriched attachment(s)\n";
$checked = 0;
foreach ($enriched as $item) {
  if (!is_array($item)) {
    continue;
  }
  $name = (string)($item["name"] ?? "");
  if ($name === "") {
    continue;
  }
  $checked++;
  if (empty($item["url"])) {
    echo "FAIL: missing download URL for $name\n";
    continue;
  }
  $token = PAXdesign_Cybercrime_Intake::attachment_access_token($ref, array("name" => $name), $admin_id);
  if ($token === "" || !PAXdesign_Cybercrime_Intake::verify_attachment_access_token($ref, $name, $token, $admin_id)) {
    echo "FAIL: access token invalid for $name\n";
    continue;
  }
  $path = PAXdesign_Cybercrime_Intake::resolve_attachment_path($item);
  if ($path === "" || !is_readable($path)) {
    echo "FAIL: file not readable on disk: $name\n";
    continue;
  }
  $size = filesize($path);
  if ($size === false || $size < 1) {
    echo "FAIL: file empty: $name\n";
    continue;
  }
  $mime = PAXdesign_Cybercrime_Intake::detect_attachment_mime($path);
  $preview = !empty($item["is_image"]);
  $can_preview = PAXdesign_Cybercrime_Intake::can_browser_preview_image($mime, $path);
  if ($preview !== $can_preview) {
    echo "FAIL: is_image mismatch for $name (is_image=" . ($preview ? "1" : "0") . " can_preview=" . ($can_preview ? "1" : "0") . " mime=$mime)\n";
    continue;
  }
  echo "OK: $name readable size=$size mime=$mime preview=" . ($preview ? "yes" : "no") . " token=valid\n";
}
if ($checked < 1) {
  echo "FAIL: no attachments verified\n";
}
' 2>&1 || fail "wp eval attachment verification failed"
fi

section "Done"
if [ "$FAIL" -ne 0 ]; then
  echo "Attachment download verification failed."
  exit 1
fi
echo "Attachment download verification passed."
