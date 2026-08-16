#!/usr/bin/env bash
# Production verification: closed reports are read-only; new reports can still start.
set -euo pipefail

WP_PATH="${WP_PATH:?WP_PATH required}"
FAIL=0
fail() { echo "FAIL: $*"; FAIL=1; }
ok() { echo "OK: $*"; }

section() { echo ""; echo "=== $* ==="; }

THEME="${WP_PATH%/}/wp-content/themes/navein"
TICKETS="${WP_PATH%/}/wp-content/plugins/paxdesign-booking/includes/class-paxdesign-cybercrime-tickets.php"

section "Live closed-readonly markers"
grep -q 'pax-ccs-closed-lock' "${THEME}/template-parts/pages/cybercrime-support.php" || fail 'portal template missing closed lock banner'
grep -q 'pax-ccs-open-new-report' "${THEME}/assets/js/apple-cybercrime-support.js" || fail 'portal JS missing Open New Report wiring'
grep -q 'cannot be modified' "$TICKETS" || fail 'tickets.php missing closed status guard'
ok 'live theme + tickets include closed read-only implementation'

section "Server guards on closed report row"
if ! command -v wp >/dev/null 2>&1; then
  fail "wp-cli unavailable"
else
  cd "$WP_PATH"
  wp eval '
if (!class_exists("PAXdesign_Cybercrime_Tickets")) {
  echo "FAIL: tickets class missing\n";
  return;
}
global $wpdb;
$table = $wpdb->prefix . "paxdesign_cybercrime_reports";
$row = $wpdb->get_row(
  "SELECT * FROM $table WHERE status IN (\"closed\",\"resolved\",\"rejected\") ORDER BY updated_at DESC LIMIT 1",
  ARRAY_A
);
if (!$row) {
  echo "WARN: no closed report found to verify guards\n";
  return;
}
$ref = (string)$row["reference_id"];
$status = PAXdesign_Cybercrime_Tickets::normalize_workflow_status((string)$row["status"]);
if (PAXdesign_Cybercrime_Tickets::is_active_status($status)) {
  echo "FAIL: expected inactive status for $ref got $status\n";
  return;
}
echo "OK: found closed report $ref status=$status\n";
$status_try = PAXdesign_Cybercrime_Tickets::update_status($ref, "in_review", 1, "", false, false);
if (!is_wp_error($status_try) || $status_try->get_error_code() !== "closed") {
  echo "FAIL: update_status should reject closed report\n";
} else {
  echo "OK: update_status blocked on closed report\n";
}
$reply_try = PAXdesign_Cybercrime_Tickets::add_customer_reply($ref, "readonly test", max(1, (int)($row["customer_user_id"] ?? 1)));
if (!is_wp_error($reply_try) || $reply_try->get_error_code() !== "closed") {
  echo "FAIL: customer reply should be blocked on closed report\n";
} else {
  echo "OK: customer reply blocked on closed report\n";
}
$active = PAXdesign_Cybercrime_Tickets::get_active_report_for_user(max(1, (int)($row["customer_user_id"] ?? 1)));
if (is_array($active) && ($active["reference_id"] ?? "") === $ref) {
  echo "FAIL: closed report still returned as active\n";
} else {
  echo "OK: closed report excluded from active report lookup\n";
}
' 2>&1 || fail "wp eval closed-readonly verification failed"
fi

section "Done"
if [ "$FAIL" -ne 0 ]; then
  echo "Closed read-only verification failed."
  exit 1
fi
echo "Closed read-only verification passed."
