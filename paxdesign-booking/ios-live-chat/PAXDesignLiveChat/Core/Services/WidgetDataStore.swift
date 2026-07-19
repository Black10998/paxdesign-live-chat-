import Foundation
import WidgetKit

enum WidgetSharedConstants {
    static let appGroupID = "group.at.paxdesign.livechat"
    static let snapshotKey = "pax.widget.snapshot"
    static let widgetKind = "PAXDashboardWidget"
}

/// Shared data bridge for widgets and the main app (App Group when available, UserDefaults fallback).
@MainActor
final class WidgetDataStore {
    static let shared = WidgetDataStore()
    static let widgetKind = WidgetSharedConstants.widgetKind

    private let appGroupID = WidgetSharedConstants.appGroupID
    private let snapshotKey = WidgetSharedConstants.snapshotKey

    struct Snapshot: Codable, Equatable {
        var unreadChats: Int
        var liveRequests: Int
        var openTasks: Int
        var upcomingEvents: Int
        var updatedAt: Date
        var nextEventTitle: String
        var liveHighlight: String
        var isSignedIn: Bool

        init(
            unreadChats: Int,
            liveRequests: Int,
            openTasks: Int,
            upcomingEvents: Int,
            updatedAt: Date,
            nextEventTitle: String = "",
            liveHighlight: String = "",
            isSignedIn: Bool = true
        ) {
            self.unreadChats = unreadChats
            self.liveRequests = liveRequests
            self.openTasks = openTasks
            self.upcomingEvents = upcomingEvents
            self.updatedAt = updatedAt
            self.nextEventTitle = nextEventTitle
            self.liveHighlight = liveHighlight
            self.isSignedIn = isSignedIn
        }
    }

    private var defaults: UserDefaults {
        #if SIDELOAD
        .standard
        #else
        UserDefaults(suiteName: appGroupID) ?? .standard
        #endif
    }

    func syncFromApp() {
        let snapshot = buildSnapshot()
        guard let data = try? JSONEncoder().encode(snapshot) else { return }
        defaults.set(data, forKey: snapshotKey)
        WidgetCenter.shared.reloadTimelines(ofKind: Self.widgetKind)
    }

    func reloadWidgetTimelines() {
        WidgetCenter.shared.reloadTimelines(ofKind: Self.widgetKind)
    }

    func loadSnapshot() -> Snapshot {
        guard let data = defaults.data(forKey: snapshotKey),
              let snapshot = try? JSONDecoder().decode(Snapshot.self, from: data) else {
            return Snapshot(unreadChats: 0, liveRequests: 0, openTasks: 0, upcomingEvents: 0, updatedAt: Date(), isSignedIn: false)
        }
        return snapshot
    }

    func resetOnLogout() {
        ChatCoordinatorProxy.liveCount = 0
        ChatCoordinatorProxy.sessions = []
        let cleared = Snapshot(
            unreadChats: 0,
            liveRequests: 0,
            openTasks: 0,
            upcomingEvents: 0,
            updatedAt: Date(),
            isSignedIn: false
        )
        if let data = try? JSONEncoder().encode(cleared) {
            defaults.set(data, forKey: snapshotKey)
        }
        WidgetCenter.shared.reloadTimelines(ofKind: Self.widgetKind)
    }

    func buildSnapshot() -> Snapshot {
        let platform = PlatformSyncService.shared
        let liveCount = platform.dashboard?.liveCount ?? ChatCoordinatorProxy.liveCount
        let upcoming = CalendarStore.shared.upcoming()
        let topLive = ChatCoordinatorProxy.sessions.first(where: { $0.isLiveRequest })?.displayName ?? ""
        return Snapshot(
            unreadChats: unreadSessionCount(),
            liveRequests: liveCount,
            openTasks: platform.dashboard?.openTasks ?? TaskStore.shared.openCount,
            upcomingEvents: upcoming.count,
            updatedAt: Date(),
            nextEventTitle: upcoming.first?.title ?? "",
            liveHighlight: topLive,
            isSignedIn: AuthStore.shared.isLoggedIn && !AuthStore.shared.isCustomerSession
        )
    }

    func unreadSessionCount() -> Int {
        let settings = AppSettingsStore.shared
        return ChatCoordinatorProxy.sessions
            .filter { !$0.isTeamDM }
            .filter { settings.isSessionUnread($0) }
            .count
    }
}

/// Lightweight proxy so widget code can compile without full coordinator graph.
enum ChatCoordinatorProxy {
    static var liveCount: Int = 0
    static var sessions: [LiveSession] = []
}
