<?php
/**
 * Cybercrime evidence upload & secure attachment pipeline checks.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/paxdesign-booking';
$theme = dirname(__DIR__, 2) . '/navein';

function ccs_evidence_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

$intake = file_get_contents($root . '/includes/class-paxdesign-cybercrime-intake.php');
$tickets = file_get_contents($root . '/includes/class-paxdesign-cybercrime-tickets.php');
$admin = file_get_contents($root . '/includes/customer/class-paxdesign-customer-admin.php');
$adminJs = file_get_contents($root . '/assets/js/cybercrime-admin.js');
$portalJs = file_get_contents($theme . '/assets/js/apple-cybercrime-support.js');
$portalCss = file_get_contents($theme . '/assets/css/apple-cybercrime-support.css');
$portalTpl = file_get_contents($theme . '/template-parts/pages/cybercrime-support.php');

ccs_evidence_assert(strpos($intake, 'ATTACHMENT_ACTION') !== false, 'intake defines attachment action');
ccs_evidence_assert(strpos($intake, 'ajax_download_attachment') !== false, 'intake registers secure attachment download');
ccs_evidence_assert(strpos($intake, 'handle_request_uploads') !== false, 'intake exposes request upload handler');
ccs_evidence_assert(strpos($intake, 'enrich_attachments') !== false, 'intake enriches attachment URLs');
ccs_evidence_assert(strpos($intake, "'path'") !== false, 'intake stores attachment path');

ccs_evidence_assert(strpos($tickets, 'paxdesign_cybercrime_customer_resubmit') !== false, 'tickets registers customer resubmit ajax');
ccs_evidence_assert(strpos($tickets, 'add_customer_evidence') !== false, 'tickets implements add_customer_evidence');
ccs_evidence_assert(strpos($tickets, 'ajax_customer_resubmit') !== false, 'tickets implements ajax_customer_resubmit');

ccs_evidence_assert(strpos($admin, 'render_cybercrime_attachment_gallery') !== false, 'admin renders attachment gallery');
ccs_evidence_assert(strpos($admin, "is_image']) || PAXdesign_Cybercrime_Intake::is_image_mime") === false, 'admin gallery trusts enriched is_image only');
ccs_evidence_assert(strpos($admin, 'pax-cc-lightbox') !== false, 'admin includes lightbox markup');

ccs_evidence_assert(strpos($adminJs, 'renderAttachments') !== false, 'admin JS renders attachments');
ccs_evidence_assert(strpos($adminJs, 'openLightbox') !== false, 'admin JS opens lightbox');

ccs_evidence_assert(strpos($portalJs, 'updateEvidenceUi') !== false, 'portal JS toggles evidence upload UI');
ccs_evidence_assert(strpos($portalJs, 'renderResubmitPreview') !== false, 'portal JS previews selected files');
ccs_evidence_assert(strpos($portalJs, 'paxdesign_cybercrime_customer_resubmit') !== false, 'portal JS calls resubmit action');
ccs_evidence_assert(strpos($portalJs, 'timelineEvidenceInlineHtml') !== false, 'portal JS renders inline evidence request CTA');
ccs_evidence_assert(strpos($portalJs, 'evidence_request_active') !== false, 'portal JS respects server evidence_request_active flag');
ccs_evidence_assert(strpos($portalJs, 'evidenceSuccessUntil') !== false, 'portal JS keeps evidence success confirmation visible after submit');
ccs_evidence_assert(strpos($portalJs, 'timeline_evidence_signature') !== false, 'portal JS tracks timeline evidence signature for sync');
ccs_evidence_assert(strpos($portalJs, 'pax-ccs-portal__evidence-request-btn') !== false, 'portal JS renders prominent evidence upload button');
ccs_evidence_assert(strpos($portalCss, 'pax-ccs-portal__evidence-request-btn') !== false, 'portal CSS styles evidence upload button');
ccs_evidence_assert(strpos($portalCss, '-webkit-text-fill-color: #fff !important') !== false, 'portal CSS locks evidence button text contrast');
ccs_evidence_assert(strpos($tickets, 'timeline_evidence_signature') !== false, 'tickets builds timeline evidence signature');
ccs_evidence_assert(strpos($tickets, 'collect_report_attachments') !== false, 'tickets merges report and message attachments for admin');
ccs_evidence_assert(strpos($tickets, 'canonicalize_attachment_record') !== false, 'tickets canonicalizes legacy and new attachment records');
ccs_evidence_assert(strpos($tickets, 'append_report_attachments') !== false, 'tickets appends new customer uploads to report attachments');
ccs_evidence_assert(strpos($tickets, 'collect_message_attachments') !== false, 'tickets collects attachments from all message meta rows');
ccs_evidence_assert(strpos($tickets, 'sync_report_attachments_column') !== false, 'tickets syncs report attachments after customer evidence upload');
ccs_evidence_assert(strpos($tickets, 'attachments_signature') !== false, 'tickets builds attachments signature for sync');
ccs_evidence_assert(strpos($tickets, 'find_stored_attachment') !== false, 'tickets resolves attachments from report and timeline messages');
ccs_evidence_assert(strpos($tickets, 'has_active_evidence_request') !== false, 'tickets detects active staff evidence requests');
ccs_evidence_assert(strpos($tickets, 'evidence_files_required') !== false, 'tickets rejects resubmit without files when evidence is required');
ccs_evidence_assert(strpos($intake, 'is_evidence_resubmit_request') !== false, 'intake validates evidence resubmit uploads');
ccs_evidence_assert(strpos($portalJs, 'appendResubmitFiles') !== false, 'portal JS sends resubmit files with explicit filenames');
ccs_evidence_assert(strpos($portalJs, 'evidence_resubmit') !== false, 'portal JS flags evidence resubmit requests');
ccs_evidence_assert(strpos($portalJs, 'activeReplySubmit.hidden = waiting') !== false, 'portal JS hides text-only reply while evidence is required');
ccs_evidence_assert(strpos($tickets, 'is_active_evidence_request') !== false, 'tickets computes active evidence request state');
ccs_evidence_assert(strpos($tickets, 'evidence_request_active') !== false, 'tickets exposes evidence_request_active on timeline entries');
ccs_evidence_assert(strpos($intake, 'normalized_upload_files') !== false, 'intake normalizes mobile and desktop multipart uploads');
ccs_evidence_assert(strpos($intake, 'recover_attachment_record') !== false, 'intake recovers legacy attachment paths from stored URLs');
ccs_evidence_assert(strpos($portalJs, 'entryHasEvidenceRequest') !== false, 'portal JS detects evidence request from entry + meta');
ccs_evidence_assert(strpos($intake, 'can_browser_preview_image') !== false, 'intake distinguishes browser-previewable images');
ccs_evidence_assert(strpos($intake, 'attachment_access_token') !== false, 'intake uses stable attachment access tokens');
ccs_evidence_assert(strpos($intake, 'verify_attachment_access_token') !== false, 'intake verifies attachment access tokens');
ccs_evidence_assert(strpos($intake, 'readfile') !== false, 'intake streams attachments with readfile');
ccs_evidence_assert(strpos($intake, 'find_stored_attachment') !== false, 'intake download checks timeline message attachments');
ccs_evidence_assert(strpos($adminJs, 'pax-cc-request-evidence') !== false, 'admin JS handles request evidence checkbox');
ccs_evidence_assert(strpos($adminJs, 'request_evidence') !== false, 'admin JS sends request_evidence flag');
ccs_evidence_assert(strpos($adminJs, 'isDeletableEntry') !== false, 'admin JS derives delete eligibility client-side');
ccs_evidence_assert(strpos($adminJs, 'pax-cc-convo__delete') !== false, 'admin JS renders conversation delete buttons');
ccs_evidence_assert(strpos($adminJs, 'allow_delete') !== false || strpos($adminJs, 'isDeletableEntry') !== false, 'admin JS keeps delete visible after poll refresh');
ccs_evidence_assert(strpos($tickets, 'allow_delete') !== false, 'tickets exposes allow_delete on timeline entries');
ccs_evidence_assert(strpos($tickets, 'admin_timeline_kind') !== false, 'tickets classifies admin conversation rows');
ccs_evidence_assert(strpos($tickets, 'request_evidence') !== false, 'tickets stores request_evidence on staff reply');
ccs_evidence_assert(strpos($tickets, 'delete_staff_message') !== false, 'tickets implements delete_staff_message');
ccs_evidence_assert(strpos($tickets, 'append_report_sync_meta') !== false, 'tickets exposes sync revision metadata');
ccs_evidence_assert(strpos($tickets, 'sync_revision') !== false, 'tickets builds sync_revision token');
ccs_evidence_assert(strpos($adminJs, 'shouldApplyReport') !== false, 'admin JS reconciles poll vs mutation state');
ccs_evidence_assert(strpos($adminJs, 'attachmentsSignature') !== false, 'admin JS tracks attachment signature for sync');

exec('node --check ' . escapeshellarg($root . '/assets/js/cybercrime-admin.js') . ' 2>&1', $adminJsCheck, $adminJsCode);
ccs_evidence_assert($adminJsCode === 0, 'node --check cybercrime-admin.js');

exec('node --check ' . escapeshellarg($theme . '/assets/js/apple-cybercrime-support.js') . ' 2>&1', $portalJsCheck, $portalJsCode);
ccs_evidence_assert($portalJsCode === 0, 'node --check apple-cybercrime-support.js');

exec('php ' . escapeshellarg(dirname(__FILE__) . '/attachment-pipeline.php') . ' 2>&1', $pipelineOut, $pipelineCode);
if ($pipelineCode !== 0) {
    fwrite(STDERR, implode("\n", $pipelineOut) . "\n");
}
ccs_evidence_assert($pipelineCode === 0, 'attachment pipeline regression (legacy + new survive sync)');

fwrite(STDOUT, "All cybercrime evidence checks passed.\n");
