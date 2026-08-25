<?php
/**
 * Bind the Elementor pricing widget to the Apple site language switcher.
 *
 * The live /preise/ page embeds an IIFE with its own DE/EN/AR pack and
 * localStorage key pax-pricing-lang. Without this patch it always paints
 * German after load, including "Mehr erfahren" dropdowns.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'navein_pricing_widget_bridge_js' ) ) {
	/**
	 * JS injected just before CARD_DATA is merged into PAX_I18N.
	 *
	 * @return string
	 */
	function navein_pricing_widget_bridge_js() {
		return 'if(!PAX_I18N.tr&&PAX_I18N.en){PAX_I18N.tr=JSON.parse(JSON.stringify(PAX_I18N.en));PAX_I18N.tr.dir="ltr";PAX_I18N.tr.title="PAXdesign Hizmetleri";PAX_I18N.tr.subtitle="İşletmeniz için profesyonel BT çözümleri";PAX_I18N.tr.statement="Raf ürünleri geliştirmiyoruz — çalışan, güvenli ve ölçeklenebilir sistemler kuruyoruz.";PAX_I18N.tr.book="Randevu al";PAX_I18N.tr.more="Daha fazla";PAX_I18N.tr.less="Daha az göster";PAX_I18N.tr.alert="Rezervasyon sistemi yükleniyor. Lütfen biraz bekleyip yeniden deneyin.";PAX_I18N.tr.badges={popular:"Popüler",premium:"Premium",new:"YENİ"};PAX_I18N.tr.processTitle="Sizinle nasıl çalışıyoruz";PAX_I18N.tr.process=[{title:"Talep gönderin",text:"Rezervasyon formu üzerinden bize ulaşın"},{title:"Analiz ve danışmanlık",text:"Gereksinimlerinizi ayrıntılı inceleriz"},{title:"Teklif",text:"Size özel bir teklif alırsınız"},{title:"Uygulama",text:"Çözümünüzü profesyonelce geliştiririz"}];PAX_I18N.tr.securityCategoryTitle="Kod ve varlık koruması";PAX_I18N.tr.securityCategorySubtitle="Kod, varlıklar ve çalışma zamanı için profesyonel koruma — diğer hizmetlerimiz gibi rezervasyonla.";}if(typeof CARD_DATA==="object"&&CARD_DATA){Object.keys(CARD_DATA).forEach(function(key){if(CARD_DATA[key]&&CARD_DATA[key].en&&!CARD_DATA[key].tr){CARD_DATA[key].tr=JSON.parse(JSON.stringify(CARD_DATA[key].en));}});}';
	}
}

if ( ! function_exists( 'navein_patch_pricing_widget_i18n' ) ) {
	/**
	 * Patch the embedded pricing IIFE so cards and dropdowns follow pax_site_lang.
	 *
	 * @param string $html
	 * @return string
	 */
	function navein_patch_pricing_widget_i18n( $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return is_string( $html ) ? $html : '';
		}
		if ( strpos( $html, 'PAX_I18N' ) === false || strpos( $html, 'pax-pricing-lang' ) === false ) {
			return $html;
		}

		$html = str_replace(
			array(
				"document.documentElement.lang = lang;\r\n",
				"document.documentElement.lang = lang;\n",
				'document.documentElement.lang = lang;',
			),
			array(
				"/* html lang owned by site switcher */\r\n",
				"/* html lang owned by site switcher */\n",
				'/* html lang owned by site switcher */',
			),
			$html
		);

		$merge_old = "['de', 'en', 'ar'].forEach(function (lang) {";
		$merge_new = navein_pricing_widget_bridge_js() . "['de', 'en', 'ar', 'tr'].forEach(function (lang) {";
		if ( strpos( $html, $merge_old ) !== false ) {
			$html = str_replace( $merge_old, $merge_new, $html );
		}

		$init_old = "applyLanguage(saved && PAX_I18N[saved] ? saved : 'de');";
		$init_new = 'applyLanguage((function(){var site=(window.PAX_SITE_I18N&&PAX_SITE_I18N.lang)||"";if(!site){try{var m=document.cookie.match(/(?:^|; )pax_site_lang=([^;]*)/);site=m?decodeURIComponent(m[1]):"";}catch(e2){}}if(site==="tr"&&PAX_I18N&&!PAX_I18N.tr&&PAX_I18N.en){PAX_I18N.tr=JSON.parse(JSON.stringify(PAX_I18N.en));}return (site&&PAX_I18N[site])?site:(saved&&PAX_I18N[saved]?saved:"de");})());';
		if ( strpos( $html, $init_old ) !== false ) {
			$html = str_replace( $init_old, $init_new, $html );
		}

		return $html;
	}
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'the_content', 'navein_patch_pricing_widget_i18n', 999 );
	add_filter(
		'elementor/widget/render_content',
		static function ( $content ) {
			return navein_patch_pricing_widget_i18n( $content );
		},
		20
	);
}
