import Foundation

enum WidgetSnapshotReader {
    static let appGroupID = WidgetSharedConstants.appGroupID
    static let snapshotKey = WidgetSharedConstants.snapshotKey
    static let widgetKind = WidgetSharedConstants.widgetKind

    struct Snapshot: Codable, Equatable {
        var unreadChats: Int
        var liveRequests: Int
        var openTasks: Int
        var upcomingEvents: Int
        var updatedAt: Date

        static let preview = Snapshot(
            unreadChats: 2,
            liveRequests: 1,
            openTasks: 3,
            upcomingEvents: 1,
            updatedAt: Date()
        )
    }

    static func load() -> Snapshot {
        let defaults = UserDefaults(suiteName: appGroupID) ?? .standard
        guard let data = defaults.data(forKey: snapshotKey),
              let snapshot = try? JSONDecoder().decode(Snapshot.self, from: data) else {
            return Snapshot(unreadChats: 0, liveRequests: 0, openTasks: 0, upcomingEvents: 0, updatedAt: Date())
        }
        return snapshot
    }
}

enum WidgetSharedConstants {
    static let appGroupID = "group.at.paxdesign.livechat"
    static let snapshotKey = "pax.widget.snapshot"
    static let widgetKind = "PAXDashboardWidget"
}
