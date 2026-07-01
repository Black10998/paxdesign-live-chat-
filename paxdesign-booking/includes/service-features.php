<?php
/**
 * Feature lists and categories for booking services.
 * Mirrors the PAXdesign pricing page card content (DE).
 */
if (!defined('ABSPATH')) {
    exit;
}

return array(
    'features' => array(
        'website' => array('Responsive Design', 'SEO-Optimierung', 'CMS Integration', 'SSL-Zertifikat'),
        'webapp' => array('Custom Development', 'API Integration', 'User Management', 'Cloud Hosting'),
        'android' => array('Native Development', 'Play Store Veröffentlichung', 'Push Notifications', 'Offline-Funktionalität'),
        'ios' => array('Swift Development', 'App Store Veröffentlichung', 'iCloud Integration', 'Face ID / Touch ID'),
        'crossplatform' => array('Beide Plattformen', 'Gemeinsame Codebasis', 'Alle Store-Features', 'Synchronisation'),
        'androidtv' => array('TV-optimierte UI', 'Fernbedienung Support', '4K Streaming', 'Chromecast Integration'),
        'security' => array('Penetration Testing', 'Security Audit', 'DSGVO-Konformität', 'Verschlüsselung'),
        'backend' => array('REST/GraphQL API', 'Datenbank Design', 'Microservices', 'Load Balancing'),
        'devops' => array('Cloud Setup', 'CI/CD Pipeline', 'Monitoring', 'Backup-Strategie'),
        'enterprise' => array('Alle Leistungen', 'Dediziertes Team', '24/7 Support', 'SLA Garantie'),
        'aiautomation' => array('Prozessautomatisierung', 'AI Chatbots', 'API-Anbindung', 'Intelligente Reports'),
        'aichatbot' => array('Automatische Antworten', 'Mehrsprachiger Support', 'WhatsApp-Anbindung', 'Training mit Ihren Daten'),
        'ecommerce' => array('WooCommerce / Shopify', 'Zahlungsgateways', 'Produktverwaltung', 'Conversion-Optimierung'),
        'maintenance' => array('Regelmäßige Updates', 'Backup', 'Sicherheitsmonitoring', 'Technischer Support'),
        'pagespeed' => array('PageSpeed Optimization', 'Bildoptimierung', 'Code-Reduktion', 'Caching / CDN'),
        'uiux' => array('Interface Design', 'User Experience', 'Wireframes', 'Prototypen'),
        'branding' => array('Logo-Design', 'Farben & Identität', 'Style Guide', 'Marketing-Materialien'),
        'crm' => array('Kundenverwaltung', 'Auftragsverfolgung', 'Benutzerrechte', 'Management-Reports'),
        'bookingsystem' => array('Terminkalender', 'Online-Zahlung', 'E-Mail/SMS-Benachrichtigungen', 'Admin-Dashboard'),
        'pwa' => array('PWA', 'Push-Benachrichtigungen', 'Offline-Funktion', 'Installation auf dem Handy'),
        'analytics' => array('Dashboard', 'Google Analytics', 'Individuelle Reports', 'KPIs'),
        'gdpr' => array('Cookie Banner', 'Privacy Policy', 'Consent Mode', 'GDPR Setup'),
        'secflash' => array('Anti-Copy Schutz', 'Flash-Härtung', 'Manipulationserkennung', 'Deployment-Sicherheit'),
        'seclayers' => array('Mehrschicht-Verschlüsselung', 'JS/CSS Schutz', 'Schlüsselmanagement', 'Runtime-Entschlüsselung'),
        'sectamper' => array('Tamper Detection', 'Echtzeit-Alarm', 'Integritäts-Trigger', 'Auto-Block Option'),
        'secruntime' => array('Runtime Härtung', 'Datenminimierung', 'DOM-Schutz', 'Sichere API-Aufrufe'),
        'secobfusc' => array('Code Obfuscation', 'Minification', 'Dead-Code Injection', 'String-Verschlüsselung'),
        'sectoken' => array('Temporäre Tokens', 'Signierte URLs', 'Ablauf-Logik', 'Zugriffskontrolle'),
        'seclicense' => array('Server-Lizenzcheck', 'Feature-Gating', 'Domain-Bindung', 'Renewal-Logik'),
        'secintegrity' => array('Hash-Validierung', 'Checksum Monitoring', 'Build-Verifikation', 'Manipulations-Report'),
    ),
    'categories' => array(
        'secflash' => 'Code & Asset Protection',
        'seclayers' => 'Code & Asset Protection',
        'sectamper' => 'Code & Asset Protection',
        'secruntime' => 'Code & Asset Protection',
        'secobfusc' => 'Code & Asset Protection',
        'sectoken' => 'Code & Asset Protection',
        'seclicense' => 'Code & Asset Protection',
        'secintegrity' => 'Code & Asset Protection',
    ),
);
