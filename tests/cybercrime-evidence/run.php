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
ccs_evidence_assert(strpos($admin, 'pax-cc-lightbox') !== false, 'admin includes lightbox markup');

ccs_evidence_assert(strpos($adminJs, 'renderAttachments') !== false, 'admin JS renders attachments');
ccs_evidence_assert(strpos($adminJs, 'openLightbox') !== false, 'admin JS opens lightbox');

ccs_evidence_assert(strpos($portalJs, 'updateEvidenceUi') !== false, 'portal JS toggles evidence upload UI');
ccs_evidence_assert(strpos($portalJs, 'renderResubmitPreview') !== false, 'portal JS previews selected files');
ccs_evidence_assert(strpos($portalJs, 'paxdesign_cybercrime_customer_resubmit') !== false, 'portal JS calls resubmit action');
ccs_evidence_assert(strpos($portalJs, 'timelineEvidenceInlineHtml') !== false, 'portal JS renders inline evidence request CTA');
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
ccs_evidence_assert(strpos($adminJs, 'compareSync') !== false, 'admin JS compares sync revisions');

exec('node --check ' . escapeshellarg($root . '/assets/js/cybercrime-admin.js') . ' 2>&1', $adminJsCheck, $adminJsCode);
ccs_evidence_assert($adminJsCode === 0, 'node --check cybercrime-admin.js');

exec('node --check ' . escapeshellarg($theme . '/assets/js/apple-cybercrime-support.js') . ' 2>&1', $portalJsCheck, $portalJsCode);
ccs_evidence_assert($portalJsCode === 0, 'node --check apple-cybercrime-support.js');

fwrite(STDOUT, "All cybercrime evidence checks passed.\n");
