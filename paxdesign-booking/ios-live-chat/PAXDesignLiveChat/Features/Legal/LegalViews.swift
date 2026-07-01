import SwiftUI

struct LegalDocumentView: View {
    let title: String
    let sections: [(String, String)]

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
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

struct PrivacyPolicyView: View {
    var body: some View {
        LegalDocumentView(title: "Datenschutz", sections: [
            ("Verantwortlicher", "PAXdesign, https://paxdesign.at\nKontakt: Datenschutzanfragen über die Website."),
            ("Zweck", "Die App dient autorisierten Mitarbeitern zur Bearbeitung von Live-Chat-Anfragen. Es werden nur Daten verarbeitet, die für den Support erforderlich sind."),
            ("Verarbeitete Daten", "Anmeldedaten (WordPress Application Password, lokal im Schlüsselbund), Chat-Inhalte, Metadaten (Zeitstempel, Session-ID), optional Profilbild lokal auf dem Gerät, Push-Token für Benachrichtigungen."),
            ("Speicherung", "Chat-Inhalte werden auf dem WordPress-Server des Auftraggebers gespeichert. Anmeldedaten verbleiben verschlüsselt im iOS-Schlüsselbund."),
            ("Übermittlung", "Alle API-Kommunikation erfolgt über HTTPS (TLS). Es wird keine Ende-zu-Ende-Verschlüsselung zusätzlich zur Transportverschlüsselung eingesetzt."),
            ("Rechtsgrundlage (DSGVO)", "Art. 6 Abs. 1 lit. b (Vertrag/Support) und lit. f (berechtigtes Interesse am Kundenservice)."),
            ("Betroffenenrechte", "Auskunft, Berichtigung, Löschung, Einschränkung, Widerspruch und Datenübertragbarkeit gemäß DSGVO. Anfragen an den Verantwortlichen."),
            ("Aufbewahrung", "Entspricht den Einstellungen des WordPress-Systems des Auftraggebers."),
        ])
    }
}

struct TermsOfServiceView: View {
    var body: some View {
        LegalDocumentView(title: "Nutzungsbedingungen", sections: [
            ("Geltungsbereich", "Diese App ist ausschließlich für autorisierte PAXdesign-Mitarbeiter bestimmt."),
            ("Zugang", "Der Zugang erfordert gültige WordPress-Administrator-Anmeldedaten und ein Application Password."),
            ("Pflichten", "Chat-Inhalte vertraulich behandeln. Keine Weitergabe von Zugangsdaten. Gerät mit Code schützen."),
            ("Verfügbarkeit", "Der Dienst hängt von der Erreichbarkeit des WordPress-Backends ab."),
            ("Haftung", "Die App wird im Rahmen des internen Support-Betriebs bereitgestellt. Schäden durch unsachgemäße Nutzung sind ausgeschlossen, soweit gesetzlich zulässig."),
        ])
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
            VStack(spacing: 20) {
                PAXClockLogo(size: 72)

                Text("PAXDesign Live Chat")
                    .font(.title2.weight(.semibold))

                Text("Native Admin-App für Echtzeit-Kundensupport.")
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.center)

                VStack(spacing: 8) {
                    LabeledContent("App-Version", value: "1.5.0")
                    LabeledContent("Build", value: "13")
                    LabeledContent("Bundle-ID", value: "at.paxdesign.livechat")
                }
                .padding()
                .background(PAXTheme.surface)
                .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
            }
            .padding(24)
        }
        .background(PAXBackground())
        .navigationTitle("Über")
        .navigationBarTitleDisplayMode(.inline)
    }
}

struct HelpView: View {
    var body: some View {
        LegalDocumentView(title: "Hilfe", sections: [
            ("Anmeldung", "Website-URL, WordPress-Benutzername und Application Password eingeben. Das Passwort wird unter Benutzer → Profil in WordPress erstellt."),
            ("Live-Anfragen", "Bei neuen Anfragen erscheint ein Vollbild-Alarm. Übernehmen oder ablehnen."),
            ("Chatten", "Nach Übernahme Nachrichten senden, Bilder anhängen, auf Nachrichten antworten oder kopieren."),
            ("KI-Vorschläge", "Vorschläge antippen, um den Text in das Eingabefeld zu übernehmen — sie werden nicht automatisch gesendet."),
            ("Synchronisation", "Die App aktualisiert Chats automatisch im Hintergrund."),
            ("Support", "Technische Probleme: https://paxdesign.at"),
        ])
    }
}
