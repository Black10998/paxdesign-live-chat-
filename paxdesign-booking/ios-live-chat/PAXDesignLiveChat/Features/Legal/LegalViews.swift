import SwiftUI

struct LegalDocumentView: View {
    let title: String
    let sections: [(String, String)]
    var webLinks: [LegalWebLink] = []

    struct LegalWebLink: Identifiable {
        let id = UUID()
        let title: String
        let url: URL
    }

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                if !webLinks.isEmpty {
                    VStack(alignment: .leading, spacing: 10) {
                        Text(L10n.CommonOnline)
                            .font(.headline)
                        ForEach(webLinks) { link in
                            Link(destination: link.url) {
                                HStack {
                                    Text(link.title)
                                        .font(.subheadline.weight(.medium))
                                    Spacer()
                                    Image(systemName: "arrow.up.right")
                                        .font(.caption)
                                        .foregroundStyle(PAXTheme.textTertiary)
                                }
                                .padding(12)
                                .paxGlassCardStyle(cornerRadius: 12, fillOpacity: 0.8, borderOpacity: 0.44, shadowOpacity: 0.1)
                            }
                        }
                    }
                }

                ForEach(Array(sections.enumerated()), id: \.offset) { _, section in
                    VStack(alignment: .leading, spacing: 8) {
                        Text(section.0)
                            .font(.headline)
                        Text(section.1)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .fixedSize(horizontal: false, vertical: true)
                    }
                    .padding(14)
                    .paxGlassCardStyle(cornerRadius: 14, fillOpacity: 0.8, borderOpacity: 0.42, shadowOpacity: 0.12)
                }
            }
            .padding(20)
        }
        .paxScreenBackground()
        .navigationTitle(title)
        .navigationBarTitleDisplayMode(.inline)
    }
}

struct SecurityView: View {
    var body: some View {
        List {
            Section {
                NavigationLink {
                    AppLockSettingsView()
                } label: {
                    Label("App-Sperre", systemImage: "lock.shield.fill")
                }
            } footer: {
                Text("Face ID, Touch ID oder App-PIN mit automatischer Sperre nach Inaktivität.")
            }

            Section("Hinweise") {
                Text("Transportverschlüsselung: Alle API-Anfragen erfolgen ausschließlich über HTTPS (TLS).")
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
                Text("Anmeldedaten werden im iOS-Schlüsselbund gespeichert. App-PINs werden gehasht im Schlüsselbund abgelegt.")
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
                Text("Kein Tracking. Keine Third-Party-Analytics.")
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .navigationTitle(L10n.LegalSecurity)
        .navigationBarTitleDisplayMode(.inline)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
    }
}

struct PrivacyPolicyView: View {
    var body: some View {
        LegalDocumentView(
            title: L10n.LegalPrivacy,
            sections: [
                ("Verantwortlicher", "PAXdesign / PrimoJob GmbH\nFranzensbrückenstraße 14, 1020 Wien\nhttps://paxdesign.at"),
                ("Zweck", "Die App dient autorisierten Mitarbeitern zur Bearbeitung von Live-Chat-Anfragen. Es werden nur Daten verarbeitet, die für den Support erforderlich sind."),
                ("Verarbeitete Daten", "Anmeldedaten (WordPress Application Password, lokal im Schlüsselbund), Chat-Inhalte, Metadaten (Zeitstempel, Session-ID), optional Profilbild lokal auf dem Gerät, Push-Token für Benachrichtigungen."),
                ("Speicherung", "Chat-Inhalte werden auf dem WordPress-Server des Auftraggebers gespeichert. Anmeldedaten verbleiben verschlüsselt im iOS-Schlüsselbund."),
                ("Übermittlung", "Alle API-Kommunikation erfolgt über HTTPS (TLS). Es wird keine Ende-zu-Ende-Verschlüsselung zusätzlich zur Transportverschlüsselung eingesetzt."),
                ("Rechtsgrundlage (DSGVO)", "Art. 6 Abs. 1 lit. b (Vertrag/Support) und lit. f (berechtigtes Interesse am Kundenservice)."),
                ("Betroffenenrechte", "Auskunft, Berichtigung, Löschung, Einschränkung, Widerspruch und Datenübertragbarkeit gemäß DSGVO. Anfragen an den Verantwortlichen."),
                ("Aufbewahrung", "Entspricht den Einstellungen des WordPress-Systems des Auftraggebers."),
            ],
            webLinks: [
                .init(title: "Vollständige Datenschutzerklärung", url: PAXLegalLinks.privacyPolicy)
            ]
        )
    }
}

struct TermsOfServiceView: View {
    var body: some View {
        LegalDocumentView(
            title: L10n.LegalTerms,
            sections: [
                ("Geltungsbereich", "Diese App ist ausschließlich für autorisierte PAXdesign-Mitarbeiter bestimmt."),
                ("Zugang", "Der Zugang erfordert gültige WordPress-Administrator-Anmeldedaten und ein Application Password."),
                ("Pflichten", "Chat-Inhalte vertraulich behandeln. Keine Weitergabe von Zugangsdaten. Gerät mit Code schützen."),
                ("Verfügbarkeit", "Der Dienst hängt von der Erreichbarkeit des WordPress-Backends ab."),
                ("Haftung", "Die App wird im Rahmen des internen Support-Betriebs bereitgestellt. Schäden durch unsachgemäße Nutzung sind ausgeschlossen, soweit gesetzlich zulässig."),
            ],
            webLinks: [
                .init(title: "Impressum & Anbieterkennzeichnung", url: PAXLegalLinks.impressum)
            ]
        )
    }
}

struct DataHandlingView: View {
    var body: some View {
        LegalDocumentView(title: "Datenverarbeitung", sections: [
            ("Lokale Speicherung", "Application Password im iOS-Schlüsselbund (kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly). Optionales Profilbild in UserDefaults."),
            ("Netzwerk", "REST-API über HTTPS. Keine Third-Party-Analytics in der App."),
            ("Push (APNs)", "Device-Token wird an den WordPress-Server übermittelt, um Live-Anfragen zu signalisieren."),
            ("Löschung", "Abmeldung entfernt lokale Zugangsdaten. Serverseitige Löschung über WordPress-Administration."),
            ("App Store Privacy", "Kein Tracking. Kontaktinformationen und Benutzerinhalte werden für App-Funktionalität verarbeitet."),
        ])
    }
}

struct AboutView: View {
    var body: some View {
        ScrollView {
            VStack(spacing: 0) {
                aboutHero
                    .padding(.top, 32)
                    .padding(.bottom, 28)

                VStack(spacing: 0) {
                    aboutInfoCard
                    featureHighlights
                        .padding(.top, 20)
                }
                .padding(.horizontal, 20)

                LegalFooterLinks()
                    .padding(.top, 28)
                    .padding(.bottom, 32)
            }
        }
        .paxScreenBackground()
        .navigationTitle(L10n.AccountAbout)
        .navigationBarTitleDisplayMode(.inline)
    }

    private var aboutHero: some View {
        VStack(spacing: 18) {
            PAXAppMark.image(size: 88)
                .shadow(color: PAXBrand.accent.opacity(0.2), radius: 16, y: 6)

            VStack(spacing: 8) {
                Text("PAXDesign Live Chat")
                    .font(.title2.weight(.bold))

                Text(L10n.AboutTagline)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 24)
            }

            HStack(spacing: 8) {
                aboutPill(L10n.AboutBuiltWith, icon: "swift")
                aboutPill(PAXAppInfo.fullVersion, icon: "number")
            }
        }
    }

    private func aboutPill(_ text: String, icon: String) -> some View {
        HStack(spacing: 5) {
            Image(systemName: icon)
                .font(.caption2.weight(.semibold))
            Text(text)
                .font(.caption.weight(.semibold))
        }
        .foregroundStyle(PAXBrand.accent)
        .padding(.horizontal, 12)
        .padding(.vertical, 6)
        .background(Capsule().fill(PAXBrand.accent.opacity(0.12)))
    }

    private var aboutInfoCard: some View {
        VStack(spacing: 0) {
            aboutRow("Version", PAXAppInfo.fullVersion, showDivider: true)
            aboutRow("Website", "https://paxdesign.at", showDivider: true)
            aboutRow("Hersteller", "PAXdesign / PrimoJob GmbH", showDivider: true)
            aboutRow("Sicherheit", "HTTPS/TLS, iOS-Schlüsselbund", showDivider: false)
        }
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.82))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.44), lineWidth: 1)
                )
        )
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
    }

    private var featureHighlights: some View {
        VStack(alignment: .leading, spacing: 14) {
            featureRow(icon: "bell.badge", title: "Push-Benachrichtigungen", subtitle: "Live-Anfragen in Echtzeit")
            featureRow(icon: "person.wave.2", title: "Live-Agent-Übernahme", subtitle: "Nahtloser Kundensupport")
            featureRow(icon: "sparkles", title: "KI-Assistent", subtitle: "Intelligente Antwortvorschläge")
            featureRow(icon: "lock.shield", title: "Enterprise-Sicherheit", subtitle: "Verschlüsselte Verbindung")
        }
        .padding(18)
        .paxGlassCardStyle(cornerRadius: 16, fillOpacity: 0.78, borderOpacity: 0.42, shadowOpacity: 0.12)
    }

    private func featureRow(icon: String, title: String, subtitle: String) -> some View {
        HStack(spacing: 14) {
            Image(systemName: icon)
                .font(.body.weight(.medium))
                .foregroundStyle(PAXBrand.accent)
                .frame(width: 32, height: 32)
                .background(Circle().fill(PAXBrand.accent.opacity(0.12)))

            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.subheadline.weight(.semibold))
                Text(subtitle)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
    }

    private func aboutRow(_ title: String, _ value: String, showDivider: Bool) -> some View {
        VStack(spacing: 0) {
            HStack(alignment: .center) {
                Text(title)
                    .font(.subheadline.weight(.medium))
                Spacer()
                Text(value)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.trailing)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 14)

            if showDivider {
                Divider()
                    .padding(.leading, 16)
            }
        }
    }
}

struct HelpView: View {
    var body: some View {
        LegalDocumentView(title: "Hilfe", sections: [
            ("Über diese App", "PAXDesign Live Chat ist die offizielle iOS-App für autorisierte Mitarbeiter. Sie verbindet sich sicher per HTTPS mit Ihrem WordPress-Backend und spiegelt die Funktionen des Browser-Live-Admin-Panels — optimiert für unterwegs und mit Push-Benachrichtigungen."),
            ("Anmeldung & Konto", "Geben Sie die HTTPS-URL Ihrer Website, Ihren WordPress-Benutzernamen (oder E-Mail) und ein Application Password ein. Das Passwort erstellen Sie in WordPress unter Benutzer → Profil. Nach erfolgreicher Anmeldung bleiben die Zugangsdaten verschlüsselt im iOS-Schlüsselbund."),
            ("Live-Anfragen", "Wenn ein Kunde einen Live-Agenten anfordert, erscheint ein Premium-Banner oben in der App sowie ein Vollbild-Alarm mit Klingelton. Wechseln Sie zum Live-Tab, um alle offenen Anfragen zu sehen. Übernehmen oder lehnen Sie Anfragen ab — übernommene Chats öffnen sich automatisch."),
            ("Chats & Nachrichten", "Im Chats-Tab sehen Sie alle aktiven Gespräche. Nutzen Sie Suche und Filter (Alle, Live, Offen, Aktiv, Geschlossen). Ungelesene Kundennachrichten werden mit einem Badge markiert. Lang drücken auf eine Nachricht: Kopieren, Teilen oder Antworten."),
            ("Bilder senden", "Tippen Sie auf das Foto-Symbol im Composer, um ein Bild aus der Mediathek zu senden. Bilder werden vor dem Upload optimiert und im Chat als Vorschaubild angezeigt. Tippen Sie auf ein Bild für die Vollbild-Ansicht."),
            ("KI-Assistent & Schnellantworten", "Nach Kundennachrichten erscheinen KI-Vorschläge und Schnellantworten oberhalb des Eingabefelds. Antippen übernimmt den Text — es wird nichts automatisch gesendet. Berechtigungen können vom Hauptadministrator eingeschränkt werden."),
            ("Benachrichtigungen & Töne", "Push-Benachrichtigungen informieren über Live-Anfragen und neue Nachrichten. In den Einstellungen können Klingelton, Sendeton, Tippgeräusch und Lautstärke angepasst werden."),
            ("Team & Berechtigungen", "Der Hauptadministrator verwaltet Mitarbeiter-Zugang und Berechtigungen (Chats ansehen, antworten, KI, Bilder, Einstellungen, Bewertungen, Team-Verwaltung, Sicherheit). Unter Konto → Team & Berechtigungen oder im WordPress-Admin."),
            ("Datenschutz & Sicherheit", "Alle Kommunikation erfolgt über TLS (HTTPS). Es gibt keine Ende-zu-Ende-Verschlüsselung — Nachrichten werden serverseitig für den Support gespeichert. E-Mail-Adressen werden in der App teilweise maskiert angezeigt. Details unter Konto → Sicherheit und Datenschutz."),
            ("Best Practices für Admins", "Melden Sie sich auf gemeinsam genutzten Geräten ab. Widerrufen Sie Application Passwords bei Verlust. Antworten Sie zeitnah auf Live-Anfragen. Nutzen Sie die KI-Vorschläge als Hilfe, prüfen Sie Inhalte vor dem Senden. Halten Sie Plugin und App aktuell."),
            ("Support", "Technische Fragen: https://paxdesign.at — Plugin-Updates werden automatisch über GitHub Releases bereitgestellt."),
        ])
    }
}
