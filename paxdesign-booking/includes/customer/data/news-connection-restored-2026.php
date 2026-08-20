<?php
if (!defined('ABSPATH')) {
    exit;
}

return array(
    'slug'     => 'app-connection-restored-2026',
    'priority' => 'high',
    'audience' => 'all_customers',
    'translations' => array(
        'de' => array(
            'title'   => 'Entschuldigung — die App ist wieder verbunden',
            'excerpt' => 'Ein vorübergehendes technisches Problem hat die Verbindung der App zur Website kurz unterbrochen. Das Problem ist behoben.',
            'body'    => '<p>Liebe Kundinnen und Kunden,</p>'
                . '<p>ein vorübergehendes technisches Problem hat dazu geführt, dass die App keine Verbindung zur Website herstellen konnte.</p>'
                . '<p>Das Problem ist inzwischen behoben. App und Website funktionieren wieder ganz normal.</p>'
                . '<p>Wir entschuldigen uns für die Unannehmlichkeiten.</p>'
                . '<p>Ihr PAXdesign-Team</p>',
        ),
        'en' => array(
            'title'   => 'Sorry — the app is connected again',
            'excerpt' => 'A temporary technical issue briefly stopped the app from reaching the website. That issue is now resolved.',
            'body'    => '<p>Dear customers,</p>'
                . '<p>A temporary technical issue caused the app to lose its connection to the website.</p>'
                . '<p>The issue has now been resolved. The app and website are working normally again.</p>'
                . '<p>We apologize for the inconvenience.</p>'
                . '<p>Your PAXdesign team</p>',
        ),
        'ar' => array(
            'title'   => 'عذرًا — التطبيق متصل من جديد',
            'excerpt' => 'أدت مشكلة تقنية مؤقتة إلى انقطاع اتصال التطبيق بالموقع. تم حل المشكلة الآن.',
            'body'    => '<p>عملاؤنا الأعزاء،</p>'
                . '<p>تسببت مشكلة تقنية مؤقتة في فقدان اتصال التطبيق بالموقع.</p>'
                . '<p>تم حل المشكلة، ويعمل التطبيق والموقع بشكل طبيعي مرة أخرى.</p>'
                . '<p>نعتذر عن الإزعاج.</p>'
                . '<p>فريق PAXdesign</p>',
        ),
    ),
);
