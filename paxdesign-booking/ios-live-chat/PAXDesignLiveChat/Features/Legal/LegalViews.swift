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
                        Text("Online")
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
                                .background(PAXTheme.surface)
                                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
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
                }
            }
            .padding(20)
        }
        .background(PAXBackground())
        .navigationTitle(title)
        .navigationBarTitleDisplayMode(.inline)
    }
}

struct SecurityView: View {
    var body: some View {
        LegalDocumentView(title: "Sicherheit", sections: [
            ("Transportverschlüsselung", "Alle API-Anfragen erfolgen ausschließlich über HTTPS (TLS). Ungesicherte HTTP-Verbindungen werden von der App abgelehnt."),
            ("Anmeldedaten", "Das Application Password wird im iOS-Schlüsselbund gespeichert (kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly) und verlässt das Gerät nicht im Klartext."),
            ("Geräteschutz", "Nutzen Sie Face ID, Touch ID oder einen Gerätecode. Die App speichert keine zusätzlichen Passwörter außerhalb des Schlüsselbundes."),
            ("Kein Tracking", "Die App enthält keine Werbe-SDKs, kein Cross-App-Tracking und keine Third-Party-Analytics."),
            ("Keine Ende-zu-Ende-Verschlüsselung", "Eine zusätzliche Ende-zu-Ende-Verschlüsselung ist nicht implementiert. Nachrichten werden serverseitig für den Support-Workflow gespeichert — analog zum Browser-Admin-Panel."),
            ("Empfehlung", "Halten Sie iOS aktuell, melden Sie sich auf gemeinsam genutzten Geräten ab und widerrufen Sie Application Passwords in WordPress bei Verlust des Geräts."),
        ])
    }
}

struct PrivacyPolicyView: View {
    var body: some View {
        LegalDocumentView(
            title: "Datenschutz",
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
            title: "Nutzungsbedingungen",
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
            VStack(spacing: 22) {
                PAXClockLogo(size: 72)

                Text("PAXDesign Live Chat")
                    .font(.title2.weight(.semibold))

                Text("Die offizielle native iOS-Administrations-App für das PAXdesign Live-Chat-System. Entwickelt für autorisierte Support-Mitarbeiter — nicht für Endkunden.")
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.center)

                VStack(alignment: .leading, spacing: 12) {
                    aboutRow("Version", PAXAppInfo.fullVersion)
                    aboutRow("Website", "https://paxdesign.at")
                    aboutRow("Hersteller", "PAXdesign / PrimoJob GmbH")
                    aboutRow("Technologie", "100 % native SwiftUI-App")
                    aboutRow("Sicherheit", "HTTPS/TLS, iOS-Schlüsselbund")
                }
                .padding()
                .background(PAXTheme.surface)
                .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))

                Text("Diese App ist das offizielle mobile Admin-Panel für Echtzeit-Kundensupport — mit Push-Benachrichtigungen, Live-Agent-Übernahme, KI-Vorschlägen und voller Parität zum Browser-Dashboard.")
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.center)

                LegalFooterLinks()
            }
            .padding(24)
        }
        .background(PAXBackground())
        .navigationTitle("Über")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func aboutRow(_ title: String, _ value: String) -> some View {
        HStack(alignment: .top) {
            Text(title)
                .font(.subheadline.weight(.semibold))
            Spacer()
            Text(value)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.trailing)
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
