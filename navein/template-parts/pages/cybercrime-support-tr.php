<?php
/**
 * Turkish overlay for Cybercrime Support badges and chips.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'ticket_history' => array(
		'closed_badge' => 'Kapalı',
		'active_badge' => 'Aktif',
		'unread'       => 'Yeni',
	),
	'status_badges' => array(
		'collecting' => array(
			'label' => 'Bilgiler toplanıyor',
		),
		'under_review' => array(
			'label' => 'İncelemede',
		),
		'waiting_for_customer' => array(
			'label' => 'Yanıtınız bekleniyor',
		),
		'resolved' => array(
			'label' => 'Onaylandı',
		),
		'closed' => array(
			'label' => 'Kapalı',
		),
		'rejected' => array(
			'label' => 'Reddedildi',
		),
	),
	'urgency' => array(
		'low'      => 'Düşük',
		'medium'   => 'Orta',
		'high'     => 'Yüksek',
		'critical' => 'Kritik — şu anda aktif',
	),
	'categories' => array(
		'account_takeover'      => 'Hesap ele geçirme',
		'phishing_fraud'        => 'Oltalama / dolandırıcılık',
		'identity_theft'        => 'Kimlik hırsızlığı',
		'malware_ransomware'    => 'Kötü amaçlı yazılım / fidye',
		'social_media_recovery' => 'Sosyal medya kurtarma',
		'financial_fraud'       => 'Finansal dolandırıcılık',
		'data_breach'           => 'Veri ihlali',
		'other'                 => 'Diğer',
	),
	'platform_chips' => array(
		'other' => 'Diğer',
	),
);
