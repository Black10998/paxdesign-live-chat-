import Foundation
import os

enum NetworkCircuitBreakerError: LocalizedError {
    case open(until: Date)
    case rateLimited

    var errorDescription: String? {
        switch self {
        case .open(let until):
            let remaining = max(0, Int(until.timeIntervalSinceNow))
            return String(
                localized: "Network protection is active. Please wait \(remaining)s before trying again.",
                comment: "Shown when edge/WAF blocks requests temporarily"
            )
        case .rateLimited:
            return String(
                localized: "Too many requests. Your message will be sent automatically in a moment.",
                comment: "Client-side burst protection — message stays queued"
            )
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

    /// Hard cap: max counted REST requests per rolling second (reads/coalesced writes exempt).
    var maxRequestsPerSecond = 15
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
        if endpoint.hasPrefix("device-") || endpoint == "devices-list" || endpoint == "push-apns-register" {
            return
        }
        if let until = openUntil, Date() < until {
            isOpen = true
            throw NetworkCircuitBreakerError.open(until: until)
        }
        isOpen = false
        openUntil = nil

        if isExemptFromRateCap(endpoint: endpoint, method: method) {
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

        // WordPress REST rate limits are per-endpoint and short-lived — do not pause all traffic.
        if status == 429 && isApplicationRateLimit(bodySnippet: bodySnippet) {
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

    /// Reads and low-priority housekeeping writes should not compete with user sends.
    private func isExemptFromRateCap(endpoint: String, method: String) -> Bool {
        let verb = method.uppercased()
        if verb == "GET" {
            return endpoint.hasPrefix("suggestions:")
                || endpoint.hasPrefix("poll:")
                || endpoint.hasPrefix("team-poll:")
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
                || endpoint.hasPrefix("team-management-")
                || endpoint.hasPrefix("platform-")
                || endpoint == "staff"
                || endpoint == "quick-replies"
                || endpoint == "quick-links"
        }
        if isUserPriorityWrite(endpoint: endpoint) {
            return true
        }
        return isLowPriorityWrite(endpoint: endpoint)
    }

    private func isUserPriorityWrite(endpoint: String) -> Bool {
        endpoint.hasPrefix("send:")
            || endpoint.hasPrefix("team-send")
            || endpoint == "send-image"
            || endpoint == "send-link"
            || endpoint == "team-broadcast"
    }

    private func isLowPriorityWrite(endpoint: String) -> Bool {
        endpoint == "typing"
            || endpoint == "team-typing"
            || endpoint == "team-read"
            || endpoint == "session-read"
            || endpoint == "team-presence"
            || endpoint == "events-ack"
            || endpoint == "team-open"
            || endpoint == "team-respond"
            || endpoint == "team-pin"
            || endpoint == "team-mute"
            || endpoint == "takeover"
            || endpoint == "decline"
            || endpoint == "close"
            || endpoint == "archive"
            || endpoint == "reopen"
            || endpoint == "release"
    }

    private func isApplicationRateLimit(bodySnippet: String) -> Bool {
        let trimmed = bodySnippet.trimmingCharacters(in: .whitespacesAndNewlines)
        guard trimmed.hasPrefix("{") else { return false }
        return trimmed.contains("\"code\"")
            || trimmed.contains("rest_")
            || trimmed.localizedCaseInsensitiveContains("rate_limit")
    }
}
