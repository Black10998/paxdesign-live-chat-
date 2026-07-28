<?php
/**
 * Bilingual copy for Cybercrime Support (Arabic default, German alternate).
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'lang_switch' => array(
		'ar' => 'Deutsch',
		'de' => 'العربية',
	),
	'hero' => array(
		'eyebrow' => array(
			'ar' => 'دعم الجرائم الإلكترونية',
			'de' => 'Cybercrime Support',
		),
		'title' => array(
			'ar' => 'مساعدة خبيرة عندما',
			'de' => 'Expertenhilfe, wenn',
		),
		'accent' => array(
			'ar' => 'تتعرض للاحتيال الرقمي.',
			'de' => 'digitale Kriminalität Sie trifft.',
		),
		'lede' => array(
			'ar' => 'استرداد الحسابات، تأمين البريد الإلكتروني، التحقيق في الاحتيال، حماية الهوية، واستجابة للحوادث — بسرية تامة ومنهجية واضحة.',
			'de' => 'Kontowiederherstellung, E-Mail-Sicherheit, Betrugsermittlung, Identitätsschutz und Incident Response — vertraulich, strukturiert und professionell.',
		),
		'cta_primary' => array(
			'ar' => 'استكشف الخدمات',
			'de' => 'Leistungen entdecken',
		),
		'cta_secondary' => array(
			'ar' => 'مسار الاستجابة',
			'de' => 'Response-Ablauf',
		),
	),
	'statement' => array(
		'ar' => 'نحن لا نقدم نصائح عامة فقط — بل نرشدك خطوة بخطوة عبر عملية الإبلاغ والاسترداد والأدلة الرقمية، مع احترام خصوصيتك في كل مرحلة.',
		'de' => 'Wir geben nicht nur allgemeine Tipps — wir führen Sie Schritt für Schritt durch Meldung, Wiederherstellung und digitale Beweissicherung, mit Respekt für Ihre Privatsphäre.',
	),
	'overview' => array(
		'eyebrow' => array(
			'ar' => 'نظرة عامة',
			'de' => 'Überblick',
		),
		'title' => array(
			'ar' => 'دعم شامل<br>للجرائم الإلكترونية.',
			'de' => 'Umfassender Support<br>bei Cybercrime.',
		),
		'items' => array(
			array(
				'title' => array(
					'ar' => 'استجابة فورية',
					'de' => 'Schnelle Reaktion',
				),
				'text' => array(
					'ar' => 'تقييم أولي خلال دقائق عبر الدردشة المباشرة العالمية، مع تصعيد إلى خبير عند الحاجة.',
					'de' => 'Ersteinschätzung in Minuten über den globalen Live Chat, mit Eskalation zu Experten bei Bedarf.',
				),
			),
			array(
				'title' => array(
					'ar' => 'منهجية قانونية',
					'de' => 'Forensische Methodik',
				),
				'text' => array(
					'ar' => 'توثيق الأدلة، سلاسل زمنية، وخطوات قابلة للتتبع للسلطات أو مزودي الخدمة.',
					'de' => 'Beweissicherung, Timelines und nachvollziehbare Schritte für Behörden oder Plattformen.',
				),
			),
			array(
				'title' => array(
					'ar' => 'سرية مطلقة',
					'de' => 'Strikte Vertraulichkeit',
				),
				'text' => array(
					'ar' => 'بياناتك محمية؛ لا نشارك التفاصيل إلا بموافقتك أو عند الضرورة القانونية.',
					'de' => 'Ihre Daten sind geschützt; Details werden nur mit Zustimmung oder bei rechtlicher Pflicht geteilt.',
				),
			),
		),
	),
	'features' => array(
		array(
			'id'    => 'social-recovery',
			'tone'  => 'dark',
			'eyebrow' => array(
				'ar' => 'استرداد الحسابات',
				'de' => 'Kontowiederherstellung',
			),
			'title' => array(
				'ar' => 'استرداد حسابات التواصل الاجتماعي',
				'de' => 'Social-Media-Kontowiederherstellung',
			),
			'text' => array(
				'ar' => 'Instagram، Facebook، TikTok، X، Snapchat — نساعدك على استعادة الوصول، تأمين الجلسات، وإيقاف محاولات الاختراق المتكررة.',
				'de' => 'Instagram, Facebook, TikTok, X, Snapchat — wir helfen beim Zugangs-Wiederherstellung, Session-Härtung und Stoppen wiederholter Übernahmeversuche.',
			),
			'items' => array(
				array(
					'ar' => 'التحقق من هوية المنصة وإجراءات الاستئناف',
					'de' => 'Plattform-Identitätsprüfung und Appeal-Prozesse',
				),
				array(
					'ar' => 'تأمين البريد والهاتف المرتبطين',
					'de' => 'Absicherung verknüpfter E-Mail und Telefonnummer',
				),
				array(
					'ar' => 'مراجعة نشاط تسجيل الدخول والأجهزة',
					'de' => 'Review von Login-Aktivität und Geräten',
				),
				array(
					'ar' => 'منع إعادة الاستيلاء بعد الاسترداد',
					'de' => 'Schutz vor erneuter Übernahme nach Recovery',
				),
			),
			'visual' => 'social',
		),
		array(
			'id'    => 'email-security',
			'tone'  => 'light',
			'eyebrow' => array(
				'ar' => 'أمان الحساب',
				'de' => 'Kontosicherheit',
			),
			'title' => array(
				'ar' => 'أمان البريد الإلكتروني والحسابات',
				'de' => 'E-Mail- & Kontosicherheit',
			),
			'text' => array(
				'ar' => 'Gmail، Outlook، iCloud، Yahoo — نؤمّن صناديق البريد، نفعّل المصادقة الثنائية، ونراجع قواعد إعادة التوجيه الخبيثة.',
				'de' => 'Gmail, Outlook, iCloud, Yahoo — wir sichern Postfächer, aktivieren 2FA und prüfen schädliche Weiterleitungsregeln.',
			),
			'items' => array(
				array(
					'ar' => 'تدقيق كلمات المرور ومدير كلمات المرور',
					'de' => 'Passwort-Audit und Password-Manager-Empfehlung',
				),
				array(
					'ar' => 'MFA / 2FA و مفاتيح الأمان',
					'de' => 'MFA / 2FA und Security Keys',
				),
				array(
					'ar' => 'كشف قواعد البريد الخبيثة',
					'de' => 'Erkennung bösartiger Mail-Regeln',
				),
				array(
					'ar' => 'عزل الجلسات النشطة المشبوهة',
					'de' => 'Isolation verdächtiger aktiver Sessions',
				),
			),
			'visual' => 'email',
		),
		array(
			'id'    => 'phishing-fraud',
			'tone'  => 'dark',
			'eyebrow' => array(
				'ar' => 'الاحتيال',
				'de' => 'Betrug',
			),
			'title' => array(
				'ar' => 'التصيد والاحتيال الإلكتروني',
				'de' => 'Phishing & Online-Betrug',
			),
			'text' => array(
				'ar' => 'تحليل رسائل التصيد، روابط مزيفة، مواقع clone، ومحاولات سرقة بيانات الدفع أو العملات الرقمية.',
				'de' => 'Analyse von Phishing-Mails, Fake-Links, Clone-Sites und Diebstahl von Zahlungs- oder Krypto-Daten.',
			),
			'items' => array(
				array(
					'ar' => 'فحص URL وسلسلة إعادة التوجيه',
					'de' => 'URL- und Redirect-Chain-Analyse',
				),
				array(
					'ar' => 'تقييم مخاطر الصفحات المزيفة',
					'de' => 'Risikobewertung gefälschter Landing Pages',
				),
				array(
					'ar' => 'إرشاد للإبلاغ للبنك أو المنصة',
					'de' => 'Anleitung für Meldung an Bank oder Plattform',
				),
				array(
					'ar' => 'خطوات منع الخسارة المالية',
					'de' => 'Schritte zur Vermeidung finanzieller Schäden',
				),
			),
			'visual' => 'shield',
		),
		array(
			'id'    => 'identity-theft',
			'tone'  => 'light',
			'eyebrow' => array(
				'ar' => 'الهوية',
				'de' => 'Identität',
			),
			'title' => array(
				'ar' => 'سرقة الهوية',
				'de' => 'Identitätsdiebstahl',
			),
			'text' => array(
				'ar' => 'عند استخدام بياناتك الشخصية دون إذن — ننسق خطوات التجميد، الإشعار، والتوثيق.',
				'de' => 'Wenn Ihre persönlichen Daten ohne Erlaubnis genutzt werden — koordinieren wir Sperrung, Meldung und Dokumentation.',
			),
			'items' => array(
				array(
					'ar' => 'تقييم نطاق التسريب',
					'de' => 'Bewertung des Leak-Umfangs',
				),
				array(
					'ar' => 'إشعار مزودي الخدمة والمنصات',
					'de' => 'Benachrichtigung von Diensten und Plattformen',
				),
				array(
					'ar' => 'مراقبة إعادة الاستخدام',
					'de' => 'Monitoring auf Wiederverwendung',
				),
				array(
					'ar' => 'دعم في التواصل مع الجهات الرسمية',
					'de' => 'Unterstützung bei Behördenkommunikation',
				),
			),
			'visual' => 'identity',
		),
		array(
			'id'    => 'malware-ransomware',
			'tone'  => 'dark',
			'eyebrow' => array(
				'ar' => 'البرمجيات الخبيثة',
				'de' => 'Malware',
			),
			'title' => array(
				'ar' => 'البرمجيات الخبيثة وفدية البيانات',
				'de' => 'Malware & Ransomware',
			),
			'text' => array(
				'ar' => 'فحص أولي، عزل الأجهزة، تقييم الضرر، وخطة استعادة بدون دفع فدية عند الإمكان.',
				'de' => 'Erstcheck, Geräte-Isolation, Schadensbewertung und Recovery-Plan — ohne Lösegeld wenn möglich.',
			),
			'items' => array(
				array(
					'ar' => 'تحديد نوع العدوى',
					'de' => 'Identifikation der Infektionsart',
				),
				array(
					'ar' => 'فصل الشبكة ومنع الانتشار',
					'de' => 'Netzwerk-Trennung und Ausbreitungsstopp',
				),
				array(
					'ar' => 'استراتيجية النسخ الاحتياطي والاستعادة',
					'de' => 'Backup- und Restore-Strategie',
				),
				array(
					'ar' => 'تقوية النظام بعد التنظيف',
					'de' => 'System-Härtung nach Bereinigung',
				),
			),
			'visual' => 'malware',
		),
		array(
			'id'    => 'file-recovery',
			'tone'  => 'light',
			'eyebrow' => array(
				'ar' => 'الأدلة الرقمية',
				'de' => 'Digitale Beweise',
			),
			'title' => array(
				'ar' => 'استرداد الملفات والأدلة الرقمية',
				'de' => 'Dateiwiederherstellung & digitale Beweise',
			),
			'text' => array(
				'ar' => 'استخراج المحادثات، لقطات الشاشة، سجلات البريد، ومخرجات قابلة للاستخدام في البلاغات الرسمية.',
				'de' => 'Extraktion von Chats, Screenshots, Mail-Logs und exportierbaren Artefakten für offizielle Meldungen.',
			),
			'items' => array(
				array(
					'ar' => 'سلسلة حفظ مبسّطة للأدلة',
					'de' => 'Vereinfachte Chain-of-Custody',
				),
				array(
					'ar' => 'تصدير منظم للمحادثات والمرفقات',
					'de' => 'Strukturierter Export von Chats und Anhängen',
				),
				array(
					'ar' => 'استرداد ملفات محذوفة عند الإمكان',
					'de' => 'Wiederherstellung gelöschter Dateien wo möglich',
				),
				array(
					'ar' => 'تقرير جاهز للسلطات أو المحامي',
					'de' => 'Bericht für Behörden oder Anwalt',
				),
			),
			'visual' => 'evidence',
		),
		array(
			'id'    => 'encryption-privacy',
			'tone'  => 'dark',
			'eyebrow' => array(
				'ar' => 'الخصوصية',
				'de' => 'Privatsphäre',
			),
			'title' => array(
				'ar' => 'التشفير والخصوصية',
				'de' => 'Verschlüsselung & Privatsphäre',
			),
			'text' => array(
				'ar' => 'تأمين الأجهزة والرسائل بعد الحادث — تشفير، إعدادات خصوصية، وتقليل البصمة الرقمية.',
				'de' => 'Absicherung von Geräten und Kommunikation nach dem Vorfall — Verschlüsselung, Privacy-Settings, reduzierte digitale Spur.',
			),
			'items' => array(
				array(
					'ar' => 'تشفير القرص والنسخ الاحتياطي الآمن',
					'de' => 'Festplattenverschlüsselung und sicheres Backup',
				),
				array(
					'ar' => 'مراجعة أذونات التطبيقات',
					'de' => 'Review von App-Berechtigungen',
				),
				array(
					'ar' => 'إعدادات خصوصية المنصات',
					'de' => 'Plattform-Privacy-Einstellungen',
				),
				array(
					'ar' => 'تقليل التعرض المستقبلي',
					'de' => 'Reduzierung künftiger Exposition',
				),
			),
			'visual' => 'lock',
		),
	),
	'process' => array(
		'eyebrow' => array(
			'ar' => 'العملية',
			'de' => 'Prozess',
		),
		'title' => array(
			'ar' => 'مسار<br>الاستجابة للحادث.',
			'de' => 'Unser Incident-<br>Response-Ablauf.',
		),
		'steps' => array(
			array(
				'title' => array(
					'ar' => 'التواصل الأول',
					'de' => 'Erstkontakt',
				),
				'text' => array(
					'ar' => 'افتح الدردشة المباشرة من أي مكان في الموقع وصف ما حدث باختصار — بدون حكم، بسرية.',
					'de' => 'Globalen Live Chat öffnen und kurz schildern — ohne Bewertung, vertraulich.',
				),
			),
			array(
				'title' => array(
					'ar' => 'التقييم',
					'de' => 'Einschätzung',
				),
				'text' => array(
					'ar' => 'نحدد الأولوية: حساب مسروق، خسارة مالية، برمجيات خبيثة، أو سرقة هوية.',
					'de' => 'Priorität setzen: Kontoübernahme, Finanzschaden, Malware oder Identitätsdiebstahl.',
				),
			),
			array(
				'title' => array(
					'ar' => 'الاحتواء',
					'de' => 'Eindämmung',
				),
				'text' => array(
					'ar' => 'خطوات فورية: تغيير كلمات المرور، قطع الجلسات، تجميد البطاقات.',
					'de' => 'Sofortmaßnahmen: Passwörter, Sessions beenden, Karten sperren.',
				),
			),
			array(
				'title' => array(
					'ar' => 'التحقيق',
					'de' => 'Untersuchung',
				),
				'text' => array(
					'ar' => 'جمع الأدلة، تحليل الروابط، وتوثيق الجدول الزمني.',
					'de' => 'Beweise sammeln, Links analysieren, Timeline dokumentieren.',
				),
			),
			array(
				'title' => array(
					'ar' => 'الاسترداد',
					'de' => 'Wiederherstellung',
				),
				'text' => array(
					'ar' => 'استعادة الحسابات، الملفات، والوصول مع تأمين دائم.',
					'de' => 'Konten, Dateien und Zugang zurück — dauerhaft abgesichert.',
				),
			),
			array(
				'title' => array(
					'ar' => 'المتابعة',
					'de' => 'Nachsorge',
				),
				'text' => array(
					'ar' => 'مراقبة، تقارير، وتوصيات لمنع تكرار الحادث.',
					'de' => 'Monitoring, Reports und Empfehlungen gegen Wiederholung.',
				),
			),
		),
	),
	'faq' => array(
		'eyebrow' => array(
			'ar' => 'أسئلة شائعة',
			'de' => 'FAQ',
		),
		'title' => array(
			'ar' => 'أسئلة<br>متكررة.',
			'de' => 'Häufige<br>Fragen.',
		),
		'items' => array(
			array(
				'q' => array(
					'ar' => 'هل المحادثة سرية؟',
					'de' => 'Ist der Chat vertraulich?',
				),
				'a' => array(
					'ar' => 'نعم. نتعامل مع بياناتك بسرية مهنية. لا نشارك التفاصيل مع أطراف ثالثة دون موافقتك، إلا عند وجود التزام قانوني.',
					'de' => 'Ja. Wir behandeln Ihre Angaben professionell vertraulich. Keine Weitergabe an Dritte ohne Zustimmung — außer bei gesetzlicher Pflicht.',
				),
			),
			array(
				'q' => array(
					'ar' => 'ماذا أعدّ قبل فتح الدردشة المباشرة؟',
					'de' => 'Was soll ich vor dem Live Chat vorbereiten?',
				),
				'a' => array(
					'ar' => 'لقطات شاشة، رسائل مشبوهة، روابط، تواريخ، وأسماء المنصات المتأثرة — إن وُجدت.',
					'de' => 'Screenshots, verdächtige Nachrichten, Links, Daten und betroffene Plattformen — falls vorhanden.',
				),
			),
			array(
				'q' => array(
					'ar' => 'هل تضمنون استرداد الحساب؟',
					'de' => 'Garantieren Sie Kontowiederherstellung?',
				),
				'a' => array(
					'ar' => 'لا يمكن لأي جهة ضمان قرار المنصة، لكننا نتبع أفضل الممارسات ونرافقك في كل خطوة رسمية.',
					'de' => 'Keiner kann Plattform-Entscheidungen garantieren — wir führen Sie aber durch bewährte offizielle Schritte.',
				),
			),
			array(
				'q' => array(
					'ar' => 'هل تتعاملون مع الشركات أيضاً؟',
					'de' => 'Unterstützen Sie auch Unternehmen?',
				),
				'a' => array(
					'ar' => 'نعم — حوادث البريد المؤسسي، الاحتيال على الموظفين، واختراق الحسابات التجارية.',
					'de' => 'Ja — Business-E-Mail, CEO-Fraud, kompromittierte Firmenkonten.',
				),
			),
			array(
				'q' => array(
					'ar' => 'هل يمكن التحدث بالعربية أو الألمانية؟',
					'de' => 'Arabisch oder Deutsch im Chat?',
				),
				'a' => array(
					'ar' => 'نعم. المساعد الذكي يتكيف مع لغتك — العربية والألمانية مدعومتان بالكامل.',
					'de' => 'Ja. Der KI-Assistent passt sich an — Arabisch und Deutsch werden voll unterstützt.',
				),
			),
		),
	),
	'trust' => array(
		'eyebrow' => array(
			'ar' => 'الثقة',
			'de' => 'Vertrauen',
		),
		'title' => array(
			'ar' => 'سرية.<br>احترافية.<br>وضوح.',
			'de' => 'Vertraulich.<br>Professionell.<br>Transparent.',
		),
		'text' => array(
			'ar' => 'PAXDesign فريق تقني متمرس في الأمن الرقمي واستجابة الحوادث. نحن لسنا سلطات تحقيق — بل شريك تقني يرشدك، يوثّق، وينسّق مع المنصات والجهات عند الحاجة.',
			'de' => 'PAXDesign ist ein erfahrenes Tech-Team für digitale Sicherheit und Incident Response. Wir sind keine Ermittlungsbehörde — sondern Ihr technischer Partner für Guidance, Dokumentation und Koordination.',
		),
		'points' => array(
			array(
				'ar' => 'معالجة آمنة للبيانات الحساسة',
				'de' => 'Sichere Verarbeitung sensibler Daten',
			),
			array(
				'ar' => 'تواصل واضح بدون مصطلحات مربكة',
				'de' => 'Klare Kommunikation ohne Jargon',
			),
			array(
				'ar' => 'مسار موثّق من البداية للنهاية',
				'de' => 'Dokumentierter Ablauf von Anfang bis Ende',
			),
		),
	),
	'cta' => array(
		'title' => array(
			'ar' => 'تحتاج مساعدة<br>الآن؟',
			'de' => 'Brauchen Sie<br>jetzt Hilfe?',
		),
		'text' => array(
			'ar' => 'استخدم الدردشة المباشرة العالمية في الموقع — المساعد الذكي يتعرف تلقائياً على أنك من صفحة دعم الجرائم الإلكترونية ويرشدك في عملية الإبلاغ.',
			'de' => 'Nutzen Sie den globalen Live Chat — der KI-Assistent erkennt automatisch Ihren Cybercrime-Kontext und führt Sie durch die Meldung.',
		),
		'primary' => array(
			'ar' => 'اتصل بنا',
			'de' => 'Kontakt aufnehmen',
		),
		'phone_label' => array(
			'ar' => 'اتصال عاجل',
			'de' => 'Dringender Anruf',
		),
	),
);
