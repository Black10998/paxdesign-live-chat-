import Foundation

/// Central refresh cadence — slower when idle, faster when live traffic is active.
@MainActor
enum AppRefreshPolicy {
    static var scenePhase: SceneActivity = .active
    static var hasOpenChat = false
    static var liveRequestCount = 0

    enum SceneActivity {
        case active
        case inactive
        case background
    }

    static var isForeground: Bool { scenePhase == .active }

    /// Session inbox polling.
    static var sessionListInterval: UInt64 {
        guard isForeground else { return 15_000_000_000 }
        if liveRequestCount > 0 { return 800_000_000 }
        if hasOpenChat { return 1_000_000_000 }
        return 2_500_000_000
    }

    /// Open customer chat thread polling.
    static var chatThreadInterval: UInt64 {
        guard isForeground else { return 12_000_000_000 }
        return hasOpenChat ? 250_000_000 : 900_000_000
    }

    /// Team inbox list polling.
    static var teamListInterval: UInt64 {
        guard isForeground else { return 15_000_000_000 }
        return hasOpenChat ? 1_500_000_000 : 3_000_000_000
    }

    /// Open team thread polling.
    static var teamThreadInterval: UInt64 {
        guard isForeground else { return 12_000_000_000 }
        return hasOpenChat ? 300_000_000 : 1_000_000_000
    }

    static func update(scenePhase: SceneActivity) {
        self.scenePhase = scenePhase
    }

    static func update(liveCount: Int, openChat: Bool) {
        liveRequestCount = liveCount
        hasOpenChat = openChat
    }
}
