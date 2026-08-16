#!/usr/bin/env bash
# Production verification: disk bytes, HTTP headers, and streamed body match for PDFs/images.
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
  grep -q 'stream_attachment_file' "$INTAKE" || fail 'stream_attachment_file missing on server'
  grep -q 'maybe_serve_attachment_early' "$INTAKE" || fail 'maybe_serve_attachment_early missing on server'
  grep -q 'verify_pdf_file' "$INTAKE" || fail 'verify_pdf_file missing on server'
  grep -q "home_url('/')" "$INTAKE" || fail 'attachment URLs should use home_url front controller'
  ok 'live intake.php includes PDF-safe attachment streaming'
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
$pdf_name = "";
$pdf_path = "";
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
  if (strpos($item["url"], "admin-ajax.php") !== false) {
    echo "FAIL: download URL still uses admin-ajax.php for $name\n";
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
  $mime = PAXdesign_Cybercrime_Intake::normalize_attachment_mime((string)($item["type"] ?? ""), $path);
  if ($mime === "application/pdf") {
    if (!PAXdesign_Cybercrime_Intake::verify_pdf_file($path)) {
      echo "WARN: skipping invalid PDF on disk (not browser-openable): $name\n";
      continue;
    }
    $pdf_name = $name;
    $pdf_path = $path;
  }
  PAXdesign_Cybercrime_Intake::$attachment_stream_dry_run = true;
  $_GET = array(
    "action" => PAXdesign_Cybercrime_Intake::ATTACHMENT_ACTION,
    "reference" => $ref,
    "file" => $name,
    "_wpnonce" => $token,
  );
  ob_start();
  try {
    PAXdesign_Cybercrime_Intake::serve_attachment_download();
    echo "FAIL: serve_attachment_download did not stream $name\n";
  } catch (RuntimeException $e) {
    echo "FAIL: serve_attachment_download error for $name: " . $e->getMessage() . "\n";
  }
  $streamed = ob_get_clean();
  PAXdesign_Cybercrime_Intake::$attachment_stream_dry_run = false;
  $disk = file_get_contents($path);
  if ($streamed !== $disk) {
    echo "FAIL: streamed body mismatch for $name (stream=" . strlen($streamed) . " disk=" . strlen($disk) . " head=" . substr($streamed, 0, 16) . ")\n";
    continue;
  }
  echo "OK: $name readable size=$size mime=$mime streamed-bytes-match-disk token=valid\n";
}
if ($checked < 1) {
  echo "FAIL: no attachments verified\n";
}
if ($pdf_name === "") {
  echo "WARN: no valid PDF attachment found on latest report; HTTP PDF curl skipped\n";
} else {
  echo "PDF_CANDIDATE=$pdf_name\n";
  echo "PDF_PATH=$pdf_path\n";
  echo "PDF_REF=$ref\n";
}
' 2>&1 || fail "wp eval attachment verification failed"
fi

section "HTTP curl download (authenticated) for PDF when available"
if command -v wp >/dev/null 2>&1 && command -v curl >/dev/null 2>&1; then
  cd "$WP_PATH"
  EVAL_OUT=$(wp eval '
if (!class_exists("PAXdesign_Cybercrime_Intake")) { return; }
global $wpdb;
$table = $wpdb->prefix . "paxdesign_cybercrime_reports";
$row = $wpdb->get_row(
  "SELECT * FROM $table WHERE attachments IS NOT NULL AND attachments != \"\" AND attachments != \"[]\" ORDER BY updated_at DESC LIMIT 1",
  ARRAY_A
);
if (!$row) { return; }
$ref = (string)$row["reference_id"];
$admin_id = 1;
wp_set_current_user($admin_id);
foreach (PAXdesign_Cybercrime_Tickets::collect_report_attachments($ref, $row) as $item) {
  if (!is_array($item)) { continue; }
  $name = (string)($item["name"] ?? "");
  $path = PAXdesign_Cybercrime_Intake::resolve_attachment_path($item);
  if ($name === "" || $path === "" || !PAXdesign_Cybercrime_Intake::verify_pdf_file($path)) { continue; }
  $url = PAXdesign_Cybercrime_Intake::attachment_download_url($ref, array("name" => $name));
  $exp = time() + 3600;
  $scheme = is_ssl() ? "secure_auth" : "auth";
  $cookie = AUTH_COOKIE . "=" . rawurlencode(wp_generate_auth_cookie($admin_id, $exp, $scheme)) . "; "
    . LOGGED_IN_COOKIE . "=" . rawurlencode(wp_generate_auth_cookie($admin_id, $exp, "logged_in"));
  echo $url . "\n" . $cookie . "\n" . $path . "\n";
  break;
}
' 2>/dev/null || true)
  if [ -n "$EVAL_OUT" ]; then
    PDF_URL=$(echo "$EVAL_OUT" | sed -n '1p')
    PDF_COOKIE=$(echo "$EVAL_OUT" | sed -n '2p')
    PDF_DISK=$(echo "$EVAL_OUT" | sed -n '3p')
    if [ -n "$PDF_URL" ] && [ -n "$PDF_COOKIE" ] && [ -n "$PDF_DISK" ]; then
      TMP_HEADERS=$(mktemp)
      TMP_BODY=$(mktemp)
      HTTP_CODE=$(curl -sS -L -o "$TMP_BODY" -D "$TMP_HEADERS" -w '%{http_code}' \
        -H "Cookie: $PDF_COOKIE" \
        "$PDF_URL" || echo "000")
      CT=$(grep -i '^Content-Type:' "$TMP_HEADERS" | tail -1 | tr -d '\r')
      CL=$(grep -i '^Content-Length:' "$TMP_HEADERS" | tail -1 | tr -d '\r')
      CD=$(grep -i '^Content-Disposition:' "$TMP_HEADERS" | tail -1 | tr -d '\r')
      DISK_SIZE=$(stat -c%s "$PDF_DISK" 2>/dev/null || wc -c < "$PDF_DISK")
      BODY_SIZE=$(stat -c%s "$TMP_BODY" 2>/dev/null || wc -c < "$TMP_BODY")
      BODY_HEAD=$(head -c 5 "$TMP_BODY" 2>/dev/null || true)
      if [ "$HTTP_CODE" != "200" ]; then
        fail "PDF HTTP status $HTTP_CODE for $PDF_URL"
      else
        ok "PDF HTTP 200"
      fi
      echo "$CT" | grep -qi 'application/pdf' || fail "PDF Content-Type missing application/pdf ($CT)"
      echo "$CT" | grep -qi 'application/pdf' && ok "PDF Content-Type is application/pdf"
      if [ -n "$CL" ]; then
        CL_VAL=$(echo "$CL" | awk '{print $2}')
        [ "$CL_VAL" = "$DISK_SIZE" ] || fail "PDF Content-Length mismatch header=$CL_VAL disk=$DISK_SIZE"
        [ "$CL_VAL" = "$DISK_SIZE" ] && ok "PDF Content-Length matches disk ($DISK_SIZE)"
      fi
      echo "$CD" | grep -qi 'inline' || fail "PDF Content-Disposition not inline ($CD)"
      echo "$CD" | grep -qi 'inline' && ok "PDF Content-Disposition inline"
      [ "$BODY_SIZE" = "$DISK_SIZE" ] || fail "PDF body size mismatch http=$BODY_SIZE disk=$DISK_SIZE"
      [ "$BODY_SIZE" = "$DISK_SIZE" ] && ok "PDF body size matches disk"
      [ "$BODY_HEAD" = "%PDF-" ] || fail "PDF body missing %PDF- magic (got $BODY_HEAD)"
      [ "$BODY_HEAD" = "%PDF-" ] && ok "PDF body starts with %PDF-"
      if cmp -s "$TMP_BODY" "$PDF_DISK"; then
        ok "PDF HTTP body byte-identical to stored file"
      else
        fail "PDF HTTP body differs from stored file"
      fi
      rm -f "$TMP_HEADERS" "$TMP_BODY"
    else
      fail "could not resolve PDF URL for HTTP curl test"
    fi
  else
    echo "WARN: no valid PDF on latest report for HTTP curl test"
  fi
else
  fail "wp-cli or curl unavailable for HTTP PDF test"
fi

section "Done"
if [ "$FAIL" -ne 0 ]; then
  echo "Attachment download verification failed."
  exit 1
fi
echo "Attachment download verification passed."
