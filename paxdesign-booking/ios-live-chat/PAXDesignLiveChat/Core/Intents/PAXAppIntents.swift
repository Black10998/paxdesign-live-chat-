#if !SIDELOAD
import AppIntents

@available(iOS 16.0, *)
struct OpenDashboardIntent: AppIntent {
    static var title: LocalizedStringResource = "intent.open_dashboard"
    static var openAppWhenRun = true

    func perform() async throws -> some IntentResult {
        NotificationCenter.default.post(name: .paxQuickAction, object: QuickActionsManager.openDashboard)
        return .result()
    }
}

@available(iOS 16.0, *)
struct OpenLiveRequestsIntent: AppIntent {
    static var title: LocalizedStringResource = "intent.open_live_requests"
    static var openAppWhenRun = true

    func perform() async throws -> some IntentResult {
        NotificationCenter.default.post(name: .paxQuickAction, object: QuickActionsManager.openLive)
        return .result()
    }
}

@available(iOS 16.0, *)
struct SearchLiveChatIntent: AppIntent {
    static var title: LocalizedStringResource = "intent.search_live_chat"
    static var openAppWhenRun = true

    @Parameter(title: "intent.query_parameter")
    var query: String?

    func perform() async throws -> some IntentResult {
        NotificationCenter.default.post(
            name: .paxQuickAction,
            object: QuickActionsManager.openSearch,
            userInfo: ["query": query ?? ""]
        )
        return .result()
    }
}

@available(iOS 16.0, *)
struct PAXLiveChatShortcuts: AppShortcutsProvider {
    static var appShortcuts: [AppShortcut] {
        AppShortcut(
            intent: OpenDashboardIntent(),
            phrases: ["Open \(.applicationName) dashboard", "Show business overview in \(.applicationName)"],
            shortTitle: "intent.short_dashboard",
            systemImageName: "house.fill"
        )
        AppShortcut(
            intent: OpenLiveRequestsIntent(),
            phrases: ["Open live requests in \(.applicationName)", "Show waiting customers in \(.applicationName)"],
            shortTitle: "intent.short_live_requests",
            systemImageName: "bell.and.waves.left.and.right.fill"
        )
        AppShortcut(
            intent: SearchLiveChatIntent(),
            phrases: ["Search \(.applicationName)", "Find chats in \(.applicationName)"],
            shortTitle: "intent.short_search",
            systemImageName: "magnifyingglass"
        )
    }
}
#endif
