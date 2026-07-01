import SwiftUI

struct SyncDebugView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @ObservedObject private var debug = SyncDebugStore.shared
    @State private var parity: DebugParityResponse?
    @State private var parityError: String?
    @State private var diagnosis = "—"
    @State private var copied = false

    var body: some View {
        List {
            Section("Diagnose") {
                Text(diagnosis)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(diagnosisColor)
            }

            Section("Laufzeit") {
                debugRow("Umgebung", debug.runtimeEnvironment)
                debugRow("Bundle-Pfad", Bundle.main.bundlePath)
            }

            Section("API / Konto") {
                debugRow("API Base URL", debug.apiBaseURL)
                debugRow("Angemeldeter Admin", debug.loggedInUser)
                debugRow("Plugin-Version", debug.pluginVersion)
            }

            Section("Sync Engine") {
                debugRow("Status", debug.syncEngineStatus)
                debugRow("Listen-Polling aktiv", debug.listPollLoopActive ? "ja" : "nein")
                debugRow("Nachrichten-Polling aktiv", debug.messagePollLoopActive ? "ja" : "nein")
                debugRow("Letzter Listen-Poll", format(date: debug.lastListPollAt))
                debugRow("Letzter Nachrichten-Poll", format(date: debug.lastMessagePollAt))
                debugRow("Letzter erfolgreicher Listen-Sync", format(date: debug.lastSuccessfulListSyncAt))
            }

            Section("Letzter HTTP Request") {
                debugRow("Endpoint", debug.lastEndpoint)
                debugRow("HTTP Status", debug.lastHTTPStatus.map(String.init) ?? "—")
                debugRow("URL", debug.lastRequestURL)
            }

            Section("Session-Liste: API vs Anzeige") {
                debugRow("API session_count (raw JSON)", debug.apiRawSessionCount.map(String.init) ?? "—")
                debugRow("API live_count (raw JSON)", debug.apiLiveCount.map(String.init) ?? "—")
                debugRow("Angezeigte Sessions", String(debug.displayedSessionCount))
                debugRow("Neueste Session-ID (API)", debug.latestSessionIdFromAPI)
                debugRow("Shortcode-Parität session_count", parity.map { String($0.sessionCount) } ?? "—")
                debugRow("Shortcode-Parität latest_session_id", parity?.latestSessionId ?? parityError ?? "—")
            }

            Section("Ausgewählte Session / Nachrichten") {
                debugRow("Ausgewählte Session-ID", debug.selectedSessionId)
                debugRow("API message_count (raw JSON)", debug.apiRawMessageCount.map(String.init) ?? "—")
                debugRow("Angezeigte Nachrichten", String(debug.displayedMessageCount))
            }

            Section("Fehler") {
                debugRow("Letzter Sync-Fehler", debug.lastSyncError)
                debugRow("Letzter Decode-Fehler", debug.lastDecodeError)
            }

            Section("Raw Body Preview") {
                Text(debug.lastRawBodyPreview)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .textSelection(.enabled)
            }

            Section {
                Button("Jetzt Listen-Sync erzwingen") {
                    Task { await forceListSync() }
                }
                Button("Shortcode-Parität abrufen") {
                    Task { await fetchParity() }
                }
                Button(copied ? "Kopiert" : "Diagnose kopieren") {
                    copyDiagnostics()
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle("Sync Debug")
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            refreshStaticFields()
            updateDiagnosis()
        }
        .onChange(of: debug.apiRawSessionCount) { _ in updateDiagnosis() }
        .onChange(of: debug.displayedSessionCount) { _ in updateDiagnosis() }
        .onChange(of: debug.lastDecodeError) { _ in updateDiagnosis() }
        .onChange(of: debug.lastHTTPStatus) { _ in updateDiagnosis() }
        .onChange(of: coordinator.sessions.count) { _ in
            debug.recordListDisplayed(count: coordinator.sessions.count, sessions: coordinator.sessions)
            updateDiagnosis()
        }
    }

    private var diagnosisColor: Color {
        if diagnosis.contains("iOS APP") { return .orange }
        if diagnosis.contains("BACKEND") { return .red }
        if diagnosis.contains("OK") { return .green }
        return PAXTheme.textPrimary
    }

    private func debugRow(_ title: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.caption)
                .foregroundStyle(PAXTheme.textTertiary)
            Text(value)
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textPrimary)
                .textSelection(.enabled)
        }
        .padding(.vertical, 2)
    }

    private func format(date: Date?) -> String {
        guard let date else { return "—" }
        return date.formatted(date: .omitted, time: .standard)
    }

    private func refreshStaticFields() {
        debug.apiBaseURL = auth.api?.publicApiBaseURL ?? auth.siteURLString + "/wp-json/paxdesign/v1/live-admin/"
        debug.loggedInUser = auth.profile?.name ?? auth.username
        debug.runtimeEnvironment = RuntimeEnvironment.detect()
        debug.recordListDisplayed(count: coordinator.sessions.count, sessions: coordinator.sessions)
    }

    private func forceListSync() async {
        await coordinator.refreshSessions(auth: auth)
        debug.recordListDisplayed(count: coordinator.sessions.count, sessions: coordinator.sessions)
        await fetchParity()
        updateDiagnosis()
    }

    private func fetchParity() async {
        guard let api = auth.api else {
            parityError = "Kein API-Client"
            return
        }
        do {
            parity = try await api.fetchDebugParity()
            parityError = nil
        } catch {
            parity = nil
            parityError = error.localizedDescription
        }
        updateDiagnosis()
    }

    private func updateDiagnosis() {
        if let status = debug.lastHTTPStatus, !(200...299).contains(status) {
            diagnosis = "BACKEND/API: HTTP \(status) — REST liefert keinen gültigen Erfolg."
            return
        }
        if debug.lastDecodeError != "—" && !debug.lastDecodeError.isEmpty {
            diagnosis = "iOS APP: Decode-Fehler — API-Antwort kommt an, Swift-Model scheitert."
            return
        }
        if let apiCount = debug.apiRawSessionCount {
            if apiCount != debug.displayedSessionCount {
                diagnosis = "iOS APP: API liefert \(apiCount) Sessions, UI zeigt \(debug.displayedSessionCount)."
                return
            }
            if let parity, parity.sessionCount != apiCount {
                diagnosis = "BACKEND/API: Parität (\(parity.sessionCount)) ≠ REST sessions (\(apiCount))."
                return
            }
            diagnosis = "OK: REST und UI stimmen überein (\(apiCount) Sessions)."
            return
        }
        if debug.syncEngineStatus == "stopped" {
            diagnosis = "iOS APP: Sync-Engine läuft nicht (Polling gestoppt)."
            return
        }
        diagnosis = "Warte auf ersten erfolgreichen Listen-Poll…"
    }

    private func copyDiagnostics() {
        let text = """
        runtime=\(debug.runtimeEnvironment)
        api_base=\(debug.apiBaseURL)
        admin=\(debug.loggedInUser)
        sync_status=\(debug.syncEngineStatus)
        http_status=\(debug.lastHTTPStatus.map(String.init) ?? "—")
        endpoint=\(debug.lastEndpoint)
        api_sessions=\(debug.apiRawSessionCount.map(String.init) ?? "—")
        ui_sessions=\(debug.displayedSessionCount)
        latest_session=\(debug.latestSessionIdFromAPI)
        api_messages=\(debug.apiRawMessageCount.map(String.init) ?? "—")
        ui_messages=\(debug.displayedMessageCount)
        decode_error=\(debug.lastDecodeError)
        sync_error=\(debug.lastSyncError)
        diagnosis=\(diagnosis)
        """
        UIPasteboard.general.string = text
        copied = true
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.5) {
            copied = false
        }
    }
}
