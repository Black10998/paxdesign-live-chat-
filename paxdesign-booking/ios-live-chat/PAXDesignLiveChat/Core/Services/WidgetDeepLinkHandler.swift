import Foundation

enum WidgetDeepLinkHandler {
    static func handle(_ url: URL) -> Bool {
        guard url.scheme?.lowercased() == "paxlivechat" else { return false }

        let host = url.host?.lowercased() ?? ""
        let action: String?
        switch host {
        case "dashboard", "home":
            action = QuickActionsManager.openDashboard
        case "live", "live-requests":
            action = QuickActionsManager.openLive
        case "chats", "messages":
            action = QuickActionsManager.openDashboard
        case "search":
            action = QuickActionsManager.openSearch
        default:
            action = nil
        }

        guard let action else { return false }
        NotificationCenter.default.post(
            name: .paxQuickAction,
            object: action
        )
        return true
    }
}
