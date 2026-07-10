import UIKit
import UserNotifications

/// Keeps the home-screen badge in sync with unread chats and live requests.
@MainActor
enum PAXApplicationBadge {
    static func sync(unreadChats: Int, unreadTeam: Int = 0, liveRequests: Int = 0) {
        let total = max(0, unreadChats + unreadTeam + liveRequests)
        UNUserNotificationCenter.current().setBadgeCount(total) { _ in }
    }

    static func clear() {
        UNUserNotificationCenter.current().setBadgeCount(0) { _ in }
    }
}
