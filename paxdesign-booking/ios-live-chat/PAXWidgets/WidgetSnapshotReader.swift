import Foundation

enum WidgetSnapshotReader {
    private static let appGroupID = "group.at.paxdesign.livechat"
    private static let snapshotKey = "pax.widget.snapshot"

    struct Snapshot: Codable {
        var unreadChats: Int
        var liveRequests: Int
        var openTasks: Int
        var upcomingEvents: Int
        var updatedAt: Date
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
