import Foundation

/// Shared data bridge for widgets and the main app (App Group when available, UserDefaults fallback).
@MainActor
final class WidgetDataStore {
    static let shared = WidgetDataStore()

    private let appGroupID = "group.at.paxdesign.livechat"
    private let snapshotKey = "pax.widget.snapshot"

    struct Snapshot: Codable {
        var unreadChats: Int
        var liveRequests: Int
        var openTasks: Int
        var upcomingEvents: Int
        var updatedAt: Date
    }

    private var defaults: UserDefaults {
        #if SIDELOAD
        .standard
        #else
        UserDefaults(suiteName: appGroupID) ?? .standard
        #endif
    }

    func syncFromApp() {
        let snapshot = Snapshot(
            unreadChats: unreadChatCount(),
            liveRequests: ChatCoordinatorProxy.liveCount,
            openTasks: TaskStore.shared.openCount,
            upcomingEvents: CalendarStore.shared.upcoming().count,
            updatedAt: Date()
        )
        if let data = try? JSONEncoder().encode(snapshot) {
            defaults.set(data, forKey: snapshotKey)
        }
    }

    func loadSnapshot() -> Snapshot {
        guard let data = defaults.data(forKey: snapshotKey),
              let snapshot = try? JSONDecoder().decode(Snapshot.self, from: data) else {
            return Snapshot(unreadChats: 0, liveRequests: 0, openTasks: 0, upcomingEvents: 0, updatedAt: Date())
        }
        return snapshot
    }

    func resetOnLogout() {
        ChatCoordinatorProxy.liveCount = 0
        ChatCoordinatorProxy.sessions = []
        defaults.removeObject(forKey: snapshotKey)
    }

    private func unreadChatCount() -> Int {
        let settings = AppSettingsStore.shared
        return ChatCoordinatorProxy.sessions.filter {
            !$0.isTeamDM && $0.needsReply && !settings.readSessionIds.contains($0.sessionId)
        }.count
    }
}

/// Lightweight proxy so widget code can compile without full coordinator graph.
enum ChatCoordinatorProxy {
    static var liveCount: Int = 0
    static var sessions: [LiveSession] = []
}
