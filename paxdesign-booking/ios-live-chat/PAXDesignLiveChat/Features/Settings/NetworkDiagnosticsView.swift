import SwiftUI

/// Live REST volume readout for 403 regression testing (Build 99+).
struct NetworkDiagnosticsView: View {
    @State private var snapshot: [String: Int] = [:]
    @State private var total = 0
    @State private var rps: Double = 0
    @State private var circuitOpen = false
    @State private var circuitReason = ""
    @State private var sseHealthy = false

    private let timer = Timer.publish(every: 2, on: .main, in: .common).autoconnect()

    var body: some View {
        List {
            Section("Übersicht") {
                LabeledContent("REST gesamt", value: "\(total)")
                LabeledContent("REST/s", value: String(format: "%.2f", rps))
                LabeledContent("SSE aktiv", value: sseHealthy ? "Ja" : "Nein")
                LabeledContent("Circuit Breaker", value: circuitOpen ? "OFFEN" : "Geschlossen")
                if circuitOpen, !circuitReason.isEmpty {
                    Text(circuitReason)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.danger)
                }
            }
            Section("Endpunkte") {
                if snapshot.isEmpty {
                    Text("Noch keine Anfragen in dieser Sitzung.")
                        .foregroundStyle(PAXTheme.textSecondary)
                } else {
                    ForEach(snapshot.sorted(by: { $0.value > $1.value }), id: \.key) { key, count in
                        LabeledContent(key, value: "\(count)")
                    }
                }
            }
            Section {
                Button("Zähler zurücksetzen") {
                    NetworkRequestTracker.shared.reset()
                    NetworkCircuitBreaker.shared.reset()
                    refresh()
                }
            }
        }
        .navigationTitle("Netzwerk")
        .onAppear { refresh() }
        .onReceive(timer) { _ in refresh() }
    }

    private func refresh() {
        snapshot = NetworkRequestTracker.shared.snapshot()
        total = NetworkRequestTracker.shared.totalInWindow
        rps = NetworkRequestTracker.shared.requestsPerSecond
        sseHealthy = AppRefreshPolicy.sseHealthy
        circuitOpen = NetworkCircuitBreaker.shared.isOpen
        circuitReason = NetworkCircuitBreaker.shared.lastTripReason
    }
}
