import UIKit
import UserNotifications

/// Keeps the home-screen badge in sync with unread message counts.
@MainActor
enum PAXApplicationBadge {
    static func sync(total: Int) {
        let count = max(0, total)
        UNUserNotificationCenter.current().setBadgeCount(count) { _ in }
        UIApplication.shared.applicationIconBadgeNumber = count
    }

    static func clear() {
        UNUserNotificationCenter.current().setBadgeCount(0) { _ in }
        UIApplication.shared.applicationIconBadgeNumber = 0
    }
}
