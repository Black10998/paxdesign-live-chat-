import Foundation
import os

/// Tracks REST volume for diagnostics and regression tests.
@MainActor
final class NetworkRequestTracker {
    static let shared = NetworkRequestTracker()

    private let log = Logger(subsystem: "at.paxdesign.livechat", category: "Network")
    private var counts: [String: Int] = [:]
    private var windowStart = Date()

    private init() {}

    func record(endpoint: String) {
        counts[endpoint, default: 0] += 1
    }

    func reset() {
        counts = [:]
        windowStart = Date()
    }

    var totalInWindow: Int {
        counts.values.reduce(0, +)
    }

    var requestsPerSecond: Double {
        let elapsed = max(Date().timeIntervalSince(windowStart), 0.001)
        return Double(totalInWindow) / elapsed
    }

    func snapshot() -> [String: Int] {
        counts
    }

    func logSummary(label: String) {
        let total = totalInWindow
        let rps = requestsPerSecond
        log.info("network \(label, privacy: .public) total=\(total) rps=\(rps, privacy: .public)")
    }
}
