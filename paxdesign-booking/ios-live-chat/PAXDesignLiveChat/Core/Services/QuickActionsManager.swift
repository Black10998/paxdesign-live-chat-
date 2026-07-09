import UIKit

enum QuickActionsManager {
    static let openDashboard = "at.paxdesign.livechat.open-dashboard"
    static let openLive = "at.paxdesign.livechat.open-live"
    static let openSearch = "at.paxdesign.livechat.open-search"
    static let composeTeam = "at.paxdesign.livechat.compose-team"

    static func configure(isLoggedIn: Bool, canViewChats: Bool, canManageUsers: Bool) {
        guard isLoggedIn else {
            UIApplication.shared.shortcutItems = []
            return
        }

        var items: [UIApplicationShortcutItem] = [
            UIApplicationShortcutItem(
                type: openDashboard,
                localizedTitle: L10n.QuickActionDashboard,
                localizedSubtitle: L10n.QuickActionDashboardSubtitle,
                icon: UIApplicationShortcutIcon(systemImageName: "house.fill"),
                userInfo: nil
            ),
            UIApplicationShortcutItem(
                type: openLive,
                localizedTitle: L10n.QuickActionLive,
                localizedSubtitle: L10n.QuickActionLiveSubtitle,
                icon: UIApplicationShortcutIcon(systemImageName: "bell.and.waves.left.and.right.fill"),
                userInfo: nil
            ),
            UIApplicationShortcutItem(
                type: openSearch,
                localizedTitle: L10n.QuickActionSearch,
                localizedSubtitle: L10n.QuickActionSearchSubtitle,
                icon: UIApplicationShortcutIcon(systemImageName: "magnifyingglass"),
                userInfo: nil
            )
        ]

        if canViewChats && canManageUsers {
            items.append(
                UIApplicationShortcutItem(
                    type: composeTeam,
                    localizedTitle: L10n.QuickActionTeam,
                    localizedSubtitle: L10n.QuickActionTeamSubtitle,
                    icon: UIApplicationShortcutIcon(systemImageName: "person.3.fill"),
                    userInfo: nil
                )
            )
        }

        UIApplication.shared.shortcutItems = items
    }
}

extension Notification.Name {
    static let paxQuickAction = Notification.Name("paxQuickAction")
}
