import Foundation
import os

enum NetworkCircuitBreakerError: LocalizedError {
    case open(until: Date)
    case rateLimited

    var errorDescription: String? {
        switch self {
        case .open(let until):
            let remaining = max(0, Int(until.timeIntervalSinceNow))
            return "Netzwerk-Schutz aktiv. Warte \(remaining)s vor neuen Anfragen."
        case .rateLimited:
            return "Zu viele Anfragen pro Sekunde."
        }
    }
}

/// Global client-side protection against edge/WAF rate limits.
@MainActor
final class NetworkCircuitBreaker {
    static let shared = NetworkCircuitBreaker()

    private let log = Logger(subsystem: "at.paxdesign.livechat", category: "CircuitBreaker")

    private(set) var isOpen = false
    private(set) var openUntil: Date?
    private(set) var lastTripReason = ""
    private(set) var sseReconnectDelayNs: UInt64 = 1_000_000_000

    private var requestTimestamps: [Date] = []
    private var inflightCounts: [String: Int] = [:]
    private var consecutiveRateLimitHits = 0

    /// Hard cap: max REST requests per rolling second.
    var maxRequestsPerSecond = 8
    /// Minimum pause after first edge 403/429.
    var minOpenDuration: TimeInterval = 300
    var maxOpenDuration: TimeInterval = 600

    private init() {}

    func reset() {
        isOpen = false
        openUntil = nil
        lastTripReason = ""
        requestTimestamps = []
        inflightCounts = [:]
        consecutiveRateLimitHits = 0
        sseReconnectDelayNs = 1_000_000_000
    }

    var pollingSuspended: Bool {
        isOpen || AppRefreshPolicy.sseHealthy
    }

    func recordRequestStart(endpoint: String, method: String = "GET") throws {
        if endpoint.hasPrefix("device-") || endpoint == "devices-list" {
            return
        }
        if let until = openUntil, Date() < until {
            isOpen = true
            throw NetworkCircuitBreakerError.open(until: until)
        }
        isOpen = false
        openUntil = nil

        let isCoalescedRead = method.uppercased() == "GET"
            && (endpoint.hasPrefix("suggestions:")
                || endpoint.hasPrefix("poll:")
                || endpoint == "me"
                || endpoint == "team-contacts"
                || endpoint == "team-sessions"
                || endpoint == "conversations-sync"
                || endpoint == "sessions"
                || endpoint == "team-management-overview"
                || endpoint == "team-management-members"
                || endpoint == "team-management-pending"
                || endpoint == "team-management-policy"
                || endpoint == "team-pending-requests"
                || endpoint.hasPrefix("team-management-"))

        if isCoalescedRead {
            inflightCounts[endpoint, default: 0] += 1
            return
        }

        let now = Date()
        requestTimestamps.removeAll { now.timeIntervalSince($0) > 1.0 }
        if requestTimestamps.count >= maxRequestsPerSecond {
            log.warning("rate cap hit endpoint=\(endpoint, privacy: .public) rps=\(self.requestTimestamps.count)")
            throw NetworkCircuitBreakerError.rateLimited
        }
        requestTimestamps.append(now)
        inflightCounts[endpoint, default: 0] += 1
    }

    func recordRequestEnd(endpoint: String) {
        let remaining = (inflightCounts[endpoint] ?? 0) - 1
        if remaining <= 0 {
            inflightCounts.removeValue(forKey: endpoint)
        } else {
            inflightCounts[endpoint] = remaining
        }
    }

    func recordSuccess() {
        consecutiveRateLimitHits = 0
        sseReconnectDelayNs = 1_000_000_000
    }

    func recordHTTPResponse(status: Int, bodySnippet: String, endpoint: String, retryAfter: String?) {
        guard status == 403 || status == 429 else {
            if (200..<400).contains(status) {
                recordSuccess()
            }
            return
        }

        let isEdgeBlock = bodySnippet.localizedCaseInsensitiveContains("Access to this resource on the server is denied")
            || (!bodySnippet.localizedCaseInsensitiveContains("rest_") && status == 403)

        consecutiveRateLimitHits += 1
        var pause = minOpenDuration * pow(2.0, Double(min(consecutiveRateLimitHits - 1, 3)))
        pause = min(pause, maxOpenDuration)

        if let retryAfter, let seconds = TimeInterval(retryAfter.trimmingCharacters(in: .whitespaces)), seconds > 0 {
            pause = max(pause, seconds)
        }

        let until = Date().addingTimeInterval(pause)
        openUntil = until
        isOpen = true
        lastTripReason = "HTTP \(status) edge=\(isEdgeBlock) endpoint=\(endpoint)"
        sseReconnectDelayNs = min(UInt64(pause * 1_000_000_000), 120_000_000_000)

        log.error("circuit OPEN until=\(until, privacy: .public) reason=\(self.lastTripReason, privacy: .public)")
    }

    func nextSSEReconnectDelayNs() -> UInt64 {
        if isOpen, let until = openUntil {
            let remaining = max(0, until.timeIntervalSinceNow)
            return UInt64(remaining * 1_000_000_000)
        }
        let delay = sseReconnectDelayNs
        sseReconnectDelayNs = min(sseReconnectDelayNs * 2, 60_000_000_000)
        return delay
    }
}
