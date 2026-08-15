<?php
/**
 * English copy for the Cybercrime Support portal (merged into cybercrime-support-data.php).
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'operator' => array(
		'service' => 'PAXDesign · Cybercrime Support',
		'disclaimer' => 'Professional reporting service operated by PAXDesign. This is not a government agency or official portal.',
	),
	'platforms_coverage' => array(
		'label' => 'Platforms & online services — cybercrime coverage',
		'note' => 'For illustration only. No partnership, endorsement, or affiliation with the listed providers.',
	),
	'portal' => array(
		'skip' => 'Skip to main content',
		'eyebrow' => 'Digital reporting portal',
		'title' => 'Cybercrime Support Portal',
		'subtitle' => 'A secure, structured channel for cybercrime reports — handled confidentially and followed up professionally.',
		'status' => array(
			array( 'label' => 'Confidential' ),
			array( 'label' => 'Secure transfer' ),
			array( 'label' => 'Structured process' ),
		),
	),
	'welcome' => array(
		'heading' => 'Welcome to the reporting portal',
		'intro' => 'Use this portal to submit a structured report about a cyber incident. The experience follows modern digital service standards: clarity, security, and a clear path from start to submission.',
		'blocks' => array(
			array(
				'title' => 'Purpose of the portal',
				'body' => 'Cybercrime incidents — fraud, account takeovers, phishing, malware, identity theft — are captured in a unified format so the PAXDesign specialist team can assess and follow up.',
			),
			array(
				'title' => 'How reports are handled',
				'body' => 'After submission, your report receives a unique reference number. The team reviews information and evidence confidentially; additional verification steps may be requested. Priority depends on urgency and incident type.',
			),
			array(
				'title' => 'What happens after submission',
				'body' => 'You will receive immediate confirmation with your reference number. The team may contact you via the email or phone provided. For urgent questions, use the global live chat on the site — the assistant recognizes the context of this page.',
			),
			array(
				'title' => 'Why accuracy matters',
				'body' => 'Accurate information speeds up assessment and reduces delays. False or unverifiable reports may be rejected. Please report only facts to the best of your knowledge.',
			),
		),
		'trust_heading' => 'Service standards',
		'trust' => array(
			array(
				'title' => 'Confidentiality',
				'text' => 'Your data is handled in a professional, restricted environment.',
			),
			array(
				'title' => 'Security',
				'text' => 'Encrypted transfer and secure file upload for evidence.',
			),
			array(
				'title' => 'Verification',
				'text' => 'Identity documents may be requested before processing continues.',
			),
			array(
				'title' => 'Structured process',
				'text' => 'Four stages: identity, incident, evidence, declaration.',
			),
		),
		'time' => 'Estimated time: 10–15 minutes',
		'start' => 'Start report',
		'view_report' => 'View your report',
	),
	'ticket_history' => array(
		'heading' => 'Ticket history',
		'intro' => 'Closed reports are read-only. For new help, start a new report.',
		'empty' => 'No previous reports.',
		'closed_badge' => 'Closed',
		'active_badge' => 'Active',
		'new_report_hint' => 'For new assistance, use “Start report” above.',
		'unread' => 'New',
	),
	'active_report' => array(
		'title' => 'Your current report',
		'intro' => 'Track the official status of your report and communication with the PAXDesign Support team.',
		'official_heading' => 'Official communication',
		'official_note' => 'This is the official record of your report — between you and the support team only. It does not include AI assistant chat.',
		'status' => 'Status',
		'reference' => 'Reference number',
		'category' => 'Category',
		'submitted' => 'Submitted',
		'timeline' => 'Updates',
		'timeline_status' => 'Status',
		'timeline_sender' => 'Sender',
		'timeline_subject' => 'Subject',
		'timeline_when' => 'Date',
		'attachments' => 'Attachments',
		'reply_label' => 'Add a message',
		'reply_placeholder' => 'Write your message to the team…',
		'reply_submit' => 'Send',
		'closed_note' => 'This report is closed. New messages cannot be added.',
		'chat' => 'AI assistant',
		'ai_heading' => 'AI assistant — questions & guidance',
		'ai_note' => 'AI chat is another interface for this same CCS case. Facts you share there are saved on this page.',
		'refresh' => 'Refresh',
		'closed_title' => 'Closed report (read-only)',
		'read_only' => 'This report is closed. You can view the full history only.',
		'back_history' => 'Back to ticket history',
		'original_heading' => 'Original request',
		'checks_heading' => 'Document checks (preliminary)',
		'checks_disclaimer' => 'Automated quality checks only — not legal verification. Uncertain items are reviewed by the team.',
		'next_heading' => 'What is needed now',
		'continue_form' => 'Continue on this page',
		'resubmit_heading' => 'Correct or add files on this same case',
		'resubmit_hint' => 'Your reference number does not change. Upload only the files that are required.',
		'resubmit_identity' => 'Replace identity document',
		'resubmit_evidence' => 'Additional evidence',
		'resubmit_submit' => 'Submit correction',
		'check_accepted' => 'Accepted for review',
		'check_rejected' => 'Rejected — correction needed',
		'check_review' => 'Pending team review',
		'rejection_heading' => 'Rejection reason',
		'rejected_next_heading' => 'Next action',
		'rejected_next' => 'No further action is required on this reference. You can start a new report if you have a new incident.',
	),
	'login_gate' => array(
		'title' => 'Sign in required',
		'message' => 'Cybercrime Support is a secure service for submitting and tracking cybercrime reports. Please sign in to your account to continue with your report.',
		'button' => 'Sign in to continue',
		'back' => 'Back to overview',
	),
	'steps' => array(
		array( 'label' => 'Identity' ),
		array( 'label' => 'Incident' ),
		array( 'label' => 'Evidence' ),
		array( 'label' => 'Review' ),
	),
	'sections' => array(
		'identity' => array(
			'title' => 'Identity & verification',
			'intro' => 'Enter your legal details as shown on official documents. We may request ID verification before proceeding.',
		),
		'incident' => array(
			'title' => 'Incident information',
			'intro' => 'Describe what happened accurately. The clearer the details, the faster the assessment.',
		),
		'evidence' => array(
			'title' => 'Upload evidence',
			'intro' => 'Attach screenshots, documents, or supporting files. Maximum 25 MB per file.',
		),
		'review' => array(
			'title' => 'Declaration & review',
			'intro' => 'Review your report before submitting. By continuing, you confirm accuracy and accept the processing terms.',
		),
	),
	'fields' => array(
		'full_name' => array(
			'label' => 'Full legal name',
			'placeholder' => 'As on passport or ID',
		),
		'email' => array(
			'label' => 'Email address',
			'placeholder' => 'name@example.com',
		),
		'phone' => array(
			'label' => 'Phone number',
			'placeholder' => '660 1234567',
		),
		'phone_code' => array(
			'label' => 'Country code',
		),
		'country' => array(
			'label' => 'Country',
			'placeholder' => 'Search for your country…',
		),
		'required' => array(
			'label' => 'Required',
		),
		'identity_document' => array(
			'label' => 'Identity document',
			'hint' => 'PDF or JPG — passport, ID card, or driver\'s license. Required to proceed.',
		),
		'identity_accuracy' => array(
			'label' => 'I confirm that the identity information I provided is accurate and correct.',
		),
		'category' => array(
			'label' => 'Incident category',
		),
		'incident_date' => array(
			'label' => 'Incident date',
		),
		'incident_time' => array(
			'label' => 'Time (approximate)',
		),
		'platforms' => array(
			'label' => 'Affected platforms or services',
			'placeholder' => 'e.g. Gmail, Instagram, Binance, …',
		),
		'description' => array(
			'label' => 'Detailed incident description',
			'placeholder' => 'Describe what happened, steps you took, and any other parties involved…',
		),
		'financial_loss' => array(
			'label' => 'Estimated financial loss (if any)',
		),
		'financial_currency' => array(
			'label' => 'Currency',
		),
		'urgency' => array(
			'label' => 'Urgency level',
		),
		'evidence_screenshots' => array(
			'label' => 'Screenshots',
		),
		'evidence_documents' => array(
			'label' => 'Documents & files',
		),
		'evidence_chats' => array(
			'label' => 'Chat exports',
		),
		'evidence_other' => array(
			'label' => 'Additional evidence',
		),
		'evidence_required' => array(
			'label' => 'Please attach at least one piece of evidence before continuing.',
		),
	),
	'guided' => array(
		'ask' => 'Current question',
		'missing' => 'Still needed in this step',
		'identity_q' => 'Who are you? Enter your legal details as on the official document, then upload a clear photo of the full document.',
		'incident_q' => 'What happened? Choose the type, then add when, which platforms, and a description in your own words.',
		'evidence_q' => 'What proves it? Upload screenshots, messages, or statements that match this incident type.',
		'review_q' => 'Review the summary, then confirm accuracy. After submit you receive a fixed reference number for this case.',
		'category_hint' => 'Choose what best describes the incident. You can add detail in the description.',
		'id_why' => 'We need a complete, readable identity document to match your name to the case. Automated checks are quality-only — the team makes the final decision.',
		'continue_blocked' => 'Complete the required answers in this step before continuing.',
	),
	'evidence_coach' => array(
		'account_takeover' => 'Most useful: email/phone change notices, recovery messages, unknown session screenshots.',
		'phishing_fraud' => 'Most useful: the message or link, sender address, and any payment you made.',
		'identity_theft' => 'Most useful: accounts opened in your name, credit alerts, platform notices.',
		'malware_ransomware' => 'Most useful: ransom note, encrypted file extensions, antivirus alert. Do not upload runnable malware.',
		'social_media_recovery' => 'Most useful: username, profile screenshots, platform emails, last known activity.',
		'financial_fraud' => 'Most useful: bank notice or statement, transaction ID, scammer chat. Hide full card numbers.',
		'data_breach' => 'Most useful: breach notice, what data leaked, and where it appeared.',
		'other' => 'Upload anything that shows the sequence: screenshots, messages, or documents.',
	),
	'platform_chips' => array(
		'other' => 'Other',
	),
	'categories' => array(
		'account_takeover'      => 'Account takeover',
		'phishing_fraud'        => 'Phishing / fraud',
		'identity_theft'        => 'Identity theft',
		'malware_ransomware'    => 'Malware / ransomware',
		'social_media_recovery' => 'Social media recovery',
		'financial_fraud'       => 'Financial fraud',
		'data_breach'           => 'Data breach',
		'other'                 => 'Other',
	),
	'urgency' => array(
		'low'      => 'Low',
		'medium'   => 'Medium',
		'high'     => 'High',
		'critical' => 'Critical — active now',
	),
	'declarations' => array(
		'truthful' => 'I confirm that all information is true and accurate to the best of my knowledge.',
		'false_reports' => 'I understand that false or misleading reports may be rejected.',
		'verification' => 'I agree that additional verification steps may be required before assistance is provided.',
	),
	'actions' => array(
		'continue'     => 'Continue',
		'back'         => 'Back',
		'back_welcome' => 'Back to introduction',
		'submit'       => 'Submit report',
		'edit'         => 'Edit',
	),
	'workflow' => array(
		'label' => 'Reporting process',
	),
	'success' => array(
		'title' => 'Report received',
		'text' => 'Thank you. Your report has been recorded securely. Keep the reference number below — it will be used in any follow-up communication.',
		'ref_label' => 'Reference number',
		'chat_button' => 'Ask AI about this report',
		'chat_hint' => 'Opens live chat with your report context — reference, status, and updates are recognized automatically.',
	),
	'errors' => array(
		'required' => 'This field is required.',
		'checkbox' => 'This confirmation is required.',
		'submit'   => 'Could not submit the report. Please try again.',
	),
	'status_badges' => array(
		'collecting' => array(
			'label' => 'Collecting information',
		),
		'under_review' => array(
			'label' => 'Under Review',
		),
		'waiting_for_customer' => array(
			'label' => 'Waiting for Your Reply',
		),
		'resolved' => array(
			'label' => 'Approved',
		),
		'closed' => array(
			'label' => 'Closed',
		),
		'rejected' => array(
			'label' => 'Rejected',
		),
	),
	'timeline_i18n' => array(
		'support_team' => 'PAXDesign Support Team',
		'customer_fallback' => 'Customer',
		'empty_timeline' => 'No updates yet.',
		'subjects' => array(
			'report_submitted' => 'Report submitted',
			'status_submitted' => 'New report',
			'status_in_review' => 'In progress',
			'status_needs_info' => 'Additional information requested',
			'status_waiting_for_customer' => 'Waiting for customer',
			'status_customer_replied' => 'Customer replied',
			'status_waiting_for_staff' => 'Waiting for staff',
			'status_resolved' => 'Approved',
			'status_closed' => 'Closed',
			'status_rejected' => 'Rejected',
		),
	),
	'portal_js' => array(
		'errors' => array(
			'config' => 'Configuration error. Please refresh the page.',
			'submit' => 'Could not submit the report. Please try again.',
			'reply'  => 'Could not send the message. Please try again.',
			'login_required' => 'Please sign in to continue.',
			'active_report_exists' => 'You already have an open report. View your current report to add updates.',
			'message_required' => 'Message is required.',
			'report_not_found' => 'Report not found.',
			'request_rejected' => 'Request rejected.',
			'rate_limited' => 'Too many attempts. Please wait before trying again.',
			'identity_document' => 'Please upload an identity document before continuing.',
			'country_required' => 'Please select your country.',
		),
		'review' => array(
			'identity' => 'Identity',
			'incident' => 'Incident',
			'evidence' => 'Evidence',
			'files'    => 'file(s)',
			'none'     => '—',
			'yes'      => 'Yes',
			'no'       => 'No',
		),
		'guided' => array(
			'files' => 'files',
		),
	),
);
