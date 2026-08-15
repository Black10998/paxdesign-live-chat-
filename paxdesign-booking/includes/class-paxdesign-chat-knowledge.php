<?php
/**
 * Structured PAXDesign service knowledge for the AI sales assistant.
 * Mirrors the public Leistungen/pricing page (CARD_DATA, DE).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Chat_Knowledge {

    /**
     * Company positioning and process — aligned with the services page.
     */
    public static function get_company_context() {
        return array(
            'name'      => 'PAXDesign',
            'url'       => 'https://paxdesign.at',
            'tagline'   => 'Professionelle IT-Lösungen für Ihr Unternehmen',
            'statement' => 'Wir entwickeln keine Produkte von der Stange – wir bauen funktionierende, sichere und skalierbare Systeme.',
            'process'   => array(
                'Anfrage stellen — Kontakt über Buchungsformular oder Chat',
                'Analyse & Beratung — Anforderungen verstehen',
                'Angebot erstellen — maßgeschneidertes Angebot',
                'Umsetzung — professionelle Entwicklung und Betreuung',
            ),
        );
    }

    /**
     * Service catalog keyed by internal ID; booking_name matches data-service on the website.
     *
     * @return array<string, array{booking_name: string, title: string, summary: string, features: string[]}>
     */
    public static function get_service_catalog() {
        $catalog = array(
            'website' => array(
                'booking_name' => 'Website',
                'title'        => 'Website',
                'summary'      => 'Moderne, responsive Website mit professionellem Design, SEO und Performance — individuell entwickelt, ohne Standard-Templates.',
                'features'     => array('Responsive Design', 'SEO-Optimierung', 'CMS Integration', 'SSL-Zertifikat'),
            ),
            'webapp' => array(
                'booking_name' => 'Web App',
                'title'        => 'Web App',
                'summary'      => 'Individuelle Webanwendung, die Geschäftsprozesse digitalisiert und automatisiert — maßgeschneidert, nicht generisches SaaS.',
                'features'     => array('Custom Development', 'API Integration', 'User Management', 'Cloud Hosting'),
            ),
            'android' => array(
                'booking_name' => 'Android App',
                'title'        => 'Android App',
                'summary'      => 'Native Android-App mit optimaler Performance, Push-Benachrichtigungen und Play Store Veröffentlichung.',
                'features'     => array('Native Development', 'Play Store Veröffentlichung', 'Push Notifications', 'Offline-Funktionalität'),
            ),
            'ios' => array(
                'booking_name' => 'iOS App',
                'title'        => 'iOS App',
                'summary'      => 'Native iOS-App in Swift für iPhone und iPad — App Store Connect und Release-Betreuung inklusive.',
                'features'     => array('Swift Development', 'App Store Veröffentlichung', 'iCloud Integration', 'Face ID / Touch ID'),
            ),
            'crossplatform' => array(
                'booking_name' => 'iOS + Android',
                'title'        => 'iOS + Android',
                'summary'      => 'Cross-Platform-Lösung mit gemeinsamer Codebasis — effizient, mit plattformspezifischer Feinabstimmung.',
                'features'     => array('Beide Plattformen', 'Gemeinsame Codebasis', 'Alle Store-Features', 'Synchronisation'),
            ),
            'androidtv' => array(
                'booking_name' => 'Android TV',
                'title'        => 'Android TV',
                'summary'      => 'TV-optimierte Anwendung mit Fernbedienungsnavigation, 4K-Streaming und Cast-Integration.',
                'features'     => array('TV-optimierte UI', 'Fernbedienung Support', '4K Streaming', 'Chromecast Integration'),
            ),
            'security' => array(
                'booking_name' => 'IT-Sicherheit',
                'title'        => 'IT-Sicherheit',
                'summary'      => 'Sicherheitsanalyse mit Penetration Testing, Schwachstellenanalyse und DSGVO-Prüfung im realen Systemkontext.',
                'features'     => array('Penetration Testing', 'Security Audit', 'DSGVO-Konformität', 'Verschlüsselung'),
            ),
            'backend' => array(
                'booking_name' => 'Backend System',
                'title'        => 'Backend System',
                'summary'      => 'Hochperformante Backend-Architektur mit REST/GraphQL APIs, skalierbarer Datenbank und sicherer Authentifizierung.',
                'features'     => array('REST/GraphQL API', 'Datenbank Design', 'Microservices', 'Load Balancing'),
            ),
            'devops' => array(
                'booking_name' => 'Server & DevOps',
                'title'        => 'Server & DevOps',
                'summary'      => 'Cloud-Infrastruktur mit CI/CD, Monitoring und Backup — AWS, Azure oder Hetzner, projektspezifisch.',
                'features'     => array('Cloud Setup', 'CI/CD Pipeline', 'Monitoring', 'Backup-Strategie'),
            ),
            'enterprise' => array(
                'booking_name' => 'Enterprise',
                'title'        => 'Enterprise',
                'summary'      => 'Komplettlösung mit dediziertem Team, 24/7 Support, SLA und langfristiger technischer Verantwortung.',
                'features'     => array('Alle Leistungen', 'Dediziertes Team', '24/7 Support', 'SLA Garantie'),
            ),
            'aiautomation' => array(
                'booking_name' => 'AI Automation',
                'title'        => 'AI Automation',
                'summary'      => 'KI-gestützte Automatisierung wiederkehrender Prozesse — von Datenerfassung bis Report-Erstellung.',
                'features'     => array('Prozessautomatisierung', 'AI Chatbots', 'API-Anbindung', 'Intelligente Reports'),
            ),
            'aichatbot' => array(
                'booking_name' => 'AI Chatbot',
                'title'        => 'AI Chatbot',
                'summary'      => 'Intelligenter Assistent für Website oder Shop — trainiert auf Ihre Marke, Produkte und Prozesse.',
                'features'     => array('Automatische Antworten', 'Mehrsprachiger Support', 'WhatsApp-Anbindung', 'Training mit Ihren Daten'),
            ),
            'ecommerce' => array(
                'booking_name' => 'E-Commerce Shop',
                'title'        => 'E-Commerce Shop',
                'summary'      => 'Professioneller Online-Shop mit Zahlungssystem, Produktverwaltung und conversion-optimierten Seiten.',
                'features'     => array('WooCommerce / Shopify', 'Zahlungsgateways', 'Produktverwaltung', 'Conversion-Optimierung'),
            ),
            'maintenance' => array(
                'booking_name' => 'Monthly Maintenance',
                'title'        => 'Monatliche Wartung',
                'summary'      => 'Monatlicher Website-Service für Updates, Backups, Sicherheitsmonitoring und technischen Support.',
                'features'     => array('Regelmäßige Updates', 'Backup', 'Sicherheitsmonitoring', 'Technischer Support'),
            ),
            'pagespeed' => array(
                'booking_name' => 'Website Speed Optimization',
                'title'        => 'Website-Geschwindigkeit',
                'summary'      => 'Performance-Optimierung für schnellere Ladezeiten, bessere Core Web Vitals und höhere Conversion.',
                'features'     => array('PageSpeed Optimization', 'Bildoptimierung', 'Code-Reduktion', 'Caching / CDN'),
            ),
            'uiux' => array(
                'booking_name' => 'UI/UX Design',
                'title'        => 'UI/UX Design',
                'summary'      => 'Benutzerfreundliche Interfaces und UX-Konzepte, die Besucher schnell zur richtigen Entscheidung führen.',
                'features'     => array('Interface Design', 'User Experience', 'Wireframes', 'Prototypen'),
            ),
            'branding' => array(
                'booking_name' => 'Branding & Identity',
                'title'        => 'Branding & Identity',
                'summary'      => 'Visuelle Identität mit Logo, Farben, Typografie und konsistentem Markenauftritt auf allen Kanälen.',
                'features'     => array('Logo-Design', 'Farben & Identität', 'Style Guide', 'Marketing-Materialien'),
            ),
            'crm' => array(
                'booking_name' => 'CRM System',
                'title'        => 'CRM System',
                'summary'      => 'Maßgeschneidertes CRM für Kunden, Aufträge und Follow-ups — exakt auf Ihre Vertriebsprozesse abgestimmt.',
                'features'     => array('Kundenverwaltung', 'Auftragsverfolgung', 'Benutzerrechte', 'Management-Reports'),
            ),
            'bookingsystem' => array(
                'booking_name' => 'Appointment Booking System',
                'title'        => 'Terminbuchungssystem',
                'summary'      => 'Intelligentes Buchungssystem für Praxen, Büros und Dienstleistungen mit Erinnerungen und Online-Zahlung.',
                'features'     => array('Terminkalender', 'Online-Zahlung', 'E-Mail/SMS-Benachrichtigungen', 'Admin-Dashboard'),
            ),
            'pwa' => array(
                'booking_name' => 'Website to App',
                'title'        => 'Website zur App',
                'summary'      => 'Progressive Web App — installierbare App-Erfahrung vom Homescreen, ohne App Store.',
                'features'     => array('PWA', 'Push-Benachrichtigungen', 'Offline-Funktion', 'Installation auf dem Handy'),
            ),
            'analytics' => array(
                'booking_name' => 'Data Analytics & Reports',
                'title'        => 'Datenanalyse & Reports',
                'summary'      => 'Dashboards für Umsatz, Kunden, Bestellungen und Traffic — datenbasierte Entscheidungen statt Bauchgefühl.',
                'features'     => array('Dashboard', 'Google Analytics', 'Individuelle Reports', 'KPIs'),
            ),
            'gdpr' => array(
                'booking_name' => 'GDPR & Cookie Setup',
                'title'        => 'GDPR & Cookie Setup',
                'summary'      => 'Technische und rechtliche Einrichtung von Cookie-Banner, Consent Mode v2 und Datenschutzseiten.',
                'features'     => array('Cookie Banner', 'Privacy Policy', 'Consent Mode', 'GDPR Setup'),
            ),
            'secflash' => array(
                'booking_name' => 'Military Flash Protection',
                'title'        => 'Military Flash Protection',
                'summary'      => 'Schutz gegen unbefugtes Kopieren und Manipulation digitaler Assets — Enterprise-Niveau.',
                'features'     => array('Anti-Copy Schutz', 'Flash-Härtung', 'Manipulationserkennung', 'Deployment-Sicherheit'),
            ),
            'seclayers' => array(
                'booking_name' => 'Encrypted Protection Layers',
                'title'        => 'Encrypted Protection Layers',
                'summary'      => 'Verschlüsselte Schutzschichten für JS, CSS und interne Logik — erschwert Reverse Engineering.',
                'features'     => array('Mehrschicht-Verschlüsselung', 'JS/CSS Schutz', 'Schlüsselmanagement', 'Runtime-Entschlüsselung'),
            ),
            'sectamper' => array(
                'booking_name' => 'Anti-Tamper Shield',
                'title'        => 'Anti-Tamper Shield',
                'summary'      => 'Erkennt unautorisierte Änderungen an Dateien und Builds — reagiert sofort, nicht erst beim Audit.',
                'features'     => array('Tamper Detection', 'Echtzeit-Alarm', 'Integritäts-Trigger', 'Auto-Block Option'),
            ),
            'secruntime' => array(
                'booking_name' => 'Secure Runtime Mode',
                'title'        => 'Secure Runtime Mode',
                'summary'      => 'Sicherer Frontend-Betrieb mit reduzierter Datenoffenlegung — sensible Logik bleibt im Hintergrund.',
                'features'     => array('Runtime Härtung', 'Datenminimierung', 'DOM-Schutz', 'Sichere API-Aufrufe'),
            ),
            'secobfusc' => array(
                'booking_name' => 'Obfuscated Source Protection',
                'title'        => 'Obfuscated Source Protection',
                'summary'      => 'Professionelle Code-Obfuscation und Minification gegen Auslesen und Kopieren.',
                'features'     => array('Code Obfuscation', 'Minification', 'Dead-Code Injection', 'String-Verschlüsselung'),
            ),
            'sectoken' => array(
                'booking_name' => 'Token-Based Asset Access',
                'title'        => 'Token-Based Asset Access',
                'summary'      => 'Geschützte Assets nur über temporäre, signierte Tokens — kein dauerhafter Direktzugriff.',
                'features'     => array('Temporäre Tokens', 'Signierte URLs', 'Ablauf-Logik', 'Zugriffskontrolle'),
            ),
            'seclicense' => array(
                'booking_name' => 'Server-Side License Verification',
                'title'        => 'Server-Side License Verification',
                'summary'      => 'Serverseitige Lizenzprüfung — geschützte Funktionen erst nach validierter Freigabe.',
                'features'     => array('Server-Lizenzcheck', 'Feature-Gating', 'Domain-Bindung', 'Renewal-Logik'),
            ),
            'secintegrity' => array(
                'booking_name' => 'Integrity Check',
                'title'        => 'Integrity Check',
                'summary'      => 'Hash- und Checksum-Validierung — Dateiintegrität in Deploy und Runtime nachweisbar.',
                'features'     => array('Hash-Validierung', 'Checksum Monitoring', 'Build-Verifikation', 'Manipulations-Report'),
            ),
        );

        return $catalog;
    }

    /**
     * Map booking display names to internal keys for the widget.
     *
     * @return array<string, string>
     */
    public static function get_booking_name_map() {
        $map = array();
        foreach (self::get_service_catalog() as $key => $service) {
            $map[$service['booking_name']] = $key;
        }
        return $map;
    }

    /**
     * Resolve a service key from booking name or internal key.
     */
    public static function resolve_service_key($value) {
        if ($value === '') {
            return '';
        }
        $catalog = self::get_service_catalog();
        if (isset($catalog[$value])) {
            return $value;
        }
        $map = self::get_booking_name_map();
        if (isset($map[$value])) {
            return $map[$value];
        }
        foreach ($catalog as $key => $service) {
            if (strcasecmp($service['title'], $value) === 0 || strcasecmp($service['booking_name'], $value) === 0) {
                return $key;
            }
        }
        return '';
    }

    /**
     * Build compact services block for the system prompt.
     *
     * @param string[] $primary_keys Optional keys to list first.
     */
    public static function build_services_prompt_block($primary_keys = array()) {
        $catalog = self::get_service_catalog();
        $lines   = array();
        $ordered = array();

        foreach ($primary_keys as $key) {
            $key = sanitize_key($key);
            if (isset($catalog[$key]) && !in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }
        foreach (array_keys($catalog) as $key) {
            if (!in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        foreach ($ordered as $key) {
            $s = $catalog[$key];
            $feat = implode(', ', array_slice($s['features'], 0, 3));
            $lines[] = sprintf(
                '- %s (Buchung: „%s"): %s — u.a. %s',
                $s['title'],
                $s['booking_name'],
                $s['summary'],
                $feat
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Build the full system prompt from admin settings.
     *
     * @param array<string, mixed> $settings
     */
    public static function build_system_prompt($settings) {
        $company = self::get_company_context();
        $phone   = isset($settings['phone']) ? $settings['phone'] : '+43 681 20543638';
        $email   = isset($settings['email']) ? $settings['email'] : 'info@paxdesign.at';
        $style   = isset($settings['response_style']) ? $settings['response_style'] : '';
        $show_prices = !empty($settings['show_prices']);
        $auto_booking = !empty($settings['auto_booking']);
        $cta_text = isset($settings['cta_text']) ? $settings['cta_text'] : 'Kostenlose Erstberatung buchen';
        $primary_raw = isset($settings['primary_services']) ? $settings['primary_services'] : '';
        $price_hints = isset($settings['price_hints']) ? trim($settings['price_hints']) : '';

        $primary_keys = array();
        if ($primary_raw !== '') {
            foreach (preg_split('/[\s,;]+/', $primary_raw) as $token) {
                $token = trim($token);
                if ($token === '') {
                    continue;
                }
                $resolved = self::resolve_service_key($token);
                if ($resolved !== '') {
                    $primary_keys[] = $resolved;
                }
            }
        }
        if (empty($primary_keys)) {
            $primary_keys = array('website', 'webapp', 'aichatbot', 'aiautomation', 'crm', 'ecommerce', 'bookingsystem', 'maintenance');
        }

        $services_block = self::build_services_prompt_block($primary_keys);
        $process_lines  = implode("\n", array_map(function ($step, $i) {
            return ($i + 1) . '. ' . $step;
        }, $company['process'], array_keys($company['process'])));

        $style_block = $style !== '' ? $style : implode("\n", array(
            '- Antworte kurz: maximal 2–3 kurze Absätze oder 3–5 Bulletpoints.',
            '- Immer auf Deutsch, professionell, freundlich, beratend — nicht zu technisch.',
            '- Keine langen Leistungs- oder Preislisten am Anfang.',
            '- Stelle pro Antwort höchstens eine gezielte Rückfrage.',
            '- Qualifiziere Leads schrittweise: Projektart → Bestand/Neustart → Branche → Funktionen → Zeitrahmen → Budget (wenn passend) → Name → E-Mail → Telefon (optional).',
            '- Nicht alle Fragen auf einmal — natürlich im Gespräch.',
        ));

        $price_block = '';
        if ($show_prices && $price_hints !== '') {
            $price_block = "\n## Preishinweise (nur bei expliziter Nachfrage, nicht verbindlich)\n" . $price_hints . "\n";
        } elseif (!$show_prices) {
            $price_block = "\n## Preise\n- Keine festen Preislisten ausgeben.\n- Bei Preisfragen: „Die Kosten hängen vom Umfang ab. Nach ein paar kurzen Fragen kann PAXDesign besser einschätzen, welche Lösung passt.“\n- Verweise auf kostenlose Erstberatung statt konkreter Beträge.\n";
        }

        $booking_block = '';
        if ($auto_booking) {
            $booking_block = implode("\n", array(
                '',
                '## Terminbuchung & Aktionen',
                '- Wenn der Kunde Interesse zeigt oder einen Termin, Beratung, Kontakt oder ein Angebot möchte: biete aktiv an: „Soll ich Ihnen direkt einen Termin zur ' . $cta_text . ' öffnen?"',
                '- Bei Zustimmung (ja, bitte, gerne, ok, …) oder klarem Wunsch („Termin buchen", „Beratung buchen", „Kontakt aufnehmen", „Angebot") am ENDE der Antwort genau EINEN unsichtbaren Marker setzen:',
                '  [[BOOKING:BOOKING_NAME]] — BOOKING_NAME exakt wie in der Leistungsliste (z.B. Website, AI Chatbot, CRM System).',
                '- Bei allgemeiner Beratung ohne konkreten Service: [[BOOKING:Beratung]]',
                '- Marker erscheinen NUR am Ende, nie mitten im Text. Keine Erklärung der Marker.',
                '- Der Marker öffnet im Widget automatisch die Terminbuchung — der Kunde muss nichts suchen.',
            ));
        }

        return implode("\n", array(
            'Du bist der digitale Sales- und Beratungsassistent von ' . $company['name'] . ' (' . $company['url'] . '), einer österreichischen Agentur für Web, Apps, KI und digitale Systeme.',
            '',
            '## Unternehmen',
            $company['tagline'],
            $company['statement'],
            '',
            '## Deine Rolle',
            '- Du kennst alle PAXDesign-Leistungen präzise und berätst wie ein erfahrener Mitarbeiter — nicht generisch.',
            '- Du führst Besucher zum passenden Service und qualifizierst Leads für die Erstberatung.',
            '- Keine übertriebenen Versprechen, keine erfundenen Projektdetails.',
            '',
            '## Antwort-Stil',
            $style_block,
            '',
            '## Ablauf mit Kunden',
            $process_lines,
            '',
            '## Kontakt',
            '- Telefon: ' . $phone,
            '- E-Mail: ' . $email,
            '- Termine: über das Booking-Widget auf der Website (wird per Marker geöffnet)',
            $price_block,
            '## Leistungen (Quelle: paxdesign.at Leistungsseite — nutze exakt diese Begriffe)',
            $services_block,
            $booking_block,
            '',
            '## Live-Agent / Mitarbeiter',
            '- Wenn der Kunde einen echten Menschen, Mitarbeiter, Live Agent, Live Chat, Support-Mitarbeiter oder Ahmad sprechen möchte:',
            '  1. Frage NICHT sofort weiter — stelle zuerst EINE kurze Qualifizierungsfrage:',
            '     „Gerne. Damit ich Sie richtig weiterleiten kann: Worum geht es kurz — Website, AI Chatbot, Booking, Support oder ein anderes Thema?"',
            '  2. Nach der kurzen Antwort des Kunden: „Danke. Ich leite Sie jetzt an einen PAXDesign-Mitarbeiter weiter."',
            '- Während auf einen Mitarbeiter gewartet wird: keine langen KI-Antworten, keine Sales-Pitches.',
            '',
            '## Beispiel (Website-Anfrage)',
            'Sehr gern. PAXDesign erstellt moderne, schnelle und sichere Websites für Unternehmen, Selbstständige und Projekte.',
            'Wir übernehmen Design, Entwicklung, SEO-Grundlagen, Performance und auf Wunsch auch Booking, CRM oder AI-Integration.',
            'Starten Sie komplett neu oder haben Sie bereits eine bestehende Website?',
        ));
    }

    /**
     * Compact account snapshot for authenticated customer AI replies.
     *
     * @param int    $user_id
     * @param string $session_id
     * @param string $focus_reference Optional Cybercrime Support reference for this chat.
     * @return string
     */
    public static function build_customer_account_context_block($user_id, $session_id = '', $focus_reference = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return '';
        }

        if (class_exists('PAXdesign_Customer_Orders')) {
            PAXdesign_Customer_Orders::link_bookings_for_user($user_id);
        }

        $user = get_user_by('id', $user_id);
        $lines = array(
            '## Customer account context (private — only for this logged-in customer)',
            '- Use ONLY the facts below for account, project, request, appointment, invoice/file, and notification questions.',
            '- If the customer asks about their project, order, invoice, appointment, or files, answer from this data.',
            '- If nothing relevant exists below, say honestly that nothing is on file yet and offer next steps.',
            '- Never invent statuses, dates, amounts, documents, or appointments.',
        );

        if ($user instanceof WP_User) {
            $lines[] = '- Customer name: ' . sanitize_text_field($user->display_name);
        }

        if (class_exists('PAXdesign_Customer_Projects')) {
            $projects = PAXdesign_Customer_Projects::list_for_user($user_id);
            if (empty($projects)) {
                $lines[] = '- Projects: none on file';
            } else {
                $lines[] = '- Projects (' . count($projects) . '):';
                foreach (array_slice($projects, 0, 5) as $project) {
                    $summary = sprintf(
                        '  • %s — %s | status: %s | progress: %d%%',
                        (string) ($project['ref'] ?? ''),
                        (string) ($project['title'] ?? ''),
                        (string) ($project['status'] ?? ''),
                        (int) ($project['progress'] ?? 0)
                    );
                    if (!empty($project['expected_completion'])) {
                        $summary .= ' | expected completion: ' . (string) $project['expected_completion'];
                    }
                    $lines[] = $summary;
                }
            }
        }

        if (class_exists('PAXdesign_Customer_Orders')) {
            $orders = PAXdesign_Customer_Orders::list_for_user($user_id);
            if (empty($orders)) {
                $lines[] = '- Service requests / orders: none on file';
            } else {
                $lines[] = '- Service requests / orders (' . count($orders) . '):';
                foreach (array_slice($orders, 0, 5) as $order) {
                    $summary = sprintf(
                        '  • %s — %s | status: %s',
                        (string) ($order['ref'] ?? ''),
                        (string) ($order['service_label'] ?? ''),
                        (string) ($order['status'] ?? '')
                    );
                    if (!empty($order['expected_delivery'])) {
                        $summary .= ' | expected delivery: ' . (string) $order['expected_delivery'];
                    }
                    if (!empty($order['booking_id'])) {
                        $summary .= ' | linked appointment booking_id: ' . (int) $order['booking_id'];
                    }
                    $lines[] = $summary;
                }
            }

            $appointments = PAXdesign_Customer_Orders::upcoming_bookings_for_user($user_id, 5);
            if (empty($appointments)) {
                $lines[] = '- Upcoming appointments: none scheduled';
            } else {
                $lines[] = '- Upcoming appointments (' . count($appointments) . '):';
                foreach ($appointments as $booking) {
                    $lines[] = sprintf(
                        '  • %s on %s %s | status: %s',
                        (string) ($booking['service'] ?? 'Appointment'),
                        (string) ($booking['booking_date'] ?? ''),
                        (string) ($booking['booking_time'] ?? ''),
                        (string) ($booking['status'] ?? 'pending')
                    );
                }
            }

            $files = PAXdesign_Customer_Orders::library_for_user($user_id, 8);
            if (empty($files)) {
                $lines[] = '- Shared files / invoices: none available in the customer portal yet';
            } else {
                $lines[] = '- Shared files / invoices (' . count($files) . ' recent):';
                foreach (array_slice($files, 0, 6) as $file) {
                    $label = (string) ($file['file_name'] ?? 'file');
                    $kind = (string) ($file['kind'] ?? 'file');
                    $parent = (string) ($file['parent_title'] ?? '');
                    $lines[] = '  • ' . $label . ' (' . $kind . ')' . ($parent !== '' ? ' — ' . $parent : '');
                }
            }
        }

        if (class_exists('PAXdesign_Customer_Notifications')) {
            $unread = PAXdesign_Customer_Notifications::unread_count($user_id);
            $lines[] = '- Unread portal notifications: ' . (int) $unread;
        }

        if (class_exists('PAXdesign_Cybercrime_Intake')) {
            $lines[] = PAXdesign_Cybercrime_Intake::build_account_context_block($user_id, $focus_reference);
        }

        if ($session_id !== '' && class_exists('PAXdesign_Customer_Projects')) {
            global $wpdb;
            $linked = $wpdb->get_row($wpdb->prepare(
                'SELECT project_ref, title, status, progress FROM ' . PAXdesign_Customer_DB::table('projects') . ' WHERE customer_user_id = %d AND chat_session_id = %s LIMIT 1',
                $user_id,
                sanitize_text_field($session_id)
            ), ARRAY_A);
            if ($linked) {
                $lines[] = '- Project linked to this chat: ' . (string) $linked['project_ref'] . ' — ' . (string) $linked['title'] . ' (' . (int) $linked['progress'] . '%, status ' . (string) $linked['status'] . ')';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Cybercrime Support page context for the global Live Chat assistant.
     *
     * @param string $language de|en|ar
     * @param string $focus_reference Optional active Cybercrime Support reference.
     * @return string
     */
    public static function build_cybercrime_support_context_block($language = '', $focus_reference = '') {
        $language = sanitize_key((string) $language);
        if ($language === '') {
            $language = 'ar';
        }

        $lines = array(
            '## Page context: Cybercrime Support (/cybercrime-support/)',
            'The visitor is on the Cybercrime Support reporting portal or opened Live Chat from that page.',
            'Switch from sales mode to confidential cyber-incident case management.',
            '',
            '## Your role on this page',
            '- You are an intelligent Cybercrime Support case-management assistant. Chat and the case page are TWO interfaces on the SAME CCS case.',
            '- When the authenticated customer tells you facts, the backend already saved them onto their existing CCS case before you reply. Treat the account/ticket context block as the live case record.',
            '- Never keep facts only in this conversation. Never create a second case. Never restart the questionnaire.',
            '- Extract facts from natural language and save them into the existing structured CCS fields (incident type, date, platforms, financial loss). Never copy the customer’s entire message onto the case page.',
            '- The case page must show clean structured fields only — never a huge pasted chat paragraph.',
            '- After facts are saved, continue the workflow: identify what is still missing, execute the next required action, and ask only for genuinely missing required information.',
            '- Do not ask for information that is already listed in the case context. Do not ask the customer to repeat information that has already been understood.',
            '- Identity documents and evidence: tell the customer to tap the + button next to the message box. Uploads attach to this same CCS case and appear in Evidence/Documents. Do not send them to a separate upload flow.',
            '- If the customer corrects a fact, the backend updates that same field. Confirm the correction using the updated case context.',
            '- Files uploaded with the + button belong to this same CCS case and appear in Evidence/Documents. Do not send the customer to a separate upload workflow.',
            '- The customer can open the Cybercrime Support case page at any time and must already see what you saved (reference, status, timeline, platforms, date, description, files).',
            '- For authenticated customers, never ask them to re-identify, re-enter account details, or prove who they are.',
            '- Answer questions like "What is my request number?", "Why did I submit this report?", "What is the current status?", and "What is still required?" from the case facts only.',
            '- Never invent a reference number, status change, or team message that is not listed in the case context.',
            '- Automated document checks are preliminary quality checks only — never present them as legal verification.',
            '- If you start checking, processing, uploading, or reviewing anything, the backend already created a tracked operation on this same CCS case. Never say “please wait” unless that tracked operation is running.',
            '- If a tracked operation is running, tell the customer the files are still being checked and that results will appear in this same conversation. Do not restart.',
            '- A follow-up such as “?” is a status check on THIS same case and conversation — never greet as a new visitor.',
            '- If something is uncertain, say an administrator will review it.',
            '- Do NOT pitch unrelated services unless the visitor explicitly asks.',
            '- Treat all details as sensitive; never ask for passwords, OTP codes, seed phrases, or full payment card numbers.',
            '- Guests cannot use this case chat. If they are not signed in, tell them to sign in through the PAXDesign account page. Do not collect case facts from a guest.',
            '',
            '## Missing-information policy',
            '- Read the case context first: saved fields, missing fields, next action, files, and status.',
            '- If a field is already saved, never ask it again.',
            '- If several fields are missing, ask the single most important missing item next.',
            '- After hundreds of messages, continue the SAME CCS case. Never restart from question 1.',
            '',
            '## Reporting guidance',
            '- Identity documents and evidence belong on this same CCS reference. Ask the customer to use the + button next to the message box.',
            '- If files failed preliminary checks, quote the exact correction and keep the same reference.',
            '- Give calm containment advice when relevant (secure accounts, change passwords from a clean device, enable 2FA, contact bank/platform).',
            '- Offer a live PAXDesign expert for urgent or complex cases.',
            '',
            '## Language',
        );

        if ($language === 'de') {
            $lines[] = '- The Cybercrime Support page defaults to Arabic, but this visitor may use German.';
            $lines[] = '- Reply in German if they write in German; otherwise match their language (Arabic/English/German).';
        } elseif ($language === 'en') {
            $lines[] = '- Default page language is Arabic; match the visitor\'s language from their messages.';
        } else {
            $lines[] = '- لغة الصفحة الافتراضية العربية. فضّل العربية حتى يكتب الزائر بلغة أخرى، ثم طابق لغته.';
            $lines[] = '- Default page language is Arabic; prefer Arabic until the visitor writes in another language, then match their language.';
        }

        $lines[] = '';
        $lines[] = '## Tone';
        $lines[] = '- Empathetic, precise, no blame. Short paragraphs. One focused question per turn when gathering facts.';

        $focus_reference = sanitize_text_field((string) $focus_reference);
        if ($focus_reference !== '') {
            $lines[] = '';
            $lines[] = '## Active report focus';
            $lines[] = '- The customer opened chat from Cybercrime Support about report **' . $focus_reference . '**.';
            $lines[] = '- This is the live CCS case. Chat writes into it; the case page displays the same record.';
            $lines[] = '- Prioritize facts for this reference from the account context block.';
            $lines[] = '- When they ask about status, reference number, reason, or updates, answer from that report data only — do not ask them to repeat details already on file.';
            $lines[] = '- Official staff/customer timeline messages are separate from this AI conversation, but case fields you collect are stored on the official case.';
            if (class_exists('PAXdesign_Cybercrime_Tickets')) {
                $ticket_block = PAXdesign_Cybercrime_Tickets::build_ai_context_block($focus_reference);
                if ($ticket_block !== '') {
                    $lines[] = '';
                    $lines[] = $ticket_block;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Force the assistant to reply in the customer's language.
     *
     * @param string $prompt
     * @param string $lang de|en|ar
     * @return string
     */
    public static function apply_customer_language($prompt, $lang) {
        $lang = sanitize_key((string) $lang);
        if ($lang === '') {
            $lang = 'de';
        }
        switch ($lang) {
            case 'en':
                return $prompt . "\n\n## Language\n- Detect the customer's language from their latest message and ALWAYS reply in that same language (German, English, or Arabic).\n- If they write in English, reply in English.\n- If they write in Arabic, reply in Arabic.\n- If they write in German, reply in German.\n- Match the customer's tone and keep answers concise.";
            case 'ar':
                return $prompt . "\n\n## اللغة\n- حدّد لغة العميل من رسالته الأخيرة ورد دائماً بنفس اللغة (العربية أو الإنجليزية أو الألمانية).\n- إذا كتب بالعربية فأجب بالعربية.\n- إذا كتب بالإنجليزية فأجب بالإنجليزية.\n- إذا كتب بالألمانية فأجب بالألمانية.\n- استخدم أسلوباً مهنياً وواضحاً وموجزاً.";
            default:
                return $prompt . "\n\n## Sprache\n- Erkenne die Sprache des Kunden anhand der letzten Nachricht und antworte IMMER in derselben Sprache (Deutsch, Englisch oder Arabisch).\n- Schreibt der Kunde auf Deutsch, antworte auf Deutsch.\n- Schreibt der Kunde auf Englisch, antworte auf Englisch.\n- Schreibt der Kunde auf Arabisch, antworte auf Arabisch.\n- Professionell, freundlich und präzise.";
        }
    }
}
