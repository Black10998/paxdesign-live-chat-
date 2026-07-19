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
        var nextEventTitle: String
        var liveHighlight: String
        var isSignedIn: Bool

        static let preview = Snapshot(
            unreadChats: 2,
            liveRequests: 1,
            openTasks: 3,
            upcomingEvents: 1,
            updatedAt: Date(),
            nextEventTitle: "Client review",
            liveHighlight: "Anna M.",
            isSignedIn: true
        )

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

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            unreadChats = try container.decode(Int.self, forKey: .unreadChats)
            liveRequests = try container.decode(Int.self, forKey: .liveRequests)
            openTasks = try container.decode(Int.self, forKey: .openTasks)
            upcomingEvents = try container.decode(Int.self, forKey: .upcomingEvents)
            updatedAt = try container.decode(Date.self, forKey: .updatedAt)
            nextEventTitle = try container.decodeIfPresent(String.self, forKey: .nextEventTitle) ?? ""
            liveHighlight = try container.decodeIfPresent(String.self, forKey: .liveHighlight) ?? ""
            isSignedIn = try container.decodeIfPresent(Bool.self, forKey: .isSignedIn) ?? true
        }
    }

    static func load() -> Snapshot {
        let defaults = UserDefaults(suiteName: appGroupID) ?? .standard
        guard let data = defaults.data(forKey: snapshotKey),
              let snapshot = try? JSONDecoder().decode(Snapshot.self, from: data) else {
            return Snapshot(
                unreadChats: 0,
                liveRequests: 0,
                openTasks: 0,
                upcomingEvents: 0,
                updatedAt: Date(),
                isSignedIn: false
            )
        }
        return snapshot
    }
}

enum WidgetSharedConstants {
    static let appGroupID = "group.at.paxdesign.livechat"
    static let snapshotKey = "pax.widget.snapshot"
    static let widgetKind = "PAXDashboardWidget"
}
