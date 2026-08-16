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
            '- Read the customer\'s COMPLETE latest message and the conversation so far. Identify the actual intent before answering.',
            '- Give a direct, factual answer to what they actually asked. Never echo or rephrase the question.',
            '- If they ask about a submitted request, status, appointment, invoice, project, or report, use account context. Never ask "What is your request?" when that data is already listed.',
            '- Reply in the SAME language as the latest customer message (German, English, or Arabic). Never force German if they wrote in another language.',
            '- Keep answers short: at most 2–3 short paragraphs or 3–5 bullets.',
            '- Professional, friendly, advisory — not overly technical.',
            '- At most one focused follow-up question per reply, and only if a needed fact is truly missing.',
            '- Qualify new sales leads step by step. Never dump every question at once. Do not treat an existing-account question as a new lead.',
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
            '- Du bist ein echter Kundenassistent: zuerst die aktuelle Frage verstehen und mit echten Daten beantworten, dann erst beraten.',
            '- Du kennst alle PAXDesign-Leistungen präzise und berätst wie ein erfahrener Mitarbeiter — nicht generisch.',
            '- Bei eingeloggten Kunden: Anfragen, Status, Termine, Dateien und Projekte aus dem Account-Kontext beantworten. Nicht nach der Anfrage fragen, wenn sie bereits vorliegt.',
            '- Du führst neue Besucher zum passenden Service und qualifizierst Leads für die Erstberatung.',
            '- Keine übertriebenen Versprechen, keine erfundenen Projektdetails, Statuswerte oder Referenznummern.',
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
            '- Wenn der Kunde nach einer bereits eingereichten Anfrage, dem Status, einem Termin, einer Rechnung oder einem Projekt fragt: beantworte das aus dem Account-Kontext. Das ist KEIN Live-Agent-Wunsch und keine Qualifizierungsfrage.',
            '- Wenn der Kunde ausdrücklich einen echten Menschen, Mitarbeiter, Live Agent, Live Chat, Support-Mitarbeiter oder Ahmad sprechen möchte:',
            '  1. Wenn das Thema aus dem Account-Kontext oder der Nachricht schon klar ist, leite direkt weiter — frage nicht „Worum geht es?"',
            '  2. Nur wenn wirklich kein Thema bekannt ist, stelle EINE kurze Qualifizierungsfrage.',
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
            '- AUTHENTICATION: this customer IS already logged in (WordPress session). NEVER ask them to sign in, create an account, or log in again.',
            '- Use ONLY the facts below for account, project, request, appointment, invoice/file, notification, and Cybercrime questions.',
            '- If they ask "What is the request I submitted?" or "What is the status of my request?", the items below ARE that request. Answer with the description, reference, status, dates, and next step.',
            '- Never ask them to describe or repeat a request that is listed here.',
            '- If nothing relevant exists below, say honestly that nothing is on file yet and offer next steps.',
            '- Never invent statuses, dates, amounts, documents, appointments, or reference numbers.',
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
                        '  • %s — %s | status: %s | progress: %d%% | updated: %s',
                        (string) ($project['ref'] ?? ''),
                        (string) ($project['title'] ?? ''),
                        (string) ($project['status'] ?? ''),
                        (int) ($project['progress'] ?? 0),
                        (string) ($project['updated_at'] ?? '')
                    );
                    if (!empty($project['expected_completion'])) {
                        $summary .= ' | expected completion: ' . (string) $project['expected_completion'];
                    }
                    $lines[] = $summary;
                    if (!empty($project['description'])) {
                        $lines[] = '    summary: ' . self::clip_context_text($project['description'], 220);
                    }
                    $lines[] = '    next step: ' . self::next_step_for_status((string) ($project['status'] ?? ''), 'project');
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
                        '  • %s — %s | status: %s | submitted: %s',
                        (string) ($order['ref'] ?? ''),
                        (string) ($order['service_label'] ?? ''),
                        (string) ($order['status'] ?? ''),
                        (string) ($order['created_at'] ?? '')
                    );
                    if (!empty($order['expected_delivery'])) {
                        $summary .= ' | expected delivery: ' . (string) $order['expected_delivery'];
                    }
                    if (!empty($order['booking_id'])) {
                        $summary .= ' | linked appointment booking_id: ' . (int) $order['booking_id'];
                    }
                    $lines[] = $summary;
                    if (!empty($order['description'])) {
                        $lines[] = '    request details: ' . self::clip_context_text($order['description'], 240);
                    } else {
                        $lines[] = '    request details: (no extra description on file; the service above is the submitted request)';
                    }
                    $note = self::latest_customer_order_note($user_id, (int) ($order['id'] ?? 0));
                    if ($note !== '') {
                        $lines[] = '    latest customer-visible update: ' . $note;
                    }
                    $lines[] = '    next step: ' . self::next_step_for_status((string) ($order['status'] ?? ''), 'order');
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
                    if (!empty($booking['message'])) {
                        $lines[] = '    booking request: ' . self::clip_context_text($booking['message'], 200);
                    }
                }
            }

            $recent_bookings = self::list_recent_bookings_for_user($user_id, 8);
            if (empty($recent_bookings)) {
                $lines[] = '- Submitted booking requests: none on file for this account email';
            } else {
                $lines[] = '- Submitted booking requests (' . count($recent_bookings) . ' recent, including past dates):';
                foreach ($recent_bookings as $booking) {
                    $lines[] = sprintf(
                        '  • %s on %s %s | status: %s | submitted: %s',
                        (string) ($booking['service'] ?? 'Appointment'),
                        (string) ($booking['booking_date'] ?? ''),
                        (string) ($booking['booking_time'] ?? ''),
                        (string) ($booking['status'] ?? 'pending'),
                        (string) ($booking['created_at'] ?? '')
                    );
                    if (!empty($booking['message'])) {
                        $lines[] = '    request details: ' . self::clip_context_text($booking['message'], 200);
                    }
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
            if (method_exists('PAXdesign_Customer_Notifications', 'list_for_user')) {
                $recent_notes = PAXdesign_Customer_Notifications::list_for_user($user_id, false, 5);
                if (!empty($recent_notes)) {
                    $lines[] = '- Recent portal notifications:';
                    foreach (array_slice($recent_notes, 0, 5) as $note) {
                        $lines[] = '  • ' . (string) ($note['created_at'] ?? '') . ': '
                            . (string) ($note['title'] ?? '') . ' — '
                            . self::clip_context_text((string) ($note['body'] ?? ''), 140);
                    }
                }
            }
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

        $has_items = (bool) preg_match('/^  • /m', implode("\n", $lines));
        if ($has_items) {
            $lines[] = '- When the customer says "my request" / "the request I submitted", use the most recent service request, booking request, project, or Cybercrime report above. Do not ask them what it was.';
        } else {
            $lines[] = '- No submitted request, booking, project, or report is on file for this account yet.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param mixed $text
     * @param int   $max
     * @return string
     */
    private static function clip_context_text($text, $max = 220) {
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $text)));
        if ($text === '') {
            return '';
        }
        $max = max(40, (int) $max);
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($len <= $max) {
            return $text;
        }
        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $max - 3) : substr($text, 0, $max - 3);
        return rtrim($cut) . '...';
    }

    /**
     * @param string $status
     * @param string $kind order|project
     * @return string
     */
    private static function next_step_for_status($status, $kind = 'order') {
        $status = sanitize_key((string) $status);
        switch ($status) {
            case 'completed':
            case 'done':
                return 'This item is completed. No action needed unless the customer has a new question.';
            case 'cancelled':
            case 'canceled':
            case 'rejected':
                return 'This item is closed. Offer to explain why from listed updates, or help start a new request.';
            case 'in_progress':
            case 'processing':
            case 'active':
                return 'The PAXDesign team is working on it. The customer can wait; they do not need to resubmit.';
            case 'confirmed':
                return 'The appointment is confirmed. The customer should attend at the listed date and time.';
            case 'planning':
                return 'The project is in planning. The team will follow up; the customer does not need to resubmit.';
            default:
                return $kind === 'project'
                    ? 'The team has this project on file and will follow up. The customer does not need to resubmit.'
                    : 'The team has received this request and will follow up. The customer does not need to resubmit.';
        }
    }

    /**
     * @param int $user_id
     * @param int $order_id
     * @return string
     */
    private static function latest_customer_order_note($user_id, $order_id) {
        $user_id = absint($user_id);
        $order_id = absint($order_id);
        if ($user_id <= 0 || $order_id <= 0 || !class_exists('PAXdesign_Customer_Orders')) {
            return '';
        }
        if (!method_exists('PAXdesign_Customer_Orders', 'get_for_user')) {
            return '';
        }
        $detail = PAXdesign_Customer_Orders::get_for_user($user_id, $order_id);
        if (!is_array($detail) || empty($detail['notes']) || !is_array($detail['notes'])) {
            return '';
        }
        $note = $detail['notes'][0];
        $body = is_array($note) ? (string) ($note['body'] ?? '') : '';
        if ($body === '') {
            return '';
        }
        $when = is_array($note) ? (string) ($note['created_at'] ?? '') : '';
        return trim($when . ' ' . self::clip_context_text($body, 180));
    }

    /**
     * Recent booking rows for the customer's email, including past dates.
     *
     * @param int $user_id
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    private static function list_recent_bookings_for_user($user_id, $limit = 8) {
        global $wpdb;
        $user = get_user_by('id', absint($user_id));
        if (!$user) {
            return array();
        }
        $bookings = $wpdb->prefix . 'paxdesign_bookings';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bookings)) !== $bookings) {
            return array();
        }
        $limit = max(1, min(12, (int) $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, service, booking_date, booking_time, status, message, created_at
             FROM $bookings
             WHERE customer_email = %s
             ORDER BY created_at DESC
             LIMIT %d",
            $user->user_email,
            $limit
        ), ARRAY_A);

        return is_array($rows) ? $rows : array();
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
            'Switch from sales mode to confidential cyber-incident support.',
            '',
            '## Your role on this page',
            '- Understand exactly what the customer needs from their complete latest message. Answer that need. Do not echo their question.',
            '- Help with Cybercrime Support: explaining the service, guiding the website report form, and answering status questions.',
            '- For authenticated customers, use the Cybercrime Support report facts from the account context (reference, category, status, dates, summary).',
            '- If they are already logged in, NEVER ask them to sign in.',
            '- Answer questions like "What is my request number?", "What is the request I submitted?", "Why did I submit this report?", "What is the current status?" from those report facts only.',
            '- If a submitted report is already in account context, do not ask what the request was and do not restart the form.',
            '- Never invent a reference number, status change, or team message that is not listed in the account context.',
            '- Do NOT pitch unrelated services unless the visitor explicitly asks.',
            '- Treat all details as sensitive; never ask for passwords, OTP codes, seed phrases, or full payment card numbers.',
            '',
            '## One-step reporting help (website form is the source of truth)',
            '- The Cybercrime report on the page has four steps: 1 Identity → 2 Incident → 3 Evidence → 4 Review / Submit.',
            '- If the customer needs help completing a report, give ONE clear step at a time. Never list all four steps or a long checklist in one reply.',
            '- Keep the instruction to one short sentence (or two at most). Then wait for them to complete that step.',
            '- After they confirm a step is done, guide them to the NEXT missing step only, until the report is submitted.',
            '- If they ask a question, answer the question first. Do not continue a previous prompt until the question is answered.',
            '- If they already have a submitted report, do not restart the form. Explain the current status and the single next action.',
            '- Give calm containment advice only when relevant (secure accounts, change passwords from a clean device, enable 2FA).',
            '- Offer a live PAXDesign expert for urgent or complex cases.',
            '',
            '## Language',
        );

        if ($language === 'de') {
            $lines[] = '- Reply in German if they write in German; otherwise match their language (Arabic/English/German).';
            $lines[] = '- Speak status names in German (or Arabic for مرفوض).';
        } elseif ($language === 'en') {
            $lines[] = '- Match the visitor\'s language from their messages.';
            $lines[] = '- If they write in Arabic, reply fully in Arabic. Use Arabic status words (مرفوض, قيد المراجعة) — never quote English labels.';
        } else {
            $lines[] = '- لغة الصفحة الافتراضية العربية. فضّل العربية حتى يكتب الزائر بلغة أخرى، ثم طابق لغته.';
            $lines[] = '- Default page language is Arabic; prefer Arabic until the visitor writes in another language, then match their language.';
            $lines[] = '- When Arabic is selected, write every status, button name, and instruction in Arabic. Rejected is always مرفوض.';
        }

        $lines[] = '';
        $lines[] = '## Tone';
        $lines[] = '- Empathetic, precise, no blame. Short paragraphs. One focused question per turn when gathering facts.';

        $focus_reference = sanitize_text_field((string) $focus_reference);
        if ($focus_reference !== '') {
            $lines[] = '';
            $lines[] = '## Active report focus';
            $lines[] = '- The customer opened chat from Cybercrime Support about report **' . $focus_reference . '**.';
            $lines[] = '- Prioritize facts for this reference from the account context block (reference number, category, reason, status, dates, updates, attachments).';
            $lines[] = '- When they ask about status, reference number, reason, or updates, answer from that report data only — do not ask them to repeat details already on file.';
            $lines[] = '- AI assistant chat (Support widget) is a SEPARATE channel. Never treat AI replies as official support messages or ticket updates.';
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
