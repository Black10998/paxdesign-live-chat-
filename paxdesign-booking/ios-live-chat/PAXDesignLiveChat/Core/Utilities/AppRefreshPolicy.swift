import Foundation

/// Central refresh cadence — SSE-first with conservative HTTP fallback.
@MainActor
enum AppRefreshPolicy {
    static var scenePhase: SceneActivity = .active
    static var hasOpenChat = false
    static var liveRequestCount = 0
    /// When true, HTTP polling loops should stay idle (SSE carries updates).
    static var sseHealthy = false

    enum SceneActivity {
        case active
        case inactive
        case background
    }

    static var isForeground: Bool { scenePhase == .active }

    /// Session inbox polling (HTTP fallback when SSE is active).
    static var sessionListInterval: UInt64 {
        if sseHealthy || NetworkCircuitBreaker.shared.pollingSuspended { return 60_000_000_000 }
        guard isForeground else { return 15_000_000_000 }
        if liveRequestCount > 0 { return 2_000_000_000 }
        if hasOpenChat { return 3_000_000_000 }
        return 6_000_000_000
    }

    /// Customer thread poll when SSE stream is healthy.
    static var chatThreadIntervalLive: UInt64 { 5_000_000_000 }

    /// Customer thread poll when SSE is stale or disconnected.
    static var chatThreadIntervalStale: UInt64 { 3_000_000_000 }

    /// Legacy accessor kept for callers that only need idle/open distinction.
    static var chatThreadInterval: UInt64 {
        guard isForeground else { return 12_000_000_000 }
        return hasOpenChat ? chatThreadIntervalLive : 900_000_000
    }

    /// Team inbox list polling.
    static var teamListInterval: UInt64 {
        if sseHealthy || NetworkCircuitBreaker.shared.pollingSuspended { return 60_000_000_000 }
        guard isForeground else { return 15_000_000_000 }
        return hasOpenChat ? 3_000_000_000 : 6_000_000_000
    }

    /// Team thread poll when SSE stream is healthy.
    static var teamThreadIntervalLive: UInt64 { 5_000_000_000 }

    /// Team thread poll when SSE is stale or disconnected.
    static var teamThreadIntervalStale: UInt64 { 3_000_000_000 }

    static var teamThreadInterval: UInt64 {
        guard isForeground else { return 12_000_000_000 }
        return hasOpenChat ? teamThreadIntervalLive : 1_000_000_000
    }

    static func update(scenePhase: SceneActivity) {
        self.scenePhase = scenePhase
    }

    static func update(liveCount: Int, openChat: Bool) {
        liveRequestCount = liveCount
        hasOpenChat = openChat
    }
}
