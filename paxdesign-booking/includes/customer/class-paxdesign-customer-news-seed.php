<?php
/**
 * Seed professional news articles for the customer portal News section.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_News_Seed {

    const OPTION_KEY = 'paxdesign_customer_news_seed_v1';

    public static function init() {
        add_action('paxdesign_customer_platform_ready', array(__CLASS__, 'maybe_seed'), 20);
    }

    public static function maybe_seed() {
        if (get_option(self::OPTION_KEY) === '1') {
            return;
        }
        global $wpdb;
        $table = PAXdesign_Customer_DB::table('news');
        $count = (int) $wpdb->get_var("SELECT COUNT(1) FROM $table WHERE status = 'published'");
        if ($count >= 20) {
            update_option(self::OPTION_KEY, '1', false);
            return;
        }
        self::seed_articles();
        update_option(self::OPTION_KEY, '1', false);
    }

    public static function seed_articles() {
        $articles = self::articles();
        $now = time();
        foreach ($articles as $index => $article) {
            $published = gmdate('Y-m-d H:i:s', $now - (($index + 1) * 86400 * 3));
            PAXdesign_Customer_News::save(array(
                'slug'          => $article['slug'],
                'title'         => $article['title'],
                'excerpt'       => $article['excerpt'],
                'body'          => $article['body'],
                'status'        => 'published',
                'priority'      => $index < 3 ? 'high' : 'normal',
                'audience'      => 'all_customers',
                'audience_meta' => array(
                    'featured_image_url' => $article['image'],
                ),
                'published_at'  => $published,
            ), 1);
        }
    }

    /**
     * @return array<int, array{slug:string,title:string,excerpt:string,body:string,image:string}>
     */
    private static function articles() {
        return array(
            array(
                'slug'    => 'paxdesign-portal-launch',
                'title'   => 'Willkommen im neuen PAXDesign Kundenportal',
                'excerpt' => 'Projekte, Anfragen, Chat und Dateien – alles an einem Ort, synchron auf Website und App.',
                'image'   => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80',
                'body'    => '<p>PAXDesign stellt das überarbeitete Kundenportal vor: eine zentrale Oberfläche für Projekte, Service-Anfragen, sicheren Dateiaustausch und direkten Chat mit unserem Team.</p><p>Ob Website oder iOS-App – Ihre Unterhaltungen und Statusupdates bleiben synchron. So behalten Sie jederzeit den Überblick über laufende Arbeiten.</p>',
            ),
            array(
                'slug'    => 'premium-webdesign-2026',
                'title'   => 'Premium Webdesign: Was 2026 wirklich zählt',
                'excerpt' => 'Performance, Barrierefreiheit und klare Markenführung sind keine Extras mehr – sie sind Standard.',
                'image'   => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&q=80',
                'body'    => '<p>Moderne Websites müssen auf allen Geräten schnell laden, gut lesbar sein und Vertrauen aufbauen. PAXDesign kombiniert reduziertes Design mit technischer Exzellenz – von der ersten Idee bis zum Go-Live.</p>',
            ),
            array(
                'slug'    => 'ki-assistent-im-kundenservice',
                'title'   => 'KI-Assistent im Kundenservice: schneller Einstieg, menschliche Betreuung',
                'excerpt' => 'Unser intelligenter Assistent beantwortet Standardfragen sofort – Live-Support übernimmt nahtlos.',
                'image'   => 'https://images.unsplash.com/photo-1677440866019-21780ecad995?w=1200&q=80',
                'body'    => '<p>Der PAXDesign KI-Assistent hilft bei Terminen, Leistungen und ersten Fragen rund um die Uhr. Wenn es komplex wird, wechseln Sie mit einem Klick zum Live-Chat – ohne neue Unterhaltung, ohne Informationsverlust.</p>',
            ),
            array(
                'slug'    => 'barrierefreiheit-light-mode',
                'title'   => 'Barrierefreiheit und Lesbarkeit im Fokus',
                'excerpt' => 'Hoher Kontrast, klare Typografie und durchdachte Light-Mode-Oberflächen für bessere Nutzbarkeit.',
                'image'   => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1200&q=80',
                'body'    => '<p>Gute Software ist für alle verständlich. Deshalb optimieren wir kontinuierlich Kontrast, Schriftgrößen und visuelle Hierarchie – besonders im Standard-Light-Mode unserer App und Website.</p>',
            ),
            array(
                'slug'    => 'ecommerce-performance',
                'title'   => 'E-Commerce Performance: Schneller verkaufen',
                'excerpt' => 'Ladezeiten, Checkout-Flow und mobile UX entscheiden über Conversion.',
                'image'   => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=1200&q=80',
                'body'    => '<p>Wir optimieren Shops für Core Web Vitals, klare Produktpräsentation und reibungslose Bezahlvorgänge. Das Ergebnis: weniger Abbrüche, mehr Abschlüsse.</p>',
            ),
            array(
                'slug'    => 'corporate-identity-digital',
                'title'   => 'Corporate Identity digital umsetzen',
                'excerpt' => 'Vom Logo bis zur gesamten Customer Journey – ein konsistentes Markenerlebnis.',
                'image'   => 'https://images.unsplash.com/photo-1561070791-25218d41252a?w=1200&q=80',
                'body'    => '<p>PAXDesign entwickelt digitale Markensysteme, die online und offline funktionieren: Farben, Typografie, Iconografie und UI-Komponenten aus einer Hand.</p>',
            ),
            array(
                'slug'    => 'wordpress-enterprise',
                'title'   => 'WordPress Enterprise: Sicher und skalierbar',
                'excerpt' => 'Professionelle WordPress-Architektur für wachsende Unternehmen.',
                'image'   => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&q=80',
                'body'    => '<p>Von Custom Plugins bis Managed Hosting: Wir bauen WordPress-Lösungen, die wartbar, sicher und erweiterbar sind – ideal für Unternehmenswebsites und Kundenportale.</p>',
            ),
            array(
                'slug'    => 'mobile-first-design',
                'title'   => 'Mobile First: Design beginnt auf dem Smartphone',
                'excerpt' => 'Über 70 % der Besucher kommen mobil – wir planen deshalb vom kleinsten Screen aus.',
                'image'   => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=1200&q=80',
                'body'    => '<p>Responsive Layouts sind bei PAXDesign keine Nachthought. Navigation, Typografie und Interaktionen werden zuerst für Touch und kleine Displays optimiert.</p>',
            ),
            array(
                'slug'    => 'seo-grundlagen-2026',
                'title'   => 'SEO-Grundlagen 2026: Sichtbarkeit mit Substanz',
                'excerpt' => 'Technisches SEO, saubere Struktur und hochwertiger Content als Fundament.',
                'image'   => 'https://images.unsplash.com/photo-1432888622747-4eb9a8f2c293?w=1200&q=80',
                'body'    => '<p>Wir integrieren SEO von Anfang an: semantisches HTML, performante Assets, strukturierte Daten und Inhalte, die echten Mehrwert bieten.</p>',
            ),
            array(
                'slug'    => 'landingpages-conversion',
                'title'   => 'Landingpages, die konvertieren',
                'excerpt' => 'Klare Botschaften, starke CTAs und messbare Ergebnisse.',
                'image'   => 'https://images.unsplash.com/photo-1553877522-43269d4ea984?w=1200&q=80',
                'body'    => '<p>Kampagnen-Landingpages mit Fokus auf eine Aktion: Anfrage, Buchung oder Download. A/B-Tests und Analytics inklusive.</p>',
            ),
            array(
                'slug'    => 'chat-echtzeit-sync',
                'title'   => 'Echtzeit-Chat zwischen App und Website',
                'excerpt' => 'Nachrichten erscheinen nahezu sofort auf allen Geräten – eine durchgehende Unterhaltung.',
                'image'   => 'https://images.unsplash.com/photo-1573164713988-8665fc963095?w=1200&q=80',
                'body'    => '<p>Unser Chat-System synchronisiert Unterhaltungen in Echtzeit zwischen iOS-App und Website. Starten Sie am Desktop, antworten Sie unterwegs – ohne Medienbruch.</p>',
            ),
            array(
                'slug'    => 'dateien-rechnungen-portal',
                'title'   => 'Dateien & Rechnungen im Portal',
                'excerpt' => 'Verträge, Freigaben und Rechnungen sicher im Kundenbereich abrufen.',
                'image'   => 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=1200&q=80',
                'body'    => '<p>Alle projektbezogenen Dokumente an einem Ort – mit klarer Struktur, Download-Funktion und Berechtigungskonzept.</p>',
            ),
            array(
                'slug'    => 'portfolio-showcase',
                'title'   => 'Portfolio Showcase: Referenzen neu präsentiert',
                'excerpt' => 'Ausgewählte Projekte mit Galerie, Details und direkter Projektanfrage.',
                'image'   => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&q=80',
                'body'    => '<p>Entdecken Sie unsere Referenzen im Portfolio-Bereich – inklusive Projektbeschreibung, Bildergalerie und Option „Ähnliches Projekt starten“.</p>',
            ),
            array(
                'slug'    => 'service-katalog-native',
                'title'   => 'Nativer Service-Katalog in der App',
                'excerpt' => 'Alle Leistungen übersichtlich – mit Details, Medien und direkter Anfrage.',
                'image'   => 'https://images.unsplash.com/photo-1551434678-e076c223a692?w=1200&q=80',
                'body'    => '<p>Der integrierte Leistungskatalog spiegelt unsere Website wider: Kategorien, Beschreibungen, Beispiele und kontextbezogene Bestellbuttons.</p>',
            ),
            array(
                'slug'    => 'push-benachrichtigungen',
                'title'   => 'Push-Benachrichtigungen: Immer informiert',
                'excerpt' => 'Updates zu Projekten, Anfragen und Chat-Nachrichten – mit feingranularer Steuerung.',
                'image'   => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
                'body'    => '<p>Aktivieren Sie Benachrichtigungen für Chat, Projekte und Aufträge. Ungelesene Hinweise bleiben sichtbar, bis Sie den jeweiligen Bereich öffnen.</p>',
            ),
            array(
                'slug'    => 'sicherheit-kundendaten',
                'title'   => 'Sicherheit & Datenschutz für Kundendaten',
                'excerpt' => 'Verschlüsselung, Geräteverwaltung und transparente Datenverarbeitung.',
                'image'   => 'https://images.unsplash.com/photo-1563986768609-322da13575f3?w=1200&q=80',
                'body'    => '<p>PAXDesign setzt auf sichere Authentifizierung, nachvollziehbare Geräte-Sessions und DSGVO-konforme Prozesse – in App und Portal.</p>',
            ),
            array(
                'slug'    => 'design-system-pax',
                'title'   => 'Design System: Einheitliche PAXDesign Sprache',
                'excerpt' => 'Akzentfarbe #C2FF00, klare Typografie und konsistente UI-Komponenten.',
                'image'   => 'https://images.unsplash.com/photo-1558655146-d09347e92766?w=1200&q=80',
                'body'    => '<p>Unser visuelles System sorgt für Wiedererkennung über alle Touchpoints – von der Marketing-Website bis zur nativen iOS-App.</p>',
            ),
            array(
                'slug'    => 'wartung-support',
                'title'   => 'Wartung & Support: Langfristige Partnerschaft',
                'excerpt' => 'Updates, Monitoring und schnelle Reaktion bei Anliegen.',
                'image'   => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=1200&q=80',
                'body'    => '<p>Nach dem Launch bleiben wir an Ihrer Seite: Sicherheitsupdates, Performance-Checks und direkter Draht über Chat und Portal.</p>',
            ),
            array(
                'slug'    => 'mehrsprachige-websites',
                'title'   => 'Mehrsprachige Websites: DE, EN, AR',
                'excerpt' => 'Inhalte und Navigation für internationale Zielgruppen vorbereiten.',
                'image'   => 'https://images.unsplash.com/photo-1526628953301-3e589a176424?w=1200&q=80',
                'body'    => '<p>Wir implementieren saubere Mehrsprachigkeit mit SEO-tauglichen URLs, RTL-Unterstützung und konsistentem Design in allen Sprachen.</p>',
            ),
            array(
                'slug'    => 'buchungssystem-integration',
                'title'   => 'Buchungssystem & Terminintegration',
                'excerpt' => 'Online-Termine direkt in Website und App – nahtlos mit Ihrem Workflow.',
                'image'   => 'https://images.unsplash.com/photo-1506784362817-a3955168657e?w=1200&q=80',
                'body'    => '<p>Das PAXDesign Buchungssystem verbindet Kalender, Erinnerungen und Kundenkommunikation – ideal für Beratungen und Service-Termine.</p>',
            ),
            array(
                'slug'    => 'news-section-update',
                'title'   => 'News-Bereich: Aktuelles aus dem PAXDesign Studio',
                'excerpt' => 'Tipps, Produktupdates und Einblicke – regelmäßig frisch für unsere Kunden.',
                'image'   => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?w=1200&q=80',
                'body'    => '<p>Der News-Bereich bündelt Ankündigungen, Branchenwissen und Feature-Updates. So bleiben Sie informiert, ohne externe Kanäle zu benötigen.</p>',
            ),
        );
    }
}
