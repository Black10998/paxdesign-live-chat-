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

    /// Customer-session home-screen badge: chat + notifications. Never overwrite with one source alone.
    static func syncCustomerPortal() {
        sync(total: CustomerChatBadgeStore.shared.unreadCount + CustomerNotificationsBadgeStore.shared.unreadCount)
    }
}
